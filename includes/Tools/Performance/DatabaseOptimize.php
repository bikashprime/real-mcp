<?php
/**
 * Tool: database_optimize — Clean and optimize the WordPress database.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\Performance;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DatabaseOptimize extends AbstractTool {

	public function get_capability(): string {
		return 'manage_options';
	}

	public function get_category(): string {
		return 'performance';
	}

	public function get_definition(): array {
		return [
			'name'        => 'database_optimize',
			'description' => 'Clean and optimize the WordPress database by removing revisions, auto-drafts, trashed posts, spam comments, expired transients, and orphaned meta.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'clean_revisions' => [
						'type'        => 'boolean',
						'description' => 'Delete post revisions.',
						'default'     => true,
					],
					'clean_auto_drafts' => [
						'type'        => 'boolean',
						'description' => 'Delete auto-drafts.',
						'default'     => true,
					],
					'clean_trash' => [
						'type'        => 'boolean',
						'description' => 'Delete trashed posts.',
						'default'     => true,
					],
					'clean_spam' => [
						'type'        => 'boolean',
						'description' => 'Delete spam comments.',
						'default'     => true,
					],
					'clean_transients' => [
						'type'        => 'boolean',
						'description' => 'Delete expired transients.',
						'default'     => true,
					],
					'optimize_tables' => [
						'type'        => 'boolean',
						'description' => 'Run OPTIMIZE TABLE on all WordPress tables.',
						'default'     => false,
					],
				],
			],
		];
	}

	public function execute( array $arguments ): array {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Database optimization requires direct queries.

		$cleaned = [];

		// Clean revisions.
		if ( $arguments['clean_revisions'] ?? true ) {
			$count = $wpdb->query(
				"DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'"
			);
			$cleaned['revisions'] = (int) $count;
		}

		// Clean auto-drafts.
		if ( $arguments['clean_auto_drafts'] ?? true ) {
			$count = $wpdb->query(
				"DELETE FROM {$wpdb->posts} WHERE post_status = 'auto-draft'"
			);
			$cleaned['auto_drafts'] = (int) $count;
		}

		// Clean trashed posts.
		if ( $arguments['clean_trash'] ?? true ) {
			$count = $wpdb->query(
				"DELETE FROM {$wpdb->posts} WHERE post_status = 'trash'"
			);
			$cleaned['trashed_posts'] = (int) $count;
		}

		// Clean spam comments.
		if ( $arguments['clean_spam'] ?? true ) {
			$count = $wpdb->query(
				"DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam'"
			);
			$cleaned['spam_comments'] = (int) $count;
		}

		// Clean expired transients.
		if ( $arguments['clean_transients'] ?? true ) {
			$count = $wpdb->query(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%' AND option_value < UNIX_TIMESTAMP()"
			);
			$wpdb->query(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' AND option_name NOT LIKE '_transient_timeout_%' AND option_name NOT IN (SELECT REPLACE(option_name, '_transient_timeout_', '_transient_') FROM (SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%') as t)"
			);
			$cleaned['expired_transients'] = (int) $count;
		}

		// Clean orphaned post meta.
		$orphaned_meta = $wpdb->query(
			"DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL"
		);
		$cleaned['orphaned_postmeta'] = (int) $orphaned_meta;

		// Optimize tables.
		if ( $arguments['optimize_tables'] ?? false ) {
			$tables = $wpdb->get_col( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $wpdb->prefix ) . '%' ) );
			$optimized = 0;
			foreach ( $tables as $table ) {
				$wpdb->query( "OPTIMIZE TABLE `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from SHOW TABLES result, not user input.
				$optimized++;
			}
			$cleaned['tables_optimized'] = $optimized;
		}

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $this->json_response( [
			'success' => true,
			'cleaned' => $cleaned,
			'total_items_removed' => array_sum( $cleaned ),
		] );
	}
}
