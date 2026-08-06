<?php
/**
 * Tool: create_elementor_page — Create a page with Elementor structure.
 *
 * Allows AI agents to create full Elementor pages by providing the
 * section/widget JSON structure directly.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\Elementor;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateElementorPage extends AbstractTool {

	public function get_capability(): string {
		return 'publish_pages';
	}

	public function get_category(): string {
		return 'elementor';
	}

	public function get_definition(): array {
		return [
			'name'        => 'create_elementor_page',
			'description' => 'Create a new page with Elementor page builder structure. You can provide either raw Elementor JSON data or simplified section definitions that will be converted to Elementor format. For professional pages like homepages, landing pages, service pages.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'title' => [
						'type'        => 'string',
						'description' => 'Page title.',
					],
					'status' => [
						'type'        => 'string',
						'description' => 'Page status.',
						'default'     => 'draft',
						'enum'        => [ 'publish', 'draft' ],
					],
					'template' => [
						'type'        => 'string',
						'description' => 'Page template. Use "elementor_canvas" for full-width no-header/footer, or "elementor_header_footer" for standard.',
						'default'     => 'elementor_header_footer',
					],
					'sections' => [
						'type'        => 'array',
						'description' => 'Array of section definitions. Each section has a layout and widgets.',
						'items'       => [
							'type'       => 'object',
							'properties' => [
								'type' => [
									'type'        => 'string',
									'description' => 'Section type: hero, text, cta, features, testimonials, gallery, contact, custom.',
								],
								'heading' => [
									'type'        => 'string',
									'description' => 'Section heading text.',
								],
								'subheading' => [
									'type'        => 'string',
									'description' => 'Subheading or tagline.',
								],
								'content' => [
									'type'        => 'string',
									'description' => 'Section body content (HTML).',
								],
								'button_text' => [
									'type'        => 'string',
									'description' => 'CTA button text.',
								],
								'button_url' => [
									'type'        => 'string',
									'description' => 'CTA button URL.',
								],
								'items' => [
									'type'        => 'array',
									'description' => 'List items for features/testimonials sections.',
									'items'       => [
										'type'       => 'object',
										'properties' => [
											'title'   => [ 'type' => 'string' ],
											'content' => [ 'type' => 'string' ],
											'icon'    => [ 'type' => 'string' ],
										],
									],
								],
								'background_color' => [
									'type'        => 'string',
									'description' => 'Section background color (hex).',
								],
							],
						],
					],
					'elementor_data' => [
						'type'        => 'string',
						'description' => 'Raw Elementor JSON data (advanced). If provided, sections array is ignored.',
					],
				],
				'required' => [ 'title' ],
			],
		];
	}

	public function execute( array $arguments ): array {
		if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
			throw new \RuntimeException( 'Elementor is not active.' );
		}

		// Create the page.
		$post_data = [
			'post_title'   => sanitize_text_field( $arguments['title'] ),
			'post_content' => '',
			'post_status'  => ( $arguments['status'] ?? 'draft' ) === 'publish' ? 'publish' : 'draft',
			'post_type'    => 'page',
		];

		$page_id = wp_insert_post( $post_data, true );
		if ( is_wp_error( $page_id ) ) {
			throw new \RuntimeException( esc_html( $page_id->get_error_message() ) );
		}

		// Set page template.
		$template = sanitize_text_field( $arguments['template'] ?? 'elementor_header_footer' );
		update_post_meta( $page_id, '_wp_page_template', $template );

		// Mark as Elementor page.
		update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $page_id, '_elementor_version', ELEMENTOR_VERSION );

		// Build Elementor data.
		if ( ! empty( $arguments['elementor_data'] ) ) {
			// Use raw Elementor JSON directly.
			$elementor_data = $arguments['elementor_data'];
		} elseif ( ! empty( $arguments['sections'] ) ) {
			// Convert simplified sections to Elementor format.
			$elementor_data = wp_json_encode( $this->build_elementor_sections( $arguments['sections'] ) );
		} else {
			$elementor_data = '[]';
		}

		update_post_meta( $page_id, '_elementor_data', wp_slash( $elementor_data ) );

		// Clear Elementor cache.
		if ( class_exists( '\Elementor\Plugin' ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}

		return $this->json_response( [
			'success' => true,
			'page_id' => $page_id,
			'url'     => get_permalink( $page_id ),
			'edit_url'=> admin_url( "post.php?post={$page_id}&action=elementor" ),
			'status'  => get_post_status( $page_id ),
		] );
	}

	/**
	 * Convert simplified section definitions to Elementor JSON structure.
	 */
	private function build_elementor_sections( array $sections ): array {
		$elementor_sections = [];

		foreach ( $sections as $index => $section ) {
			$elementor_sections[] = $this->build_section( $section, $index );
		}

		return $elementor_sections;
	}

	private function build_section( array $section, int $index ): array {
		$section_id = $this->generate_id();
		$column_id  = $this->generate_id();
		$widgets    = [];

		// Build widgets based on section type.
		if ( ! empty( $section['heading'] ) ) {
			$widgets[] = [
				'id'         => $this->generate_id(),
				'elType'     => 'widget',
				'widgetType' => 'heading',
				'settings'   => [
					'title'        => $section['heading'],
					'header_size'  => $index === 0 ? 'h1' : 'h2',
					'align'        => 'center',
				],
				'elements'   => [],
			];
		}

		if ( ! empty( $section['subheading'] ) ) {
			$widgets[] = [
				'id'         => $this->generate_id(),
				'elType'     => 'widget',
				'widgetType' => 'heading',
				'settings'   => [
					'title'       => $section['subheading'],
					'header_size' => 'h3',
					'align'       => 'center',
				],
				'elements'   => [],
			];
		}

		if ( ! empty( $section['content'] ) ) {
			$widgets[] = [
				'id'         => $this->generate_id(),
				'elType'     => 'widget',
				'widgetType' => 'text-editor',
				'settings'   => [
					'editor' => wp_kses_post( $section['content'] ),
				],
				'elements'   => [],
			];
		}

		if ( ! empty( $section['button_text'] ) ) {
			$widgets[] = [
				'id'         => $this->generate_id(),
				'elType'     => 'widget',
				'widgetType' => 'button',
				'settings'   => [
					'text'  => sanitize_text_field( $section['button_text'] ),
					'link'  => [
						'url'         => esc_url_raw( $section['button_url'] ?? '#' ),
						'is_external' => false,
					],
					'align' => 'center',
					'size'  => 'lg',
				],
				'elements'   => [],
			];
		}

		// Feature items.
		if ( ! empty( $section['items'] ) && ( $section['type'] ?? '' ) === 'features' ) {
			foreach ( $section['items'] as $item ) {
				$widgets[] = [
					'id'         => $this->generate_id(),
					'elType'     => 'widget',
					'widgetType' => 'icon-box',
					'settings'   => [
						'title_text'      => sanitize_text_field( $item['title'] ?? '' ),
						'description_text'=> wp_kses_post( $item['content'] ?? '' ),
					],
					'elements'   => [],
				];
			}
		}

		// Section settings.
		$settings = [
			'padding' => [
				'top'    => '60',
				'bottom' => '60',
				'unit'   => 'px',
			],
		];

		if ( ! empty( $section['background_color'] ) ) {
			$settings['background_background'] = 'classic';
			$settings['background_color'] = sanitize_hex_color( $section['background_color'] );
		}

		return [
			'id'       => $section_id,
			'elType'   => 'section',
			'settings' => $settings,
			'elements' => [
				[
					'id'       => $column_id,
					'elType'   => 'column',
					'settings' => [ '_column_size' => 100 ],
					'elements' => $widgets,
				],
			],
		];
	}

	/**
	 * Generate a random Elementor-style element ID.
	 */
	private function generate_id(): string {
		return substr( md5( wp_generate_uuid4() ), 0, 7 );
	}
}
