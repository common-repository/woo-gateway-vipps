<?php
/**
 *
 * Licensing
 *
 * @since    1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VIPPS_LICENSING {

	/**
	 * Setup class
	 * @since 1.0.0
	 */
	public function __construct( $product_name, $master_url, $beta, $require_ssl, $author ) {
		$this->license_init();
		$this->product_name = $product_name;
		$this->master_url   = $master_url;
		$this->beta         = $beta;
		$this->require_ssl  = $require_ssl;
		$this->author       = $author;
	}

	/**
	 * Init Licensing
	 * @since 1.0.0
	 */
	private function license_init() {

		add_action( 'admin_init', array( $this, 'vipps_register_option' ) );
		add_action( 'admin_notices', array( $this, 'vipps_admin_notices' ) );

	}

	/**
	 * Store License in Wordpress
	 * @since 1.0.0
	 */
	function vipps_register_option() {
		// creates our settings in the options table
		add_option( 'vipps_woocommerce_license_status', '' );
	}

	/**
	 * Sanitize License
	 * @since 1.0.0
	 */
	function vipps_sanitize_license( $new ) {
		$old = get_option( 'bp_vipps_purchase_code' );
		if ( $old && $old != $new ) {
			delete_option( 'vipps_woocommerce_license_status' ); // new license has been entered, so must reactivate
		}

		return $new;
	}

	/**
	 * Activate License
	 * @since 1.0.0
	 */
	function activate_license( $license = '' ) {

		// Deactivate current license so it can be used elsewhere
		$this->deactivate_license();

		$license = trim( $license );

		// data to send in our API request
		$api_params = array(
				'edd_action' => 'activate_license',
				'license'    => $license,
				'item_name'  => urlencode( $this->product_name ), // the name of our product in EDD
				'url'        => home_url()
		);

		// Call the custom API
		$response = wp_remote_post( $this->master_url, array( 'timeout'   => 15,
		                                                      'sslverify' => $this->require_ssl,
		                                                      'body'      => $api_params
		) );

		// make sure the response came back okay
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {

			if ( is_wp_error( $response ) ) {
				$message = $response->get_error_message();
			} else {
				$message = __( 'En feil oppstod, vennligst prøv igjen.', 'woo-gateway-vipps' );
			}

		} else {

			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			if ( isset( $license_data->success ) && false === $license_data->success ) {

				switch ( $license_data->error ) {

					case 'expired' :

						$message = sprintf(
								__( 'Din lisensnøkkel utløp %s.', 'woo-gateway-vipps' ),
								date_i18n( get_option( 'date_format' ), strtotime( $license_data->expires, current_time( 'timestamp' ) ) )
						);
						break;

					case 'revoked' :

						$message = __( 'Din lisensnøkkel er deaktivert.', 'woo-gateway-vipps' );
						break;

					case 'missing' :

						$message = __( 'Feil lisensnøkkel.', 'woo-gateway-vipps' );
						break;

					case 'invalid' :
					case 'site_inactive' :

						$message = __( 'Lisensnøkkelen er ikke aktiv for dette domenet', 'woo-gateway-vipps' );
						break;

					case 'item_name_mismatch' :

						$message = sprintf( __( 'Det ser ut til at dette er feil lisensnøkkel for %s.', 'woo-gateway-vipps' ), $this->product_name );
						break;

					case 'no_activations_left':

						$message = __( 'Din lisensnøkkel er benyttet maksimalt antall ganger.', 'woo-gateway-vipps' );
						break;

					default :

						$message = __( 'En feil er oppstått, vennligst prøv igjen.', 'woo-gateway-vipps' );
						break;
				}

			}

		}

		update_option( 'bp_vipps_purchase_code', $license );
		// Check if anything passed on a message constituting a failure
		if ( ! empty( $message ) ) {
			return [
					'error'   => true,
					'success' => false,
					'message' => $message
			];
		} else {
			$success = false;
			if ( isset( $license_data->license ) && $license_data->license == 'valid' ) {
				$success = true;
			}
			update_option( 'vipps_woocommerce_license_status', $success ? 'valid' : 'invalid' );

			return [
					'error'   => false,
					'success' => $success,
					'message' => isset( $license_data->license ) ? $license_data->license : 'invalid' // 'valid' / 'invalid'
			];
		}
	}

	/**
	 * Deactivate License
	 * @since 1.0.0
	 */
	function deactivate_license( $license = null ) {

		if ( $license === null ) {
			$license = get_option( 'bp_vipps_purchase_code' );
		}

		// retrieve the license from the database
		$license = trim( $license );

		// data to send in our API request
		$api_params = array(
				'edd_action' => 'deactivate_license',
				'license'    => $license,
				'item_name'  => urlencode( $this->product_name ), // the name of our product in EDD
				'url'        => home_url()
		);

		// Call the custom API
		$response = wp_remote_post( $this->master_url, array( 'timeout'   => 15,
		                                                      'sslverify' => $this->require_ssl,
		                                                      'body'      => $api_params
		) );

		// make sure the response came back okay
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {

			if ( is_wp_error( $response ) ) {
				$message = $response->get_error_message();
			} else {
				$message = __( 'En feil er oppstått, vennligst prøv igjen.', 'woo-gateway-vipps' );
			}

			return [
					'error'   => true,
					'success' => false,
					'message' => $message
			];

		} else {

			// decode the license data
			$license_data = json_decode( wp_remote_retrieve_body( $response ) );
			$success      = false;
			if ( isset( $license_data->license ) && $license_data->license == 'deactivated' ) {
				delete_option( 'vipps_woocommerce_license_status' );
				delete_option( 'bp_vipps_purchase_code' );
				$success = true;
			}

			return [
					'error'   => false,
					'success' => $success,
					'message' => isset( $license_data->license ) ? $license_data->license : 'failed' // 'deactivated' / 'failed'
			];

		}
	}

	/**
	 * Get License
	 * @since 1.0.0
	 */
	function get_license() {
		return get_option( 'bp_vipps_purchase_code' );
	}

	/**
	 * Get License Status
	 * @since 1.0.0
	 */
	function get_license_status() {
		return get_option( 'vipps_woocommerce_license_status' );
	}

	/**
	 * Validate License
	 * @since 1.0.0
	 */
	function check_license( $license = '' ) {

		$license = trim( $license );

		$api_params = array(
				'edd_action' => 'check_license',
				'license'    => $license,
				'item_name'  => urlencode( $this->product_name ),
				'url'        => home_url()
		);

		// Call the custom API.
		$response = wp_remote_post( $this->master_url, array( 'timeout'   => 15,
		                                                      'sslverify' => $this->require_ssl,
		                                                      'body'      => $api_params
		) );

		if ( is_wp_error( $response ) ) {
			return [
					'error'   => true,
					'success' => false,
					'message' => $response
			];
		} else {
			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			return [
					'error'   => false,
					'success' => true,
					'message' => isset( $license_data->license ) ? $license_data->license : 'invalid' // 'valid' / 'invalid'
			];
		}

	}

	/**
	 * Activation Notice
	 * @since 1.0.0
	 */
	function vipps_admin_notices() {
		if ( isset( $_GET['sl_activation'] ) && ! empty( $_GET['message'] ) ) {

			switch ( $_GET['sl_activation'] ) {

				case 'false':
					$message = urldecode( $_GET['message'] );
					?>
          <div class="error">
            <p><?php echo $message; ?></p>
          </div>
					<?php
					break;

				case 'true':
					$message = urldecode( $_GET['message'] );
					?>
          <div class="success">
            <p><?php echo $message; ?></p>
          </div>
					<?php
					break;
				default:
					break;

			}
		}
	}

}