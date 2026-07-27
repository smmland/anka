<?php
/**
 * A troubleshooting page that checks the server's ability to talk to the
 * Behpardakht Mellat web service, without needing to run a real transaction.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BMG_Diagnostics {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
	}

	public static function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'عیب‌یابی بانک ملت', 'bank-mellat-gateway' ),
			__( 'عیب‌یابی بانک ملت', 'bank-mellat-gateway' ),
			'manage_woocommerce',
			'bmg-diagnostics',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'شما اجازه دسترسی به این صفحه را ندارید.', 'bank-mellat-gateway' ), '', array( 'response' => 403 ) );
		}

		$checks = self::run_checks();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'عیب‌یابی اتصال درگاه بانک ملت', 'bank-mellat-gateway' ); ?></h1>
			<p><?php esc_html_e( 'این صفحه سلامت زیرساخت سرور برای اتصال به وب‌سرویس بانک ملت را بررسی می‌کند؛ برای اجرای آن نیازی به انجام تراکنش واقعی نیست.', 'bank-mellat-gateway' ); ?></p>

			<table class="widefat striped" style="max-width:900px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'بررسی', 'bank-mellat-gateway' ); ?></th>
						<th style="width:120px;"><?php esc_html_e( 'نتیجه', 'bank-mellat-gateway' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $checks as $check ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $check['label'] ); ?></strong>
								<?php if ( ! empty( $check['detail'] ) ) : ?>
									<br /><span style="color:#666;"><?php echo esc_html( $check['detail'] ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( 'ok' === $check['status'] ) : ?>
									<span style="color:#1a7f37;">&#10003; <?php esc_html_e( 'موفق', 'bank-mellat-gateway' ); ?></span>
								<?php elseif ( 'warning' === $check['status'] ) : ?>
									<span style="color:#996800;">&#9888; <?php esc_html_e( 'هشدار', 'bank-mellat-gateway' ); ?></span>
								<?php else : ?>
									<span style="color:#c00000;">&#10007; <?php esc_html_e( 'خطا', 'bank-mellat-gateway' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p style="margin-top:16px;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bmg-diagnostics' ) ); ?>" class="button"><?php esc_html_e( 'بررسی مجدد', 'bank-mellat-gateway' ); ?></a>
			</p>
		</div>
		<?php
	}

	private static function run_checks() {
		$checks = array();

		$checks[] = array(
			'label'  => __( 'نسخه PHP', 'bank-mellat-gateway' ),
			'status' => version_compare( PHP_VERSION, '7.4', '>=' ) ? 'ok' : 'error',
			'detail' => PHP_VERSION,
		);

		$soap_ok  = extension_loaded( 'soap' );
		$checks[] = array(
			'label'  => __( 'اکستنشن PHP SOAP', 'bank-mellat-gateway' ),
			'status' => $soap_ok ? 'ok' : 'error',
			'detail' => $soap_ok ? __( 'فعال است.', 'bank-mellat-gateway' ) : __( 'باید توسط هاست فعال شود؛ بدون آن اتصال به بانک ممکن نیست.', 'bank-mellat-gateway' ),
		);

		$checks[] = array(
			'label'  => __( 'اکستنشن OpenSSL', 'bank-mellat-gateway' ),
			'status' => extension_loaded( 'openssl' ) ? 'ok' : 'error',
		);

		$zip_ok   = class_exists( 'ZipArchive' );
		$checks[] = array(
			'label'  => __( 'اکستنشن ZipArchive (برای خروجی اکسل گزارش‌ها)', 'bank-mellat-gateway' ),
			'status' => $zip_ok ? 'ok' : 'warning',
			'detail' => $zip_ok ? '' : __( 'در دسترس نیست؛ خروجی گزارش‌ها به‌صورت CSV ارائه می‌شود.', 'bank-mellat-gateway' ),
		);

		if ( $soap_ok && class_exists( 'BMG_API' ) ) {
			$started = microtime( true );
			try {
				new SoapClient(
					BMG_API::WSDL_URL,
					array(
						'connection_timeout' => 10,
						'cache_wsdl'         => WSDL_CACHE_NONE,
						'stream_context'     => stream_context_create(
							array(
								'ssl' => array(
									'verify_peer'      => true,
									'verify_peer_name' => true,
								),
							)
						),
					)
				);
				$elapsed  = round( ( microtime( true ) - $started ) * 1000 );
				$checks[] = array(
					'label'  => __( 'اتصال به وب‌سرویس بانک ملت (WSDL)', 'bank-mellat-gateway' ),
					'status' => 'ok',
					/* translators: %d: milliseconds */
					'detail' => sprintf( __( 'با موفقیت در %d میلی‌ثانیه برقرار شد.', 'bank-mellat-gateway' ), $elapsed ),
				);
			} catch ( Exception $e ) {
				$checks[] = array(
					'label'  => __( 'اتصال به وب‌سرویس بانک ملت (WSDL)', 'bank-mellat-gateway' ),
					'status' => 'error',
					'detail' => $e->getMessage(),
				);
			}
		}

		$settings  = get_option( 'woocommerce_bank_mellat_settings', array() );
		$has_creds = ! empty( $settings['terminal_id'] ) && ! empty( $settings['username'] ) && ! empty( $settings['password'] );

		$checks[] = array(
			'label'  => __( 'تنظیم اطلاعات ترمینال', 'bank-mellat-gateway' ),
			'status' => $has_creds ? 'ok' : 'warning',
			'detail' => $has_creds ? __( 'ترمینال آی‌دی، نام کاربری و رمز عبور تنظیم شده‌اند.', 'bank-mellat-gateway' ) : __( 'هنوز در تنظیمات درگاه وارد نشده‌اند.', 'bank-mellat-gateway' ),
		);

		$checks[] = array(
			'label'  => __( 'فعال بودن درگاه', 'bank-mellat-gateway' ),
			'status' => ( isset( $settings['enabled'] ) && 'yes' === $settings['enabled'] ) ? 'ok' : 'warning',
		);

		// Best-effort outbound IP — useful because Bank Mellat only accepts webservice
		// calls from the static IP registered on the terminal contract.
		$ip_response = wp_remote_get( 'https://api.ipify.org', array( 'timeout' => 5 ) );
		if ( ! is_wp_error( $ip_response ) && 200 === wp_remote_retrieve_response_code( $ip_response ) ) {
			$ip = trim( wp_remote_retrieve_body( $ip_response ) );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				$checks[] = array(
					'label'  => __( 'IP خروجی سرور (برای ثبت در لیست سفید بانک)', 'bank-mellat-gateway' ),
					'status' => 'ok',
					'detail' => $ip . ' — ' . __( 'اگر سایت پشت CDN یا پروکسی است، ممکن است این IP واقعی نباشد.', 'bank-mellat-gateway' ),
				);
			}
		}

		$checks[] = array(
			'label'  => __( 'زمان سرور (Asia/Tehran)', 'bank-mellat-gateway' ),
			'status' => 'ok',
			'detail' => ( new DateTime( 'now', new DateTimeZone( 'Asia/Tehran' ) ) )->format( 'Y-m-d H:i:s' ),
		);

		return $checks;
	}
}
