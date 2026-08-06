<?php
/**
 * Tool: update_themes — Update one or all themes.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\Maintenance;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UpdateThemes extends AbstractTool {

	public function get_capability(): string {
		return 'update_themes';
	}

	public function get_category(): string {
		return 'maintenance';
	}

	public function get_definition(): array {
		return [
			'name'        => 'update_themes',
			'description' => 'Update one or all WordPress themes to their latest versions.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'themes' => [
						'type'        => 'array',
						'items'       => [ 'type' => 'string' ],
						'description' => 'Array of theme slugs to update. If empty with all=true, updates all.',
					],
					'all' => [
						'type'        => 'boolean',
						'description' => 'If true, update all themes with available updates.',
						'default'     => false,
					],
				],
			],
		];
	}

	public function execute( array $arguments ): array {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';

		wp_update_themes();
		$update_info = get_site_transient( 'update_themes' );

		if ( empty( $update_info->response ) ) {
			return $this->json_response( [
				'success' => true,
				'message' => 'All themes are up to date.',
				'updated' => [],
			] );
		}

		$to_update = [];
		if ( ! empty( $arguments['themes'] ) ) {
			foreach ( $arguments['themes'] as $theme ) {
				$theme = sanitize_text_field( $theme );
				if ( isset( $update_info->response[ $theme ] ) ) {
					$to_update[] = $theme;
				}
			}
		} elseif ( $arguments['all'] ?? false ) {
			$to_update = array_keys( $update_info->response );
		}

		if ( empty( $to_update ) ) {
			return $this->json_response( [
				'success' => true,
				'message' => 'No themes to update.',
				'updated' => [],
			] );
		}

		$skin     = new \Automatic_Upgrader_Skin();
		$upgrader = new \Theme_Upgrader( $skin );

		$updated = [];
		$failed  = [];
		foreach ( $to_update as $theme_slug ) {
			$result = $upgrader->upgrade( $theme_slug );
			if ( $result && ! is_wp_error( $result ) ) {
				$theme_obj = wp_get_theme( $theme_slug );
				$updated[] = [
					'theme'       => $theme_slug,
					'name'        => $theme_obj->get( 'Name' ),
					'new_version' => $theme_obj->get( 'Version' ),
				];
			} else {
				$failed[] = $theme_slug;
			}
		}

		return $this->json_response( [
			'success' => true,
			'updated' => $updated,
			'failed'  => $failed,
		] );
	}
}
