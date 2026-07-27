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
		$this->supports            = array( 'products' );

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
			'messages'         => array(
				'title'       => __( 'پیام‌های سفارشی', 'bank-mellat-gateway' ),
				'type'        => 'title',
				'description' => __( 'می‌توانید در این پیام‌ها از متغیرهای زیر استفاده کنید: {order_id} (شماره سفارش)، {tracking_code} (شماره پیگیری بانک)، {error_code} (کد خطا)، {error_message} (توضیح خطا).', 'bank-mellat-gateway' ),
			),
			'success_message'  => array(
				'title'       => __( 'پیام پرداخت موفق', 'bank-mellat-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'این پیام در صفحه تشکر (بعد از پرداخت موفق) به مشتری نمایش داده و به‌عنوان یادداشت سفارش نیز ثبت می‌شود.', 'bank-mellat-gateway' ),
				'default'     => __( 'پرداخت شما با موفقیت انجام شد. شماره پیگیری تراکنش: {tracking_code}', 'bank-mellat-gateway' ),
				'desc_tip'    => false,
			),
			'failed_message'   => array(
				'title'       => __( 'پیام پرداخت ناموفق', 'bank-mellat-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'این پیام هنگام ناموفق بودن یا انصراف از پرداخت به مشتری نمایش داده می‌شود.', 'bank-mellat-gateway' ),
				'default'     => __( 'پرداخت شما انجام نشد. {error_message} لطفاً دوباره تلاش کنید یا با پشتیبانی فروشگاه تماس بگیرید.', 'bank-mellat-gateway' ),
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
	 * Replaces {order_id}, {tracking_code}, {error_code} and {error_message} placeholders
	 * in an admin-configured message template.
	 */
	private function render_message( $template, $order, $extra = array() ) {
		$replacements = array(
			'{order_id}'       => $order->get_order_number(),
			'{tracking_code}'  => $order->get_transaction_id(),
			'{error_code}'     => isset( $extra['error_code'] ) ? $extra['error_code'] : '',
			'{error_message}'  => isset( $extra['error_message'] ) ? $extra['error_message'] : '',
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
			$this->log( 'PayRequest failed for order ' . $order->get_id() . ' with code ' . $result['res_code'], 'error' );
			$order->add_order_note( sprintf( __( 'درخواست پرداخت بانک ملت ناموفق بود. کد: %s', 'bank-mellat-gateway' ), $result['res_code'] ) );

			$message = $this->render_message(
				$this->get_option( 'failed_message' ),
				$order,
				array(
					'error_code'    => $result['res_code'],
					'error_message' => BMG_API::get_error_message( $result['res_code'] ),
				)
			);
			wc_add_notice( wp_kses_post( $message ), 'error' );
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

		echo '<p>' . esc_html__( 'در حال انتقال به درگاه پرداخت بانک ملت...', 'bank-mellat-gateway' ) . '</p>';
		?>
		<form action="<?php echo esc_url( BMG_API::STARTPAY_URL ); ?>" method="post" id="bmg-mellat-redirect-form">
			<input type="hidden" name="RefId" value="<?php echo esc_attr( $ref_id ); ?>" />
			<noscript>
				<button type="submit"><?php esc_html_e( 'ادامه پرداخت', 'bank-mellat-gateway' ); ?></button>
			</noscript>
		</form>
		<script type="text/javascript">
			document.getElementById( 'bmg-mellat-redirect-form' ).submit();
		</script>
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
			$order->update_status( 'failed', sprintf( __( 'پرداخت بانک ملت ناموفق بود. کد: %s', 'bank-mellat-gateway' ), $res_code ) );
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
			$this->log( 'Verify failed for order ' . $sale_order_id . ' with code ' . $verify_code, 'error' );
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

		$this->log( 'Settle failed for order ' . $sale_order_id . ' with code ' . $settle_code . '; attempting reversal.', 'error' );

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
		$message = $this->render_message(
			$this->get_option( 'failed_message' ),
			$order,
			array(
				'error_code'    => $code,
				'error_message' => BMG_API::get_error_message( $code ),
			)
		);
		wc_add_notice( wp_kses_post( $message ), 'error' );
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
	 * Shows the stored bank transaction details on the admin order edit screen.
	 */
	public function display_order_meta_box( $order ) {
		if ( ! $order instanceof WC_Order || $order->get_payment_method() !== $this->id ) {
			return;
		}

		$ref_id   = $order->get_meta( '_bmg_ref_id' );
		$sale_ref = $order->get_meta( '_bmg_sale_reference_id' );
		$card_pan = $order->get_meta( '_bmg_card_pan' );

		if ( empty( $ref_id ) && empty( $sale_ref ) && empty( $card_pan ) ) {
			return;
		}

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
}
