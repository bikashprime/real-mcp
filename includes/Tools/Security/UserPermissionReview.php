<?php
/**
 * Tool: user_permission_review — Review user roles and capabilities.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\Security;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UserPermissionReview extends AbstractTool {

	public function get_capability(): string {
		return 'list_users';
	}

	public function get_category(): string {
		return 'security';
	}

	public function get_definition(): array {
		return [
			'name'        => 'user_permission_review',
			'description' => 'Review all user accounts, their roles, capabilities, and identify potential permission issues like excessive admin accounts or stale users.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'include_inactive_days' => [
						'type'        => 'integer',
						'description' => 'Flag users who haven\'t logged in for this many days.',
						'default'     => 90,
					],
				],
			],
		];
	}

	public function execute( array $arguments ): array {
		$inactive_days = (int) ( $arguments['include_inactive_days'] ?? 90 );
		$cutoff_date   = gmdate( 'Y-m-d H:i:s', strtotime( "-{$inactive_days} days" ) );

		$users_by_role = [];
		$issues        = [];

		$roles = wp_roles()->get_names();
		foreach ( $roles as $role_slug => $role_name ) {
			$users = get_users( [ 'role' => $role_slug, 'fields' => [ 'ID', 'user_login', 'user_email', 'user_registered' ] ] );
			$users_by_role[ $role_slug ] = [
				'name'  => $role_name,
				'count' => count( $users ),
				'users' => [],
			];

			foreach ( $users as $user ) {
				$last_login = get_user_meta( $user->ID, 'last_login', true );
				$user_data = [
					'id'         => $user->ID,
					'login'      => $user->user_login,
					'email'      => $user->user_email,
					'registered' => $user->user_registered,
					'last_login' => $last_login ?: 'unknown',
				];

				// Flag stale accounts.
				if ( $last_login && $last_login < $cutoff_date ) {
					$user_data['flag'] = 'inactive';
				}

				$users_by_role[ $role_slug ]['users'][] = $user_data;
			}
		}

		// Identify issues.
		$admin_count = $users_by_role['administrator']['count'] ?? 0;
		if ( $admin_count > 3 ) {
			$issues[] = "High number of administrators: {$admin_count} (recommend 1-3)";
		}

		$subscriber_count = $users_by_role['subscriber']['count'] ?? 0;
		$total_users = array_sum( array_column( $users_by_role, 'count' ) );

		return $this->json_response( [
			'total_users'   => $total_users,
			'roles'         => $users_by_role,
			'issues'        => $issues,
			'inactive_threshold_days' => $inactive_days,
		] );
	}
}
