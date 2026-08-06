<?php
/**
 * Tool: rankmath_get_ai_visibility_brand_queries — Get monitored queries for a tracked brand.
 *
 * Requires Rank Math PRO with Content AI subscription.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\RankMath;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GetAiVisibilityBrandQueries extends AbstractTool {

	public function get_capability(): string {
		return 'manage_options';
	}

	public function get_category(): string {
		return 'rankmath';
	}

	public function get_definition(): array {
		return [
			'name'        => 'rankmath_get_ai_visibility_brand_queries',
			'description' => 'Retrieve the specific queries being monitored for a tracked brand in AI Visibility. Requires Rank Math PRO with Content AI.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'brand_name' => [
						'type'        => 'string',
						'description' => 'Name of the brand to get queries for.',
					],
				],
				'required' => [ 'brand_name' ],
			],
		];
	}

	public function execute( array $arguments ): array {
		if ( ! class_exists( 'RankMath' ) ) {
			throw new \RuntimeException( 'Rank Math SEO is not active.' );
		}

		if ( ! defined( 'RANK_MATH_PRO_FILE' ) ) {
			throw new \RuntimeException( 'Rank Math PRO is required for AI Visibility features.' );
		}

		$brand_name = sanitize_text_field( $arguments['brand_name'] );
		$brands     = get_option( 'rank_math_ai_visibility_brands', [] );

		// Find the brand.
		$found = null;
		foreach ( $brands as $brand ) {
			if ( strcasecmp( $brand['name'] ?? '', $brand_name ) === 0 ) {
				$found = $brand;
				break;
			}
		}

		if ( ! $found ) {
			throw new \InvalidArgumentException( esc_html( "Brand '{$brand_name}' is not being tracked." ) );
		}

		$queries = $found['queries'] ?? [];

		return $this->json_response( [
			'success'       => true,
			'brand_name'    => $found['name'],
			'total_queries' => count( $queries ),
			'queries'       => $queries,
		] );
	}
}
