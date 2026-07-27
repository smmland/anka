<?php
/**
 * WooCommerce payment gateway for Bank Mellat (Behpardakht Mellat).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
	return;
}

class WC_Gateway_Bank_Mellat extends WC_Payment_Gateway {

	/** @var bool */
	private $convert_to_rial;

	/** @var bool */
	private $debug;

	public function __construct() {
		$this->id                 = 'bank_mellat';
		$this->has_fields         = false;
		$this->method_title       = __( 'بانک ملت (به‌پرداخت)', 'bank-mellat-gateway' );
		$this->method_description = __( 'پرداخت آنلاین سفارش از طریق درگاه به‌پرداخت ملت (Behpardakht Mellat). برای فعال‌سازی، ترمینال آی‌دی، نام کاربری و رمز عبور دریافتی از بانک را وارد کنید.', 'bank-mellat-gateway' );
		$this->supports            = array( 'products', 'refunds' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title             = $this->get_option( 'title' );
		$this->description       = $this->get_option( 'description' );
		$this->enabled            = $this->get_option( 'enabled' );
		$this->icon               = esc_url( $this->get_option( 'icon_url', BMG_URL . 'assets/images/mellat-icon.svg' ) );
		$this->convert_to_rial   = 'yes' === $this->get_option( 'convert_to_rial', 'yes' );
		$this->debug              = 'yes' === $this->get_option( 'debug', 'no' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		add_action( 'woocommerce_api_wc_gateway_bank_mellat', array( $this, 'handle_callback' ) );

		add_filter( 'woocommerce_order_actions', array( $this, 'add_order_actions' ) );
		add_action( 'woocommerce_order_action_bmg_inquiry', array( $this, 'order_action_inquiry' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_order_meta_box' ) );
		add_action( 'admin_post_bmg_clear_log', array( $this, 'handle_clear_log' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Loads the bundled QR code generator only on the order edit screen, where the
	 * payment-link box needs it.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'woocommerce_page_wc-orders' ), true ) ) {
			return;
		}

		wp_enqueue_script( 'bmg-qrcode', BMG_URL . 'assets/js/vendor/qrcode.js', array(), BMG_VERSION, true );
		wp_enqueue_script( 'bmg-pay-link', BMG_URL . 'assets/js/pay-link.js', array( 'bmg-qrcode' ), BMG_VERSION, true );
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'          => array(
				'title'   => __( 'فعال‌سازی', 'bank-mellat-gateway' ),
				'type'    => 'checkbox',
				'label'   => __( 'فعال‌سازی درگاه پرداخت بانک ملت', 'bank-mellat-gateway' ),
				'default' => 'no',
			),
			'title'            => array(
				'title'       => __( 'عنوان', 'bank-mellat-gateway' ),
				'type'        => 'text',
				'description' => __( 'عنوانی که در هنگام تسویه‌حساب به مشتری نمایش داده می‌شود.', 'bank-mellat-gateway' ),
				'default'     => __( 'پرداخت آنلاین (بانک ملت)', 'bank-mellat-gateway' ),
				'desc_tip'    => true,
			),
			'description'      => array(
				'title'       => __( 'توضیحات', 'bank-mellat-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'توضیحی که هنگام تسویه‌حساب زیر عنوان نمایش داده می‌شود.', 'bank-mellat-gateway' ),
				'default'     => __( 'پرداخت امن از طریق درگاه بانک ملت.', 'bank-mellat-gateway' ),
				'desc_tip'    => true,
			),
			'icon_url'         => array(
				'title'       => __( 'آیکون درگاه', 'bank-mellat-gateway' ),
				'type'        => 'text',
				'description' => __( 'آدرس تصویر آیکونی که کنار عنوان در صفحه تسویه‌حساب نمایش داده می‌شود. به‌صورت پیش‌فرض آیکون به‌پرداخت ملت تنظیم شده؛ در صورت تمایل می‌توانید آن را با آدرس تصویر دیگری جایگزین کنید (خالی گذاشتن = بدون آیکون).', 'bank-mellat-gateway' ),
				'default'     => BMG_URL . 'assets/images/mellat-icon.svg',
				'desc_tip'    => false,
			),
			'credentials'      => array(
				'title'       => __( 'اطلاعات پذیرنده', 'bank-mellat-gateway' ),
				'type'        => 'title',
				'description' => __( 'این اطلاعات را بانک ملت پس از عقد قرارداد پذیرندگی به‌پرداخت در اختیار شما قرار می‌دهد.', 'bank-mellat-gateway' ),
			),
			'terminal_id'      => array(
				'title'             => __( 'ترمینال آی‌دی (Terminal ID)', 'bank-mellat-gateway' ),
				'type'              => 'text',
				'description'       => __( 'شماره ترمینال دریافتی از بانک ملت.', 'bank-mellat-gateway' ),
				'default'           => '',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'autocomplete' => 'off',
					'inputmode'    => 'numeric',
				),
			),
			'username'         => array(
				'title'             => __( 'نام کاربری (Username)', 'bank-mellat-gateway' ),
				'type'              => 'text',
				'description'       => __( 'نام کاربری وب‌سرویس به‌پرداخت ملت.', 'bank-mellat-gateway' ),
				'default'           => '',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			),
			'password'         => array(
				'title'             => __( 'رمز عبور (Password)', 'bank-mellat-gateway' ),
				'type'              => 'password',
				'description'       => __( 'رمز عبور وب‌سرویس به‌پرداخت ملت. این مقدار به‌صورت پنهان نمایش داده می‌شود.', 'bank-mellat-gateway' ),
				'default'           => '',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'autocomplete' => 'new-password',
				),
			),
			'checkout_flow'    => array(
				'title' => __( 'نحوه انتقال به درگاه بانک', 'bank-mellat-gateway' ),
				'type'  => 'title',
			),
			'show_invoice_preview' => array(
				'title'       => __( 'نمایش پیش‌فاکتور', 'bank-mellat-gateway' ),
				'type'        => 'checkbox',
				'label'       => __( 'قبل از انتقال به صفحه پرداخت بانک، خلاصه سفارش را به مشتری نشان بده', 'bank-mellat-gateway' ),
				'description' => __( 'در حالت پیش‌فرض مشتری بلافاصله به صفحه پرداخت بانک منتقل می‌شود. با فعال‌سازی این گزینه، ابتدا صفحه‌ای شامل اقلام سفارش و مبلغ نهایی نمایش داده می‌شود و مشتری با کلیک روی دکمه «پرداخت» به بانک منتقل می‌گردد.', 'bank-mellat-gateway' ),
				'default'     => 'no',
				'desc_tip'    => false,
			),
			'messages'         => array(
				'title'       => __( 'پیام‌های سفارشی', 'bank-mellat-gateway' ),
				'type'        => 'title',
				'description' => __( 'می‌توانید در این پیام‌ها از متغیرهای زیر استفاده کنید: {order_id} (شماره سفارش)، {tracking_code} (کد رهگیری تراکنش)، {ref_id} (شماره مرجع RefId)، {sale_reference_id} (شماره پیگیری بانک)، {card_pan} (شماره کارت)، {amount} (مبلغ سفارش)، {error_code} (کد خطا)، {error_message} (توضیح خطا).', 'bank-mellat-gateway' ),
			),
			'success_message'  => array(
				'title'       => __( 'پیام پرداخت موفق', 'bank-mellat-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'این پیام در صفحه تشکر (بعد از پرداخت موفق) به مشتری نمایش داده و به‌عنوان یادداشت سفارش نیز ثبت می‌شود.', 'bank-mellat-gateway' ),
				'default'     => __( 'پرداخت شما با موفقیت انجام شد. کد رهگیری: {tracking_code} — شماره پیگیری بانک: {sale_reference_id}', 'bank-mellat-gateway' ),
				'desc_tip'    => false,
			),
			'failed_message'   => array(
				'title'       => __( 'پیام پرداخت ناموفق', 'bank-mellat-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'این پیام هنگام ناموفق بودن پرداخت (به جز انصراف کاربر) به مشتری نمایش داده می‌شود.', 'bank-mellat-gateway' ),
				'default'     => __( 'پرداخت شما انجام نشد. {error_message} لطفاً دوباره تلاش کنید یا با پشتیبانی فروشگاه تماس بگیرید.', 'bank-mellat-gateway' ),
				'desc_tip'    => false,
			),
			'cancelled_message' => array(
				'title'       => __( 'پیام انصراف از پرداخت', 'bank-mellat-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'این پیام وقتی نمایش داده می‌شود که مشتری خودش در صفحه بانک از پرداخت انصراف داده باشد.', 'bank-mellat-gateway' ),
				'default'     => __( 'پرداخت لغو شد. سفارش شما ثبت شده و در صورت تمایل می‌توانید دوباره اقدام به پرداخت کنید.', 'bank-mellat-gateway' ),
				'desc_tip'    => false,
			),
			'limits'           => array(
				'title' => __( 'محدودیت مبلغ (اختیاری)', 'bank-mellat-gateway' ),
				'type'  => 'title',
			),
			'min_amount'       => array(
				'title'       => __( 'حداقل مبلغ سفارش', 'bank-mellat-gateway' ),
				'type'        => 'text',
				'description' => __( 'به واحد پول فروشگاه. اگر مبلغ سبد خرید کمتر از این مقدار باشد، این روش پرداخت نمایش داده نمی‌شود. خالی = بدون محدودیت.', 'bank-mellat-gateway' ),
				'default'     => '',
				'desc_tip'    => true,
				'custom_attributes' => array(
					'inputmode' => 'decimal',
				),
			),
			'max_amount'       => array(
				'title'       => __( 'حداکثر مبلغ سفارش', 'bank-mellat-gateway' ),
				'type'        => 'text',
				'description' => __( 'به واحد پول فروشگاه. اگر مبلغ سبد خرید بیشتر از این مقدار باشد، این روش پرداخت نمایش داده نمی‌شود. خالی = بدون محدودیت.', 'bank-mellat-gateway' ),
				'default'     => '',
				'desc_tip'    => true,
				'custom_attributes' => array(
					'inputmode' => 'decimal',
				),
			),
			'advanced'         => array(
				'title' => __( 'تنظیمات پیشرفته', 'bank-mellat-gateway' ),
				'type'  => 'title',
			),
			'convert_to_rial'  => array(
				'title'       => __( 'تبدیل واحد پول به ریال', 'bank-mellat-gateway' ),
				'type'        => 'checkbox',
				'label'       => __( 'مبلغ سفارش (تومان) قبل از ارسال به بانک در ۱۰ ضرب شود', 'bank-mellat-gateway' ),
				'description' => __( 'بانک ملت مبلغ را به ریال دریافت می‌کند. اگر واحد پول فروشگاه شما تومان است این گزینه را فعال نگه دارید؛ اگر واحد پول از قبل ریال است، غیرفعال کنید.', 'bank-mellat-gateway' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'debug'            => array(
				'title'       => __( 'حالت اشکال‌زدایی', 'bank-mellat-gateway' ),
				'type'        => 'checkbox',
				'label'       => __( 'ثبت رویدادهای تراکنش در لاگ ووکامرس', 'bank-mellat-gateway' ),
				'description' => __( 'رمز عبور هرگز در لاگ ثبت نمی‌شود.', 'bank-mellat-gateway' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
		);
	}

	public function is_available() {
		if ( 'yes' !== $this->enabled ) {
			return false;
		}

		if ( '' === $this->get_option( 'terminal_id' ) || '' === $this->get_option( 'username' ) || '' === $this->get_option( 'password' ) ) {
			return false;
		}

		$total = null;

		if ( WC()->cart && ! WC()->cart->is_empty() ) {
			$total = (float) WC()->cart->get_total( 'edit' );
		}

		if ( null !== $total ) {
			$min = $this->get_option( 'min_amount' );
			$max = $this->get_option( 'max_amount' );

			if ( '' !== $min && $total < (float) $min ) {
				return false;
			}

			if ( '' !== $max && $total > (float) $max ) {
				return false;
			}
		}

		return parent::is_available();
	}

	private function get_api() {
		return new BMG_API(
			$this->get_option( 'terminal_id' ),
			$this->get_option( 'username' ),
			$this->get_option( 'password' )
		);
	}

	private function log( $message, $level = 'info' ) {
		if ( ! $this->debug || ! function_exists( 'wc_get_logger' ) ) {
			return;
		}
		wc_get_logger()->log( $level, $message, array( 'source' => 'bank-mellat-gateway' ) );
	}

	private function to_rial( $amount ) {
		$amount = (float) $amount;
		if ( $this->convert_to_rial ) {
			$amount *= 10;
		}
		return (int) round( $amount );
	}

	/**
	 * Replaces {order_id}, {tracking_code}, {ref_id}, {sale_reference_id}, {card_pan},
	 * {amount}, {error_code} and {error_message} placeholders in an admin-configured
	 * message template.
	 */
	private function render_message( $template, $order, $extra = array() ) {
		$replacements = array(
			'{order_id}'          => $order->get_order_number(),
			'{tracking_code}'     => $order->get_transaction_id(),
			'{ref_id}'            => $order->get_meta( '_bmg_ref_id' ),
			'{sale_reference_id}' => $order->get_meta( '_bmg_sale_reference_id' ),
			'{card_pan}'          => $order->get_meta( '_bmg_card_pan' ),
			'{amount}'            => wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ),
			'{error_code}'        => isset( $extra['error_code'] ) ? $extra['error_code'] : '',
			'{error_message}'     => isset( $extra['error_message'] ) ? $extra['error_message'] : '',
		);

		return strtr( (string) $template, $replacements );
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wc_add_notice( __( 'سفارش یافت نشد.', 'bank-mellat-gateway' ), 'error' );
			return array( 'result' => 'fail' );
		}

		$amount_rial = $this->to_rial( $order->get_total() );

		if ( $amount_rial < 1000 ) {
			wc_add_notice( __( 'مبلغ سفارش برای پرداخت از طریق بانک ملت معتبر نیست.', 'bank-mellat-gateway' ), 'error' );
			return array( 'result' => 'fail' );
		}

		$callback_url = WC()->api_request_url( 'WC_Gateway_Bank_Mellat' );

		try {
			$api    = $this->get_api();
			$result = $api->request_payment( $order->get_id(), $amount_rial, $callback_url, 0, $order->get_order_number() );
		} catch ( Exception $e ) {
			$this->log( 'PayRequest exception for order ' . $order->get_id() . ': ' . $e->getMessage(), 'error' );
			wc_add_notice( __( 'خطا در برقراری ارتباط با درگاه بانک ملت. لطفاً چند لحظه دیگر مجدداً تلاش کنید.', 'bank-mellat-gateway' ), 'error' );
			return array( 'result' => 'fail' );
		}

		if ( '0' !== $result['res_code'] || empty( $result['ref_id'] ) ) {
			$this->log( 'PayRequest failed for order ' . $order->get_id() . ' with code ' . $result['res_code'] . '. Raw response: ' . $api->get_last_raw_response(), 'error' );
			$order->add_order_note( sprintf( __( 'درخواست پرداخت بانک ملت ناموفق بود. کد: %s', 'bank-mellat-gateway' ), $result['res_code'] ) );
			$this->add_failed_notice( $order, $result['res_code'] );
			return array( 'result' => 'fail' );
		}

		$order->update_meta_data( '_bmg_ref_id', sanitize_text_field( $result['ref_id'] ) );
		$order->save();

		$order->update_status( 'pending', __( 'در انتظار بازگشت از درگاه پرداخت بانک ملت.', 'bank-mellat-gateway' ) );

		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		);
	}

	/**
	 * Renders an auto-submitting form that POSTs the payer to the bank's gateway,
	 * as required by the Behpardakht Mellat protocol.
	 */
	public function receipt_page( $order_id ) {
		$order  = wc_get_order( $order_id );
		$ref_id = $order ? $order->get_meta( '_bmg_ref_id' ) : '';

		if ( empty( $ref_id ) ) {
			echo '<p>' . esc_html__( 'اطلاعات پرداخت این سفارش یافت نشد. لطفاً دوباره تلاش کنید.', 'bank-mellat-gateway' ) . '</p>';
			return;
		}

		if ( 'yes' === $this->get_option( 'show_invoice_preview' ) ) {
			$this->render_invoice_preview( $order );
			$this->render_redirect_form( $ref_id, false );
			return;
		}

		$this->render_redirect_form( $ref_id, true );
	}

	private function render_invoice_preview( $order ) {
		echo '<div class="bmg-invoice-preview" style="max-width:560px;margin:0 auto 16px;">';
		echo '<h3>' . esc_html__( 'پیش‌فاکتور سفارش', 'bank-mellat-gateway' ) . '</h3>';
		echo '<table class="shop_table" style="width:100%;border-collapse:collapse;">';
		echo '<thead><tr>';
		echo '<th style="text-align:start;padding:6px;border-bottom:1px solid #ddd;">' . esc_html__( 'محصول', 'bank-mellat-gateway' ) . '</th>';
		echo '<th style="text-align:end;padding:6px;border-bottom:1px solid #ddd;">' . esc_html__( 'جمع', 'bank-mellat-gateway' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $order->get_items() as $item ) {
			echo '<tr>';
			echo '<td style="padding:6px;border-bottom:1px solid #eee;">' . esc_html( $item->get_name() ) . ' &times; ' . (int) $item->get_quantity() . '</td>';
			echo '<td style="text-align:end;padding:6px;border-bottom:1px solid #eee;">' . wp_kses_post( wc_price( $order->get_line_total( $item, false, false ), array( 'currency' => $order->get_currency() ) ) ) . '</td>';
			echo '</tr>';
		}

		echo '<tr>';
		echo '<td style="padding:6px;font-weight:bold;">' . esc_html__( 'مبلغ قابل پرداخت', 'bank-mellat-gateway' ) . '</td>';
		echo '<td style="text-align:end;padding:6px;font-weight:bold;">' . wp_kses_post( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ) . '</td>';
		echo '</tr>';

		echo '</tbody></table></div>';
	}

	private function render_redirect_form( $ref_id, $auto_submit ) {
		if ( $auto_submit ) {
			echo '<p>' . esc_html__( 'در حال انتقال به درگاه پرداخت بانک ملت...', 'bank-mellat-gateway' ) . '</p>';
		}
		?>
		<form action="<?php echo esc_url( BMG_API::STARTPAY_URL ); ?>" method="post" id="bmg-mellat-redirect-form" style="text-align:center;">
			<input type="hidden" name="RefId" value="<?php echo esc_attr( $ref_id ); ?>" />
			<?php if ( $auto_submit ) : ?>
				<noscript>
					<button type="submit"><?php esc_html_e( 'ادامه پرداخت', 'bank-mellat-gateway' ); ?></button>
				</noscript>
			<?php else : ?>
				<button type="submit" class="button alt"><?php esc_html_e( 'پرداخت و انتقال به درگاه بانک ملت', 'bank-mellat-gateway' ); ?></button>
			<?php endif; ?>
		</form>
		<?php if ( $auto_submit ) : ?>
			<script type="text/javascript">
				document.getElementById( 'bmg-mellat-redirect-form' ).submit();
			</script>
		<?php endif; ?>
		<?php
	}

	/**
	 * Shows the admin-configured success message on the WooCommerce order-received page.
	 */
	public function thankyou_page( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order || ! $order->is_paid() ) {
			return;
		}

		$message = $this->render_message( $this->get_option( 'success_message' ), $order );

		if ( '' === trim( wp_strip_all_tags( $message ) ) ) {
			return;
		}

		echo '<div class="woocommerce-message bmg-success-message" role="alert">' . wp_kses_post( wpautop( $message ) ) . '</div>';
	}

	/**
	 * Handles the bank's server-side POST-back after the payer finishes on the bank's page.
	 */
	public function handle_callback() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- bank-initiated callback, verified via stored RefId below.
		$ref_id            = isset( $_POST['RefId'] ) ? sanitize_text_field( wp_unslash( $_POST['RefId'] ) ) : '';
		$res_code          = isset( $_POST['ResCode'] ) ? sanitize_text_field( wp_unslash( $_POST['ResCode'] ) ) : '';
		$sale_order_id     = isset( $_POST['SaleOrderId'] ) ? absint( $_POST['SaleOrderId'] ) : 0;
		$sale_reference_id = isset( $_POST['SaleReferenceId'] ) ? sanitize_text_field( wp_unslash( $_POST['SaleReferenceId'] ) ) : '';
		$card_pan          = isset( $_POST['CardHolderPan'] ) ? sanitize_text_field( wp_unslash( $_POST['CardHolderPan'] ) ) : '';
		// phpcs:enable

		if ( empty( $ref_id ) || $sale_order_id <= 0 ) {
			wp_die( esc_html__( 'اطلاعات بازگشتی از بانک ملت نامعتبر است.', 'bank-mellat-gateway' ), '', array( 'response' => 400 ) );
		}

		$order = wc_get_order( $sale_order_id );

		if ( ! $order || $order->get_payment_method() !== $this->id ) {
			wp_die( esc_html__( 'سفارش مرتبط با این تراکنش یافت نشد.', 'bank-mellat-gateway' ), '', array( 'response' => 404 ) );
		}

		$stored_ref_id = $order->get_meta( '_bmg_ref_id' );

		if ( empty( $stored_ref_id ) || ! hash_equals( (string) $stored_ref_id, $ref_id ) ) {
			$this->log( 'RefId mismatch on callback for order ' . $sale_order_id, 'error' );
			wp_die( esc_html__( 'اعتبارسنجی تراکنش ناموفق بود.', 'bank-mellat-gateway' ), '', array( 'response' => 400 ) );
		}

		if ( ! empty( $sale_reference_id ) ) {
			$order->update_meta_data( '_bmg_sale_reference_id', $sale_reference_id );
		}
		if ( ! empty( $card_pan ) ) {
			$order->update_meta_data( '_bmg_card_pan', $card_pan );
		}
		if ( ! empty( $sale_reference_id ) || ! empty( $card_pan ) ) {
			$order->save();
		}

		// Idempotency: never re-process an order that is already paid.
		if ( $order->is_paid() ) {
			wp_safe_redirect( $this->get_return_url( $order ) );
			exit;
		}

		if ( '0' !== $res_code ) {
			$status_note = ( '17' === $res_code )
				? __( 'مشتری از پرداخت بانک ملت انصراف داد.', 'bank-mellat-gateway' )
				: sprintf( __( 'پرداخت بانک ملت ناموفق بود. کد: %s', 'bank-mellat-gateway' ), $res_code );
			$order->update_status( 'failed', $status_note );
			$this->add_failed_notice( $order, $res_code );
			wp_safe_redirect( $order->get_checkout_payment_url( false ) );
			exit;
		}

		try {
			$api         = $this->get_api();
			$verify_code = $api->verify( $sale_order_id, $sale_order_id, $sale_reference_id );
		} catch ( Exception $e ) {
			$this->log( 'Verify exception for order ' . $sale_order_id . ': ' . $e->getMessage(), 'error' );
			$order->add_order_note( __( 'خطا در ارتباط با بانک ملت هنگام تایید تراکنش.', 'bank-mellat-gateway' ) );
			wp_safe_redirect( $order->get_checkout_payment_url( false ) );
			exit;
		}

		if ( '0' !== $verify_code ) {
			$this->log( 'Verify failed for order ' . $sale_order_id . ' with code ' . $verify_code . '. Raw response: ' . $api->get_last_raw_response(), 'error' );
			$order->update_status( 'failed', sprintf( __( 'تایید تراکنش بانک ملت ناموفق بود. کد: %s', 'bank-mellat-gateway' ), $verify_code ) );
			$this->add_failed_notice( $order, $verify_code );
			wp_safe_redirect( $order->get_checkout_payment_url( false ) );
			exit;
		}

		try {
			$settle_code = $api->settle( $sale_order_id, $sale_order_id, $sale_reference_id );
		} catch ( Exception $e ) {
			$this->log( 'Settle exception for order ' . $sale_order_id . ': ' . $e->getMessage(), 'error' );
			$settle_code = '';
		}

		// "0" success, "45" already settled — both are a completed payment.
		if ( in_array( $settle_code, array( '0', '45' ), true ) ) {
			$order->payment_complete( sanitize_text_field( $sale_reference_id ) );

			$note = $this->render_message( $this->get_option( 'success_message' ), $order );
			$order->add_order_note( wp_strip_all_tags( $note ) );

			if ( function_exists( 'WC' ) && WC()->cart ) {
				WC()->cart->empty_cart();
			}

			wp_safe_redirect( $this->get_return_url( $order ) );
			exit;
		}

		$this->log( 'Settle failed for order ' . $sale_order_id . ' with code ' . $settle_code . '. Raw response: ' . $api->get_last_raw_response() . '. Attempting reversal.', 'error' );

		try {
			$api->reverse( $sale_order_id, $sale_order_id, $sale_reference_id );
		} catch ( Exception $e ) {
			$this->log( 'Reversal exception for order ' . $sale_order_id . ': ' . $e->getMessage(), 'error' );
		}

		$order->update_status( 'failed', sprintf( __( 'نهایی‌سازی (Settle) تراکنش بانک ملت ناموفق بود. کد: %s', 'bank-mellat-gateway' ), $settle_code ) );
		$this->add_failed_notice( $order, $settle_code );
		wp_safe_redirect( $order->get_checkout_payment_url( false ) );
		exit;
	}

	private function add_failed_notice( $order, $code ) {
		$template_key = ( '17' === (string) $code ) ? 'cancelled_message' : 'failed_message';

		$message = $this->render_message(
			$this->get_option( $template_key ),
			$order,
			array(
				'error_code'    => $code,
				'error_message' => BMG_API::get_error_message( $code ),
			)
		);
		wc_add_notice( wp_kses_post( $message ), 'error' );
	}

	/**
	 * Refunds via the WooCommerce order screen.
	 *
	 * Bank Mellat's reversal webservice only cancels a transaction's FULL original
	 * amount, and only before that day's settlement batch closes it out — there is
	 * no partial-refund or post-settlement-refund API. So: a full-amount request is
	 * attempted through the bank automatically; anything else is rejected with an
	 * explanation rather than silently pretending to succeed.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return new WP_Error( 'bmg_refund_error', __( 'سفارش یافت نشد.', 'bank-mellat-gateway' ) );
		}

		$sale_reference_id = $order->get_meta( '_bmg_sale_reference_id' );
		if ( empty( $sale_reference_id ) ) {
			$sale_reference_id = $order->get_transaction_id();
		}

		if ( empty( $sale_reference_id ) ) {
			return new WP_Error( 'bmg_refund_error', __( 'اطلاعات تراکنش بانک برای این سفارش ثبت نشده و امکان استرداد خودکار وجود ندارد.', 'bank-mellat-gateway' ) );
		}

		$order_total   = (float) $order->get_total();
		$refund_amount = null === $amount ? $order_total : (float) $amount;

		if ( abs( $refund_amount - $order_total ) > 0.01 ) {
			return new WP_Error(
				'bmg_refund_error',
				__( 'استرداد جزئی توسط وب‌سرویس بانک ملت پشتیبانی نمی‌شود (این وب‌سرویس فقط استرداد کامل مبلغ تراکنش را قبول می‌کند). برای استرداد مبلغ جزئی باید از پنل بانک یا روش دیگری اقدام کنید.', 'bank-mellat-gateway' )
			);
		}

		try {
			$api  = $this->get_api();
			$code = $api->reverse( $order->get_id(), $order->get_id(), $sale_reference_id );
		} catch ( Exception $e ) {
			$this->log( 'Refund/reversal exception for order ' . $order->get_id() . ': ' . $e->getMessage(), 'error' );
			return new WP_Error( 'bmg_refund_error', __( 'خطا در ارتباط با بانک ملت هنگام استرداد. لطفاً بعداً دوباره تلاش کنید یا استرداد را از پنل بانک انجام دهید.', 'bank-mellat-gateway' ) );
		}

		if ( '0' === $code ) {
			$order->add_order_note(
				$reason
					? sprintf( __( 'استرداد کامل مبلغ سفارش از طریق بانک ملت با موفقیت انجام شد. دلیل: %s', 'bank-mellat-gateway' ), $reason )
					: __( 'استرداد کامل مبلغ سفارش از طریق بانک ملت با موفقیت انجام شد.', 'bank-mellat-gateway' )
			);
			return true;
		}

		$this->log( 'Refund/reversal failed for order ' . $order->get_id() . ' with code ' . $code . '. Raw response: ' . $api->get_last_raw_response(), 'error' );

		return new WP_Error(
			'bmg_refund_error',
			sprintf(
				/* translators: 1: bank result code, 2: description of that code */
				__( 'استرداد خودکار ناموفق بود (کد %1$s: %2$s). این معمولاً یعنی تراکنش قبلاً نهایی (Settle) شده است؛ برای استرداد باید از پنل مدیریت بانک ملت اقدام کنید.', 'bank-mellat-gateway' ),
				$code,
				BMG_API::get_error_message( $code )
			)
		);
	}

	/**
	 * Adds a manual "check transaction status" action to the order edit screen,
	 * for support/troubleshooting without altering the order.
	 */
	public function add_order_actions( $actions ) {
		global $theorder;

		if ( $theorder instanceof WC_Order && $theorder->get_payment_method() === $this->id && $theorder->get_meta( '_bmg_ref_id' ) ) {
			$actions['bmg_inquiry'] = __( 'استعلام وضعیت تراکنش از بانک ملت', 'bank-mellat-gateway' );
		}

		return $actions;
	}

	public function order_action_inquiry( $order ) {
		$sale_reference_id = $order->get_meta( '_bmg_sale_reference_id' );
		if ( empty( $sale_reference_id ) ) {
			$sale_reference_id = $order->get_transaction_id();
		}

		if ( empty( $sale_reference_id ) ) {
			$order->add_order_note( __( 'امکان استعلام وجود ندارد: شماره پیگیری بانک برای این سفارش ثبت نشده است.', 'bank-mellat-gateway' ) );
			return;
		}

		try {
			$api  = $this->get_api();
			$code = $api->inquiry( $order->get_id(), $order->get_id(), $sale_reference_id );
		} catch ( Exception $e ) {
			$this->log( 'Inquiry exception for order ' . $order->get_id() . ': ' . $e->getMessage(), 'error' );
			$order->add_order_note( __( 'خطا در ارتباط با بانک ملت هنگام استعلام وضعیت تراکنش.', 'bank-mellat-gateway' ) );
			return;
		}

		$order->add_order_note( sprintf( __( 'نتیجه استعلام تراکنش از بانک ملت (کد %1$s): %2$s', 'bank-mellat-gateway' ), $code, BMG_API::get_error_message( $code ) ) );
	}

	/**
	 * Shows the stored bank transaction details, and a shareable payment link + QR
	 * code for unpaid orders, on the admin order edit screen.
	 */
	public function display_order_meta_box( $order ) {
		if ( ! $order instanceof WC_Order || $order->get_payment_method() !== $this->id ) {
			return;
		}

		$ref_id   = $order->get_meta( '_bmg_ref_id' );
		$sale_ref = $order->get_meta( '_bmg_sale_reference_id' );
		$card_pan = $order->get_meta( '_bmg_card_pan' );

		if ( ! empty( $ref_id ) || ! empty( $sale_ref ) || ! empty( $card_pan ) ) {
			echo '<div class="bmg-order-meta" style="clear:both;padding-top:10px;margin-top:10px;border-top:1px solid #eee;">';
			echo '<h4>' . esc_html__( 'اطلاعات تراکنش بانک ملت', 'bank-mellat-gateway' ) . '</h4>';

			if ( ! empty( $ref_id ) ) {
				echo '<p><strong>' . esc_html__( 'شماره مرجع (RefId):', 'bank-mellat-gateway' ) . '</strong> ' . esc_html( $ref_id ) . '</p>';
			}
			if ( ! empty( $sale_ref ) ) {
				echo '<p><strong>' . esc_html__( 'شماره پیگیری (SaleReferenceId):', 'bank-mellat-gateway' ) . '</strong> ' . esc_html( $sale_ref ) . '</p>';
			}
			if ( ! empty( $card_pan ) ) {
				echo '<p><strong>' . esc_html__( 'شماره کارت:', 'bank-mellat-gateway' ) . '</strong> ' . esc_html( $card_pan ) . '</p>';
			}

			echo '</div>';
		}

		if ( $order->needs_payment() ) {
			$pay_url = $order->get_checkout_payment_url();

			echo '<div class="bmg-pay-link" style="clear:both;padding-top:10px;margin-top:10px;border-top:1px solid #eee;">';
			echo '<h4>' . esc_html__( 'لینک پرداخت اختصاصی', 'bank-mellat-gateway' ) . '</h4>';
			echo '<p style="color:#666;">' . esc_html__( 'این لینک را می‌توانید برای مشتری (مثلاً در اینستاگرام یا پیامک) ارسال کنید تا مستقیماً این سفارش را از طریق بانک ملت پرداخت کند.', 'bank-mellat-gateway' ) . '</p>';
			echo '<input type="text" readonly value="' . esc_attr( $pay_url ) . '" onclick="this.select();" style="width:100%;margin-bottom:8px;" />';
			echo '<div id="bmg-qr-canvas" data-url="' . esc_attr( $pay_url ) . '" style="margin-bottom:8px;"></div>';
			echo '<button type="button" class="button" id="bmg-qr-download">' . esc_html__( 'دانلود QR (مناسب اینستاگرام)', 'bank-mellat-gateway' ) . '</button>';
			echo '</div>';
		}
	}

	/**
	 * Renders the settings form and, right below it, a read-only viewer for this
	 * gateway's own debug log so troubleshooting doesn't require leaving the page.
	 */
	public function admin_options() {
		parent::admin_options();
		$this->render_log_viewer();
	}

	/**
	 * Finds this gateway's WooCommerce log files (newest first).
	 */
	private function get_log_files() {
		if ( ! defined( 'WC_LOG_DIR' ) || ! is_dir( WC_LOG_DIR ) ) {
			return array();
		}

		$files = glob( trailingslashit( WC_LOG_DIR ) . 'bank-mellat-gateway-*.log' );

		if ( empty( $files ) ) {
			return array();
		}

		usort(
			$files,
			function ( $a, $b ) {
				return filemtime( $b ) - filemtime( $a );
			}
		);

		return $files;
	}

	/**
	 * Reads up to $max_lines from the end of a file without loading huge files entirely into memory.
	 */
	private function tail_file( $path, $max_lines = 300, $max_bytes = 262144 ) {
		if ( ! is_readable( $path ) ) {
			return '';
		}

		$size   = filesize( $path );
		$handle = fopen( $path, 'r' );

		if ( ! $handle ) {
			return '';
		}

		$read_bytes = min( $size, $max_bytes );
		fseek( $handle, -$read_bytes, SEEK_END );
		$data = fread( $handle, $read_bytes );
		fclose( $handle );

		$lines = explode( "\n", $data );
		$lines = array_slice( $lines, -$max_lines );

		return implode( "\n", $lines );
	}

	private function render_log_viewer() {
		echo '<h3>' . esc_html__( 'گزارش رویدادهای درگاه بانک ملت', 'bank-mellat-gateway' ) . '</h3>';

		if ( ! $this->debug ) {
			echo '<p>' . esc_html__( 'برای مشاهده گزارش تراکنش‌ها و خطاها در همین صفحه، ابتدا گزینه «حالت اشکال‌زدایی» را در بالا فعال و ذخیره کنید، سپس یک بار پرداخت را تست کنید.', 'bank-mellat-gateway' ) . '</p>';
			return;
		}

		$files = $this->get_log_files();

		if ( empty( $files ) ) {
			echo '<p>' . esc_html__( 'هنوز هیچ رویدادی ثبت نشده است.', 'bank-mellat-gateway' ) . '</p>';
			return;
		}

		$content = $this->tail_file( $files[0] );

		echo '<p>' . sprintf(
			/* translators: %s: log file name */
			esc_html__( 'آخرین رویدادها (فایل: %s). برای دیدن رویدادهای جدید، این صفحه را رفرش کنید.', 'bank-mellat-gateway' ),
			esc_html( basename( $files[0] ) )
		) . '</p>';

		echo '<textarea readonly rows="18" dir="ltr" style="width:100%;max-width:100%;font-family:Consolas,Menlo,monospace;font-size:12px;direction:ltr;text-align:left;white-space:pre;">' . esc_textarea( $content ) . '</textarea>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:8px;">';
		wp_nonce_field( 'bmg_clear_log' );
		echo '<input type="hidden" name="action" value="bmg_clear_log" />';
		echo '<button type="submit" class="button" onclick="return confirm(\'' . esc_js( __( 'همه فایل‌های گزارش این درگاه پاک شوند؟', 'bank-mellat-gateway' ) ) . '\');">' . esc_html__( 'پاک کردن گزارش', 'bank-mellat-gateway' ) . '</button>';
		echo '</form>';
	}

	public function handle_clear_log() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'شما اجازه دسترسی به این بخش را ندارید.', 'bank-mellat-gateway' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'bmg_clear_log' );

		foreach ( $this->get_log_files() as $file ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- best-effort cleanup, missing/locked files are not fatal.
			@unlink( $file );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $this->id ) );
		exit;
	}
}
