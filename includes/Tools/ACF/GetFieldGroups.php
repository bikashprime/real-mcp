<?php
/**
 * Tool: acf_get_field_groups — List all ACF field groups.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\ACF;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GetFieldGroups extends AbstractTool {

	public function get_capability(): string {
		return 'edit_posts';
	}

	public function get_category(): string {
		return 'acf';
	}

	public function get_definition(): array {
		return [
			'name'        => 'acf_get_field_groups',
			'description' => 'List all ACF field groups with their fields, locations, and settings.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'include_fields' => [
						'type'        => 'boolean',
						'description' => 'Include full field definitions for each group.',
						'default'     => true,
					],
				],
			],
		];
	}

	public function execute( array $arguments ): array {
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			throw new \RuntimeException( 'Advanced Custom Fields is not active.' );
		}

		$include_fields = $arguments['include_fields'] ?? true;
		$groups         = acf_get_field_groups();
		$result         = [];

		foreach ( $groups as $group ) {
			$entry = [
				'key'      => $group['key'],
				'title'    => $group['title'],
				'active'   => (bool) $group['active'],
				'location' => $group['location'] ?? [],
			];

			if ( $include_fields ) {
				$fields        = acf_get_fields( $group['key'] );
				$entry['fields'] = array_map( function ( $field ) {
					return [
						'key'   => $field['key'],
						'name'  => $field['name'],
						'label' => $field['label'],
						'type'  => $field['type'],
						'required' => ! empty( $field['required'] ),
					];
				}, $fields ?: [] );
			}

			$result[] = $entry;
		}

		return $this->json_response( [
			'success'      => true,
			'total_groups' => count( $result ),
			'groups'       => $result,
		] );
	}
}
