<?php
/**
 * Tool: astra_get_theme_settings — Get Astra theme/Astra Pro settings.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\AstraPro;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GetThemeSettings extends AbstractTool {

	public function get_capability(): string {
		return 'manage_options';
	}

	public function get_category(): string {
		return 'astra';
	}

	public function get_definition(): array {
		return [
			'name'        => 'astra_get_theme_settings',
			'description' => 'Retrieve Astra theme and Astra Pro settings — layout, colors, typography, header/footer config, and active modules.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'section' => [
						'type'        => 'string',
						'description' => 'Specific section to retrieve. Leave empty for overview.',
						'enum'        => [ 'all', 'layout', 'colors', 'typography', 'header', 'footer', 'modules' ],
						'default'     => 'all',
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

		$section  = $arguments['section'] ?? 'all';
		$settings = [];

		// Layout settings.
		if ( $section === 'all' || $section === 'layout' ) {
			$settings['layout'] = [
				'site_layout'          => get_option( 'astra-settings', [] )['site-layout'] ?? 'ast-full-width-layout',
				'container_layout'     => get_option( 'astra-settings', [] )['site-content-layout'] ?? 'plain-container',
				'sidebar_layout'       => get_option( 'astra-settings', [] )['site-sidebar-layout'] ?? 'right-sidebar',
				'content_width'        => get_option( 'astra-settings', [] )['site-content-width'] ?? 1200,
			];
		}

		// Colors.
		if ( $section === 'all' || $section === 'colors' ) {
			$astra_settings = get_option( 'astra-settings', [] );
			$settings['colors'] = [
				'theme_color'      => $astra_settings['theme-color'] ?? '',
				'link_color'       => $astra_settings['link-color'] ?? '',
				'text_color'       => $astra_settings['text-color'] ?? '',
				'heading_color'    => $astra_settings['heading-base-color'] ?? '',
			];
		}

		// Typography.
		if ( $section === 'all' || $section === 'typography' ) {
			$astra_settings = get_option( 'astra-settings', [] );
			$settings['typography'] = [
				'body_font_family' => $astra_settings['body-font-family'] ?? 'default',
				'body_font_size'   => $astra_settings['font-size-body'] ?? [],
				'heading_font'     => $astra_settings['headings-font-family'] ?? 'default',
			];
		}

		// Header.
		if ( $section === 'all' || $section === 'header' ) {
			$settings['header'] = [
				'header_layout'       => get_option( 'astra-settings', [] )['header-layouts'] ?? 'header-main-layout-1',
				'transparent_header'  => ! empty( get_option( 'astra-settings', [] )['transparent-header-enable'] ),
				'sticky_header'       => defined( 'ASTRA_EXT_VER' ) && ! empty( get_option( 'astra-settings', [] )['sticky-header-on-devices'] ),
			];
		}

		// Footer.
		if ( $section === 'all' || $section === 'footer' ) {
			$settings['footer'] = [
				'footer_layout' => get_option( 'astra-settings', [] )['footer-sml-layout'] ?? '',
				'copyright'     => get_option( 'astra-settings', [] )['footer-sml-section-1'] ?? '',
			];
		}

		// Astra Pro modules.
		if ( $section === 'all' || $section === 'modules' ) {
			$is_pro = defined( 'ASTRA_EXT_VER' );
			$settings['modules'] = [
				'astra_pro_active' => $is_pro,
				'pro_version'      => $is_pro ? ASTRA_EXT_VER : null,
			];
			if ( $is_pro ) {
				$extensions = get_option( 'astra-ext-settings', [] );
				$settings['modules']['active_modules'] = array_keys( array_filter( $extensions ) );
			}
		}

		return $this->json_response( [
			'success'       => true,
			'theme_version' => defined( 'ASTRA_THEME_VERSION' ) ? ASTRA_THEME_VERSION : 'unknown',
			'is_pro'        => defined( 'ASTRA_EXT_VER' ),
			'settings'      => $settings,
		] );
	}
}
