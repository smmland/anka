<?php
/**
 * Styles / scripts.
 *
 * @package Arankia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function arankia_enqueue_assets() {
	// فونت فارسی وزیرمتن.
	wp_enqueue_style(
		'arankia-font-vazirmatn',
		'https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css',
		array(),
		'33.003'
	);

	wp_enqueue_style( 'arankia-main', ARANKIA_URI . '/assets/css/main.css', array( 'arankia-font-vazirmatn' ), ARANKIA_VERSION );

	if ( class_exists( 'WooCommerce' ) ) {
		wp_enqueue_style( 'arankia-woocommerce', ARANKIA_URI . '/assets/css/woocommerce.css', array( 'arankia-main' ), ARANKIA_VERSION );

		if ( is_account_page() ) {
			wp_enqueue_style( 'arankia-account', ARANKIA_URI . '/assets/css/account.css', array( 'arankia-woocommerce' ), ARANKIA_VERSION );
		}

		if ( is_front_page() ) {
			wp_enqueue_style( 'arankia-front-page', ARANKIA_URI . '/assets/css/front-page.css', array( 'arankia-woocommerce' ), ARANKIA_VERSION );
		}
	}

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}

	wp_enqueue_script( 'arankia-main', ARANKIA_URI . '/assets/js/main.js', array(), ARANKIA_VERSION, true );

	if ( class_exists( 'WooCommerce' ) ) {
		wp_enqueue_script( 'arankia-ajax-cart', ARANKIA_URI . '/assets/js/ajax-cart.js', array(), ARANKIA_VERSION, true );

		if ( is_account_page() ) {
			wp_enqueue_script( 'arankia-account', ARANKIA_URI . '/assets/js/account.js', array(), ARANKIA_VERSION, true );
		}

		if ( is_front_page() ) {
			wp_enqueue_script( 'arankia-front-page', ARANKIA_URI . '/assets/js/front-page.js', array( 'arankia-ajax-cart' ), ARANKIA_VERSION, true );
		}

		wp_localize_script(
			'arankia-ajax-cart',
			'arankiaVars',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'arankia_nonce' ),
				'isLoggedIn'     => is_user_logged_in(),
				'wishlistNonce'  => wp_create_nonce( 'arankia_wishlist_nonce' ),
				'newsletterNonce' => wp_create_nonce( 'arankia_newsletter_nonce' ),
				'loginUrl'       => wc_get_page_permalink( 'myaccount' ),
				'i18n'           => array(
					'addedToWishlist'   => __( 'به لیست علاقه‌مندی‌ها اضافه شد', 'arankia' ),
					'removedFromWishlist' => __( 'از لیست علاقه‌مندی‌ها حذف شد', 'arankia' ),
					'error'             => __( 'خطایی رخ داد، دوباره تلاش کنید', 'arankia' ),
				),
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'arankia_enqueue_assets' );

function arankia_admin_assets( $hook ) {
	wp_enqueue_style( 'arankia-admin', ARANKIA_URI . '/assets/css/admin.css', array(), ARANKIA_VERSION );
}
add_action( 'admin_enqueue_scripts', 'arankia_admin_assets' );

/**
 * Skip-link + editor style basics.
 */
function arankia_editor_styles() {
	add_editor_style( 'assets/css/editor-style.css' );
}
add_action( 'after_setup_theme', 'arankia_editor_styles' );
