<?php

// Prevent accessing directly
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * Perform actions based on message from vipps response
 */
function perform_vipps_callback( $method, $order_id = null, $request_id = null, $skip_error = false ) {

	$success = false;

	if ( $order_id === null ) {
		$order_id = sanitize_text_field( $_GET['order_id'] );
	}
	if ( $request_id === null ) {
		$request_id = sanitize_text_field( $_GET['vipps_request_id'] );
	}

	if ( $method === 'express' ) {
		$merchant_number = get_option( 'bp_vipps_express_merchant' );
		$client_id       = get_option( 'bp_vipps_express_client_id' );
		$client_secret   = get_option( 'bp_vipps_express_client_secret' );
		$primary_key     = get_option( 'bp_vipps_express_primary_key' );
	} else {
		$merchant_number = get_option( 'bp_vipps_one_click_merchant' );
		$client_id       = get_option( 'bp_vipps_one_click_client_id' );
		$client_secret   = get_option( 'bp_vipps_one_click_client_secret' );
		$primary_key     = get_option( 'bp_vipps_one_click_primary_key' );
	}

	$primary_key_auth = get_option( 'bp_vipps_auth_primary_key' );

	// Collect data
	$json      = file_get_contents( 'php://input' );
	$data      = json_decode( sanitize_text_field( $json ), true );
	$order     = wc_get_order( $order_id );
	$total     = $order->get_total() * 100; // vipps takes value as 'øre'
	$datetime  = new DateTime();
	$timestamp = $datetime->format( DateTime::ATOM );

	// Store callback
	$order->update_meta_data( 'vipps_callback', json_encode( $data ) );
	$order->save();

	// Only allow callback for pending orders
	if ( $order->get_status() === 'pending' ) {
		// Detect errors
		$error = isset( $data['errorInfo'] ) ? $data['errorInfo'] : null;
		if ( ! $skip_error && isset( $data['errorInfo']['errorMessage'] ) ) {
			if ( isset( $data['errorInfo']['errorCode'] ) && (string) $data['errorInfo']['errorCode'] === '41' ) {
				$error_message = __( 'User don\'t have a valid card', 'woo-gateway-vipps' );
			} else {
				$error_message = $data['errorInfo']['errorMessage'];
			}
			$order->update_status( 'failed', $error_message );
			$order->save();
			// Detect cancellation
		} else if ( ! $skip_error && ( ! isset( $data['transactionInfo']['status'] ) || $data['transactionInfo']['status'] !== 'RESERVED' ) ) {
			$error_message = __( 'Unknown error', 'woo-gateway-vipps' );
			if ( $data['transactionInfo']['status'] === 'CANCELLED' ) {
				$error_message = __( 'Kansellert via appen', 'woo-gateway-vipps' );
			}
			$order->update_status( 'cancelled', $error_message );
			$order->save();
			// Proceed & capture
		} else {

			// Validate that payment is correctly handled by vipps
			if ( ! $skip_error ) {
				$amount = isset( $data['transactionInfo']['amount'] ) ? $data['transactionInfo']['amount'] : 0;
				if ( intval( $amount ) !== intval( $total ) ) {
					$error_message = 'Amount received from vipps (' . $amount . ') is not equal to Order Total (' . $total . ')';
					$order->update_status( 'on-hold', $error_message );
					$order->save();
				} else {
					$vipps_order_id = isset( $data['orderId'] ) ? $data['orderId'] : 0;
					if ( (string) $vipps_order_id !== (string) $order_id ) {
						$error_message = 'Order Id received from vipps (' . $vipps_order_id . ') is not equal to Order Id (' . $order_id . ')';
						$order->update_status( 'on-hold', $error_message );
						$order->save();
					}
				}
			}

			// Get token
			include_once( plugin_dir_path( __FILE__ ) . '/generate_token.php' );
			$results = generate_token( $client_id, $client_secret, $primary_key_auth );

			// Handle error
			if ( ! $results['success'] ) {
				$order->update_status( 'on-hold' );
				$order->update_meta_data( 'vipps_access_error', json_encode( $results['error'] ) );
				$order->save();
			} else {

				$token = $results['token'];

				// Capture reservation
				$capture_endpoint = 'https://api.vipps.no/Ecomm/v1/payments/';
				$postfields       = [
						'merchantInfo' => [
								'merchantSerialNumber' => $merchant_number
						],
						'transaction'  => [
								'amount'          => $total,
								'transactionText' => __( 'Payment completed', 'woo-gateway-vipps' )
						]
				];
				if ( $method === 'express' ) {
					$capture_endpoint = 'https://api.vipps.no/ecomm/v2/payments/';
				}

				// Make request
				$curl = curl_init();
				curl_setopt_array( $curl, [
						CURLOPT_CUSTOMREQUEST  => 'POST',
						CURLOPT_URL            => $capture_endpoint . $order_id . '/capture',
						CURLOPT_HEADER         => false,
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_VERBOSE        => true,
						CURLOPT_POSTFIELDS     => json_encode( $postfields ),
						CURLOPT_HTTPHEADER     => [
								'Authorization: Bearer ' . $token,
								'X-Request-Id: ' . $request_id,
								'X-TimeStamp: ' . $timestamp,
								'X-Source-Address: ' . $_SERVER['SERVER_ADDR'],
								'X-App-Id: ' . $client_id,
								'Content-Type: application/json',
								'Ocp-Apim-Subscription-Key: ' . $primary_key
						]
				] );
				$results = curl_exec( $curl );
				curl_close( $curl );
				$data = json_decode( sanitize_text_field( $results ), true );

				$preCaptured = false;
				// Detect if already captured:
				if ( isset( $data['errorCode'] ) && (string) $data['errorCode'] === '61' ) {
					$preCaptured = true;
				}

				// Store response
				$order->update_meta_data( 'vipps_capture', json_encode( $data ) );
				$order->save();

				// Handle response
				if ( $preCaptured || ( isset( $data['transactionInfo']['status'] ) && (string) $data['transactionInfo']['status'] === 'Captured' ) ) {

					$amount   = isset( $data['transactionInfo']['amount'] ) ? $data['transactionInfo']['amount'] : null;
					$captured = isset( $data['transactionSummary']['capturedAmount'] ) ? $data['transactionSummary']['capturedAmount'] : null;

					// If captured correctly
					if ( $preCaptured || ( $amount !== null && intval( $amount ) === intval( $captured ) ) ) {

						// Reduce stock levels
						if ( method_exists( $order, 'reduce_order_stock' ) ) {
							$order->reduce_order_stock();
						}

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

						// If captured but doesnt match order total
					} else {

						$order->add_order_note( 'Beløpet i ordren: ' . intval( $total ) . ' samsvarer ikke med beløpet trukket fra vipps: ' . intval( $captured ) );
						$order->update_status( 'on-hold' );
						$order->save();

					}

					$success = true;

				} else {

					$order->add_order_note( 'vipps kunne ikke trekke beløpet.' );
					$order->update_status( 'failed' );
					$order->save();

				}
			}

		}
	} else if ( $order->get_status() === 'processing' || $order->get_status() === 'on-hold' || $order->get_status() === 'completed' ) {
		$success = true;
	}

	return $success;

}