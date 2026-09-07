<?php
/**
 * کیف پول اختصاصی آرانکیا — به‌طور کامل درون قالب پیاده‌سازی شده است:
 * - جدول اختصاصی تراکنش‌ها در دیتابیس (بدون نیاز به افزونه جانبی)
 * - endpoint «کیف پول من» در پنل کاربری با تاریخچه تراکنش‌ها و شارژ کیف پول
 * - درگاه پرداخت ووکامرسی «پرداخت از کیف پول» برای پرداخت سفارش با موجودی کیف پول
 * - مدیریت افزایش/کاهش موجودی از پیشخوان مدیریت
 *
 * @package Arankia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * توابع پایه کیف پول
 * ---------------------------------------------------------------------- */

function arankia_wallet_get_balance( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	if ( ! $user_id ) {
		return 0.0;
	}
	$balance = get_user_meta( $user_id, '_arankia_wallet_balance', true );
	return $balance ? (float) $balance : 0.0;
}

/**
 * ثبت یک تراکنش کیف پول (واریز/برداشت) و به‌روزرسانی موجودی.
 *
 * @param int    $user_id.
 * @param float  $amount مبلغ مثبت.
 * @param string $type 'credit' یا 'debit'.
 * @param string $description.
 * @param int    $order_id.
 * @return true|WP_Error
 */
function arankia_wallet_add_transaction( $user_id, $amount, $type, $description = '', $order_id = 0 ) {
	global $wpdb;

	$amount = round( (float) $amount, 2 );
	if ( $amount <= 0 || ! in_array( $type, array( 'credit', 'debit' ), true ) ) {
		return new WP_Error( 'arankia_wallet_invalid', __( 'مقدار یا نوع تراکنش نامعتبر است.', 'arankia' ) );
	}

	$current = arankia_wallet_get_balance( $user_id );

	if ( 'debit' === $type && $amount > $current ) {
		return new WP_Error( 'arankia_wallet_insufficient', __( 'موجودی کیف پول کافی نیست.', 'arankia' ) );
	}

	$new_balance = 'credit' === $type ? ( $current + $amount ) : ( $current - $amount );

	update_user_meta( $user_id, '_arankia_wallet_balance', $new_balance );

	$table = $wpdb->prefix . 'arankia_wallet_transactions';
	$wpdb->insert(
		$table,
		array(
			'user_id'       => $user_id,
			'type'          => $type,
			'amount'        => $amount,
			'balance_after' => $new_balance,
			'description'   => $description,
			'order_id'      => $order_id,
			'created_at'    => current_time( 'mysql' ),
		),
		array( '%d', '%s', '%f', '%f', '%s', '%d', '%s' )
	);

	return true;
}

function arankia_wallet_get_transactions( $user_id, $limit = 20, $offset = 0 ) {
	global $wpdb;
	$table = $wpdb->prefix . 'arankia_wallet_transactions';
	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$user_id,
			$limit,
			$offset
		)
	);
}

function arankia_wallet_count_transactions( $user_id ) {
	global $wpdb;
	$table = $wpdb->prefix . 'arankia_wallet_transactions';
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

/* -------------------------------------------------------------------------
 * Endpoint «کیف پول من»
 * ---------------------------------------------------------------------- */

function arankia_wallet_register_endpoint() {
	add_rewrite_endpoint( 'wallet', EP_ROOT | EP_PAGES );
}
add_action( 'init', 'arankia_wallet_register_endpoint' );

function arankia_wallet_query_vars( $vars ) {
	$vars[] = 'wallet';
	return $vars;
}
add_filter( 'query_vars', 'arankia_wallet_query_vars' );

/**
 * محصول مجازی «شارژ کیف پول» که برای ساخت سفارش شارژ استفاده می‌شود؛
 * فقط یک‌بار ساخته شده و از فروشگاه و جستجو مخفی است.
 */
function arankia_wallet_get_topup_product_id() {
	$product_id = get_option( 'arankia_wallet_topup_product_id' );

	if ( $product_id && get_post( $product_id ) ) {
		return (int) $product_id;
	}

	$product = new WC_Product_Simple();
	$product->set_name( __( 'شارژ کیف پول', 'arankia' ) );
	$product->set_status( 'publish' );
	$product->set_catalog_visibility( 'hidden' );
	$product->set_virtual( true );
	$product->set_price( 0 );
	$product->set_regular_price( 0 );
	$product->set_sold_individually( true );
	$id = $product->save();

	update_option( 'arankia_wallet_topup_product_id', $id );

	return $id;
}

function arankia_wallet_handle_topup_submit() {
	if ( ! isset( $_POST['arankia_wallet_topup_nonce'] ) || ! wp_verify_nonce( $_POST['arankia_wallet_topup_nonce'], 'arankia_wallet_topup' ) ) {
		return;
	}
	if ( ! is_user_logged_in() ) {
		return;
	}

	$amount = isset( $_POST['arankia_topup_amount'] ) ? (float) preg_replace( '/[^0-9.]/', '', wp_unslash( $_POST['arankia_topup_amount'] ) ) : 0;

	if ( $amount < 10000 ) {
		wc_add_notice( __( 'حداقل مبلغ شارژ کیف پول ۱۰٬۰۰۰ تومان است.', 'arankia' ), 'error' );
		return;
	}

	$user_id    = get_current_user_id();
	$product_id = arankia_wallet_get_topup_product_id();

	$order = wc_create_order( array( 'customer_id' => $user_id ) );
	$item  = new WC_Order_Item_Product();
	$item->set_product( wc_get_product( $product_id ) );
	$item->set_name( __( 'شارژ کیف پول', 'arankia' ) );
	$item->set_quantity( 1 );
	$item->set_subtotal( $amount );
	$item->set_total( $amount );
	$order->add_item( $item );

	$order->update_meta_data( '_arankia_wallet_topup', $amount );
	$order->set_customer_id( $user_id );
	$order->calculate_totals();
	$order->set_status( 'pending' );
	$order->save();

	wp_safe_redirect( $order->get_checkout_payment_url() );
	exit;
}
add_action( 'template_redirect', 'arankia_wallet_handle_topup_submit' );

/**
 * پس از تکمیل موفق پرداخت سفارش شارژ کیف پول، موجودی کاربر افزایش می‌یابد.
 */
function arankia_wallet_credit_on_payment( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	$amount = $order->get_meta( '_arankia_wallet_topup' );
	if ( ! $amount ) {
		return;
	}

	if ( 'yes' === $order->get_meta( '_arankia_wallet_credited' ) ) {
		return;
	}

	$result = arankia_wallet_add_transaction(
		$order->get_customer_id(),
		(float) $amount,
		'credit',
		/* translators: %s: order number. */
		sprintf( __( 'شارژ کیف پول - سفارش #%s', 'arankia' ), $order->get_order_number() ),
		$order_id
	);

	if ( ! is_wp_error( $result ) ) {
		$order->update_meta_data( '_arankia_wallet_credited', 'yes' );
		$order->add_order_note( __( 'موجودی کیف پول مشتری با موفقیت شارژ شد.', 'arankia' ) );
		$order->save();
	}
}
add_action( 'woocommerce_payment_complete', 'arankia_wallet_credit_on_payment' );
add_action( 'woocommerce_order_status_completed', 'arankia_wallet_credit_on_payment' );
add_action( 'woocommerce_order_status_processing', 'arankia_wallet_credit_on_payment' );

/**
 * محتوای صفحه «کیف پول من».
 */
function arankia_wallet_endpoint_content() {
	$user_id      = get_current_user_id();
	$balance      = arankia_wallet_get_balance( $user_id );
	$transactions = arankia_wallet_get_transactions( $user_id, 20 );
	$presets      = array( 50000, 100000, 200000, 500000 );
	?>
	<div class="account-wallet">
		<div class="wallet-balance-card">
			<span class="wallet-balance-label"><?php esc_html_e( 'موجودی فعلی کیف پول', 'arankia' ); ?></span>
			<span class="wallet-balance-amount"><?php echo esc_html( arankia_format_toman( $balance ) ); ?></span>
		</div>

		<div class="account-section">
			<div class="account-section-head">
				<h3><?php esc_html_e( 'شارژ کیف پول', 'arankia' ); ?></h3>
			</div>
			<form method="post" class="wallet-topup-form">
				<?php wp_nonce_field( 'arankia_wallet_topup', 'arankia_wallet_topup_nonce' ); ?>
				<div class="wallet-presets">
					<?php foreach ( $presets as $preset ) : ?>
						<button type="button" class="wallet-preset-btn" data-amount="<?php echo esc_attr( $preset ); ?>"><?php echo esc_html( arankia_format_toman( $preset ) ); ?></button>
					<?php endforeach; ?>
				</div>
				<div class="wallet-amount-row">
					<label for="arankia_topup_amount"><?php esc_html_e( 'مبلغ دلخواه (تومان)', 'arankia' ); ?></label>
					<input type="text" inputmode="numeric" id="arankia_topup_amount" name="arankia_topup_amount" placeholder="<?php esc_attr_e( 'مثلاً 150000', 'arankia' ); ?>" required />
				</div>
				<button type="submit" class="btn btn-primary"><?php esc_html_e( 'پرداخت و شارژ کیف پول', 'arankia' ); ?></button>
			</form>
		</div>

		<div class="account-section">
			<div class="account-section-head">
				<h3><?php esc_html_e( 'تاریخچه تراکنش‌ها', 'arankia' ); ?></h3>
			</div>
			<?php if ( $transactions ) : ?>
				<div class="account-orders-table-wrap">
					<table class="account-orders-table wallet-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'تاریخ', 'arankia' ); ?></th>
								<th><?php esc_html_e( 'شرح', 'arankia' ); ?></th>
								<th><?php esc_html_e( 'مبلغ', 'arankia' ); ?></th>
								<th><?php esc_html_e( 'موجودی پس از تراکنش', 'arankia' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $transactions as $txn ) : ?>
								<tr>
									<td data-title="<?php esc_attr_e( 'تاریخ', 'arankia' ); ?>"><?php echo esc_html( date_i18n( 'Y/m/d H:i', strtotime( $txn->created_at ) ) ); ?></td>
									<td data-title="<?php esc_attr_e( 'شرح', 'arankia' ); ?>"><?php echo esc_html( $txn->description ); ?></td>
									<td data-title="<?php esc_attr_e( 'مبلغ', 'arankia' ); ?>" class="wallet-amount wallet-amount--<?php echo esc_attr( $txn->type ); ?>">
										<?php echo ( 'credit' === $txn->type ? '+' : '-' ) . esc_html( arankia_format_toman( $txn->amount ) ); ?>
									</td>
									<td data-title="<?php esc_attr_e( 'موجودی', 'arankia' ); ?>"><?php echo esc_html( arankia_format_toman( $txn->balance_after ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else : ?>
				<p class="account-empty"><?php esc_html_e( 'هنوز تراکنشی در کیف پول شما ثبت نشده است.', 'arankia' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
	<?php
}
add_action( 'woocommerce_account_wallet_endpoint', 'arankia_wallet_endpoint_content' );

/* -------------------------------------------------------------------------
 * درگاه پرداخت «پرداخت از کیف پول»
 * ---------------------------------------------------------------------- */

function arankia_register_wallet_gateway( $gateways ) {
	$gateways[] = 'Arankia_WC_Gateway_Wallet';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'arankia_register_wallet_gateway' );

function arankia_init_wallet_gateway_class() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	class Arankia_WC_Gateway_Wallet extends WC_Payment_Gateway {

		public function __construct() {
			$this->id                 = 'arankia_wallet';
			$this->icon               = '';
			$this->has_fields         = false;
			$this->method_title       = __( 'پرداخت از کیف پول آرانکیا', 'arankia' );
			$this->method_description = __( 'به مشتریان اجازه می‌دهد مبلغ سفارش را از موجودی کیف پول خود پرداخت کنند.', 'arankia' );

			$this->init_form_fields();
			$this->init_settings();

			$this->title       = $this->get_option( 'title' );
			$this->description = $this->get_option( 'description' );
			$this->enabled     = $this->get_option( 'enabled' );

			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}

		public function init_form_fields() {
			$this->form_fields = array(
				'enabled'     => array(
					'title'   => __( 'فعال‌سازی', 'arankia' ),
					'type'    => 'checkbox',
					'label'   => __( 'فعال‌سازی پرداخت از کیف پول', 'arankia' ),
					'default' => 'yes',
				),
				'title'       => array(
					'title'       => __( 'عنوان', 'arankia' ),
					'type'        => 'text',
					'default'     => __( 'پرداخت از کیف پول', 'arankia' ),
					'desc_tip'    => true,
				),
				'description' => array(
					'title'   => __( 'توضیحات', 'arankia' ),
					'type'    => 'textarea',
					'default' => __( 'مبلغ سفارش از موجودی کیف پول شما کسر خواهد شد.', 'arankia' ),
				),
			);
		}

		/**
		 * فقط زمانی که کاربر وارد شده و موجودی کافی داشته باشد نمایش داده می‌شود،
		 * و برای سفارش‌های شارژ کیف پول (جلوگیری از حلقه) غیرفعال است.
		 */
		public function is_available() {
			if ( 'yes' !== $this->enabled || ! is_user_logged_in() ) {
				return false;
			}

			if ( WC()->cart && arankia_cart_has_wallet_topup() ) {
				return false;
			}

			$total = WC()->cart ? (float) WC()->cart->get_total( 'edit' ) : 0;
			return $total > 0 && arankia_wallet_get_balance() >= $total;
		}

		public function process_payment( $order_id ) {
			$order = wc_get_order( $order_id );

			$result = arankia_wallet_add_transaction(
				$order->get_customer_id(),
				(float) $order->get_total(),
				'debit',
				/* translators: %s: order number. */
				sprintf( __( 'پرداخت سفارش #%s از کیف پول', 'arankia' ), $order->get_order_number() ),
				$order_id
			);

			if ( is_wp_error( $result ) ) {
				wc_add_notice( $result->get_error_message(), 'error' );
				return array( 'result' => 'failure' );
			}

			$order->payment_complete();
			$order->add_order_note( __( 'پرداخت با موفقیت از کیف پول کاربر انجام شد.', 'arankia' ) );
			WC()->cart->empty_cart();

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		}
	}
}
add_action( 'plugins_loaded', 'arankia_init_wallet_gateway_class', 11 );

function arankia_cart_has_wallet_topup() {
	if ( ! WC()->cart ) {
		return false;
	}
	$topup_id = (int) get_option( 'arankia_wallet_topup_product_id' );
	if ( ! $topup_id ) {
		return false;
	}
	foreach ( WC()->cart->get_cart() as $item ) {
		if ( (int) $item['product_id'] === $topup_id ) {
			return true;
		}
	}
	return false;
}

/* -------------------------------------------------------------------------
 * مدیریت از پیشخوان: افزایش/کاهش دستی موجودی + گزارش تراکنش‌ها
 * ---------------------------------------------------------------------- */

function arankia_wallet_admin_menu() {
	add_submenu_page(
		'woocommerce',
		__( 'کیف پول مشتریان', 'arankia' ),
		__( 'کیف پول مشتریان', 'arankia' ),
		'manage_woocommerce',
		'arankia-wallet',
		'arankia_wallet_admin_page'
	);
}
add_action( 'admin_menu', 'arankia_wallet_admin_menu' );

function arankia_wallet_admin_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	$searched_user = null;
	if ( ! empty( $_GET['arankia_search_user'] ) ) {
		$searched_user = get_user_by( 'email', sanitize_text_field( wp_unslash( $_GET['arankia_search_user'] ) ) );
		if ( ! $searched_user ) {
			$searched_user = get_user_by( 'login', sanitize_text_field( wp_unslash( $_GET['arankia_search_user'] ) ) );
		}
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'کیف پول مشتریان', 'arankia' ); ?></h1>

		<form method="get" class="arankia-wallet-search">
			<input type="hidden" name="page" value="arankia-wallet" />
			<input type="text" name="arankia_search_user" placeholder="<?php esc_attr_e( 'ایمیل یا نام کاربری مشتری', 'arankia' ); ?>" value="<?php echo esc_attr( isset( $_GET['arankia_search_user'] ) ? wp_unslash( $_GET['arankia_search_user'] ) : '' ); ?>" />
			<?php submit_button( __( 'جستجو', 'arankia' ), 'secondary', '', false ); ?>
		</form>

		<?php if ( $searched_user ) : ?>
			<h2>
				<?php
				/* translators: %s: customer display name. */
				printf( esc_html__( 'کیف پول %s', 'arankia' ), esc_html( $searched_user->display_name ) );
				?>
				— <?php echo esc_html( arankia_format_toman( arankia_wallet_get_balance( $searched_user->ID ) ) ); ?>
			</h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="arankia-wallet-adjust-form">
				<?php wp_nonce_field( 'arankia_wallet_adjust', 'arankia_wallet_adjust_nonce' ); ?>
				<input type="hidden" name="action" value="arankia_wallet_adjust" />
				<input type="hidden" name="user_id" value="<?php echo esc_attr( $searched_user->ID ); ?>" />
				<input type="hidden" name="redirect_search" value="<?php echo esc_attr( isset( $_GET['arankia_search_user'] ) ? wp_unslash( $_GET['arankia_search_user'] ) : '' ); ?>" />
				<label>
					<?php esc_html_e( 'مبلغ (تومان)', 'arankia' ); ?>
					<input type="number" name="amount" min="1" step="1" required />
				</label>
				<label>
					<select name="type">
						<option value="credit"><?php esc_html_e( 'افزایش موجودی', 'arankia' ); ?></option>
						<option value="debit"><?php esc_html_e( 'کاهش موجودی', 'arankia' ); ?></option>
					</select>
				</label>
				<label>
					<?php esc_html_e( 'توضیح', 'arankia' ); ?>
					<input type="text" name="description" placeholder="<?php esc_attr_e( 'مثلاً: هدیه جبرانی', 'arankia' ); ?>" />
				</label>
				<?php submit_button( __( 'اعمال تغییر', 'arankia' ), 'primary', '', false ); ?>
			</form>

			<h3><?php esc_html_e( 'تاریخچه تراکنش‌ها', 'arankia' ); ?></h3>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'تاریخ', 'arankia' ); ?></th>
						<th><?php esc_html_e( 'نوع', 'arankia' ); ?></th>
						<th><?php esc_html_e( 'مبلغ', 'arankia' ); ?></th>
						<th><?php esc_html_e( 'موجودی پس از تراکنش', 'arankia' ); ?></th>
						<th><?php esc_html_e( 'شرح', 'arankia' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( arankia_wallet_get_transactions( $searched_user->ID, 50 ) as $txn ) : ?>
						<tr>
							<td><?php echo esc_html( $txn->created_at ); ?></td>
							<td><?php echo esc_html( 'credit' === $txn->type ? __( 'واریز', 'arankia' ) : __( 'برداشت', 'arankia' ) ); ?></td>
							<td><?php echo esc_html( arankia_format_toman( $txn->amount ) ); ?></td>
							<td><?php echo esc_html( arankia_format_toman( $txn->balance_after ) ); ?></td>
							<td><?php echo esc_html( $txn->description ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php elseif ( isset( $_GET['arankia_search_user'] ) ) : ?>
			<p><?php esc_html_e( 'کاربری با این مشخصات یافت نشد.', 'arankia' ); ?></p>
		<?php else : ?>
			<p><?php esc_html_e( 'برای مشاهده و مدیریت کیف پول یک مشتری، ایمیل یا نام کاربری او را جستجو کنید.', 'arankia' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

function arankia_handle_wallet_admin_adjust() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'دسترسی غیرمجاز.', 'arankia' ) );
	}
	if ( ! isset( $_POST['arankia_wallet_adjust_nonce'] ) || ! wp_verify_nonce( $_POST['arankia_wallet_adjust_nonce'], 'arankia_wallet_adjust' ) ) {
		wp_die( esc_html__( 'درخواست نامعتبر.', 'arankia' ) );
	}

	$user_id     = absint( $_POST['user_id'] );
	$amount      = isset( $_POST['amount'] ) ? (float) $_POST['amount'] : 0;
	$type        = isset( $_POST['type'] ) && 'debit' === $_POST['type'] ? 'debit' : 'credit';
	$description = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';
	$description = $description ? $description : ( 'credit' === $type ? __( 'افزایش موجودی توسط مدیر', 'arankia' ) : __( 'کاهش موجودی توسط مدیر', 'arankia' ) );

	$result = arankia_wallet_add_transaction( $user_id, $amount, $type, $description );

	$redirect = add_query_arg(
		array(
			'page'                 => 'arankia-wallet',
			'arankia_search_user'  => rawurlencode( sanitize_text_field( wp_unslash( $_POST['redirect_search'] ) ) ),
			'arankia_wallet_notice' => is_wp_error( $result ) ? 'error' : 'success',
		),
		admin_url( 'admin.php' )
	);

	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'admin_post_arankia_wallet_adjust', 'arankia_handle_wallet_admin_adjust' );
