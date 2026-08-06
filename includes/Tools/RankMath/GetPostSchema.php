<?php
/**
 * Tool: rankmath_get_post_schema — Retrieve Schema markup from a post via Rank Math.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\RankMath;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GetPostSchema extends AbstractTool {

	public function get_capability(): string {
		return 'edit_posts';
	}

	public function get_category(): string {
		return 'rankmath';
	}

	public function get_definition(): array {
		return [
			'name'        => 'rankmath_get_post_schema',
			'description' => 'Retrieve Schema markup (structured data) applied to a post via Rank Math, and list available Schema types that can be applied.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'post_id' => [
						'type'        => 'integer',
						'description' => 'The post or page ID.',
					],
				],
				'required' => [ 'post_id' ],
			],
		];
	}

	public function execute( array $arguments ): array {
		if ( ! class_exists( 'RankMath' ) ) {
			throw new \RuntimeException( 'Rank Math SEO is not active.' );
		}

		$post_id = (int) $arguments['post_id'];
		$post    = get_post( $post_id );

		if ( ! $post ) {
			throw new \InvalidArgumentException( 'Post not found.' );
		}

		// Get Rank Math schema data.
		$schema_data = get_post_meta( $post_id, 'rank_math_schema_' . strtolower( get_post_type( $post_id ) ), true );

		// Rank Math stores schema in a specific meta key format.
		// Try the generic schema meta.
		if ( empty( $schema_data ) ) {
			$all_meta    = get_post_meta( $post_id );
			$schema_data = [];

			foreach ( $all_meta as $key => $value ) {
				if ( strpos( $key, 'rank_math_schema_' ) === 0 ) {
					$decoded = maybe_unserialize( $value[0] );
					if ( ! empty( $decoded ) ) {
						$schema_data[ $key ] = $decoded;
					}
				}
			}
		}

		// Get the rich snippet type set for this post.
		$snippet_type = get_post_meta( $post_id, 'rank_math_rich_snippet', true );

		// Available schema types in Rank Math.
		$available_types = [
			'Article',
			'Book',
			'Course',
			'Event',
			'FAQ',
			'HowTo',
			'JobPosting',
			'LocalBusiness',
			'Music',
			'Person',
			'Product',
			'Recipe',
			'Restaurant',
			'Review',
			'Service',
			'SoftwareApplication',
			'Video',
		];

		// PRO types (only if Rank Math PRO is active).
		if ( defined( 'RANK_MATH_PRO_FILE' ) ) {
			$available_types = array_merge( $available_types, [
				'Dataset',
				'FactCheck',
				'MathSolver',
				'Movie',
				'PodcastEpisode',
				'Scholarship',
				'TVSeries',
			] );
		}

		return $this->json_response( [
			'success'          => true,
			'post_id'          => $post_id,
			'title'            => $post->post_title,
			'current_schema'   => $snippet_type ?: 'none',
			'schema_data'      => $schema_data ?: null,
			'available_types'  => $available_types,
			'is_pro'           => defined( 'RANK_MATH_PRO_FILE' ),
		] );
	}
}
