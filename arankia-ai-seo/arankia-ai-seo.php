<?php
/**
 * Plugin Name: سئوی هوشمند محصولات آرانکیا
 * Plugin URI: https://arankia.ir
 * Description: تولید و مدیریت خودکار سئوی محصولات ووکامرس (عنوان، توضیحات، متا سئو یواست و کلیدواژه‌ها) با اتصال به ChatGPT، Claude و سرویس‌های هوش مصنوعی ایرانی، همراه با صفحه بازبینی و تایید پیش از اعمال تغییرات.
 * Version: 1.0.0
 * Author: Arankia
 * Author URI: https://arankia.ir
 * Text Domain: arankia-ai-seo
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AAS_VERSION', '1.0.0' );
define( 'AAS_FILE', __FILE__ );
define( 'AAS_DIR', plugin_dir_path( __FILE__ ) );
define( 'AAS_URL', plugin_dir_url( __FILE__ ) );
define( 'AAS_BASENAME', plugin_basename( __FILE__ ) );

require_once AAS_DIR . 'includes/class-aas-install.php';
require_once AAS_DIR . 'includes/class-aas-settings.php';
require_once AAS_DIR . 'includes/class-aas-fields.php';
require_once AAS_DIR . 'includes/class-aas-suggestion.php';
require_once AAS_DIR . 'includes/class-aas-seo-checklist.php';
require_once AAS_DIR . 'includes/providers/interface-aas-ai-provider.php';
require_once AAS_DIR . 'includes/providers/class-aas-provider-helper.php';
require_once AAS_DIR . 'includes/providers/class-aas-provider-openai.php';
require_once AAS_DIR . 'includes/providers/class-aas-provider-anthropic.php';
require_once AAS_DIR . 'includes/providers/class-aas-provider-generic.php';
require_once AAS_DIR . 'includes/providers/class-aas-provider-factory.php';
require_once AAS_DIR . 'includes/class-aas-prompt-builder.php';
require_once AAS_DIR . 'includes/class-aas-generator.php';
require_once AAS_DIR . 'includes/class-aas-queue.php';
require_once AAS_DIR . 'includes/class-aas-product-hooks.php';
require_once AAS_DIR . 'includes/class-aas-ajax.php';
require_once AAS_DIR . 'includes/class-aas-admin.php';

final class Arankia_AI_Seo {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		register_activation_hook( AAS_FILE, array( 'AAS_Install', 'activate' ) );
		register_deactivation_hook( AAS_FILE, array( $this, 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_filter( 'plugin_action_links_' . AAS_BASENAME, array( $this, 'settings_link' ) );
	}

	public function deactivate() {
		AAS_Queue::clear_scheduled_events();
	}

	public function init() {
		load_plugin_textdomain( 'arankia-ai-seo', false, dirname( AAS_BASENAME ) . '/languages' );

		AAS_Install::maybe_upgrade();

		new AAS_Queue();
		new AAS_Product_Hooks();
		new AAS_Ajax();
		new AAS_Admin();

		add_action( 'admin_notices', array( $this, 'render_requirements_notice' ) );
	}

	public function render_requirements_notice() {
		if ( class_exists( 'WooCommerce' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>' .
			esc_html__( 'افزونه «سئوی هوشمند محصولات آرانکیا» برای کار کردن نیاز به فعال بودن ووکامرس دارد.', 'arankia-ai-seo' ) .
			'</p></div>';
	}

	public function settings_link( $links ) {
		$url  = admin_url( 'admin.php?page=arankia-ai-seo-settings' );
		$link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'تنظیمات', 'arankia-ai-seo' ) . '</a>';
		array_unshift( $links, $link );
		return $links;
	}
}

Arankia_AI_Seo::instance();
