<?php
/**
 * پنل کاربری: منوی حساب کاربری، آمار داشبورد و صفحه «محصولات خریداری‌شده».
 *
 * @package Arankia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * آدرس یکی از endpoint های سفارشی حساب کاربری (wallet/wishlist/tickets/purchased-products).
 */
function arankia_get_endpoint_url( $endpoint ) {
	return wc_get_account_endpoint_url( $endpoint );
}

/* -------------------------------------------------------------------------
 * منوی حساب کاربری
 * ---------------------------------------------------------------------- */

function arankia_account_menu_items( $items ) {
	unset( $items['downloads'] );

	$labels = array(
		'dashboard'          => __( 'داشبورد', 'arankia' ),
		'orders'             => __( 'سفارش‌های من', 'arankia' ),
		'purchased-products' => __( 'محصولات خریداری‌شده', 'arankia' ),
		'wallet'             => __( 'کیف پول من', 'arankia' ),
		'wishlist'           => __( 'لیست علاقه‌مندی‌ها', 'arankia' ),
		'tickets'            => __( 'تیکت پشتیبانی', 'arankia' ),
		'edit-address'       => __( 'نشانی‌های من', 'arankia' ),
		'payment-methods'    => __( 'شیوه‌های پرداخت', 'arankia' ),
		'edit-account'       => __( 'اطلاعات حساب کاربری', 'arankia' ),
		'customer-logout'    => __( 'خروج', 'arankia' ),
	);

	$order = array( 'dashboard', 'orders', 'purchased-products', 'wallet', 'wishlist', 'tickets', 'edit-address', 'payment-methods', 'edit-account', 'customer-logout' );

	$ordered = array();
	foreach ( $order as $key ) {
		if ( isset( $items[ $key ] ) || in_array( $key, array( 'purchased-products', 'wallet', 'wishlist', 'tickets' ), true ) ) {
			$ordered[ $key ] = isset( $labels[ $key ] ) ? $labels[ $key ] : $items[ $key ];
		}
	}

	// هر آیتم باقی‌مانده‌ای که فراموش نشده باشد، در انتها اضافه می‌شود.
	foreach ( $items as $key => $label ) {
		if ( ! isset( $ordered[ $key ] ) ) {
			$ordered[ $key ] = $label;
		}
	}

	return $ordered;
}
add_filter( 'woocommerce_account_menu_items', 'arankia_account_menu_items' );

function arankia_endpoint_titles( $title, $endpoint ) {
	$titles = array(
		'purchased-products' => __( 'محصولات خریداری‌شده', 'arankia' ),
		'wallet'              => __( 'کیف پول من', 'arankia' ),
		'wishlist'            => __( 'لیست علاقه‌مندی‌ها', 'arankia' ),
		'tickets'             => __( 'تیکت پشتیبانی', 'arankia' ),
	);
	return isset( $titles[ $endpoint ] ) ? $titles[ $endpoint ] : $title;
}
add_filter( 'woocommerce_endpoint_wallet_title', 'arankia_endpoint_titles', 10, 2 );
add_filter( 'woocommerce_endpoint_wishlist_title', 'arankia_endpoint_titles', 10, 2 );
add_filter( 'woocommerce_endpoint_tickets_title', 'arankia_endpoint_titles', 10, 2 );
add_filter( 'woocommerce_endpoint_purchased-products_title', 'arankia_endpoint_titles', 10, 2 );

/* -------------------------------------------------------------------------
 * آمار سفارش‌های مشتری (برای کارت‌های داشبورد)
 * ---------------------------------------------------------------------- */

function arankia_customer_order_stats( $user_id ) {
	$cache_key = 'arankia_order_stats_' . $user_id;
	$cached    = wp_cache_get( $cache_key, 'arankia' );
	if ( false !== $cached ) {
		return $cached;
	}

	$pending    = wc_get_orders(
		array(
			'customer_id' => $user_id,
			'status'      => 'pending',
			'limit'       => -1,
			'return'      => 'ids',
		)
	);
	$processing = wc_get_orders(
		array(
			'customer_id' => $user_id,
			'status'      => 'processing',
			'limit'       => -1,
			'return'      => 'ids',
		)
	);
	$all_orders = wc_get_orders(
		array(
			'customer_id' => $user_id,
			'status'      => array( 'pending', 'processing', 'on-hold', 'completed' ),
			'limit'       => -1,
			'return'      => 'ids',
		)
	);

	$paid_orders = wc_get_orders(
		array(
			'customer_id' => $user_id,
			'status'      => array( 'processing', 'completed' ),
			'limit'       => -1,
			'return'      => 'objects',
		)
	);

	$total_spent = 0;
	foreach ( $paid_orders as $order ) {
		$total_spent += (float) $order->get_total();
	}

	$stats = array(
		'total_orders' => count( $all_orders ),
		'pending'      => count( $pending ),
		'processing'   => count( $processing ),
		'total_spent'  => $total_spent,
	);

	wp_cache_set( $cache_key, $stats, 'arankia', 5 * MINUTE_IN_SECONDS );

	return $stats;
}

/* -------------------------------------------------------------------------
 * Endpoint «محصولات خریداری‌شده»
 * ---------------------------------------------------------------------- */

function arankia_purchased_register_endpoint() {
	add_rewrite_endpoint( 'purchased-products', EP_ROOT | EP_PAGES );
}
add_action( 'init', 'arankia_purchased_register_endpoint' );

function arankia_purchased_query_vars( $vars ) {
	$vars[] = 'purchased-products';
	return $vars;
}
add_filter( 'query_vars', 'arankia_purchased_query_vars' );

/**
 * لیست بدون تکرار محصولاتی که مشتری تاکنون خریداری کرده (از سفارش‌های پرداخت‌شده).
 */
function arankia_get_purchased_products( $user_id ) {
	$orders = wc_get_orders(
		array(
			'customer_id' => $user_id,
			'status'      => array( 'processing', 'completed' ),
			'limit'       => -1,
			'return'      => 'objects',
		)
	);

	$products = array();
	foreach ( $orders as $order ) {
		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			if ( $product_id && ! isset( $products[ $product_id ] ) ) {
				$products[ $product_id ] = array(
					'product_id' => $product_id,
					'last_order' => $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0,
					'count'      => 1,
				);
			} elseif ( $product_id ) {
				$products[ $product_id ]['count'] ++;
			}
		}
	}

	uasort(
		$products,
		function ( $a, $b ) {
			return $b['last_order'] <=> $a['last_order'];
		}
	);

	return $products;
}

function arankia_purchased_products_endpoint_content() {
	$products = arankia_get_purchased_products( get_current_user_id() );
	?>
	<div class="account-purchased-products">
		<?php if ( empty( $products ) ) : ?>
			<p class="account-empty"><?php esc_html_e( 'شما هنوز هیچ محصولی خریداری نکرده‌اید.', 'arankia' ); ?></p>
		<?php else : ?>
			<div class="account-orders-table-wrap">
				<table class="account-orders-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'محصول', 'arankia' ); ?></th>
							<th><?php esc_html_e( 'تعداد خرید', 'arankia' ); ?></th>
							<th><?php esc_html_e( 'آخرین خرید', 'arankia' ); ?></th>
							<th><?php esc_html_e( 'عملیات', 'arankia' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $products as $row ) : ?>
							<?php
							$product = wc_get_product( $row['product_id'] );
							if ( ! $product ) {
								continue;
							}
							?>
							<tr>
								<td data-title="<?php esc_attr_e( 'محصول', 'arankia' ); ?>" class="purchased-product-cell">
									<?php echo wp_kses_post( $product->get_image( 'thumbnail' ) ); ?>
									<a href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
								</td>
								<td data-title="<?php esc_attr_e( 'تعداد خرید', 'arankia' ); ?>"><?php echo esc_html( $row['count'] ); ?></td>
								<td data-title="<?php esc_attr_e( 'آخرین خرید', 'arankia' ); ?>"><?php echo esc_html( date_i18n( 'Y/m/d', $row['last_order'] ) ); ?></td>
								<td data-title="<?php esc_attr_e( 'عملیات', 'arankia' ); ?>">
									<?php if ( $product->is_purchasable() && $product->is_in_stock() ) : ?>
										<a class="btn-link" href="<?php echo esc_url( $product->add_to_cart_url() ); ?>"><?php esc_html_e( 'خرید مجدد', 'arankia' ); ?></a>
									<?php else : ?>
										<span class="btn-link disabled"><?php esc_html_e( 'ناموجود', 'arankia' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>
	<?php
}
add_action( 'woocommerce_account_purchased-products_endpoint', 'arankia_purchased_products_endpoint_content' );

/**
 * پس از هر تغییر وضعیت سفارش، کش آمار داشبورد آن مشتری پاک می‌شود.
 */
function arankia_clear_order_stats_cache( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( $order ) {
		wp_cache_delete( 'arankia_order_stats_' . $order->get_customer_id(), 'arankia' );
	}
}
add_action( 'woocommerce_order_status_changed', 'arankia_clear_order_stats_cache' );
