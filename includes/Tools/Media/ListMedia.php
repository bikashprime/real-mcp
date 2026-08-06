<?php
/**
 * Tool: list_media — List media library items.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\Media;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ListMedia extends AbstractTool {

	public function get_capability(): string {
		return 'upload_files';
	}

	public function get_category(): string {
		return 'media';
	}

	public function get_definition(): array {
		return [
			'name'        => 'list_media',
			'description' => 'List media library items with filtering by type, date, or attachment to posts. Returns URLs, dimensions, alt text.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'mime_type' => [
						'type'        => 'string',
						'description' => 'Filter by MIME type prefix (e.g., "image", "video", "application/pdf").',
					],
					'per_page' => [
						'type'        => 'integer',
						'description' => 'Number of items to return (max 100).',
						'default'     => 20,
					],
					'page' => [
						'type'        => 'integer',
						'description' => 'Page number.',
						'default'     => 1,
					],
					'post_parent' => [
						'type'        => 'integer',
						'description' => 'Only items attached to this post ID.',
					],
					'missing_alt' => [
						'type'        => 'boolean',
						'description' => 'If true, only return images missing alt text.',
						'default'     => false,
					],
				],
			],
		];
	}

	public function execute( array $arguments ): array {
		$per_page = min( (int) ( $arguments['per_page'] ?? 20 ), 100 );
		$page     = max( (int) ( $arguments['page'] ?? 1 ), 1 );

		$query_args = [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		if ( ! empty( $arguments['mime_type'] ) ) {
			$query_args['post_mime_type'] = sanitize_text_field( $arguments['mime_type'] );
		}

		if ( ! empty( $arguments['post_parent'] ) ) {
			$query_args['post_parent'] = (int) $arguments['post_parent'];
		}

		if ( ! empty( $arguments['missing_alt'] ) ) {
			$query_args['post_mime_type'] = 'image';
			$query_args['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required to find images missing alt text.
				'relation' => 'OR',
				[
					'key'     => '_wp_attachment_image_alt',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => '_wp_attachment_image_alt',
					'value'   => '',
					'compare' => '=',
				],
			];
		}

		$query = new \WP_Query( $query_args );
		$items = [];

		foreach ( $query->posts as $attachment ) {
			$meta = wp_get_attachment_metadata( $attachment->ID );
			$item = [
				'id'        => $attachment->ID,
				'title'     => $attachment->post_title,
				'url'       => wp_get_attachment_url( $attachment->ID ),
				'mime_type' => $attachment->post_mime_type,
				'alt_text'  => get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
				'caption'   => $attachment->post_excerpt,
				'date'      => $attachment->post_date_gmt,
				'filesize'  => filesize( get_attached_file( $attachment->ID ) ) ?: 0,
			];

			if ( ! empty( $meta['width'] ) ) {
				$item['width']  = $meta['width'];
				$item['height'] = $meta['height'];
			}

			$items[] = $item;
		}

		return $this->json_response( [
			'items'       => $items,
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
			'page'        => $page,
		] );
	}
}
