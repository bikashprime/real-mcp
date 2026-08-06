<?php
/**
 * Tool: seo_site_audit — Run a comprehensive SEO audit of the site.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\SEO;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteAudit extends AbstractTool {

	public function get_capability(): string {
		return 'manage_options';
	}

	public function get_category(): string {
		return 'seo';
	}

	public function get_definition(): array {
		return [
			'name'        => 'seo_site_audit',
			'description' => 'Run a site-wide SEO audit checking meta data, content quality, internal linking, and common issues across all published posts.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'limit' => [
						'type'        => 'integer',
						'description' => 'Number of posts to audit (max 100).',
						'default'     => 50,
					],
				],
			],
		];
	}

	public function execute( array $arguments ): array {
		$limit = min( (int) ( $arguments['limit'] ?? 50 ), 100 );

		$posts = get_posts( [
			'post_type'      => [ 'post', 'page' ],
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );

		$issues        = [];
		$missing_meta  = 0;
		$missing_image = 0;
		$short_content = 0;
		$no_links      = 0;

		foreach ( $posts as $post ) {
			$post_issues = [];

			// Check meta description.
			$meta_desc = get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true )
				?: get_post_meta( $post->ID, 'rank_math_description', true )
				?: get_post_meta( $post->ID, '_aioseo_description', true )
				?: '';

			if ( empty( $meta_desc ) ) {
				$post_issues[] = 'missing_meta_description';
				$missing_meta++;
			}

			// Check featured image.
			if ( ! has_post_thumbnail( $post->ID ) ) {
				$post_issues[] = 'missing_featured_image';
				$missing_image++;
			}

			// Check content length.
			$word_count = str_word_count( wp_strip_all_tags( $post->post_content ) );
			if ( $word_count < 300 ) {
				$post_issues[] = 'short_content';
				$short_content++;
			}

			// Check internal links.
			$has_internal = preg_match( '/href=["\']' . preg_quote( home_url(), '/' ) . '/i', $post->post_content );
			if ( ! $has_internal ) {
				$post_issues[] = 'no_internal_links';
				$no_links++;
			}

			if ( ! empty( $post_issues ) ) {
				$issues[] = [
					'id'     => $post->ID,
					'title'  => $post->post_title,
					'url'    => get_permalink( $post->ID ),
					'type'   => $post->post_type,
					'issues' => $post_issues,
				];
			}
		}

		$total    = count( $posts );
		$healthy  = $total - count( $issues );
		$score    = $total > 0 ? round( ( $healthy / $total ) * 100 ) : 0;

		return $this->json_response( [
			'posts_audited'          => $total,
			'posts_with_issues'      => count( $issues ),
			'overall_score'          => $score,
			'summary'                => [
				'missing_meta_description' => $missing_meta,
				'missing_featured_image'   => $missing_image,
				'short_content'            => $short_content,
				'no_internal_links'        => $no_links,
			],
			'issues' => array_slice( $issues, 0, 20 ),
		] );
	}
}
