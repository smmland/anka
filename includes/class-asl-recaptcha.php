<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASL_Recaptcha {

	const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

	public static function is_enabled_for( $form ) {
		$settings = ASL_Settings::get_group( 'asl_recaptcha' );

		if ( empty( $settings['enabled'] ) || empty( $settings['site_key'] ) || empty( $settings['secret_key'] ) ) {
			return false;
		}

		if ( 'send_otp' === $form ) {
			return ! empty( $settings['apply_send_otp'] );
		}

		if ( 'password_login' === $form ) {
			return ! empty( $settings['apply_password_login'] );
		}

		return false;
	}

	/**
	 * @return true|WP_Error
	 */
	public static function verify( $token, $form = 'send_otp' ) {
		if ( ! self::is_enabled_for( $form ) ) {
			return true;
		}

		if ( empty( $token ) ) {
			return new WP_Error( 'asl_recaptcha_missing', ASL_Settings::message( 'recaptcha_failed' ) );
		}

		$settings = ASL_Settings::get_group( 'asl_recaptcha' );

		$response = wp_remote_post(
			self::VERIFY_URL,
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => $settings['secret_key'],
					'response' => $token,
					'remoteip' => self::get_client_ip(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'asl_recaptcha_error', ASL_Settings::message( 'recaptcha_failed' ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $data['success'] ) ) {
			return new WP_Error( 'asl_recaptcha_failed', ASL_Settings::message( 'recaptcha_failed' ) );
		}

		if ( 'v3' === $settings['version'] ) {
			$score = isset( $data['score'] ) ? (float) $data['score'] : 0;
			if ( $score < (float) $settings['v3_threshold'] ) {
				return new WP_Error( 'asl_recaptcha_low_score', ASL_Settings::message( 'recaptcha_failed' ) );
			}
		}

		return true;
	}

	private static function get_client_ip() {
		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				$ip = trim( explode( ',', $ip )[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '';
	}
}
