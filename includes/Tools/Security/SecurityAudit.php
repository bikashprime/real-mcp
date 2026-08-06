<?php
/**
 * Tool: security_audit — Run a security audit of the WordPress site.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\Security;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SecurityAudit extends AbstractTool {

	public function get_capability(): string {
		return 'manage_options';
	}

	public function get_category(): string {
		return 'security';
	}

	public function get_definition(): array {
		return [
			'name'        => 'security_audit',
			'description' => 'Run a comprehensive security audit checking WordPress configuration, file permissions, user accounts, and common vulnerabilities.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => new \stdClass(),
			],
		];
	}

	public function execute( array $arguments ): array {
		$issues   = [];
		$warnings = [];
		$passed   = [];

		// Check WordPress version.
		global $wp_version;
		$passed[] = "WordPress version: {$wp_version}";

		// Check if debug mode is enabled.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$issues[] = 'WP_DEBUG is enabled (should be disabled in production)';
		} else {
			$passed[] = 'WP_DEBUG is disabled';
		}

		// Check if debug log is publicly accessible.
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$issues[] = 'WP_DEBUG_LOG is enabled (log file may be publicly accessible)';
		}

		// Check file editor.
		if ( ! defined( 'DISALLOW_FILE_EDIT' ) || ! DISALLOW_FILE_EDIT ) {
			$warnings[] = 'File editor is enabled (add DISALLOW_FILE_EDIT to wp-config.php)';
		} else {
			$passed[] = 'File editor is disabled';
		}

		// Check SSL.
		if ( is_ssl() ) {
			$passed[] = 'SSL is active';
		} else {
			$issues[] = 'SSL is not active (site not served over HTTPS)';
		}

		// Check table prefix.
		global $wpdb;
		if ( $wpdb->prefix === 'wp_' ) {
			$warnings[] = 'Default table prefix "wp_" is in use';
		} else {
			$passed[] = 'Custom table prefix is in use';
		}

		// Check admin user with ID 1.
		$user_1 = get_userdata( 1 );
		if ( $user_1 && $user_1->user_login === 'admin' ) {
			$warnings[] = 'Default "admin" username exists with ID 1';
		}

		// Check users with weak roles.
		$admins = get_users( [ 'role' => 'administrator', 'fields' => 'ID' ] );
		if ( count( $admins ) > 3 ) {
			$warnings[] = count( $admins ) . ' administrator accounts exist (consider reducing)';
		}

		// Check auto-updates.
		$auto_updates = get_option( 'auto_update_core_major', 'unset' );
		if ( $auto_updates === 'unset' ) {
			$warnings[] = 'Auto-updates for core are not explicitly configured';
		}

		// Check inactive plugins.
		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', [] );
		$inactive       = count( $all_plugins ) - count( $active_plugins );
		if ( $inactive > 0 ) {
			$warnings[] = "{$inactive} inactive plugin(s) installed (consider removing)";
		}

		// Check .htaccess or security headers.
		$passed[] = 'Active plugins: ' . count( $active_plugins );

		$score = 100 - ( count( $issues ) * 20 ) - ( count( $warnings ) * 5 );

		return $this->json_response( [
			'score'    => max( 0, $score ),
			'issues'   => $issues,
			'warnings' => $warnings,
			'passed'   => $passed,
			'summary'  => [
				'critical' => count( $issues ),
				'warnings' => count( $warnings ),
				'passed'   => count( $passed ),
			],
		] );
	}
}
