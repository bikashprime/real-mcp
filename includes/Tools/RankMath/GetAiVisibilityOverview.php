<?php
/**
 * Tool: rankmath_get_ai_visibility_overview — Get AI Visibility report for all tracked brands.
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

class GetAiVisibilityOverview extends AbstractTool {

	public function get_capability(): string {
		return 'manage_options';
	}

	public function get_category(): string {
		return 'rankmath';
	}

	public function get_definition(): array {
		return [
			'name'        => 'rankmath_get_ai_visibility_overview',
			'description' => 'Retrieve the AI Visibility overview report for all tracked brands. Returns global AI Visibility score, total brands tracked, average mentions per brand, and individual brand metrics. Requires Rank Math PRO with Content AI.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => new \stdClass(),
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

		// Check if AI Visibility data is available.
		$brands = get_option( 'rank_math_ai_visibility_brands', [] );

		if ( empty( $brands ) ) {
			return $this->json_response( [
				'success' => true,
				'message' => 'No brands are currently being tracked. Use the Create Brand tool to add a brand.',
				'brands'  => [],
			] );
		}

		$total_mentions = 0;
		$brand_data     = [];

		foreach ( $brands as $brand ) {
			$mentions = $brand['mentions'] ?? 0;
			$total_mentions += $mentions;

			$brand_data[] = [
				'name'       => $brand['name'] ?? '',
				'url'        => $brand['url'] ?? '',
				'score'      => $brand['score'] ?? 0,
				'rank'       => $brand['rank'] ?? null,
				'mentions'   => $mentions,
				'citations'  => $brand['citations'] ?? 0,
				'sentiment'  => $brand['sentiment'] ?? 'neutral',
			];
		}

		$avg_mentions = count( $brands ) > 0 ? round( $total_mentions / count( $brands ), 1 ) : 0;
		$avg_score    = count( $brands ) > 0
			? round( array_sum( array_column( $brands, 'score' ) ) / count( $brands ) )
			: 0;

		return $this->json_response( [
			'success'                => true,
			'global_score'           => $avg_score,
			'total_brands_tracked'   => count( $brands ),
			'average_mentions'       => $avg_mentions,
			'brands'                 => $brand_data,
		] );
	}
}
