<?php
/**
 * Tool: update_alt_text — Update alt text for media attachments.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\Media;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UpdateAltText extends AbstractTool {

	public function get_capability(): string {
		return 'upload_files';
	}

	public function get_category(): string {
		return 'media';
	}

	public function get_definition(): array {
		return [
			'name'        => 'update_alt_text',
			'description' => 'Update the alt text for one or more images in the media library. Essential for accessibility (WCAG) compliance.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'updates' => [
						'type'        => 'array',
						'description' => 'Array of alt text updates.',
						'items'       => [
							'type'       => 'object',
							'properties' => [
								'attachment_id' => [
									'type'        => 'integer',
									'description' => 'Attachment ID.',
								],
								'alt_text' => [
									'type'        => 'string',
									'description' => 'New alt text.',
								],
							],
							'required' => [ 'attachment_id', 'alt_text' ],
						],
					],
				],
				'required' => [ 'updates' ],
			],
		];
	}

	public function execute( array $arguments ): array {
		$results = [];

		foreach ( $arguments['updates'] as $update ) {
			$id  = (int) $update['attachment_id'];
			$alt = sanitize_text_field( $update['alt_text'] );

			$attachment = get_post( $id );
			if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
				$results[] = [ 'id' => $id, 'success' => false, 'error' => 'Attachment not found.' ];
				continue;
			}

			update_post_meta( $id, '_wp_attachment_image_alt', $alt );
			$results[] = [
				'id'       => $id,
				'success'  => true,
				'alt_text' => $alt,
				'url'      => wp_get_attachment_url( $id ),
			];
		}

		return $this->json_response( [
			'updated' => count( array_filter( $results, fn( $r ) => $r['success'] ) ),
			'results' => $results,
		] );
	}
}
