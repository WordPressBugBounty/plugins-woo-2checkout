<?php
/**
 * Payment Gateway - 2Checkout for WooCommerce
 *
 * @package    StorePress\PaymentGateway
 *
 * @wordpress-plugin
 * Plugin Name:          Payment Gateway - 2Checkout for WooCommerce
 * Plugin URI:           https://wordpress.org/plugins/woo-2checkout/
 * Description:          2Checkout Payment Gateway for WooCommerce.
 * Author:               Emran Ahmed
 * Version:              3.1.0
 * Requires PHP:         7.4
 * Requires at least:    6.4
 * Tested up to:         6.7
 * WC requires at least: 8.1
 * WC tested up to:      9.7
 * Text Domain:          woo-2checkout
 * Author URI:           https://getwooplugins.com/
 * License:              GPL v3 or later
 * License URI:          https://www.gnu.org/licenses/gpl-3.0.html
 * Requires Plugins:     woocommerce
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || die( 'Keep Silent' );

use StorePress\TwoCheckoutPaymentGateway\Plugin;
use StorePress\TwoCheckoutPaymentGateway\Plugin_Extended;

if ( ! defined( 'STOREPRESS_TWO_CHECKOUT_PLUGIN_FILE' ) ) {
	define( 'STOREPRESS_TWO_CHECKOUT_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'STOREPRESS_TWO_CHECKOUT_COMPATIBLE_EXTENDED_VERSION' ) ) {
	define( 'STOREPRESS_TWO_CHECKOUT_COMPATIBLE_EXTENDED_VERSION', '3.1.0' );
}

/**
 * Get compatible version of extended plugin.
 *
 * @return string
 */
function woo_2checkout_compatible_pro_version(): string {
	return constant( 'STOREPRESS_TWO_CHECKOUT_COMPATIBLE_EXTENDED_VERSION' );
}

/**
 * Get Pro Plugin File
 *
 * @return string
 */
function woo_2checkout_pro_plugin_file(): string {
	return defined( 'STOREPRESS_TWO_CHECKOUT_PRO_PLUGIN_FILE' ) ? constant( 'STOREPRESS_TWO_CHECKOUT_PRO_PLUGIN_FILE' ) : 'woo-2checkout-pro/woo-2checkout-pro.php';
}

/**
 * The main function that returns the Plugin class
 *
 * @return Plugin|Plugin_Extended
 * @since 1.0.0
 */
function woo_2checkout() {
	// Include the Plugin class.
	if ( ! class_exists( '\StorePress\TwoCheckoutPaymentGateway\Plugin' ) ) {
		require_once plugin_dir_path( __FILE__ ) . 'includes/Plugin.php';
	}

	if ( function_exists( 'woo_2checkout_pro' ) && function_exists( 'woo_2checkout_pro_is_compatible' ) && woo_2checkout_pro_is_compatible() ) {
		return woo_2checkout_pro();
	}

	return Plugin::instance();
}

/**
 * Init to hook.
 *
 * @return void
 */
function woo_2checkout_init() {
	woo_2checkout();
}

// Get the plugin running.
add_action( 'plugins_loaded', 'woo_2checkout_init' );
