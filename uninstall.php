<?php
/**
 * Uninstall hook — clean up plugin data.
 *
 * @package Real_MCP
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove plugin options.
delete_option( 'real_mcp_api_key' );
delete_option( 'real_mcp_allowed_origins' );
delete_option( 'real_mcp_admin_user_id' );
delete_option( 'real_mcp_version' );
delete_option( 'real_mcp_enabled_tools' );

// Clean up any session transients.
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup requires direct query.
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_real_mcp_session_%' OR option_name LIKE '_transient_timeout_real_mcp_session_%'"
);
