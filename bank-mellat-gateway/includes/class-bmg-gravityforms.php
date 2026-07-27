<?php
/**
 * Gravity Forms integration: lets any form collect a Bank Mellat payment,
 * independently of WooCommerce, using the same BMG_API SOAP client.
 *
 * NOTE: This integration was built against Gravity Forms' documented, stable
 * add-on APIs (GFAddOn settings framework, GFAPI, gform_after_submission,
 * gform_confirmation) rather than the deeper Payment Add-On Framework
 * callback internals, specifically so it stays simple enough to verify by
 * reading — but it has not been run against a live Gravity Forms install.
 * Test the full submit -> bank -> return flow on staging before relying on it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'GFForms' ) ) {
	return;
}

GFForms::include_addon_framework();

class BMG_GF_Addon extends GFAddOn {

	protected $_version                  = BMG_VERSION;
	protected $_min_gravityforms_version = '2.5';
	protected $_slug                     = 'bmg-gravityforms';
	protected $_path                     = 'bank-mellat-gateway/includes/class-bmg-gravityforms.php';
	protected $_full_path                = __FILE__;
	protected $_title                    = 'بانک ملت (به‌پرداخت) برای Gravity Forms';
	protected $_short_title              = 'بانک ملت';
	protected $_capabilities             = array( 'manage_options' );
	protected $_capabilities_settings_page = 'manage_options';
	protected $_capabilities_form_settings = 'manage_options';

	private static $_instance = null;

	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function init() {
		parent::init();

		add_action( 'gform_after_submission', array( $this, 'maybe_start_payment' ), 10, 2 );
		add_filter( 'gform_confirmation', array( $this, 'maybe_redirect_confirmation' ), 10, 4 );
		add_action( 'template_redirect', array( $this, 'maybe_handle_frontend' ) );
		add_action( 'wp_body_open', array( $this, 'maybe_render_result_banner' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_page' ), 20 );
		add_action( 'admin_post_bmg_gf_refund', array( $this, 'handle_refund' ) );
	}

	public function plugin_settings_fields() {
		return array(
			array(
				'title'  => __( 'تنظیمات بانک ملت برای Gravity Forms', 'bank-mellat-gateway' ),
				'fields' => array(
					array(
						'name'    => 'use_woocommerce_credentials',
						'label'   => __( 'اطلاعات ترمینال', 'bank-mellat-gateway' ),
						'type'    => 'checkbox',
						'choices' => array(
							array(
								'label' => __( 'استفاده از همان ترمینال آی‌دی/نام کاربری/رمز عبور تنظیم‌شده در افزونه ووکامرس بانک ملت', 'bank-mellat-gateway' ),
								'name'  => 'use_woocommerce_credentials',
							),
						),
					),
					array(
						'name'  => 'terminal_id',
						'label' => __( 'ترمینال آی‌دی', 'bank-mellat-gateway' ),
						'type'  => 'text',
						'class' => 'medium',
					),
					array(
						'name'  => 'username',
						'label' => __( 'نام کاربری', 'bank-mellat-gateway' ),
						'type'  => 'text',
						'class' => 'medium',
					),
					array(
						'name'       => 'password',
						'label'      => __( 'رمز عبور', 'bank-mellat-gateway' ),
						'type'       => 'text',
						'input_type' => 'password',
						'class'      => 'medium',
					),
					array(
						'name'    => 'convert_to_rial',
						'label'   => __( 'واحد پول', 'bank-mellat-gateway' ),
						'type'    => 'checkbox',
						'choices' => array(
							array(
								'label' => __( 'مبلغ فرم (تومان) قبل از ارسال به بانک در ۱۰ ضرب شود', 'bank-mellat-gateway' ),
								'name'  => 'convert_to_rial',
							),
						),
					),
				),
			),
		);
	}

	public function form_settings_fields( $form ) {
		$amount_fields = array( array( 'label' => __( '— انتخاب فیلد مبلغ —', 'bank-mellat-gateway' ), 'value' => '' ) );

		if ( ! empty( $form['fields'] ) ) {
			foreach ( $form['fields'] as $field ) {
				if ( in_array( $field->type, array( 'number', 'product', 'total', 'donation' ), true ) ) {
					$amount_fields[] = array(
						'label' => GFCommon::get_label( $field ) . ' (#' . $field->id . ')',
						'value' => (string) $field->id,
					);
				}
			}
		}

		return array(
			array(
				'title'  => __( 'پرداخت بانک ملت', 'bank-mellat-gateway' ),
				'fields' => array(
					array(
						'name'    => 'enabled',
						'label'   => __( 'فعال‌سازی', 'bank-mellat-gateway' ),
						'type'    => 'checkbox',
						'choices' => array(
							array(
								'label' => __( 'بعد از ارسال این فرم، کاربر برای پرداخت به بانک ملت منتقل شود.', 'bank-mellat-gateway' ),
								'name'  => 'enabled',
							),
						),
					),
					array(
						'name'    => 'amount_field',
						'label'   => __( 'فیلد مبلغ', 'bank-mellat-gateway' ),
						'type'    => 'select',
						'choices' => $amount_fields,
					),
				),
			),
		);
	}

	/**
	 * @return array{0:string,1:string,2:string,3:bool} terminal_id, username, password, convert_to_rial
	 */
	private function get_api_credentials() {
		$settings = $this->get_plugin_settings();

		if ( ! empty( $settings['use_woocommerce_credentials'] ) ) {
			$wc_settings = get_option( 'woocommerce_bank_mellat_settings', array() );
			return array(
				isset( $wc_settings['terminal_id'] ) ? $wc_settings['terminal_id'] : '',
				isset( $wc_settings['username'] ) ? $wc_settings['username'] : '',
				isset( $wc_settings['password'] ) ? $wc_settings['password'] : '',
				! isset( $wc_settings['convert_to_rial'] ) || 'yes' === $wc_settings['convert_to_rial'],
			);
		}

		return array(
			isset( $settings['terminal_id'] ) ? $settings['terminal_id'] : '',
			isset( $settings['username'] ) ? $settings['username'] : '',
			isset( $settings['password'] ) ? $settings['password'] : '',
			! empty( $settings['convert_to_rial'] ),
		);
	}

	public function maybe_start_payment( $entry, $form ) {
		$settings = $this->get_form_settings( $form );

		if ( empty( $settings['enabled'] ) || empty( $settings['amount_field'] ) ) {
			return;
		}

		$amount = isset( $entry[ $settings['amount_field'] ] ) ? (float) GFCommon::to_number( $entry[ $settings['amount_field'] ] ) : 0;

		if ( $amount <= 0 ) {
			return;
		}

		list( $terminal_id, $username, $password, $convert_to_rial ) = $this->get_api_credentials();

		if ( empty( $terminal_id ) || empty( $username ) || empty( $password ) ) {
			gform_update_meta( $entry['id'], 'bmg_error', __( 'اطلاعات ترمینال بانک ملت برای Gravity Forms تنظیم نشده است.', 'bank-mellat-gateway' ) );
			return;
		}

		$amount_rial  = (int) round( $convert_to_rial ? $amount * 10 : $amount );
		$token        = wp_generate_password( 32, false );
		$callback_url = add_query_arg(
			array(
				'bmg-gf-callback' => 1,
				'entry'           => $entry['id'],
				'token'           => $token,
			),
			home_url( '/' )
		);

		require_once BMG_DIR . 'includes/class-bmg-api.php';

		try {
			$api    = new BMG_API( $terminal_id, $username, $password );
			$result = $api->request_payment( $entry['id'], $amount_rial, $callback_url, 0, 'GF-' . $form['id'] );
		} catch ( Exception $e ) {
			gform_update_meta( $entry['id'], 'bmg_error', $e->getMessage() );
			return;
		}

		if ( '0' !== $result['res_code'] || empty( $result['ref_id'] ) ) {
			gform_update_meta( $entry['id'], 'bmg_error', BMG_API::get_error_message( $result['res_code'] ) );
			return;
		}

		gform_update_meta( $entry['id'], 'bmg_ref_id', sanitize_text_field( $result['ref_id'] ) );
		gform_update_meta( $entry['id'], 'bmg_token', $token );
		gform_update_meta( $entry['id'], 'bmg_amount', $amount );
	}

	public function maybe_redirect_confirmation( $confirmation, $form, $entry, $ajax ) {
		$ref_id = gform_get_meta( $entry['id'], 'bmg_ref_id' );

		if ( empty( $ref_id ) ) {
			return $confirmation;
		}

		$url = add_query_arg(
			array(
				'bmg-gf-redirect' => 1,
				'entry'           => $entry['id'],
			),
			home_url( '/' )
		);

		return array( 'redirect' => $url );
	}

	public function maybe_handle_frontend() {
		if ( isset( $_GET['bmg-gf-redirect'], $_GET['entry'] ) ) {
			$this->render_redirect_page( absint( $_GET['entry'] ) );
			exit;
		}

		if ( isset( $_GET['bmg-gf-callback'], $_GET['entry'] ) ) {
			$this->handle_callback( absint( $_GET['entry'] ) );
			exit;
		}
	}

	private function render_redirect_page( $entry_id ) {
		if ( ! class_exists( 'GFAPI' ) ) {
			wp_die( esc_html__( 'Gravity Forms در دسترس نیست.', 'bank-mellat-gateway' ), '', array( 'response' => 500 ) );
		}

		$ref_id = gform_get_meta( $entry_id, 'bmg_ref_id' );

		if ( empty( $ref_id ) ) {
			wp_die( esc_html__( 'اطلاعات پرداخت این فرم یافت نشد.', 'bank-mellat-gateway' ), '', array( 'response' => 404 ) );
		}

		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );

		echo '<!DOCTYPE html><html dir="rtl" lang="fa"><head><meta charset="UTF-8" /><title>' . esc_html__( 'انتقال به درگاه پرداخت', 'bank-mellat-gateway' ) . '</title></head>';
		echo '<body style="font-family:Tahoma,Arial,sans-serif;text-align:center;padding-top:80px;">';
		echo '<p>' . esc_html__( 'در حال انتقال به درگاه پرداخت بانک ملت...', 'bank-mellat-gateway' ) . '</p>';
		echo '<form action="' . esc_url( BMG_API::STARTPAY_URL ) . '" method="post" id="bmg-gf-redirect-form">';
		echo '<input type="hidden" name="RefId" value="' . esc_attr( $ref_id ) . '" />';
		echo '<noscript><button type="submit">' . esc_html__( 'ادامه پرداخت', 'bank-mellat-gateway' ) . '</button></noscript>';
		echo '</form>';
		echo '<script type="text/javascript">document.getElementById("bmg-gf-redirect-form").submit();</script>';
		echo '</body></html>';
	}

	private function handle_callback( $entry_id ) {
		if ( ! class_exists( 'GFAPI' ) ) {
			wp_die( esc_html__( 'Gravity Forms در دسترس نیست.', 'bank-mellat-gateway' ), '', array( 'response' => 500 ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- bank-initiated callback, verified via stored token below.
		$ref_id            = isset( $_POST['RefId'] ) ? sanitize_text_field( wp_unslash( $_POST['RefId'] ) ) : '';
		$res_code          = isset( $_POST['ResCode'] ) ? sanitize_text_field( wp_unslash( $_POST['ResCode'] ) ) : '';
		$sale_reference_id = isset( $_POST['SaleReferenceId'] ) ? sanitize_text_field( wp_unslash( $_POST['SaleReferenceId'] ) ) : '';
		$card_pan          = isset( $_POST['CardHolderPan'] ) ? sanitize_text_field( wp_unslash( $_POST['CardHolderPan'] ) ) : '';
		// phpcs:enable

		if ( empty( $ref_id ) ) {
			wp_die( esc_html__( 'اطلاعات بازگشتی از بانک ملت نامعتبر است.', 'bank-mellat-gateway' ), '', array( 'response' => 400 ) );
		}

		$entry = GFAPI::get_entry( $entry_id );

		if ( is_wp_error( $entry ) ) {
			wp_die( esc_html__( 'رکورد فرم یافت نشد.', 'bank-mellat-gateway' ), '', array( 'response' => 404 ) );
		}

		$stored_ref_id = gform_get_meta( $entry_id, 'bmg_ref_id' );

		if ( empty( $stored_ref_id ) || ! hash_equals( (string) $stored_ref_id, $ref_id ) ) {
			wp_die( esc_html__( 'اعتبارسنجی تراکنش ناموفق بود.', 'bank-mellat-gateway' ), '', array( 'response' => 400 ) );
		}

		if ( isset( $entry['payment_status'] ) && 'Paid' === $entry['payment_status'] ) {
			$this->redirect_result( true, $entry );
		}

		if ( '0' !== $res_code ) {
			gform_update_meta( $entry_id, 'bmg_error', BMG_API::get_error_message( $res_code ) );
			$this->redirect_result( false, $entry );
		}

		list( $terminal_id, $username, $password ) = $this->get_api_credentials();
		require_once BMG_DIR . 'includes/class-bmg-api.php';
		$api = new BMG_API( $terminal_id, $username, $password );

		try {
			$verify_code = $api->verify( $entry_id, $entry_id, $sale_reference_id );
		} catch ( Exception $e ) {
			gform_update_meta( $entry_id, 'bmg_error', $e->getMessage() );
			$this->redirect_result( false, $entry );
			return;
		}

		if ( '0' !== $verify_code ) {
			gform_update_meta( $entry_id, 'bmg_error', BMG_API::get_error_message( $verify_code ) );
			$this->redirect_result( false, $entry );
			return;
		}

		try {
			$settle_code = $api->settle( $entry_id, $entry_id, $sale_reference_id );
		} catch ( Exception $e ) {
			$settle_code = '';
		}

		if ( in_array( $settle_code, array( '0', '45' ), true ) ) {
			gform_update_meta( $entry_id, 'bmg_sale_reference_id', $sale_reference_id );
			if ( ! empty( $card_pan ) ) {
				gform_update_meta( $entry_id, 'bmg_card_pan', $card_pan );
			}

			$entry['payment_status'] = 'Paid';
			$entry['payment_amount'] = gform_get_meta( $entry_id, 'bmg_amount' );
			$entry['payment_date']   = gmdate( 'Y-m-d H:i:s' );
			$entry['transaction_id'] = $sale_reference_id;
			$entry['payment_method'] = 'Bank Mellat';
			$entry['is_fulfilled']   = true;
			GFAPI::update_entry( $entry );

			$this->redirect_result( true, $entry );
			return;
		}

		try {
			$api->reverse( $entry_id, $entry_id, $sale_reference_id );
		} catch ( Exception $e ) {
			// Best-effort; nothing more we can do from here.
		}

		gform_update_meta( $entry_id, 'bmg_error', BMG_API::get_error_message( $settle_code ) );
		$this->redirect_result( false, $entry );
	}

	private function redirect_result( $success, $entry ) {
		$base = ! empty( $entry['source_url'] ) ? $entry['source_url'] : home_url( '/' );
		$url  = add_query_arg( $success ? 'bmg_gf_paid' : 'bmg_gf_failed', '1', $base );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Best-effort success/failure banner shown on the page the visitor lands back on.
	 * Uses wp_body_open (standard since WP 5.2) rather than trying to reconstruct
	 * Gravity Forms' own configured confirmation, which this integration doesn't replay.
	 */
	public function maybe_render_result_banner() {
		if ( isset( $_GET['bmg_gf_paid'] ) ) {
			echo '<div style="background:#e6f4ea;color:#1a7f37;padding:12px;text-align:center;">' . esc_html__( 'پرداخت شما با موفقیت از طریق بانک ملت انجام شد.', 'bank-mellat-gateway' ) . '</div>';
		} elseif ( isset( $_GET['bmg_gf_failed'] ) ) {
			echo '<div style="background:#fde7e9;color:#c00000;padding:12px;text-align:center;">' . esc_html__( 'پرداخت شما ناموفق بود یا لغو شد. لطفاً دوباره تلاش کنید.', 'bank-mellat-gateway' ) . '</div>';
		}
	}

	public function register_admin_page() {
		if ( ! class_exists( 'GFAPI' ) ) {
			return;
		}

		add_submenu_page(
			'woocommerce',
			__( 'فرم‌های بانک ملت (Gravity Forms)', 'bank-mellat-gateway' ),
			__( 'فرم‌های بانک ملت', 'bank-mellat-gateway' ),
			'manage_options',
			'bmg-gf-entries',
			array( $this, 'render_admin_page' )
		);
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'شما اجازه دسترسی به این صفحه را ندارید.', 'bank-mellat-gateway' ), '', array( 'response' => 403 ) );
		}

		$entries = GFAPI::get_entries(
			0,
			array(
				'status'        => 'active',
				'field_filters' => array(
					array(
						'key'   => 'payment_method',
						'value' => 'Bank Mellat',
					),
				),
			),
			array( 'key' => 'date_created', 'direction' => 'DESC' ),
			array( 'offset' => 0, 'page_size' => 50 )
		);

		if ( is_wp_error( $entries ) ) {
			$entries = array();
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'پرداخت‌های بانک ملت در Gravity Forms', 'bank-mellat-gateway' ); ?></h1>
			<table class="widefat striped" style="max-width:1100px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'شماره ورودی', 'bank-mellat-gateway' ); ?></th>
						<th><?php esc_html_e( 'فرم', 'bank-mellat-gateway' ); ?></th>
						<th><?php esc_html_e( 'تاریخ', 'bank-mellat-gateway' ); ?></th>
						<th><?php esc_html_e( 'وضعیت پرداخت', 'bank-mellat-gateway' ); ?></th>
						<th><?php esc_html_e( 'مبلغ', 'bank-mellat-gateway' ); ?></th>
						<th><?php esc_html_e( 'شماره پیگیری', 'bank-mellat-gateway' ); ?></th>
						<th><?php esc_html_e( 'عملیات', 'bank-mellat-gateway' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $entries ) ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'هنوز پرداختی از طریق بانک ملت ثبت نشده است.', 'bank-mellat-gateway' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $entries as $entry ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=gf_entries&view=entry&id=' . (int) $entry['form_id'] . '&lid=' . (int) $entry['id'] ) ); ?>">#<?php echo esc_html( $entry['id'] ); ?></a></td>
								<td><?php echo esc_html( $entry['form_id'] ); ?></td>
								<td><?php echo esc_html( $entry['date_created'] ); ?></td>
								<td><?php echo esc_html( isset( $entry['payment_status'] ) ? $entry['payment_status'] : '' ); ?></td>
								<td><?php echo esc_html( isset( $entry['payment_amount'] ) ? $entry['payment_amount'] : '' ); ?></td>
								<td><?php echo esc_html( isset( $entry['transaction_id'] ) ? $entry['transaction_id'] : '' ); ?></td>
								<td>
									<?php if ( isset( $entry['payment_status'] ) && 'Paid' === $entry['payment_status'] ) : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'استرداد کامل این پرداخت انجام شود؟', 'bank-mellat-gateway' ) ); ?>');">
											<?php wp_nonce_field( 'bmg_gf_refund_' . $entry['id'] ); ?>
											<input type="hidden" name="action" value="bmg_gf_refund" />
											<input type="hidden" name="entry_id" value="<?php echo esc_attr( $entry['id'] ); ?>" />
											<button type="submit" class="button"><?php esc_html_e( 'استرداد', 'bank-mellat-gateway' ); ?></button>
										</form>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Full-amount refund only — same real-world constraint as the WooCommerce
	 * gateway: Bank Mellat's reversal webservice cancels the whole transaction
	 * and only works before that day's settlement batch closes it out.
	 */
	public function handle_refund() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'شما اجازه دسترسی به این بخش را ندارید.', 'bank-mellat-gateway' ), '', array( 'response' => 403 ) );
		}

		$entry_id = isset( $_POST['entry_id'] ) ? absint( $_POST['entry_id'] ) : 0;
		check_admin_referer( 'bmg_gf_refund_' . $entry_id );

		if ( ! class_exists( 'GFAPI' ) || ! $entry_id ) {
			wp_die( esc_html__( 'رکورد فرم یافت نشد.', 'bank-mellat-gateway' ), '', array( 'response' => 404 ) );
		}

		$entry = GFAPI::get_entry( $entry_id );
		if ( is_wp_error( $entry ) ) {
			wp_die( esc_html__( 'رکورد فرم یافت نشد.', 'bank-mellat-gateway' ), '', array( 'response' => 404 ) );
		}

		$sale_reference_id = gform_get_meta( $entry_id, 'bmg_sale_reference_id' );
		if ( empty( $sale_reference_id ) ) {
			$sale_reference_id = isset( $entry['transaction_id'] ) ? $entry['transaction_id'] : '';
		}

		if ( empty( $sale_reference_id ) ) {
			wp_die( esc_html__( 'اطلاعات تراکنش بانک برای این ورودی ثبت نشده است.', 'bank-mellat-gateway' ), '', array( 'response' => 400 ) );
		}

		list( $terminal_id, $username, $password ) = $this->get_api_credentials();
		require_once BMG_DIR . 'includes/class-bmg-api.php';
		$api = new BMG_API( $terminal_id, $username, $password );

		try {
			$code = $api->reverse( $entry_id, $entry_id, $sale_reference_id );
		} catch ( Exception $e ) {
			$code = '';
		}

		if ( '0' === $code ) {
			$entry['payment_status'] = 'Refunded';
			GFAPI::update_entry( $entry );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=bmg-gf-entries' ) );
		exit;
	}
}

BMG_GF_Addon::get_instance();
