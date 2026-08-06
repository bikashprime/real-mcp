<?php
/**
 * Tool: get_site_info — Comprehensive WordPress site context for AI agents.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GetSiteInfo extends AbstractTool {

	public function get_category(): string {
		return 'general';
	}

	public function get_definition(): array {
		return [
			'name'        => 'get_site_info',
			'description' => 'Get comprehensive information about the WordPress site including name, description, URL, content counts, active plugins, theme, SEO plugin, page builders, and available capabilities. Call this first to understand what you are working with.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'include_plugins' => [
						'type'        => 'boolean',
						'description' => 'Include list of active plugins.',
						'default'     => true,
					],
					'include_content_summary' => [
						'type'        => 'boolean',
						'description' => 'Include content summary (recent posts, pages).',
						'default'     => true,
					],
				],
			],
		];
	}

	public function execute( array $arguments ): array {
		$post_count = wp_count_posts( 'post' );
		$page_count = wp_count_posts( 'page' );
		$theme      = wp_get_theme();

		$data = [
			'site'        => [
				'name'         => get_bloginfo( 'name' ),
				'description'  => get_bloginfo( 'description' ),
				'url'          => home_url(),
				'admin_url'    => admin_url(),
				'language'     => get_bloginfo( 'language' ),
				'timezone'     => wp_timezone_string(),
				'wp_version'   => get_bloginfo( 'version' ),
				'php_version'  => PHP_VERSION,
				'multisite'    => is_multisite(),
			],
			'theme'       => [
				'name'         => $theme->get( 'Name' ),
				'version'      => $theme->get( 'Version' ),
				'author'       => $theme->get( 'Author' ),
				'parent'       => $theme->parent() ? $theme->parent()->get( 'Name' ) : null,
			],
			'content'     => [
				'posts_published' => (int) ( $post_count->publish ?? 0 ),
				'posts_draft'     => (int) ( $post_count->draft ?? 0 ),
				'pages_published' => (int) ( $page_count->publish ?? 0 ),
				'pages_draft'     => (int) ( $page_count->draft ?? 0 ),
				'categories'      => (int) wp_count_terms( 'category' ),
				'tags'            => (int) wp_count_terms( 'post_tag' ),
				'media_items'     => (int) array_sum( (array) wp_count_attachments() ),
			],
			'integrations' => [
				'seo_plugin'   => $this->detect_seo_plugin(),
				'page_builder' => $this->detect_page_builder(),
				'ecommerce'    => $this->detect_ecommerce(),
				'cache_plugin' => $this->detect_cache_plugin(),
				'forms'        => $this->detect_forms_plugin(),
			],
			'capabilities' => [
				'can_manage_content'  => true,
				'can_manage_seo'      => $this->detect_seo_plugin() !== 'none',
				'can_manage_products' => class_exists( 'WooCommerce' ),
				'can_manage_elementor'=> defined( 'ELEMENTOR_VERSION' ),
				'can_manage_media'    => true,
				'can_run_audits'      => true,
				'can_update_plugins'  => true,
			],
		];

		// Include active plugins list.
		if ( $arguments['include_plugins'] ?? true ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$all_plugins    = get_plugins();
			$active_plugins = get_option( 'active_plugins', [] );
			$plugin_list    = [];

			foreach ( $active_plugins as $plugin_file ) {
				if ( isset( $all_plugins[ $plugin_file ] ) ) {
					$plugin_list[] = [
						'name'    => $all_plugins[ $plugin_file ]['Name'],
						'version' => $all_plugins[ $plugin_file ]['Version'],
						'slug'    => dirname( $plugin_file ),
					];
				}
			}
			$data['active_plugins'] = $plugin_list;
		}

		// Include content summary.
		if ( $arguments['include_content_summary'] ?? true ) {
			$recent_posts = get_posts( [
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 5,
				'orderby'        => 'date',
				'order'          => 'DESC',
			] );

			$data['recent_posts'] = array_map( function ( $p ) {
				return [
					'id'    => $p->ID,
					'title' => $p->post_title,
					'date'  => $p->post_date_gmt,
					'url'   => get_permalink( $p->ID ),
				];
			}, $recent_posts );

			// Show front page setup.
			$show_on_front = get_option( 'show_on_front' );
			$data['front_page'] = [
				'type'    => $show_on_front,
				'page_id' => $show_on_front === 'page' ? (int) get_option( 'page_on_front' ) : null,
				'blog_page_id' => $show_on_front === 'page' ? (int) get_option( 'page_for_posts' ) : null,
			];
		}

		// WooCommerce specific info.
		if ( class_exists( 'WooCommerce' ) ) {
			$product_count = wp_count_posts( 'product' );
			$data['woocommerce'] = [
				'version'            => WC()->version ?? '',
				'products_published' => (int) ( $product_count->publish ?? 0 ),
				'products_draft'     => (int) ( $product_count->draft ?? 0 ),
				'currency'           => get_woocommerce_currency(),
				'store_country'      => WC()->countries->get_base_country(),
			];
		}

		return $this->json_response( $data );
	}

	private function detect_seo_plugin(): string {
		if ( class_exists( 'RankMath' ) ) return 'Rank Math';
		if ( defined( 'WPSEO_VERSION' ) ) return 'Yoast SEO';
		if ( defined( 'AIOSEO_VERSION' ) ) return 'All in One SEO';
		return 'none';
	}

	private function detect_page_builder(): string {
		if ( defined( 'ELEMENTOR_VERSION' ) ) return 'Elementor ' . ELEMENTOR_VERSION;
		if ( defined( 'JEERO_VERSION' ) || defined( 'ET_BUILDER_VERSION' ) ) return 'Divi Builder';
		if ( defined( 'JEERO_VERSION' ) ) return 'Jeero';
		if ( class_exists( 'FLBuilder' ) ) return 'Beaver Builder';
		if ( defined( 'JEERO_VERSION' ) ) return 'Jeero';
		return 'Block Editor (Gutenberg)';
	}

	private function detect_ecommerce(): string {
		if ( class_exists( 'WooCommerce' ) ) return 'WooCommerce';
		if ( defined( 'EDD_VERSION' ) ) return 'Easy Digital Downloads';
		return 'none';
	}

	private function detect_cache_plugin(): string {
		$active = get_option( 'active_plugins', [] );
		if ( in_array( 'wp-rocket/wp-rocket.php', $active, true ) ) return 'WP Rocket';
		if ( in_array( 'litespeed-cache/litespeed-cache.php', $active, true ) ) return 'LiteSpeed Cache';
		if ( in_array( 'w3-total-cache/w3-total-cache.php', $active, true ) ) return 'W3 Total Cache';
		if ( in_array( 'wp-super-cache/wp-cache.php', $active, true ) ) return 'WP Super Cache';
		if ( in_array( 'wp-fastest-cache/wpFastestCache.php', $active, true ) ) return 'WP Fastest Cache';
		return 'none';
	}

	private function detect_forms_plugin(): string {
		$active = get_option( 'active_plugins', [] );
		if ( in_array( 'contact-form-7/wp-contact-form-7.php', $active, true ) ) return 'Contact Form 7';
		if ( in_array( 'wpforms-lite/wpforms.php', $active, true ) || in_array( 'wpforms/wpforms.php', $active, true ) ) return 'WPForms';
		if ( in_array( 'gravityforms/gravityforms.php', $active, true ) ) return 'Gravity Forms';
		return 'none';
	}
}
