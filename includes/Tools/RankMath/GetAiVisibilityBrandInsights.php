<?php
/**
 * Tool: rankmath_get_ai_visibility_brand_insights — Get AI Visibility metrics for a specific brand.
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

class GetAiVisibilityBrandInsights extends AbstractTool {

	public function get_capability(): string {
		return 'manage_options';
	}

	public function get_category(): string {
		return 'rankmath';
	}

	public function get_definition(): array {
		return [
			'name'        => 'rankmath_get_ai_visibility_brand_insights',
			'description' => 'Retrieve AI Visibility metrics for a specific tracked brand — score, rank, recent mentions, citations, average sentiment, queries, and competitor data. Requires Rank Math PRO with Content AI.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'brand_name' => [
						'type'        => 'string',
						'description' => 'Name of the brand to retrieve insights for.',
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
			throw new \InvalidArgumentException( "Brand '{$brand_name}' is not being tracked. Use the create brand tool to add it." );
		}

		return $this->json_response( [
			'success'     => true,
			'brand'       => [
				'name'        => $found['name'] ?? '',
				'url'         => $found['url'] ?? '',
				'description' => $found['description'] ?? '',
				'score'       => $found['score'] ?? 0,
				'rank'        => $found['rank'] ?? null,
				'mentions'    => $found['mentions'] ?? 0,
				'citations'   => $found['citations'] ?? 0,
				'sentiment'   => $found['sentiment'] ?? 'neutral',
				'queries'     => $found['queries'] ?? [],
				'competitors' => $found['competitors'] ?? [],
				'country'     => $found['country'] ?? 'global',
				'last_updated'=> $found['last_updated'] ?? null,
			],
		] );
	}
}
