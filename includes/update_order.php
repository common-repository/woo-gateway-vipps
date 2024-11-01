<?php

// Prevent accessing directly
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

function update_order( $order_id, $status, $notice = null ) {

	$order = wc_get_order( $order_id );

	if ( $notice !== null ) {
		$order->add_order_note( $notice );
	}

	if ( $order->get_status() === 'pending' && ( $status === 'completed' || $status === 'processing' ) ) {

		$downloadable = 0;
		$virtual      = 0;
		$count        = 0;
		$items        = $order->get_items();

		foreach ( $items as $item ) {
			if ( method_exists( $item, 'downloadable' ) && $item->downloadable() ) {
				$downloadable ++;
			} else {
				$downloadable --;
			}
			if ( method_exists( $item, 'is_virtual' ) && $item->is_virtual() ) {
				$virtual ++;
			} else {
				$virtual --;
			}
			$count ++;
		}

		// If only virtual and downloadable products, processing is not needed
		if ( $downloadable === $count && $virtual === $count ) {
			$order->update_status( 'completed' );
		} else {
			$order->update_status( 'processing' );
		}

		$order->add_order_note( 'Betaling med vipps fullført' );
		$order->payment_complete();
		$order->save();

	} else if ( $status === 'refunded' ) {

		if ( wp_verify_nonce( $_REQUEST['nonce'], 'bp-admin' ) ) {

			$order->update_status( 'refunded' );
			$order->save();

		} else {

			return [
					'success' => __( 'Du trenger administrator rettigheter for dette', 'woo-gateway-vipps' )
			];

		}

	} else if ( $order->get_status() === 'pending' ) {

		$order->add_order_note( 'vipps kunne ikke trekke beløpet.' );
		$order->update_status( $status );
		$order->save();

	} else {

		return [
				'error' => __( 'Kun avventende ordre kan oppdateres her', 'woo-gateway-vipps' )
		];

	}

	return [
			'success' => __( 'Status oppdatert', 'woo-gateway-vipps' )
	];

}