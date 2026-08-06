<?php
/**
 * Tool: plugin_audit — Audit installed plugins for risks and updates.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\Security;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PluginAudit extends AbstractTool {

	public function get_capability(): string {
		return 'manage_options';
	}

	public function get_category(): string {
		return 'security';
	}

	public function get_definition(): array {
		return [
			'name'        => 'plugin_audit',
			'description' => 'Audit all installed plugins for update availability, compatibility, and potential security risks. Shows active/inactive status, version info, and last update dates.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => new \stdClass(),
			],
		];
	}

	public function execute( array $arguments ): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', [] );

		// Force update check.
		wp_update_plugins();
		$update_info = get_site_transient( 'update_plugins' );

		$plugins_data = [];
		$needs_update = 0;
		$inactive_count = 0;

		foreach ( $all_plugins as $plugin_file => $plugin_info ) {
			$is_active = in_array( $plugin_file, $active_plugins, true );
			$has_update = isset( $update_info->response[ $plugin_file ] );

			if ( ! $is_active ) {
				$inactive_count++;
			}
			if ( $has_update ) {
				$needs_update++;
			}

			$entry = [
				'file'          => $plugin_file,
				'name'          => $plugin_info['Name'],
				'version'       => $plugin_info['Version'],
				'author'        => $plugin_info['Author'] ?? '',
				'active'        => $is_active,
				'update_available' => $has_update,
			];

			if ( $has_update ) {
				$entry['new_version'] = $update_info->response[ $plugin_file ]->new_version ?? '';
			}

			// Flag risk indicators.
			$risks = [];
			if ( ! $is_active ) {
				$risks[] = 'inactive_but_installed';
			}
			if ( empty( $plugin_info['AuthorURI'] ) && empty( $plugin_info['PluginURI'] ) ) {
				$risks[] = 'no_support_url';
			}
			if ( $has_update ) {
				$risks[] = 'outdated';
			}

			$entry['risks'] = $risks;
			$plugins_data[] = $entry;
		}

		return $this->json_response( [
			'total_plugins'   => count( $all_plugins ),
			'active'          => count( $active_plugins ),
			'inactive'        => $inactive_count,
			'needs_update'    => $needs_update,
			'plugins'         => $plugins_data,
		] );
	}
}
