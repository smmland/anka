<?php
/**
 * نوار پایین موبایل — میان‌بر سریع به خانه، دسته‌بندی‌ها، علاقه‌مندی‌ها،
 * سبد خرید و حساب کاربری؛ فقط در نمای موبایل نمایش داده می‌شود (assets/css/main.css).
 *
 * @package Arankia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}
?>
<nav class="mobile-tabbar" aria-label="<?php esc_attr_e( 'منوی سریع موبایل', 'arankia' ); ?>">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="<?php echo is_front_page() ? 'is-active' : ''; ?>">
		<?php arankia_icon( 'home' ); ?><?php esc_html_e( 'خانه', 'arankia' ); ?>
	</a>
	<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="<?php echo ( is_shop() || is_product_taxonomy() ) ? 'is-active' : ''; ?>">
		<?php arankia_icon( 'grid' ); ?><?php esc_html_e( 'دسته‌ها', 'arankia' ); ?>
	</a>
	<a href="<?php echo esc_url( arankia_get_endpoint_url( 'wishlist' ) ); ?>" class="<?php echo is_wc_endpoint_url( 'wishlist' ) ? 'is-active' : ''; ?>">
		<?php arankia_icon( 'heart' ); ?><?php esc_html_e( 'علاقه‌مندی', 'arankia' ); ?>
	</a>
	<a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="<?php echo is_cart() ? 'is-active' : ''; ?>">
		<?php arankia_icon( 'cart' ); ?><?php esc_html_e( 'سبد خرید', 'arankia' ); ?>
		<span class="action-count cart-count"><?php echo absint( WC()->cart ? WC()->cart->get_cart_contents_count() : 0 ); ?></span>
	</a>
	<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="<?php echo is_account_page() ? 'is-active' : ''; ?>">
		<?php arankia_icon( 'user' ); ?><?php esc_html_e( 'حساب من', 'arankia' ); ?>
	</a>
</nav>
