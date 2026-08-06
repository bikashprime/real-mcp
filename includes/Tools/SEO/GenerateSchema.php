<?php
/**
 * Tool: generate_schema — Generate JSON-LD schema markup for a post.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\SEO;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GenerateSchema extends AbstractTool {

	public function get_capability(): string {
		return 'edit_posts';
	}

	public function get_category(): string {
		return 'seo';
	}

	public function get_definition(): array {
		return [
			'name'        => 'generate_schema',
			'description' => 'Generate JSON-LD structured data (schema.org markup) for a post and optionally save it as post meta.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'id' => [
						'type'        => 'integer',
						'description' => 'Post or page ID.',
					],
					'schema_type' => [
						'type'        => 'string',
						'description' => 'Schema type to generate.',
						'enum'        => [ 'Article', 'BlogPosting', 'FAQPage', 'HowTo', 'Product', 'LocalBusiness' ],
						'default'     => 'Article',
					],
					'save' => [
						'type'        => 'boolean',
						'description' => 'Whether to save the schema as post meta.',
						'default'     => false,
					],
				],
				'required' => [ 'id' ],
			],
		];
	}

	public function execute( array $arguments ): array {
		$post_id     = (int) $arguments['id'];
		$schema_type = $arguments['schema_type'] ?? 'Article';
		$save        = (bool) ( $arguments['save'] ?? false );

		$post = get_post( $post_id );
		if ( ! $post ) {
			throw new \InvalidArgumentException( 'Post not found.' );
		}

		$schema = $this->build_schema( $post, $schema_type );

		if ( $save ) {
			update_post_meta( $post_id, '_real_mcp_schema', wp_json_encode( $schema ) );
		}

		return $this->json_response( [
			'post_id' => $post_id,
			'schema'  => $schema,
			'saved'   => $save,
		] );
	}

	private function build_schema( \WP_Post $post, string $type ): array {
		$base = [
			'@context' => 'https://schema.org',
			'@type'    => $type,
		];

		$author_name = get_the_author_meta( 'display_name', $post->post_author );
		$thumbnail   = get_the_post_thumbnail_url( $post->ID, 'full' );

		return match ( $type ) {
			'Article', 'BlogPosting' => array_merge( $base, [
				'headline'      => $post->post_title,
				'description'   => wp_strip_all_tags( get_the_excerpt( $post ) ),
				'url'           => get_permalink( $post->ID ),
				'datePublished' => get_the_date( 'c', $post ),
				'dateModified'  => get_the_modified_date( 'c', $post ),
				'author'        => [
					'@type' => 'Person',
					'name'  => $author_name,
				],
				'publisher'     => [
					'@type' => 'Organization',
					'name'  => get_bloginfo( 'name' ),
					'url'   => home_url(),
				],
				'image'         => $thumbnail ?: '',
			] ),
			'FAQPage' => array_merge( $base, [
				'name'       => $post->post_title,
				'url'        => get_permalink( $post->ID ),
				'mainEntity' => [],
			] ),
			default => array_merge( $base, [
				'name'        => $post->post_title,
				'description' => wp_strip_all_tags( get_the_excerpt( $post ) ),
				'url'         => get_permalink( $post->ID ),
			] ),
		};
	}
}
