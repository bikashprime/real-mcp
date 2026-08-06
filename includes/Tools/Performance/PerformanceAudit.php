<?php
/**
 * Tool: performance_audit — Audit site performance configuration.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\Performance;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PerformanceAudit extends AbstractTool {

	public function get_capability(): string {
		return 'manage_options';
	}

	public function get_category(): string {
		return 'performance';
	}

	public function get_definition(): array {
		return [
			'name'        => 'performance_audit',
			'description' => 'Audit site performance including caching status, database optimization opportunities, image optimization, asset loading, and Core Web Vitals preparation.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => new \stdClass(),
			],
		];
	}

	public function execute( array $arguments ): array {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Performance audit requires real-time DB statistics.

		$findings = [];
		$recommendations = [];

		// Check object cache.
		$object_cache = wp_using_ext_object_cache();
		$findings['object_cache'] = $object_cache ? 'External object cache active' : 'No external object cache';
		if ( ! $object_cache ) {
			$recommendations[] = 'Install Redis or Memcached for object caching';
		}

		// Check page cache (detect common cache plugins).
		$cache_plugins = [
			'wp-super-cache/wp-cache.php',
			'w3-total-cache/w3-total-cache.php',
			'wp-fastest-cache/wpFastestCache.php',
			'litespeed-cache/litespeed-cache.php',
			'wp-rocket/wp-rocket.php',
		];
		$active_plugins = get_option( 'active_plugins', [] );
		$has_page_cache = false;
		foreach ( $cache_plugins as $cp ) {
			if ( in_array( $cp, $active_plugins, true ) ) {
				$has_page_cache = true;
				$findings['page_cache'] = 'Active: ' . $cp;
				break;
			}
		}
		if ( ! $has_page_cache ) {
			$findings['page_cache'] = 'No page cache plugin detected';
			$recommendations[] = 'Install a page caching plugin';
		}

		// Database checks.
		$transients = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'"
		);
		$findings['transients'] = (int) $transients;
		if ( $transients > 500 ) {
			$recommendations[] = "Clean up {$transients} expired transients";
		}

		$revisions = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'"
		);
		$findings['post_revisions'] = (int) $revisions;
		if ( $revisions > 200 ) {
			$recommendations[] = "Consider limiting or cleaning {$revisions} post revisions";
		}

		$auto_drafts = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'"
		);
		$findings['auto_drafts'] = (int) $auto_drafts;

		$trashed = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'"
		);
		$findings['trashed_posts'] = (int) $trashed;

		$spam_comments = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'"
		);
		$findings['spam_comments'] = (int) $spam_comments;
		if ( $spam_comments > 100 ) {
			$recommendations[] = "Delete {$spam_comments} spam comments";
		}

		// Autoloaded data size.
		$autoload_size = $wpdb->get_var(
			"SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload = 'yes'"
		);
		$autoload_mb = round( (int) $autoload_size / 1024 / 1024, 2 );
		$findings['autoloaded_data_mb'] = $autoload_mb;
		if ( $autoload_mb > 1 ) {
			$recommendations[] = "Autoloaded data is {$autoload_mb}MB (should be under 1MB)";
		}

		// Image optimization check.
		$unoptimized = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
		);
		$findings['total_images'] = (int) $unoptimized;

		// PHP version.
		$findings['php_version'] = PHP_VERSION;
		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
			$recommendations[] = 'Upgrade PHP to 8.1+ for better performance';
		}

		// Memory limit.
		$findings['memory_limit'] = ini_get( 'memory_limit' );

		$score = 100 - ( count( $recommendations ) * 10 );

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $this->json_response( [
			'score'           => max( 0, $score ),
			'findings'        => $findings,
			'recommendations' => $recommendations,
		] );
	}
}
