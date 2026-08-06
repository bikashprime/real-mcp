<?php
/**
 * Tool: woo_bulk_price_update — Bulk update product prices.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\WooCommerce;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BulkPriceUpdate extends AbstractTool {

	public function get_capability(): string {
		return 'edit_products';
	}

	public function get_category(): string {
		return 'woocommerce';
	}

	public function get_definition(): array {
		return [
			'name'        => 'woo_bulk_price_update',
			'description' => 'Bulk update prices for multiple products. Supports setting exact prices or applying percentage adjustments.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'product_ids' => [
						'type'        => 'array',
						'items'       => [ 'type' => 'integer' ],
						'description' => 'Array of product IDs to update.',
					],
					'category' => [
						'type'        => 'string',
						'description' => 'Update all products in this category slug (alternative to product_ids).',
					],
					'adjustment_type' => [
						'type'        => 'string',
						'description' => 'How to adjust prices.',
						'enum'        => [ 'fixed', 'percentage_increase', 'percentage_decrease' ],
					],
					'amount' => [
						'type'        => 'number',
						'description' => 'Amount to adjust. For fixed: new price. For percentage: the percentage value (e.g., 10 for 10%).',
					],
					'apply_to' => [
						'type'        => 'string',
						'description' => 'Which price to update.',
						'default'     => 'regular_price',
						'enum'        => [ 'regular_price', 'sale_price' ],
					],
				],
				'required' => [ 'adjustment_type', 'amount' ],
			],
		];
	}

	public function execute( array $arguments ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			throw new \RuntimeException( 'WooCommerce is not active.' );
		}

		$adjustment_type = $arguments['adjustment_type'];
		$amount          = (float) $arguments['amount'];
		$apply_to        = $arguments['apply_to'] ?? 'regular_price';
		$product_ids     = [];

		// Get product IDs from category or direct list.
		if ( ! empty( $arguments['product_ids'] ) ) {
			$product_ids = array_map( 'intval', $arguments['product_ids'] );
		} elseif ( ! empty( $arguments['category'] ) ) {
			$term = get_term_by( 'slug', sanitize_title( $arguments['category'] ), 'product_cat' );
			if ( $term ) {
				$product_ids = wc_get_products( [
					'category' => [ $term->slug ],
					'limit'    => 200,
					'return'   => 'ids',
				] );
			}
		}

		if ( empty( $product_ids ) ) {
			throw new \InvalidArgumentException( 'No products found to update.' );
		}

		$updated = 0;
		foreach ( $product_ids as $pid ) {
			$product = wc_get_product( $pid );
			if ( ! $product ) {
				continue;
			}

			$current_price = (float) ( $apply_to === 'sale_price'
				? $product->get_sale_price()
				: $product->get_regular_price() );

			$new_price = match ( $adjustment_type ) {
				'fixed'               => $amount,
				'percentage_increase' => $current_price * ( 1 + $amount / 100 ),
				'percentage_decrease' => $current_price * ( 1 - $amount / 100 ),
				default               => $current_price,
			};

			$new_price = round( max( 0, $new_price ), 2 );

			if ( $apply_to === 'sale_price' ) {
				$product->set_sale_price( (string) $new_price );
			} else {
				$product->set_regular_price( (string) $new_price );
			}

			$product->save();
			$updated++;
		}

		return $this->json_response( [
			'success'         => true,
			'products_updated'=> $updated,
			'adjustment_type' => $adjustment_type,
			'amount'          => $amount,
			'applied_to'      => $apply_to,
		] );
	}
}
