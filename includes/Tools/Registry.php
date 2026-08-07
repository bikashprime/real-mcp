<?php
/**
 * Tool Registry — manages available MCP tools with auto-discovery.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Registry {

	/**
	 * Registered tools.
	 *
	 * @var array<string, ToolInterface>
	 */
	private static array $tools = [];

	/**
	 * Whether built-in tools have been loaded.
	 */
	private static bool $loaded = false;

	/**
	 * Load built-in tools and fire registration hook.
	 */
	private static function load(): void {
		if ( self::$loaded ) {
			return;
		}

		// Register base-level read tools.
		self::register( new GetPosts() );
		self::register( new GetPost() );
		self::register( new SearchContent() );
		self::register( new GetSiteInfo() );
		self::register( new ListCategories() );
		self::register( new ListTags() );

		// Register Content tools.
		self::register( new Content\CreatePost() );
		self::register( new Content\UpdatePost() );
		self::register( new Content\DeletePost() );
		self::register( new Content\MergePosts() );
		self::register( new Content\CreatePage() );
		self::register( new Content\GetPostContent() );
		self::register( new Content\ManagePostMeta() );
		self::register( new Content\ManageOptions() );

		// Register SEO tools.
		self::register( new SEO\GetSeoData() );
		self::register( new SEO\BulkGetSeoData() );
		self::register( new SEO\UpdateSeoMeta() );
		self::register( new SEO\SiteAudit() );
		self::register( new SEO\GenerateSchema() );

		// Register Media tools.
		self::register( new Media\UploadMedia() );
		self::register( new Media\ListMedia() );
		self::register( new Media\UpdateAltText() );
		self::register( new Media\RegenerateThumbnails() );

		// Register WooCommerce tools (only if WooCommerce is active).
		if ( class_exists( 'WooCommerce' ) || defined( 'WC_PLUGIN_FILE' ) ) {
			self::register( new WooCommerce\CreateProduct() );
			self::register( new WooCommerce\UpdateProduct() );
			self::register( new WooCommerce\GetProducts() );
			self::register( new WooCommerce\UpdateInventory() );
			self::register( new WooCommerce\BulkPriceUpdate() );
			self::register( new WooCommerce\ManageCoupons() );
		}

		// Register Elementor tools (only if Elementor is active).
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			self::register( new Elementor\GetElementorData() );
			self::register( new Elementor\UpdateElementorContent() );
			self::register( new Elementor\CreateElementorPage() );
		}

		// Register Elementor Pro tools (only if Elementor Pro is active).
		if ( defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			self::register( new ElementorPro\GetFormSubmissions() );
			self::register( new ElementorPro\ManageGlobalWidgets() );
			self::register( new ElementorPro\ManageTemplates() );
		}

		// Register ACF tools (only if ACF is active).
		if ( class_exists( 'ACF' ) || function_exists( 'acf_get_field_groups' ) ) {
			self::register( new ACF\GetFieldGroups() );
			self::register( new ACF\GetFields() );
			self::register( new ACF\UpdateFields() );
			self::register( new ACF\DeleteField() );
		}

		// Register Classic Editor tools (only if Classic Editor is active).
		if ( defined( 'CLASSIC_EDITOR_VERSION' ) || class_exists( 'Classic_Editor' ) ) {
			self::register( new ClassicEditor\GetEditorContent() );
			self::register( new ClassicEditor\SwitchEditor() );
		}

		// Register Astra/Astra Pro tools (only if Astra theme is active).
		if ( defined( 'ASTRA_THEME_VERSION' ) || get_template() === 'astra' ) {
			self::register( new AstraPro\GetThemeSettings() );
			self::register( new AstraPro\UpdateThemeSettings() );
			if ( defined( 'ASTRA_EXT_VER' ) ) {
				self::register( new AstraPro\ManageCustomLayouts() );
			}
		}

		// Register Table Addons for Elementor tools.
		if ( defined( 'TABLE_ADDONS_FOR_ELEMENTOR_VERSION' ) || class_exists( 'TableAddonsForElementor' ) || defined( 'JEstarter_VERSION' ) ) {
			self::register( new TableAddons\ManageTables() );
		}

		// Register Rank Math tools (only if Rank Math is active).
		if ( class_exists( 'RankMath' ) ) {
			self::register( new RankMath\AuditSiteSeo() );
			self::register( new RankMath\FixSiteSeo() );
			self::register( new RankMath\GetPostSchema() );
			self::register( new RankMath\GetPostSeoMeta() );
			self::register( new RankMath\GetPostLinks() );
			self::register( new RankMath\GetLinkReport() );
			self::register( new RankMath\GetAiVisibilityOverview() );
			self::register( new RankMath\GetAiVisibilityBrandInsights() );
			self::register( new RankMath\GetAiVisibilityBrandQueries() );
			self::register( new RankMath\CreateAiVisibilityBrand() );
		}

		// Register Security tools.
		self::register( new Security\SecurityAudit() );
		self::register( new Security\PluginAudit() );
		self::register( new Security\UserPermissionReview() );

		// Register Performance tools.
		self::register( new Performance\PerformanceAudit() );
		self::register( new Performance\DatabaseOptimize() );
		self::register( new Performance\CacheCleanup() );

		// Register Maintenance tools.
		self::register( new Maintenance\UpdatePlugins() );
		self::register( new Maintenance\UpdateThemes() );
		self::register( new Maintenance\HealthCheck() );

		// Register Accessibility tools.
		self::register( new Accessibility\AccessibilityAudit() );

		/**
		 * Allow third-party plugins to register additional MCP tools.
		 *
		 * Example:
		 * add_action( 'real_mcp_register_tools', function( $registry ) {
		 *     $registry::register( new My_Custom_Tool() );
		 * } );
		 *
		 * @param string $registry The Registry class name for calling ::register().
		 */
		do_action( 'real_mcp_register_tools', self::class );

		self::$loaded = true;
	}

	/**
	 * Register a tool.
	 */
	public static function register( ToolInterface $tool ): void {
		$definition = $tool->get_definition();
		self::$tools[ $definition['name'] ] = $tool;
	}

	/**
	 * Get all registered tools (filtered by enabled abilities).
	 *
	 * @return array<string, ToolInterface>
	 */
	public static function get_tools(): array {
		self::load();
		return self::filter_enabled( self::$tools );
	}

	/**
	 * Get all registered tools WITHOUT filtering (for admin UI).
	 *
	 * @return array<string, ToolInterface>
	 */
	public static function get_all_tools(): array {
		self::load();
		return self::$tools;
	}

	/**
	 * Get a specific tool by name (only if enabled).
	 */
	public static function get_tool( string $name ): ?ToolInterface {
		self::load();
		$tool = self::$tools[ $name ] ?? null;
		if ( $tool && ! \Real_MCP\Admin::is_tool_enabled( $name ) ) {
			return null;
		}
		return $tool;
	}

	/**
	 * Get tools filtered by category (respects enabled status).
	 *
	 * @param string $category Category name.
	 * @return array<string, ToolInterface>
	 */
	public static function get_tools_by_category( string $category ): array {
		self::load();
		$filtered = array_filter( self::$tools, fn( $tool ) => $tool->get_category() === $category );
		return self::filter_enabled( $filtered );
	}

	/**
	 * Get list of available categories.
	 *
	 * @return array<string>
	 */
	public static function get_categories(): array {
		self::load();
		$enabled = self::filter_enabled( self::$tools );
		return array_values( array_unique( array_map( fn( $tool ) => $tool->get_category(), $enabled ) ) );
	}

	/**
	 * Filter tools by enabled abilities setting.
	 *
	 * @param array<string, ToolInterface> $tools
	 * @return array<string, ToolInterface>
	 */
	private static function filter_enabled( array $tools ): array {
		$enabled_list = \Real_MCP\Admin::get_enabled_tools();
		if ( $enabled_list === null ) {
			return $tools; // Never configured — all enabled.
		}
		return array_filter( $tools, function( ToolInterface $tool ) use ( $enabled_list ) {
			$def = $tool->get_definition();
			return in_array( $def['name'], $enabled_list, true );
		} );
	}

	/**
	 * Get info about plugin tools that are inactive (plugin not installed).
	 * Used by admin UI to show locked groups.
	 *
	 * @return array<string, array{label: string, plugin_url: string, tools: array}>
	 */
	public static function get_inactive_plugin_tools(): array {
		$inactive = [];

		if ( ! class_exists( 'WooCommerce' ) && ! defined( 'WC_PLUGIN_FILE' ) ) {
			$inactive['woocommerce'] = [
				'label'      => __( 'WooCommerce', 'real-mcp' ),
				'plugin_url' => 'https://wordpress.org/plugins/woocommerce/',
				'tools'      => [
					[ 'name' => 'woo_get_products', 'description' => 'List products with details' ],
					[ 'name' => 'woo_create_product', 'description' => 'Create a new product' ],
					[ 'name' => 'woo_update_product', 'description' => 'Update product name, description, price, SKU, categories, status' ],
					[ 'name' => 'woo_update_inventory', 'description' => 'Update stock quantity and status' ],
					[ 'name' => 'woo_bulk_price_update', 'description' => 'Bulk adjust prices' ],
					[ 'name' => 'woo_manage_coupons', 'description' => 'Create/manage promotional coupons' ],
				],
			];
		}

		if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
			$inactive['elementor'] = [
				'label'      => __( 'Elementor', 'real-mcp' ),
				'plugin_url' => 'https://wordpress.org/plugins/elementor/',
				'tools'      => [
					[ 'name' => 'get_elementor_data', 'description' => 'Read Elementor page structure' ],
					[ 'name' => 'create_elementor_page', 'description' => 'Create a page with Elementor structure' ],
					[ 'name' => 'update_elementor_content', 'description' => 'Update text in Elementor widgets' ],
				],
			];
		}

		if ( ! class_exists( 'RankMath' ) ) {
			$inactive['rankmath'] = [
				'label'      => __( 'Rank Math SEO', 'real-mcp' ),
				'plugin_url' => 'https://wordpress.org/plugins/seo-by-rank-math/',
				'tools'      => [
					[ 'name' => 'rankmath_audit_site_seo', 'description' => 'Run a detailed SEO audit on your site' ],
					[ 'name' => 'rankmath_fix_site_seo', 'description' => 'Automatically fix failed SEO tests' ],
					[ 'name' => 'rankmath_get_post_schema', 'description' => 'Retrieve Schema markup for a post' ],
					[ 'name' => 'rankmath_get_post_seo_meta', 'description' => 'Get full SEO metadata' ],
					[ 'name' => 'rankmath_get_post_links', 'description' => 'Get all links with anchor text and follow status' ],
					[ 'name' => 'rankmath_get_link_report', 'description' => 'Site-wide link status report' ],
					[ 'name' => 'rankmath_get_ai_visibility_overview', 'description' => 'AI Visibility report for all brands (PRO)' ],
					[ 'name' => 'rankmath_get_ai_visibility_brand_insights', 'description' => 'AI Visibility for a specific brand (PRO)' ],
					[ 'name' => 'rankmath_get_ai_visibility_brand_queries', 'description' => 'Queries tracked for a brand (PRO)' ],
					[ 'name' => 'rankmath_create_ai_visibility_brand', 'description' => 'Add a new brand to track (PRO)' ],
				],
			];
		}

		if ( ! defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			$inactive['elementor_pro'] = [
				'label'      => __( 'Elementor Pro', 'real-mcp' ),
				'plugin_url' => 'https://elementor.com/pro/',
				'tools'      => [
					[ 'name' => 'elementor_pro_get_form_submissions', 'description' => 'Retrieve form submissions' ],
					[ 'name' => 'elementor_pro_manage_global_widgets', 'description' => 'List and update global widgets' ],
					[ 'name' => 'elementor_pro_manage_templates', 'description' => 'List saved templates (headers, footers, popups)' ],
				],
			];
		}

		if ( ! class_exists( 'ACF' ) && ! function_exists( 'acf_get_field_groups' ) ) {
			$inactive['acf'] = [
				'label'      => __( 'Advanced Custom Fields (ACF)', 'real-mcp' ),
				'plugin_url' => 'https://wordpress.org/plugins/advanced-custom-fields/',
				'tools'      => [
					[ 'name' => 'acf_get_field_groups', 'description' => 'List all field groups with fields and locations' ],
					[ 'name' => 'acf_get_fields', 'description' => 'Get ACF field values for a post/user/term' ],
					[ 'name' => 'acf_update_fields', 'description' => 'Update ACF field values' ],
					[ 'name' => 'acf_delete_field', 'description' => 'Delete (clear) an ACF field value' ],
				],
			];
		}

		if ( ! defined( 'CLASSIC_EDITOR_VERSION' ) && ! class_exists( 'Classic_Editor' ) ) {
			$inactive['classic_editor'] = [
				'label'      => __( 'Classic Editor', 'real-mcp' ),
				'plugin_url' => 'https://wordpress.org/plugins/classic-editor/',
				'tools'      => [
					[ 'name' => 'classic_editor_get_content', 'description' => 'Get post content in classic editor format' ],
					[ 'name' => 'classic_editor_switch', 'description' => 'Switch a post between block and classic editor' ],
				],
			];
		}

		if ( ! defined( 'ASTRA_THEME_VERSION' ) && get_template() !== 'astra' ) {
			$inactive['astra'] = [
				'label'      => __( 'Astra / Astra Pro', 'real-mcp' ),
				'plugin_url' => 'https://wordpress.org/themes/astra/',
				'tools'      => [
					[ 'name' => 'astra_get_theme_settings', 'description' => 'Get Astra theme settings — layout, colors, typography' ],
					[ 'name' => 'astra_update_theme_settings', 'description' => 'Update Astra theme settings' ],
					[ 'name' => 'astra_manage_custom_layouts', 'description' => 'Manage Astra Pro custom layouts/hooks (PRO)' ],
				],
			];
		}

		if ( ! defined( 'TABLE_ADDONS_FOR_ELEMENTOR_VERSION' ) && ! class_exists( 'TableAddonsForElementor' ) && ! defined( 'JEstarter_VERSION' ) ) {
			$inactive['table_addons'] = [
				'label'      => __( 'Table Addons for Elementor', 'real-mcp' ),
				'plugin_url' => 'https://wordpress.org/plugins/developer/developer/',
				'tools'      => [
					[ 'name' => 'table_addons_manage', 'description' => 'List pages with tables and extract table data' ],
				],
			];
		}

		return $inactive;
	}
}
