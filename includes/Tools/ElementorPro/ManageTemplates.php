<?php
/**
 * Tool: elementor_pro_manage_templates — List, export, and manage Elementor Pro templates.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\ElementorPro;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ManageTemplates extends AbstractTool {

	public function get_capability(): string {
		return 'edit_posts';
	}

	public function get_category(): string {
		return 'elementor_pro';
	}

	public function get_definition(): array {
		return [
			'name'        => 'elementor_pro_manage_templates',
			'description' => 'List Elementor Pro saved templates (sections, pages, headers, footers, popups). Filter by type.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'type' => [
						'type'        => 'string',
						'description' => 'Template type to filter.',
						'enum'        => [ 'all', 'page', 'section', 'header', 'footer', 'single', 'archive', 'popup', 'loop-item' ],
						'default'     => 'all',
					],
				],
			],
		];
	}

	public function execute( array $arguments ): array {
		if ( ! defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			throw new \RuntimeException( 'Elementor Pro is not active.' );
		}

		$type       = $arguments['type'] ?? 'all';
		$query_args = [
			'post_type'      => 'elementor_library',
			'posts_per_page' => 100,
			'post_status'    => 'publish',
		];

		if ( $type !== 'all' ) {
			$query_args['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'   => '_elementor_template_type',
					'value' => sanitize_text_field( $type ),
				],
			];
		}

		$templates = get_posts( $query_args );
		$result    = [];

		foreach ( $templates as $tpl ) {
			$tpl_type = get_post_meta( $tpl->ID, '_elementor_template_type', true );
			$result[] = [
				'id'       => $tpl->ID,
				'title'    => $tpl->post_title,
				'type'     => $tpl_type ?: 'unknown',
				'date'     => $tpl->post_date,
				'modified' => $tpl->post_modified,
				'status'   => $tpl->post_status,
			];
		}

		return $this->json_response( [
			'success'   => true,
			'total'     => count( $result ),
			'templates' => $result,
		] );
	}
}
