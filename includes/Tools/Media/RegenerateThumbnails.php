<?php
/**
 * Tool: regenerate_thumbnails — Regenerate image thumbnails.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\Media;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RegenerateThumbnails extends AbstractTool {

	public function get_capability(): string {
		return 'manage_options';
	}

	public function get_category(): string {
		return 'media';
	}

	public function get_definition(): array {
		return [
			'name'        => 'regenerate_thumbnails',
			'description' => 'Regenerate thumbnails for specific images or all images in the media library. Useful after theme changes.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'attachment_ids' => [
						'type'        => 'array',
						'items'       => [ 'type' => 'integer' ],
						'description' => 'Specific attachment IDs to regenerate. If empty, processes recent images.',
					],
					'limit' => [
						'type'        => 'integer',
						'description' => 'Max images to process (for safety). Default 20.',
						'default'     => 20,
					],
				],
			],
		];
	}

	public function execute( array $arguments ): array {
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$limit = min( (int) ( $arguments['limit'] ?? 20 ), 50 );
		$ids   = $arguments['attachment_ids'] ?? [];

		if ( empty( $ids ) ) {
			$ids = get_posts( [
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'posts_per_page' => $limit,
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'DESC',
			] );
		} else {
			$ids = array_slice( array_map( 'intval', $ids ), 0, $limit );
		}

		$results = [];
		foreach ( $ids as $id ) {
			$file = get_attached_file( $id );
			if ( ! $file || ! file_exists( $file ) ) {
				$results[] = [ 'id' => $id, 'success' => false, 'error' => 'File not found.' ];
				continue;
			}

			$metadata = wp_generate_attachment_metadata( $id, $file );
			if ( is_wp_error( $metadata ) || empty( $metadata ) ) {
				$results[] = [ 'id' => $id, 'success' => false, 'error' => 'Failed to regenerate.' ];
				continue;
			}

			wp_update_attachment_metadata( $id, $metadata );
			$results[] = [
				'id'      => $id,
				'success' => true,
				'sizes'   => count( $metadata['sizes'] ?? [] ),
			];
		}

		return $this->json_response( [
			'processed' => count( $results ),
			'success'   => count( array_filter( $results, fn( $r ) => $r['success'] ) ),
			'results'   => $results,
		] );
	}
}
