<?php
/**
 * Tool: elementor_pro_manage_global_widgets — List and update global/template widgets.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\ElementorPro;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ManageGlobalWidgets extends AbstractTool {

	public function get_capability(): string {
		return 'edit_posts';
	}

	public function get_category(): string {
		return 'elementor_pro';
	}

	public function get_definition(): array {
		return [
			'name'        => 'elementor_pro_manage_global_widgets',
			'description' => 'List Elementor Pro global widgets (saved templates of type widget). Can also update text content of a global widget.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'action' => [
						'type'        => 'string',
						'description' => 'Action to perform.',
						'enum'        => [ 'list', 'update' ],
						'default'     => 'list',
					],
					'widget_id' => [
						'type'        => 'integer',
						'description' => 'Global widget post ID (required for update).',
					],
					'field' => [
						'type'        => 'string',
						'description' => 'Settings field to update (e.g., "title", "editor").',
					],
					'value' => [
						'type'        => 'string',
						'description' => 'New value for the field.',
					],
				],
			],
		];
	}

	public function execute( array $arguments ): array {
		if ( ! defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			throw new \RuntimeException( 'Elementor Pro is not active.' );
		}

		$action = $arguments['action'] ?? 'list';

		if ( $action === 'list' ) {
			return $this->list_global_widgets();
		}

		return $this->update_global_widget( $arguments );
	}

	private function list_global_widgets(): array {
		$widgets = get_posts( [
			'post_type'      => 'elementor_library',
			'posts_per_page' => 100,
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'   => '_elementor_template_type',
					'value' => 'widget',
				],
			],
		] );

		$result = [];
		foreach ( $widgets as $widget ) {
			$result[] = [
				'id'    => $widget->ID,
				'title' => $widget->post_title,
				'date'  => $widget->post_date,
			];
		}

		return $this->json_response( [
			'success' => true,
			'total'   => count( $result ),
			'widgets' => $result,
		] );
	}

	private function update_global_widget( array $arguments ): array {
		$widget_id = (int) ( $arguments['widget_id'] ?? 0 );
		$field     = sanitize_key( $arguments['field'] ?? '' );
		$value     = wp_kses_post( $arguments['value'] ?? '' );

		if ( ! $widget_id || ! $field ) {
			throw new \InvalidArgumentException( 'widget_id and field are required for update.' );
		}

		$data = get_post_meta( $widget_id, '_elementor_data', true );
		if ( empty( $data ) ) {
			throw new \InvalidArgumentException( 'Widget not found or has no Elementor data.' );
		}

		$elements = json_decode( $data, true );
		if ( ! is_array( $elements ) ) {
			throw new \RuntimeException( 'Failed to parse widget data.' );
		}

		$updated = $this->update_first_widget( $elements, $field, $value );

		if ( $updated ) {
			update_post_meta( $widget_id, '_elementor_data', wp_slash( wp_json_encode( $elements ) ) );
		}

		return $this->json_response( [
			'success'   => $updated,
			'widget_id' => $widget_id,
			'field'     => $field,
		] );
	}

	private function update_first_widget( array &$elements, string $field, string $value ): bool {
		foreach ( $elements as &$el ) {
			if ( isset( $el['settings'] ) ) {
				$el['settings'][ $field ] = $value;
				return true;
			}
			if ( ! empty( $el['elements'] ) ) {
				if ( $this->update_first_widget( $el['elements'], $field, $value ) ) {
					return true;
				}
			}
		}
		return false;
	}
}
