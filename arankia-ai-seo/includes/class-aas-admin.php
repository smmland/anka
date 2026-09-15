<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAS_Admin {

	const PAGE_DASHBOARD = 'arankia-ai-seo';
	const PAGE_REVIEW    = 'arankia-ai-seo-review';
	const PAGE_SETTINGS  = 'arankia-ai-seo-settings';
	const ADMIN_NONCE    = 'aas_admin_nonce';

	private $hook_suffixes = array();

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_save_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'render_notices' ) );
	}

	public function register_menu() {
		$this->hook_suffixes[] = add_menu_page(
			__( 'سئوی هوشمند آرانکیا', 'arankia-ai-seo' ),
			__( 'سئوی هوشمند', 'arankia-ai-seo' ),
			'manage_options',
			self::PAGE_DASHBOARD,
			array( $this, 'render_dashboard' ),
			'dashicons-chart-line',
			56
		);

		$this->hook_suffixes[] = add_submenu_page(
			self::PAGE_DASHBOARD,
			__( 'لیست محصولات', 'arankia-ai-seo' ),
			__( 'لیست محصولات', 'arankia-ai-seo' ),
			'manage_options',
			self::PAGE_DASHBOARD,
			array( $this, 'render_dashboard' )
		);

		$this->hook_suffixes[] = add_submenu_page(
			self::PAGE_DASHBOARD,
			__( 'بررسی و تایید سئو', 'arankia-ai-seo' ),
			__( 'بررسی و تایید', 'arankia-ai-seo' ),
			'manage_options',
			self::PAGE_REVIEW,
			array( $this, 'render_review' )
		);

		$this->hook_suffixes[] = add_submenu_page(
			self::PAGE_DASHBOARD,
			__( 'تنظیمات سئوی هوشمند', 'arankia-ai-seo' ),
			__( 'تنظیمات', 'arankia-ai-seo' ),
			'manage_options',
			self::PAGE_SETTINGS,
			array( $this, 'render_settings' )
		);
	}

	public function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, $this->hook_suffixes, true ) ) {
			return;
		}

		wp_enqueue_style( 'aas-admin', AAS_URL . 'admin/css/admin.css', array(), AAS_VERSION );
		wp_enqueue_script( 'aas-admin', AAS_URL . 'admin/js/admin.js', array( 'jquery' ), AAS_VERSION, true );

		wp_localize_script( 'aas-admin', 'AAS_Admin_Data', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( self::ADMIN_NONCE ),
			'i18n'    => array(
				'working'       => __( 'در حال پردازش...', 'arankia-ai-seo' ),
				'confirmReject' => __( 'همه پیشنهادهای این محصول رد شود؟', 'arankia-ai-seo' ),
				'genericError'  => __( 'خطایی رخ داد. لطفا دوباره تلاش کنید.', 'arankia-ai-seo' ),
			),
		) );
	}

	public function render_notices() {
		if ( ! isset( $_GET['page'] ) || 0 !== strpos( sanitize_key( wp_unslash( $_GET['page'] ) ), 'arankia-ai-seo' ) ) {
			return;
		}
		if ( ! empty( $_GET['updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'تنظیمات با موفقیت ذخیره شد.', 'arankia-ai-seo' ) . '</p></div>';
		}
	}

	/* ------------------------------------------------------------------ */
	/* Dashboard / product list                                            */
	/* ------------------------------------------------------------------ */

	public function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			echo '<div class="wrap"><p>' . esc_html__( 'ووکامرس فعال نیست.', 'arankia-ai-seo' ) . '</p></div>';
			return;
		}

		$filter = isset( $_GET['aas_filter'] ) ? sanitize_key( wp_unslash( $_GET['aas_filter'] ) ) : 'all';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( 'pending' === $filter ) {
			$args['meta_query'] = array( array( 'key' => '_aas_status', 'value' => 'pending_review' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}

		$query        = new WP_Query( $args );
		$pending_count = AAS_Suggestion::count_pending_products();
		?>
		<div class="wrap aas-admin-wrap">
			<div class="aas-admin-header">
				<h1><?php esc_html_e( 'سئوی هوشمند محصولات', 'arankia-ai-seo' ); ?></h1>
				<p><?php esc_html_e( 'وضعیت سئوی محصولات را بررسی کنید و با هوش مصنوعی پیشنهاد بسازید؛ هیچ تغییری بدون تایید شما روی محصول اعمال نمی‌شود.', 'arankia-ai-seo' ); ?></p>
			</div>

			<ul class="subsubsub">
				<li><a href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_DASHBOARD, 'aas_filter' => 'all' ), admin_url( 'admin.php' ) ) ); ?>" class="<?php echo 'all' === $filter ? 'current' : ''; ?>"><?php esc_html_e( 'همه محصولات', 'arankia-ai-seo' ); ?></a> |</li>
				<li><a href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_DASHBOARD, 'aas_filter' => 'pending' ), admin_url( 'admin.php' ) ) ); ?>" class="<?php echo 'pending' === $filter ? 'current' : ''; ?>">
					<?php
					/* translators: %d: number of products */
					printf( esc_html__( 'در انتظار بررسی (%d)', 'arankia-ai-seo' ), (int) $pending_count );
					?>
				</a></li>
			</ul>

			<?php if ( $pending_count > 0 ) : ?>
				<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_REVIEW ) ); ?>"><?php esc_html_e( 'شروع بررسی صف پیشنهادها', 'arankia-ai-seo' ); ?></a></p>
			<?php endif; ?>

			<form id="aas-bulk-form">
				<div class="tablenav top">
					<div class="alignleft actions">
						<select id="aas-provider-select">
							<?php foreach ( $this->provider_options() as $id => $label ) : ?>
								<option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<button type="button" class="button" id="aas-bulk-generate"><?php esc_html_e( 'تولید سئو برای انتخاب‌شده‌ها', 'arankia-ai-seo' ); ?></button>
						<span class="aas-bulk-result"></span>
					</div>
				</div>

				<table class="widefat fixed striped aas-product-table">
					<thead>
						<tr>
							<td class="check-column"><input type="checkbox" id="aas-select-all" /></td>
							<th><?php esc_html_e( 'محصول', 'arankia-ai-seo' ); ?></th>
							<th><?php esc_html_e( 'وضعیت سئوی هوشمند', 'arankia-ai-seo' ); ?></th>
							<th><?php esc_html_e( 'مشکلات چک‌لیست سئو', 'arankia-ai-seo' ); ?></th>
							<th><?php esc_html_e( 'عملیات', 'arankia-ai-seo' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( ! $query->have_posts() ) : ?>
							<tr><td colspan="5"><?php esc_html_e( 'محصولی یافت نشد.', 'arankia-ai-seo' ); ?></td></tr>
						<?php endif; ?>
						<?php while ( $query->have_posts() ) : $query->the_post(); ?>
							<?php
							$product_id = get_the_ID();
							$issues     = AAS_Seo_Checklist::count_issues( $product_id );
							$status     = get_post_meta( $product_id, '_aas_status', true );
							?>
							<tr>
								<td class="check-column"><input type="checkbox" class="aas-row-checkbox" value="<?php echo esc_attr( $product_id ); ?>" /></td>
								<td>
									<strong><a href="<?php echo esc_url( get_edit_post_link( $product_id ) ); ?>"><?php echo esc_html( get_the_title() ); ?></a></strong>
								</td>
								<td><?php echo $this->status_badge( $status ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
								<td>
									<?php if ( $issues[ AAS_Seo_Checklist::STATUS_ERROR ] > 0 ) : ?>
										<span class="aas-badge aas-badge-error"><?php printf( esc_html__( '%d ایراد', 'arankia-ai-seo' ), (int) $issues[ AAS_Seo_Checklist::STATUS_ERROR ] ); ?></span>
									<?php endif; ?>
									<?php if ( $issues[ AAS_Seo_Checklist::STATUS_WARNING ] > 0 ) : ?>
										<span class="aas-badge aas-badge-warning"><?php printf( esc_html__( '%d هشدار', 'arankia-ai-seo' ), (int) $issues[ AAS_Seo_Checklist::STATUS_WARNING ] ); ?></span>
									<?php endif; ?>
									<?php if ( 0 === $issues[ AAS_Seo_Checklist::STATUS_ERROR ] && 0 === $issues[ AAS_Seo_Checklist::STATUS_WARNING ] ) : ?>
										<span class="aas-badge aas-badge-ok"><?php esc_html_e( 'بدون مشکل', 'arankia-ai-seo' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<button type="button" class="button aas-generate-one" data-product-id="<?php echo esc_attr( $product_id ); ?>"><?php esc_html_e( 'تولید/بازتولید با AI', 'arankia-ai-seo' ); ?></button>
									<?php if ( 'pending_review' === $status ) : ?>
										<a class="button button-primary" href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_REVIEW, 'product_id' => $product_id ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'بررسی و تایید', 'arankia-ai-seo' ); ?></a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endwhile; wp_reset_postdata(); ?>
					</tbody>
				</table>

				<?php
				$total_pages = $query->max_num_pages;
				if ( $total_pages > 1 ) {
					echo '<div class="tablenav-pages">';
					echo paginate_links( array( // phpcs:ignore WordPress.Security.EscapeOutput
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'current'   => $paged,
						'total'     => $total_pages,
					) );
					echo '</div>';
				}
				?>
			</form>
		</div>
		<?php
	}

	private function status_badge( $status ) {
		$map = array(
			''                => array( __( 'بدون بررسی', 'arankia-ai-seo' ), 'aas-badge-muted' ),
			'queued'          => array( __( 'در صف تولید', 'arankia-ai-seo' ), 'aas-badge-muted' ),
			'processing'      => array( __( 'در حال تولید...', 'arankia-ai-seo' ), 'aas-badge-muted' ),
			'pending_review'  => array( __( 'آماده بررسی', 'arankia-ai-seo' ), 'aas-badge-warning' ),
			'no_changes'      => array( __( 'تغییری پیشنهاد نشد', 'arankia-ai-seo' ), 'aas-badge-ok' ),
			'reviewed'        => array( __( 'بررسی و اعمال شد', 'arankia-ai-seo' ), 'aas-badge-ok' ),
			'error'           => array( __( 'خطا', 'arankia-ai-seo' ), 'aas-badge-error' ),
		);
		$entry = isset( $map[ $status ] ) ? $map[ $status ] : $map[''];
		return '<span class="aas-badge ' . esc_attr( $entry[1] ) . '">' . esc_html( $entry[0] ) . '</span>';
	}

	private function provider_options() {
		$options = array();
		foreach ( AAS_Provider_Factory::get_ids() as $id ) {
			$provider = AAS_Provider_Factory::get( $id );
			if ( $provider ) {
				$options[ $id ] = $provider->label();
			}
		}
		return $options;
	}

	/* ------------------------------------------------------------------ */
	/* Review slider                                                       */
	/* ------------------------------------------------------------------ */

	public function render_review() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			echo '<div class="wrap"><p>' . esc_html__( 'ووکامرس فعال نیست.', 'arankia-ai-seo' ) . '</p></div>';
			return;
		}

		$queue      = AAS_Suggestion::get_pending_product_ids( 500 );
		$product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;

		if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
			$product_id = ! empty( $queue ) ? (int) $queue[0] : 0;
		}

		echo '<div class="wrap aas-admin-wrap aas-review-wrap">';
		echo '<h1>' . esc_html__( 'بررسی و تایید سئوی هوشمند', 'arankia-ai-seo' ) . '</h1>';

		if ( ! $product_id ) {
			echo '<p>' . esc_html__( 'در حال حاضر هیچ محصولی در صف بررسی نیست.', 'arankia-ai-seo' ) . '</p>';
			echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_DASHBOARD ) ) . '">' . esc_html__( 'بازگشت به لیست محصولات', 'arankia-ai-seo' ) . '</a></p>';
			echo '</div>';
			return;
		}

		$this->render_review_slider( $product_id, $queue );
		echo '</div>';
	}

	private function render_review_slider( $product_id, $queue ) {
		$position = array_search( $product_id, $queue, true );
		$total    = count( $queue );
		$prev_id  = false !== $position && $total > 1 ? $queue[ ( $position - 1 + $total ) % $total ] : 0;
		$next_id  = false !== $position && $total > 1 ? $queue[ ( $position + 1 ) % $total ] : 0;

		$pending_map = AAS_Suggestion::get_pending_map( $product_id );
		$checklist   = AAS_Seo_Checklist::evaluate( $product_id );
		$status      = get_post_meta( $product_id, '_aas_status', true );
		$last_error  = get_post_meta( $product_id, '_aas_last_error', true );
		$batch_id    = get_post_meta( $product_id, '_aas_last_batch_id', true );
		?>
		<div class="aas-review-toolbar">
			<div class="aas-review-position">
				<?php if ( false !== $position ) : ?>
					<?php printf( esc_html__( 'محصول %1$d از %2$d در صف بررسی', 'arankia-ai-seo' ), (int) $position + 1, (int) $total ); ?>
				<?php else : ?>
					<?php esc_html_e( 'این محصول در صف بررسی نیست', 'arankia-ai-seo' ); ?>
				<?php endif; ?>
			</div>
			<div class="aas-review-nav">
				<?php if ( $prev_id ) : ?>
					<a class="button" href="<?php echo esc_url( add_query_arg( 'product_id', $prev_id ) ); ?>">&larr; <?php esc_html_e( 'قبلی', 'arankia-ai-seo' ); ?></a>
				<?php endif; ?>
				<?php if ( $next_id ) : ?>
					<a class="button" href="<?php echo esc_url( add_query_arg( 'product_id', $next_id ) ); ?>"><?php esc_html_e( 'بعدی', 'arankia-ai-seo' ); ?> &rarr;</a>
				<?php endif; ?>
			</div>
		</div>

		<div class="aas-product-card" data-product-id="<?php echo esc_attr( $product_id ); ?>" data-batch-id="<?php echo esc_attr( $batch_id ); ?>" data-next-id="<?php echo esc_attr( $next_id ); ?>">
			<h2>
				<?php echo esc_html( get_the_title( $product_id ) ); ?>
				<a class="aas-edit-link" href="<?php echo esc_url( get_edit_post_link( $product_id ) ); ?>" target="_blank"><?php esc_html_e( 'ویرایش در ووکامرس', 'arankia-ai-seo' ); ?></a>
			</h2>

			<?php if ( 'error' === $status && $last_error ) : ?>
				<div class="notice notice-error inline"><p><?php echo esc_html( $last_error ); ?></p></div>
			<?php endif; ?>

			<?php if ( empty( $pending_map ) ) : ?>
				<p><?php esc_html_e( 'هنوز پیشنهادی از هوش مصنوعی برای این محصول تولید نشده است.', 'arankia-ai-seo' ); ?></p>
			<?php endif; ?>

			<div class="aas-fields">
				<?php foreach ( AAS_Fields::all() as $field_key => $field_meta ) : ?>
					<?php
					$issue       = isset( $checklist[ $field_key ] ) ? $checklist[ $field_key ] : array( 'status' => 'ok', 'messages' => array() );
					$suggestion  = isset( $pending_map[ $field_key ] ) ? $pending_map[ $field_key ] : null;
					$current_val = AAS_Fields::get_current_value( $product_id, $field_key );
					?>
					<div class="aas-field-row aas-issue-<?php echo esc_attr( $issue['status'] ); ?>">
						<div class="aas-field-head">
							<label>
								<?php if ( $suggestion ) : ?>
									<input type="checkbox" class="aas-field-checkbox" name="fields[]" value="<?php echo esc_attr( $field_key ); ?>" checked="checked" />
								<?php endif; ?>
								<strong><?php echo esc_html( $field_meta['label'] ); ?></strong>
							</label>
							<span class="aas-checklist-badge aas-badge-<?php echo esc_attr( $issue['status'] ); ?>">
								<?php
								echo 'ok' === $issue['status']
									? esc_html__( 'مشکلی ندارد', 'arankia-ai-seo' )
									: esc_html( implode( ' — ', $issue['messages'] ) );
								?>
							</span>
						</div>
						<div class="aas-field-compare">
							<div class="aas-field-old">
								<span class="aas-field-col-label"><?php esc_html_e( 'مقدار فعلی', 'arankia-ai-seo' ); ?></span>
								<div class="aas-field-value"><?php echo '' !== $current_val ? esc_html( wp_strip_all_tags( $current_val ) ) : '<em>' . esc_html__( '(خالی)', 'arankia-ai-seo' ) . '</em>'; ?></div>
							</div>
							<div class="aas-field-new">
								<span class="aas-field-col-label"><?php esc_html_e( 'پیشنهاد هوش مصنوعی', 'arankia-ai-seo' ); ?></span>
								<div class="aas-field-value">
									<?php if ( $suggestion ) : ?>
										<?php echo esc_html( wp_strip_all_tags( $suggestion->new_value ) ); ?>
									<?php else : ?>
										<em><?php esc_html_e( 'پیشنهاد جدیدی وجود ندارد.', 'arankia-ai-seo' ); ?></em>
									<?php endif; ?>
								</div>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="aas-review-actions">
				<select class="aas-provider-select-single">
					<?php foreach ( $this->provider_options() as $id => $label ) : ?>
						<option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="button" class="button aas-regenerate"><?php esc_html_e( 'بازتولید با AI', 'arankia-ai-seo' ); ?></button>
				<?php if ( ! empty( $pending_map ) ) : ?>
					<button type="button" class="button aas-reject-all"><?php esc_html_e( 'رد همه پیشنهادها', 'arankia-ai-seo' ); ?></button>
					<button type="button" class="button button-primary aas-apply-selected"><?php esc_html_e( 'اعمال موارد تیک‌خورده', 'arankia-ai-seo' ); ?></button>
				<?php endif; ?>
				<span class="aas-review-result"></span>
			</div>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Settings                                                            */
	/* ------------------------------------------------------------------ */

	private function settings_tabs() {
		return array(
			'general'   => __( 'تنظیمات عمومی', 'arankia-ai-seo' ),
			'providers' => __( 'سرویس‌های هوش مصنوعی', 'arankia-ai-seo' ),
		);
	}

	private function current_settings_tab() {
		$tabs = $this->settings_tabs();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		return isset( $tabs[ $tab ] ) ? $tab : 'general';
	}

	public function maybe_save_settings() {
		if ( ! isset( $_POST['aas_save_tab'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab = sanitize_key( wp_unslash( $_POST['aas_save_tab'] ) );
		if ( ! isset( $this->settings_tabs()[ $tab ] ) ) {
			return;
		}

		check_admin_referer( 'aas_save_' . $tab, 'aas_nonce' );

		if ( 'general' === $tab ) {
			update_option( 'aas_general', $this->sanitize_general() );
		} elseif ( 'providers' === $tab ) {
			update_option( 'aas_providers', $this->sanitize_providers() );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SETTINGS, 'tab' => $tab, 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private function cb( $key ) {
		return isset( $_POST[ $key ] ) ? 1 : 0;
	}

	private function txt( $key, $default = '' ) {
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : $default;
	}

	private function sanitize_general() {
		$defaults       = AAS_Settings::defaults()['aas_general'];
		$enabled_fields = array();
		foreach ( array_keys( AAS_Fields::all() ) as $field_key ) {
			$enabled_fields[ $field_key ] = isset( $_POST['enabled_fields'][ $field_key ] ) ? 1 : 0;
		}

		$provider = $this->txt( 'active_provider', $defaults['active_provider'] );
		if ( ! in_array( $provider, AAS_Provider_Factory::get_ids(), true ) ) {
			$provider = $defaults['active_provider'];
		}

		return array(
			'auto_trigger'             => $this->cb( 'auto_trigger' ),
			'active_provider'          => $provider,
			'enabled_fields'           => $enabled_fields,
			'extra_instructions'       => isset( $_POST['extra_instructions'] ) ? sanitize_textarea_field( wp_unslash( $_POST['extra_instructions'] ) ) : '',
			'bulk_delay_seconds'       => max( 5, absint( $this->txt( 'bulk_delay_seconds', $defaults['bulk_delay_seconds'] ) ) ),
			'delete_data_on_uninstall' => $this->cb( 'delete_data_on_uninstall' ),
		);
	}

	private function sanitize_providers() {
		return array(
			'openai' => array(
				'enabled' => isset( $_POST['openai_enabled'] ) ? 1 : 0,
				'api_key' => $this->txt( 'openai_api_key' ),
				'model'   => $this->txt( 'openai_model', 'gpt-4o-mini' ) ?: 'gpt-4o-mini',
			),
			'anthropic' => array(
				'enabled' => isset( $_POST['anthropic_enabled'] ) ? 1 : 0,
				'api_key' => $this->txt( 'anthropic_api_key' ),
				'model'   => $this->txt( 'anthropic_model', 'claude-3-5-haiku-latest' ) ?: 'claude-3-5-haiku-latest',
			),
			'generic' => array(
				'enabled'            => isset( $_POST['generic_enabled'] ) ? 1 : 0,
				'label'              => $this->txt( 'generic_label' ),
				'base_url'           => isset( $_POST['generic_base_url'] ) ? esc_url_raw( wp_unslash( $_POST['generic_base_url'] ) ) : '',
				'endpoint_path'      => $this->txt( 'generic_endpoint_path', '/chat/completions' ) ?: '/chat/completions',
				'api_key'            => $this->txt( 'generic_api_key' ),
				'model'              => $this->txt( 'generic_model' ),
				'auth_header_name'   => $this->txt( 'generic_auth_header_name', 'Authorization' ) ?: 'Authorization',
				'auth_header_prefix' => isset( $_POST['generic_auth_header_prefix'] ) ? (string) wp_unslash( $_POST['generic_auth_header_prefix'] ) : 'Bearer ',
			),
		);
	}

	public function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tabs = $this->settings_tabs();
		$tab  = $this->current_settings_tab();
		?>
		<div class="wrap aas-admin-wrap">
			<h1><?php esc_html_e( 'تنظیمات سئوی هوشمند', 'arankia-ai-seo' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_SETTINGS, 'tab' => $slug ), admin_url( 'admin.php' ) ) ); ?>" class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<div class="aas-admin-panel">
				<?php
				if ( 'general' === $tab ) {
					$this->render_settings_general();
				} else {
					$this->render_settings_providers();
				}
				?>
			</div>
		</div>
		<?php
	}

	private function render_settings_general() {
		$g = AAS_Settings::get_group( 'aas_general' );
		?>
		<form method="post">
			<?php wp_nonce_field( 'aas_save_general', 'aas_nonce' ); ?>
			<input type="hidden" name="aas_save_tab" value="general" />
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'تولید خودکار برای محصولات جدید', 'arankia-ai-seo' ); ?></th>
					<td>
						<label><input type="checkbox" name="auto_trigger" value="1" <?php checked( 1, (int) $g['auto_trigger'] ); ?> /> <?php esc_html_e( 'وقتی محصول جدیدی منتشر می‌شود، به‌صورت خودکار پیشنهاد سئو تولید شود (نیاز به تایید در صفحه بررسی همچنان باقی است)', 'arankia-ai-seo' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'سرویس هوش مصنوعی پیش‌فرض', 'arankia-ai-seo' ); ?></th>
					<td>
						<select name="active_provider">
							<?php foreach ( $this->provider_options() as $id => $label ) : ?>
								<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $g['active_provider'], $id ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'برای استفاده از هر سرویس، ابتدا آن را در تب «سرویس‌های هوش مصنوعی» فعال و پیکربندی کنید.', 'arankia-ai-seo' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'فیلدهای فعال برای تولید', 'arankia-ai-seo' ); ?></th>
					<td>
						<?php foreach ( AAS_Fields::all() as $field_key => $field_meta ) : ?>
							<label style="display:block;margin-bottom:6px;">
								<input type="checkbox" name="enabled_fields[<?php echo esc_attr( $field_key ); ?>]" value="1" <?php checked( ! empty( $g['enabled_fields'][ $field_key ] ) ); ?> />
								<?php echo esc_html( $field_meta['label'] ); ?>
							</label>
						<?php endforeach; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'دستورالعمل اضافه برای هوش مصنوعی', 'arankia-ai-seo' ); ?></th>
					<td>
						<textarea name="extra_instructions" rows="4" class="large-text"><?php echo esc_textarea( $g['extra_instructions'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'مثلا لحن نوشتار، برند سایت، یا نکات خاص فروشگاه که در همه پیشنهادها رعایت شود.', 'arankia-ai-seo' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'فاصله زمانی بین درخواست‌های دسته‌ای (ثانیه)', 'arankia-ai-seo' ); ?></th>
					<td><input type="number" min="5" name="bulk_delay_seconds" value="<?php echo esc_attr( $g['bulk_delay_seconds'] ); ?>" class="small-text" />
						<p class="description"><?php esc_html_e( 'هنگام تولید سئو برای چند محصول هم‌زمان، برای جلوگیری از محدودیت سرویس هوش مصنوعی استفاده می‌شود.', 'arankia-ai-seo' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'حذف داده‌ها هنگام حذف افزونه', 'arankia-ai-seo' ); ?></th>
					<td><label><input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( 1, (int) $g['delete_data_on_uninstall'] ); ?> /> <?php esc_html_e( 'با حذف افزونه، تمام تنظیمات و پیشنهادهای ذخیره‌شده پاک شود', 'arankia-ai-seo' ); ?></label></td>
				</tr>
			</table>
			<?php submit_button( __( 'ذخیره تنظیمات', 'arankia-ai-seo' ) ); ?>
		</form>
		<?php
	}

	private function render_settings_providers() {
		$p = AAS_Settings::get_group( 'aas_providers' );
		?>
		<form method="post">
			<?php wp_nonce_field( 'aas_save_providers', 'aas_nonce' ); ?>
			<input type="hidden" name="aas_save_tab" value="providers" />

			<h2 class="aas-section-title"><?php esc_html_e( 'OpenAI (ChatGPT)', 'arankia-ai-seo' ); ?></h2>
			<table class="form-table">
				<tr><th scope="row"><?php esc_html_e( 'فعال باشد', 'arankia-ai-seo' ); ?></th><td><input type="checkbox" name="openai_enabled" value="1" <?php checked( 1, (int) $p['openai']['enabled'] ); ?> /></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'کلید API', 'arankia-ai-seo' ); ?></th><td><input type="password" class="regular-text" name="openai_api_key" value="<?php echo esc_attr( $p['openai']['api_key'] ); ?>" autocomplete="off" /></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'مدل', 'arankia-ai-seo' ); ?></th><td><input type="text" class="regular-text" name="openai_model" value="<?php echo esc_attr( $p['openai']['model'] ); ?>" /></td></tr>
			</table>

			<h2 class="aas-section-title"><?php esc_html_e( 'Anthropic (Claude)', 'arankia-ai-seo' ); ?></h2>
			<table class="form-table">
				<tr><th scope="row"><?php esc_html_e( 'فعال باشد', 'arankia-ai-seo' ); ?></th><td><input type="checkbox" name="anthropic_enabled" value="1" <?php checked( 1, (int) $p['anthropic']['enabled'] ); ?> /></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'کلید API', 'arankia-ai-seo' ); ?></th><td><input type="password" class="regular-text" name="anthropic_api_key" value="<?php echo esc_attr( $p['anthropic']['api_key'] ); ?>" autocomplete="off" /></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'مدل', 'arankia-ai-seo' ); ?></th><td><input type="text" class="regular-text" name="anthropic_model" value="<?php echo esc_attr( $p['anthropic']['model'] ); ?>" /></td></tr>
			</table>

			<h2 class="aas-section-title"><?php esc_html_e( 'سرویس هوش مصنوعی سفارشی (ایرانی یا هر سرویس سازگار با OpenAI)', 'arankia-ai-seo' ); ?></h2>
			<table class="form-table">
				<tr><th scope="row"><?php esc_html_e( 'فعال باشد', 'arankia-ai-seo' ); ?></th><td><input type="checkbox" name="generic_enabled" value="1" <?php checked( 1, (int) $p['generic']['enabled'] ); ?> /></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'نام نمایشی', 'arankia-ai-seo' ); ?></th><td><input type="text" class="regular-text" name="generic_label" value="<?php echo esc_attr( $p['generic']['label'] ); ?>" placeholder="<?php esc_attr_e( 'مثلا متیس، آوال، تپ‌سیج و ...', 'arankia-ai-seo' ); ?>" /></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'آدرس پایه (Base URL)', 'arankia-ai-seo' ); ?></th><td><input type="url" class="regular-text" name="generic_base_url" value="<?php echo esc_attr( $p['generic']['base_url'] ); ?>" placeholder="https://api.example.com/v1" /></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'مسیر endpoint', 'arankia-ai-seo' ); ?></th><td><input type="text" class="regular-text" name="generic_endpoint_path" value="<?php echo esc_attr( $p['generic']['endpoint_path'] ); ?>" /><p class="description"><?php esc_html_e( 'برای سرویس‌های سازگار با OpenAI معمولا /chat/completions است.', 'arankia-ai-seo' ); ?></p></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'کلید API', 'arankia-ai-seo' ); ?></th><td><input type="password" class="regular-text" name="generic_api_key" value="<?php echo esc_attr( $p['generic']['api_key'] ); ?>" autocomplete="off" /></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'مدل', 'arankia-ai-seo' ); ?></th><td><input type="text" class="regular-text" name="generic_model" value="<?php echo esc_attr( $p['generic']['model'] ); ?>" /></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'نام هدر احراز هویت', 'arankia-ai-seo' ); ?></th><td><input type="text" class="regular-text" name="generic_auth_header_name" value="<?php echo esc_attr( $p['generic']['auth_header_name'] ); ?>" /></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'پیشوند مقدار هدر', 'arankia-ai-seo' ); ?></th><td><input type="text" class="regular-text" name="generic_auth_header_prefix" value="<?php echo esc_attr( $p['generic']['auth_header_prefix'] ); ?>" placeholder="Bearer " /><p class="description"><?php esc_html_e( 'اگر سرویس شما به‌صورت Authorization: Bearer KEY کار می‌کند مقدار پیش‌فرض را نگه دارید.', 'arankia-ai-seo' ); ?></p></td></tr>
			</table>

			<?php submit_button( __( 'ذخیره تنظیمات', 'arankia-ai-seo' ) ); ?>
		</form>
		<?php
	}
}
