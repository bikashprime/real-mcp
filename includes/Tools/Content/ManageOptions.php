<?php
/**
 * Tool: manage_options — Read or update WordPress site options.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\Content;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ManageOptions extends AbstractTool {

	public function get_capability(): string {
		return 'manage_options';
	}

	public function get_category(): string {
		return 'maintenance';
	}

	/**
	 * Options that are safe to read/update via MCP.
	 */
	private const ALLOWED_OPTIONS = [
		'blogname', 'blogdescription', 'timezone_string', 'date_format',
		'time_format', 'posts_per_page', 'permalink_structure',
		'default_comment_status', 'default_ping_status', 'show_on_front',
		'page_on_front', 'page_for_posts',
	];

	public function get_definition(): array {
		return [
			'name'        => 'manage_options',
			'description' => 'Read or update core WordPress site settings (site title, description, timezone, permalink structure, etc.). Only safe options are allowed.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'action' => [
						'type'        => 'string',
						'description' => 'Action to perform.',
						'enum'        => [ 'get', 'set', 'list' ],
					],
					'option' => [
						'type'        => 'string',
						'description' => 'Option name.',
					],
					'value' => [
						'type'        => 'string',
						'description' => 'New value (for set action).',
					],
				],
				'required' => [ 'action' ],
			],
		];
	}

	public function execute( array $arguments ): array {
		return match ( $arguments['action'] ) {
			'list' => $this->list_options(),
			'get'  => $this->get_option_value( $arguments['option'] ?? '' ),
			'set'  => $this->set_option_value( $arguments['option'] ?? '', $arguments['value'] ?? '' ),
			default => throw new \InvalidArgumentException( 'Invalid action.' ),
		};
	}

	private function list_options(): array {
		$options = [];
		foreach ( self::ALLOWED_OPTIONS as $key ) {
			$options[ $key ] = get_option( $key, '' );
		}
		return $this->json_response( [ 'options' => $options ] );
	}

	private function get_option_value( string $option ): array {
		if ( empty( $option ) ) {
			throw new \InvalidArgumentException( 'Option name is required.' );
		}

		if ( ! in_array( $option, self::ALLOWED_OPTIONS, true ) ) {
			/* translators: %s: option name */
			throw new \InvalidArgumentException( sprintf( 'Option %s is not accessible via MCP.', esc_html( $option ) ) );
		}

		return $this->json_response( [
			'option' => $option,
			'value'  => get_option( $option, '' ),
		] );
	}

	private function set_option_value( string $option, string $value ): array {
		if ( empty( $option ) ) {
			throw new \InvalidArgumentException( 'Option name is required.' );
		}

		if ( ! in_array( $option, self::ALLOWED_OPTIONS, true ) ) {
			/* translators: %s: option name */
			throw new \InvalidArgumentException( sprintf( 'Option %s is not updatable via MCP.', esc_html( $option ) ) );
		}

		update_option( $option, sanitize_text_field( $value ) );

		return $this->json_response( [
			'success' => true,
			'option'  => $option,
			'value'   => get_option( $option ),
		] );
	}
}
