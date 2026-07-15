<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASL_Admin {

	const PAGE_SLUG    = 'arankia-sms-login';
	const ADMIN_NONCE  = 'asl_admin_nonce';

	private $hook_suffix = '';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_save_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'render_notices' ) );
	}

	public function register_menu() {
		$this->hook_suffix = add_menu_page(
			__( 'ورود پیامکی آرانکیا', 'arankia-sms-login' ),
			__( 'ورود پیامکی', 'arankia-sms-login' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-smartphone',
			30
		);
	}

	public function enqueue_assets( $hook ) {
		if ( $hook !== $this->hook_suffix ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'asl-admin', ASL_URL . 'admin/css/admin.css', array(), ASL_VERSION );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_script( 'asl-admin', ASL_URL . 'admin/js/admin.js', array( 'jquery', 'wp-color-picker' ), ASL_VERSION, true );

		wp_localize_script( 'asl-admin', 'ASL_Admin_Data', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( self::ADMIN_NONCE ),
			'i18n'    => array(
				'chooseIcon'  => __( 'انتخاب آیکون', 'arankia-sms-login' ),
				'useThisIcon' => __( 'استفاده از این تصویر', 'arankia-sms-login' ),
				'sending'     => __( 'در حال ارسال...', 'arankia-sms-login' ),
			),
		) );
	}

	private function get_tabs() {
		return array(
			'general'     => __( 'تنظیمات عمومی', 'arankia-sms-login' ),
			'melipayamak' => __( 'اتصال به ملی‌پیامک', 'arankia-sms-login' ),
			'recaptcha'   => __( 'ریکپچای گوگل', 'arankia-sms-login' ),
			'design'      => __( 'طراحی و متن‌ها', 'arankia-sms-login' ),
			'messages'    => __( 'پیام‌های هشدار و خطا', 'arankia-sms-login' ),
		);
	}

	private function current_tab() {
		$tabs = $this->get_tabs();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		return isset( $tabs[ $tab ] ) ? $tab : 'general';
	}

	public function maybe_save_settings() {
		if ( ! isset( $_POST['asl_save_tab'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab = sanitize_key( wp_unslash( $_POST['asl_save_tab'] ) );

		if ( ! isset( $this->get_tabs()[ $tab ] ) ) {
			return;
		}

		check_admin_referer( 'asl_save_' . $tab, 'asl_nonce' );

		$method = 'sanitize_' . $tab;
		if ( method_exists( $this, $method ) ) {
			update_option( 'asl_' . $tab, $this->$method() );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => $tab, 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function render_notices() {
		if ( ! isset( $_GET['page'] ) || self::PAGE_SLUG !== $_GET['page'] || empty( $_GET['updated'] ) ) {
			return;
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'تنظیمات با موفقیت ذخیره شد.', 'arankia-sms-login' ) . '</p></div>';
	}

	/* ------------------------------------------------------------------ */
	/* Sanitizers                                                          */
	/* ------------------------------------------------------------------ */

	private function cb( $key ) {
		return isset( $_POST[ $key ] ) ? 1 : 0;
	}

	private function txt( $key, $default = '' ) {
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : $default;
	}

	private function sanitize_general() {
		$defaults = ASL_Settings::defaults()['asl_general'];

		return array(
			'enable_panel'             => $this->cb( 'enable_panel' ),
			'allow_password_login'     => $this->cb( 'allow_password_login' ),
			'allow_register'           => $this->cb( 'allow_register' ),
			'auto_register_on_login'   => $this->cb( 'auto_register_on_login' ),
			'mobile_meta_key'          => $this->txt( 'mobile_meta_key', $defaults['mobile_meta_key'] ) ?: $defaults['mobile_meta_key'],
			'fallback_meta_keys'       => $this->txt( 'fallback_meta_keys', $defaults['fallback_meta_keys'] ),
			'redirect_after_login'     => isset( $_POST['redirect_after_login'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_after_login'] ) ) : '',
			'replace_wp_login'         => $this->cb( 'replace_wp_login' ),
			'login_page_id'            => isset( $_POST['login_page_id'] ) ? absint( $_POST['login_page_id'] ) : 0,
			'delete_data_on_uninstall' => $this->cb( 'delete_data_on_uninstall' ),
		);
	}

	private function sanitize_melipayamak() {
		$method = isset( $_POST['connection_method'] ) && 'apikey' === $_POST['connection_method'] ? 'apikey' : 'webservice';

		return array(
			'connection_method'     => $method,
			'username'              => $this->txt( 'username' ),
			'password'              => $this->txt( 'password' ),
			'api_key'               => $this->txt( 'api_key' ),
			'sender_number'         => $this->txt( 'sender_number' ),
			'otp_body_id'           => $this->txt( 'otp_body_id' ),
			'code_length'           => max( 4, min( 8, absint( $this->txt( 'code_length', 5 ) ) ) ),
			'code_expiry'           => max( 30, absint( $this->txt( 'code_expiry', 120 ) ) ),
			'resend_wait'           => max( 20, absint( $this->txt( 'resend_wait', 90 ) ) ),
			'max_attempts'          => max( 1, absint( $this->txt( 'max_attempts', 5 ) ) ),
			'max_requests_per_hour' => max( 1, absint( $this->txt( 'max_requests_per_hour', 10 ) ) ),
		);
	}

	private function sanitize_recaptcha() {
		$version = isset( $_POST['version'] ) && 'v3' === $_POST['version'] ? 'v3' : 'v2';
		$threshold = isset( $_POST['v3_threshold'] ) ? (float) $_POST['v3_threshold'] : 0.5;

		return array(
			'enabled'              => $this->cb( 'enabled' ),
			'version'              => $version,
			'site_key'             => $this->txt( 'site_key' ),
			'secret_key'           => $this->txt( 'secret_key' ),
			'v3_threshold'         => max( 0, min( 1, $threshold ) ),
			'apply_send_otp'       => $this->cb( 'apply_send_otp' ),
			'apply_password_login' => $this->cb( 'apply_password_login' ),
		);
	}

	private function sanitize_design() {
		$defaults = ASL_Settings::defaults()['asl_design'];

		$primary   = $this->txt( 'primary_color' );
		$secondary = $this->txt( 'secondary_color' );

		$fields = array(
			'icon_url'                => isset( $_POST['icon_url'] ) ? esc_url_raw( wp_unslash( $_POST['icon_url'] ) ) : '',
			'primary_color'           => sanitize_hex_color( $primary ) ?: $defaults['primary_color'],
			'secondary_color'         => sanitize_hex_color( $secondary ) ?: $defaults['secondary_color'],
			'font_family'             => $this->txt( 'font_family' ),
			'show_powered_by'         => $this->cb( 'show_powered_by' ),
		);

		foreach ( array(
			'title_login', 'subtitle_login', 'title_register', 'subtitle_register',
			'tab_label_login', 'tab_label_register', 'placeholder_mobile', 'placeholder_code',
			'placeholder_password', 'placeholder_name', 'btn_send_code', 'btn_verify',
			'btn_register', 'btn_password_login', 'link_use_password', 'link_use_otp',
			'link_resend', 'text_switch_to_register', 'text_switch_to_login', 'powered_by_text',
		) as $key ) {
			$fields[ $key ] = $this->txt( $key, $defaults[ $key ] ) ?: $defaults[ $key ];
		}

		return $fields;
	}

	private function sanitize_messages() {
		$defaults = ASL_Settings::defaults()['asl_messages'];
		$fields   = array();

		foreach ( $defaults as $key => $default_value ) {
			$value = isset( $_POST[ $key ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) : '';
			$fields[ $key ] = '' !== $value ? $value : $default_value;
		}

		return $fields;
	}

	/* ------------------------------------------------------------------ */
	/* Rendering                                                           */
	/* ------------------------------------------------------------------ */

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tabs = $this->get_tabs();
		$tab  = $this->current_tab();
		?>
		<div class="wrap asl-admin-wrap">
			<div class="asl-admin-header">
				<div class="asl-admin-brand">
					<span class="dashicons dashicons-smartphone"></span>
					<div>
						<h1><?php esc_html_e( 'ورود و ثبت‌نام پیامکی آرانکیا', 'arankia-sms-login' ); ?></h1>
						<p><?php esc_html_e( 'مدیریت ورود پیامکی، اتصال به ملی‌پیامک، ریکپچا و طراحی صفحه ورود.', 'arankia-sms-login' ); ?></p>
					</div>
				</div>
				<code class="asl-shortcode-hint">[arankia_sms_login]</code>
			</div>

			<nav class="asl-admin-tabs nav-tab-wrapper">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => $slug ), admin_url( 'admin.php' ) ) ); ?>"
						class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="asl-admin-panel">
				<?php
				$method = 'render_tab_' . $tab;
				$this->$method();
				?>
			</div>
		</div>
		<?php
	}

	private function field_row( $label, $content_callback, $desc = '' ) {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
		call_user_func( $content_callback );
		if ( $desc ) {
			echo '<p class="description">' . esc_html( $desc ) . '</p>';
		}
		echo '</td></tr>';
	}

	private function checkbox( $key, $value, $label = '' ) {
		printf(
			'<label class="asl-switch"><input type="checkbox" name="%1$s" value="1" %2$s /><span class="asl-switch-slider"></span></label> %3$s',
			esc_attr( $key ),
			checked( 1, (int) $value, false ),
			esc_html( $label )
		);
	}

	private function text_input( $key, $value, $type = 'text', $extra = '' ) {
		printf(
			'<input type="%1$s" name="%2$s" value="%3$s" class="regular-text" %4$s />',
			esc_attr( $type ),
			esc_attr( $key ),
			esc_attr( $value ),
			$extra
		);
	}

	private function render_tab_general() {
		$g = ASL_Settings::get_group( 'asl_general' );
		?>
		<form method="post">
			<?php wp_nonce_field( 'asl_save_general', 'asl_nonce' ); ?>
			<input type="hidden" name="asl_save_tab" value="general" />
			<table class="form-table">
				<?php
				$this->field_row( __( 'فعال‌سازی پنل ورود پیامکی', 'arankia-sms-login' ), function () use ( $g ) {
					$this->checkbox( 'enable_panel', $g['enable_panel'], __( 'فعال باشد', 'arankia-sms-login' ) );
				}, __( 'در صورت غیرفعال بودن، فرم ورود پیامکی نمایش داده نمی‌شود.', 'arankia-sms-login' ) );

				$this->field_row( __( 'ورود با رمز عبور', 'arankia-sms-login' ), function () use ( $g ) {
					$this->checkbox( 'allow_password_login', $g['allow_password_login'], __( 'به کاربران اجازه ورود با رمز عبور نیز داده شود', 'arankia-sms-login' ) );
				} );

				$this->field_row( __( 'امکان ثبت‌نام', 'arankia-sms-login' ), function () use ( $g ) {
					$this->checkbox( 'allow_register', $g['allow_register'], __( 'نمایش تب ثبت‌نام در صفحه ورود', 'arankia-sms-login' ) );
				} );

				$this->field_row( __( 'ثبت‌نام خودکار هنگام ورود', 'arankia-sms-login' ), function () use ( $g ) {
					$this->checkbox( 'auto_register_on_login', $g['auto_register_on_login'], __( 'اگر شماره موبایل در سایت ثبت نبود، هنگام ورود با پیامک به‌صورت خودکار حساب بسازد', 'arankia-sms-login' ) );
				} );

				$this->field_row( __( 'کلید متا شماره موبایل', 'arankia-sms-login' ), function () use ( $g ) {
					$this->text_input( 'mobile_meta_key', $g['mobile_meta_key'] );
				}, __( 'نام فیلد usermeta که شماره موبایل کاربر در آن ذخیره می‌شود.', 'arankia-sms-login' ) );

				$this->field_row( __( 'کلیدهای متای جایگزین برای جستجو', 'arankia-sms-login' ), function () use ( $g ) {
					$this->text_input( 'fallback_meta_keys', $g['fallback_meta_keys'] );
				}, __( 'برای سازگاری با ووکامرس مثلا billing_phone؛ چند مورد را با ویرگول جدا کنید.', 'arankia-sms-login' ) );

				$this->field_row( __( 'آدرس بازگشت پس از ورود', 'arankia-sms-login' ), function () use ( $g ) {
					$this->text_input( 'redirect_after_login', $g['redirect_after_login'], 'url' );
				}, __( 'خالی بگذارید تا کاربر به صفحه اصلی سایت هدایت شود.', 'arankia-sms-login' ) );

				$this->field_row( __( 'جایگزینی صفحه ورود وردپرس', 'arankia-sms-login' ), function () use ( $g ) {
					$this->checkbox( 'replace_wp_login', $g['replace_wp_login'], __( 'هدایت wp-login.php به صفحه ورود پیامکی', 'arankia-sms-login' ) );
					echo '<p class="description" style="margin-top:8px;">';
					if ( ! empty( $g['login_page_id'] ) && get_post( $g['login_page_id'] ) ) {
						printf(
							/* translators: %s: page edit link */
							esc_html__( 'صفحه ورود فعلی: %s', 'arankia-sms-login' ),
							'<a href="' . esc_url( get_edit_post_link( $g['login_page_id'] ) ) . '">' . esc_html( get_the_title( $g['login_page_id'] ) ) . '</a>'
						);
					}
					echo '</p>';
				} );

				$this->field_row( __( 'حذف تنظیمات هنگام حذف افزونه', 'arankia-sms-login' ), function () use ( $g ) {
					$this->checkbox( 'delete_data_on_uninstall', $g['delete_data_on_uninstall'], __( 'با حذف افزونه، تمام تنظیمات پاک شود', 'arankia-sms-login' ) );
				} );
				?>
			</table>
			<?php submit_button( __( 'ذخیره تنظیمات', 'arankia-sms-login' ) ); ?>
		</form>
		<?php
	}

	private function render_tab_melipayamak() {
		$m = ASL_Settings::get_group( 'asl_melipayamak' );
		?>
		<form method="post">
			<?php wp_nonce_field( 'asl_save_melipayamak', 'asl_nonce' ); ?>
			<input type="hidden" name="asl_save_tab" value="melipayamak" />
			<table class="form-table">
				<?php
				$this->field_row( __( 'روش اتصال', 'arankia-sms-login' ), function () use ( $m ) {
					?>
					<select name="connection_method">
						<option value="webservice" <?php selected( $m['connection_method'], 'webservice' ); ?>><?php esc_html_e( 'نام کاربری و رمز عبور (وب‌سرویس REST)', 'arankia-sms-login' ); ?></option>
						<option value="apikey" <?php selected( $m['connection_method'], 'apikey' ); ?>><?php esc_html_e( 'کلید API (پنل کنسول ملی‌پیامک)', 'arankia-sms-login' ); ?></option>
					</select>
					<?php
				} );

				$this->field_row( __( 'نام کاربری', 'arankia-sms-login' ), function () use ( $m ) {
					$this->text_input( 'username', $m['username'] );
				} );

				$this->field_row( __( 'رمز عبور', 'arankia-sms-login' ), function () use ( $m ) {
					$this->text_input( 'password', $m['password'], 'password' );
				} );

				$this->field_row( __( 'کلید API', 'arankia-sms-login' ), function () use ( $m ) {
					$this->text_input( 'api_key', $m['api_key'] );
				}, __( 'در صورت استفاده از روش کلید API از پنل کنسول ملی‌پیامک.', 'arankia-sms-login' ) );

				$this->field_row( __( 'شماره خط فرستنده', 'arankia-sms-login' ), function () use ( $m ) {
					$this->text_input( 'sender_number', $m['sender_number'] );
				} );

				$this->field_row( __( 'شناسه پترن (bodyId) کد تایید', 'arankia-sms-login' ), function () use ( $m ) {
					$this->text_input( 'otp_body_id', $m['otp_body_id'] );
				}, __( 'برای ارسال مطمئن‌تر و بدون فیلتر شدن، از پترن OTP تایید شده ملی‌پیامک با یک متغیر (کد) استفاده کنید.', 'arankia-sms-login' ) );

				$this->field_row( __( 'طول کد تایید', 'arankia-sms-login' ), function () use ( $m ) {
					$this->text_input( 'code_length', $m['code_length'], 'number', 'min="4" max="8"' );
				} );

				$this->field_row( __( 'زمان اعتبار کد (ثانیه)', 'arankia-sms-login' ), function () use ( $m ) {
					$this->text_input( 'code_expiry', $m['code_expiry'], 'number', 'min="30"' );
				} );

				$this->field_row( __( 'فاصله زمانی ارسال مجدد (ثانیه)', 'arankia-sms-login' ), function () use ( $m ) {
					$this->text_input( 'resend_wait', $m['resend_wait'], 'number', 'min="20"' );
				} );

				$this->field_row( __( 'حداکثر تلاش برای وارد کردن کد', 'arankia-sms-login' ), function () use ( $m ) {
					$this->text_input( 'max_attempts', $m['max_attempts'], 'number', 'min="1"' );
				} );

				$this->field_row( __( 'حداکثر درخواست کد در ساعت (به ازای هر شماره)', 'arankia-sms-login' ), function () use ( $m ) {
					$this->text_input( 'max_requests_per_hour', $m['max_requests_per_hour'], 'number', 'min="1"' );
				} );
				?>
			</table>
			<?php submit_button( __( 'ذخیره تنظیمات', 'arankia-sms-login' ) ); ?>
		</form>

		<div class="asl-test-sms-box">
			<h3><?php esc_html_e( 'ارسال پیامک تست', 'arankia-sms-login' ); ?></h3>
			<p class="description"><?php esc_html_e( 'برای اطمینان از صحت تنظیمات فوق (پس از ذخیره)، یک پیامک تست ارسال کنید.', 'arankia-sms-login' ); ?></p>
			<input type="tel" id="asl-test-mobile" placeholder="09xxxxxxxxx" maxlength="11" class="regular-text" />
			<button type="button" class="button button-secondary" id="asl-test-send-btn"><?php esc_html_e( 'ارسال پیامک تست', 'arankia-sms-login' ); ?></button>
			<span id="asl-test-result"></span>
		</div>
		<?php
	}

	private function render_tab_recaptcha() {
		$r = ASL_Settings::get_group( 'asl_recaptcha' );
		?>
		<form method="post">
			<?php wp_nonce_field( 'asl_save_recaptcha', 'asl_nonce' ); ?>
			<input type="hidden" name="asl_save_tab" value="recaptcha" />
			<table class="form-table">
				<?php
				$this->field_row( __( 'فعال‌سازی ریکپچا', 'arankia-sms-login' ), function () use ( $r ) {
					$this->checkbox( 'enabled', $r['enabled'], __( 'فعال باشد', 'arankia-sms-login' ) );
				} );

				$this->field_row( __( 'نسخه ریکپچا', 'arankia-sms-login' ), function () use ( $r ) {
					?>
					<select name="version">
						<option value="v2" <?php selected( $r['version'], 'v2' ); ?>><?php esc_html_e( 'نسخه ۲ (چک‌باکس «من ربات نیستم»)', 'arankia-sms-login' ); ?></option>
						<option value="v3" <?php selected( $r['version'], 'v3' ); ?>><?php esc_html_e( 'نسخه ۳ (نامرئی)', 'arankia-sms-login' ); ?></option>
					</select>
					<?php
				} );

				$this->field_row( __( 'کلید سایت (Site Key)', 'arankia-sms-login' ), function () use ( $r ) {
					$this->text_input( 'site_key', $r['site_key'] );
				} );

				$this->field_row( __( 'کلید محرمانه (Secret Key)', 'arankia-sms-login' ), function () use ( $r ) {
					$this->text_input( 'secret_key', $r['secret_key'], 'password' );
				} );

				$this->field_row( __( 'حداقل امتیاز مجاز (فقط نسخه ۳)', 'arankia-sms-login' ), function () use ( $r ) {
					$this->text_input( 'v3_threshold', $r['v3_threshold'], 'number', 'min="0" max="1" step="0.1"' );
				} );

				$this->field_row( __( 'اعمال روی فرم دریافت کد پیامکی', 'arankia-sms-login' ), function () use ( $r ) {
					$this->checkbox( 'apply_send_otp', $r['apply_send_otp'] );
				} );

				$this->field_row( __( 'اعمال روی فرم ورود با رمز عبور', 'arankia-sms-login' ), function () use ( $r ) {
					$this->checkbox( 'apply_password_login', $r['apply_password_login'] );
				} );
				?>
			</table>
			<?php submit_button( __( 'ذخیره تنظیمات', 'arankia-sms-login' ) ); ?>
		</form>
		<?php
	}

	private function render_tab_design() {
		$d = ASL_Settings::get_group( 'asl_design' );
		?>
		<form method="post">
			<?php wp_nonce_field( 'asl_save_design', 'asl_nonce' ); ?>
			<input type="hidden" name="asl_save_tab" value="design" />

			<h2 class="asl-section-title"><?php esc_html_e( 'ظاهر', 'arankia-sms-login' ); ?></h2>
			<table class="form-table">
				<?php
				$this->field_row( __( 'آیکون صفحه ورود', 'arankia-sms-login' ), function () use ( $d ) {
					?>
					<div class="asl-media-field">
						<img id="asl-icon-preview" src="<?php echo esc_url( $d['icon_url'] ); ?>" style="<?php echo $d['icon_url'] ? '' : 'display:none;'; ?>" />
						<input type="hidden" name="icon_url" id="asl-icon-url" value="<?php echo esc_attr( $d['icon_url'] ); ?>" />
						<button type="button" class="button" id="asl-icon-select"><?php esc_html_e( 'انتخاب تصویر', 'arankia-sms-login' ); ?></button>
						<button type="button" class="button" id="asl-icon-remove" <?php echo $d['icon_url'] ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'حذف تصویر', 'arankia-sms-login' ); ?></button>
					</div>
					<?php
				}, __( 'در صورت عدم انتخاب، آیکون پیش‌فرض نمایش داده می‌شود.', 'arankia-sms-login' ) );

				$this->field_row( __( 'رنگ اصلی', 'arankia-sms-login' ), function () use ( $d ) {
					printf( '<input type="text" class="asl-color-field" name="primary_color" value="%s" />', esc_attr( $d['primary_color'] ) );
				} );

				$this->field_row( __( 'رنگ دوم (گرادینت)', 'arankia-sms-login' ), function () use ( $d ) {
					printf( '<input type="text" class="asl-color-field" name="secondary_color" value="%s" />', esc_attr( $d['secondary_color'] ) );
				} );

				$this->field_row( __( 'فونت اختصاصی (اختیاری)', 'arankia-sms-login' ), function () use ( $d ) {
					$this->text_input( 'font_family', $d['font_family'] );
				}, __( 'مثال: Vazirmatn, Tahoma, sans-serif — خالی بگذارید تا فونت قالب سایت استفاده شود.', 'arankia-sms-login' ) );

				$this->field_row( __( 'نمایش برند در پایین فرم', 'arankia-sms-login' ), function () use ( $d ) {
					$this->checkbox( 'show_powered_by', $d['show_powered_by'] );
				} );

				$this->field_row( __( 'متن برند پایین فرم', 'arankia-sms-login' ), function () use ( $d ) {
					$this->text_input( 'powered_by_text', $d['powered_by_text'] );
				} );
				?>
			</table>

			<h2 class="asl-section-title"><?php esc_html_e( 'متن‌های صفحه ورود', 'arankia-sms-login' ); ?></h2>
			<table class="form-table">
				<?php
				$labels = array(
					'title_login'             => __( 'عنوان تب ورود', 'arankia-sms-login' ),
					'subtitle_login'          => __( 'زیرعنوان تب ورود', 'arankia-sms-login' ),
					'title_register'          => __( 'عنوان تب ثبت‌نام', 'arankia-sms-login' ),
					'subtitle_register'       => __( 'زیرعنوان تب ثبت‌نام', 'arankia-sms-login' ),
					'tab_label_login'         => __( 'برچسب تب ورود', 'arankia-sms-login' ),
					'tab_label_register'      => __( 'برچسب تب ثبت‌نام', 'arankia-sms-login' ),
					'placeholder_mobile'      => __( 'متن راهنمای شماره موبایل', 'arankia-sms-login' ),
					'placeholder_code'        => __( 'متن راهنمای کد تایید', 'arankia-sms-login' ),
					'placeholder_password'    => __( 'متن راهنمای رمز عبور', 'arankia-sms-login' ),
					'placeholder_name'        => __( 'متن راهنمای نام (ثبت‌نام)', 'arankia-sms-login' ),
					'btn_send_code'           => __( 'متن دکمه دریافت کد', 'arankia-sms-login' ),
					'btn_verify'              => __( 'متن دکمه تایید ورود', 'arankia-sms-login' ),
					'btn_register'            => __( 'متن دکمه ثبت‌نام', 'arankia-sms-login' ),
					'btn_password_login'      => __( 'متن دکمه ورود با رمز عبور', 'arankia-sms-login' ),
					'link_use_password'       => __( 'متن لینک «ورود با رمز عبور»', 'arankia-sms-login' ),
					'link_use_otp'            => __( 'متن لینک «ورود با پیامک»', 'arankia-sms-login' ),
					'link_resend'             => __( 'متن لینک ارسال مجدد کد', 'arankia-sms-login' ),
					'text_switch_to_register' => __( 'متن سوییچ به ثبت‌نام', 'arankia-sms-login' ),
					'text_switch_to_login'    => __( 'متن سوییچ به ورود', 'arankia-sms-login' ),
				);
				foreach ( $labels as $key => $label ) {
					$this->field_row( $label, function () use ( $key, $d ) {
						$this->text_input( $key, $d[ $key ] );
					} );
				}
				?>
			</table>
			<?php submit_button( __( 'ذخیره تنظیمات', 'arankia-sms-login' ) ); ?>
		</form>
		<?php
	}

	private function render_tab_messages() {
		$msg = ASL_Settings::get_group( 'asl_messages' );
		$labels = array(
			'invalid_mobile'          => __( 'شماره موبایل نامعتبر', 'arankia-sms-login' ),
			'otp_sent'                => __( 'ارسال موفق کد', 'arankia-sms-login' ),
			'otp_send_failed'         => __( 'خطا در ارسال پیامک', 'arankia-sms-login' ),
			'invalid_code'            => __( 'کد تایید نادرست', 'arankia-sms-login' ),
			'code_expired'            => __( 'انقضای کد تایید', 'arankia-sms-login' ),
			'too_many_requests'       => __( 'تعداد درخواست بیش از حد', 'arankia-sms-login' ),
			'resend_wait'             => __( 'محدودیت ارسال مجدد (از {sec} برای ثانیه استفاده کنید)', 'arankia-sms-login' ),
			'recaptcha_failed'        => __( 'خطای ریکپچا', 'arankia-sms-login' ),
			'user_exists'             => __( 'کاربر قبلا ثبت‌نام کرده', 'arankia-sms-login' ),
			'user_not_found'          => __( 'کاربر یافت نشد', 'arankia-sms-login' ),
			'wrong_password'          => __( 'رمز عبور اشتباه', 'arankia-sms-login' ),
			'login_success'           => __( 'پیام موفقیت ورود', 'arankia-sms-login' ),
			'register_success'        => __( 'پیام موفقیت ثبت‌نام', 'arankia-sms-login' ),
			'generic_error'           => __( 'خطای عمومی', 'arankia-sms-login' ),
			'panel_disabled'          => __( 'پنل غیرفعال است', 'arankia-sms-login' ),
			'password_login_disabled' => __( 'ورود با رمز عبور غیرفعال است', 'arankia-sms-login' ),
			'register_disabled'       => __( 'ثبت‌نام غیرفعال است', 'arankia-sms-login' ),
		);
		?>
		<form method="post">
			<?php wp_nonce_field( 'asl_save_messages', 'asl_nonce' ); ?>
			<input type="hidden" name="asl_save_tab" value="messages" />
			<table class="form-table">
				<?php foreach ( $labels as $key => $label ) : ?>
					<?php
					$this->field_row( $label, function () use ( $key, $msg ) {
						printf(
							'<textarea name="%1$s" class="large-text" rows="2">%2$s</textarea>',
							esc_attr( $key ),
							esc_textarea( $msg[ $key ] )
						);
					} );
					?>
				<?php endforeach; ?>
			</table>
			<?php submit_button( __( 'ذخیره تنظیمات', 'arankia-sms-login' ) ); ?>
		</form>
		<?php
	}
}
