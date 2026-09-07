<?php
/**
 * Arankia Store — Theme bootstrap.
 *
 * @package Arankia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARANKIA_VERSION', '1.3.0' );
define( 'ARANKIA_DIR', get_template_directory() );
define( 'ARANKIA_URI', get_template_directory_uri() );

/**
 * Core includes. Every module below is self-contained inside the theme —
 * no external plugin is required for wallet, wishlist or support tickets.
 */
require_once ARANKIA_DIR . '/inc/setup.php';
require_once ARANKIA_DIR . '/inc/enqueue.php';
require_once ARANKIA_DIR . '/inc/template-tags.php';
require_once ARANKIA_DIR . '/inc/customizer.php';
require_once ARANKIA_DIR . '/inc/elementor.php';
require_once ARANKIA_DIR . '/inc/activation.php';

if ( class_exists( 'WooCommerce' ) ) {
	require_once ARANKIA_DIR . '/inc/woocommerce.php';
	require_once ARANKIA_DIR . '/inc/account.php';
	require_once ARANKIA_DIR . '/inc/invoice.php';
	require_once ARANKIA_DIR . '/inc/class-wallet.php';
	require_once ARANKIA_DIR . '/inc/class-wishlist.php';
	require_once ARANKIA_DIR . '/inc/front-page.php';
}

require_once ARANKIA_DIR . '/inc/class-support-tickets.php';

/**
 * Friendly admin notice when WooCommerce is missing, since the whole
 * shop/account/wallet/wishlist experience depends on it.
 */
function arankia_woocommerce_missing_notice() {
	if ( class_exists( 'WooCommerce' ) ) {
		return;
	}
	?>
	<div class="notice notice-warning">
		<p>
			<?php esc_html_e( 'قالب آرانکیا برای فعال شدن کامل امکانات فروشگاهی (محصول، سبد خرید، پنل کاربری، کیف پول) به افزونه ووکامرس نیاز دارد. لطفاً افزونه WooCommerce را نصب و فعال کنید.', 'arankia' ); ?>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'arankia_woocommerce_missing_notice' );
