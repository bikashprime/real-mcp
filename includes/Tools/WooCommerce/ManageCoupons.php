<?php
/**
 * Tool: woo_manage_coupons — Create or manage WooCommerce coupons/promotions.
 *
 * @package Real_MCP
 */

namespace Real_MCP\Tools\WooCommerce;

use Real_MCP\Tools\AbstractTool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ManageCoupons extends AbstractTool {

	public function get_capability(): string {
		return 'manage_woocommerce';
	}

	public function get_category(): string {
		return 'woocommerce';
	}

	public function get_definition(): array {
		return [
			'name'        => 'woo_manage_coupons',
			'description' => 'Create, update, or delete WooCommerce coupons for promotions and seasonal campaigns.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'action' => [
						'type'        => 'string',
						'description' => 'Action to perform.',
						'enum'        => [ 'create', 'update', 'delete', 'list' ],
					],
					'coupon_id' => [
						'type'        => 'integer',
						'description' => 'Coupon ID (required for update/delete).',
					],
					'code' => [
						'type'        => 'string',
						'description' => 'Coupon code (required for create).',
					],
					'discount_type' => [
						'type'        => 'string',
						'description' => 'Type of discount.',
						'enum'        => [ 'percent', 'fixed_cart', 'fixed_product' ],
						'default'     => 'percent',
					],
					'amount' => [
						'type'        => 'string',
						'description' => 'Discount amount (e.g., "10" for 10% or $10).',
					],
					'expiry_date' => [
						'type'        => 'string',
						'description' => 'Expiry date in YYYY-MM-DD format.',
					],
					'usage_limit' => [
						'type'        => 'integer',
						'description' => 'Total usage limit.',
					],
					'minimum_amount' => [
						'type'        => 'string',
						'description' => 'Minimum order amount.',
					],
					'product_ids' => [
						'type'        => 'array',
						'items'       => [ 'type' => 'integer' ],
						'description' => 'Restrict to specific product IDs.',
					],
				],
				'required' => [ 'action' ],
			],
		];
	}

	public function execute( array $arguments ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			throw new \RuntimeException( 'WooCommerce is not active.' );
		}

		return match ( $arguments['action'] ) {
			'create' => $this->create_coupon( $arguments ),
			'update' => $this->update_coupon( $arguments ),
			'delete' => $this->delete_coupon( $arguments ),
			'list'   => $this->list_coupons(),
			default  => throw new \InvalidArgumentException( 'Invalid action.' ),
		};
	}

	private function create_coupon( array $args ): array {
		if ( empty( $args['code'] ) ) {
			throw new \InvalidArgumentException( 'Coupon code is required.' );
		}

		$coupon = new \WC_Coupon();
		$coupon->set_code( sanitize_text_field( $args['code'] ) );
		$coupon->set_discount_type( $args['discount_type'] ?? 'percent' );
		$coupon->set_amount( sanitize_text_field( $args['amount'] ?? '0' ) );

		if ( ! empty( $args['expiry_date'] ) ) {
			$coupon->set_date_expires( $args['expiry_date'] );
		}
		if ( isset( $args['usage_limit'] ) ) {
			$coupon->set_usage_limit( (int) $args['usage_limit'] );
		}
		if ( ! empty( $args['minimum_amount'] ) ) {
			$coupon->set_minimum_amount( $args['minimum_amount'] );
		}
		if ( ! empty( $args['product_ids'] ) ) {
			$coupon->set_product_ids( array_map( 'intval', $args['product_ids'] ) );
		}

		$coupon_id = $coupon->save();

		return $this->json_response( [
			'success'   => true,
			'coupon_id' => $coupon_id,
			'code'      => $coupon->get_code(),
		] );
	}

	private function update_coupon( array $args ): array {
		if ( empty( $args['coupon_id'] ) ) {
			throw new \InvalidArgumentException( 'Coupon ID is required for update.' );
		}

		$coupon = new \WC_Coupon( (int) $args['coupon_id'] );
		if ( ! $coupon->get_id() ) {
			throw new \InvalidArgumentException( 'Coupon not found.' );
		}

		if ( isset( $args['amount'] ) ) {
			$coupon->set_amount( sanitize_text_field( $args['amount'] ) );
		}
		if ( isset( $args['expiry_date'] ) ) {
			$coupon->set_date_expires( $args['expiry_date'] );
		}
		if ( isset( $args['usage_limit'] ) ) {
			$coupon->set_usage_limit( (int) $args['usage_limit'] );
		}

		$coupon->save();

		return $this->json_response( [
			'success'   => true,
			'coupon_id' => $coupon->get_id(),
			'code'      => $coupon->get_code(),
		] );
	}

	private function delete_coupon( array $args ): array {
		if ( empty( $args['coupon_id'] ) ) {
			throw new \InvalidArgumentException( 'Coupon ID is required for delete.' );
		}

		$coupon = new \WC_Coupon( (int) $args['coupon_id'] );
		if ( ! $coupon->get_id() ) {
			throw new \InvalidArgumentException( 'Coupon not found.' );
		}

		$coupon->delete( true );

		return $this->json_response( [
			'success'   => true,
			'coupon_id' => (int) $args['coupon_id'],
			'action'    => 'deleted',
		] );
	}

	private function list_coupons(): array {
		$coupons = get_posts( [
			'post_type'      => 'shop_coupon',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
		] );

		$data = [];
		foreach ( $coupons as $coupon_post ) {
			$coupon = new \WC_Coupon( $coupon_post->ID );
			$data[] = [
				'id'            => $coupon->get_id(),
				'code'          => $coupon->get_code(),
				'discount_type' => $coupon->get_discount_type(),
				'amount'        => $coupon->get_amount(),
				'expiry_date'   => $coupon->get_date_expires() ? $coupon->get_date_expires()->date( 'Y-m-d' ) : null,
				'usage_count'   => $coupon->get_usage_count(),
				'usage_limit'   => $coupon->get_usage_limit(),
			];
		}

		return $this->json_response( [ 'coupons' => $data ] );
	}
}
