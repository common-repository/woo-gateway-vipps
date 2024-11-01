<?php

// Prevent accessing directly
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

function perform_vipps_checkout( $ip, $version, $phone, $error = false ) {

	include_once plugin_dir_path( __FILE__ ) . '/enqueue_app.php';

	$phone    = ( isset( $_GET['phone'] ) && $_GET['phone'] !== '' ) ? sanitize_text_field( $_GET['phone'] ) : $phone;
	$order_id = sanitize_text_field( $_GET['vipps_approve_order'] );
	$total    = 0;
	if ( $order = wc_get_order( $order_id ) ) {
		$total = $order->get_total() * 100; // Convert to 'øre'
	}

	enqueue_app( 'middleware', $ip, $version, $phone, $order_id, $total, $error );

	return '<div id="boxplugins-vipps-checkout"></div>';

}

?>