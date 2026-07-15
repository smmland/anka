<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core OTP / password authentication logic: rate limiting, code storage (transients),
 * user lookup/creation and session establishment. AJAX handlers call into this class.
 */
class ASL_Auth {

	const OTP_PREFIX    = 'asl_otp_';
	const RESEND_PREFIX = 'asl_resend_';
	const RATE_PREFIX   = 'asl_rate_';

	public static function normalize_mobile( $mobile ) {
		$mobile = self::to_english_digits( (string) $mobile );
		$mobile = preg_replace( '/[^0-9+]/', '', $mobile );

		if ( 0 === strpos( $mobile, '0098' ) ) {
			$mobile = '0' . substr( $mobile, 4 );
		} elseif ( 0 === strpos( $mobile, '+98' ) ) {
			$mobile = '0' . substr( $mobile, 3 );
		} elseif ( 0 === strpos( $mobile, '98' ) && 12 === strlen( $mobile ) ) {
			$mobile = '0' . substr( $mobile, 2 );
		}

		return $mobile;
	}

	public static function is_valid_mobile( $mobile ) {
		return (bool) preg_match( '/^09\d{9}$/', $mobile );
	}

	private static function to_english_digits( $string ) {
		$persian = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		$arabic  = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$english = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		$string  = str_replace( $persian, $english, $string );
		return str_replace( $arabic, $english, $string );
	}

	/**
	 * @return true|WP_Error
	 */
	public static function check_rate_limit( $mobile ) {
		$settings = ASL_Settings::get_group( 'asl_melipayamak' );
		$resend_key = self::RESEND_PREFIX . md5( $mobile );

		if ( false !== get_transient( $resend_key ) ) {
			$stored    = (int) get_transient( $resend_key );
			$remaining = self::transient_ttl_remaining( $resend_key, $stored );
			$wait      = max( 1, min( $stored, $remaining ) );
			$message   = str_replace( '{sec}', $wait, ASL_Settings::message( 'resend_wait' ) );
			return new WP_Error( 'asl_resend_wait', $message );
		}

		$rate_key = self::RATE_PREFIX . md5( $mobile );
		$count    = (int) get_transient( $rate_key );

		if ( $count >= (int) $settings['max_requests_per_hour'] ) {
			return new WP_Error( 'asl_rate_limited', ASL_Settings::message( 'too_many_requests' ) );
		}

		return true;
	}

	private static function bump_rate_limit( $mobile ) {
		$rate_key = self::RATE_PREFIX . md5( $mobile );
		$count    = (int) get_transient( $rate_key );

		if ( 0 === $count ) {
			set_transient( $rate_key, 1, HOUR_IN_SECONDS );
		} else {
			set_transient( $rate_key, $count + 1, HOUR_IN_SECONDS );
		}
	}

	/**
	 * @return array|WP_Error {resend_wait:int}
	 */
	public static function send_otp( $mobile, $mode, $name = '' ) {
		$general  = ASL_Settings::get_group( 'asl_general' );
		$settings = ASL_Settings::get_group( 'asl_melipayamak' );

		if ( empty( $general['enable_panel'] ) ) {
			return new WP_Error( 'asl_disabled', ASL_Settings::message( 'panel_disabled' ) );
		}

		if ( 'register' === $mode && empty( $general['allow_register'] ) ) {
			return new WP_Error( 'asl_register_disabled', ASL_Settings::message( 'register_disabled' ) );
		}

		if ( ! self::is_valid_mobile( $mobile ) ) {
			return new WP_Error( 'asl_invalid_mobile', ASL_Settings::message( 'invalid_mobile' ) );
		}

		$rate_check = self::check_rate_limit( $mobile );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$existing_user = self::find_user_by_mobile( $mobile );

		if ( 'register' === $mode && $existing_user ) {
			return new WP_Error( 'asl_user_exists', ASL_Settings::message( 'user_exists' ) );
		}

		if ( 'login' === $mode && ! $existing_user && empty( $general['auto_register_on_login'] ) ) {
			return new WP_Error( 'asl_user_not_found', ASL_Settings::message( 'user_not_found' ) );
		}

		$code_length = max( 4, min( 8, (int) $settings['code_length'] ) );
		$code        = self::generate_code( $code_length );

		$sent = ASL_Melipayamak::send_otp( $mobile, $code );

		if ( is_wp_error( $sent ) ) {
			self::bump_rate_limit( $mobile );
			return new WP_Error( 'asl_send_failed', ASL_Settings::message( 'otp_send_failed' ) );
		}

		$expiry = max( 30, (int) $settings['code_expiry'] );
		set_transient(
			self::OTP_PREFIX . md5( $mobile ),
			array(
				'code'     => $code,
				'attempts' => 0,
				'mode'     => $mode,
				'name'     => sanitize_text_field( $name ),
				'created'  => time(),
			),
			$expiry
		);

		$resend_wait = max( 20, (int) $settings['resend_wait'] );
		set_transient( self::RESEND_PREFIX . md5( $mobile ), $resend_wait, $resend_wait );

		self::bump_rate_limit( $mobile );

		return array(
			'resend_wait' => $resend_wait,
			'expiry'      => $expiry,
			'message'     => ASL_Settings::message( 'otp_sent' ),
		);
	}

	private static function generate_code( $length ) {
		$min = (int) str_pad( '1', $length, '0' );
		$max = (int) str_pad( '', $length, '9' );
		return (string) wp_rand( $min, $max );
	}

	/**
	 * @return array|WP_Error {redirect:string}
	 */
	public static function verify_otp( $mobile, $code ) {
		$general = ASL_Settings::get_group( 'asl_general' );
		$settings = ASL_Settings::get_group( 'asl_melipayamak' );

		if ( empty( $general['enable_panel'] ) ) {
			return new WP_Error( 'asl_disabled', ASL_Settings::message( 'panel_disabled' ) );
		}

		$key  = self::OTP_PREFIX . md5( $mobile );
		$data = get_transient( $key );

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'asl_code_expired', ASL_Settings::message( 'code_expired' ) );
		}

		$max_attempts = max( 1, (int) $settings['max_attempts'] );

		if ( (int) $data['attempts'] >= $max_attempts ) {
			delete_transient( $key );
			return new WP_Error( 'asl_too_many_attempts', ASL_Settings::message( 'too_many_requests' ) );
		}

		if ( ! hash_equals( (string) $data['code'], (string) $code ) ) {
			$data['attempts'] = (int) $data['attempts'] + 1;
			$ttl = self::transient_ttl_remaining( $key, (int) $settings['code_expiry'] );
			set_transient( $key, $data, max( 1, $ttl ) );
			return new WP_Error( 'asl_invalid_code', ASL_Settings::message( 'invalid_code' ) );
		}

		delete_transient( $key );
		delete_transient( self::RESEND_PREFIX . md5( $mobile ) );

		$user = self::find_user_by_mobile( $mobile );

		if ( ! $user ) {
			if ( 'register' === $data['mode'] || ! empty( $general['auto_register_on_login'] ) ) {
				$user = self::create_user( $mobile, $data['name'] );
				if ( is_wp_error( $user ) ) {
					return $user;
				}
			} else {
				return new WP_Error( 'asl_user_not_found', ASL_Settings::message( 'user_not_found' ) );
			}
		}

		self::log_in_user( $user );

		return array(
			'redirect' => self::get_redirect_url( $user ),
			'message'  => ( 'register' === $data['mode'] ) ? ASL_Settings::message( 'register_success' ) : ASL_Settings::message( 'login_success' ),
		);
	}

	private static function transient_ttl_remaining( $key, $fallback ) {
		$timeout = get_option( '_transient_timeout_' . $key );
		if ( $timeout ) {
			return (int) $timeout - time();
		}
		return $fallback;
	}

	/**
	 * @return array|WP_Error {redirect:string}
	 */
	public static function password_login( $mobile, $password ) {
		$general = ASL_Settings::get_group( 'asl_general' );

		if ( empty( $general['enable_panel'] ) ) {
			return new WP_Error( 'asl_disabled', ASL_Settings::message( 'panel_disabled' ) );
		}

		if ( empty( $general['allow_password_login'] ) ) {
			return new WP_Error( 'asl_password_disabled', ASL_Settings::message( 'password_login_disabled' ) );
		}

		if ( ! self::is_valid_mobile( $mobile ) ) {
			return new WP_Error( 'asl_invalid_mobile', ASL_Settings::message( 'invalid_mobile' ) );
		}

		$rate_key = 'asl_pwrate_' . md5( $mobile );
		$attempts = (int) get_transient( $rate_key );
		if ( $attempts >= 10 ) {
			return new WP_Error( 'asl_too_many_attempts', ASL_Settings::message( 'too_many_requests' ) );
		}

		$user = self::find_user_by_mobile( $mobile );

		if ( ! $user ) {
			return new WP_Error( 'asl_user_not_found', ASL_Settings::message( 'user_not_found' ) );
		}

		if ( ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
			set_transient( $rate_key, $attempts + 1, HOUR_IN_SECONDS );
			return new WP_Error( 'asl_wrong_password', ASL_Settings::message( 'wrong_password' ) );
		}

		delete_transient( $rate_key );
		self::log_in_user( $user );

		return array(
			'redirect' => self::get_redirect_url( $user ),
			'message'  => ASL_Settings::message( 'login_success' ),
		);
	}

	public static function find_user_by_mobile( $mobile ) {
		$general    = ASL_Settings::get_group( 'asl_general' );
		$meta_keys  = array_filter( array_merge(
			array( $general['mobile_meta_key'] ),
			array_map( 'trim', explode( ',', $general['fallback_meta_keys'] ) )
		) );

		foreach ( array_unique( $meta_keys ) as $meta_key ) {
			if ( '' === $meta_key ) {
				continue;
			}
			$users = get_users( array(
				'meta_key'   => $meta_key,
				'meta_value' => $mobile,
				'number'     => 1,
				'fields'     => 'all',
			) );
			if ( ! empty( $users ) ) {
				return $users[0];
			}
		}

		return false;
	}

	/**
	 * @return WP_User|WP_Error
	 */
	private static function create_user( $mobile, $name = '' ) {
		$general = ASL_Settings::get_group( 'asl_general' );

		$base_login = 'user_' . $mobile;
		$login      = $base_login;
		$suffix     = 1;
		while ( username_exists( $login ) ) {
			$login = $base_login . '_' . $suffix;
			$suffix++;
		}

		$host  = wp_parse_url( home_url(), PHP_URL_HOST );
		$email = $mobile . '@' . ( $host ? $host : 'arankia.ir' ) . '.invalid';

		$user_id = wp_insert_user( array(
			'user_login'   => $login,
			'user_pass'    => wp_generate_password( 20, true ),
			'user_email'   => $email,
			'display_name' => $name ? $name : $mobile,
			'first_name'   => $name,
			'role'         => apply_filters( 'asl_new_user_role', 'subscriber' ),
		) );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		update_user_meta( $user_id, $general['mobile_meta_key'], $mobile );

		do_action( 'asl_user_registered', $user_id, $mobile );

		return get_user_by( 'id', $user_id );
	}

	private static function log_in_user( $user ) {
		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );
		do_action( 'wp_login', $user->user_login, $user );
	}

	private static function get_redirect_url( $user ) {
		$general = ASL_Settings::get_group( 'asl_general' );

		if ( ! empty( $general['redirect_after_login'] ) ) {
			return esc_url_raw( $general['redirect_after_login'] );
		}

		return apply_filters( 'asl_login_redirect', home_url( '/' ), $user );
	}
}
