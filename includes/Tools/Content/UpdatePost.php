<?php
/**
 * Tool: update_post — Update an existing post.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\Content;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UpdatePost extends AbstractTool {

	public function get_capability(): string {
		return 'edit_posts';
	}

	public function get_category(): string {
		return 'content';
	}

	public function get_definition(): array {
		return [
			'name'        => 'update_post',
			'description' => 'Update an existing post\'s title, content, excerpt, status, categories, or tags.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'id' => [
						'type'        => 'integer',
						'description' => 'The post ID to update.',
					],
					'title' => [
						'type'        => 'string',
						'description' => 'New post title.',
					],
					'content' => [
						'type'        => 'string',
						'description' => 'New post content (supports HTML).',
					],
					'excerpt' => [
						'type'        => 'string',
						'description' => 'New post excerpt.',
					],
					'status' => [
						'type'        => 'string',
						'description' => 'New post status.',
						'enum'        => [ 'publish', 'draft', 'pending', 'private' ],
					],
					'categories' => [
						'type'        => 'array',
						'items'       => [ 'type' => 'string' ],
						'description' => 'Replace categories with this list of names/slugs.',
					],
					'tags' => [
						'type'        => 'array',
						'items'       => [ 'type' => 'string' ],
						'description' => 'Replace tags with this list.',
					],
				],
				'required' => [ 'id' ],
			],
		];
	}

	public function execute( array $arguments ): array {
		$post_id = (int) $arguments['id'];
		$post    = get_post( $post_id );

		if ( ! $post ) {
			throw new \InvalidArgumentException( 'Post not found.' );
		}

		$post_data = [ 'ID' => $post_id ];

		if ( isset( $arguments['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $arguments['title'] );
		}
		if ( isset( $arguments['content'] ) ) {
			$post_data['post_content'] = wp_kses_post( $arguments['content'] );
		}
		if ( isset( $arguments['excerpt'] ) ) {
			$post_data['post_excerpt'] = sanitize_textarea_field( $arguments['excerpt'] );
		}
		if ( isset( $arguments['status'] ) && in_array( $arguments['status'], [ 'publish', 'draft', 'pending', 'private' ], true ) ) {
			$post_data['post_status'] = $arguments['status'];
		}

		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			throw new \RuntimeException( esc_html( $result->get_error_message() ) );
		}

		// Update categories.
		if ( isset( $arguments['categories'] ) ) {
			$cat_ids = [];
			foreach ( $arguments['categories'] as $cat ) {
				$term = get_term_by( 'slug', sanitize_title( $cat ), 'category' )
					?: get_term_by( 'name', $cat, 'category' );
				if ( $term ) {
					$cat_ids[] = $term->term_id;
				}
			}
			wp_set_post_categories( $post_id, $cat_ids );
		}

		// Update tags.
		if ( isset( $arguments['tags'] ) ) {
			wp_set_post_tags( $post_id, array_map( 'sanitize_text_field', $arguments['tags'] ) );
		}

		return $this->json_response( [
			'success'  => true,
			'post_id'  => $post_id,
			'url'      => get_permalink( $post_id ),
			'modified' => get_post_modified_time( 'c', true, $post_id ),
		] );
	}
}
