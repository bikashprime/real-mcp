<?php
/**
 * Tool: search_content — Search posts by keyword.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SearchContent extends AbstractTool {

	public function get_category(): string {
		return 'content';
	}

	public function get_definition(): array {
		return [
			'name'        => 'search_content',
			'description' => 'Search published posts and pages by keyword.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'query' => [
						'type'        => 'string',
						'description' => 'Search query string.',
					],
					'post_type' => [
						'type'        => 'string',
						'description' => 'Post type to search: post, page, or any.',
						'default'     => 'any',
						'enum'        => [ 'post', 'page', 'any' ],
					],
					'per_page' => [
						'type'        => 'integer',
						'description' => 'Number of results to return (max 50).',
						'default'     => 10,
					],
				],
				'required' => [ 'query' ],
			],
		];
	}

	public function execute( array $arguments ): array {
		$search   = sanitize_text_field( $arguments['query'] ?? '' );
		$per_page = min( (int) ( $arguments['per_page'] ?? 10 ), 50 );

		if ( empty( $search ) ) {
			throw new \InvalidArgumentException( 'Search query is required.' );
		}

		$post_type = $arguments['post_type'] ?? 'any';
		if ( ! in_array( $post_type, [ 'post', 'page', 'any' ], true ) ) {
			$post_type = 'any';
		}

		$query = new \WP_Query( [
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			's'              => $search,
			'posts_per_page' => $per_page,
		] );

		$results = [];
		foreach ( $query->posts as $post ) {
			$results[] = [
				'id'        => $post->ID,
				'title'     => $post->post_title,
				'slug'      => $post->post_name,
				'type'      => $post->post_type,
				'excerpt'   => wp_strip_all_tags( get_the_excerpt( $post ) ),
				'date'      => $post->post_date_gmt,
				'url'       => get_permalink( $post ),
			];
		}

		return [
			[
				'type' => 'text',
				'text' => wp_json_encode( [
					'results' => $results,
					'total'   => (int) $query->found_posts,
					'query'   => $search,
				], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
			],
		];
	}
}
