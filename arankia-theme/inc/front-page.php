<?php
/**
 * داده‌های صفحه اصلی (لندینگ): دسته‌بندی‌ها، پیشنهاد ویژه، پرفروش‌ها،
 * تازه‌ها و فرم عضویت در خبرنامه — همه چیز با داده واقعی ووکامرس کار
 * می‌کند و در نبود محتوا، به‌شکل مناسب پنهان یا جایگزین می‌شود.
 *
 * @package Arankia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * پرفروش‌ترین دسته‌های محصول (برای قفسه دسته‌بندی‌ها).
 *
 * @return WP_Term[]
 */
function arankia_home_categories( $limit = 6 ) {
	$terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'parent'     => 0,
			'number'     => $limit,
			'orderby'    => 'count',
			'order'      => 'DESC',
		)
	);

	return is_wp_error( $terms ) ? array() : $terms;
}

/**
 * محصولات دارای تخفیف فعال، برای بخش «پیشنهاد امروز».
 *
 * @return WC_Product[]
 */
function arankia_home_deal_products( $limit = 4 ) {
	$ids = wc_get_product_ids_on_sale();
	if ( empty( $ids ) ) {
		return array();
	}

	return wc_get_products(
		array(
			'include' => $ids,
			'limit'   => $limit,
			'orderby' => 'rand',
			'status'  => 'publish',
		)
	);
}

/**
 * زمان پایان نزدیک‌ترین تخفیف فعال (برای شمارش معکوس)، در غیر این صورت
 * پایان همان روز به‌عنوان مقدار پیش‌فرض بازگردانده می‌شود.
 */
function arankia_home_deal_countdown_target( $deal_products ) {
	$soonest = null;

	foreach ( $deal_products as $product ) {
		$date_to = $product->get_date_on_sale_to();
		if ( $date_to && ( ! $soonest || $date_to->getTimestamp() < $soonest ) ) {
			$soonest = $date_to->getTimestamp();
		}
	}

	if ( $soonest && $soonest > time() ) {
		return $soonest;
	}

	return strtotime( 'tomorrow midnight' );
}

function arankia_home_bestsellers( $limit = 8 ) {
	return wc_get_products(
		array(
			'limit'   => $limit,
			'orderby' => 'popularity',
			'status'  => 'publish',
		)
	);
}

function arankia_home_new_arrivals( $limit = 8 ) {
	return wc_get_products(
		array(
			'limit'   => $limit,
			'orderby' => 'date',
			'order'   => 'DESC',
			'status'  => 'publish',
		)
	);
}

/**
 * چند محصول تصادفی برای کارت‌های شناور بخش هیرو — اگر فروشگاه هنوز
 * محصولی ندارد، قالب از محتوای نمونه استفاده می‌کند.
 */
function arankia_home_hero_products( $limit = 4 ) {
	return wc_get_products(
		array(
			'limit'   => $limit,
			'orderby' => 'rand',
			'status'  => 'publish',
		)
	);
}

/**
 * یک کارت محصول برای قفسه‌های صفحه اصلی (پیشنهاد ویژه، پرفروش‌ها، تازه‌ها).
 * از دکمه علاقه‌مندی و افزودن به سبد واقعی خود قالب استفاده می‌کند تا با
 * بقیه سایت هماهنگ باشد.
 *
 * @param WC_Product $product
 * @param string     $badge 'sale' | 'new' | ''
 */
function arankia_home_render_product_card( $product, $badge = '' ) {
	$visual = arankia_visual_placeholder( $product->get_id() );
	?>
	<div class="home-product-card">
		<div class="home-product-media home-tile-tint-<?php echo esc_attr( $visual['tint'] ); ?>">
			<?php if ( has_post_thumbnail( $product->get_id() ) ) : ?>
				<?php echo $product->get_image( 'arankia-product-thumb' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php else : ?>
				<?php arankia_icon( $visual['icon'] ); ?>
			<?php endif; ?>

			<?php if ( 'sale' === $badge && $product->is_on_sale() ) : ?>
				<span class="home-sale-badge"><?php esc_html_e( 'تخفیف', 'arankia' ); ?></span>
			<?php elseif ( 'new' === $badge ) : ?>
				<span class="home-new-badge"><?php esc_html_e( 'جدید', 'arankia' ); ?></span>
			<?php endif; ?>

			<?php if ( function_exists( 'arankia_wishlist_button' ) ) : ?>
				<?php arankia_wishlist_button( $product->get_id() ); ?>
			<?php endif; ?>
		</div>
		<div class="home-product-body">
			<?php $cats = wc_get_product_category_list( $product->get_id() ); ?>
			<?php if ( $cats ) : ?><span class="home-product-cat"><?php echo wp_strip_all_tags( $cats ); ?></span><?php endif; ?>
			<h3 class="home-product-name"><a href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></h3>
			<?php if ( wc_review_ratings_enabled() && $product->get_average_rating() > 0 ) : ?>
				<div class="home-product-rating"><?php echo wc_get_rating_html( $product->get_average_rating() ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			<?php endif; ?>
			<div class="home-product-price"><?php echo $product->get_price_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			<a href="<?php echo esc_url( $product->add_to_cart_url() ); ?>" class="home-product-add ajax_add_to_cart add_to_cart_button" data-quantity="1" data-product_id="<?php echo esc_attr( $product->get_id() ); ?>" data-product_sku="<?php echo esc_attr( $product->get_sku() ); ?>" rel="nofollow">
				<?php arankia_icon( 'cart' ); ?><?php esc_html_e( 'افزودن به سبد', 'arankia' ); ?>
			</a>
		</div>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * فرم عضویت در خبرنامه / باشگاه مشتریان
 * ---------------------------------------------------------------------- */

function arankia_ajax_newsletter_subscribe() {
	check_ajax_referer( 'arankia_newsletter_nonce', 'nonce' );

	$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

	if ( ! is_email( $email ) ) {
		wp_send_json_error( array( 'message' => __( 'ایمیل وارد‌شده معتبر نیست.', 'arankia' ) ) );
	}

	global $wpdb;
	$table = $wpdb->prefix . 'arankia_newsletter_subscribers';

	$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE email = %s", $email ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	if ( ! $exists ) {
		$wpdb->insert(
			$table,
			array(
				'email'      => $email,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s' )
		);
	}

	wp_send_json_success( array( 'message' => __( 'با موفقیت عضو شدید!', 'arankia' ) ) );
}
add_action( 'wp_ajax_arankia_newsletter_subscribe', 'arankia_ajax_newsletter_subscribe' );
add_action( 'wp_ajax_nopriv_arankia_newsletter_subscribe', 'arankia_ajax_newsletter_subscribe' );

function arankia_newsletter_admin_menu() {
	add_menu_page(
		__( 'خبرنامه آرانکیا', 'arankia' ),
		__( 'خبرنامه', 'arankia' ),
		'manage_options',
		'arankia-newsletter',
		'arankia_newsletter_admin_page',
		'dashicons-email',
		58
	);
}
add_action( 'admin_menu', 'arankia_newsletter_admin_menu' );

function arankia_newsletter_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	global $wpdb;
	$table = $wpdb->prefix . 'arankia_newsletter_subscribers';
	$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 300" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
	?>
	<div class="wrap">
		<h1>
			<?php esc_html_e( 'اعضای خبرنامه آرانکیا', 'arankia' ); ?>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=arankia_export_newsletter' ), 'arankia_export_newsletter' ) ); ?>" class="page-title-action"><?php esc_html_e( 'دانلود CSV', 'arankia' ); ?></a>
		</h1>
		<p>
			<?php
			/* translators: %d: total subscriber count. */
			printf( esc_html__( 'مجموع اعضا: %d نفر', 'arankia' ), $total );
			?>
		</p>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ایمیل', 'arankia' ); ?></th>
					<th><?php esc_html_e( 'تاریخ عضویت', 'arankia' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( $rows ) : ?>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row->email ); ?></td>
							<td><?php echo esc_html( $row->created_at ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="2"><?php esc_html_e( 'هنوز عضوی ثبت نشده است.', 'arankia' ); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}

function arankia_export_newsletter_csv() {
	if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'arankia_export_newsletter' ) ) {
		wp_die( esc_html__( 'دسترسی غیرمجاز.', 'arankia' ) );
	}

	global $wpdb;
	$table = $wpdb->prefix . 'arankia_newsletter_subscribers';
	$rows  = $wpdb->get_results( "SELECT email, created_at FROM {$table} ORDER BY created_at DESC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=arankia-newsletter.csv' );

	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, array( 'email', 'created_at' ) );
	foreach ( $rows as $row ) {
		fputcsv( $out, array( $row->email, $row->created_at ) );
	}
	fclose( $out );
	exit;
}
add_action( 'admin_post_arankia_export_newsletter', 'arankia_export_newsletter_csv' );
