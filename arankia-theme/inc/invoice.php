<?php
/**
 * فاکتور قابل چاپ سفارش — یک صفحه مستقل و سبک (بدون هدر/فوتر سایت) که
 * از طریق ?arankia_invoice=ORDER_ID&key=ORDER_KEY در دسترس است.
 *
 * @package Arankia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function arankia_get_invoice_url( $order ) {
	return add_query_arg(
		array(
			'arankia_invoice' => $order->get_id(),
			'key'             => $order->get_order_key(),
		),
		home_url( '/' )
	);
}

function arankia_maybe_render_invoice() {
	if ( empty( $_GET['arankia_invoice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	$order_id = absint( $_GET['arankia_invoice'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$order    = wc_get_order( $order_id );

	if ( ! $order ) {
		wp_die( esc_html__( 'سفارش یافت نشد.', 'arankia' ) );
	}

	$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$owner_ok = is_user_logged_in() && get_current_user_id() === $order->get_customer_id();
	$key_ok   = $key && hash_equals( $order->get_order_key(), $key );

	if ( ! $owner_ok && ! $key_ok && ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'شما اجازه مشاهده این فاکتور را ندارید.', 'arankia' ) );
	}

	arankia_render_invoice_html( $order );
	exit;
}
add_action( 'template_redirect', 'arankia_maybe_render_invoice' );

function arankia_render_invoice_html( $order ) {
	$site_name  = get_bloginfo( 'name' );
	$site_phone = get_theme_mod( 'arankia_header_phone', '' );
	?>
	<!doctype html>
	<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<title><?php echo esc_html( $site_name ); ?> — <?php esc_html_e( 'فاکتور سفارش', 'arankia' ); ?> #<?php echo esc_html( $order->get_order_number() ); ?></title>
		<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" />
		<style>
			* { box-sizing: border-box; }
			body { font-family: Vazirmatn, Tahoma, sans-serif; direction: rtl; background: #f3f4f6; margin: 0; padding: 24px; color: #1f2430; }
			.invoice-box { max-width: 820px; margin: 0 auto; background: #fff; border-radius: 10px; padding: 32px; box-shadow: 0 2px 12px rgba(0,0,0,.06); }
			.invoice-head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #eee; padding-bottom: 20px; margin-bottom: 20px; }
			.invoice-head h1 { font-size: 20px; margin: 0 0 6px; }
			.invoice-meta { text-align: left; font-size: 13px; color: #555; }
			.invoice-parties { display: flex; justify-content: space-between; gap: 20px; margin-bottom: 24px; flex-wrap: wrap; }
			.invoice-parties div { font-size: 13px; line-height: 2; }
			table.invoice-items { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
			table.invoice-items th, table.invoice-items td { border: 1px solid #e5e7eb; padding: 10px; font-size: 13px; text-align: center; }
			table.invoice-items th { background: #f9fafb; }
			.invoice-totals { width: 320px; margin-inline-start: auto; font-size: 14px; }
			.invoice-totals div { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px dashed #e5e7eb; }
			.invoice-totals .grand-total { font-weight: 700; font-size: 16px; border-bottom: none; }
			.print-btn { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 16px; background: #0f6e5e; color: #fff; border: none; padding: 10px 18px; border-radius: 8px; cursor: pointer; font-family: inherit; font-size: 14px; }
			@media print { .print-btn { display: none; } body { background: #fff; padding: 0; } .invoice-box { box-shadow: none; } }
		</style>
	</head>
	<body>
		<button class="print-btn" onclick="window.print()"><?php esc_html_e( 'چاپ / دریافت PDF', 'arankia' ); ?></button>

		<div class="invoice-box">
			<div class="invoice-head">
				<div>
					<h1><?php echo esc_html( $site_name ); ?></h1>
					<?php if ( $site_phone ) : ?><span><?php echo esc_html( $site_phone ); ?></span><?php endif; ?>
				</div>
				<div class="invoice-meta">
					<div><?php esc_html_e( 'شماره فاکتور:', 'arankia' ); ?> #<?php echo esc_html( $order->get_order_number() ); ?></div>
					<div><?php esc_html_e( 'تاریخ:', 'arankia' ); ?> <?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></div>
					<div><?php esc_html_e( 'وضعیت:', 'arankia' ); ?> <?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></div>
				</div>
			</div>

			<div class="invoice-parties">
				<div>
					<strong><?php esc_html_e( 'خریدار', 'arankia' ); ?></strong><br />
					<?php echo esc_html( $order->get_formatted_billing_full_name() ); ?><br />
					<?php echo esc_html( $order->get_billing_phone() ); ?><br />
					<?php echo wp_kses_post( $order->get_formatted_billing_address() ? $order->get_formatted_billing_address() : __( 'ثبت نشده', 'arankia' ) ); ?>
				</div>
			</div>

			<table class="invoice-items">
				<thead>
					<tr>
						<th><?php esc_html_e( 'محصول', 'arankia' ); ?></th>
						<th><?php esc_html_e( 'تعداد', 'arankia' ); ?></th>
						<th><?php esc_html_e( 'قیمت واحد', 'arankia' ); ?></th>
						<th><?php esc_html_e( 'جمع', 'arankia' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $order->get_items() as $item ) : ?>
						<tr>
							<td><?php echo esc_html( $item->get_name() ); ?></td>
							<td><?php echo esc_html( $item->get_quantity() ); ?></td>
							<td><?php echo wp_kses_post( wc_price( $order->get_item_total( $item, false, true ) ) ); ?></td>
							<td><?php echo wp_kses_post( wc_price( $item->get_total() ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<div class="invoice-totals">
				<div><span><?php esc_html_e( 'جمع جزء', 'arankia' ); ?></span><span><?php echo wp_kses_post( wc_price( $order->get_subtotal() ) ); ?></span></div>
				<?php if ( (float) $order->get_total_discount() > 0 ) : ?>
					<div><span><?php esc_html_e( 'تخفیف', 'arankia' ); ?></span><span>-<?php echo wp_kses_post( wc_price( $order->get_total_discount() ) ); ?></span></div>
				<?php endif; ?>
				<?php if ( (float) $order->get_shipping_total() > 0 ) : ?>
					<div><span><?php esc_html_e( 'هزینه ارسال', 'arankia' ); ?></span><span><?php echo wp_kses_post( wc_price( $order->get_shipping_total() ) ); ?></span></div>
				<?php endif; ?>
				<div class="grand-total"><span><?php esc_html_e( 'مبلغ قابل پرداخت', 'arankia' ); ?></span><span><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span></div>
			</div>
		</div>
	</body>
	</html>
	<?php
}
