<?php
/**
 * Tool: elementor_pro_get_form_submissions — Get Elementor Pro form submissions.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\ElementorPro;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GetFormSubmissions extends AbstractTool {

	public function get_capability(): string {
		return 'manage_options';
	}

	public function get_category(): string {
		return 'elementor_pro';
	}

	public function get_definition(): array {
		return [
			'name'        => 'elementor_pro_get_form_submissions',
			'description' => 'Retrieve Elementor Pro form submissions. Filter by form name, date range, or status.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'form_name' => [
						'type'        => 'string',
						'description' => 'Filter by form name/ID.',
					],
					'limit' => [
						'type'        => 'integer',
						'description' => 'Number of submissions to return.',
						'default'     => 20,
					],
					'status' => [
						'type'        => 'string',
						'description' => 'Filter by status.',
						'enum'        => [ 'all', 'unread', 'read' ],
						'default'     => 'all',
					],
				],
			],
		];
	}

	public function execute( array $arguments ): array {
		if ( ! defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			throw new \RuntimeException( 'Elementor Pro is not active.' );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'e_submissions';
		$limit = min( (int) ( $arguments['limit'] ?? 20 ), 100 );

		// Check if submissions table exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'e_submissions' ) );

		if ( ! $table_exists ) {
			return $this->json_response( [
				'success'     => true,
				'submissions' => [],
				'message'     => 'Form submissions table not found. Ensure Elementor Pro form submissions are enabled.',
			] );
		}

		$where = '1=1';
		if ( ! empty( $arguments['form_name'] ) ) {
			$where .= $wpdb->prepare( ' AND form_name = %s', sanitize_text_field( $arguments['form_name'] ) );
		}
		if ( ( $arguments['status'] ?? 'all' ) === 'unread' ) {
			$where .= " AND is_read = 0";
		} elseif ( ( $arguments['status'] ?? 'all' ) === 'read' ) {
			$where .= " AND is_read = 1";
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$submissions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, form_name, created_at, is_read FROM `{$wpdb->prefix}e_submissions` WHERE {$where} ORDER BY created_at DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit
			)
		);

		$results = [];
		foreach ( $submissions as $sub ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$values = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT `key`, `value` FROM `{$wpdb->prefix}e_submissions_values` WHERE submission_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$sub->id
				)
			);

			$fields = [];
			foreach ( $values as $v ) {
				$fields[ $v->key ] = $v->value;
			}

			$results[] = [
				'id'         => (int) $sub->id,
				'form_name'  => $sub->form_name,
				'created_at' => $sub->created_at,
				'is_read'    => (bool) $sub->is_read,
				'fields'     => $fields,
			];
		}

		return $this->json_response( [
			'success'     => true,
			'total'       => count( $results ),
			'submissions' => $results,
		] );
	}
}
