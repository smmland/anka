<?php
/**
 * سیستم تیکت پشتیبانی — کاملاً درون قالب پیاده‌سازی شده، بدون افزونه جانبی.
 * هر تیکت یک post از نوع arankia_ticket است و هر پیام (پاسخ) یک post از نوع
 * arankia_ticket_reply با post_parent برابر شناسه تیکت.
 *
 * @package Arankia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * ثبت نوع‌های پست و وضعیت‌ها
 * ---------------------------------------------------------------------- */

function arankia_register_ticket_post_types() {
	register_post_type(
		'arankia_ticket',
		array(
			'labels'             => array(
				'name'          => __( 'تیکت‌های پشتیبانی', 'arankia' ),
				'singular_name' => __( 'تیکت پشتیبانی', 'arankia' ),
				'add_new_item'  => __( 'افزودن تیکت', 'arankia' ),
				'edit_item'     => __( 'مشاهده / پاسخ به تیکت', 'arankia' ),
				'search_items'  => __( 'جستجوی تیکت', 'arankia' ),
				'not_found'     => __( 'تیکتی یافت نشد', 'arankia' ),
			),
			'public'             => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'menu_icon'          => 'dashicons-tickets-alt',
			'capability_type'    => 'post',
			'map_meta_cap'       => true,
			'supports'           => array( 'title' ),
			'has_archive'        => false,
			'exclude_from_search' => true,
			'show_in_rest'       => false,
		)
	);

	register_post_type(
		'arankia_ticket_reply',
		array(
			'labels'             => array(
				'name'          => __( 'پاسخ‌های تیکت', 'arankia' ),
				'singular_name' => __( 'پاسخ تیکت', 'arankia' ),
			),
			'public'             => false,
			'show_ui'            => false,
			'capability_type'    => 'post',
			'supports'           => array( 'title', 'editor' ),
			'exclude_from_search' => true,
		)
	);
}
add_action( 'init', 'arankia_register_ticket_post_types' );

function arankia_register_ticket_statuses() {
	register_post_status(
		'ticket-open',
		array(
			'label'                     => __( 'باز', 'arankia' ),
			'public'                    => false,
			'internal'                  => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: number of tickets. */
			'label_count'               => _n_noop( 'باز <span class="count">(%s)</span>', 'باز <span class="count">(%s)</span>', 'arankia' ),
		)
	);
	register_post_status(
		'ticket-answered',
		array(
			'label'                     => __( 'پاسخ داده شده', 'arankia' ),
			'public'                    => false,
			'internal'                  => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'پاسخ داده شده <span class="count">(%s)</span>', 'پاسخ داده شده <span class="count">(%s)</span>', 'arankia' ),
		)
	);
	register_post_status(
		'ticket-closed',
		array(
			'label'                     => __( 'بسته شده', 'arankia' ),
			'public'                    => false,
			'internal'                  => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'بسته شده <span class="count">(%s)</span>', 'بسته شده <span class="count">(%s)</span>', 'arankia' ),
		)
	);
}
add_action( 'init', 'arankia_register_ticket_statuses' );

function arankia_ticket_status_label( $status ) {
	$labels = array(
		'ticket-open'     => __( 'باز', 'arankia' ),
		'ticket-answered' => __( 'پاسخ داده شده', 'arankia' ),
		'ticket-closed'   => __( 'بسته شده', 'arankia' ),
	);
	return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
}

/* -------------------------------------------------------------------------
 * Endpoint «تیکت پشتیبانی»
 * ---------------------------------------------------------------------- */

function arankia_tickets_register_endpoint() {
	add_rewrite_endpoint( 'tickets', EP_ROOT | EP_PAGES );
}
add_action( 'init', 'arankia_tickets_register_endpoint' );

function arankia_tickets_query_vars( $vars ) {
	$vars[] = 'tickets';
	return $vars;
}
add_filter( 'query_vars', 'arankia_tickets_query_vars' );

/**
 * ثبت تیکت جدید یا پاسخ مشتری، پیش از رندر خروجی (برای امکان ریدایرکت).
 */
function arankia_tickets_handle_frontend_actions() {
	if ( ! is_user_logged_in() || ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
		return;
	}

	$account_url = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'tickets' ) : '';

	// ثبت تیکت جدید.
	if ( isset( $_POST['arankia_new_ticket_nonce'] ) && wp_verify_nonce( $_POST['arankia_new_ticket_nonce'], 'arankia_new_ticket' ) ) {
		$subject = sanitize_text_field( wp_unslash( $_POST['ticket_subject'] ) );
		$message = wp_kses_post( wp_unslash( $_POST['ticket_message'] ) );

		if ( $subject && $message ) {
			$ticket_id = wp_insert_post(
				array(
					'post_type'    => 'arankia_ticket',
					'post_title'   => $subject,
					'post_content' => $message,
					'post_status'  => 'ticket-open',
					'post_author'  => get_current_user_id(),
				)
			);

			if ( $ticket_id && ! is_wp_error( $ticket_id ) ) {
				update_post_meta( $ticket_id, '_arankia_ticket_priority', sanitize_text_field( wp_unslash( $_POST['ticket_priority'] ?? 'normal' ) ) );
				update_post_meta( $ticket_id, '_arankia_ticket_department', sanitize_text_field( wp_unslash( $_POST['ticket_department'] ?? 'general' ) ) );

				arankia_notify_admin_new_ticket( $ticket_id );

				wp_safe_redirect( trailingslashit( $account_url ) . $ticket_id );
				exit;
			}
		}
	}

	// پاسخ مشتری به تیکت موجود.
	if ( isset( $_POST['arankia_ticket_reply_nonce'] ) && wp_verify_nonce( $_POST['arankia_ticket_reply_nonce'], 'arankia_ticket_reply' ) ) {
		$ticket_id = absint( $_POST['ticket_id'] );
		$ticket    = get_post( $ticket_id );

		if ( $ticket && 'arankia_ticket' === $ticket->post_type && (int) $ticket->post_author === get_current_user_id() ) {
			$message = wp_kses_post( wp_unslash( $_POST['reply_message'] ) );

			if ( $message ) {
				$reply_id = wp_insert_post(
					array(
						'post_type'   => 'arankia_ticket_reply',
						'post_title'  => 'reply-' . $ticket_id,
						'post_content' => $message,
						'post_status' => 'publish',
						'post_parent' => $ticket_id,
						'post_author' => get_current_user_id(),
					)
				);

				if ( $reply_id && ! is_wp_error( $reply_id ) ) {
					update_post_meta( $reply_id, '_arankia_is_staff', 'no' );
					wp_update_post(
						array(
							'ID'          => $ticket_id,
							'post_status' => 'ticket-open',
						)
					);
					arankia_notify_admin_new_reply( $ticket_id, $reply_id );
				}
			}

			wp_safe_redirect( trailingslashit( $account_url ) . $ticket_id );
			exit;
		}
	}
}
add_action( 'template_redirect', 'arankia_tickets_handle_frontend_actions' );

/**
 * محتوای صفحه تیکت‌ها: لیست تیکت‌ها، فرم تیکت جدید یا نمایش یک تیکت مشخص.
 */
function arankia_tickets_endpoint_content( $value ) {
	$value = trim( (string) $value );

	if ( $value && is_numeric( $value ) ) {
		arankia_render_single_ticket( absint( $value ) );
		return;
	}

	arankia_render_tickets_list_and_form();
}
add_action( 'woocommerce_account_tickets_endpoint', 'arankia_tickets_endpoint_content' );

function arankia_render_tickets_list_and_form() {
	$tickets = get_posts(
		array(
			'post_type'      => 'arankia_ticket',
			'author'         => get_current_user_id(),
			'post_status'    => array( 'ticket-open', 'ticket-answered', 'ticket-closed' ),
			'posts_per_page' => -1,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		)
	);

	$account_url = wc_get_account_endpoint_url( 'tickets' );
	?>
	<div class="account-tickets">

		<div class="account-section">
			<div class="account-section-head">
				<h3><?php esc_html_e( 'تیکت‌های من', 'arankia' ); ?></h3>
			</div>

			<?php if ( $tickets ) : ?>
				<div class="account-orders-table-wrap">
					<table class="account-orders-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'موضوع', 'arankia' ); ?></th>
								<th><?php esc_html_e( 'وضعیت', 'arankia' ); ?></th>
								<th><?php esc_html_e( 'آخرین بروزرسانی', 'arankia' ); ?></th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $tickets as $ticket ) : ?>
								<tr>
									<td data-title="<?php esc_attr_e( 'موضوع', 'arankia' ); ?>"><?php echo esc_html( $ticket->post_title ); ?></td>
									<td data-title="<?php esc_attr_e( 'وضعیت', 'arankia' ); ?>">
										<span class="order-status-badge ticket-status-<?php echo esc_attr( $ticket->post_status ); ?>"><?php echo esc_html( arankia_ticket_status_label( $ticket->post_status ) ); ?></span>
									</td>
									<td data-title="<?php esc_attr_e( 'آخرین بروزرسانی', 'arankia' ); ?>"><?php echo esc_html( get_the_modified_date( 'Y/m/d H:i', $ticket ) ); ?></td>
									<td><a class="btn-link" href="<?php echo esc_url( trailingslashit( $account_url ) . $ticket->ID ); ?>"><?php esc_html_e( 'مشاهده', 'arankia' ); ?></a></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else : ?>
				<p class="account-empty"><?php esc_html_e( 'شما تاکنون تیکتی ثبت نکرده‌اید.', 'arankia' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="account-section">
			<div class="account-section-head">
				<h3><?php esc_html_e( 'ثبت تیکت جدید', 'arankia' ); ?></h3>
			</div>
			<form method="post" class="new-ticket-form">
				<?php wp_nonce_field( 'arankia_new_ticket', 'arankia_new_ticket_nonce' ); ?>
				<div class="form-row">
					<label for="ticket_subject"><?php esc_html_e( 'موضوع', 'arankia' ); ?></label>
					<input type="text" id="ticket_subject" name="ticket_subject" required />
				</div>
				<div class="form-row form-row-split">
					<div>
						<label for="ticket_department"><?php esc_html_e( 'بخش مربوطه', 'arankia' ); ?></label>
						<select id="ticket_department" name="ticket_department">
							<option value="general"><?php esc_html_e( 'عمومی', 'arankia' ); ?></option>
							<option value="order"><?php esc_html_e( 'سفارش‌ها', 'arankia' ); ?></option>
							<option value="payment"><?php esc_html_e( 'مالی و پرداخت', 'arankia' ); ?></option>
							<option value="technical"><?php esc_html_e( 'فنی', 'arankia' ); ?></option>
						</select>
					</div>
					<div>
						<label for="ticket_priority"><?php esc_html_e( 'اولویت', 'arankia' ); ?></label>
						<select id="ticket_priority" name="ticket_priority">
							<option value="low"><?php esc_html_e( 'کم', 'arankia' ); ?></option>
							<option value="normal" selected><?php esc_html_e( 'عادی', 'arankia' ); ?></option>
							<option value="high"><?php esc_html_e( 'فوری', 'arankia' ); ?></option>
						</select>
					</div>
				</div>
				<div class="form-row">
					<label for="ticket_message"><?php esc_html_e( 'شرح درخواست', 'arankia' ); ?></label>
					<textarea id="ticket_message" name="ticket_message" rows="6" required></textarea>
				</div>
				<button type="submit" class="btn btn-primary"><?php esc_html_e( 'ارسال تیکت', 'arankia' ); ?></button>
			</form>
		</div>
	</div>
	<?php
}

function arankia_render_single_ticket( $ticket_id ) {
	$ticket = get_post( $ticket_id );

	if ( ! $ticket || 'arankia_ticket' !== $ticket->post_type || (int) $ticket->post_author !== get_current_user_id() ) {
		echo '<p class="account-empty">' . esc_html__( 'تیکت مورد نظر یافت نشد.', 'arankia' ) . '</p>';
		return;
	}

	$replies = get_posts(
		array(
			'post_type'      => 'arankia_ticket_reply',
			'post_parent'    => $ticket_id,
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		)
	);
	?>
	<div class="account-tickets single-ticket">
		<a class="btn-link back-link" href="<?php echo esc_url( wc_get_account_endpoint_url( 'tickets' ) ); ?>">&raquo; <?php esc_html_e( 'بازگشت به لیست تیکت‌ها', 'arankia' ); ?></a>

		<div class="ticket-thread-head">
			<h3><?php echo esc_html( $ticket->post_title ); ?></h3>
			<span class="order-status-badge ticket-status-<?php echo esc_attr( $ticket->post_status ); ?>"><?php echo esc_html( arankia_ticket_status_label( $ticket->post_status ) ); ?></span>
		</div>

		<div class="ticket-thread">
			<div class="ticket-message">
				<div class="ticket-message-head">
					<strong><?php esc_html_e( 'شما', 'arankia' ); ?></strong>
					<span><?php echo esc_html( get_the_date( 'Y/m/d H:i', $ticket ) ); ?></span>
				</div>
				<div class="ticket-message-body"><?php echo wp_kses_post( wpautop( $ticket->post_content ) ); ?></div>
			</div>

			<?php foreach ( $replies as $reply ) : ?>
				<?php $is_staff = 'yes' === get_post_meta( $reply->ID, '_arankia_is_staff', true ); ?>
				<div class="ticket-message <?php echo $is_staff ? 'is-staff' : ''; ?>">
					<div class="ticket-message-head">
						<strong><?php echo $is_staff ? esc_html__( 'پشتیبانی آرانکیا', 'arankia' ) : esc_html__( 'شما', 'arankia' ); ?></strong>
						<span><?php echo esc_html( get_the_date( 'Y/m/d H:i', $reply ) ); ?></span>
					</div>
					<div class="ticket-message-body"><?php echo wp_kses_post( wpautop( $reply->post_content ) ); ?></div>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( 'ticket-closed' !== $ticket->post_status ) : ?>
			<form method="post" class="ticket-reply-form">
				<?php wp_nonce_field( 'arankia_ticket_reply', 'arankia_ticket_reply_nonce' ); ?>
				<input type="hidden" name="ticket_id" value="<?php echo esc_attr( $ticket_id ); ?>" />
				<textarea name="reply_message" rows="4" placeholder="<?php esc_attr_e( 'پاسخ خود را بنویسید...', 'arankia' ); ?>" required></textarea>
				<button type="submit" class="btn btn-primary"><?php esc_html_e( 'ارسال پاسخ', 'arankia' ); ?></button>
			</form>
		<?php else : ?>
			<p class="account-empty"><?php esc_html_e( 'این تیکت بسته شده است. در صورت نیاز، تیکت جدیدی ثبت کنید.', 'arankia' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * ایمیل‌های اطلاع‌رسانی
 * ---------------------------------------------------------------------- */

function arankia_notify_admin_new_ticket( $ticket_id ) {
	$ticket = get_post( $ticket_id );
	if ( ! $ticket ) {
		return;
	}
	$admin_email = get_option( 'admin_email' );
	/* translators: %s: ticket subject. */
	$subject = sprintf( __( 'تیکت پشتیبانی جدید: %s', 'arankia' ), $ticket->post_title );
	$body    = sprintf(
		/* translators: 1: subject, 2: admin link. */
		__( "یک تیکت پشتیبانی جدید ثبت شد.\n\nموضوع: %1\$s\n\nمشاهده و پاسخ: %2\$s", 'arankia' ),
		$ticket->post_title,
		admin_url( 'post.php?post=' . $ticket_id . '&action=edit' )
	);
	wp_mail( $admin_email, $subject, $body );
}

function arankia_notify_admin_new_reply( $ticket_id, $reply_id ) {
	$admin_email = get_option( 'admin_email' );
	$ticket      = get_post( $ticket_id );
	/* translators: %s: ticket subject. */
	$subject = sprintf( __( 'پاسخ جدید مشتری روی تیکت: %s', 'arankia' ), $ticket->post_title );
	$body    = sprintf(
		/* translators: %s: admin link. */
		__( "مشتری یک پیام جدید روی تیکت خود ثبت کرد.\n\nمشاهده: %s", 'arankia' ),
		admin_url( 'post.php?post=' . $ticket_id . '&action=edit' )
	);
	wp_mail( $admin_email, $subject, $body );
}

function arankia_notify_customer_staff_reply( $ticket_id ) {
	$ticket = get_post( $ticket_id );
	if ( ! $ticket ) {
		return;
	}
	$customer = get_user_by( 'id', $ticket->post_author );
	if ( ! $customer ) {
		return;
	}
	/* translators: %s: ticket subject. */
	$subject = sprintf( __( 'پاسخ جدید برای تیکت شما: %s', 'arankia' ), $ticket->post_title );
	$body    = sprintf(
		/* translators: %s: account link. */
		__( "تیم پشتیبانی آرانکیا به تیکت شما پاسخ داد.\n\nمشاهده پاسخ: %s", 'arankia' ),
		trailingslashit( wc_get_account_endpoint_url( 'tickets' ) ) . $ticket_id
	);
	wp_mail( $customer->user_email, $subject, $body );
}

/* -------------------------------------------------------------------------
 * پیشخوان مدیریت: ستون‌های لیست + متاباکس گفتگو و پاسخ
 * ---------------------------------------------------------------------- */

function arankia_ticket_admin_columns( $columns ) {
	$new = array(
		'cb'          => $columns['cb'],
		'title'       => __( 'موضوع', 'arankia' ),
		'customer'    => __( 'مشتری', 'arankia' ),
		'status'      => __( 'وضعیت', 'arankia' ),
		'priority'    => __( 'اولویت', 'arankia' ),
		'date'        => $columns['date'],
	);
	return $new;
}
add_filter( 'manage_arankia_ticket_posts_columns', 'arankia_ticket_admin_columns' );

function arankia_ticket_admin_column_content( $column, $post_id ) {
	switch ( $column ) {
		case 'customer':
			$user = get_user_by( 'id', get_post_field( 'post_author', $post_id ) );
			echo $user ? esc_html( $user->display_name . ' (' . $user->user_email . ')' ) : '&mdash;';
			break;
		case 'status':
			echo esc_html( arankia_ticket_status_label( get_post_status( $post_id ) ) );
			break;
		case 'priority':
			$priority = get_post_meta( $post_id, '_arankia_ticket_priority', true );
			echo esc_html( $priority ? $priority : '-' );
			break;
	}
}
add_action( 'manage_arankia_ticket_posts_custom_column', 'arankia_ticket_admin_column_content', 10, 2 );

function arankia_ticket_metabox() {
	add_meta_box( 'arankia_ticket_thread', __( 'گفتگوی تیکت', 'arankia' ), 'arankia_ticket_metabox_render', 'arankia_ticket', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'arankia_ticket_metabox' );

function arankia_ticket_metabox_render( $post ) {
	$replies = get_posts(
		array(
			'post_type'      => 'arankia_ticket_reply',
			'post_parent'    => $post->ID,
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'ASC',
		)
	);
	$customer = get_user_by( 'id', $post->post_author );
	?>
	<div class="arankia-admin-ticket-thread">
		<div class="arankia-ticket-message">
			<strong><?php echo $customer ? esc_html( $customer->display_name ) : esc_html__( 'مشتری', 'arankia' ); ?></strong>
			<span> — <?php echo esc_html( get_the_date( 'Y/m/d H:i', $post ) ); ?></span>
			<p><?php echo wp_kses_post( wpautop( $post->post_content ) ); ?></p>
		</div>
		<?php foreach ( $replies as $reply ) : ?>
			<?php $is_staff = 'yes' === get_post_meta( $reply->ID, '_arankia_is_staff', true ); ?>
			<div class="arankia-ticket-message <?php echo $is_staff ? 'is-staff' : ''; ?>">
				<strong><?php echo $is_staff ? esc_html__( 'پشتیبانی', 'arankia' ) : ( $customer ? esc_html( $customer->display_name ) : '' ); ?></strong>
				<span> — <?php echo esc_html( get_the_date( 'Y/m/d H:i', $reply ) ); ?></span>
				<p><?php echo wp_kses_post( wpautop( $reply->post_content ) ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>

	<hr />

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'arankia_admin_ticket_reply', 'arankia_admin_ticket_reply_nonce' ); ?>
		<input type="hidden" name="action" value="arankia_admin_ticket_reply" />
		<input type="hidden" name="ticket_id" value="<?php echo esc_attr( $post->ID ); ?>" />
		<p>
			<textarea name="reply_message" rows="5" style="width:100%;" placeholder="<?php esc_attr_e( 'پاسخ خود را بنویسید...', 'arankia' ); ?>"></textarea>
		</p>
		<p>
			<label><?php esc_html_e( 'وضعیت تیکت پس از پاسخ:', 'arankia' ); ?></label>
			<select name="new_status">
				<option value="ticket-answered"><?php esc_html_e( 'پاسخ داده شده', 'arankia' ); ?></option>
				<option value="ticket-open"><?php esc_html_e( 'باز بماند', 'arankia' ); ?></option>
				<option value="ticket-closed"><?php esc_html_e( 'بسته شود', 'arankia' ); ?></option>
			</select>
		</p>
		<?php submit_button( __( 'ارسال پاسخ', 'arankia' ) ); ?>
	</form>
	<?php
}

function arankia_handle_admin_ticket_reply() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'دسترسی غیرمجاز.', 'arankia' ) );
	}
	if ( ! isset( $_POST['arankia_admin_ticket_reply_nonce'] ) || ! wp_verify_nonce( $_POST['arankia_admin_ticket_reply_nonce'], 'arankia_admin_ticket_reply' ) ) {
		wp_die( esc_html__( 'درخواست نامعتبر.', 'arankia' ) );
	}

	$ticket_id     = absint( $_POST['ticket_id'] );
	$message       = isset( $_POST['reply_message'] ) ? wp_kses_post( wp_unslash( $_POST['reply_message'] ) ) : '';
	$posted_status = isset( $_POST['new_status'] ) ? sanitize_text_field( wp_unslash( $_POST['new_status'] ) ) : '';
	$status        = in_array( $posted_status, array( 'ticket-answered', 'ticket-open', 'ticket-closed' ), true ) ? $posted_status : 'ticket-answered';

	if ( $message ) {
		$reply_id = wp_insert_post(
			array(
				'post_type'    => 'arankia_ticket_reply',
				'post_title'   => 'reply-' . $ticket_id,
				'post_content' => $message,
				'post_status'  => 'publish',
				'post_parent'  => $ticket_id,
				'post_author'  => get_current_user_id(),
			)
		);

		if ( $reply_id && ! is_wp_error( $reply_id ) ) {
			update_post_meta( $reply_id, '_arankia_is_staff', 'yes' );
			arankia_notify_customer_staff_reply( $ticket_id );
		}
	}

	wp_update_post(
		array(
			'ID'          => $ticket_id,
			'post_status' => $status,
		)
	);

	wp_safe_redirect( admin_url( 'post.php?post=' . $ticket_id . '&action=edit&arankia_replied=1' ) );
	exit;
}
add_action( 'admin_post_arankia_admin_ticket_reply', 'arankia_handle_admin_ticket_reply' );
