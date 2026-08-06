<?php
/**
 * Tool: woo_update_product — Update an existing WooCommerce product.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\WooCommerce;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UpdateProduct extends AbstractTool {

	public function get_capability(): string {
		return 'edit_products';
	}

	public function get_category(): string {
		return 'woocommerce';
	}

	public function get_definition(): array {
		return [
			'name'        => 'woo_update_product',
			'description' => 'Update an existing WooCommerce product\'s name, description, price, categories, or other attributes.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'product_id' => [
						'type'        => 'integer',
						'description' => 'Product ID to update.',
					],
					'name' => [
						'type'        => 'string',
						'description' => 'New product name.',
					],
					'description' => [
						'type'        => 'string',
						'description' => 'New full description (HTML).',
					],
					'short_description' => [
						'type'        => 'string',
						'description' => 'New short description.',
					],
					'regular_price' => [
						'type'        => 'string',
						'description' => 'New regular price.',
					],
					'sale_price' => [
						'type'        => 'string',
						'description' => 'New sale price (empty string to remove).',
					],
					'sku' => [
						'type'        => 'string',
						'description' => 'New SKU.',
					],
					'status' => [
						'type'        => 'string',
						'description' => 'Product status.',
						'enum'        => [ 'publish', 'draft', 'pending' ],
					],
					'categories' => [
						'type'        => 'array',
						'items'       => [ 'type' => 'string' ],
						'description' => 'Replace product categories.',
					],
				],
				'required' => [ 'product_id' ],
			],
		];
	}

	public function execute( array $arguments ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			throw new \RuntimeException( 'WooCommerce is not active.' );
		}

		$product = wc_get_product( (int) $arguments['product_id'] );
		if ( ! $product ) {
			throw new \InvalidArgumentException( 'Product not found.' );
		}

		if ( isset( $arguments['name'] ) ) {
			$product->set_name( sanitize_text_field( $arguments['name'] ) );
		}
		if ( isset( $arguments['description'] ) ) {
			$product->set_description( wp_kses_post( $arguments['description'] ) );
		}
		if ( isset( $arguments['short_description'] ) ) {
			$product->set_short_description( wp_kses_post( $arguments['short_description'] ) );
		}
		if ( isset( $arguments['regular_price'] ) ) {
			$product->set_regular_price( sanitize_text_field( $arguments['regular_price'] ) );
		}
		if ( array_key_exists( 'sale_price', $arguments ) ) {
			$product->set_sale_price( sanitize_text_field( $arguments['sale_price'] ) );
		}
		if ( isset( $arguments['sku'] ) ) {
			$product->set_sku( sanitize_text_field( $arguments['sku'] ) );
		}
		if ( isset( $arguments['status'] ) ) {
			$product->set_status( $arguments['status'] );
		}

		$product->save();

		// Update categories.
		if ( isset( $arguments['categories'] ) ) {
			$cat_ids = [];
			foreach ( $arguments['categories'] as $cat_name ) {
				$term = get_term_by( 'name', $cat_name, 'product_cat' )
					?: get_term_by( 'slug', sanitize_title( $cat_name ), 'product_cat' );
				if ( $term ) {
					$cat_ids[] = $term->term_id;
				}
			}
			$product->set_category_ids( $cat_ids );
			$product->save();
		}

		return $this->json_response( [
			'success'    => true,
			'product_id' => $product->get_id(),
			'name'       => $product->get_name(),
			'url'        => $product->get_permalink(),
		] );
	}
}
