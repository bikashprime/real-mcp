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
	 * Get all registered tools.
	 *
	 * @return array<string, ToolInterface>
	 */
	public static function get_tools(): array {
		self::load();
		return self::$tools;
	}

	/**
	 * Get a specific tool by name.
	 */
	public static function get_tool( string $name ): ?ToolInterface {
		self::load();
		return self::$tools[ $name ] ?? null;
	}

	/**
	 * Get tools filtered by category.
	 *
	 * @param string $category Category name.
	 * @return array<string, ToolInterface>
	 */
	public static function get_tools_by_category( string $category ): array {
		self::load();
		return array_filter( self::$tools, fn( $tool ) => $tool->get_category() === $category );
	}

	/**
	 * Get list of available categories.
	 *
	 * @return array<string>
	 */
	public static function get_categories(): array {
		self::load();
		return array_unique( array_map( fn( $tool ) => $tool->get_category(), self::$tools ) );
	}
}
