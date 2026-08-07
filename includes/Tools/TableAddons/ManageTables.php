<?php
/**
 * Tool: table_addons_manage — List and read table data from Table Addons for Elementor.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\TableAddons;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ManageTables extends AbstractTool {

	public function get_capability(): string {
		return 'edit_posts';
	}

	public function get_category(): string {
		return 'table_addons';
	}

	public function get_definition(): array {
		return [
			'name'        => 'table_addons_manage',
			'description' => 'List pages with table widgets and retrieve table content from Table Addons for Elementor. Can read table data including headers, rows, and cell content.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'action' => [
						'type'        => 'string',
						'description' => 'Action to perform.',
						'enum'        => [ 'list_pages', 'get_table' ],
						'default'     => 'list_pages',
					],
					'post_id' => [
						'type'        => 'integer',
						'description' => 'Post/page ID to extract table data from (for get_table).',
					],
				],
			],
		];
	}

	public function execute( array $arguments ): array {
		if ( ! defined( 'JEstarter_VERSION' ) && ! defined( 'TABLE_ADDONS_FOR_ELEMENTOR_VERSION' ) && ! class_exists( 'TableAddonsForElementor' ) ) {
			throw new \RuntimeException( 'Table Addons for Elementor is not active.' );
		}

		$action = $arguments['action'] ?? 'list_pages';

		if ( $action === 'get_table' ) {
			return $this->get_table_data( $arguments );
		}

		return $this->list_pages_with_tables();
	}

	private function list_pages_with_tables(): array {
		$pages = get_posts( [
			'post_type'      => [ 'post', 'page' ],
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'meta_key'       => '_elementor_data', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		] );

		$results = [];
		foreach ( $pages as $page ) {
			$data = get_post_meta( $page->ID, '_elementor_data', true );
			if ( ! empty( $data ) && ( strpos( $data, 'table' ) !== false || strpos( $data, 'eael-data-table' ) !== false ) ) {
				$results[] = [
					'id'    => $page->ID,
					'title' => $page->post_title,
					'url'   => get_permalink( $page->ID ),
				];
			}
		}

		return $this->json_response( [
			'success' => true,
			'total'   => count( $results ),
			'pages'   => $results,
		] );
	}

	private function get_table_data( array $arguments ): array {
		$post_id = (int) ( $arguments['post_id'] ?? 0 );
		if ( ! $post_id ) {
			throw new \InvalidArgumentException( 'post_id is required.' );
		}

		$data = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $data ) ) {
			throw new \InvalidArgumentException( 'No Elementor data found for this post.' );
		}

		$elements = json_decode( $data, true );
		if ( ! is_array( $elements ) ) {
			throw new \RuntimeException( 'Failed to parse Elementor data.' );
		}

		$tables = [];
		$this->extract_tables( $elements, $tables );

		return $this->json_response( [
			'success'      => true,
			'post_id'      => $post_id,
			'tables_found' => count( $tables ),
			'tables'       => $tables,
		] );
	}

	/**
	 * Recursively extract table widget data from Elementor elements.
	 */
	private function extract_tables( array $elements, array &$tables ): void {
		foreach ( $elements as $element ) {
			$widget_type = $element['widgetType'] ?? '';

			// Match common table widget types.
			if ( strpos( $widget_type, 'table' ) !== false || $widget_type === 'eael-data-table' ) {
				$settings = $element['settings'] ?? [];
				$table    = [
					'widget_type' => $widget_type,
					'element_id'  => $element['id'] ?? '',
				];

				// Extract header rows.
				if ( ! empty( $settings['table_header'] ) ) {
					$table['headers'] = array_map( function ( $cell ) {
						return wp_strip_all_tags( $cell['table_header_col'] ?? $cell['text'] ?? '' );
					}, $settings['table_header'] );
				}

				// Extract body rows.
				if ( ! empty( $settings['table_body'] ) ) {
					$rows    = [];
					$row     = [];
					foreach ( $settings['table_body'] as $cell ) {
						if ( ( $cell['table_body_element'] ?? '' ) === 'row' ) {
							if ( ! empty( $row ) ) {
								$rows[] = $row;
							}
							$row = [];
						} else {
							$row[] = wp_strip_all_tags( $cell['table_body_col'] ?? $cell['text'] ?? '' );
						}
					}
					if ( ! empty( $row ) ) {
						$rows[] = $row;
					}
					$table['rows'] = $rows;
				}

				$tables[] = $table;
			}

			if ( ! empty( $element['elements'] ) ) {
				$this->extract_tables( $element['elements'], $tables );
			}
		}
	}
}
