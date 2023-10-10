<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://esparksinc.com
 * @since             1.0.0
 * @package           Deema
 *
 * @wordpress-plugin
 * Plugin Name:       Deema Payment Gateway
 * Plugin URI:        https://esparksinc.com
 * Description:       Deema is a payment gateway created by E-Sparks.
 * Version:           1.0.0
 * Author:            Muhib Ullah
 * Author URI:        https://esparksinc.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       deema
 * Domain Path:       /languages
 */

// Exit if accessed directly.

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Required minimums and constants
 */
define( 'WC_DEEMA_VERSION', '1.0.0' ); // WRCS: DEFINED_VERSION.
define( 'WC_DEEMA_MIN_PHP_VER', '7.3.0' );
define( 'WC_DEEMA_MIN_WC_VER', '7.4' );
define( 'WC_DEEMA_FUTURE_MIN_WC_VER', '7.5' );
define( 'WC_DEEMA_MAIN_FILE', __FILE__ );
define( 'WC_DEEMA_ABSPATH', __DIR__ . '/' );
define( 'WC_DEEMA_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WC_DEEMA_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

// phpcs:disable WordPress.Files.FileName

/**
 * WooCommerce fallback notice.
 *
 * @since 4.1.2
 */
function woocommerce_deema_missing_wc_notice() {
	/* translators: 1. URL link. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Deema requires WooCommerce to be installed and active. You can download %s here.', 'woocommerce-gateway-deema' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

/**
 * WooCommerce not supported fallback notice.
 *
 * @since 4.4.0
 */
function woocommerce_deema_wc_not_supported() {
	/* translators: $1. Minimum WooCommerce version. $2. Current WooCommerce version. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Deema requires WooCommerce %1$s or greater to be installed and active. WooCommerce %2$s is no longer supported.', 'woocommerce-gateway-deema' ), esc_html( WC_DEEMA_MIN_WC_VER ), esc_html( WC_VERSION ) ) . '</strong></p></div>';
}

function woocommerce_gateway_deema() {

	class WC_Deema {

		/**
		 * @var Singleton The reference the *Singleton* instance of this class
		 */
		private static $instance;

		/**
		 * Returns the *Singleton* instance of this class.
		 *
		 * @return Singleton The *Singleton* instance.
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		public function __clone(){}

		public function __wakeup() {}

		public function __construct()
		{
			$this->init();
		}

		public function init()
		{
			require_once dirname( __FILE__ ) . '/includes/class-wc-gateway-deema.php';
			require_once dirname( __FILE__ ) . '/includes/webhook/class-wc-deema-webhook-handler.php';
			require_once dirname( __FILE__ ) . '/includes/crons/wc-deema-order-status-cron.php';
			// require_once dirname( __FILE__ ) . '/classes/class-wc-gateway-paynow-helper.php';
			// require_once dirname( __FILE__ ) . '/includes/constants.php';

			/**
			 * Custom currency and currency symbol
			 */
			// add_filter( 'woocommerce_currencies', 'add_zwl_currency' );

			// function add_zwl_currency( $currencies ) {
			// 	$currencies['ZWL'] = __( 'Zimbabwe', 'woocommerce' );
			// 	return $currencies;
			// }

			// add_filter('woocommerce_currency_symbol', 'add_zwl_currency_symbol', 10, 2);

			// function add_zwl_currency_symbol( $currency_symbol, $currency ) {
			// 	switch( $currency ) {
			// 		case 'ZWL': $currency_symbol = 'ZWL'; break;
			// 	}
			// 	return $currency_symbol;
			// }

			add_filter('woocommerce_payment_gateways', array ($this, 'woocommerce_deema_add_gateway' ) );
			// add_action( 'woocommerce_thankyou', array( $this, 'order_cancelled_redirect' ), 10 , 1);
		}

		/**
		 * Add the gateway to WooCommerce
		 *
		 * @since 1.0.0
		 */
		function woocommerce_deema_add_gateway( $methods ) {
			$methods[] = 'WC_Gateway_Deema';
			return $methods;
		} // End woocommerce_paynow_add_gateway()

		// function order_cancelled_redirect( $order_id ){
		// 	global $woocommerce;
		// 	$order = new WC_Order($order_id);
		// 	$meta = get_post_meta( $order_id, '_wc_paynow_payment_meta', true );

		// 	if ( !empty($meta['Status']) && strtolower( $meta['Status'] ) == ps_cancelled ) {
		// 		wc_add_notice( __( 'You cancelled your payment on Paynow.', 'woocommerce' ), 'error' );
		// 		// wp_redirect( $order->get_cancel_order_url() );
		// 		wp_redirect( $order->get_checkout_payment_url() );
		// 	}
		// }

	}

	WC_Deema::get_instance();
}

add_action( 'plugins_loaded', 'woocommerce_gateway_deema_init' );

if ( ! function_exists( 'create_deema_table' ) ) {
	function create_deema_table() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'deema_order_references';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id INT NOT NULL AUTO_INCREMENT,
			woocommerce_order_id BIGINT NOT NULL,
			woocommerce_order_number VARCHAR(255) NOT NULL,
			deema_reference_number VARCHAR(255) NOT NULL,
			deema_purchase_id BIGINT NOT NULL,
			PRIMARY KEY (id)
		) $charset_collate;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}
}
register_activation_hook( __FILE__, 'create_deema_table' );

function woocommerce_gateway_deema_init() {
	load_plugin_textdomain( 'woocommerce-gateway-deema', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'woocommerce_deema_missing_wc_notice' );
		return;
	}

	if ( version_compare( WC_VERSION, WC_DEEMA_MIN_WC_VER, '<' ) ) {
		add_action( 'admin_notices', 'woocommerce_deema_wc_not_supported' );
		return;
	}

	woocommerce_gateway_deema();
}

