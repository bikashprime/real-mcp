<?php
/**
 * Tool: acf_delete_field — Delete an ACF field value from a post.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\ACF;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DeleteField extends AbstractTool {

	public function get_capability(): string {
		return 'edit_posts';
	}

	public function get_category(): string {
		return 'acf';
	}

	public function get_definition(): array {
		return [
			'name'        => 'acf_delete_field',
			'description' => 'Delete (clear) an ACF field value from a post, user, term, or options page.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'post_id' => [
						'type'        => 'integer',
						'description' => 'Post/page ID.',
					],
					'field_name' => [
						'type'        => 'string',
						'description' => 'The ACF field name to clear.',
					],
					'object_type' => [
						'type'        => 'string',
						'description' => 'Object type: post, user, term, option.',
						'default'     => 'post',
						'enum'        => [ 'post', 'user', 'term', 'option' ],
					],
				],
				'required' => [ 'post_id', 'field_name' ],
			],
		];
	}

	public function execute( array $arguments ): array {
		if ( ! function_exists( 'delete_field' ) ) {
			throw new \RuntimeException( 'Advanced Custom Fields is not active.' );
		}

		$post_id     = (int) $arguments['post_id'];
		$field_name  = sanitize_text_field( $arguments['field_name'] );
		$object_type = $arguments['object_type'] ?? 'post';

		$target = match ( $object_type ) {
			'user'   => 'user_' . $post_id,
			'term'   => 'term_' . $post_id,
			'option' => 'option',
			default  => $post_id,
		};

		$result = delete_field( $field_name, $target );

		return $this->json_response( [
			'success'    => (bool) $result,
			'post_id'    => $post_id,
			'field_name' => $field_name,
		] );
	}
}
