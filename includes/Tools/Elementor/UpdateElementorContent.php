<?php
/**
 * Tool: update_elementor_content — Update text content within Elementor widgets.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\Elementor;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UpdateElementorContent extends AbstractTool {

	public function get_capability(): string {
		return 'edit_posts';
	}

	public function get_category(): string {
		return 'elementor';
	}

	public function get_definition(): array {
		return [
			'name'        => 'update_elementor_content',
			'description' => 'Update text content of specific Elementor widgets by their element ID. Useful for updating headings, text editors, buttons text, etc.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'post_id' => [
						'type'        => 'integer',
						'description' => 'The page/post ID.',
					],
					'updates' => [
						'type'        => 'array',
						'description' => 'Array of widget content updates.',
						'items'       => [
							'type'       => 'object',
							'properties' => [
								'element_id' => [
									'type'        => 'string',
									'description' => 'The Elementor element ID to update.',
								],
								'field' => [
									'type'        => 'string',
									'description' => 'Settings field to update (e.g., "title", "editor", "text", "button_text").',
								],
								'value' => [
									'type'        => 'string',
									'description' => 'New value for the field.',
								],
							],
							'required' => [ 'element_id', 'field', 'value' ],
						],
					],
				],
				'required' => [ 'post_id', 'updates' ],
			],
		];
	}

	public function execute( array $arguments ): array {
		if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
			throw new \RuntimeException( 'Elementor is not active.' );
		}

		$post_id = (int) $arguments['post_id'];
		$post    = get_post( $post_id );

		if ( ! $post ) {
			throw new \InvalidArgumentException( 'Post not found.' );
		}

		$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $elementor_data ) ) {
			throw new \InvalidArgumentException( 'This page does not have Elementor data.' );
		}

		$data = json_decode( $elementor_data, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new \RuntimeException( 'Failed to parse Elementor data.' );
		}

		$updated_count = 0;
		foreach ( $arguments['updates'] as $update ) {
			$element_id = sanitize_text_field( $update['element_id'] );
			$field      = sanitize_key( $update['field'] );
			$value      = wp_kses_post( $update['value'] );

			if ( $this->update_element( $data, $element_id, $field, $value ) ) {
				$updated_count++;
			}
		}

		if ( $updated_count > 0 ) {
			update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );

			// Clear Elementor cache for this post.
			if ( class_exists( '\Elementor\Plugin' ) ) {
				\Elementor\Plugin::$instance->files_manager->clear_cache();
			}
		}

		return $this->json_response( [
			'success'      => true,
			'post_id'      => $post_id,
			'updates_applied' => $updated_count,
			'updates_requested' => count( $arguments['updates'] ),
		] );
	}

	/**
	 * Recursively find and update an element by ID.
	 */
	private function update_element( array &$elements, string $id, string $field, string $value ): bool {
		foreach ( $elements as &$element ) {
			if ( ( $element['id'] ?? '' ) === $id ) {
				$element['settings'][ $field ] = $value;
				return true;
			}

			if ( ! empty( $element['elements'] ) ) {
				if ( $this->update_element( $element['elements'], $id, $field, $value ) ) {
					return true;
				}
			}
		}
		return false;
	}
}
