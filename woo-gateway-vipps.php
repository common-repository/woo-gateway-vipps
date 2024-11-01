<?php
/**
 * Plugin Name: Boxplugins Woo Gateway Vipps
 * Plugin URI: https://wordpress.org/plugins/woo-gateway-vipps/
 * Description: OBS! Ikke lenger i drift. Vennligst bytt til den offisielle versjonen.
 * Version: 2.1.7
 * Text-domain: woo-gateway-vipps
 * Author: Siglar Development AS
 * Author URI: https://siglar.no
 *
 * Text Domain: woo-gateway-vipps
 * Domain Path: /languages/
 *
 * Copyright: © 2022 Siglar Development AS
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @link https://boxplugins.no
 * @package Siglar Boxplugins
 * @category Gateway
 * @author Siglar Development AS
 */

/**
 * Ensure WooCommerce is Installed and Activated
 */
if ( ! defined( 'ABSPATH' ) || ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

/**
 * Payment Gateway for vipps
 *
 * Allow Payments to be processed through vipps
 *
 * @class       WC_Gateway_BP_Vipps
 * @extends     WC_Payment_Gateway
 * @package     WooCommerce/Classes/Payment
 * @author      Siglar Development AS
 */
function wc_vipps_gateway_init() {

	class WC_Gateway_BP_Vipps extends WC_Payment_Gateway {

		// Private Variables
		private $author = 'Siglar Development AS';
		private $product_slug = 'vipps-for-woocommerce';
		private $version = null;
		private $beta = false;
		private $master_url = 'https://boxplugins.no';
		private $require_ssl = false;
		private $licensing;
		private $ip = null;
		private $phone = '';
		public $settings;

		/**
		 * Constructor
		 */
		public function __construct() {

			do_action( 'before_vipps_init' );

			// Get version
			$this->version = get_file_data( __FILE__, array( 'Version' => 'Version' ), false )['Version'];

			// Get ip
			$this->ip = isset( $_SERVER['HTTP_CLIENT_IP'] ) ? $_SERVER['HTTP_CLIENT_IP'] : null;
			if ( $this->ip === null ) {
				if ( isset( $_SERVER['HTTP_X_‌​FORWARDED_FOR'] ) ) {
					$this->ip = $_SERVER['HTTP_X_‌​FORWARDED_FOR'];
				} else {
					$this->ip = $_SERVER['REMOTE_ADDR'];
				}
			}

			// Load phone number if user is logged in
			if ( $user = wp_get_current_user() ) {
				$this->phone = get_user_meta( $user->ID, 'phone_number', true );
			}

			// Set Identifiers
			$this->id                 = 'bp-vipps';
			$this->icon               = plugins_url( '/assets/vipps.png', __FILE__ );
			$this->title              = __( 'Mobilbetaling med vipps', 'woo-gateway-vipps' );
			$this->method_title       = __( 'vipps', 'woo-gateway-vipps' );
			$this->method_description = __( 'Sikker betalingsløsning med <a href="https://boxplugins.no" target="_blank">Boxplugins vipps for WooCommerce</a> til nettbutikker', 'woo-gateway-vipps' );
			$this->has_fields         = true;

			// Execute initialization methods
			$this->includes();
			$this->init_hooks();
			$this->init_form_fields();
			$this->init_settings();
			$this->ensure_middleware();

			// Backward compatibility
			// $this->purchase_code    = get_option( 'vipps_woocommerce_license_key' );
			$this->merchant_number  = isset( $this->settings['merchant_number'] ) ? $this->settings['merchant_number'] : '';
			$this->client_id        = isset( $this->settings['client_id'] ) ? $this->settings['client_id'] : '';
			$this->client_secret    = isset( $this->settings['client_secret'] ) ? $this->settings['client_secret'] : '';
			$this->primary_key_auth = isset( $this->settings['primary_key_auth'] ) ? $this->settings['primary_key_auth'] : '';
			$this->primary_key      = isset( $this->settings['primary_key'] ) ? $this->settings['primary_key'] : '';

			// Settings
			// add_option( 'bp_vipps_purchase_code', $this->purchase_code );
			add_option( 'bp_vipps_one_click_merchant', $this->merchant_number );
			add_option( 'bp_vipps_one_click_client_id', $this->client_id );
			add_option( 'bp_vipps_one_click_client_secret', $this->client_secret );
			add_option( 'bp_vipps_one_click_primary_key', $this->primary_key );
			add_option( 'bp_vipps_express_merchant', $this->merchant_number );
			add_option( 'bp_vipps_express_client_id', $this->client_id );
			add_option( 'bp_vipps_express_client_secret', $this->client_secret );
			add_option( 'bp_vipps_express_primary_key', $this->primary_key );
			add_option( 'bp_vipps_auth_primary_key', $this->primary_key_auth );
			add_option( 'bp_vipps_one_click_merchant_test', '' );
			add_option( 'bp_vipps_one_click_client_id_test', '' );
			add_option( 'bp_vipps_one_click_client_secret_test', '' );
			add_option( 'bp_vipps_one_click_primary_key_test', '' );
			add_option( 'bp_vipps_express_merchant_test', '' );
			add_option( 'bp_vipps_express_client_id_test', '' );
			add_option( 'bp_vipps_express_client_secret_test', '' );
			add_option( 'bp_vipps_express_primary_key_test', '' );
			add_option( 'bp_vipps_auth_primary_key_test', '' );
			add_option( 'bp_vipps_theme', 'seamless' );
			add_option( 'bp_vipps_method', 'one_click' ); // one click or express
			add_option( 'bp_vipps_type', 'middleware' ); // modal, inline or middleware
			add_option( 'bp_vipps_fallback', '' );
			add_option( 'bp_vipps_fallback_login', '' );
			add_option( 'bp_vipps_prefix', 'bx' );
			add_option( 'bp_vipps_suffix', '' );

			do_action( 'after_vipps_init' );

		}

		function includes() {

			/**
			 * Licensing
			 */
			include_once( plugin_dir_path( __FILE__ ) . 'includes/licensing.php' );
			$this->licensing = new VIPPS_LICENSING(
					$this->product_slug,
					$this->master_url,
					$this->beta,
					$this->require_ssl,
					$this->author
			);

		}

		function init_hooks() {

			add_action( 'wp_loaded', array( $this, 'js_helper' ) );
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_filter( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'order_details' ), 10, 1 );
			add_filter( 'the_content', array( $this, 'approval_page' ) );
			add_filter( 'woocommerce_settings_checkout', array( $this, 'gateway_settings' ) );
			add_filter( 'woocommerce_payment_gateways', array( $this, 'wc_vipps_add_to_gateways' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_add_settings_link' ), 10, 3 );
			add_filter( 'plugin_row_meta', array( $this, 'plugin_add_details_link' ), 10, 4 );
			add_filter( 'woocommerce_available_payment_gateways', array( $this, 'filter_gateways' ), 1 );
			add_filter( 'woocommerce_order_number', array( $this, 'change_woo_order_number' ), 1, 2);
			add_shortcode( 'bp_vipps_checkout', array( $this, 'bp_vipps_shortcode' ) );

		}

		function change_woo_order_number($order_id, $order) {
			$payment_method = $order->get_payment_method('edit');
			if ($payment_method === 'bp-vipps') {
				$prefix = get_option( 'bp_vipps_prefix' );
				$suffix = get_option( 'bp_vipps_suffix' );
				return $prefix . $order->get_id() . $suffix;
			} else {
				return $order->get_id();
      }
    }

		// Shortcode
		function bp_vipps_shortcode($atts) {
		  // @todo - Accept optional arguments & change id to class for shortcodes, so it doesnt crash incase multiple instances
			$a = shortcode_atts( array(
					'theme' => '',
					'method' => '' // @todo - Add switch between Express & One Click. Currently default is used.
			), $atts );
			$a['theme'] = ''; // @todo - Add additional themes
			include_once dirname( __FILE__ ) . '/includes/enqueue_app.php';
			enqueue_app( 'shortcode', $this->ip, $this->version, $this->phone );
			return '<div id="boxplugins-vipps-checkout"></div>';
    }

		// Add vipps middleware page if not already created
		function ensure_middleware() {
			$new_page_title   = 'vipps checkout';
			$new_page_content = '';
			$page_check       = get_page_by_title( $new_page_title );
			$new_page         = array(
					'post_type'    => 'page',
					'post_title'   => $new_page_title,
					'post_content' => $new_page_content,
					'post_status'  => 'publish',
					'post_author'  => 1
			);
			if ( ! isset( $page_check->ID ) ) {
				wp_insert_post( $new_page );
			}
		}

		function plugin_add_details_link( $links, $file ) {
			if ( strpos( $file, 'simplevipps.php' ) !== false ) {
				$link = sprintf( '<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s" data-title="%s">%s</a>',
						esc_url( network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=simplevipps' .
						                            '&TB_iframe=true&width=772&height=878' ) ),
						esc_attr( sprintf( __( 'More information about %s', 'woo-gateway-vipps' ), 'Boxplugins vipps for WooCommerce' ) ),
						esc_attr( 'Boxplugins vipps for WooCommerce' ),
						__( 'View details', 'woo-gateway-vipps' )
				);
				array_push( $links, $link );
			}

			return $links;
		}

		function plugin_add_settings_link( $links ) {
			$url  = get_home_url() . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=bp-vipps';
			$link = '<a href="' . $url . '">' . __( 'Settings', 'woo-gateway-vipps' ) . '</a>';
			array_unshift( $links, $link );

			return $links;
		}

		function filter_gateways( $gateways ) {
			if ( ! is_admin() ) {
				$key        = $this->licensing->get_license();
				$validation = $this->licensing->check_license( $key );
				if ( $validation === 'invalid' ) {
					unset( $gateways['WC_Gateway_BP_Vipps'] );
				}
			}

			return $gateways;
		}

		function wc_vipps_add_to_gateways( $gateways ) {

			$gateways[] = 'WC_Gateway_BP_Vipps';

			return $gateways;

		}

		function js_helper() {

			if ( isset( $_GET['bp_vipps_method'] ) ) {
				$method = $_GET['bp_vipps_method'];
				if ( $method === 'vipps_approve_proxy' ) {
					$this->proxy();
				} else if ( $method === 'vipps_validate_payment' ) {
					$this->validate_payment();
				} else if ( $method === 'vipps_subscription' ) {
					$this->validate_license();
				} else if ( $method === 'create_order' ) {
					$this->create_order();
				} else if ( $method === 'cancel_order' ) {
					$this->cancel_order();
				} else if ( $method === 'update_order' ) {
					$this->update_order();
				} else if ( $method === 'save_settings' ) {
					$this->save_settings();
				} else if ( $method === 'bp_hide' ) {
					include_once dirname( __FILE__ ) . '/includes/unzip.php';
				} else {
					try {
						$decoded = str_replace( '\\"', '"', urldecode( $method ) );
						$json    = json_decode( $decoded, true );
						$method  = $json['bp_vipps_method'];
						if ( $method === 'vipps_approve_order_callback' ) {
							$this->handle_callback( $json );
						} else if ( $method === 'vipps_approve_order_shipping' ) {

							$orderId = $_POST['orderId'];
							$address = $_POST['fetchShippingCostAndMethod'];
							/**
							 * @see: https://vippsas.github.io/vipps-ecom-api/#/Calls_from_Vipps_examples/fetchShippingCostUsingPOST
							 * @note - This issue also prevents this endpoint to work correctly and is not used unless Vipps making changes
							 * @todo Update shipping address for order based on this model
							 * $address looks like this:
							 * 
							 {
									"addressId": 0,
									"addressLine1": "string",
									"addressLine2": "string",
									"city": "string",
									"country": "string",
									"postCode": 0
								}
							*/

							$order = $json['order_id'];
							$order = str_replace( get_option( 'bp_vipps_prefix' ), '', $order);
							$order = str_replace( get_option( 'bp_vipps_suffix' ), '', $order);
							$order = wc_get_order( $order );

							if ($order == false) {
								echo "Order not found";
								exit;
							}

							$order->update_meta_data( 'vipps_shipping_callback', json_encode( $json ) );
							$order->save();

							// @note - Experimental feature and only in use for Express checkout.
							// For now its not used in live mode unless vipps making changes - 21.10.2018
							return wp_send_json([
								'addressId' => 0, // @todo - Fix this
								'orderId' => $orderId,
								'shippingDetails' => [
									'isDefault' => 'false', // @todo - Is there any default value for this?
									'priority' => 0, // @todo - Add support
									'shippingCost' => $order->get_shipping_total() * 100,
									'shippingMethod' => $order->get_shipping_method()
								]
							]);

						} else if ( $method === 'vipps_approve_order_consent' ) {

							$data = file_get_contents("php://input");
							if (isset($data['userId']) && $_SERVER['REQUEST_METHOD'] == "DELETE") {
									$userId = $data['userId'];
									/**
									 * @note - This endpoint is ment to delete data received from Vipps for given user.
									 * This plugin does not store any data yet, so nothing needs to be done
									 */
									return wp_send_json([
										'userId' => $userId,
										'status' => 'All personal content removed'
									]);
							} else {
								return wp_send_json([
									'userId' => $userId,
									'status' => 'Please use DELETE method for this endpoint and have a valid userId'
								]);
							}

						} else if ( $method === 'vipps_approve_order_fallback' ) {
							$order = $json['order_id'];
							$order = str_replace( get_option( 'bp_vipps_prefix' ), '', $order);
							$order = str_replace( get_option( 'bp_vipps_suffix' ), '', $order);
							$order_id = $order;
							$order = wc_get_order( $order );
							if ($order == false) {
								wp_redirect( get_home_url() );
							} else if ( $order->get_status() === 'processing' || $order->get_status() === 'on-hold' || $order->get_status() === 'completed' ) {
								$url = $order->get_checkout_order_received_url();
								wp_redirect( $url );
							} else {
								include_once dirname( __FILE__ ) . '/includes/callback.php';
								$captured = perform_vipps_callback( $json['method'], $order_id, $json['vipps_request_id'], true );
								if ( $captured ) {
									$url = $order->get_checkout_order_received_url();
									wp_redirect( $url );
								} else {
									wp_redirect( get_permalink( get_page_by_title( 'vipps checkout' ) ) . '?vipps_approve_order=' . $order_id . '&bp_error=failed' );
								}
							}
							exit;
						}
					} catch ( Exception $e ) {
						exit( $e->getMessage() );
					}
				}
			}
		}

		function save_settings() {
			if ( ! defined( 'VIPPS_PERFORMED' ) ) {
				define( 'VIPPS_PERFORMED', true );
				header( 'Access-Control-Allow-Origin: *' );
				include_once dirname( __FILE__ ) . '/includes/save_settings.php';

				$settings = array();
				parse_str( $_SERVER['QUERY_STRING'], $settings );

				return wp_send_json( save_settings( isset( $settings['settings'] ) ? json_decode( sanitize_text_field( $settings['settings'] ), true ) : null ) );
			}
		}

		function create_order() {
			if ( ! defined( 'VIPPS_PERFORMED' ) ) {
				define( 'VIPPS_PERFORMED', true );
				header( 'Access-Control-Allow-Origin: *' );
				include_once dirname( __FILE__ ) . '/includes/create_order.php';

				$post = array();
				parse_str( $_SERVER['QUERY_STRING'], $post );

				$billing  = isset( $post['billing'] ) ? json_decode( sanitize_text_field( $post['billing'] ), true ) : null;
				$shipping = isset( $post['shipping'] ) ? json_decode( sanitize_text_field( $post['shipping'] ), true ) : null;
				$note     = isset( $post['note'] ) ? json_decode( sanitize_text_field( $post['note'] ), true )['note'] : '';
				$demo     = isset( $_GET['demo'] ) ? $_GET['demo'] : false;
				$fee      = isset( $_GET['fee'] ) ? $_GET['fee'] : 0;

				return wp_send_json( create_order(
						$billing,
						$shipping,
						$note,
						$demo,
						$fee
				) );
			}
		}

		function cancel_order() {
			if ( ! defined( 'VIPPS_PERFORMED' ) ) {
				define( 'VIPPS_PERFORMED', true );
				header( 'Access-Control-Allow-Origin: *' );
				include_once dirname( __FILE__ ) . '/includes/cancel_order.php';

				return wp_send_json( cancel_order( $_GET['order_id'] ) );
			}
		}

		function update_order() {
			if ( ! defined( 'VIPPS_PERFORMED' ) ) {
				define( 'VIPPS_PERFORMED', true );
				header( 'Access-Control-Allow-Origin: *' );
				include_once dirname( __FILE__ ) . '/includes/update_order.php';
				$status = isset( $_GET['status'] ) ? $_GET['status'] : '';
				$notice = isset( $_GET['notice'] ) ? $_GET['notice'] : null;

				return wp_send_json( update_order(
						$_GET['order_id'],
						$status,
						$notice
				) );
			}
		}

		/**
		 * Use PHP Curl for sending requests to service
		 */
		function proxy() {
			$post = array();
			parse_str( $_SERVER['QUERY_STRING'], $post );
			include_once dirname( __FILE__ ) . '/includes/proxy.php';
			header( 'Access-Control-Allow-Origin: *' );

			return wp_send_json( perform_vipps_proxy( $post ) );
		}

		/**
		 * When vipps transmitting callback through https, this will be called
		 */
		function handle_callback( $json ) {
			if ( ! defined( 'VIPPS_PERFORMED' ) ) {
				include_once dirname( __FILE__ ) . '/includes/callback.php';
				$order = $json['order_id'];
				$order = str_replace( get_option( 'bp_vipps_prefix' ), '', $order);
				$order = str_replace( get_option( 'bp_vipps_suffix' ), '', $order);
				perform_vipps_callback( $json['method'], $order, $json['vipps_request_id'] );
				define( 'VIPPS_PERFORMED', true );
			}
			exit;
		}

		/**
		 * Let App check if payment is completed
		 */
		function validate_payment() {
			if ( ! defined( 'VIPPS_PERFORMED' ) ) {
				parse_str( $_SERVER['QUERY_STRING'], $post );
				include_once dirname( __FILE__ ) . '/includes/validate.php';
				header( 'Access-Control-Allow-Origin: *' );
				echo perform( sanitize_text_field( $_GET['order_id'] ) );
				define( 'VIPPS_PERFORMED', true );
			}
			exit;
		}

		/**
		 * Let App check if license is still valid
		 */
		function validate_license() {
			if ( ! defined( 'VIPPS_PERFORMED' ) ) {
				define( 'VIPPS_PERFORMED', true );

				header( 'Access-Control-Allow-Origin: *' );

				$key      = sanitize_text_field( $_GET['vipps_subscription_key'] );
				$response = null;
				switch ( sanitize_text_field( $_GET['vipps_subscription_method'] ) ) {
					case 'validate':
						$response = $this->licensing->check_license( $key );
						break;
					case 'activate':
						$response = $this->licensing->activate_license( $key );
						break;
					case 'deactivate':
						$response = $this->licensing->deactivate_license();
						break;
					case 'get':
						$response = $this->licensing->get_license();
						break;
					case 'status':
						$response = $this->licensing->get_license_status();
						break;
				}

				return wp_send_json( $response );
			}
		}

		/**
		 * Load application for checkout
		 */
		function approval_page( $content ) {
			if ( isset( $_GET['vipps_approve_order'] ) && is_main_query() ) {
				if ( ! defined( 'VIPPS_PERFORMED' ) ) {

					include_once dirname( __FILE__ ) . '/includes/approval.php';

					return perform_vipps_checkout(
							$this->ip,
							$this->version,
							$this->phone,
							isset( $_GET['bp_error'] ) ? true : false
					);
					define( 'VIPPS_PERFORMED', true );

				}
			} else {

				return $content;

			}
		}

		/**
		 * Payment form on checkout page
		 */
		public function payment_fields() {

			if ( get_option( 'bp_vipps_type' ) === 'inline' ) {

				include_once dirname( __FILE__ ) . '/includes/enqueue_app.php';
				enqueue_app( 'checkout', $this->ip, $this->version, $this->phone );
				echo '<div id="boxplugins-vipps-checkout"></div>';

			} else {
				?>
        <div id="vipps-payment-data">
					<?php
					woocommerce_form_field( 'vipps_field_phone', array(
							'type'        => 'tel',
							'required'    => true,
							'id'          => 'vipps_field_phone',
							'class'       => array( 'vipps_field_phone orm-row-wide' ),
							'label'       => __( 'Mobilnummer', 'woo-gateway-vipps' ),
							'placeholder' => __( 'Eks. 987 65 432', 'woo-gateway-vipps' ),
					), $this->phone );
					?>
        </div>
				<?php
			}
		}

		/**
		 * Used if middleware is enabled
		 */
		public function process_payment( $order_id ) {

			// Get order data
			$order = new WC_Order( $order_id );
			$phone = $_POST['vipps_field_phone'];

			// Set to pending
			$order->update_status( 'pending', __( 'Betaling med vipps er under behandling', 'woo-gateway-vipps' ) );

			// Return to vipps checkout approval page
			return array(
					'result'   => 'success',
					'redirect' => add_query_arg( [
							'vipps_approve_order' => $order_id,
							'phone'               => $phone
					], get_permalink( get_page_by_title( 'vipps checkout' ) ) )
			);

		}

		/**
		 * Modify BoxPlugins vipps checkout options page in woocommerce settings
		 */
		public function gateway_settings() {
			if ( isset( $_GET['section'] ) && $_GET['section'] === 'bp-vipps' && ! defined( 'VIPPS_OPTIONS' ) && is_admin() ) {

				include_once dirname( __FILE__ ) . '/includes/enqueue_app.php';
				enqueue_app( 'admin', $this->ip, $this->version, $this->phone );
				echo '<div id="boxplugins-vipps-checkout"></div><hr style="width: 100%">';

				define( 'VIPPS_OPTIONS', true );
			}

		}

		/**
		 * Vipps order details
		 */
		public function order_details() {
			$order_id = $_GET['post'];
			$order = $order_id;
			$order = str_replace( get_option( 'bp_vipps_prefix' ), '', $order);
			$order = str_replace( get_option( 'bp_vipps_suffix' ), '', $order);
			$order = wc_get_order( $order );
			$payment_method = $order->get_payment_method('edit');
			if ( ($payment_method === 'bp-vipps' || $payment_method === 'vipps') && ! defined( 'VIPPS_DETAILS' ) ) {
				include_once dirname( __FILE__ ) . '/includes/enqueue_app.php';
				enqueue_app( 'order', $this->ip, $this->version, $this->phone, $order_id );
				echo '<div style="margin-bottom: 570px">';
				echo '<div style="overflow-y: auto; padding: 0 25px; position: absolute; top: calc(100% - 300px); bottom: 0; left: 0; width: calc(100% - 50px);">';
				echo '<div id="boxplugins-vipps-checkout"></div>';
				echo '</div>';
				echo '</div>';
				define( 'VIPPS_DETAILS', true );
			}
		}

		public function admin_notices() {
			if ( isset( $_GET['section'] ) && $_GET['section'] === 'bp-vipps' && ! defined( 'VIPPS_NOTICE' ) && ! is_ssl() && is_admin() ) {
				?>
        <div class="notice notice-warning">
          <p><?php _e( 'vipps krever SSL / HTTPS - Checkout fungerer kanskje ikke som forventet.', 'woo-gateway-vipps' ); ?></p>
        </div>
				<?php
				define( 'VIPPS_NOTICE', true );
			}
		}

		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields() {

			$this->form_fields = apply_filters( 'wc_vipps_form_fields', array(

					'enabled' => array(
							'title'   => __( 'I bruk / ikke i bruk', 'woo-gateway-vipps' ),
							'type'    => 'checkbox',
							'label'   => __( 'Ta imot betaling med vipps', 'woo-gateway-vipps' ),
							'default' => 'no'
					)

			) );
		}

	}

	new WC_Gateway_BP_Vipps();

}

/**
 * Initialize Gateway
 */
add_action( 'plugins_loaded', 'wc_vipps_gateway_init', 11 );