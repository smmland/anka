<?php
/**
 * A lightweight, dependency-free reporting dashboard for Bank Mellat transactions:
 * revenue, success rate, a peak-hours chart, and an Excel/CSV export.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BMG_Reports {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_post_bmg_export_report', array( __CLASS__, 'handle_export' ) );
	}

	public static function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'گزارش بانک ملت', 'bank-mellat-gateway' ),
			__( 'گزارش بانک ملت', 'bank-mellat-gateway' ),
			'manage_woocommerce',
			'bmg-reports',
			array( __CLASS__, 'render_page' )
		);
	}

	private static function get_range() {
		$from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		$to   = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : gmdate( 'Y-m-d' );

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ) {
			$from = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) ) {
			$to = gmdate( 'Y-m-d' );
		}

		return array( $from, $to );
	}

	/**
	 * @return WC_Order[]
	 */
	private static function query_orders( $from, $to ) {
		return wc_get_orders(
			array(
				'payment_method' => 'bank_mellat',
				'date_created'   => $from . '...' . $to,
				'limit'          => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'return'         => 'objects',
			)
		);
	}

	private static function build_stats( $orders ) {
		$paid_statuses = wc_get_is_paid_statuses();

		$attempts   = 0;
		$paid_count = 0;
		$revenue    = 0.0;
		$hours      = array_fill( 0, 24, 0 );

		foreach ( $orders as $order ) {
			$attempts++;

			if ( in_array( $order->get_status(), $paid_statuses, true ) ) {
				$paid_count++;
				$revenue += (float) $order->get_total();

				$date = $order->get_date_paid() ? $order->get_date_paid() : $order->get_date_created();
				if ( $date ) {
					$hours[ (int) $date->date( 'G' ) ]++;
				}
			}
		}

		return array(
			'attempts'     => $attempts,
			'paid_count'   => $paid_count,
			'revenue'      => $revenue,
			'success_rate' => $attempts > 0 ? round( $paid_count / $attempts * 100, 1 ) : 0,
			'hours'        => $hours,
		);
	}

	private static function render_bar_chart( $hours ) {
		$max    = max( 1, max( $hours ) );
		$width  = 700;
		$height = 200;
		$gap    = 3;
		$count  = count( $hours );
		$bar_w  = ( $width - ( $count - 1 ) * $gap ) / $count;

		$svg = '<svg viewBox="0 0 ' . $width . ' ' . ( $height + 24 ) . '" width="100%" style="max-width:700px;height:auto;" role="img" aria-label="' . esc_attr__( 'نمودار ساعات اوج فروش', 'bank-mellat-gateway' ) . '">';

		foreach ( $hours as $hour => $value ) {
			$bar_h = ( $value / $max ) * $height;
			$x     = $hour * ( $bar_w + $gap );
			$y     = $height - $bar_h;

			$svg .= '<rect x="' . round( $x, 1 ) . '" y="' . round( $y, 1 ) . '" width="' . round( $bar_w, 1 ) . '" height="' . round( $bar_h, 1 ) . '" fill="#B71C2B" rx="2">';
			/* translators: 1: hour of day, 2: number of successful payments in that hour */
			$svg .= '<title>' . esc_html( sprintf( __( 'ساعت %1$s — %2$d پرداخت', 'bank-mellat-gateway' ), sprintf( '%02d:00', $hour ), $value ) ) . '</title>';
			$svg .= '</rect>';

			if ( 0 === $hour % 3 ) {
				$svg .= '<text x="' . round( $x + $bar_w / 2, 1 ) . '" y="' . ( $height + 16 ) . '" font-size="10" text-anchor="middle" fill="#666">' . sprintf( '%02d', $hour ) . '</text>';
			}
		}

		$svg .= '</svg>';

		return $svg;
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'شما اجازه دسترسی به این صفحه را ندارید.', 'bank-mellat-gateway' ), '', array( 'response' => 403 ) );
		}

		list( $from, $to ) = self::get_range();
		$orders             = self::query_orders( $from, $to );
		$stats              = self::build_stats( $orders );

		$export_url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'bmg_export_report',
					'from'   => $from,
					'to'     => $to,
				),
				admin_url( 'admin-post.php' )
			),
			'bmg_export_report'
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'گزارش تراکنش‌های بانک ملت', 'bank-mellat-gateway' ); ?></h1>

			<form method="get" style="margin:16px 0;display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
				<input type="hidden" name="page" value="bmg-reports" />
				<label>
					<?php esc_html_e( 'از تاریخ', 'bank-mellat-gateway' ); ?><br />
					<input type="date" name="from" value="<?php echo esc_attr( $from ); ?>" />
				</label>
				<label>
					<?php esc_html_e( 'تا تاریخ', 'bank-mellat-gateway' ); ?><br />
					<input type="date" name="to" value="<?php echo esc_attr( $to ); ?>" />
				</label>
				<button type="submit" class="button"><?php esc_html_e( 'اعمال بازه', 'bank-mellat-gateway' ); ?></button>
				<a href="<?php echo esc_url( $export_url ); ?>" class="button button-primary"><?php esc_html_e( 'خروجی اکسل / CSV', 'bank-mellat-gateway' ); ?></a>
			</form>

			<div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px;">
				<?php
				self::render_stat_tile( __( 'درآمد بازه انتخابی', 'bank-mellat-gateway' ), wp_strip_all_tags( wc_price( $stats['revenue'] ) ) );
				self::render_stat_tile( __( 'پرداخت‌های موفق', 'bank-mellat-gateway' ), number_format_i18n( $stats['paid_count'] ) );
				self::render_stat_tile( __( 'کل تلاش‌های پرداخت', 'bank-mellat-gateway' ), number_format_i18n( $stats['attempts'] ) );
				self::render_stat_tile( __( 'نرخ موفقیت', 'bank-mellat-gateway' ), $stats['success_rate'] . '%' );
				?>
			</div>

			<h2><?php esc_html_e( 'ساعات اوج فروش', 'bank-mellat-gateway' ); ?></h2>
			<?php echo self::render_bar_chart( $stats['hours'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above. ?>

			<h2 style="margin-top:24px;"><?php esc_html_e( 'آخرین تراکنش‌ها', 'bank-mellat-gateway' ); ?></h2>
			<table class="widefat striped" style="max-width:1000px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'سفارش', 'bank-mellat-gateway' ); ?></th>
						<th><?php esc_html_e( 'تاریخ', 'bank-mellat-gateway' ); ?></th>
						<th><?php esc_html_e( 'وضعیت', 'bank-mellat-gateway' ); ?></th>
						<th><?php esc_html_e( 'مبلغ', 'bank-mellat-gateway' ); ?></th>
						<th><?php esc_html_e( 'شماره پیگیری', 'bank-mellat-gateway' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $orders ) ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'در این بازه تراکنشی ثبت نشده است.', 'bank-mellat-gateway' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( array_slice( $orders, 0, 50 ) as $order ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">#<?php echo esc_html( $order->get_order_number() ); ?></a></td>
								<td><?php echo esc_html( $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i' ) : '' ); ?></td>
								<td><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></td>
								<td><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
								<td><?php echo esc_html( $order->get_meta( '_bmg_sale_reference_id' ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			<?php if ( count( $orders ) > 50 ) : ?>
				<p><?php echo esc_html( sprintf(
					/* translators: %d: number of additional orders not shown in the table */
					__( '%d تراکنش دیگر در این بازه ثبت شده که برای مشاهده کامل از خروجی اکسل/CSV استفاده کنید.', 'bank-mellat-gateway' ),
					count( $orders ) - 50
				) ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_stat_tile( $label, $value ) {
		echo '<div style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:16px 20px;min-width:160px;">';
		echo '<div style="font-size:13px;color:#666;">' . esc_html( $label ) . '</div>';
		echo '<div style="font-size:24px;font-weight:700;margin-top:4px;">' . wp_kses_post( $value ) . '</div>';
		echo '</div>';
	}

	public static function handle_export() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'شما اجازه دسترسی به این بخش را ندارید.', 'bank-mellat-gateway' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'bmg_export_report' );

		list( $from, $to ) = self::get_range();
		$orders             = self::query_orders( $from, $to );

		$rows = array(
			array(
				__( 'شماره سفارش', 'bank-mellat-gateway' ),
				__( 'تاریخ', 'bank-mellat-gateway' ),
				__( 'وضعیت', 'bank-mellat-gateway' ),
				__( 'مبلغ', 'bank-mellat-gateway' ),
				__( 'شماره پیگیری', 'bank-mellat-gateway' ),
				__( 'شماره کارت', 'bank-mellat-gateway' ),
			),
		);

		foreach ( $orders as $order ) {
			$rows[] = array(
				$order->get_order_number(),
				$order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i' ) : '',
				wc_get_order_status_name( $order->get_status() ),
				(float) $order->get_total(),
				(string) $order->get_meta( '_bmg_sale_reference_id' ),
				(string) $order->get_meta( '_bmg_card_pan' ),
			);
		}

		$filename_base = 'bank-mellat-report-' . $from . '-to-' . $to;

		require_once BMG_DIR . 'includes/class-bmg-xlsx-writer.php';
		$writer = new BMG_XLSX_Writer();
		foreach ( $rows as $row ) {
			$writer->add_row( $row );
		}

		$tmp_file = wp_tempnam( 'bmg-report' );
		$is_xlsx  = $writer->save( $tmp_file );

		if ( $is_xlsx ) {
			nocache_headers();
			header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
			header( 'Content-Disposition: attachment; filename="' . $filename_base . '.xlsx"' );
			header( 'Content-Length: ' . filesize( $tmp_file ) );
			readfile( $tmp_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile -- streaming a freshly generated temp file for direct download.
			wp_delete_file( $tmp_file );
			exit;
		}

		wp_delete_file( $tmp_file );

		// Fallback when the zip extension isn't available on this host.
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename_base . '.csv"' );
		echo "\xEF\xBB\xBF"; // UTF-8 BOM so Excel opens Persian text correctly.

		$out = fopen( 'php://output', 'w' );
		foreach ( $rows as $row ) {
			fputcsv( $out, $row );
		}
		fclose( $out );
		exit;
	}
}
