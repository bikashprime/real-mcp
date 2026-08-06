<?php
/**
 * Tool: rankmath_create_ai_visibility_brand — Add a new brand to track in AI Visibility.
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

class CreateAiVisibilityBrand extends AbstractTool {

	public function get_capability(): string {
		return 'manage_options';
	}

	public function get_category(): string {
		return 'rankmath';
	}

	public function get_definition(): array {
		return [
			'name'        => 'rankmath_create_ai_visibility_brand',
			'description' => 'Add a new brand or product to track on AI platforms via Rank Math AI Visibility. Provide name, URL, and a detailed description for accurate tracking. Requires Rank Math PRO with Content AI.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'name' => [
						'type'        => 'string',
						'description' => 'Brand or product name to track.',
					],
					'url' => [
						'type'        => 'string',
						'description' => 'Brand or product URL.',
					],
					'description' => [
						'type'        => 'string',
						'description' => 'Detailed description of the brand. Include what it is used for, target audience, and what makes it stand out. This helps AI Visibility accurately track it.',
					],
					'country' => [
						'type'        => 'string',
						'description' => 'Country code for tracking (e.g., "us", "uk", "global").',
						'default'     => 'global',
					],
				],
				'required' => [ 'name', 'url', 'description' ],
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

		$name        = sanitize_text_field( $arguments['name'] );
		$url         = esc_url_raw( $arguments['url'] );
		$description = sanitize_textarea_field( $arguments['description'] );
		$country     = sanitize_text_field( $arguments['country'] ?? 'global' );

		if ( empty( $name ) || empty( $url ) || empty( $description ) ) {
			throw new \InvalidArgumentException( 'Name, URL, and description are all required.' );
		}

		// Get existing brands.
		$brands = get_option( 'rank_math_ai_visibility_brands', [] );

		// Check for duplicates.
		foreach ( $brands as $brand ) {
			if ( strcasecmp( $brand['name'] ?? '', $name ) === 0 ) {
				throw new \InvalidArgumentException( "Brand '{$name}' is already being tracked." );
			}
		}

		// Add the new brand.
		$new_brand = [
			'name'         => $name,
			'url'          => $url,
			'description'  => $description,
			'country'      => $country,
			'score'        => 0,
			'rank'         => null,
			'mentions'     => 0,
			'citations'    => 0,
			'sentiment'    => 'neutral',
			'queries'      => [],
			'competitors'  => [],
			'created_at'   => current_time( 'mysql' ),
			'last_updated' => null,
		];

		$brands[] = $new_brand;
		update_option( 'rank_math_ai_visibility_brands', $brands );

		return $this->json_response( [
			'success' => true,
			'message' => "Brand '{$name}' has been added to AI Visibility tracking.",
			'brand'   => $new_brand,
		] );
	}
}
