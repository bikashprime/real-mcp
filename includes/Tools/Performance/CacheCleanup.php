<?php
/**
 * Tool: cache_cleanup — Clear various WordPress caches.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\Performance;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CacheCleanup extends AbstractTool {

	public function get_capability(): string {
		return 'manage_options';
	}

	public function get_category(): string {
		return 'performance';
	}

	public function get_definition(): array {
		return [
			'name'        => 'cache_cleanup',
			'description' => 'Clear WordPress object cache, transients, and flush rewrite rules. Triggers cache purge hooks for popular caching plugins.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'object_cache' => [
						'type'        => 'boolean',
						'description' => 'Flush the object cache.',
						'default'     => true,
					],
					'transients' => [
						'type'        => 'boolean',
						'description' => 'Delete all transients.',
						'default'     => true,
					],
					'rewrite_rules' => [
						'type'        => 'boolean',
						'description' => 'Flush rewrite rules.',
						'default'     => false,
					],
					'page_cache' => [
						'type'        => 'boolean',
						'description' => 'Attempt to purge page cache (via hooks for popular plugins).',
						'default'     => true,
					],
				],
			],
		];
	}

	public function execute( array $arguments ): array {
		$actions = [];

		// Flush object cache.
		if ( $arguments['object_cache'] ?? true ) {
			wp_cache_flush();
			$actions[] = 'object_cache_flushed';
		}

		// Clear transients.
		if ( $arguments['transients'] ?? true ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk transient cleanup requires direct DB.
			$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'" );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_%'" );
			$actions[] = 'transients_cleared';
		}

		// Flush rewrite rules.
		if ( $arguments['rewrite_rules'] ?? false ) {
			flush_rewrite_rules();
			$actions[] = 'rewrite_rules_flushed';
		}

		// Trigger page cache purge for popular plugins.
		if ( $arguments['page_cache'] ?? true ) {
			// WP Super Cache.
			if ( function_exists( 'wp_cache_clear_cache' ) ) {
				wp_cache_clear_cache();
				$actions[] = 'wp_super_cache_cleared';
			}

			// W3 Total Cache.
			if ( function_exists( 'w3tc_flush_all' ) ) {
				w3tc_flush_all();
				$actions[] = 'w3tc_flushed';
			}

			// LiteSpeed Cache.
			if ( class_exists( 'LiteSpeed_Cache_API' ) ) {
				do_action( 'litespeed_purge_all' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Third-party plugin hook.
				$actions[] = 'litespeed_purged';
			}

			// WP Rocket.
			if ( function_exists( 'rocket_clean_domain' ) ) {
				rocket_clean_domain();
				$actions[] = 'wp_rocket_cleared';
			}

			// Generic cache purge action.
			do_action( 'real_mcp_cache_purge' );
		}

		return $this->json_response( [
			'success' => true,
			'actions' => $actions,
		] );
	}
}
