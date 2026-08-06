<?php
/**
 * Tool: get_posts — Retrieve a list of published posts.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GetPosts extends AbstractTool {

	public function get_category(): string {
		return 'content';
	}

	public function get_definition(): array {
		return [
			'name'        => 'get_posts',
			'description' => 'Retrieve a list of published posts from the WordPress site. Supports pagination and filtering by category or tag.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'per_page' => [
						'type'        => 'integer',
						'description' => 'Number of posts to return (max 100).',
						'default'     => 10,
					],
					'page' => [
						'type'        => 'integer',
						'description' => 'Page number for pagination.',
						'default'     => 1,
					],
					'category' => [
						'type'        => 'string',
						'description' => 'Filter by category slug.',
					],
					'tag' => [
						'type'        => 'string',
						'description' => 'Filter by tag slug.',
					],
					'orderby' => [
						'type'        => 'string',
						'description' => 'Order by field: date, title, modified.',
						'default'     => 'date',
						'enum'        => [ 'date', 'title', 'modified' ],
					],
					'order' => [
						'type'        => 'string',
						'description' => 'Sort order: ASC or DESC.',
						'default'     => 'DESC',
						'enum'        => [ 'ASC', 'DESC' ],
					],
				],
			],
		];
	}

	public function execute( array $arguments ): array {
		$per_page = min( (int) ( $arguments['per_page'] ?? 10 ), 100 );
		$page     = max( (int) ( $arguments['page'] ?? 1 ), 1 );
		$orderby  = in_array( $arguments['orderby'] ?? 'date', [ 'date', 'title', 'modified' ], true )
			? $arguments['orderby']
			: 'date';
		$order    = in_array( $arguments['order'] ?? 'DESC', [ 'ASC', 'DESC' ], true )
			? $arguments['order']
			: 'DESC';

		$query_args = [
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => $orderby,
			'order'          => $order,
		];

		if ( ! empty( $arguments['category'] ) ) {
			$query_args['category_name'] = sanitize_text_field( $arguments['category'] );
		}

		if ( ! empty( $arguments['tag'] ) ) {
			$query_args['tag'] = sanitize_text_field( $arguments['tag'] );
		}

		$query = new \WP_Query( $query_args );
		$posts = [];

		foreach ( $query->posts as $post ) {
			$posts[] = [
				'id'         => $post->ID,
				'title'      => $post->post_title,
				'slug'       => $post->post_name,
				'excerpt'    => wp_strip_all_tags( get_the_excerpt( $post ) ),
				'date'       => $post->post_date_gmt,
				'modified'   => $post->post_modified_gmt,
				'url'        => get_permalink( $post ),
				'categories' => wp_list_pluck( get_the_category( $post->ID ), 'slug' ),
				'tags'       => wp_list_pluck( get_the_tags( $post->ID ) ?: [], 'slug' ),
			];
		}

		return [
			[
				'type' => 'text',
				'text' => wp_json_encode( [
					'posts'       => $posts,
					'total'       => (int) $query->found_posts,
					'total_pages' => (int) $query->max_num_pages,
					'page'        => $page,
				], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
			],
		];
	}
}
