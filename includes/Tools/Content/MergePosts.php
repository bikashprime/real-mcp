<?php
/**
 * Tool: merge_posts — Merge two posts into one.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\Content;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MergePosts extends AbstractTool {

	public function get_capability(): string {
		return 'edit_posts';
	}

	public function get_category(): string {
		return 'content';
	}

	public function get_definition(): array {
		return [
			'name'        => 'merge_posts',
			'description' => 'Merge two posts into one. Content from the secondary post is appended to the primary post, and the secondary post is trashed.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'primary_id' => [
						'type'        => 'integer',
						'description' => 'The post ID to keep (content will be merged into this).',
					],
					'secondary_id' => [
						'type'        => 'integer',
						'description' => 'The post ID to merge from (will be trashed after merge).',
					],
					'separator' => [
						'type'        => 'string',
						'description' => 'HTML separator between merged content.',
						'default'     => '<hr>',
					],
				],
				'required' => [ 'primary_id', 'secondary_id' ],
			],
		];
	}

	public function execute( array $arguments ): array {
		$primary   = get_post( (int) $arguments['primary_id'] );
		$secondary = get_post( (int) $arguments['secondary_id'] );

		if ( ! $primary || ! $secondary ) {
			throw new \InvalidArgumentException( 'One or both posts not found.' );
		}

		$separator = wp_kses_post( $arguments['separator'] ?? '<hr>' );
		$merged_content = $primary->post_content . "\n\n" . $separator . "\n\n" . $secondary->post_content;

		// Merge tags from both posts.
		$primary_tags   = wp_get_post_tags( $primary->ID, [ 'fields' => 'names' ] );
		$secondary_tags = wp_get_post_tags( $secondary->ID, [ 'fields' => 'names' ] );
		$all_tags       = array_unique( array_merge(
			is_array( $primary_tags ) ? $primary_tags : [],
			is_array( $secondary_tags ) ? $secondary_tags : []
		) );

		// Update primary post.
		$result = wp_update_post( [
			'ID'           => $primary->ID,
			'post_content' => $merged_content,
		], true );

		if ( is_wp_error( $result ) ) {
			throw new \RuntimeException( esc_html( $result->get_error_message() ) );
		}

		// Set merged tags.
		if ( ! empty( $all_tags ) ) {
			wp_set_post_tags( $primary->ID, $all_tags );
		}

		// Trash the secondary post.
		wp_trash_post( $secondary->ID );

		return $this->json_response( [
			'success'      => true,
			'primary_id'   => $primary->ID,
			'secondary_id' => $secondary->ID,
			'action'       => 'merged_and_secondary_trashed',
			'url'          => get_permalink( $primary->ID ),
		] );
	}
}
