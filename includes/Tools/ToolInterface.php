<?php
/**
 * Tool interface — contract for all MCP tools.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface ToolInterface {

	/**
	 * Get the tool definition for tools/list.
	 *
	 * @return array{name: string, description: string, inputSchema: array}
	 */
	public function get_definition(): array;

	/**
	 * Execute the tool with given arguments.
	 *
	 * @param array $arguments Input arguments matching the inputSchema.
	 * @return array Array of content items (MCP content blocks).
	 */
	public function execute( array $arguments ): array;

	/**
	 * Get the WordPress capability required to use this tool.
	 *
	 * @return string WordPress capability slug (e.g., 'read', 'edit_posts', 'manage_options').
	 */
	public function get_capability(): string;

	/**
	 * Get the tool category for grouping.
	 *
	 * @return string Category identifier (e.g., 'content', 'woocommerce', 'media', 'security', 'maintenance').
	 */
	public function get_category(): string;
}
