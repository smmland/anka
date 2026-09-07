<?php
/**
 * Customizer: brand colors, header contact info, social links, footer text.
 * All values are exposed as CSS custom properties so Elementor-built pages
 * can also read them (e.g. via global colors matching the theme palette).
 *
 * @package Arankia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function arankia_customize_register( $wp_customize ) {

	$wp_customize->add_panel(
		'arankia_options',
		array(
			'title'    => __( 'تنظیمات قالب آرانکیا', 'arankia' ),
			'priority' => 30,
		)
	);

	/* ---------------- رنگ‌بندی ---------------- */
	$wp_customize->add_section(
		'arankia_colors',
		array(
			'title' => __( 'رنگ‌بندی برند', 'arankia' ),
			'panel' => 'arankia_options',
		)
	);

	$color_fields = array(
		'primary_color'   => array( '#0f6e5e', __( 'رنگ اصلی', 'arankia' ) ),
		'secondary_color' => array( '#e8b13b', __( 'رنگ ثانویه (تخفیف/برچسب)', 'arankia' ) ),
		'dark_color'      => array( '#101828', __( 'رنگ تیره متن/فوتر', 'arankia' ) ),
	);

	foreach ( $color_fields as $id => $field ) {
		$wp_customize->add_setting(
			'arankia_' . $id,
			array(
				'default'           => $field[0],
				'sanitize_callback' => 'sanitize_hex_color',
			)
		);
		$wp_customize->add_control(
			new WP_Customize_Color_Control(
				$wp_customize,
				'arankia_' . $id,
				array(
					'label'   => $field[1],
					'section' => 'arankia_colors',
				)
			)
		);
	}

	/* ---------------- هدر ---------------- */
	$wp_customize->add_section(
		'arankia_header',
		array(
			'title' => __( 'نوار بالای هدر', 'arankia' ),
			'panel' => 'arankia_options',
		)
	);

	$wp_customize->add_setting(
		'arankia_announce_enable',
		array(
			'default'           => true,
			'sanitize_callback' => 'rest_sanitize_boolean',
		)
	);
	$wp_customize->add_control(
		'arankia_announce_enable',
		array(
			'label'   => __( 'نمایش نوار اعلان بالای سایت', 'arankia' ),
			'section' => 'arankia_header',
			'type'    => 'checkbox',
		)
	);

	$text_fields = array(
		'announce_text'  => array( __( 'ارسال رایگان برای سفارش‌های بالای ۲,۰۰۰,۰۰۰ تومان — تا پایان این هفته', 'arankia' ), __( 'متن نوار اعلان', 'arankia' ) ),
		'header_phone'   => array( '021-00000000', __( 'شماره تماس', 'arankia' ) ),
		'header_hours'   => array( __( 'شنبه تا پنجشنبه ۹ تا ۱۸', 'arankia' ), __( 'ساعات پاسخگویی', 'arankia' ) ),
		'social_instagram' => array( '', __( 'لینک اینستاگرام', 'arankia' ) ),
		'social_telegram'  => array( '', __( 'لینک تلگرام', 'arankia' ) ),
		'social_whatsapp'  => array( '', __( 'لینک واتساپ', 'arankia' ) ),
	);

	foreach ( $text_fields as $id => $field ) {
		$wp_customize->add_setting(
			'arankia_' . $id,
			array(
				'default'           => $field[0],
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		$wp_customize->add_control(
			'arankia_' . $id,
			array(
				'label'   => $field[1],
				'section' => 'arankia_header',
				'type'    => 'text',
			)
		);
	}

	/* ---------------- فوتر ---------------- */
	$wp_customize->add_section(
		'arankia_footer',
		array(
			'title' => __( 'فوتر', 'arankia' ),
			'panel' => 'arankia_options',
		)
	);

	$wp_customize->add_setting(
		'arankia_footer_about',
		array(
			'default'           => __( 'آرانکیا؛ خرید مطمئن و سریع با پشتیبانی همیشگی.', 'arankia' ),
			'sanitize_callback' => 'sanitize_textarea_field',
		)
	);
	$wp_customize->add_control(
		'arankia_footer_about',
		array(
			'label'   => __( 'متن کوتاه درباره فروشگاه', 'arankia' ),
			'section' => 'arankia_footer',
			'type'    => 'textarea',
		)
	);

	$wp_customize->add_setting(
		'arankia_footer_copyright',
		array(
			/* translators: %year% is replaced at render time. */
			'default'           => __( 'تمامی حقوق این وب‌سایت متعلق به آرانکیا است. © %year%', 'arankia' ),
			'sanitize_callback' => 'sanitize_text_field',
		)
	);
	$wp_customize->add_control(
		'arankia_footer_copyright',
		array(
			'label'   => __( 'متن کپی‌رایت', 'arankia' ),
			'section' => 'arankia_footer',
			'type'    => 'text',
		)
	);

	/* ---------------- صفحه اصلی (لندینگ) ---------------- */
	$wp_customize->add_section(
		'arankia_home',
		array(
			'title' => __( 'صفحه اصلی (لندینگ)', 'arankia' ),
			'panel' => 'arankia_options',
		)
	);

	$wp_customize->add_setting(
		'arankia_hero_title',
		array(
			'default'           => arankia_home_default( 'hero_title' ),
			'sanitize_callback' => 'sanitize_text_field',
		)
	);
	$wp_customize->add_control(
		'arankia_hero_title',
		array(
			'label'   => __( 'عنوان اصلی هیرو', 'arankia' ),
			'section' => 'arankia_home',
			'type'    => 'text',
		)
	);

	$wp_customize->add_setting(
		'arankia_hero_subtitle',
		array(
			'default'           => arankia_home_default( 'hero_subtitle' ),
			'sanitize_callback' => 'sanitize_textarea_field',
		)
	);
	$wp_customize->add_control(
		'arankia_hero_subtitle',
		array(
			'label'   => __( 'توضیح زیر عنوان هیرو', 'arankia' ),
			'section' => 'arankia_home',
			'type'    => 'textarea',
		)
	);

	// آمار (۴ عدد بزرگ).
	for ( $i = 1; $i <= 4; $i++ ) {
		$wp_customize->add_setting(
			'arankia_stat_value_' . $i,
			array(
				'default'           => arankia_home_default( 'stat_value_' . $i ),
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		$wp_customize->add_control(
			'arankia_stat_value_' . $i,
			array(
				/* translators: %d: stat tile number. */
				'label'   => sprintf( __( 'عدد آماری شماره %d', 'arankia' ), $i ),
				'section' => 'arankia_home',
				'type'    => 'text',
			)
		);
		$wp_customize->add_setting(
			'arankia_stat_label_' . $i,
			array(
				'default'           => arankia_home_default( 'stat_label_' . $i ),
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		$wp_customize->add_control(
			'arankia_stat_label_' . $i,
			array(
				/* translators: %d: stat tile number. */
				'label'   => sprintf( __( 'برچسب آماری شماره %d', 'arankia' ), $i ),
				'section' => 'arankia_home',
				'type'    => 'text',
			)
		);
	}

	// نظرات مشتریان (۳ مورد نمونه، قابل جایگزینی).
	for ( $i = 1; $i <= 3; $i++ ) {
		$wp_customize->add_setting(
			'arankia_testimonial_text_' . $i,
			array(
				'default'           => arankia_home_default( 'testimonial_text_' . $i ),
				'sanitize_callback' => 'sanitize_textarea_field',
			)
		);
		$wp_customize->add_control(
			'arankia_testimonial_text_' . $i,
			array(
				/* translators: %d: testimonial number. */
				'label'   => sprintf( __( 'متن نظر مشتری %d (نمونه — جایگزین کنید)', 'arankia' ), $i ),
				'section' => 'arankia_home',
				'type'    => 'textarea',
			)
		);
		$wp_customize->add_setting(
			'arankia_testimonial_name_' . $i,
			array(
				'default'           => arankia_home_default( 'testimonial_name_' . $i ),
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		$wp_customize->add_control(
			'arankia_testimonial_name_' . $i,
			array(
				/* translators: %d: testimonial number. */
				'label'   => sprintf( __( 'نام مشتری %d', 'arankia' ), $i ),
				'section' => 'arankia_home',
				'type'    => 'text',
			)
		);
		$wp_customize->add_setting(
			'arankia_testimonial_city_' . $i,
			array(
				'default'           => arankia_home_default( 'testimonial_city_' . $i ),
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		$wp_customize->add_control(
			'arankia_testimonial_city_' . $i,
			array(
				/* translators: %d: testimonial number. */
				'label'   => sprintf( __( 'شهر مشتری %d', 'arankia' ), $i ),
				'section' => 'arankia_home',
				'type'    => 'text',
			)
		);
	}

	$wp_customize->add_setting(
		'arankia_newsletter_title',
		array(
			'default'           => arankia_home_default( 'newsletter_title' ),
			'sanitize_callback' => 'sanitize_text_field',
		)
	);
	$wp_customize->add_control(
		'arankia_newsletter_title',
		array(
			'label'   => __( 'عنوان بخش خبرنامه', 'arankia' ),
			'section' => 'arankia_home',
			'type'    => 'text',
		)
	);

	$wp_customize->add_setting(
		'arankia_newsletter_text',
		array(
			'default'           => arankia_home_default( 'newsletter_text' ),
			'sanitize_callback' => 'sanitize_textarea_field',
		)
	);
	$wp_customize->add_control(
		'arankia_newsletter_text',
		array(
			'label'   => __( 'توضیح بخش خبرنامه', 'arankia' ),
			'section' => 'arankia_home',
			'type'    => 'textarea',
		)
	);
}
add_action( 'customize_register', 'arankia_customize_register' );

function arankia_customizer_css() {
	$primary   = get_theme_mod( 'arankia_primary_color', '#0f6e5e' );
	$secondary = get_theme_mod( 'arankia_secondary_color', '#e8b13b' );
	$dark      = get_theme_mod( 'arankia_dark_color', '#101828' );
	?>
	<style id="arankia-customizer-css">
		:root {
			--arankia-primary: <?php echo esc_html( $primary ); ?>;
			--arankia-secondary: <?php echo esc_html( $secondary ); ?>;
			--arankia-dark: <?php echo esc_html( $dark ); ?>;
		}
	</style>
	<?php
}
add_action( 'wp_head', 'arankia_customizer_css' );
