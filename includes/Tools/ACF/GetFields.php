<?php
/**
 * Tool: acf_get_fields — Get ACF field values for a post.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\ACF;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GetFields extends AbstractTool {

	public function get_capability(): string {
		return 'edit_posts';
	}

	public function get_category(): string {
		return 'acf';
	}

	public function get_definition(): array {
		return [
			'name'        => 'acf_get_fields',
			'description' => 'Get all ACF field values for a specific post, page, user, or term.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'post_id' => [
						'type'        => 'integer',
						'description' => 'Post/page ID. Use 0 with object_type for options page.',
					],
					'object_type' => [
						'type'        => 'string',
						'description' => 'Object type: post, user, term, option.',
						'default'     => 'post',
						'enum'        => [ 'post', 'user', 'term', 'option' ],
					],
				],
				'required' => [ 'post_id' ],
			],
		];
	}

	public function execute( array $arguments ): array {
		if ( ! function_exists( 'get_fields' ) ) {
			throw new \RuntimeException( 'Advanced Custom Fields is not active.' );
		}

		$post_id     = (int) $arguments['post_id'];
		$object_type = $arguments['object_type'] ?? 'post';

		$target = match ( $object_type ) {
			'user'   => 'user_' . $post_id,
			'term'   => 'term_' . $post_id,
			'option' => 'option',
			default  => $post_id,
		};

		$fields = get_fields( $target );

		if ( $fields === false ) {
			return $this->json_response( [
				'success' => true,
				'post_id' => $post_id,
				'fields'  => [],
				'message' => 'No ACF fields found for this object.',
			] );
		}

		return $this->json_response( [
			'success'     => true,
			'post_id'     => $post_id,
			'object_type' => $object_type,
			'fields'      => $fields,
		] );
	}
}
