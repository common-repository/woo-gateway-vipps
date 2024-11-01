<?php

// Prevent accessing directly
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

function perform_vipps_proxy( $axios ) {

	$axios['headers'] = json_decode( $axios['headers'], true );
	$url              = isset( $axios['url'] ) ? $axios['url'] : '';
	$method           = isset( $axios['method'] ) ? $axios['method'] : '';
	$data             = $axios['data'];

	/**
	 * Only allow use of primary keys if request is sent to vipps with https,
	 * else it should be rejected in case of sniffing. This should keep keys hidden for client.
	 */
	if ($data !== '' && substr($url,0,21) === 'https://api.vipps.no/') {
		// Correct primary_keys which is hidden in js client
		$data = str_replace('one_pm_key', get_option( 'bp_vipps_one_click_primary_key' ), $data);
		$data = str_replace('express_pm_key', get_option( 'bp_vipps_express_primary_key' ), $data);
		$data = str_replace('auth_pm_key', get_option( 'bp_vipps_auth_primary_key' ), $data);
		$data = str_replace('one_id', get_option( 'bp_vipps_one_click_client_id' ), $data);
		$data = str_replace('one_secret', get_option( 'bp_vipps_one_click_client_secret' ), $data);
		$data = str_replace('express_id', get_option( 'bp_vipps_express_client_id' ), $data);
		$data = str_replace('express_secret', get_option( 'bp_vipps_express_client_secret' ), $data);
	}

	$headers = [];
	if ($axios['headers'] !== null) {
		foreach ( $axios['headers'] as $key => $value ) {

			/**
			 * Only allow use of primary keys if request is sent to vipps with https,
			 * else it should be rejected in case of sniffing. This should keep keys hidden for client.
			 */
			if (substr($url,0,21) === 'https://api.vipps.no/') {
				// Correct primary_keys which is hidden in js client
				$value = str_replace('one_pm_key', get_option( 'bp_vipps_one_click_primary_key' ), $value);
				$value = str_replace('express_pm_key', get_option( 'bp_vipps_express_primary_key' ), $value);
				$value = str_replace('auth_pm_key', get_option( 'bp_vipps_auth_primary_key' ), $value);
				$value = str_replace('one_id', get_option( 'bp_vipps_one_click_client_id' ), $value);
				$value = str_replace('one_secret', get_option( 'bp_vipps_one_click_client_secret' ), $value);
				$value = str_replace('express_id', get_option( 'bp_vipps_express_client_id' ), $value);
				$value = str_replace('express_secret', get_option( 'bp_vipps_express_client_secret' ), $value);
			}

			$headers[] = $key . ': ' . $value;
		}
	}

	if ($url === 'https://boxplugins.no') {
		// Call the custom API.
		$response = wp_remote_post( $url,
				array( 'timeout'   => 15,
				       'sslverify' => false,
				       'body'      => array(
						       'edd_action' => $data['edd_action'],
						       'license'    => $data['license'],
						       'item_name'  => $data['item_name'],
						       'url'        => $data['url']
				       )
				) );

		if ( is_wp_error( $response ) ) {
			$response = [
					'error'   => true,
					'success' => false,
					'message' => $response
			];
		} else {
			$license_data = json_decode( wp_remote_retrieve_body( $response ) );
			$response = [
					'error'   => false,
					'success' => true,
					'message' => isset( $license_data->license ) ? $license_data->license : 'invalid' // 'valid' / 'invalid'
			];
		}
	} else {

		$opt = [
				CURLOPT_URL            => $url,
				CURLOPT_HEADER         => false,
				CURLOPT_HTTPHEADER     => $headers,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_VERBOSE        => true
		];

		if ( $data !== '' ) {
			$opt[ CURLOPT_POSTFIELDS ] = $data;
		}

		if ( $method !== '' ) {
			$opt[ CURLOPT_CUSTOMREQUEST ] = $method;
		}

		$curl = curl_init();
		curl_setopt_array( $curl, $opt );

		// Get Headers
		curl_setopt( $curl, CURLOPT_HEADERFUNCTION,
				function ( $curl, $header ) use ( &$headers ) {
					$len    = strlen( $header );
					$header = explode( ':', $header, 2 );
					if ( count( $header ) < 2 ) {
						return $len;
					}
					$name             = strtolower( trim( $header[0] ) );
					$headers[ $name ] = trim( $header[1] );

					return $len;
				}
		);

		// Get Body
		$results = json_decode( curl_exec( $curl ) );
		curl_close( $curl );

		// Pass response back to client
		$response = [
				'body'    => $results,
				'headers' => $headers
		];

	}

	return $response;
}