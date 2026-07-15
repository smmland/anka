<?php
/**
 * Plugin Name: ورود و ثبت‌نام پیامکی آرانکیا
 * Plugin URI: https://arankia.ir
 * Description: افزونه اختصاصی ورود و ثبت‌نام کاربران با کد تایید پیامکی از طریق ملی‌پیامک، همراه با ورود با رمز عبور، ریکپچای گوگل و طراحی اختصاصی سایت آرانکیا.
 * Version: 1.0.0
 * Author: Arankia
 * Author URI: https://arankia.ir
 * Text Domain: arankia-sms-login
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ASL_VERSION', '1.0.0' );
define( 'ASL_FILE', __FILE__ );
define( 'ASL_DIR', plugin_dir_path( __FILE__ ) );
define( 'ASL_URL', plugin_dir_url( __FILE__ ) );
define( 'ASL_BASENAME', plugin_basename( __FILE__ ) );

require_once ASL_DIR . 'includes/class-asl-settings.php';
require_once ASL_DIR . 'includes/class-asl-melipayamak.php';
require_once ASL_DIR . 'includes/class-asl-recaptcha.php';
require_once ASL_DIR . 'includes/class-asl-auth.php';
require_once ASL_DIR . 'includes/class-asl-ajax.php';
require_once ASL_DIR . 'includes/class-asl-shortcode.php';
require_once ASL_DIR . 'includes/class-asl-admin.php';

final class Arankia_SMS_Login {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		register_activation_hook( ASL_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( ASL_FILE, array( $this, 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_filter( 'plugin_action_links_' . ASL_BASENAME, array( $this, 'settings_link' ) );
	}

	public function activate() {
		ASL_Settings::install_defaults();
		$this->maybe_create_login_page();
	}

	public function deactivate() {
		// Intentionally left blank: settings and pages are preserved on deactivation.
	}

	public function init() {
		load_plugin_textdomain( 'arankia-sms-login', false, dirname( ASL_BASENAME ) . '/languages' );

		new ASL_Admin();
		new ASL_Ajax();
		new ASL_Shortcode();
	}

	public function settings_link( $links ) {
		$url  = admin_url( 'admin.php?page=arankia-sms-login' );
		$link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'تنظیمات', 'arankia-sms-login' ) . '</a>';
		array_unshift( $links, $link );
		return $links;
	}

	private function maybe_create_login_page() {
		$general = ASL_Settings::get_group( 'asl_general' );

		if ( ! empty( $general['login_page_id'] ) && get_post( $general['login_page_id'] ) ) {
			return;
		}

		$page_id = wp_insert_post( array(
			'post_title'   => __( 'ورود و ثبت‌نام', 'arankia-sms-login' ),
			'post_content' => '[arankia_sms_login]',
			'post_status'  => 'publish',
			'post_type'    => 'page',
		) );

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			$general['login_page_id'] = $page_id;
			update_option( 'asl_general', $general );
		}
	}
}

Arankia_SMS_Login::instance();
