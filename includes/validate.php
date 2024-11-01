<?php

// Prevent accessing directly
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

function perform( $order_id ) {

	$order = wc_get_order( $order_id );

	// Pass response back to client
	if ( $order->get_status() !== 'failed' ) {
		$response = json_encode( [
				'status' => $order->get_status()
		] );
	} else {
		$order_meta = get_post_meta( $order_id );
		$response   = json_encode( [
				'status'    => $order->get_status(),
				'error'     => $order_meta['_vipps_error'][0],
				'capturing' => isset( $order_meta['_vipps_processing'][0] ) ? 'yes' : 'no'
		] );
	}

	if ( $order->get_status() === 'processing' || $order->get_status() === 'on-hold' || $order->get_status() === 'completed' ) {
		global $woocommerce;
		$woocommerce->cart->empty_cart();
	}

	return $response;

}