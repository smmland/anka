<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central place for option groups, defaults, and read helpers.
 * Everything is stored as one option row per group so tabs save independently.
 */
class ASL_Settings {

	const GROUPS = array( 'asl_general', 'asl_melipayamak', 'asl_recaptcha', 'asl_design', 'asl_messages' );

	public static function defaults() {
		return array(
			'asl_general' => array(
				'enable_panel'             => 1,
				'allow_password_login'     => 1,
				'allow_register'           => 1,
				'auto_register_on_login'   => 0,
				'mobile_meta_key'          => 'asl_mobile',
				'fallback_meta_keys'       => 'billing_phone',
				'redirect_after_login'     => '',
				'replace_wp_login'         => 0,
				'login_page_id'            => 0,
				'delete_data_on_uninstall' => 0,
				'enable_terms'             => 0,
				'terms_text_mode'          => 0, // 0 = checkbox (requires click), 1 = plain text (no click needed)
			),
			'asl_melipayamak' => array(
				'connection_method'    => 'webservice', // webservice | apikey
				'username'             => '',
				'password'             => '',
				'api_key'              => '',
				'sender_number'        => '',
				'otp_body_id'          => '',
				'code_length'          => 5,
				'code_expiry'          => 120,
				'resend_wait'          => 90,
				'max_attempts'         => 5,
				'max_requests_per_hour'=> 10,
			),
			'asl_recaptcha' => array(
				'enabled'              => 0,
				'version'              => 'v2', // v2 | v3
				'site_key'             => '',
				'secret_key'           => '',
				'v3_threshold'         => 0.5,
				'apply_send_otp'       => 1,
				'apply_password_login' => 1,
			),
			'asl_design' => array(
				'icon_url'                  => '',
				'primary_color'             => '#7C3AED',
				'secondary_color'           => '#06B6D4',
				'font_family'               => '',
				'title_login'               => 'خوش آمدید',
				'subtitle_login'            => 'برای ورود، شماره موبایل خود را وارد کنید',
				'title_register'            => 'ثبت‌نام در آرانکیا',
				'subtitle_register'         => 'برای ساخت حساب کاربری، شماره موبایل خود را وارد کنید',
				'tab_label_login'           => 'ورود',
				'tab_label_register'        => 'ثبت‌نام',
				'placeholder_mobile'        => '09xxxxxxxxx',
				'placeholder_code'          => 'کد تایید پیامک‌شده',
				'placeholder_password'      => 'رمز عبور',
				'placeholder_name'          => 'نام و نام خانوادگی (اختیاری)',
				'btn_send_code'             => 'دریافت کد تایید',
				'btn_verify'                => 'ورود',
				'btn_register'              => 'ثبت‌نام',
				'btn_password_login'        => 'ورود با رمز عبور',
				'link_use_password'         => 'ورود با رمز عبور',
				'link_use_otp'              => 'ورود با کد تایید پیامکی',
				'link_resend'               => 'ارسال مجدد کد',
				'text_switch_to_register'   => 'حساب کاربری ندارید؟ ثبت‌نام کنید',
				'text_switch_to_login'      => 'قبلا ثبت‌نام کرده‌اید؟ وارد شوید',
				'show_powered_by'           => 1,
				'powered_by_text'           => 'ورود امن با آرانکیا',
				'terms_text'                => 'با ثبت‌نام، {link} سایت آرانکیا را می‌پذیرم.',
				'terms_link_label'          => 'قوانین و مقررات',
				'terms_url'                 => '',
			),
			'asl_messages' => array(
				'invalid_mobile'          => 'شماره موبایل وارد شده معتبر نیست.',
				'otp_sent'                => 'کد تایید برای شما پیامک شد.',
				'otp_send_failed'         => 'ارسال پیامک با خطا مواجه شد. لطفا دوباره تلاش کنید.',
				'invalid_code'            => 'کد وارد شده صحیح نیست.',
				'code_expired'            => 'کد تایید منقضی شده است. لطفا مجددا درخواست دهید.',
				'too_many_requests'       => 'تعداد درخواست‌های شما بیش از حد مجاز است. کمی بعد دوباره تلاش کنید.',
				'resend_wait'             => 'لطفا {sec} ثانیه صبر کنید و دوباره تلاش کنید.',
				'recaptcha_failed'        => 'تایید ریکپچا ناموفق بود. لطفا صفحه را رفرش کرده و دوباره تلاش کنید.',
				'user_exists'             => 'این شماره موبایل قبلا ثبت‌نام کرده است. از قسمت ورود اقدام کنید.',
				'user_not_found'          => 'کاربری با این شماره موبایل یافت نشد.',
				'wrong_password'          => 'رمز عبور وارد شده اشتباه است.',
				'login_success'           => 'ورود شما با موفقیت انجام شد.',
				'register_success'        => 'ثبت‌نام شما با موفقیت انجام شد.',
				'generic_error'           => 'خطایی رخ داد. لطفا دوباره تلاش کنید.',
				'panel_disabled'          => 'امکان ورود پیامکی در حال حاضر غیرفعال است.',
				'password_login_disabled' => 'ورود با رمز عبور غیرفعال است.',
				'register_disabled'       => 'ثبت‌نام غیرفعال است.',
				'terms_required'          => 'برای ثبت‌نام باید قوانین و مقررات را بپذیرید.',
			),
		);
	}

	public static function install_defaults() {
		foreach ( self::defaults() as $group => $values ) {
			if ( false === get_option( $group, false ) ) {
				add_option( $group, $values );
			}
		}
	}

	public static function get_group( $group ) {
		$defaults = self::defaults();
		if ( ! isset( $defaults[ $group ] ) ) {
			return array();
		}
		$stored = get_option( $group, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return wp_parse_args( $stored, $defaults[ $group ] );
	}

	public static function get( $group, $key, $fallback = null ) {
		$values = self::get_group( $group );
		return array_key_exists( $key, $values ) ? $values[ $key ] : $fallback;
	}

	public static function message( $key ) {
		$messages = self::get_group( 'asl_messages' );
		return isset( $messages[ $key ] ) && '' !== $messages[ $key ] ? $messages[ $key ] : self::defaults()['asl_messages'][ $key ];
	}
}
