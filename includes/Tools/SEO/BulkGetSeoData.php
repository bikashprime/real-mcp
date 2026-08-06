<?php
/**
 * Tool: bulk_get_seo_data — Get SEO data for multiple posts at once.
 *
 * Essential for AI agents doing site-wide SEO fixes — allows the agent
 * to understand multiple posts' SEO state in a single call.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\SEO;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BulkGetSeoData extends AbstractTool {

	public function get_capability(): string {
		return 'edit_posts';
	}

	public function get_category(): string {
		return 'seo';
	}

	public function get_definition(): array {
		return [
			'name'        => 'bulk_get_seo_data',
			'description' => 'Get SEO metadata, scores, and issues for multiple posts at once. Returns focus keyword, meta title/description status, content length, and a list of issues per post. Use this to efficiently audit many posts in a single call.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'post_ids' => [
						'type'        => 'array',
						'items'       => [ 'type' => 'integer' ],
						'description' => 'Array of post IDs to audit. If not provided, audits recent posts.',
					],
					'limit' => [
						'type'        => 'integer',
						'description' => 'Number of recent posts to audit if post_ids not given (max 50).',
						'default'     => 20,
					],
					'only_with_issues' => [
						'type'        => 'boolean',
						'description' => 'Only return posts that have SEO issues.',
						'default'     => false,
					],
				],
			],
		];
	}

	public function execute( array $arguments ): array {
		$posts = [];

		if ( ! empty( $arguments['post_ids'] ) ) {
			foreach ( array_slice( $arguments['post_ids'], 0, 50 ) as $id ) {
				$p = get_post( (int) $id );
				if ( $p ) {
					$posts[] = $p;
				}
			}
		} else {
			$limit = min( (int) ( $arguments['limit'] ?? 20 ), 50 );
			$posts = get_posts( [
				'post_type'      => [ 'post', 'page' ],
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
			] );
		}

		$results = [];
		foreach ( $posts as $post ) {
			$entry = $this->analyze_post( $post );

			if ( $arguments['only_with_issues'] ?? false ) {
				if ( empty( $entry['issues'] ) ) {
					continue;
				}
			}

			$results[] = $entry;
		}

		$total_issues = array_sum( array_map( fn( $r ) => count( $r['issues'] ), $results ) );
		$avg_score    = count( $results ) > 0
			? round( array_sum( array_map( fn( $r ) => $r['score'], $results ) ) / count( $results ) )
			: 0;

		return $this->json_response( [
			'posts_analyzed' => count( $results ),
			'total_issues'   => $total_issues,
			'average_score'  => $avg_score,
			'seo_plugin'     => $this->detect_seo_plugin(),
			'results'        => $results,
		] );
	}

	private function analyze_post( \WP_Post $post ): array {
		$post_id = $post->ID;

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

		// Rank Math score (if available).
		$rankmath_score = get_post_meta( $post_id, 'rank_math_seo_score', true );

		$content    = wp_strip_all_tags( $post->post_content );
		$word_count = str_word_count( $content );

		$issues = [];

		if ( empty( $meta_title ) ) {
			$issues[] = 'missing_meta_title';
		}
		if ( empty( $meta_desc ) ) {
			$issues[] = 'missing_meta_description';
		} elseif ( strlen( $meta_desc ) < 120 ) {
			$issues[] = 'meta_description_too_short';
		} elseif ( strlen( $meta_desc ) > 160 ) {
			$issues[] = 'meta_description_too_long';
		}
		if ( empty( $focus_keyword ) ) {
			$issues[] = 'no_focus_keyword';
		}
		if ( ! has_post_thumbnail( $post_id ) ) {
			$issues[] = 'no_featured_image';
		}
		if ( $word_count < 300 ) {
			$issues[] = 'content_too_short';
		}

		// Check for internal links.
		$internal_links = preg_match_all( '/href=["\']' . preg_quote( home_url(), '/' ) . '/i', $post->post_content );
		if ( $internal_links === 0 ) {
			$issues[] = 'no_internal_links';
		}

		// Check heading structure.
		if ( ! preg_match( '/<h[2-3]/i', $post->post_content ) ) {
			$issues[] = 'no_subheadings';
		}

		// Focus keyword in content check.
		if ( ! empty( $focus_keyword ) && stripos( $content, $focus_keyword ) === false ) {
			$issues[] = 'focus_keyword_not_in_content';
		}

		$score = max( 0, 100 - ( count( $issues ) * 12 ) );

		return [
			'id'              => $post_id,
			'title'           => $post->post_title,
			'url'             => get_permalink( $post_id ),
			'type'            => $post->post_type,
			'meta_title'      => $meta_title,
			'meta_description'=> $meta_desc,
			'focus_keyword'   => $focus_keyword,
			'word_count'      => $word_count,
			'has_thumbnail'   => has_post_thumbnail( $post_id ),
			'internal_links'  => $internal_links,
			'rankmath_score'  => $rankmath_score ? (int) $rankmath_score : null,
			'issues'          => $issues,
			'score'           => $score,
		];
	}

	private function detect_seo_plugin(): string {
		if ( class_exists( 'RankMath' ) ) return 'rankmath';
		if ( defined( 'WPSEO_VERSION' ) ) return 'yoast';
		if ( defined( 'AIOSEO_VERSION' ) ) return 'aioseo';
		return 'none';
	}
}
