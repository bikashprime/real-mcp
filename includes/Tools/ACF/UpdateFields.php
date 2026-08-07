<?php
/**
 * Tool: acf_update_fields — Update ACF field values for a post.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\ACF;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UpdateFields extends AbstractTool {

	public function get_capability(): string {
		return 'edit_posts';
	}

	public function get_category(): string {
		return 'acf';
	}

	public function get_definition(): array {
		return [
			'name'        => 'acf_update_fields',
			'description' => 'Update one or more ACF field values for a post, page, user, term, or options page.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'post_id' => [
						'type'        => 'integer',
						'description' => 'Post/page ID. Use 0 with object_type "option" for options page.',
					],
					'object_type' => [
						'type'        => 'string',
						'description' => 'Object type: post, user, term, option.',
						'default'     => 'post',
						'enum'        => [ 'post', 'user', 'term', 'option' ],
					],
					'fields' => [
						'type'        => 'object',
						'description' => 'Object of field_name => value pairs to update.',
					],
				],
				'required' => [ 'post_id', 'fields' ],
			],
		];
	}

	public function execute( array $arguments ): array {
		if ( ! function_exists( 'update_field' ) ) {
			throw new \RuntimeException( 'Advanced Custom Fields is not active.' );
		}

		$post_id     = (int) $arguments['post_id'];
		$object_type = $arguments['object_type'] ?? 'post';
		$fields      = $arguments['fields'] ?? [];

		if ( empty( $fields ) ) {
			throw new \InvalidArgumentException( 'No fields provided to update.' );
		}

		$target = match ( $object_type ) {
			'user'   => 'user_' . $post_id,
			'term'   => 'term_' . $post_id,
			'option' => 'option',
			default  => $post_id,
		};

		$updated = [];
		$failed  = [];

		foreach ( $fields as $field_name => $value ) {
			$field_name = sanitize_text_field( $field_name );
			$result     = update_field( $field_name, $value, $target );
			if ( $result !== false ) {
				$updated[] = $field_name;
			} else {
				$failed[] = $field_name;
			}
		}

		return $this->json_response( [
			'success' => true,
			'post_id' => $post_id,
			'updated' => $updated,
			'failed'  => $failed,
		] );
	}
}
