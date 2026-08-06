<?php
/**
 * Tool: delete_post — Trash or permanently delete a post.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\Content;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DeletePost extends AbstractTool {

	public function get_capability(): string {
		return 'delete_posts';
	}

	public function get_category(): string {
		return 'content';
	}

	public function get_definition(): array {
		return [
			'name'        => 'delete_post',
			'description' => 'Move a post to trash or permanently delete it.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'id' => [
						'type'        => 'integer',
						'description' => 'The post ID to delete.',
					],
					'force' => [
						'type'        => 'boolean',
						'description' => 'If true, permanently deletes. Otherwise moves to trash.',
						'default'     => false,
					],
				],
				'required' => [ 'id' ],
			],
		];
	}

	public function execute( array $arguments ): array {
		$post_id = (int) $arguments['id'];
		$force   = (bool) ( $arguments['force'] ?? false );

		$post = get_post( $post_id );
		if ( ! $post ) {
			throw new \InvalidArgumentException( 'Post not found.' );
		}

		$result = wp_delete_post( $post_id, $force );

		if ( ! $result ) {
			throw new \RuntimeException( 'Failed to delete post.' );
		}

		return $this->json_response( [
			'success' => true,
			'post_id' => $post_id,
			'action'  => $force ? 'permanently_deleted' : 'trashed',
		] );
	}
}
