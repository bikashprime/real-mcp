<?php
/**
 * Tool: woo_create_product — Create a new WooCommerce product.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\WooCommerce;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateProduct extends AbstractTool {

	public function get_capability(): string {
		return 'publish_products';
	}

	public function get_category(): string {
		return 'woocommerce';
	}

	public function get_definition(): array {
		return [
			'name'        => 'woo_create_product',
			'description' => 'Create a new WooCommerce product with price, stock, categories, and attributes. Requires WooCommerce to be active.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'name' => [
						'type'        => 'string',
						'description' => 'Product name.',
					],
					'description' => [
						'type'        => 'string',
						'description' => 'Full product description (HTML).',
					],
					'short_description' => [
						'type'        => 'string',
						'description' => 'Short product description.',
					],
					'regular_price' => [
						'type'        => 'string',
						'description' => 'Regular price (e.g., "29.99").',
					],
					'sale_price' => [
						'type'        => 'string',
						'description' => 'Sale price.',
					],
					'sku' => [
						'type'        => 'string',
						'description' => 'Product SKU.',
					],
					'stock_quantity' => [
						'type'        => 'integer',
						'description' => 'Stock quantity.',
					],
					'categories' => [
						'type'        => 'array',
						'items'       => [ 'type' => 'string' ],
						'description' => 'Product category names.',
					],
					'status' => [
						'type'        => 'string',
						'description' => 'Product status.',
						'default'     => 'publish',
						'enum'        => [ 'publish', 'draft', 'pending' ],
					],
					'type' => [
						'type'        => 'string',
						'description' => 'Product type.',
						'default'     => 'simple',
						'enum'        => [ 'simple', 'variable', 'grouped', 'external' ],
					],
				],
				'required' => [ 'name', 'regular_price' ],
			],
		];
	}

	public function execute( array $arguments ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			throw new \RuntimeException( 'WooCommerce is not active.' );
		}

		$product = new \WC_Product_Simple();
		$product->set_name( sanitize_text_field( $arguments['name'] ) );
		$product->set_status( in_array( $arguments['status'] ?? 'publish', [ 'publish', 'draft', 'pending' ], true )
			? $arguments['status'] : 'publish' );
		$product->set_regular_price( sanitize_text_field( $arguments['regular_price'] ) );

		if ( ! empty( $arguments['sale_price'] ) ) {
			$product->set_sale_price( sanitize_text_field( $arguments['sale_price'] ) );
		}
		if ( ! empty( $arguments['description'] ) ) {
			$product->set_description( wp_kses_post( $arguments['description'] ) );
		}
		if ( ! empty( $arguments['short_description'] ) ) {
			$product->set_short_description( wp_kses_post( $arguments['short_description'] ) );
		}
		if ( ! empty( $arguments['sku'] ) ) {
			$product->set_sku( sanitize_text_field( $arguments['sku'] ) );
		}
		if ( isset( $arguments['stock_quantity'] ) ) {
			$product->set_manage_stock( true );
			$product->set_stock_quantity( (int) $arguments['stock_quantity'] );
		}

		$product_id = $product->save();

		if ( ! $product_id ) {
			throw new \RuntimeException( 'Failed to create product.' );
		}

		// Set categories.
		if ( ! empty( $arguments['categories'] ) ) {
			$cat_ids = [];
			foreach ( $arguments['categories'] as $cat_name ) {
				$term = get_term_by( 'name', $cat_name, 'product_cat' );
				if ( $term ) {
					$cat_ids[] = $term->term_id;
				} else {
					$new_term = wp_insert_term( sanitize_text_field( $cat_name ), 'product_cat' );
					if ( ! is_wp_error( $new_term ) ) {
						$cat_ids[] = $new_term['term_id'];
					}
				}
			}
			if ( ! empty( $cat_ids ) ) {
				wp_set_object_terms( $product_id, $cat_ids, 'product_cat' );
			}
		}

		return $this->json_response( [
			'success'    => true,
			'product_id' => $product_id,
			'url'        => get_permalink( $product_id ),
			'sku'        => $product->get_sku(),
		] );
	}
}
