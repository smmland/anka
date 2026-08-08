<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASL_Shortcode {

	private static $should_enqueue = false;

	public function __construct() {
		add_shortcode( 'arankia_sms_login', array( $this, 'render' ) );
		add_action( 'wp', array( $this, 'detect_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'login_init', array( $this, 'maybe_redirect_wp_login' ) );
	}

	public function detect_shortcode() {
		if ( is_singular() ) {
			$post = get_post();
			if ( $post && has_shortcode( $post->post_content, 'arankia_sms_login' ) ) {
				self::$should_enqueue = true;
			}
		}
	}

	public function maybe_redirect_wp_login() {
		$general = ASL_Settings::get_group( 'asl_general' );

		if ( empty( $general['replace_wp_login'] ) || empty( $general['login_page_id'] ) ) {
			return;
		}

		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : 'login';

		$allowed_actions = array( 'logout', 'lostpassword', 'rp', 'resetpass', 'postpass' );
		if ( in_array( $action, $allowed_actions, true ) || is_user_logged_in() ) {
			return;
		}

		$page_url = get_permalink( $general['login_page_id'] );
		if ( ! $page_url ) {
			return;
		}

		wp_safe_redirect( $page_url );
		exit;
	}

	public function enqueue_assets() {
		if ( ! self::$should_enqueue ) {
			return;
		}

		wp_enqueue_style( 'asl-public', ASL_URL . 'public/css/login.css', array(), ASL_VERSION );
		wp_enqueue_script( 'asl-public', ASL_URL . 'public/js/login.js', array(), ASL_VERSION, true );

		$recaptcha = ASL_Settings::get_group( 'asl_recaptcha' );

		if ( ! empty( $recaptcha['enabled'] ) && ! empty( $recaptcha['site_key'] ) ) {
			$src = 'v3' === $recaptcha['version']
				? 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $recaptcha['site_key'] )
				: 'https://www.google.com/recaptcha/api.js?hl=fa&render=explicit&onload=aslRecaptchaOnload';
			wp_enqueue_script( 'asl-recaptcha', $src, array( 'asl-public' ), null, true );
		}

		wp_localize_script( 'asl-public', 'ASL_Data', array(
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( ASL_Ajax::NONCE_ACTION ),
			'messages'  => array(
				'invalidMobile' => ASL_Settings::message( 'invalid_mobile' ),
				'genericError'  => ASL_Settings::message( 'generic_error' ),
			),
			'recaptcha' => array(
				'enabled'        => ! empty( $recaptcha['enabled'] ) && ! empty( $recaptcha['site_key'] ),
				'version'        => $recaptcha['version'],
				'siteKey'        => $recaptcha['site_key'],
				'onSendOtp'      => ASL_Recaptcha::is_enabled_for( 'send_otp' ),
				'onPasswordLogin'=> ASL_Recaptcha::is_enabled_for( 'password_login' ),
			),
		) );
	}

	public function render( $atts ) {
		$atts = shortcode_atts( array(
			'mode' => 'both', // both | login | register
		), $atts, 'arankia_sms_login' );

		self::$should_enqueue = true;
		$this->enqueue_assets();

		if ( is_user_logged_in() ) {
			return $this->render_logged_in_notice();
		}

		$general  = ASL_Settings::get_group( 'asl_general' );
		$design   = ASL_Settings::get_group( 'asl_design' );

		if ( empty( $general['enable_panel'] ) ) {
			return '<div class="asl-card asl-disabled-notice">' . esc_html( ASL_Settings::message( 'panel_disabled' ) ) . '</div>';
		}

		$show_register = ( 'both' === $atts['mode'] || 'register' === $atts['mode'] ) && ! empty( $general['allow_register'] );
		$show_login    = ( 'both' === $atts['mode'] || 'login' === $atts['mode'] );
		$show_tabs     = $show_register && $show_login;
		$show_password = ! empty( $general['allow_password_login'] );

		$style_vars = sprintf(
			'--asl-primary:%s;--asl-secondary:%s;%s',
			esc_attr( $design['primary_color'] ),
			esc_attr( $design['secondary_color'] ),
			$design['font_family'] ? 'font-family:' . esc_attr( $design['font_family'] ) . ';' : ''
		);

		ob_start();
		?>
		<div class="asl-wrapper" dir="rtl">
			<div class="asl-card" style="<?php echo esc_attr( $style_vars ); ?>">

				<div class="asl-card-icon">
					<?php if ( ! empty( $design['icon_url'] ) ) : ?>
						<img src="<?php echo esc_url( $design['icon_url'] ); ?>" alt="<?php esc_attr_e( 'آرانکیا', 'arankia-sms-login' ); ?>" />
					<?php else : ?>
						<span class="asl-icon-default" aria-hidden="true">
							<svg viewBox="0 0 24 24" width="34" height="34" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M17 2H7C5.9 2 5 2.9 5 4v16c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2Zm-5 19c-.83 0-1.5-.67-1.5-1.5S11.17 18 12 18s1.5.67 1.5 1.5S12.83 21 12 21ZM17 17H7V5h10v12Z" fill="currentColor"/>
							</svg>
						</span>
					<?php endif; ?>
				</div>

				<?php if ( $show_tabs ) : ?>
				<div class="asl-tabs" role="tablist">
					<button type="button" class="asl-tab is-active" data-target="login" role="tab" aria-selected="true"><?php echo esc_html( $design['tab_label_login'] ); ?></button>
					<button type="button" class="asl-tab" data-target="register" role="tab" aria-selected="false"><?php echo esc_html( $design['tab_label_register'] ); ?></button>
					<span class="asl-tab-indicator" aria-hidden="true"></span>
				</div>
				<?php endif; ?>

				<div class="asl-alert" role="alert" aria-live="polite" hidden></div>

				<?php if ( $show_login ) : ?>
				<section class="asl-panel is-active" data-panel="login">
					<h2 class="asl-title"><?php echo esc_html( $design['title_login'] ); ?></h2>
					<p class="asl-subtitle"><?php echo esc_html( $design['subtitle_login'] ); ?></p>

					<form class="asl-form" data-form="login-otp" novalidate>
						<?php wp_nonce_field( ASL_Ajax::NONCE_ACTION, '_asl_nonce', false ); ?>
						<div class="asl-field">
							<input type="tel" inputmode="numeric" name="mobile" class="asl-input asl-mobile-input" placeholder="<?php echo esc_attr( $design['placeholder_mobile'] ); ?>" maxlength="11" required />
						</div>
						<div class="asl-field asl-otp-row" hidden>
							<input type="text" inputmode="numeric" name="code" class="asl-input asl-code-input" placeholder="<?php echo esc_attr( $design['placeholder_code'] ); ?>" maxlength="8" />
							<button type="button" class="asl-resend-btn" disabled><?php echo esc_html( $design['link_resend'] ); ?></button>
						</div>
						<div class="asl-recaptcha-holder" data-form-recaptcha="send_otp" hidden></div>
						<button type="submit" class="asl-btn asl-btn-primary" data-step="request"><?php echo esc_html( $design['btn_send_code'] ); ?></button>
					</form>

					<?php if ( $show_password ) : ?>
					<form class="asl-form" data-form="login-password" hidden novalidate>
						<?php wp_nonce_field( ASL_Ajax::NONCE_ACTION, '_asl_nonce', false ); ?>
						<div class="asl-field">
							<input type="tel" inputmode="numeric" name="mobile" class="asl-input asl-mobile-input" placeholder="<?php echo esc_attr( $design['placeholder_mobile'] ); ?>" maxlength="11" required />
						</div>
						<div class="asl-field">
							<input type="password" name="password" class="asl-input" placeholder="<?php echo esc_attr( $design['placeholder_password'] ); ?>" required />
						</div>
						<div class="asl-recaptcha-holder" data-form-recaptcha="password_login" hidden></div>
						<button type="submit" class="asl-btn asl-btn-primary"><?php echo esc_html( $design['btn_password_login'] ); ?></button>
					</form>

					<button type="button" class="asl-link-switch" data-switch-method>
						<span class="asl-show-on-otp"><?php echo esc_html( $design['link_use_password'] ); ?></span>
						<span class="asl-show-on-password" hidden><?php echo esc_html( $design['link_use_otp'] ); ?></span>
					</button>
					<?php endif; ?>

					<?php if ( $show_tabs ) : ?>
					<p class="asl-switch-text"><button type="button" class="asl-link-tab" data-target="register"><?php echo esc_html( $design['text_switch_to_register'] ); ?></button></p>
					<?php endif; ?>
				</section>
				<?php endif; ?>

				<?php if ( $show_register ) : ?>
				<section class="asl-panel<?php echo $show_login ? '' : ' is-active'; ?>" data-panel="register">
					<h2 class="asl-title"><?php echo esc_html( $design['title_register'] ); ?></h2>
					<p class="asl-subtitle"><?php echo esc_html( $design['subtitle_register'] ); ?></p>

					<form class="asl-form" data-form="register-otp" novalidate>
						<?php wp_nonce_field( ASL_Ajax::NONCE_ACTION, '_asl_nonce', false ); ?>
						<div class="asl-field">
							<input type="text" name="name" class="asl-input" placeholder="<?php echo esc_attr( $design['placeholder_name'] ); ?>" />
						</div>
						<div class="asl-field">
							<input type="tel" inputmode="numeric" name="mobile" class="asl-input asl-mobile-input" placeholder="<?php echo esc_attr( $design['placeholder_mobile'] ); ?>" maxlength="11" required />
						</div>
						<div class="asl-field asl-otp-row" hidden>
							<input type="text" inputmode="numeric" name="code" class="asl-input asl-code-input" placeholder="<?php echo esc_attr( $design['placeholder_code'] ); ?>" maxlength="8" />
							<button type="button" class="asl-resend-btn" disabled><?php echo esc_html( $design['link_resend'] ); ?></button>
						</div>
						<div class="asl-recaptcha-holder" data-form-recaptcha="send_otp" hidden></div>

						<?php if ( ! empty( $general['enable_terms'] ) ) : ?>
						<div class="asl-field asl-terms-row">
							<?php if ( empty( $general['terms_text_mode'] ) ) : ?>
							<label class="asl-terms-label">
								<input type="checkbox" name="terms_accepted" value="1" class="asl-terms-checkbox" required />
								<span><?php echo $this->render_terms_html( $design ); // phpcs:ignore -- pre-escaped in render_terms_html ?></span>
							</label>
							<?php else : ?>
							<div class="asl-terms-text">
								<input type="hidden" name="terms_accepted" value="1" />
								<?php echo $this->render_terms_html( $design ); // phpcs:ignore -- pre-escaped in render_terms_html ?>
							</div>
							<?php endif; ?>
						</div>
						<?php endif; ?>

						<button type="submit" class="asl-btn asl-btn-primary" data-step="request"><?php echo esc_html( $design['btn_register'] ); ?></button>
					</form>

					<?php if ( $show_tabs ) : ?>
					<p class="asl-switch-text"><button type="button" class="asl-link-tab" data-target="login"><?php echo esc_html( $design['text_switch_to_login'] ); ?></button></p>
					<?php endif; ?>
				</section>
				<?php endif; ?>

				<?php if ( ! empty( $design['show_powered_by'] ) ) : ?>
				<div class="asl-powered-by"><?php echo esc_html( $design['powered_by_text'] ); ?></div>
				<?php endif; ?>

			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Builds the (already-escaped) terms sentence with {link} swapped for a safe anchor.
	 */
	private function render_terms_html( $design ) {
		$label = esc_html( $design['terms_link_label'] );
		$link  = $design['terms_url']
			? '<a href="' . esc_url( $design['terms_url'] ) . '" target="_blank" rel="noopener noreferrer">' . $label . '</a>'
			: $label;

		$text = $design['terms_text'];

		if ( false !== strpos( $text, '{link}' ) ) {
			list( $before, $after ) = explode( '{link}', $text, 2 );
			return esc_html( $before ) . $link . esc_html( $after );
		}

		return esc_html( $text ) . ' ' . $link;
	}

	private function render_logged_in_notice() {
		$user = wp_get_current_user();
		return '<div class="asl-wrapper" dir="rtl"><div class="asl-card asl-logged-in-notice">' .
			sprintf(
				/* translators: %s: display name */
				esc_html__( 'شما با نام «%s» وارد شده‌اید.', 'arankia-sms-login' ),
				esc_html( $user->display_name )
			) .
			' <a href="' . esc_url( wp_logout_url( get_permalink() ) ) . '">' . esc_html__( 'خروج', 'arankia-sms-login' ) . '</a>' .
			'</div></div>';
	}
}
