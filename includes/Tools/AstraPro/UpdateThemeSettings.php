<?php
/**
 * Tool: astra_update_theme_settings — Update Astra theme settings.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\AstraPro;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UpdateThemeSettings extends AbstractTool {

	public function get_capability(): string {
		return 'manage_options';
	}

	public function get_category(): string {
		return 'astra';
	}

	public function get_definition(): array {
		return [
			'name'        => 'astra_update_theme_settings',
			'description' => 'Update Astra theme settings — layout, colors, typography, header/footer. Provide only the settings you want to change.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'site_layout' => [
						'type'        => 'string',
						'description' => 'Site layout.',
						'enum'        => [ 'ast-full-width-layout', 'ast-box-layout', 'ast-padded-layout', 'ast-fluid-width-layout' ],
					],
					'container_layout' => [
						'type'        => 'string',
						'description' => 'Content container layout.',
						'enum'        => [ 'plain-container', 'content-boxed-container', 'boxed-container', 'page-builder' ],
					],
					'sidebar_layout' => [
						'type'        => 'string',
						'description' => 'Sidebar position.',
						'enum'        => [ 'no-sidebar', 'left-sidebar', 'right-sidebar' ],
					],
					'content_width' => [
						'type'        => 'integer',
						'description' => 'Content width in pixels (e.g., 1200).',
					],
					'theme_color' => [
						'type'        => 'string',
						'description' => 'Primary theme color (hex, e.g., "#0073aa").',
					],
					'link_color' => [
						'type'        => 'string',
						'description' => 'Link color (hex).',
					],
					'text_color' => [
						'type'        => 'string',
						'description' => 'Body text color (hex).',
					],
					'transparent_header' => [
						'type'        => 'boolean',
						'description' => 'Enable transparent header.',
					],
				],
			],
		];
	}

	public function execute( array $arguments ): array {
		$is_astra = defined( 'ASTRA_THEME_VERSION' ) || get_template() === 'astra';
		if ( ! $is_astra ) {
			throw new \RuntimeException( 'Astra theme is not active.' );
		}

		$astra_settings = get_option( 'astra-settings', [] );
		$updated        = [];

		$map = [
			'site_layout'        => 'site-layout',
			'container_layout'   => 'site-content-layout',
			'sidebar_layout'     => 'site-sidebar-layout',
			'content_width'      => 'site-content-width',
			'theme_color'        => 'theme-color',
			'link_color'         => 'link-color',
			'text_color'         => 'text-color',
			'transparent_header' => 'transparent-header-enable',
		];

		foreach ( $map as $arg_key => $setting_key ) {
			if ( isset( $arguments[ $arg_key ] ) ) {
				$value = $arguments[ $arg_key ];
				if ( is_string( $value ) ) {
					$value = sanitize_text_field( $value );
				}
				$astra_settings[ $setting_key ] = $value;
				$updated[] = $arg_key;
			}
		}

		if ( empty( $updated ) ) {
			throw new \InvalidArgumentException( 'No settings provided to update.' );
		}

		update_option( 'astra-settings', $astra_settings );

		return $this->json_response( [
			'success' => true,
			'updated' => $updated,
		] );
	}
}
