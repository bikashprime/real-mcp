<?php
/**
 * Tool: astra_manage_custom_layouts — Manage Astra Pro custom layouts (hooks).
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\AstraPro;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ManageCustomLayouts extends AbstractTool {

	public function get_capability(): string {
		return 'manage_options';
	}

	public function get_category(): string {
		return 'astra';
	}

	public function get_definition(): array {
		return [
			'name'        => 'astra_manage_custom_layouts',
			'description' => 'List or create Astra Pro custom layouts (hooks). Custom layouts inject content at specific positions in the theme.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'action' => [
						'type'        => 'string',
						'description' => 'Action to perform.',
						'enum'        => [ 'list', 'create' ],
						'default'     => 'list',
					],
					'title' => [
						'type'        => 'string',
						'description' => 'Layout title (for create).',
					],
					'content' => [
						'type'        => 'string',
						'description' => 'HTML/PHP content for the layout (for create).',
					],
					'hook' => [
						'type'        => 'string',
						'description' => 'Astra hook location (e.g., "astra_head", "astra_header_after", "astra_footer_before").',
					],
				],
			],
		];
	}

	public function execute( array $arguments ): array {
		if ( ! defined( 'ASTRA_EXT_VER' ) ) {
			throw new \RuntimeException( 'Astra Pro is not active.' );
		}

		$action = $arguments['action'] ?? 'list';

		if ( $action === 'list' ) {
			return $this->list_layouts();
		}

		return $this->create_layout( $arguments );
	}

	private function list_layouts(): array {
		$layouts = get_posts( [
			'post_type'      => 'astra-advanced-hook',
			'posts_per_page' => 100,
			'post_status'    => [ 'publish', 'draft' ],
		] );

		$result = [];
		foreach ( $layouts as $layout ) {
			$result[] = [
				'id'     => $layout->ID,
				'title'  => $layout->post_title,
				'status' => $layout->post_status,
				'hook'   => get_post_meta( $layout->ID, 'ast-advanced-hook-location', true ),
			];
		}

		return $this->json_response( [
			'success' => true,
			'total'   => count( $result ),
			'layouts' => $result,
		] );
	}

	private function create_layout( array $arguments ): array {
		$title   = sanitize_text_field( $arguments['title'] ?? '' );
		$content = wp_kses_post( $arguments['content'] ?? '' );
		$hook    = sanitize_text_field( $arguments['hook'] ?? '' );

		if ( empty( $title ) || empty( $hook ) ) {
			throw new \InvalidArgumentException( 'Title and hook are required.' );
		}

		$post_id = wp_insert_post( [
			'post_type'    => 'astra-advanced-hook',
			'post_title'   => $title,
			'post_content' => $content,
			'post_status'  => 'publish',
		], true );

		if ( is_wp_error( $post_id ) ) {
			throw new \RuntimeException( esc_html( $post_id->get_error_message() ) );
		}

		update_post_meta( $post_id, 'ast-advanced-hook-location', $hook );
		update_post_meta( $post_id, 'ast-advanced-hook-layout', '0' );

		return $this->json_response( [
			'success' => true,
			'id'      => $post_id,
			'title'   => $title,
			'hook'    => $hook,
		] );
	}
}
