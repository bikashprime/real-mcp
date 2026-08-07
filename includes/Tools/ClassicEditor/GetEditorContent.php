<?php
/**
 * Tool: classic_editor_get_content — Get post content in classic editor format.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\ClassicEditor;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GetEditorContent extends AbstractTool {

	public function get_capability(): string {
		return 'edit_posts';
	}

	public function get_category(): string {
		return 'classic_editor';
	}

	public function get_definition(): array {
		return [
			'name'        => 'classic_editor_get_content',
			'description' => 'Get a post\'s content in classic editor format (raw HTML). Shows which editor is default and whether block/classic is forced.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'post_id' => [
						'type'        => 'integer',
						'description' => 'The post ID.',
					],
				],
				'required' => [ 'post_id' ],
			],
		];
	}

	public function execute( array $arguments ): array {
		$post_id = (int) $arguments['post_id'];
		$post    = get_post( $post_id );

		if ( ! $post ) {
			throw new \InvalidArgumentException( 'Post not found.' );
		}

		$classic_active = defined( 'CLASSIC_EDITOR_VERSION' ) || class_exists( 'Classic_Editor' );
		$default_editor = get_option( 'classic-editor-replace', 'classic' );
		$allow_switch   = get_option( 'classic-editor-allow-users', 'allow' );
		$post_editor    = get_post_meta( $post_id, 'classic-editor-remember', true );

		return $this->json_response( [
			'success'          => true,
			'post_id'          => $post_id,
			'title'            => $post->post_title,
			'content'          => $post->post_content,
			'has_blocks'       => has_blocks( $post->post_content ),
			'word_count'       => str_word_count( wp_strip_all_tags( $post->post_content ) ),
			'editor_settings'  => [
				'classic_editor_active' => $classic_active,
				'default_editor'        => $default_editor,
				'allow_user_switch'     => $allow_switch === 'allow',
				'post_editor'           => $post_editor ?: $default_editor,
			],
		] );
	}
}
