<?php
/**
 * My Account dashboard — overview cards + recent orders.
 *
 * @package Arankia
 */

defined( 'ABSPATH' ) || exit;

$user_id     = get_current_user_id();
$stats       = arankia_customer_order_stats( $user_id );
$wallet      = arankia_wallet_get_balance( $user_id );
$wishlist_ct = arankia_wishlist_count( $user_id );
?>

<div class="account-dashboard">

	<div class="account-welcome">
		<h2>
			<?php
			/* translators: %s: customer display name. */
			printf( esc_html__( 'سلام %s، خوش آمدید', 'arankia' ), '<span>' . esc_html( $current_user->display_name ) . '</span>' );
			?>
		</h2>
		<p><?php esc_html_e( 'از اینجا می‌توانید سفارش‌ها، کیف پول، لیست علاقه‌مندی‌ها و تیکت‌های پشتیبانی خود را مدیریت کنید.', 'arankia' ); ?></p>
	</div>

	<div class="account-stats-grid">
		<a class="account-stat-card" href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">
			<?php arankia_icon( 'box' ); ?>
			<span class="stat-value"><?php echo esc_html( $stats['total_orders'] ); ?></span>
			<span class="stat-label"><?php esc_html_e( 'کل سفارش‌ها', 'arankia' ); ?></span>
		</a>
		<a class="account-stat-card is-pending" href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">
			<?php arankia_icon( 'clock' ); ?>
			<span class="stat-value"><?php echo esc_html( $stats['pending'] ); ?></span>
			<span class="stat-label"><?php esc_html_e( 'در انتظار پرداخت', 'arankia' ); ?></span>
		</a>
		<a class="account-stat-card is-processing" href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">
			<?php arankia_icon( 'box' ); ?>
			<span class="stat-value"><?php echo esc_html( $stats['processing'] ); ?></span>
			<span class="stat-label"><?php esc_html_e( 'در حال انجام', 'arankia' ); ?></span>
		</a>
		<a class="account-stat-card" href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">
			<?php arankia_icon( 'card' ); ?>
			<span class="stat-value stat-value--sm"><?php echo esc_html( arankia_format_toman( $stats['total_spent'] ) ); ?></span>
			<span class="stat-label"><?php esc_html_e( 'مبلغ کل سفارشات', 'arankia' ); ?></span>
		</a>
		<a class="account-stat-card" href="<?php echo esc_url( arankia_get_endpoint_url( 'wallet' ) ); ?>">
			<?php arankia_icon( 'wallet' ); ?>
			<span class="stat-value stat-value--sm"><?php echo esc_html( arankia_format_toman( $wallet ) ); ?></span>
			<span class="stat-label"><?php esc_html_e( 'موجودی کیف پول', 'arankia' ); ?></span>
		</a>
		<a class="account-stat-card" href="<?php echo esc_url( arankia_get_endpoint_url( 'wishlist' ) ); ?>">
			<?php arankia_icon( 'heart' ); ?>
			<span class="stat-value"><?php echo esc_html( $wishlist_ct ); ?></span>
			<span class="stat-label"><?php esc_html_e( 'لیست علاقه‌مندی‌ها', 'arankia' ); ?></span>
		</a>
	</div>

	<div class="account-section">
		<div class="account-section-head">
			<h3><?php esc_html_e( 'آخرین سفارش‌های شما', 'arankia' ); ?></h3>
			<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>"><?php esc_html_e( 'مشاهده همه', 'arankia' ); ?></a>
		</div>

		<?php $recent_orders = wc_get_orders( array( 'customer_id' => $user_id, 'limit' => 5, 'orderby' => 'date', 'order' => 'DESC' ) ); ?>

		<?php if ( $recent_orders ) : ?>
			<div class="account-orders-table-wrap">
				<table class="account-orders-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'شماره سفارش', 'arankia' ); ?></th>
							<th><?php esc_html_e( 'تاریخ', 'arankia' ); ?></th>
							<th><?php esc_html_e( 'وضعیت', 'arankia' ); ?></th>
							<th><?php esc_html_e( 'مبلغ', 'arankia' ); ?></th>
							<th><?php esc_html_e( 'عملیات', 'arankia' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $recent_orders as $order ) : ?>
							<tr>
								<td data-title="<?php esc_attr_e( 'شماره سفارش', 'arankia' ); ?>">#<?php echo esc_html( $order->get_order_number() ); ?></td>
								<td data-title="<?php esc_attr_e( 'تاریخ', 'arankia' ); ?>"><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></td>
								<td data-title="<?php esc_attr_e( 'وضعیت', 'arankia' ); ?>">
									<span class="order-status-badge status-<?php echo esc_attr( $order->get_status() ); ?>"><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></span>
								</td>
								<td data-title="<?php esc_attr_e( 'مبلغ', 'arankia' ); ?>"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
								<td data-title="<?php esc_attr_e( 'عملیات', 'arankia' ); ?>">
									<a class="btn-link" href="<?php echo esc_url( $order->get_view_order_url() ); ?>"><?php esc_html_e( 'مشاهده', 'arankia' ); ?></a>
									<?php if ( $order->has_status( array( 'processing', 'completed', 'on-hold' ) ) ) : ?>
										| <a class="btn-link" href="<?php echo esc_url( arankia_get_invoice_url( $order ) ); ?>" target="_blank"><?php esc_html_e( 'فاکتور', 'arankia' ); ?></a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else : ?>
			<p class="account-empty"><?php esc_html_e( 'تاکنون سفارشی ثبت نکرده‌اید.', 'arankia' ); ?></p>
		<?php endif; ?>
	</div>

	<div class="account-quick-links">
		<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-address' ) ); ?>"><?php arankia_icon( 'map-pin' ); ?><?php esc_html_e( 'مدیریت نشانی‌ها', 'arankia' ); ?></a>
		<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'payment-methods' ) ); ?>"><?php arankia_icon( 'card' ); ?><?php esc_html_e( 'شیوه‌های پرداخت', 'arankia' ); ?></a>
		<a href="<?php echo esc_url( arankia_get_endpoint_url( 'tickets' ) ); ?>"><?php arankia_icon( 'ticket' ); ?><?php esc_html_e( 'تیکت پشتیبانی', 'arankia' ); ?></a>
		<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-account' ) ); ?>"><?php arankia_icon( 'user' ); ?><?php esc_html_e( 'اطلاعات حساب کاربری', 'arankia' ); ?></a>
	</div>

</div>
