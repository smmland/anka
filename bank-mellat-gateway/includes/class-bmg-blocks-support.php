<?php
/**
 * Registers the Bank Mellat gateway with the WooCommerce Cart & Checkout Blocks.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
	return;
}

final class BMG_Blocks_Support extends \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {

	protected $name = 'bank_mellat';

	/** @var WC_Gateway_Bank_Mellat|null */
	private $gateway;

	public function initialize() {
		$this->settings = get_option( 'woocommerce_bank_mellat_settings', array() );

		$gateways      = WC()->payment_gateways->payment_gateways();
		$this->gateway = isset( $gateways[ $this->name ] ) ? $gateways[ $this->name ] : null;
	}

	public function is_active() {
		return $this->gateway && $this->gateway->is_available();
	}

	public function get_payment_method_script_handles() {
		wp_register_script(
			'bmg-blocks-integration',
			BMG_URL . 'assets/js/blocks-integration.js',
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n' ),
			BMG_VERSION,
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'bmg-blocks-integration', 'bank-mellat-gateway', BMG_DIR . 'languages' );
		}

		return array( 'bmg-blocks-integration' );
	}

	public function get_payment_method_data() {
		if ( ! $this->gateway ) {
			return array();
		}

		return array(
			'title'       => $this->gateway->get_title(),
			'description' => $this->gateway->get_description(),
			'icon'        => $this->gateway->icon,
			'supports'    => array_keys( array_filter( array( 'products' => true, 'refunds' => in_array( 'refunds', (array) $this->gateway->supports, true ) ) ) ),
		);
	}
}
