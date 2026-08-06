<?php
/**
 * Tool: list_categories — Retrieve all post categories.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ListCategories extends AbstractTool {

	public function get_category(): string {
		return 'content';
	}

	public function get_definition(): array {
		return [
			'name'        => 'list_categories',
			'description' => 'List all post categories with their names, slugs, and post counts.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'hide_empty' => [
						'type'        => 'boolean',
						'description' => 'Whether to hide categories with no posts.',
						'default'     => true,
					],
				],
			],
		];
	}

	public function execute( array $arguments ): array {
		$hide_empty = $arguments['hide_empty'] ?? true;

		$categories = get_categories( [
			'hide_empty' => (bool) $hide_empty,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );

		$data = [];
		foreach ( $categories as $category ) {
			$data[] = [
				'id'          => $category->term_id,
				'name'        => $category->name,
				'slug'        => $category->slug,
				'description' => $category->description,
				'count'       => $category->count,
				'parent'      => $category->parent,
			];
		}

		return [
			[
				'type' => 'text',
				'text' => wp_json_encode( [ 'categories' => $data ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
			],
		];
	}
}
