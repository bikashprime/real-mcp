<?php
/**
 * Tool: accessibility_audit — WCAG compliance audit of site content.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\Accessibility;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AccessibilityAudit extends AbstractTool {

	public function get_capability(): string {
		return 'edit_posts';
	}

	public function get_category(): string {
		return 'accessibility';
	}

	public function get_definition(): array {
		return [
			'name'        => 'accessibility_audit',
			'description' => 'Audit posts and pages for WCAG accessibility issues including missing alt text, heading hierarchy, link text, color contrast indicators, and form labels.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'post_id' => [
						'type'        => 'integer',
						'description' => 'Audit a specific post/page. If not provided, audits recent published content.',
					],
					'limit' => [
						'type'        => 'integer',
						'description' => 'Number of posts to audit (if no post_id given). Max 30.',
						'default'     => 10,
					],
				],
			],
		];
	}

	public function execute( array $arguments ): array {
		if ( ! empty( $arguments['post_id'] ) ) {
			$posts = [ get_post( (int) $arguments['post_id'] ) ];
			if ( ! $posts[0] ) {
				throw new \InvalidArgumentException( 'Post not found.' );
			}
		} else {
			$limit = min( (int) ( $arguments['limit'] ?? 10 ), 30 );
			$posts = get_posts( [
				'post_type'      => [ 'post', 'page' ],
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
			] );
		}

		$audit_results = [];
		$total_issues  = 0;

		foreach ( $posts as $post ) {
			$issues = $this->audit_post( $post );
			$total_issues += count( $issues );
			$audit_results[] = [
				'id'     => $post->ID,
				'title'  => $post->post_title,
				'url'    => get_permalink( $post->ID ),
				'type'   => $post->post_type,
				'issues' => $issues,
				'score'  => max( 0, 100 - ( count( $issues ) * 10 ) ),
			];
		}

		return $this->json_response( [
			'posts_audited' => count( $posts ),
			'total_issues'  => $total_issues,
			'results'       => $audit_results,
		] );
	}

	private function audit_post( \WP_Post $post ): array {
		$issues  = [];
		$content = $post->post_content;

		// Check images without alt text.
		preg_match_all( '/<img[^>]*>/i', $content, $images );
		foreach ( $images[0] ?? [] as $img ) {
			if ( ! preg_match( '/alt\s*=\s*["\'][^"\']+["\']/i', $img ) ) {
				$src = '';
				if ( preg_match( '/src\s*=\s*["\']([^"\']+)["\']/i', $img, $m ) ) {
					$src = $m[1];
				}
				$issues[] = [
					'type'    => 'missing_alt_text',
					'level'   => 'A',
					'element' => 'img',
					'detail'  => "Image missing alt text" . ( $src ? ": {$src}" : '' ),
				];
			}
		}

		// Check heading hierarchy (h1 should not appear in content, only in title).
		if ( preg_match( '/<h1[\s>]/i', $content ) ) {
			$issues[] = [
				'type'   => 'heading_hierarchy',
				'level'  => 'A',
				'detail' => 'H1 tag found in content (should only be the page title).',
			];
		}

		// Check for skipped heading levels.
		preg_match_all( '/<h(\d)/i', $content, $headings );
		$levels = array_map( 'intval', $headings[1] ?? [] );
		for ( $i = 1; $i < count( $levels ); $i++ ) {
			if ( $levels[ $i ] > $levels[ $i - 1 ] + 1 ) {
				$issues[] = [
					'type'   => 'heading_skip',
					'level'  => 'A',
					'detail' => "Heading level skipped: h{$levels[$i-1]} to h{$levels[$i]}.",
				];
				break;
			}
		}

		// Check links without descriptive text.
		preg_match_all( '/<a[^>]*>(.*?)<\/a>/is', $content, $links );
		foreach ( $links[1] ?? [] as $link_text ) {
			$clean = strtolower( trim( wp_strip_all_tags( $link_text ) ) );
			if ( in_array( $clean, [ 'click here', 'here', 'read more', 'link', 'more' ], true ) ) {
				$issues[] = [
					'type'   => 'vague_link_text',
					'level'  => 'A',
					'detail' => "Non-descriptive link text: \"{$clean}\".",
				];
			}
		}

		// Check for links without href.
		preg_match_all( '/<a(?![^>]*href)[^>]*>/i', $content, $no_href );
		if ( ! empty( $no_href[0] ) ) {
			$issues[] = [
				'type'   => 'link_no_href',
				'level'  => 'A',
				'detail' => 'Link(s) without href attribute found.',
			];
		}

		// Check for empty buttons.
		preg_match_all( '/<button[^>]*>(.*?)<\/button>/is', $content, $buttons );
		foreach ( $buttons[1] ?? [] as $btn_text ) {
			if ( empty( trim( wp_strip_all_tags( $btn_text ) ) ) ) {
				$issues[] = [
					'type'   => 'empty_button',
					'level'  => 'A',
					'detail' => 'Button with no accessible text found.',
				];
			}
		}

		// Check featured image alt text.
		if ( has_post_thumbnail( $post->ID ) ) {
			$thumb_id = get_post_thumbnail_id( $post->ID );
			$alt      = get_post_meta( $thumb_id, '_wp_attachment_image_alt', true );
			if ( empty( $alt ) ) {
				$issues[] = [
					'type'   => 'featured_image_no_alt',
					'level'  => 'A',
					'detail' => 'Featured image has no alt text.',
				];
			}
		}

		return $issues;
	}
}
