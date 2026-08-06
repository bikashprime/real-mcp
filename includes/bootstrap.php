<?php
/**
 * Plugin bootstrap.
 *
 * @package Real_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Real_MCP\Server;
use Real_MCP\Admin;
use Real_MCP\Endpoint;
use Real_MCP\Tools\Registry as ToolRegistry;

/**
 * Initialize the plugin on plugins_loaded.
 */
add_action( 'plugins_loaded', function () {
	// Register REST API endpoint.
	Endpoint::register();

	// Admin settings page.
	if ( is_admin() ) {
		Admin::init();
	}
} );

/**
 * Handle CORS preflight OPTIONS requests early.
 *
 * Many MCP clients (browser-based, Electron apps like Claude Desktop, Cursor)
 * send preflight OPTIONS requests. WordPress REST API doesn't handle these by
 * default, so we intercept them before WordPress processes the request.
 */
add_action( 'rest_api_init', function () {
	// Handle OPTIONS preflight requests.
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) === 'OPTIONS' ) {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( strpos( $request_uri, 'real-mcp/v1/mcp' ) !== false ) {
			$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '*';
			header( 'Access-Control-Allow-Origin: ' . $origin );
			header( 'Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS' );
			header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, Accept, Mcp-Session-Id, MCP-Protocol-Version, Last-Event-ID' );
			header( 'Access-Control-Allow-Credentials: true' );
			header( 'Access-Control-Max-Age: 86400' );
			header( 'Content-Length: 0' );
			header( 'Content-Type: text/plain' );
			status_header( 204 );
			exit;
		}
	}
}, 1 );

/**
 * Activation hook — flush rewrite rules.
 */
register_activation_hook( REAL_MCP_FILE, function () {
	Endpoint::register();
	flush_rewrite_rules();
} );

/**
 * Deactivation hook — clean up.
 */
register_deactivation_hook( REAL_MCP_FILE, function () {
	flush_rewrite_rules();
} );

/**
 * Add a well-known endpoint for MCP server discovery.
 *
 * Some clients look for /.well-known/mcp or similar discovery endpoints.
 * We add a simple rewrite to help clients find the MCP endpoint.
 */
add_action( 'init', function () {
	add_rewrite_rule(
		'^\.well-known/mcp$',
		'index.php?rest_route=/real-mcp/v1/mcp',
		'top'
	);
} );
