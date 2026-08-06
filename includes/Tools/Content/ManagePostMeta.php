<?php
/**
 * Tool: manage_post_meta — Read, add, update, or delete post metadata.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\Content;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ManagePostMeta extends AbstractTool {

	public function get_capability(): string {
		return 'edit_posts';
	}

	public function get_category(): string {
		return 'content';
	}

	public function get_definition(): array {
		return [
			'name'        => 'manage_post_meta',
			'description' => 'Read, add, update, or delete custom fields (post meta) on any post or page.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'post_id' => [
						'type'        => 'integer',
						'description' => 'The post ID.',
					],
					'action' => [
						'type'        => 'string',
						'description' => 'Action to perform.',
						'enum'        => [ 'get', 'set', 'delete' ],
					],
					'key' => [
						'type'        => 'string',
						'description' => 'Meta key name.',
					],
					'value' => [
						'type'        => 'string',
						'description' => 'Meta value (for set action).',
					],
				],
				'required' => [ 'post_id', 'action' ],
			],
		];
	}

	public function execute( array $arguments ): array {
		$post_id = (int) $arguments['post_id'];
		$action  = $arguments['action'];

		if ( ! get_post( $post_id ) ) {
			throw new \InvalidArgumentException( 'Post not found.' );
		}

		return match ( $action ) {
			'get'    => $this->get_meta( $post_id, $arguments['key'] ?? null ),
			'set'    => $this->set_meta( $post_id, $arguments ),
			'delete' => $this->delete_meta( $post_id, $arguments['key'] ?? '' ),
			default  => throw new \InvalidArgumentException( 'Invalid action.' ),
		};
	}

	private function get_meta( int $post_id, ?string $key ): array {
		if ( $key ) {
			$value = get_post_meta( $post_id, sanitize_key( $key ), true );
			return $this->json_response( [ 'key' => $key, 'value' => $value ] );
		}

		$all_meta = get_post_custom( $post_id );
		// Filter out internal WordPress meta.
		$filtered = array_filter( $all_meta, fn( $k ) => ! str_starts_with( $k, '_' ), ARRAY_FILTER_USE_KEY );

		return $this->json_response( [ 'meta' => $filtered ] );
	}

	private function set_meta( int $post_id, array $args ): array {
		if ( empty( $args['key'] ) ) {
			throw new \InvalidArgumentException( 'Meta key is required for set action.' );
		}

		$key   = sanitize_key( $args['key'] );
		$value = sanitize_textarea_field( $args['value'] ?? '' );

		update_post_meta( $post_id, $key, $value );

		return $this->json_response( [
			'success' => true,
			'post_id' => $post_id,
			'key'     => $key,
			'value'   => $value,
		] );
	}

	private function delete_meta( int $post_id, string $key ): array {
		if ( empty( $key ) ) {
			throw new \InvalidArgumentException( 'Meta key is required for delete action.' );
		}

		$key = sanitize_key( $key );
		delete_post_meta( $post_id, $key );

		return $this->json_response( [
			'success' => true,
			'post_id' => $post_id,
			'key'     => $key,
			'action'  => 'deleted',
		] );
	}
}
