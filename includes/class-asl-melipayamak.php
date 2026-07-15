<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin client around Melipayamak's REST APIs.
 * Supports both the classic username/password webservice and the newer console API-key service.
 */
class ASL_Melipayamak {

	const WEBSERVICE_BASE = 'https://rest.payamak-panel.com/api/SendSMS/';
	const CONSOLE_BASE    = 'https://console.melipayamak.com/api/send/';

	/**
	 * Send an OTP code. Prefers the approved pattern (bodyId) when configured, since
	 * unpatterned OTP SMS is routinely filtered by Iranian carriers.
	 *
	 * @return true|WP_Error
	 */
	public static function send_otp( $mobile, $code ) {
		$settings = ASL_Settings::get_group( 'asl_melipayamak' );

		if ( 'apikey' === $settings['connection_method'] ) {
			if ( empty( $settings['api_key'] ) ) {
				return new WP_Error( 'asl_no_api_key', __( 'کلید API ملی‌پیامک تنظیم نشده است.', 'arankia-sms-login' ) );
			}

			if ( ! empty( $settings['otp_body_id'] ) ) {
				return self::request(
					self::CONSOLE_BASE . 'otp/' . rawurlencode( $settings['api_key'] ),
					array(
						'bodyId' => $settings['otp_body_id'],
						'to'     => $mobile,
						'args'   => array( (string) $code ),
					)
				);
			}

			if ( empty( $settings['sender_number'] ) ) {
				return new WP_Error( 'asl_no_sender', __( 'شماره فرستنده (خط) ملی‌پیامک تنظیم نشده است.', 'arankia-sms-login' ) );
			}

			return self::request(
				self::CONSOLE_BASE . 'shared/' . rawurlencode( $settings['api_key'] ),
				array(
					'from' => $settings['sender_number'],
					'to'   => $mobile,
					'text' => self::default_otp_text( $code ),
				)
			);
		}

		// Webservice (username/password) method.
		if ( empty( $settings['username'] ) || empty( $settings['password'] ) ) {
			return new WP_Error( 'asl_no_credentials', __( 'نام کاربری یا رمز عبور وب‌سرویس ملی‌پیامک تنظیم نشده است.', 'arankia-sms-login' ) );
		}

		if ( ! empty( $settings['otp_body_id'] ) ) {
			return self::request(
				self::WEBSERVICE_BASE . 'BaseNumber3',
				array(
					'username' => $settings['username'],
					'password' => $settings['password'],
					'text'     => (string) $code,
					'to'       => $mobile,
					'bodyId'   => $settings['otp_body_id'],
				)
			);
		}

		if ( empty( $settings['sender_number'] ) ) {
			return new WP_Error( 'asl_no_sender', __( 'شماره فرستنده (خط) ملی‌پیامک تنظیم نشده است.', 'arankia-sms-login' ) );
		}

		return self::request(
			self::WEBSERVICE_BASE . 'SendSMS',
			array(
				'username' => $settings['username'],
				'password' => $settings['password'],
				'to'       => array( $mobile ),
				'from'     => $settings['sender_number'],
				'text'     => self::default_otp_text( $code ),
				'isFlash'  => false,
			)
		);
	}

	/**
	 * Send an arbitrary text message, used by the admin "test send" button.
	 *
	 * @return true|WP_Error
	 */
	public static function send_test( $mobile, $text ) {
		$settings = ASL_Settings::get_group( 'asl_melipayamak' );

		if ( 'apikey' === $settings['connection_method'] ) {
			if ( empty( $settings['api_key'] ) || empty( $settings['sender_number'] ) ) {
				return new WP_Error( 'asl_missing_config', __( 'کلید API یا شماره فرستنده تنظیم نشده است.', 'arankia-sms-login' ) );
			}
			return self::request(
				self::CONSOLE_BASE . 'shared/' . rawurlencode( $settings['api_key'] ),
				array(
					'from' => $settings['sender_number'],
					'to'   => $mobile,
					'text' => $text,
				)
			);
		}

		if ( empty( $settings['username'] ) || empty( $settings['password'] ) || empty( $settings['sender_number'] ) ) {
			return new WP_Error( 'asl_missing_config', __( 'نام کاربری، رمز عبور یا شماره فرستنده تنظیم نشده است.', 'arankia-sms-login' ) );
		}

		return self::request(
			self::WEBSERVICE_BASE . 'SendSMS',
			array(
				'username' => $settings['username'],
				'password' => $settings['password'],
				'to'       => array( $mobile ),
				'from'     => $settings['sender_number'],
				'text'     => $text,
				'isFlash'  => false,
			)
		);
	}

	private static function default_otp_text( $code ) {
		/* translators: %s: the verification code */
		return sprintf( __( 'کد تایید آرانکیا: %s', 'arankia-sms-login' ), $code );
	}

	/**
	 * @return true|WP_Error
	 */
	private static function request( $url, $body ) {
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'asl_http_error', sprintf( 'Melipayamak HTTP %d: %s', $code, $raw ) );
		}

		// The webservice API returns a numeric "RetStatus" (1 = success); the console
		// API returns {"recId": ...} on success. Treat any non-empty successful body as OK
		// unless it clearly reports a known failure status.
		if ( is_array( $data ) && isset( $data['RetStatus'] ) && (int) $data['RetStatus'] !== 1 ) {
			$status_message = isset( $data['StrRetStatus'] ) ? $data['StrRetStatus'] : $raw;
			return new WP_Error( 'asl_send_failed', $status_message );
		}

		return true;
	}
}
