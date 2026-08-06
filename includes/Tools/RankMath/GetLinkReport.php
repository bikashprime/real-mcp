<?php
/**
 * Tool: rankmath_get_link_report — Get site-wide link status report.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\RankMath;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GetLinkReport extends AbstractTool {

	public function get_capability(): string {
		return 'manage_options';
	}

	public function get_category(): string {
		return 'rankmath';
	}

	public function get_definition(): array {
		return [
			'name'        => 'rankmath_get_link_report',
			'description' => 'Check the status of links across your site. Identifies posts without internal or external links, total link counts, and (with Rank Math PRO) broken links and redirect chains.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'limit' => [
						'type'        => 'integer',
						'description' => 'Number of posts to analyze (max 100).',
						'default'     => 50,
					],
				],
			],
		];
	}

	public function execute( array $arguments ): array {
		if ( ! class_exists( 'RankMath' ) ) {
			throw new \RuntimeException( 'Rank Math SEO is not active.' );
		}

		$limit    = min( (int) ( $arguments['limit'] ?? 50 ), 100 );
		$home_url = home_url();

		$posts = get_posts( [
			'post_type'      => [ 'post', 'page' ],
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );

		$total_internal    = 0;
		$total_external    = 0;
		$no_internal_links = [];
		$no_external_links = [];

		foreach ( $posts as $post ) {
			$content = $post->post_content;

			// Count internal links.
			$internal = preg_match_all(
				'/href=["\'](' . preg_quote( $home_url, '/' ) . '[^"\']*|\/[^"\']*)["\']/',
				$content
			);

			// Count external links.
			$external = preg_match_all(
				'/href=["\']https?:\/\/[^"\']+["\']/',
				$content
			) - $internal;
			$external = max( 0, $external );

			$total_internal += $internal;
			$total_external += $external;

			if ( $internal === 0 ) {
				$no_internal_links[] = [
					'id'    => $post->ID,
					'title' => $post->post_title,
					'url'   => get_permalink( $post->ID ),
				];
			}

			if ( $external === 0 ) {
				$no_external_links[] = [
					'id'    => $post->ID,
					'title' => $post->post_title,
					'url'   => get_permalink( $post->ID ),
				];
			}
		}

		$report = [
			'success'            => true,
			'posts_analyzed'     => count( $posts ),
			'total_internal_links' => $total_internal,
			'total_external_links' => $total_external,
			'posts_without_internal_links' => count( $no_internal_links ),
			'posts_without_external_links' => count( $no_external_links ),
			'no_internal_links'  => array_slice( $no_internal_links, 0, 20 ),
			'no_external_links'  => array_slice( $no_external_links, 0, 20 ),
		];

		// PRO feature: Check Rank Math's internal link counter data.
		if ( defined( 'RANK_MATH_PRO_FILE' ) ) {
			global $wpdb;

			$table_name = 'rank_math_internal_links';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time audit query.
			$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . $table_name ) );

			if ( $table_exists === $wpdb->prefix . $table_name ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Safe: table uses $wpdb->prefix + hardcoded name.
				$broken_links = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}{$table_name}` WHERE status_code >= 400 OR status_code = 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Safe: table uses $wpdb->prefix + hardcoded name.
				$redirects = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}{$table_name}` WHERE status_code >= 300 AND status_code < 400" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

				$report['pro_data'] = [
					'broken_links'    => $broken_links,
					'redirect_chains' => $redirects,
				];
			}
		}

		$report['is_pro'] = defined( 'RANK_MATH_PRO_FILE' );

		return $this->json_response( $report );
	}
}
