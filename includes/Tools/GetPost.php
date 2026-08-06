<?php
/**
 * Tool: get_post — Retrieve a single post by ID or slug.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GetPost extends AbstractTool {

	public function get_category(): string {
		return 'content';
	}

	public function get_definition(): array {
		return [
			'name'        => 'get_post',
			'description' => 'Retrieve the full content of a single published post by its ID or slug.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'id' => [
						'type'        => 'integer',
						'description' => 'The post ID.',
					],
					'slug' => [
						'type'        => 'string',
						'description' => 'The post slug.',
					],
				],
			],
		];
	}

	public function execute( array $arguments ): array {
		$post = null;

		if ( ! empty( $arguments['id'] ) ) {
			$post = get_post( (int) $arguments['id'] );
		} elseif ( ! empty( $arguments['slug'] ) ) {
			$posts = get_posts( [
				'name'        => sanitize_title( $arguments['slug'] ),
				'post_type'   => 'post',
				'post_status' => 'publish',
				'numberposts' => 1,
			] );
			$post = $posts[0] ?? null;
		}

		if ( ! $post || $post->post_status !== 'publish' ) {
			throw new \InvalidArgumentException( 'Post not found or not published.' );
		}

		$content = wp_strip_all_tags( apply_filters( 'the_content', $post->post_content ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter intentionally used to render content.

		$data = [
			'id'         => $post->ID,
			'title'      => $post->post_title,
			'slug'       => $post->post_name,
			'content'    => $content,
			'excerpt'    => wp_strip_all_tags( get_the_excerpt( $post ) ),
			'date'       => $post->post_date_gmt,
			'modified'   => $post->post_modified_gmt,
			'url'        => get_permalink( $post ),
			'author'     => get_the_author_meta( 'display_name', $post->post_author ),
			'categories' => wp_list_pluck( get_the_category( $post->ID ), 'name' ),
			'tags'       => wp_list_pluck( get_the_tags( $post->ID ) ?: [], 'name' ),
		];

		return [
			[
				'type' => 'text',
				'text' => wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
			],
		];
	}
}
