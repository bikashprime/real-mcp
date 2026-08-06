<?php
/**
 * Abstract base tool — provides defaults for common tool methods.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AbstractTool implements ToolInterface {

	/**
	 * Default capability is 'read' (safe for read-only tools).
	 */
	public function get_capability(): string {
		return 'read';
	}

	/**
	 * Default category is 'general'.
	 */
	public function get_category(): string {
		return 'general';
	}

	/**
	 * Helper to build a successful JSON text response.
	 *
	 * @param mixed $data Data to encode as JSON.
	 * @return array MCP content blocks.
	 */
	protected function json_response( mixed $data ): array {
		return [
			[
				'type' => 'text',
				'text' => wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
			],
		];
	}

	/**
	 * Helper to build a plain text response.
	 *
	 * @param string $text Response text.
	 * @return array MCP content blocks.
	 */
	protected function text_response( string $text ): array {
		return [
			[
				'type' => 'text',
				'text' => $text,
			],
		];
	}
}
