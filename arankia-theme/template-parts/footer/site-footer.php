<?php
/**
 * Default site footer (fallback when Elementor Pro Theme Builder footer
 * location is not assigned).
 *
 * @package Arankia
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$has_widgets = is_active_sidebar( 'footer-1' ) || is_active_sidebar( 'footer-2' ) || is_active_sidebar( 'footer-3' ) || is_active_sidebar( 'footer-4' );
$copyright   = get_theme_mod( 'arankia_footer_copyright', __( 'تمامی حقوق این وب‌سایت متعلق به آرانکیا است. © %year%', 'arankia' ) );
$copyright   = str_replace( '%year%', gmdate( 'Y' ), $copyright );
?>
<footer id="colophon" class="site-footer">

	<?php if ( $has_widgets ) : ?>
	<div class="footer-widgets">
		<div class="container footer-widgets-grid">
			<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
				<?php if ( is_active_sidebar( 'footer-' . $i ) ) : ?>
					<div class="footer-col">
						<?php dynamic_sidebar( 'footer-' . $i ); ?>
					</div>
				<?php elseif ( 1 === $i ) : ?>
					<div class="footer-col footer-about">
						<?php if ( has_custom_logo() ) : ?>
							<?php the_custom_logo(); ?>
						<?php else : ?>
							<strong class="footer-site-title"><?php bloginfo( 'name' ); ?></strong>
						<?php endif; ?>
						<p><?php echo esc_html( get_theme_mod( 'arankia_footer_about', '' ) ); ?></p>
					</div>
				<?php endif; ?>
			<?php endfor; ?>
		</div>
	</div>
	<?php endif; ?>

	<?php if ( has_nav_menu( 'footer' ) ) : ?>
	<div class="footer-menu">
		<div class="container">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'footer',
					'container'      => false,
					'menu_class'     => 'footer-menu-list',
					'fallback_cb'    => false,
				)
			);
			?>
		</div>
	</div>
	<?php endif; ?>

	<div class="footer-bottom">
		<div class="container footer-bottom-inner">
			<p class="footer-copyright"><?php echo esc_html( $copyright ); ?></p>
			<div class="footer-trust">
				<span><?php esc_html_e( 'پرداخت امن', 'arankia' ); ?></span>
				<span><?php esc_html_e( 'ضمانت اصالت کالا', 'arankia' ); ?></span>
				<span><?php esc_html_e( 'پشتیبانی آنلاین', 'arankia' ); ?></span>
			</div>
		</div>
	</div>

</footer>

<button class="back-to-top" id="back-to-top" aria-label="<?php esc_attr_e( 'بازگشت به بالا', 'arankia' ); ?>">&uarr;</button>
