<?php
/**
 * Tool: create_post — Create and publish a new blog post.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\Content;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreatePost extends AbstractTool {

	public function get_capability(): string {
		return 'publish_posts';
	}

	public function get_category(): string {
		return 'content';
	}

	public function get_definition(): array {
		return [
			'name'        => 'create_post',
			'description' => 'Create and publish a new blog post with title, content, categories, and tags.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'title' => [
						'type'        => 'string',
						'description' => 'Post title.',
					],
					'content' => [
						'type'        => 'string',
						'description' => 'Post content (supports HTML).',
					],
					'excerpt' => [
						'type'        => 'string',
						'description' => 'Optional post excerpt.',
					],
					'status' => [
						'type'        => 'string',
						'description' => 'Post status: publish, draft, pending.',
						'default'     => 'draft',
						'enum'        => [ 'publish', 'draft', 'pending' ],
					],
					'categories' => [
						'type'        => 'array',
						'items'       => [ 'type' => 'string' ],
						'description' => 'Array of category names or slugs.',
					],
					'tags' => [
						'type'        => 'array',
						'items'       => [ 'type' => 'string' ],
						'description' => 'Array of tag names.',
					],
					'author_id' => [
						'type'        => 'integer',
						'description' => 'Author user ID. Defaults to the admin user.',
					],
				],
				'required' => [ 'title', 'content' ],
			],
		];
	}

	public function execute( array $arguments ): array {
		$post_data = [
			'post_title'   => sanitize_text_field( $arguments['title'] ),
			'post_content' => wp_kses_post( $arguments['content'] ),
			'post_status'  => in_array( $arguments['status'] ?? 'draft', [ 'publish', 'draft', 'pending' ], true )
				? $arguments['status'] : 'draft',
			'post_type'    => 'post',
		];

		if ( ! empty( $arguments['excerpt'] ) ) {
			$post_data['post_excerpt'] = sanitize_textarea_field( $arguments['excerpt'] );
		}

		if ( ! empty( $arguments['author_id'] ) ) {
			$post_data['post_author'] = (int) $arguments['author_id'];
		}

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			throw new \RuntimeException( esc_html( $post_id->get_error_message() ) );
		}

		// Set categories.
		if ( ! empty( $arguments['categories'] ) ) {
			$cat_ids = [];
			foreach ( $arguments['categories'] as $cat ) {
				$term = get_term_by( 'slug', sanitize_title( $cat ), 'category' )
					?: get_term_by( 'name', $cat, 'category' );
				if ( $term ) {
					$cat_ids[] = $term->term_id;
				} else {
					$new_term = wp_insert_term( sanitize_text_field( $cat ), 'category' );
					if ( ! is_wp_error( $new_term ) ) {
						$cat_ids[] = $new_term['term_id'];
					}
				}
			}
			if ( ! empty( $cat_ids ) ) {
				wp_set_post_categories( $post_id, $cat_ids );
			}
		}

		// Set tags.
		if ( ! empty( $arguments['tags'] ) ) {
			$tag_names = array_map( 'sanitize_text_field', $arguments['tags'] );
			wp_set_post_tags( $post_id, $tag_names );
		}

		return $this->json_response( [
			'success' => true,
			'post_id' => $post_id,
			'url'     => get_permalink( $post_id ),
			'status'  => get_post_status( $post_id ),
		] );
	}
}
