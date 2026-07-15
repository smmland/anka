<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASL_Ajax {

	const NONCE_ACTION = 'asl_public_nonce';

	public function __construct() {
		add_action( 'wp_ajax_asl_send_otp', array( $this, 'send_otp' ) );
		add_action( 'wp_ajax_nopriv_asl_send_otp', array( $this, 'send_otp' ) );

		add_action( 'wp_ajax_asl_verify_otp', array( $this, 'verify_otp' ) );
		add_action( 'wp_ajax_nopriv_asl_verify_otp', array( $this, 'verify_otp' ) );

		add_action( 'wp_ajax_asl_password_login', array( $this, 'password_login' ) );
		add_action( 'wp_ajax_nopriv_asl_password_login', array( $this, 'password_login' ) );

		add_action( 'wp_ajax_asl_send_test_sms', array( $this, 'send_test_sms' ) );
	}

	private function check_nonce() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), self::NONCE_ACTION ) ) {
			wp_send_json_error( array( 'message' => ASL_Settings::message( 'generic_error' ) ), 403 );
		}
	}

	public function send_otp() {
		$this->check_nonce();

		if ( is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => ASL_Settings::message( 'generic_error' ) ) );
		}

		$mobile = ASL_Auth::normalize_mobile( isset( $_POST['mobile'] ) ? wp_unslash( $_POST['mobile'] ) : '' );
		$mode   = isset( $_POST['mode'] ) && 'register' === $_POST['mode'] ? 'register' : 'login';
		$name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

		$captcha = ASL_Recaptcha::verify(
			isset( $_POST['recaptcha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['recaptcha_token'] ) ) : '',
			'send_otp'
		);

		if ( is_wp_error( $captcha ) ) {
			wp_send_json_error( array( 'message' => $captcha->get_error_message() ) );
		}

		$result = ASL_Auth::send_otp( $mobile, $mode, $name );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	public function verify_otp() {
		$this->check_nonce();

		if ( is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => ASL_Settings::message( 'generic_error' ) ) );
		}

		$mobile = ASL_Auth::normalize_mobile( isset( $_POST['mobile'] ) ? wp_unslash( $_POST['mobile'] ) : '' );
		$code   = isset( $_POST['code'] ) ? preg_replace( '/\D/', '', wp_unslash( $_POST['code'] ) ) : '';

		if ( ! ASL_Auth::is_valid_mobile( $mobile ) ) {
			wp_send_json_error( array( 'message' => ASL_Settings::message( 'invalid_mobile' ) ) );
		}

		$result = ASL_Auth::verify_otp( $mobile, $code );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	public function password_login() {
		$this->check_nonce();

		if ( is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => ASL_Settings::message( 'generic_error' ) ) );
		}

		$mobile   = ASL_Auth::normalize_mobile( isset( $_POST['mobile'] ) ? wp_unslash( $_POST['mobile'] ) : '' );
		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';

		$captcha = ASL_Recaptcha::verify(
			isset( $_POST['recaptcha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['recaptcha_token'] ) ) : '',
			'password_login'
		);

		if ( is_wp_error( $captcha ) ) {
			wp_send_json_error( array( 'message' => $captcha->get_error_message() ) );
		}

		$result = ASL_Auth::password_login( $mobile, $password );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	public function send_test_sms() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'arankia-sms-login' ) ), 403 );
		}

		check_ajax_referer( 'asl_admin_nonce', 'nonce' );

		$mobile = ASL_Auth::normalize_mobile( isset( $_POST['mobile'] ) ? wp_unslash( $_POST['mobile'] ) : '' );

		if ( ! ASL_Auth::is_valid_mobile( $mobile ) ) {
			wp_send_json_error( array( 'message' => ASL_Settings::message( 'invalid_mobile' ) ) );
		}

		$result = ASL_Melipayamak::send_test( $mobile, __( 'پیامک تست از افزونه ورود پیامکی آرانکیا', 'arankia-sms-login' ) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'پیامک تست با موفقیت ارسال شد.', 'arankia-sms-login' ) ) );
	}
}
