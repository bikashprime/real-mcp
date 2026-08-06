<?php
/**
 * Tool: woo_get_products — List WooCommerce products.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\WooCommerce;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GetProducts extends AbstractTool {

	public function get_capability(): string {
		return 'edit_products';
	}

	public function get_category(): string {
		return 'woocommerce';
	}

	public function get_definition(): array {
		return [
			'name'        => 'woo_get_products',
			'description' => 'List WooCommerce products with details including price, stock, categories, and descriptions. Useful before bulk operations.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'per_page' => [
						'type'        => 'integer',
						'description' => 'Number of products (max 100).',
						'default'     => 20,
					],
					'page' => [
						'type'        => 'integer',
						'description' => 'Page number.',
						'default'     => 1,
					],
					'category' => [
						'type'        => 'string',
						'description' => 'Filter by category slug.',
					],
					'status' => [
						'type'        => 'string',
						'description' => 'Filter by status.',
						'enum'        => [ 'publish', 'draft', 'pending', 'any' ],
						'default'     => 'any',
					],
					'search' => [
						'type'        => 'string',
						'description' => 'Search products by keyword.',
					],
				],
			],
		];
	}

	public function execute( array $arguments ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			throw new \RuntimeException( 'WooCommerce is not active.' );
		}

		$per_page = min( (int) ( $arguments['per_page'] ?? 20 ), 100 );
		$page     = max( (int) ( $arguments['page'] ?? 1 ), 1 );

		$args = [
			'limit'  => $per_page,
			'page'   => $page,
			'return' => 'objects',
		];

		if ( ! empty( $arguments['category'] ) ) {
			$args['category'] = [ sanitize_title( $arguments['category'] ) ];
		}

		if ( ! empty( $arguments['status'] ) && $arguments['status'] !== 'any' ) {
			$args['status'] = $arguments['status'];
		}

		$products = wc_get_products( $args );

		// If search is specified, we need WP_Query.
		if ( ! empty( $arguments['search'] ) ) {
			$query = new \WP_Query( [
				'post_type'      => 'product',
				'posts_per_page' => $per_page,
				'paged'          => $page,
				's'              => sanitize_text_field( $arguments['search'] ),
			] );
			$products = array_map( 'wc_get_product', $query->posts );
			$products = array_filter( $products );
		}

		$data = [];
		foreach ( $products as $product ) {
			if ( ! $product ) {
				continue;
			}
			$data[] = [
				'id'                => $product->get_id(),
				'name'              => $product->get_name(),
				'slug'              => $product->get_slug(),
				'status'            => $product->get_status(),
				'type'              => $product->get_type(),
				'sku'               => $product->get_sku(),
				'regular_price'     => $product->get_regular_price(),
				'sale_price'        => $product->get_sale_price(),
				'price'             => $product->get_price(),
				'stock_status'      => $product->get_stock_status(),
				'stock_quantity'    => $product->get_stock_quantity(),
				'short_description' => wp_strip_all_tags( $product->get_short_description() ),
				'categories'        => wp_list_pluck( $product->get_category_ids()
					? array_map( 'get_term', $product->get_category_ids() ) : [], 'name' ),
				'url'               => $product->get_permalink(),
			];
		}

		return $this->json_response( [
			'products' => $data,
			'count'    => count( $data ),
			'page'     => $page,
		] );
	}
}
