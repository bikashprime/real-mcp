<?php
/**
 * Tool: get_elementor_data — Read Elementor page builder data.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\Elementor;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GetElementorData extends AbstractTool {

	public function get_capability(): string {
		return 'edit_posts';
	}

	public function get_category(): string {
		return 'elementor';
	}

	public function get_definition(): array {
		return [
			'name'        => 'get_elementor_data',
			'description' => 'Retrieve the Elementor page builder data (widgets, sections, content) for a specific page. Requires Elementor to be active.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'post_id' => [
						'type'        => 'integer',
						'description' => 'The page/post ID built with Elementor.',
					],
				],
				'required' => [ 'post_id' ],
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

		// Extract a simplified structure.
		$structure = $this->simplify_elements( $data );

		return $this->json_response( [
			'post_id'   => $post_id,
			'title'     => $post->post_title,
			'sections'  => count( $data ),
			'structure' => $structure,
			'raw_data'  => $data,
		] );
	}

	/**
	 * Simplify Elementor element tree for readability.
	 */
	private function simplify_elements( array $elements, int $depth = 0 ): array {
		$result = [];

		foreach ( $elements as $element ) {
			$entry = [
				'type'     => $element['elType'] ?? 'unknown',
				'widget'   => $element['widgetType'] ?? null,
				'id'       => $element['id'] ?? '',
			];

			// Extract text content from common widgets.
			$settings = $element['settings'] ?? [];
			if ( ! empty( $settings['title'] ) ) {
				$entry['title'] = wp_strip_all_tags( $settings['title'] );
			}
			if ( ! empty( $settings['editor'] ) ) {
				$entry['text'] = mb_substr( wp_strip_all_tags( $settings['editor'] ), 0, 200 );
			}
			if ( ! empty( $settings['text'] ) ) {
				$entry['text'] = mb_substr( wp_strip_all_tags( $settings['text'] ), 0, 200 );
			}

			// Recurse into children.
			if ( ! empty( $element['elements'] ) && $depth < 4 ) {
				$entry['children'] = $this->simplify_elements( $element['elements'], $depth + 1 );
			}

			$result[] = $entry;
		}

		return $result;
	}
}
