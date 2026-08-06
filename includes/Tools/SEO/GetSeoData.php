<?php
/**
 * Tool: get_seo_data — Retrieve SEO metadata for a post or page.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\SEO;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GetSeoData extends AbstractTool {

	public function get_capability(): string {
		return 'edit_posts';
	}

	public function get_category(): string {
		return 'seo';
	}

	public function get_definition(): array {
		return [
			'name'        => 'get_seo_data',
			'description' => 'Get SEO metadata for a post/page including meta title, description, Open Graph data, and analysis of missing SEO elements.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'id' => [
						'type'        => 'integer',
						'description' => 'Post or page ID.',
					],
				],
				'required' => [ 'id' ],
			],
		];
	}

	public function execute( array $arguments ): array {
		$post_id = (int) $arguments['id'];
		$post    = get_post( $post_id );

		if ( ! $post ) {
			throw new \InvalidArgumentException( 'Post not found.' );
		}

		// Gather SEO data from common SEO plugins (Yoast, Rank Math, All in One SEO).
		$meta_title = get_post_meta( $post_id, '_yoast_wpseo_title', true )
			?: get_post_meta( $post_id, 'rank_math_title', true )
			?: get_post_meta( $post_id, '_aioseo_title', true )
			?: '';

		$meta_desc = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true )
			?: get_post_meta( $post_id, 'rank_math_description', true )
			?: get_post_meta( $post_id, '_aioseo_description', true )
			?: '';

		$focus_keyword = get_post_meta( $post_id, '_yoast_wpseo_focuskw', true )
			?: get_post_meta( $post_id, 'rank_math_focus_keyword', true )
			?: '';

		$og_title = get_post_meta( $post_id, '_yoast_wpseo_opengraph-title', true )
			?: get_post_meta( $post_id, 'rank_math_facebook_title', true )
			?: '';

		$og_desc = get_post_meta( $post_id, '_yoast_wpseo_opengraph-description', true )
			?: get_post_meta( $post_id, 'rank_math_facebook_description', true )
			?: '';

		// Analyze missing elements.
		$issues = [];
		if ( empty( $meta_title ) ) {
			$issues[] = 'Missing meta title';
		}
		if ( empty( $meta_desc ) ) {
			$issues[] = 'Missing meta description';
		}
		if ( empty( $focus_keyword ) ) {
			$issues[] = 'No focus keyword set';
		}
		if ( ! has_post_thumbnail( $post_id ) ) {
			$issues[] = 'No featured image';
		}

		$content = wp_strip_all_tags( $post->post_content );
		$word_count = str_word_count( $content );
		if ( $word_count < 300 ) {
			$issues[] = "Content too short ({$word_count} words, recommend 300+)";
		}

		// Check for internal links.
		$internal_links = preg_match_all( '/href=["\']' . preg_quote( home_url(), '/' ) . '/i', $post->post_content );
		if ( $internal_links === 0 ) {
			$issues[] = 'No internal links found';
		}

		return $this->json_response( [
			'post_id'       => $post_id,
			'title'         => $post->post_title,
			'url'           => get_permalink( $post_id ),
			'meta_title'    => $meta_title,
			'meta_desc'     => $meta_desc,
			'focus_keyword' => $focus_keyword,
			'og_title'      => $og_title,
			'og_description'=> $og_desc,
			'word_count'    => $word_count,
			'has_thumbnail' => has_post_thumbnail( $post_id ),
			'internal_links'=> $internal_links,
			'issues'        => $issues,
			'score'         => max( 0, 100 - ( count( $issues ) * 15 ) ),
		] );
	}
}
