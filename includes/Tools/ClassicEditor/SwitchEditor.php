<?php
/**
 * Tool: classic_editor_switch — Switch a post between block and classic editor.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\ClassicEditor;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SwitchEditor extends AbstractTool {

	public function get_capability(): string {
		return 'edit_posts';
	}

	public function get_category(): string {
		return 'classic_editor';
	}

	public function get_definition(): array {
		return [
			'name'        => 'classic_editor_switch',
			'description' => 'Switch a post between block editor and classic editor, or change the site-wide default editor setting.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'action' => [
						'type'        => 'string',
						'description' => 'Action to perform.',
						'enum'        => [ 'switch_post', 'set_default' ],
					],
					'post_id' => [
						'type'        => 'integer',
						'description' => 'Post ID (for switch_post action).',
					],
					'editor' => [
						'type'        => 'string',
						'description' => 'Editor to switch to.',
						'enum'        => [ 'classic', 'block' ],
					],
				],
				'required' => [ 'action', 'editor' ],
			],
		];
	}

	public function execute( array $arguments ): array {
		if ( ! defined( 'CLASSIC_EDITOR_VERSION' ) && ! class_exists( 'Classic_Editor' ) ) {
			throw new \RuntimeException( 'Classic Editor plugin is not active.' );
		}

		$action = $arguments['action'];
		$editor = $arguments['editor'];

		if ( $action === 'set_default' ) {
			update_option( 'classic-editor-replace', $editor );
			return $this->json_response( [
				'success'        => true,
				'default_editor' => $editor,
				'message'        => "Default editor set to {$editor}.",
			] );
		}

		// switch_post
		$post_id = (int) ( $arguments['post_id'] ?? 0 );
		if ( ! $post_id || ! get_post( $post_id ) ) {
			throw new \InvalidArgumentException( 'Valid post_id is required for switch_post.' );
		}

		$meta_value = $editor === 'classic' ? 'classic-editor' : 'block-editor';
		update_post_meta( $post_id, 'classic-editor-remember', $meta_value );

		return $this->json_response( [
			'success' => true,
			'post_id' => $post_id,
			'editor'  => $editor,
			'message' => "Post {$post_id} will now open in the {$editor} editor.",
		] );
	}
}
