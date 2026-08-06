<?php
/**
 * Tool: get_post_content — Get raw and rendered content of a post for editing.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\Content;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GetPostContent extends AbstractTool {

	public function get_capability(): string {
		return 'edit_posts';
	}

	public function get_category(): string {
		return 'content';
	}

	public function get_definition(): array {
		return [
			'name'        => 'get_post_content',
			'description' => 'Get the full raw HTML content and metadata of any post (published, draft, or pending) for editing purposes. Unlike get_post which only reads published content, this tool provides edit-level access.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'id' => [
						'type'        => 'integer',
						'description' => 'Post ID.',
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

		return $this->json_response( [
			'id'              => $post->ID,
			'title'           => $post->post_title,
			'slug'            => $post->post_name,
			'status'          => $post->post_status,
			'type'            => $post->post_type,
			'content_raw'     => $post->post_content,
			'excerpt'         => $post->post_excerpt,
			'date'            => $post->post_date_gmt,
			'modified'        => $post->post_modified_gmt,
			'author_id'       => (int) $post->post_author,
			'author_name'     => get_the_author_meta( 'display_name', $post->post_author ),
			'url'             => get_permalink( $post->ID ),
			'featured_image'  => get_the_post_thumbnail_url( $post->ID, 'full' ) ?: null,
			'categories'      => wp_list_pluck( get_the_category( $post->ID ) ?: [], 'name' ),
			'tags'            => wp_list_pluck( get_the_tags( $post->ID ) ?: [], 'name' ),
			'word_count'      => str_word_count( wp_strip_all_tags( $post->post_content ) ),
			'custom_fields'   => get_post_custom( $post->ID ),
		] );
	}
}
