<?php
/**
 * Tool: woo_update_inventory — Update stock for one or more products.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\WooCommerce;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UpdateInventory extends AbstractTool {

	public function get_capability(): string {
		return 'edit_products';
	}

	public function get_category(): string {
		return 'woocommerce';
	}

	public function get_definition(): array {
		return [
			'name'        => 'woo_update_inventory',
			'description' => 'Update stock quantity and status for one or more WooCommerce products.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'updates' => [
						'type'        => 'array',
						'description' => 'Array of inventory updates.',
						'items'       => [
							'type'       => 'object',
							'properties' => [
								'product_id' => [
									'type'        => 'integer',
									'description' => 'Product ID.',
								],
								'stock_quantity' => [
									'type'        => 'integer',
									'description' => 'New stock quantity.',
								],
								'stock_status' => [
									'type'        => 'string',
									'description' => 'Stock status.',
									'enum'        => [ 'instock', 'outofstock', 'onbackorder' ],
								],
							],
							'required' => [ 'product_id' ],
						],
					],
				],
				'required' => [ 'updates' ],
			],
		];
	}

	public function execute( array $arguments ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			throw new \RuntimeException( 'WooCommerce is not active.' );
		}

		$results = [];

		foreach ( $arguments['updates'] as $update ) {
			$product_id = (int) $update['product_id'];
			$product    = wc_get_product( $product_id );

			if ( ! $product ) {
				$results[] = [
					'product_id' => $product_id,
					'success'    => false,
					'error'      => 'Product not found.',
				];
				continue;
			}

			if ( isset( $update['stock_quantity'] ) ) {
				$product->set_manage_stock( true );
				$product->set_stock_quantity( (int) $update['stock_quantity'] );
			}

			if ( isset( $update['stock_status'] ) && in_array( $update['stock_status'], [ 'instock', 'outofstock', 'onbackorder' ], true ) ) {
				$product->set_stock_status( $update['stock_status'] );
			}

			$product->save();

			$results[] = [
				'product_id'     => $product_id,
				'success'        => true,
				'name'           => $product->get_name(),
				'stock_quantity' => $product->get_stock_quantity(),
				'stock_status'   => $product->get_stock_status(),
			];
		}

		return $this->json_response( [
			'updated' => count( array_filter( $results, fn( $r ) => $r['success'] ) ),
			'failed'  => count( array_filter( $results, fn( $r ) => ! $r['success'] ) ),
			'results' => $results,
		] );
	}
}
