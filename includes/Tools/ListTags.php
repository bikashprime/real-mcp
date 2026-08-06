<?php
/**
 * Tool: list_tags — Retrieve all post tags.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ListTags extends AbstractTool {

	public function get_category(): string {
		return 'content';
	}

	public function get_definition(): array {
		return [
			'name'        => 'list_tags',
			'description' => 'List all post tags with their names, slugs, and post counts.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'hide_empty' => [
						'type'        => 'boolean',
						'description' => 'Whether to hide tags with no posts.',
						'default'     => true,
					],
				],
			],
		];
	}

	public function execute( array $arguments ): array {
		$hide_empty = $arguments['hide_empty'] ?? true;

		$tags = get_tags( [
			'hide_empty' => (bool) $hide_empty,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );

		$data = [];
		if ( is_array( $tags ) ) {
			foreach ( $tags as $tag ) {
				$data[] = [
					'id'          => $tag->term_id,
					'name'        => $tag->name,
					'slug'        => $tag->slug,
					'description' => $tag->description,
					'count'       => $tag->count,
				];
			}
		}

		return [
			[
				'type' => 'text',
				'text' => wp_json_encode( [ 'tags' => $data ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
			],
		];
	}
}
