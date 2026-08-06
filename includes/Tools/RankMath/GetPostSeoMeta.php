<?php
/**
 * Tool: rankmath_get_post_seo_meta — Retrieve post SEO metadata via Rank Math.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\RankMath;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GetPostSeoMeta extends AbstractTool {

	public function get_capability(): string {
		return 'edit_posts';
	}

	public function get_category(): string {
		return 'rankmath';
	}

	public function get_definition(): array {
		return [
			'name'        => 'rankmath_get_post_seo_meta',
			'description' => 'Retrieve a post\'s complete SEO metadata from Rank Math — title, description, focus keyword, robots settings, canonical URL, OpenGraph, Twitter Card, and SEO score.',
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

		// Core SEO meta.
		$title         = get_post_meta( $post_id, 'rank_math_title', true );
		$description   = get_post_meta( $post_id, 'rank_math_description', true );
		$focus_keyword = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
		$canonical     = get_post_meta( $post_id, 'rank_math_canonical_url', true );
		$seo_score     = get_post_meta( $post_id, 'rank_math_seo_score', true );

		// Robots meta.
		$robots_meta = get_post_meta( $post_id, 'rank_math_robots', true );
		$robots      = ! empty( $robots_meta ) ? (array) $robots_meta : [];

		// OpenGraph meta.
		$og_title       = get_post_meta( $post_id, 'rank_math_facebook_title', true );
		$og_description = get_post_meta( $post_id, 'rank_math_facebook_description', true );
		$og_image       = get_post_meta( $post_id, 'rank_math_facebook_image', true );

		// Twitter Card meta.
		$twitter_title       = get_post_meta( $post_id, 'rank_math_twitter_title', true );
		$twitter_description = get_post_meta( $post_id, 'rank_math_twitter_description', true );
		$twitter_image       = get_post_meta( $post_id, 'rank_math_twitter_image', true );
		$twitter_card_type   = get_post_meta( $post_id, 'rank_math_twitter_card_type', true );

		// Advanced.
		$breadcrumb_title = get_post_meta( $post_id, 'rank_math_breadcrumb_title', true );
		$pillar_content   = get_post_meta( $post_id, 'rank_math_pillar_content', true );

		return $this->json_response( [
			'success'   => true,
			'post_id'   => $post_id,
			'title'     => $post->post_title,
			'url'       => get_permalink( $post_id ),
			'seo_meta'  => [
				'meta_title'       => $title ?: null,
				'meta_description' => $description ?: null,
				'focus_keyword'    => $focus_keyword ?: null,
				'canonical_url'    => $canonical ?: get_permalink( $post_id ),
				'seo_score'        => $seo_score ? (int) $seo_score : null,
				'robots'           => $robots,
			],
			'opengraph' => [
				'og_title'       => $og_title ?: null,
				'og_description' => $og_description ?: null,
				'og_image'       => $og_image ?: null,
			],
			'twitter' => [
				'card_type'   => $twitter_card_type ?: 'summary_large_image',
				'title'       => $twitter_title ?: null,
				'description' => $twitter_description ?: null,
				'image'       => $twitter_image ?: null,
			],
			'advanced' => [
				'breadcrumb_title' => $breadcrumb_title ?: null,
				'pillar_content'   => ! empty( $pillar_content ),
			],
		] );
	}
}
