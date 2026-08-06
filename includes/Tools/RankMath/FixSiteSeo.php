<?php
/**
 * Tool: rankmath_fix_site_seo — Fix failed Rank Math SEO tests.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\RankMath;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FixSiteSeo extends AbstractTool {

	public function get_capability(): string {
		return 'manage_options';
	}

	public function get_category(): string {
		return 'rankmath';
	}

	public function get_definition(): array {
		return [
			'name'        => 'rankmath_fix_site_seo',
			'description' => 'Automatically fix failed Rank Math SEO tests. Can fix: blog visibility, permalink structure, site tagline, sitemaps, schema, noindex meta, robots.txt, and missing focus keywords.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'tests' => [
						'type'        => 'array',
						'items'       => [ 'type' => 'string' ],
						'description' => 'Array of test names to fix. Options: blog_public, permalink_structure, site_tagline, sitemaps, schema, noindex_meta, robots_txt, focus_keywords. If empty, fixes all failing tests.',
					],
					'tagline' => [
						'type'        => 'string',
						'description' => 'New site tagline (used when fixing site_tagline test).',
					],
				],
			],
		];
	}

	public function execute( array $arguments ): array {
		if ( ! class_exists( 'RankMath' ) ) {
			throw new \RuntimeException( 'Rank Math SEO is not active.' );
		}

		$tests_to_fix = $arguments['tests'] ?? [];
		$fix_all      = empty( $tests_to_fix );
		$fixed        = [];
		$skipped      = [];

		// Fix: Blog public.
		if ( $fix_all || in_array( 'blog_public', $tests_to_fix, true ) ) {
			if ( get_option( 'blog_public' ) !== '1' ) {
				update_option( 'blog_public', '1' );
				$fixed[] = 'blog_public';
			} else {
				$skipped[] = [ 'test' => 'blog_public', 'reason' => 'Already passing.' ];
			}
		}

		// Fix: Permalink structure.
		if ( $fix_all || in_array( 'permalink_structure', $tests_to_fix, true ) ) {
			$permalink = get_option( 'permalink_structure' );
			if ( empty( $permalink ) || strpos( $permalink, '%postname%' ) === false ) {
				update_option( 'permalink_structure', '/%postname%/' );
				flush_rewrite_rules();
				$fixed[] = 'permalink_structure';
			} else {
				$skipped[] = [ 'test' => 'permalink_structure', 'reason' => 'Already passing.' ];
			}
		}

		// Fix: Site tagline.
		if ( $fix_all || in_array( 'site_tagline', $tests_to_fix, true ) ) {
			$current = get_option( 'blogdescription' );
			if ( empty( $current ) || $current === 'Just another WordPress site' ) {
				$new_tagline = $arguments['tagline'] ?? get_option( 'blogname' ) . ' - Your trusted source';
				update_option( 'blogdescription', sanitize_text_field( $new_tagline ) );
				$fixed[] = 'site_tagline';
			} else {
				$skipped[] = [ 'test' => 'site_tagline', 'reason' => 'Already passing.' ];
			}
		}

		// Fix: Sitemaps.
		if ( $fix_all || in_array( 'sitemaps', $tests_to_fix, true ) ) {
			if ( ! \RankMath\Helper::is_module_active( 'sitemap' ) ) {
				$active_modules   = (array) get_option( 'rank_math_modules', [] );
				$active_modules[] = 'sitemap';
				update_option( 'rank_math_modules', array_unique( $active_modules ) );
				$fixed[] = 'sitemaps';
			} else {
				$skipped[] = [ 'test' => 'sitemaps', 'reason' => 'Already passing.' ];
			}
		}

		// Fix: Schema.
		if ( $fix_all || in_array( 'schema', $tests_to_fix, true ) ) {
			if ( ! \RankMath\Helper::is_module_active( 'rich-snippet' ) ) {
				$active_modules   = (array) get_option( 'rank_math_modules', [] );
				$active_modules[] = 'rich-snippet';
				update_option( 'rank_math_modules', array_unique( $active_modules ) );
				$fixed[] = 'schema';
			} else {
				$skipped[] = [ 'test' => 'schema', 'reason' => 'Already passing.' ];
			}
		}

		// Fix: Noindex meta.
		if ( $fix_all || in_array( 'noindex_meta', $tests_to_fix, true ) ) {
			$titles = (array) get_option( 'rank-math-options-titles', [] );
			$robots = $titles['robots_global'] ?? [];
			if ( is_array( $robots ) && in_array( 'noindex', $robots, true ) ) {
				$robots = array_diff( $robots, [ 'noindex' ] );
				$titles['robots_global'] = array_values( $robots );
				update_option( 'rank-math-options-titles', $titles );
				$fixed[] = 'noindex_meta';
			} else {
				$skipped[] = [ 'test' => 'noindex_meta', 'reason' => 'Already passing.' ];
			}
		}

		// Fix: Robots.txt.
		if ( $fix_all || in_array( 'robots_txt', $tests_to_fix, true ) ) {
			$robots_txt = get_option( 'rank_math_robots_txt' );
			if ( empty( $robots_txt ) && ! file_exists( ABSPATH . 'robots.txt' ) ) {
				$site_url    = site_url( '/' );
				$default_txt = "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n\nSitemap: {$site_url}sitemap_index.xml";
				update_option( 'rank_math_robots_txt', $default_txt );
				$fixed[] = 'robots_txt';
			} else {
				$skipped[] = [ 'test' => 'robots_txt', 'reason' => 'Already passing.' ];
			}
		}

		// Fix: Missing focus keywords.
		if ( $fix_all || in_array( 'focus_keywords', $tests_to_fix, true ) ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fix operation, needs fresh data.
			$posts_without_kw = $wpdb->get_results(
				"SELECT p.ID, p.post_title FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'rank_math_focus_keyword'
				 WHERE p.post_status = 'publish'
				 AND p.post_type IN ('post', 'page')
				 AND (pm.meta_value IS NULL OR pm.meta_value = '')
				 LIMIT 100"
			);

			$kw_fixed = 0;
			foreach ( $posts_without_kw as $post ) {
				// Use the post title as the focus keyword.
				$keyword = strtolower( sanitize_text_field( $post->post_title ) );
				update_post_meta( $post->ID, 'rank_math_focus_keyword', $keyword );
				$kw_fixed++;
			}

			if ( $kw_fixed > 0 ) {
				$fixed[] = "focus_keywords ({$kw_fixed} posts updated)";
			} else {
				$skipped[] = [ 'test' => 'focus_keywords', 'reason' => 'All posts already have focus keywords.' ];
			}
		}

		return $this->json_response( [
			'success' => true,
			'fixed'   => $fixed,
			'skipped' => $skipped,
		] );
	}
}
