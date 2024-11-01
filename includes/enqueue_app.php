<?php

function enqueue_app( $field = 'checkout', $ip = '', $version = '', $phone = '', $order_id = '', $total = 0, $error = false ) {

	wp_register_script( 'vipps_checkout_runtime', plugins_url( '../app/js/runtime.js', __FILE__ ), array(), $version, true );
	wp_register_script( 'vipps_checkout_vendor', get_home_url() . '?bp_vipps_method=bp_hide&decode=vendor', array(), $version, true );
	wp_register_script( 'vipps_checkout_main', get_home_url() . '?bp_vipps_method=bp_hide&decode=app', array(), $version, true );

	// Common data
	$data = [
			'field'            => $field,
			'server'           => get_home_url(),
			// 'license'          => get_option( 'bp_vipps_purchase_code' ),
			'ip'               => $ip,
			'phone'            => $phone,
			'theme'            => get_option( 'bp_vipps_theme' ),
			'method'           => get_option( 'bp_vipps_method' ),
			'type'             => get_option( 'bp_vipps_type' ),
			'fallback'         => get_option( 'bp_vipps_fallback' ),
			'fallback_login'   => get_option( 'bp_vipps_fallback_login' ),
			'primary_key_auth' => 'auth_pm_key',
			'prefix'           => get_option( 'bp_vipps_prefix' ),
			'suffix'           => get_option( 'bp_vipps_suffix' )
	];

	include_once( plugin_dir_path( __FILE__ ) . '/generate_token.php' );

	// One Click
	$token_one = generate_token(
			get_option( 'bp_vipps_one_click_client_id' ),
			get_option( 'bp_vipps_one_click_client_secret' ),
			get_option( 'bp_vipps_auth_primary_key' )
	)['token'];
	$data      = array_merge( $data, [
			'primary_key_one'     => 'one_pm_key',
			'client_id_one'       => get_option( 'bp_vipps_one_click_client_id' ),
			'merchant_number_one' => get_option( 'bp_vipps_one_click_merchant' ),
			'token_one'           => $token_one
	] );

	// Express
	$token_express = generate_token(
			get_option( 'bp_vipps_express_client_id' ),
			get_option( 'bp_vipps_express_client_secret' ),
			get_option( 'bp_vipps_auth_primary_key' )
	)['token'];
	$data          = array_merge( $data, [
			'primary_key_express'     => 'express_pm_key',
			'client_id_express'       => get_option( 'bp_vipps_express_client_id' ),
			'merchant_number_express' => get_option( 'bp_vipps_express_merchant' ),
			'token_express'           => $token_express
	] );

	if ( $error ) {

		$data = array_merge( $data, [
				'error' => true
		] );

	} else if ( ($field === 'admin' || $field === 'order') && is_admin() ) {

		if ( $field === 'order' ) {

			$data         = array_merge( $data, [
					'order_id' => $order_id
			] );

		}

		// Add secrets
		$data = array_merge( $data, [
				'client_secret_one'     => get_option( 'bp_vipps_one_click_client_secret' ),
				'client_secret_express' => get_option( 'bp_vipps_express_client_secret' ),
				'nonce'                 => wp_create_nonce( 'bp-admin' )
		] );
		$data['primary_key_auth'] = get_option( 'bp_vipps_auth_primary_key' );
		$data['primary_key_one'] = get_option( 'bp_vipps_one_click_primary_key' );
		$data['primary_key_express'] = get_option( 'bp_vipps_express_primary_key' );

		// Auth Test
		$data = array_merge( $data, [
				'primary_key_auth_test' => get_option( 'bp_vipps_auth_primary_key_test' )
		] );

		// One Click Test
		$token_one_test = generate_token(
				get_option( 'bp_vipps_one_click_client_id_test' ),
				get_option( 'bp_vipps_one_click_client_secret_test' ),
				get_option( 'bp_vipps_auth_primary_key' ),
				true
		)['token'];
		$data           = array_merge( $data, [
				'primary_key_one_test'     => get_option( 'bp_vipps_one_click_primary_key_test' ),
				'client_id_one_test'       => get_option( 'bp_vipps_one_click_client_id_test' ),
				'client_secret_one_test'   => get_option( 'bp_vipps_one_click_client_secret_test' ),
				'merchant_number_one_test' => get_option( 'bp_vipps_one_click_merchant_test' ),
				'token_one_test'           => $token_one_test
		] );

		// Express Test
		$token_express_test = generate_token(
				get_option( 'bp_vipps_express_client_id_test' ),
				get_option( 'bp_vipps_express_client_secret_test' ),
				get_option( 'bp_vipps_express_primary_key_test' ),
				true
		)['token'];
		$data               = array_merge( $data, [
				'primary_key_express_test'     => get_option( 'bp_vipps_express_primary_key_test' ),
				'client_id_express_test'       => get_option( 'bp_vipps_express_client_id_test' ),
				'client_secret_express_test'   => get_option( 'bp_vipps_express_client_secret_test' ),
				'merchant_number_express_test' => get_option( 'bp_vipps_express_merchant_test' ),
				'token_express_test'           => $token_express_test
		] );

	} else if ( $field === 'middleware' ) {

		$data['type'] = 'middleware';
		$data         = array_merge( $data, [
				'order_id' => $order_id,
				'total'    => $total
		] );

	} else if ( $field === 'checkout' ) {

		$data = array_merge( $data, [] );

	} else if ( $field === 'shortcode' ) {

		$data = array_merge( $data, [] );

	}

	wp_localize_script( 'vipps_checkout_main', 'vipps_checkout_data', $data );

	wp_enqueue_script( 'vipps_checkout_runtime' );
	wp_enqueue_script( 'vipps_checkout_vendor' );
	wp_enqueue_script( 'vipps_checkout_main' );

	wp_enqueue_style( 'vipps_checkout', plugins_url( '../app/css/app.css', __FILE__ ), array(), $version );

}