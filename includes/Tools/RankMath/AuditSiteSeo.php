<?php
/**
 * Tool: rankmath_audit_site_seo — Run Rank Math SEO audit on the site.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\RankMath;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AuditSiteSeo extends AbstractTool {

	public function get_capability(): string {
		return 'manage_options';
	}

	public function get_category(): string {
		return 'rankmath';
	}

	public function get_definition(): array {
		return [
			'name'        => 'rankmath_audit_site_seo',
			'description' => 'Run a detailed SEO audit on your site using Rank Math. Returns pass/fail results for SEO tests including blog visibility, permalink structure, sitemaps, schema, robots.txt, and more.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'url' => [
						'type'        => 'string',
						'description' => 'URL to audit. Defaults to the current site if empty.',
					],
				],
			],
		];
	}

	public function execute( array $arguments ): array {
		if ( ! class_exists( 'RankMath' ) ) {
			throw new \RuntimeException( 'Rank Math SEO is not active.' );
		}

		$results = [];

		// Test 1: Blog public.
		$blog_public = get_option( 'blog_public' );
		$results[] = [
			'test'   => 'blog_public',
			'label'  => 'Blog is visible to search engines',
			'status' => $blog_public === '1' ? 'pass' : 'fail',
			'detail' => $blog_public === '1' ? 'Your site is visible to search engines.' : 'Your site is set to discourage search engine indexing.',
		];

		// Test 2: Permalink structure.
		$permalink = get_option( 'permalink_structure' );
		$results[] = [
			'test'   => 'permalink_structure',
			'label'  => 'SEO-friendly permalink structure',
			'status' => ( ! empty( $permalink ) && strpos( $permalink, '%postname%' ) !== false ) ? 'pass' : 'fail',
			'detail' => ! empty( $permalink ) ? "Current structure: {$permalink}" : 'Using plain permalinks (not SEO-friendly).',
		];

		// Test 3: Site tagline.
		$tagline = get_option( 'blogdescription' );
		$results[] = [
			'test'   => 'site_tagline',
			'label'  => 'Site tagline is set',
			'status' => ( ! empty( $tagline ) && $tagline !== 'Just another WordPress site' ) ? 'pass' : 'fail',
			'detail' => ! empty( $tagline ) ? "Current tagline: {$tagline}" : 'No tagline set.',
		];

		// Test 4: Sitemap module.
		$sitemap_enabled = \RankMath\Helper::is_module_active( 'sitemap' );
		$results[] = [
			'test'   => 'sitemaps',
			'label'  => 'XML Sitemap is enabled',
			'status' => $sitemap_enabled ? 'pass' : 'fail',
			'detail' => $sitemap_enabled ? 'Rank Math Sitemap module is active.' : 'Sitemap module is disabled.',
		];

		// Test 5: Schema module.
		$schema_enabled = \RankMath\Helper::is_module_active( 'rich-snippet' );
		$results[] = [
			'test'   => 'schema',
			'label'  => 'Schema/Structured Data is enabled',
			'status' => $schema_enabled ? 'pass' : 'fail',
			'detail' => $schema_enabled ? 'Schema module is active.' : 'Schema module is disabled.',
		];

		// Test 6: Noindex meta check.
		$robots_global = \RankMath\Helper::get_settings( 'titles.robots_global' );
		$is_indexed    = is_array( $robots_global ) ? ! in_array( 'noindex', $robots_global, true ) : true;
		$results[] = [
			'test'   => 'noindex_meta',
			'label'  => 'Site is set to index',
			'status' => $is_indexed ? 'pass' : 'fail',
			'detail' => $is_indexed ? 'Global robots is set to index.' : 'Global robots includes noindex.',
		];

		// Test 7: Focus keywords.
		global $wpdb;
		$posts_without_kw = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'rank_math_focus_keyword'
			 WHERE p.post_status = 'publish'
			 AND p.post_type IN ('post', 'page')
			 AND (pm.meta_value IS NULL OR pm.meta_value = '')"
		);
		$total_posts = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE post_status = 'publish' AND post_type IN ('post', 'page')"
		);
		$results[] = [
			'test'   => 'focus_keywords',
			'label'  => 'Posts have focus keywords',
			'status' => $posts_without_kw === 0 ? 'pass' : 'warning',
			'detail' => "{$posts_without_kw} of {$total_posts} published posts/pages are missing a focus keyword.",
		];

		// Test 8: Robots.txt.
		$robots_txt = get_option( 'rank_math_robots_txt' );
		$has_robots = ! empty( $robots_txt ) || file_exists( ABSPATH . 'robots.txt' );
		$results[] = [
			'test'   => 'robots_txt',
			'label'  => 'Robots.txt is configured',
			'status' => $has_robots ? 'pass' : 'fail',
			'detail' => $has_robots ? 'Robots.txt is present.' : 'No robots.txt found.',
		];

		$passed = count( array_filter( $results, fn( $r ) => $r['status'] === 'pass' ) );
		$failed = count( array_filter( $results, fn( $r ) => $r['status'] === 'fail' ) );
		$score  = count( $results ) > 0 ? round( ( $passed / count( $results ) ) * 100 ) : 0;

		return $this->json_response( [
			'success'      => true,
			'total_tests'  => count( $results ),
			'passed'       => $passed,
			'failed'       => $failed,
			'score'        => $score,
			'results'      => $results,
		] );
	}
}
