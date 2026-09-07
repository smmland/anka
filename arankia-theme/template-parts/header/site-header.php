<?php
/**
 * Default site header (fallback when Elementor Pro Theme Builder header
 * location is not assigned).
 *
 * @package Arankia
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$phone   = get_theme_mod( 'arankia_header_phone', '' );
$hours   = get_theme_mod( 'arankia_header_hours', '' );
$ig      = get_theme_mod( 'arankia_social_instagram', '' );
$tg      = get_theme_mod( 'arankia_social_telegram', '' );
$wa      = get_theme_mod( 'arankia_social_whatsapp', '' );
$has_wc  = class_exists( 'WooCommerce' );

$announce_text = get_theme_mod( 'arankia_announce_text', arankia_home_default( 'announce_text' ) );
if ( get_theme_mod( 'arankia_announce_enable', true ) && $announce_text ) :
	?>
	<div class="site-announce"><?php echo wp_kses_post( $announce_text ); ?></div>
	<?php
endif;
?>
<header id="masthead" class="site-header">

	<?php if ( $phone || $hours || $ig || $tg || $wa ) : ?>
	<div class="header-topbar">
		<div class="container topbar-inner">
			<div class="topbar-info">
				<?php if ( $hours ) : ?><span><?php arankia_icon( 'clock' ); ?><?php echo esc_html( $hours ); ?></span><?php endif; ?>
				<?php if ( $phone ) : ?><a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a><?php endif; ?>
			</div>
			<?php if ( $ig || $tg || $wa ) : ?>
			<div class="topbar-social">
				<?php if ( $ig ) : ?><a href="<?php echo esc_url( $ig ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'اینستاگرام', 'arankia' ); ?></a><?php endif; ?>
				<?php if ( $tg ) : ?><a href="<?php echo esc_url( $tg ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'تلگرام', 'arankia' ); ?></a><?php endif; ?>
				<?php if ( $wa ) : ?><a href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'واتساپ', 'arankia' ); ?></a><?php endif; ?>
			</div>
			<?php endif; ?>
		</div>
	</div>
	<?php endif; ?>

	<div class="header-main">
		<div class="container header-main-inner">

			<button class="mobile-menu-toggle" aria-label="<?php esc_attr_e( 'باز کردن منو', 'arankia' ); ?>" aria-expanded="false" data-toggle="mobile-menu">
				<?php arankia_icon( 'menu' ); ?>
			</button>

			<div class="site-branding">
				<?php if ( has_custom_logo() ) : ?>
					<?php the_custom_logo(); ?>
				<?php else : ?>
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="site-title-link"><?php bloginfo( 'name' ); ?></a>
				<?php endif; ?>
			</div>

			<nav class="primary-nav" aria-label="<?php esc_attr_e( 'منوی اصلی', 'arankia' ); ?>">
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'container'      => false,
						'menu_class'     => 'primary-menu',
						'fallback_cb'    => false,
					)
				);
				?>
			</nav>

			<div class="header-search">
				<button class="header-search-toggle" aria-label="<?php esc_attr_e( 'جستجو', 'arankia' ); ?>" data-toggle="search-modal">
					<?php arankia_icon( 'search' ); ?>
				</button>
			</div>

			<div class="header-actions">
				<?php if ( $has_wc ) : ?>
				<a class="header-action-icon wishlist-icon" href="<?php echo esc_url( arankia_get_endpoint_url( 'wishlist' ) ); ?>" aria-label="<?php esc_attr_e( 'علاقه‌مندی‌ها', 'arankia' ); ?>">
					<?php arankia_icon( 'heart' ); ?>
					<span class="action-count wishlist-count"><?php echo esc_html( arankia_wishlist_count() ); ?></span>
				</a>

				<a class="header-action-icon account-icon" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" aria-label="<?php esc_attr_e( 'حساب کاربری', 'arankia' ); ?>">
					<?php arankia_icon( 'user' ); ?>
				</a>

				<a class="header-action-icon cart-icon" href="<?php echo esc_url( wc_get_cart_url() ); ?>" aria-label="<?php esc_attr_e( 'سبد خرید', 'arankia' ); ?>">
					<?php arankia_icon( 'cart' ); ?>
					<span class="action-count cart-count"><?php echo absint( WC()->cart ? WC()->cart->get_cart_contents_count() : 0 ); ?></span>
				</a>
				<?php endif; ?>
			</div>

		</div>
	</div>

	<div class="search-modal" id="search-modal">
		<div class="search-modal-inner">
			<?php get_search_form(); ?>
			<button class="search-modal-close" data-toggle="search-modal" aria-label="<?php esc_attr_e( 'بستن', 'arankia' ); ?>"><?php arankia_icon( 'close' ); ?></button>
		</div>
	</div>

	<div class="mobile-menu" id="mobile-menu">
		<div class="mobile-menu-head">
			<span><?php esc_html_e( 'منو', 'arankia' ); ?></span>
			<button class="mobile-menu-close" data-toggle="mobile-menu" aria-label="<?php esc_attr_e( 'بستن', 'arankia' ); ?>"><?php arankia_icon( 'close' ); ?></button>
		</div>
		<?php
		wp_nav_menu(
			array(
				'theme_location' => 'mobile',
				'container'      => false,
				'menu_class'     => 'mobile-menu-list',
				'fallback_cb'    => 'arankia_mobile_menu_fallback',
			)
		);
		?>
		<?php if ( $has_wc ) : ?>
		<div class="mobile-menu-account">
			<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>"><?php arankia_icon( 'user' ); ?><?php esc_html_e( 'حساب کاربری من', 'arankia' ); ?></a>
			<a href="<?php echo esc_url( arankia_get_endpoint_url( 'wallet' ) ); ?>"><?php arankia_icon( 'wallet' ); ?><?php esc_html_e( 'کیف پول من', 'arankia' ); ?></a>
		</div>
		<?php endif; ?>
	</div>
	<div class="mobile-menu-overlay" data-toggle="mobile-menu"></div>

</header>
<?php
if ( ! function_exists( 'arankia_mobile_menu_fallback' ) ) {
	function arankia_mobile_menu_fallback() {
		wp_nav_menu(
			array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'mobile-menu-list',
				'fallback_cb'    => false,
			)
		);
	}
}
