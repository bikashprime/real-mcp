<?php
/**
 * Tool: update_seo_meta — Update SEO metadata for a post.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\SEO;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UpdateSeoMeta extends AbstractTool {

	public function get_capability(): string {
		return 'edit_posts';
	}

	public function get_category(): string {
		return 'seo';
	}

	public function get_definition(): array {
		return [
			'name'        => 'update_seo_meta',
			'description' => 'Update SEO metadata (title, description, focus keyword, Open Graph) for a post. Works with Yoast, Rank Math, and All in One SEO.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'id' => [
						'type'        => 'integer',
						'description' => 'Post or page ID.',
					],
					'meta_title' => [
						'type'        => 'string',
						'description' => 'SEO meta title.',
					],
					'meta_description' => [
						'type'        => 'string',
						'description' => 'SEO meta description.',
					],
					'focus_keyword' => [
						'type'        => 'string',
						'description' => 'Focus keyword for SEO optimization.',
					],
					'og_title' => [
						'type'        => 'string',
						'description' => 'Open Graph title for social sharing.',
					],
					'og_description' => [
						'type'        => 'string',
						'description' => 'Open Graph description for social sharing.',
					],
				],
				'required' => [ 'id' ],
			],
		];
	}

	public function execute( array $arguments ): array {
		$post_id = (int) $arguments['id'];
		$post    = get_post( $post_id );

		if ( ! $post ) {
			throw new \InvalidArgumentException( 'Post not found.' );
		}

		$updated = [];

		// Detect which SEO plugin is active.
		$seo_plugin = $this->detect_seo_plugin();

		if ( isset( $arguments['meta_title'] ) ) {
			$title = sanitize_text_field( $arguments['meta_title'] );
			match ( $seo_plugin ) {
				'yoast'     => update_post_meta( $post_id, '_yoast_wpseo_title', $title ),
				'rankmath'  => update_post_meta( $post_id, 'rank_math_title', $title ),
				'aioseo'    => update_post_meta( $post_id, '_aioseo_title', $title ),
				default     => update_post_meta( $post_id, '_real_mcp_seo_title', $title ),
			};
			$updated[] = 'meta_title';
		}

		if ( isset( $arguments['meta_description'] ) ) {
			$desc = sanitize_textarea_field( $arguments['meta_description'] );
			match ( $seo_plugin ) {
				'yoast'     => update_post_meta( $post_id, '_yoast_wpseo_metadesc', $desc ),
				'rankmath'  => update_post_meta( $post_id, 'rank_math_description', $desc ),
				'aioseo'    => update_post_meta( $post_id, '_aioseo_description', $desc ),
				default     => update_post_meta( $post_id, '_real_mcp_seo_desc', $desc ),
			};
			$updated[] = 'meta_description';
		}

		if ( isset( $arguments['focus_keyword'] ) ) {
			$kw = sanitize_text_field( $arguments['focus_keyword'] );
			match ( $seo_plugin ) {
				'yoast'     => update_post_meta( $post_id, '_yoast_wpseo_focuskw', $kw ),
				'rankmath'  => update_post_meta( $post_id, 'rank_math_focus_keyword', $kw ),
				default     => update_post_meta( $post_id, '_real_mcp_focus_keyword', $kw ),
			};
			$updated[] = 'focus_keyword';
		}

		if ( isset( $arguments['og_title'] ) ) {
			$og_title = sanitize_text_field( $arguments['og_title'] );
			match ( $seo_plugin ) {
				'yoast'     => update_post_meta( $post_id, '_yoast_wpseo_opengraph-title', $og_title ),
				'rankmath'  => update_post_meta( $post_id, 'rank_math_facebook_title', $og_title ),
				default     => update_post_meta( $post_id, '_real_mcp_og_title', $og_title ),
			};
			$updated[] = 'og_title';
		}

		if ( isset( $arguments['og_description'] ) ) {
			$og_desc = sanitize_textarea_field( $arguments['og_description'] );
			match ( $seo_plugin ) {
				'yoast'     => update_post_meta( $post_id, '_yoast_wpseo_opengraph-description', $og_desc ),
				'rankmath'  => update_post_meta( $post_id, 'rank_math_facebook_description', $og_desc ),
				default     => update_post_meta( $post_id, '_real_mcp_og_desc', $og_desc ),
			};
			$updated[] = 'og_description';
		}

		return $this->json_response( [
			'success'    => true,
			'post_id'    => $post_id,
			'seo_plugin' => $seo_plugin,
			'updated'    => $updated,
		] );
	}

	/**
	 * Detect which SEO plugin is active.
	 */
	private function detect_seo_plugin(): string {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'yoast';
		}
		if ( class_exists( 'RankMath' ) ) {
			return 'rankmath';
		}
		if ( defined( 'AIOSEO_VERSION' ) ) {
			return 'aioseo';
		}
		return 'none';
	}
}
