<?php

// Prevent accessing directly
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

function save_settings( $settings ) {

	if ( wp_verify_nonce( $_REQUEST['nonce'], 'bp-admin' ) ) {

		$valid = [
				// 'bp_vipps_purchase_code',
				'bp_vipps_one_click_merchant',
				'bp_vipps_one_click_client_id',
				'bp_vipps_one_click_client_secret',
				'bp_vipps_one_click_primary_key',
				'bp_vipps_express_merchant',
				'bp_vipps_express_client_id',
				'bp_vipps_express_client_secret',
				'bp_vipps_express_primary_key',
				'bp_vipps_auth_primary_key',
				'bp_vipps_one_click_merchant_test',
				'bp_vipps_one_click_client_id_test',
				'bp_vipps_one_click_client_secret_test',
				'bp_vipps_one_click_primary_key_test',
				'bp_vipps_express_merchant_test',
				'bp_vipps_express_client_id_test',
				'bp_vipps_express_client_secret_test',
				'bp_vipps_express_primary_key_test',
				'bp_vipps_auth_primary_key_test',
				'bp_vipps_theme',
				'bp_vipps_method',
				'bp_vipps_type',
				'bp_vipps_fallback',
				'bp_vipps_fallback_login',
				'bp_vipps_prefix',
				'bp_vipps_suffix'
		];

		foreach ( $settings as $key => $value ) {
			if ( in_array( $key, $valid ) ) {
				update_option( $key, $value );
			}
		}

		return [
				'success' => __( 'Innstillinger lagret', 'woo-gateway-vipps' )
		];

	} else {

		return [
				'error' => __( 'Du trenger administrator rettigheter for dette', 'woo-gateway-vipps' )
		];

	}

}