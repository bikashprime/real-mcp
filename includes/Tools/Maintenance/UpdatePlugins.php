<?php
/**
 * Tool: update_plugins — Update one or all plugins.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\Maintenance;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UpdatePlugins extends AbstractTool {

	public function get_capability(): string {
		return 'update_plugins';
	}

	public function get_category(): string {
		return 'maintenance';
	}

	public function get_definition(): array {
		return [
			'name'        => 'update_plugins',
			'description' => 'Update one or all WordPress plugins to their latest versions.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'plugins' => [
						'type'        => 'array',
						'items'       => [ 'type' => 'string' ],
						'description' => 'Array of plugin file paths to update (e.g., ["akismet/akismet.php"]). If empty, updates all.',
					],
					'all' => [
						'type'        => 'boolean',
						'description' => 'If true, update all plugins with available updates.',
						'default'     => false,
					],
				],
			],
		];
	}

	public function execute( array $arguments ): array {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';

		wp_update_plugins();
		$update_info = get_site_transient( 'update_plugins' );

		if ( empty( $update_info->response ) ) {
			return $this->json_response( [
				'success' => true,
				'message' => 'All plugins are up to date.',
				'updated' => [],
			] );
		}

		$to_update = [];
		if ( ! empty( $arguments['plugins'] ) ) {
			foreach ( $arguments['plugins'] as $plugin ) {
				$plugin = sanitize_text_field( $plugin );
				if ( isset( $update_info->response[ $plugin ] ) ) {
					$to_update[] = $plugin;
				}
			}
		} elseif ( $arguments['all'] ?? false ) {
			$to_update = array_keys( $update_info->response );
		}

		if ( empty( $to_update ) ) {
			return $this->json_response( [
				'success' => true,
				'message' => 'No plugins to update.',
				'updated' => [],
			] );
		}

		// Use the WordPress upgrader.
		$skin     = new \Automatic_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );

		$results = $upgrader->bulk_upgrade( $to_update );

		$updated = [];
		$failed  = [];
		foreach ( $to_update as $plugin ) {
			if ( ! empty( $results[ $plugin ] ) && ! is_wp_error( $results[ $plugin ] ) ) {
				$info = get_plugins()[ $plugin ] ?? [];
				$updated[] = [
					'plugin'      => $plugin,
					'name'        => $info['Name'] ?? $plugin,
					'new_version' => $info['Version'] ?? 'unknown',
				];
			} else {
				$failed[] = $plugin;
			}
		}

		return $this->json_response( [
			'success' => true,
			'updated' => $updated,
			'failed'  => $failed,
		] );
	}
}
