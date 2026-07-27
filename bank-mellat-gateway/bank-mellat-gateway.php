<?php
/**
 * Plugin Name: درگاه پرداخت بانک ملت (به‌پرداخت) برای ووکامرس
 * Plugin URI: https://arankia.ir
 * Description: اتصال امن درگاه پرداخت بانک ملت (به‌پرداخت ملت) به ووکامرس از طریق وب‌سرویس رسمی بانک؛ با تنظیمات ترمینال آی‌دی، نام کاربری و رمز عبور در بخش پرداخت‌های ووکامرس.
 * Version: 1.1.0
 * Author: Arankia
 * Author URI: https://arankia.ir
 * Text Domain: bank-mellat-gateway
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 6.0
 * WC tested up to: 9.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BMG_VERSION', '1.1.0' );
define( 'BMG_FILE', __FILE__ );
define( 'BMG_DIR', plugin_dir_path( __FILE__ ) );
define( 'BMG_URL', plugin_dir_url( __FILE__ ) );
define( 'BMG_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Declare HPOS (High-Performance Order Storage) compatibility early.
 */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', BMG_FILE, true );
	}
} );

add_action( 'plugins_loaded', 'bmg_bootstrap', 11 );

function bmg_bootstrap() {
	load_plugin_textdomain( 'bank-mellat-gateway', false, dirname( BMG_BASENAME ) . '/languages' );

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'bmg_missing_woocommerce_notice' );
		return;
	}

	if ( ! extension_loaded( 'soap' ) ) {
		add_action( 'admin_notices', 'bmg_missing_soap_notice' );
		return;
	}

	require_once BMG_DIR . 'includes/class-bmg-api.php';
	require_once BMG_DIR . 'includes/class-wc-gateway-bank-mellat.php';

	add_filter( 'woocommerce_payment_gateways', 'bmg_add_gateway' );
}

function bmg_add_gateway( $gateways ) {
	$gateways[] = 'WC_Gateway_Bank_Mellat';
	return $gateways;
}

function bmg_missing_woocommerce_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>' . esc_html__( 'برای فعال‌سازی افزونه درگاه پرداخت بانک ملت، افزونه ووکامرس باید نصب و فعال باشد.', 'bank-mellat-gateway' ) . '</p></div>';
}

function bmg_missing_soap_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>' . esc_html__( 'افزونه درگاه پرداخت بانک ملت نیاز به فعال بودن اکستنشن PHP SOAP روی سرور دارد.', 'bank-mellat-gateway' ) . '</p></div>';
}

add_filter( 'plugin_action_links_' . BMG_BASENAME, function ( $links ) {
	$url  = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=bank_mellat' );
	$link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'تنظیمات', 'bank-mellat-gateway' ) . '</a>';
	array_unshift( $links, $link );
	return $links;
} );
