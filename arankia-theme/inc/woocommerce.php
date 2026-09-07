<?php
/**
 * WooCommerce integration: layout wrapper, loop tweaks, breadcrumb,
 * mini-cart count fragment. Product/checkout/cart pages stay on
 * WooCommerce's own templates (styled via assets/css/woocommerce.css)
 * so plugin updates keep working; only the loop card markup and the
 * My Account shell are overridden.
 *
 * @package Arankia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Swap WooCommerce's default wrapper for the theme's own container so shop
 * pages share the exact same grid/sidebar structure as the rest of the site.
 */
remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
add_action( 'woocommerce_before_main_content', 'arankia_wc_wrapper_start', 10 );
add_action( 'woocommerce_after_main_content', 'arankia_wc_wrapper_end', 10 );

function arankia_wc_has_shop_sidebar() {
	return is_active_sidebar( 'shop-sidebar' ) && ( is_shop() || is_product_taxonomy() || is_product_category() || is_product_tag() );
}

function arankia_wc_wrapper_start() {
	$has_sidebar = arankia_wc_has_shop_sidebar();
	echo '<div class="container page-container"><div class="' . ( $has_sidebar ? 'content-with-sidebar' : 'full-width-shop' ) . '"><main id="main" class="site-main woocommerce-main">';
}

function arankia_wc_wrapper_end() {
	echo '</main>';
	if ( arankia_wc_has_shop_sidebar() ) {
		get_sidebar();
	}
	echo '</div></div>';
}

/**
 * Loop / gallery tweaks.
 */
add_filter(
	'loop_shop_columns',
	function () {
		return 4;
	}
);

add_filter(
	'woocommerce_output_related_products_args',
	function ( $args ) {
		$args['posts_per_page'] = 4;
		$args['columns']        = 4;
		return $args;
	}
);

/**
 * Custom sale badge with the actual discount percentage.
 */
function arankia_sale_flash( $html, $post, $product ) {
	if ( ! $product->is_on_sale() || ! $product->is_type( 'simple' ) ) {
		return '<span class="onsale">' . esc_html__( 'تخفیف', 'arankia' ) . '</span>';
	}

	$regular = (float) $product->get_regular_price();
	$sale    = (float) $product->get_sale_price();

	if ( $regular <= 0 ) {
		return '<span class="onsale">' . esc_html__( 'تخفیف', 'arankia' ) . '</span>';
	}

	$percent = round( ( ( $regular - $sale ) / $regular ) * 100 );

	/* translators: %d: discount percentage. */
	return '<span class="onsale">' . sprintf( esc_html__( '%d%% تخفیف', 'arankia' ), $percent ) . '</span>';
}
add_filter( 'woocommerce_sale_flash', 'arankia_sale_flash', 10, 3 );

/**
 * Keep the header cart bubble in sync via WooCommerce's fragment system.
 */
function arankia_cart_count_fragment( $fragments ) {
	ob_start();
	?>
	<span class="action-count cart-count"><?php echo absint( WC()->cart ? WC()->cart->get_cart_contents_count() : 0 ); ?></span>
	<?php
	$fragments['.cart-count'] = ob_get_clean();
	return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'arankia_cart_count_fragment' );

/**
 * Trust badges under the add-to-cart button on the single product page.
 */
function arankia_single_product_trust_badges() {
	?>
	<div class="single-product-trust-badges">
		<span><?php arankia_icon( 'check' ); ?><?php esc_html_e( 'ضمانت اصالت کالا', 'arankia' ); ?></span>
		<span><?php arankia_icon( 'box' ); ?><?php esc_html_e( '۷ روز ضمانت بازگشت', 'arankia' ); ?></span>
		<span><?php arankia_icon( 'card' ); ?><?php esc_html_e( 'پرداخت امن آنلاین', 'arankia' ); ?></span>
	</div>
	<?php
}
add_action( 'woocommerce_single_product_summary', 'arankia_single_product_trust_badges', 31 );

/**
 * Mobile sticky add-to-cart bar shown once the real add-to-cart form has
 * scrolled out of view (see assets/js/main.js IntersectionObserver).
 */
function arankia_output_sticky_add_to_cart() {
	global $product;
	if ( ! $product || ! $product->is_purchasable() ) {
		return;
	}
	?>
	<div class="sticky-add-to-cart">
		<span class="sticky-price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
		<a href="#" class="btn btn-primary" onclick="document.querySelector('form.cart')?.scrollIntoView({behavior:'smooth', block:'center'});return false;">
			<?php esc_html_e( 'افزودن به سبد خرید', 'arankia' ); ?>
		</a>
	</div>
	<?php
}
add_action( 'woocommerce_after_single_product', 'arankia_output_sticky_add_to_cart' );

/**
 * Adds a "دریافت فاکتور" (invoice) action next to "پرداخت مجدد" /
 * "مشاهده" on the My Account > Orders list.
 */
function arankia_order_actions( $actions, $order ) {
	if ( $order->has_status( array( 'processing', 'completed', 'on-hold' ) ) ) {
		$actions['invoice'] = array(
			'url'  => arankia_get_invoice_url( $order ),
			'name' => __( 'دریافت فاکتور', 'arankia' ),
		);
	}
	return $actions;
}
add_filter( 'woocommerce_my_account_my_orders_actions', 'arankia_order_actions', 10, 2 );

/**
 * Nicer Persian labels for the default WooCommerce order statuses shown
 * across the dashboard cards, order list and tickets context.
 */
function arankia_order_status_label( $status ) {
	$labels = array(
		'pending'    => __( 'در انتظار پرداخت', 'arankia' ),
		'processing' => __( 'در حال پردازش', 'arankia' ),
		'on-hold'    => __( 'در انتظار بررسی', 'arankia' ),
		'completed'  => __( 'تکمیل‌شده', 'arankia' ),
		'cancelled'  => __( 'لغو‌شده', 'arankia' ),
		'refunded'   => __( 'بازگشت وجه', 'arankia' ),
		'failed'     => __( 'ناموفق', 'arankia' ),
	);

	return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
}

/**
 * Disable the default WooCommerce stylesheet in favour of assets/css/woocommerce.css.
 */
add_filter( 'woocommerce_enqueue_styles', '__return_empty_array' );
