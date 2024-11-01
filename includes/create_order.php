<?php

// Prevent accessing directly
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

function order_from_cart( $customer_note = '' ) {

	function create_order_line_items( &$order, $cart ) {
		foreach ( $cart->get_cart() as $cart_item_key => $values ) {
			/**
			 * Filter hook to get initial item object.
			 * @since 3.1.0
			 */
			$item                       = apply_filters( 'woocommerce_checkout_create_order_line_item_object', new WC_Order_Item_Product(), $cart_item_key, $values, $order );
			$product                    = $values['data'];
			$item->legacy_values        = $values; // @deprecated For legacy actions.
			$item->legacy_cart_item_key = $cart_item_key; // @deprecated For legacy actions.
			$item->set_props( array(
					'quantity'     => $values['quantity'],
					'variation'    => $values['variation'],
					'subtotal'     => $values['line_subtotal'],
					'total'        => $values['line_total'],
					'subtotal_tax' => $values['line_subtotal_tax'],
					'total_tax'    => $values['line_tax'],
					'taxes'        => $values['line_tax_data'],
			) );
			if ( $product ) {
				$item->set_props( array(
						'name'         => $product->get_name(),
						'tax_class'    => $product->get_tax_class(),
						'product_id'   => $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id(),
						'variation_id' => $product->is_type( 'variation' ) ? $product->get_id() : 0,
				) );
			}
			$item->set_backorder_meta();

			/**
			 * Action hook to adjust item before save.
			 * @since 3.0.0
			 */
			do_action( 'woocommerce_checkout_create_order_line_item', $item, $cart_item_key, $values, $order );

			// Add item to order and save.
			$order->add_item( $item );
		}
	}

	function create_order_fee_lines( &$order, $cart ) {
		foreach ( $cart->get_fees() as $fee_key => $fee ) {
			$item                 = new WC_Order_Item_Fee();
			$item->legacy_fee     = $fee; // @deprecated For legacy actions.
			$item->legacy_fee_key = $fee_key; // @deprecated For legacy actions.
			$item->set_props( array(
					'name'      => $fee->name,
					'tax_class' => $fee->taxable ? $fee->tax_class : 0,
					'amount'    => $fee->amount,
					'total'     => $fee->total,
					'total_tax' => $fee->tax,
					'taxes'     => array(
							'total' => $fee->tax_data,
					),
			) );

			/**
			 * Action hook to adjust item before save.
			 * @since 3.0.0
			 */
			do_action( 'woocommerce_checkout_create_order_fee_item', $item, $fee_key, $fee, $order );

			// Add item to order and save.
			$order->add_item( $item );
		}
	}

	function create_order_shipping_lines( &$order, $chosen_shipping_methods, $packages ) {
		foreach ( $packages as $package_key => $package ) {
			if ( isset( $chosen_shipping_methods[ $package_key ], $package['rates'][ $chosen_shipping_methods[ $package_key ] ] ) ) {
				/** @var WC_Shipping_Rate $shipping_rate */
				$shipping_rate            = $package['rates'][ $chosen_shipping_methods[ $package_key ] ];
				$item                     = new WC_Order_Item_Shipping();
				$item->legacy_package_key = $package_key; // @deprecated For legacy actions.
				$item->set_props( array(
						'method_title' => $shipping_rate->label,
						'method_id'    => $shipping_rate->id,
						'total'        => wc_format_decimal( $shipping_rate->cost ),
						'taxes'        => array(
								'total' => $shipping_rate->taxes,
						),
				) );

				foreach ( $shipping_rate->get_meta_data() as $key => $value ) {
					$item->add_meta_data( $key, $value, true );
				}

				/**
				 * Action hook to adjust item before save.
				 * @since 3.0.0
				 */
				do_action( 'woocommerce_checkout_create_order_shipping_item', $item, $package_key, $package, $order );

				// Add item to order and save.
				$order->add_item( $item );
			}
		}
	}

	function create_order_tax_lines( &$order, $cart ) {
		foreach ( array_keys( $cart->get_cart_contents_taxes() + $cart->get_shipping_taxes() + $cart->get_fee_taxes() ) as $tax_rate_id ) {
			if ( $tax_rate_id && apply_filters( 'woocommerce_cart_remove_taxes_zero_rate_id', 'zero-rated' ) !== $tax_rate_id ) {
				$item = new WC_Order_Item_Tax();
				$item->set_props( array(
						'rate_id'            => $tax_rate_id,
						'tax_total'          => $cart->get_tax_amount( $tax_rate_id ),
						'shipping_tax_total' => $cart->get_shipping_tax_amount( $tax_rate_id ),
						'rate_code'          => WC_Tax::get_rate_code( $tax_rate_id ),
						'label'              => WC_Tax::get_rate_label( $tax_rate_id ),
						'compound'           => WC_Tax::is_compound( $tax_rate_id ),
				) );

				/**
				 * Action hook to adjust item before save.
				 * @since 3.0.0
				 */
				do_action( 'woocommerce_checkout_create_order_tax_item', $item, $tax_rate_id, $order );

				// Add item to order and save.
				$order->add_item( $item );
			}
		}
	}

	function create_order_coupon_lines( &$order, $cart ) {
		foreach ( $cart->get_coupons() as $code => $coupon ) {
			$item = new WC_Order_Item_Coupon();
			$item->set_props( array(
					'code'         => $code,
					'discount'     => $cart->get_coupon_discount_amount( $code ),
					'discount_tax' => $cart->get_coupon_discount_tax_amount( $code ),
			) );
			$item->add_meta_data( 'coupon_data', $coupon->get_data() );

			/**
			 * Action hook to adjust item before save.
			 * @since 3.0.0
			 */
			do_action( 'woocommerce_checkout_create_order_coupon_item', $item, $code, $coupon, $order );

			// Add item to order and save.
			$order->add_item( $item );
		}
	}

	try {
		$cart_hash = md5( json_encode( wc_clean( WC()->cart->get_cart_for_session() ) ) . WC()->cart->total );
		$order     = new WC_Order();
		$order->set_created_via( 'checkout' );
		$order->set_cart_hash( $cart_hash );
		$order->set_customer_id( apply_filters( 'woocommerce_checkout_customer_id', get_current_user_id() ) );
		$order->set_currency( get_woocommerce_currency() );
		$order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );
		$order->set_customer_ip_address( WC_Geolocation::get_ip_address() );
		$order->set_customer_user_agent( wc_get_user_agent() );
		$order->set_customer_note( $customer_note );
		$order->set_payment_method( 'bp-vipps' );
		$order->set_shipping_total( WC()->cart->get_shipping_total() );
		$order->set_discount_total( WC()->cart->get_discount_total() );
		$order->set_discount_tax( WC()->cart->get_discount_tax() );
		$order->set_cart_tax( WC()->cart->get_cart_contents_tax() + WC()->cart->get_fee_tax() );
		$order->set_shipping_tax( WC()->cart->get_shipping_tax() );
		$order->set_total( WC()->cart->get_total( 'edit' ) );
		create_order_line_items( $order, WC()->cart );
		create_order_fee_lines( $order, WC()->cart );
		create_order_shipping_lines( $order, WC()->session->get( 'chosen_shipping_methods' ), WC()->shipping->get_packages() );
		create_order_tax_lines( $order, WC()->cart );
		create_order_coupon_lines( $order, WC()->cart );

		/**
		 * Action hook to adjust order before save.
		 * @since 3.0.0
		 */
		do_action( 'woocommerce_checkout_create_order', $order, [] );

		// Save the order.
		$order_id = $order->save();

		do_action( 'woocommerce_checkout_update_order_meta', $order_id, [] );

		return $order_id;
	} catch ( Exception $e ) {
		return new WP_Error( 'checkout-error', $e->getMessage() );
	}
}

function create_order( $billing = null, $shipping = null, $note = '', $demo = false, $feeamount = 100 ) {

	if ( ! $demo ) {
		// Create order from cart
		$order_id = order_from_cart( $note );
		$order    = wc_get_order( $order_id );
		$order->calculate_totals();
		$order->update_status( 'pending', __( 'Betaling med vipps er under behandling', 'woo-gateway-vipps' ) );
		if ( $billing !== null ) {
			$order->set_billing_first_name( $billing['first_name'] );
			$order->set_billing_last_name( $billing['last_name'] );
			$order->set_billing_company( $billing['company'] );
			$order->set_billing_address_1( $billing['address_1'] );
			$order->set_billing_address_2( $billing['address_2'] );
			$order->set_billing_city( $billing['city'] );
			$order->set_billing_state( $billing['state'] );
			$order->set_billing_postcode( $billing['postcode'] );
			$order->set_billing_country( $billing['country'] );
			$order->set_billing_email( $billing['email'] );
			$order->set_billing_phone( $billing['phone'] );
		}
		if ( $shipping !== null ) {
			$order->set_shipping_first_name( $shipping['first_name'] );
			$order->set_shipping_last_name( $shipping['last_name'] );
			$order->set_shipping_company( $shipping['company'] );
			$order->set_shipping_address_1( $shipping['address_1'] );
			$order->set_shipping_address_2( $shipping['address_2'] );
			$order->set_shipping_city( $shipping['city'] );
			$order->set_shipping_state( $shipping['state'] );
			$order->set_shipping_postcode( $shipping['postcode'] );
			$order->set_shipping_country( $shipping['country'] );
		}
		$order->save();
	} else {

		$fee = array( 'name' => 'vipps_demo', 'amount' => $feeamount, 'taxable' => false, 'tax_class' => '' );

		function add_fee( $_fee, $order_id ) {

			$item_id = wc_add_order_item( $order_id, array(
					'order_item_name' => $_fee['name'],
					'order_item_type' => 'fee'
			) );

			wc_add_order_item_meta( $item_id, '_tax_class', '0' );
			wc_add_order_item_meta( $item_id, '_line_total', wc_format_decimal( $_fee['amount'] ) );

			do_action( 'woocommerce_order_add_fee', $order_id, $item_id, $_fee );

			return $item_id;

		}

		$order    = wc_create_order( [ 'customer_id' => get_current_user_id() ] );
		$order_id = $order->get_id();
		add_fee( $fee, $order_id );
		$order->calculate_totals();
		$order->update_status( 'pending', __( 'Demo betaling med vipps er under behandling', 'woo-gateway-vipps' ) );
		$order->save();

	}

	return [
			// 'uzS20aKklZplu' => get_option( 'vipps_woocommerce_license_key' ),
			'order_id'      => $order_id,
			'fee'           => $feeamount, // In 'øre'
			'billing'       => $billing,
			'shipping'      => $shipping,
			'note'          => $note,
			'total'         => $order->get_total() * 100
	];

}