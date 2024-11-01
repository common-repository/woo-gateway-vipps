<?php

// Prevent accessing directly
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

function cancel_order( $order_id ) {

	// Remove only if status is pending
	$order = wc_get_order( $order_id );
	if ( $order->get_status() === 'pending' ) {
		wp_delete_post( $order_id, true );

		return [
				'order_id' => $order_id,
				'status'   => 'removed'
		];
	} else {
		return [
				'order_id' => $order_id,
				'error'    => 'Could not be removed because it is not pending'
		];
	}

}