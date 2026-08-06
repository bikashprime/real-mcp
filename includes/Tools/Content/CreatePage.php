<?php
/**
 * Tool: create_page — Create a new page (FAQ, knowledge base, etc.).
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\Content;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreatePage extends AbstractTool {

	public function get_capability(): string {
		return 'publish_pages';
	}

	public function get_category(): string {
		return 'content';
	}

	public function get_definition(): array {
		return [
			'name'        => 'create_page',
			'description' => 'Create a new WordPress page. Useful for FAQ pages, knowledge bases, landing pages, etc.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'title' => [
						'type'        => 'string',
						'description' => 'Page title.',
					],
					'content' => [
						'type'        => 'string',
						'description' => 'Page content (supports HTML).',
					],
					'status' => [
						'type'        => 'string',
						'description' => 'Page status.',
						'default'     => 'draft',
						'enum'        => [ 'publish', 'draft', 'pending' ],
					],
					'parent_id' => [
						'type'        => 'integer',
						'description' => 'Parent page ID for hierarchical pages.',
					],
					'template' => [
						'type'        => 'string',
						'description' => 'Page template filename.',
					],
				],
				'required' => [ 'title', 'content' ],
			],
		];
	}

	public function execute( array $arguments ): array {
		$post_data = [
			'post_title'   => sanitize_text_field( $arguments['title'] ),
			'post_content' => wp_kses_post( $arguments['content'] ),
			'post_status'  => in_array( $arguments['status'] ?? 'draft', [ 'publish', 'draft', 'pending' ], true )
				? $arguments['status'] : 'draft',
			'post_type'    => 'page',
		];

		if ( ! empty( $arguments['parent_id'] ) ) {
			$post_data['post_parent'] = (int) $arguments['parent_id'];
		}

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			throw new \RuntimeException( esc_html( $post_id->get_error_message() ) );
		}

		if ( ! empty( $arguments['template'] ) ) {
			update_post_meta( $post_id, '_wp_page_template', sanitize_file_name( $arguments['template'] ) );
		}

		return $this->json_response( [
			'success' => true,
			'page_id' => $post_id,
			'url'     => get_permalink( $post_id ),
			'status'  => get_post_status( $post_id ),
		] );
	}
}
