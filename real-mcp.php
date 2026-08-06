<?php
/**
 * Plugin Name: Real MCP
 * Plugin URI: https://wordpress.org/plugins/real-mcp/
 * Description: Turns your WordPress site into a Model Context Protocol (MCP) server, enabling AI agents to discover and interact with your site's content and capabilities directly — no external server required.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Tested up to: 7.0
 * Requires PHP: 8.0
 * Author: Bikash C Mahata
 * Author URI: https://profiles.wordpress.org/bikashcmahata/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: real-mcp
 * Domain Path: /languages
 * Update URI: https://wordpress.org/plugins/real-mcp/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'REAL_MCP_VERSION', '1.0.0' );
define( 'REAL_MCP_PROTOCOL_VERSION', '2025-06-18' );
define( 'REAL_MCP_FILE', __FILE__ );
define( 'REAL_MCP_DIR', plugin_dir_path( __FILE__ ) );
define( 'REAL_MCP_URL', plugin_dir_url( __FILE__ ) );
define( 'REAL_MCP_MIN_PROTOCOL_VERSION', '2024-11-05' );

// Autoloader.
spl_autoload_register( function ( $class ) {
	$prefix = 'Real_MCP\\';
	$len    = strlen( $prefix );

	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, $len );
	$file           = REAL_MCP_DIR . 'includes/' . str_replace( '\\', '/', $relative_class ) . '.php';

	if ( file_exists( $file ) ) {
		require $file;
	}
} );

// Boot the plugin.
require_once REAL_MCP_DIR . 'includes/bootstrap.php';
