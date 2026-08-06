<?php
/**
 * Tool: rankmath_get_post_links — Retrieve all links from a post.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\RankMath;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GetPostLinks extends AbstractTool {

	public function get_capability(): string {
		return 'edit_posts';
	}

	public function get_category(): string {
		return 'rankmath';
	}

	public function get_definition(): array {
		return [
			'name'        => 'rankmath_get_post_links',
			'description' => 'Retrieve all internal and external links in a post, including anchor text, URL, and follow status. Helps identify link opportunities and avoid duplicate linking.',
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

		$content  = $post->post_content;
		$home_url = home_url();
		$links    = [];

		// Parse all anchor tags from the content.
		if ( preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$url         = $match[1];
				$anchor_text = wp_strip_all_tags( $match[2] );
				$full_tag    = $match[0];

				// Determine follow status.
				$is_nofollow = (bool) preg_match( '/rel=["\'][^"\']*nofollow[^"\']*["\']/', $full_tag );

				// Determine if internal or external.
				$is_internal = (
					strpos( $url, $home_url ) === 0 ||
					strpos( $url, '/' ) === 0
				);

				$links[] = [
					'url'         => $url,
					'anchor_text' => $anchor_text,
					'type'        => $is_internal ? 'internal' : 'external',
					'follow'      => $is_nofollow ? 'nofollow' : 'dofollow',
				];
			}
		}

		$internal_count = count( array_filter( $links, fn( $l ) => $l['type'] === 'internal' ) );
		$external_count = count( array_filter( $links, fn( $l ) => $l['type'] === 'external' ) );

		return $this->json_response( [
			'success'        => true,
			'post_id'        => $post_id,
			'title'          => $post->post_title,
			'total_links'    => count( $links ),
			'internal_links' => $internal_count,
			'external_links' => $external_count,
			'links'          => $links,
		] );
	}
}
