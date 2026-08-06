<?php
/**
 * Tool: upload_media — Upload media from a URL to the media library.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\Media;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UploadMedia extends AbstractTool {

	public function get_capability(): string {
		return 'upload_files';
	}

	public function get_category(): string {
		return 'media';
	}

	public function get_definition(): array {
		return [
			'name'        => 'upload_media',
			'description' => 'Upload an image or file to the WordPress media library from a URL. Optionally set alt text and attach to a post.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'url' => [
						'type'        => 'string',
						'description' => 'URL of the file to download and add to media library.',
					],
					'title' => [
						'type'        => 'string',
						'description' => 'Media title.',
					],
					'alt_text' => [
						'type'        => 'string',
						'description' => 'Alt text for the image.',
					],
					'caption' => [
						'type'        => 'string',
						'description' => 'Media caption.',
					],
					'post_id' => [
						'type'        => 'integer',
						'description' => 'Attach to this post ID.',
					],
				],
				'required' => [ 'url' ],
			],
		];
	}

	public function execute( array $arguments ): array {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$url     = esc_url_raw( $arguments['url'] );
		$post_id = (int) ( $arguments['post_id'] ?? 0 );

		// Download and sideload the file.
		$tmp = download_url( $url );
		if ( is_wp_error( $tmp ) ) {
			throw new \RuntimeException( esc_html( 'Failed to download file: ' . $tmp->get_error_message() ) );
		}

		$filename  = basename( wp_parse_url( $url, PHP_URL_PATH ) );
		$file_array = [
			'name'     => sanitize_file_name( $filename ),
			'tmp_name' => $tmp,
		];

		$attachment_id = media_handle_sideload( $file_array, $post_id );

		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $tmp );
			throw new \RuntimeException( esc_html( 'Failed to upload: ' . $attachment_id->get_error_message() ) );
		}

		// Set title.
		if ( ! empty( $arguments['title'] ) ) {
			wp_update_post( [
				'ID'         => $attachment_id,
				'post_title' => sanitize_text_field( $arguments['title'] ),
			] );
		}

		// Set alt text.
		if ( ! empty( $arguments['alt_text'] ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $arguments['alt_text'] ) );
		}

		// Set caption.
		if ( ! empty( $arguments['caption'] ) ) {
			wp_update_post( [
				'ID'           => $attachment_id,
				'post_excerpt' => sanitize_textarea_field( $arguments['caption'] ),
			] );
		}

		return $this->json_response( [
			'success'       => true,
			'attachment_id' => $attachment_id,
			'url'           => wp_get_attachment_url( $attachment_id ),
			'filename'      => get_attached_file( $attachment_id ),
		] );
	}
}
