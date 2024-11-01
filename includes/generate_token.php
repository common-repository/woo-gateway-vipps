<?php

// Prevent accessing directly
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

function generate_token( $client_id, $client_secret, $primary_key_auth, $test = false ) {

	if ( ! $test ) {
		$url = 'https://api.vipps.no/accessToken/get';
	} else {
		$url = 'https://apitest.vipps.no/accessToken/get';
	}

	// Get token
	$curl = curl_init();
	curl_setopt_array( $curl, [
			CURLOPT_CUSTOMREQUEST  => 'POST',
			CURLOPT_URL            => $url,
			CURLOPT_HEADER         => false,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_VERBOSE        => true,
			CURLOPT_HTTPHEADER     => [
					'client_id: ' . $client_id,
					'client_secret: ' . $client_secret,
					'Ocp-Apim-Subscription-Key: ' . $primary_key_auth,
					'Content-Length: 0'
			]
	] );
	$results = curl_exec( $curl );
	curl_close( $curl );
	$results = json_decode( $results, true );

	// Validate token
	$error = false;
	if ( isset( $results['access_token'] ) ) {
		$token = $results['access_token'];
	} else if ( isset( $results['error_description'] ) ) {
		$error = $results['error_description'];
	} else if ( isset( $results['error'] ) ) {
		$error = $results['error'];
	} else {
		$error = __( 'Unknown error: ', 'woo-gateway-vipps' ) . json_encode( $results, JSON_PRETTY_PRINT );
	}

	// Handle errors
	if ( $error ) {
		return [
				'success' => false,
				'error'   => $error,
				'token'   => ''
		];
	} else {
		return [
				'success' => true,
				'error'   => '',
				'token'   => $token
		];
	}

}