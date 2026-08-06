<?php
/**
 * Tool: health_check — Run WordPress Site Health checks.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\Maintenance;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HealthCheck extends AbstractTool {

	public function get_capability(): string {
		return 'manage_options';
	}

	public function get_category(): string {
		return 'maintenance';
	}

	public function get_definition(): array {
		return [
			'name'        => 'health_check',
			'description' => 'Run WordPress Site Health diagnostics and return current status, tests results, and recommended actions.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => new \stdClass(),
			],
		];
	}

	public function execute( array $arguments ): array {
		require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';

		$health = \WP_Site_Health::get_instance();

		// Get all direct test results.
		$tests = \WP_Site_Health::get_tests();
		$results = [
			'critical' => [],
			'recommended' => [],
			'good' => [],
		];

		foreach ( $tests['direct'] ?? [] as $test ) {
			if ( ! is_callable( $test['test'] ) ) {
				continue;
			}

			try {
				$result = call_user_func( $test['test'] );
				if ( ! is_array( $result ) ) {
					continue;
				}

				$entry = [
					'label'       => $result['label'] ?? '',
					'status'      => $result['status'] ?? 'good',
					'description' => wp_strip_all_tags( $result['description'] ?? '' ),
				];

				match ( $result['status'] ?? 'good' ) {
					'critical'    => $results['critical'][] = $entry,
					'recommended' => $results['recommended'][] = $entry,
					default       => $results['good'][] = $entry,
				};
			} catch ( \Throwable $e ) {
				// Skip tests that throw exceptions.
				continue;
			}
		}

		return $this->json_response( [
			'summary' => [
				'critical'    => count( $results['critical'] ),
				'recommended' => count( $results['recommended'] ),
				'good'        => count( $results['good'] ),
			],
			'critical'    => $results['critical'],
			'recommended' => $results['recommended'],
			'good'        => array_slice( $results['good'], 0, 10 ),
		] );
	}
}
