<?php
/**
 * لیست علاقه‌مندی‌ها — کاملاً درون قالب، بدون نیاز به افزونه جانبی.
 * برای کاربران عضو در user meta و برای مهمان‌ها در کوکی ذخیره می‌شود.
 *
 * @package Arankia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARANKIA_WISHLIST_COOKIE', 'arankia_wishlist' );

function arankia_wishlist_get_ids( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();

	if ( $user_id ) {
		$ids = get_user_meta( $user_id, '_arankia_wishlist', true );
		return is_array( $ids ) ? array_map( 'absint', $ids ) : array();
	}

	if ( empty( $_COOKIE[ ARANKIA_WISHLIST_COOKIE ] ) ) {
		return array();
	}

	$ids = json_decode( wp_unslash( $_COOKIE[ ARANKIA_WISHLIST_COOKIE ] ), true );
	return is_array( $ids ) ? array_map( 'absint', $ids ) : array();
}

function arankia_wishlist_count( $user_id = 0 ) {
	return count( arankia_wishlist_get_ids( $user_id ) );
}

function arankia_wishlist_has_product( $product_id, $user_id = 0 ) {
	return in_array( (int) $product_id, arankia_wishlist_get_ids( $user_id ), true );
}

function arankia_wishlist_toggle( $product_id, $user_id = 0 ) {
	$product_id = absint( $product_id );
	$user_id    = $user_id ? $user_id : get_current_user_id();
	$ids        = arankia_wishlist_get_ids( $user_id );
	$in_list    = in_array( $product_id, $ids, true );

	if ( $in_list ) {
		$ids = array_values( array_diff( $ids, array( $product_id ) ) );
	} else {
		$ids[] = $product_id;
	}

	if ( $user_id ) {
		update_user_meta( $user_id, '_arankia_wishlist', $ids );
	} else {
		setcookie( ARANKIA_WISHLIST_COOKIE, wp_json_encode( $ids ), time() + ( 90 * DAY_IN_SECONDS ), COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN );
		$_COOKIE[ ARANKIA_WISHLIST_COOKIE ] = wp_json_encode( $ids );
	}

	return ! $in_list;
}

/**
 * وقتی کاربر مهمان وارد می‌شود، آیتم‌های کوکی به حساب کاربری او منتقل می‌شود.
 */
function arankia_wishlist_merge_on_login( $user_login, $user ) {
	$cookie_ids = arankia_wishlist_get_ids( 0 );
	if ( empty( $cookie_ids ) ) {
		return;
	}
	$user_ids = arankia_wishlist_get_ids( $user->ID );
	$merged   = array_values( array_unique( array_merge( $user_ids, $cookie_ids ) ) );
	update_user_meta( $user->ID, '_arankia_wishlist', $merged );
	setcookie( ARANKIA_WISHLIST_COOKIE, '', time() - HOUR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN );
}
add_action( 'wp_login', 'arankia_wishlist_merge_on_login', 10, 2 );

/**
 * دکمه قلبِ افزودن/حذف از علاقه‌مندی‌ها (در کارت محصول و صفحه محصول استفاده می‌شود).
 */
function arankia_wishlist_button( $product_id ) {
	$active = arankia_wishlist_has_product( $product_id );
	?>
	<button
		type="button"
		class="wishlist-btn<?php echo $active ? ' is-active' : ''; ?>"
		data-product-id="<?php echo esc_attr( $product_id ); ?>"
		aria-pressed="<?php echo $active ? 'true' : 'false'; ?>"
		aria-label="<?php esc_attr_e( 'افزودن به لیست علاقه‌مندی‌ها', 'arankia' ); ?>"
	>
		<?php arankia_icon( 'heart' ); ?>
	</button>
	<?php
}
add_action( 'woocommerce_single_product_summary', 'arankia_wishlist_button_single', 6 );
function arankia_wishlist_button_single() {
	global $product;
	if ( $product ) {
		echo '<div class="single-product-wishlist">';
		arankia_wishlist_button( $product->get_id() );
		echo '<span>' . esc_html__( 'افزودن به علاقه‌مندی‌ها', 'arankia' ) . '</span></div>';
	}
}

/**
 * AJAX: افزودن/حذف محصول از لیست علاقه‌مندی‌ها.
 */
function arankia_ajax_toggle_wishlist() {
	check_ajax_referer( 'arankia_wishlist_nonce', 'nonce' );

	$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
	if ( ! $product_id || ! get_post( $product_id ) ) {
		wp_send_json_error( array( 'message' => __( 'محصول یافت نشد.', 'arankia' ) ) );
	}

	$added = arankia_wishlist_toggle( $product_id );

	wp_send_json_success(
		array(
			'added' => $added,
			'count' => arankia_wishlist_count(),
		)
	);
}
add_action( 'wp_ajax_arankia_toggle_wishlist', 'arankia_ajax_toggle_wishlist' );
add_action( 'wp_ajax_nopriv_arankia_toggle_wishlist', 'arankia_ajax_toggle_wishlist' );

/* -------------------------------------------------------------------------
 * Endpoint «لیست علاقه‌مندی‌ها»
 * ---------------------------------------------------------------------- */

function arankia_wishlist_register_endpoint() {
	add_rewrite_endpoint( 'wishlist', EP_ROOT | EP_PAGES );
}
add_action( 'init', 'arankia_wishlist_register_endpoint' );

function arankia_wishlist_query_vars( $vars ) {
	$vars[] = 'wishlist';
	return $vars;
}
add_filter( 'query_vars', 'arankia_wishlist_query_vars' );

function arankia_wishlist_endpoint_content() {
	$ids = arankia_wishlist_get_ids();
	?>
	<div class="account-wishlist">
		<?php if ( empty( $ids ) ) : ?>
			<p class="account-empty"><?php esc_html_e( 'لیست علاقه‌مندی‌های شما خالی است.', 'arankia' ); ?></p>
			<a class="btn btn-primary" href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>"><?php esc_html_e( 'مشاهده محصولات', 'arankia' ); ?></a>
		<?php else : ?>
			<ul class="products wishlist-grid columns-4">
				<?php
				foreach ( $ids as $product_id ) {
					$post_obj = get_post( $product_id );
					if ( ! $post_obj || 'product' !== $post_obj->post_type ) {
						continue;
					}
					global $product;
					$product = wc_get_product( $product_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
					if ( ! $product ) {
						continue;
					}
					wc_get_template_part( 'content', 'product' );
				}
				?>
			</ul>
		<?php endif; ?>
	</div>
	<?php
}
add_action( 'woocommerce_account_wishlist_endpoint', 'arankia_wishlist_endpoint_content' );
