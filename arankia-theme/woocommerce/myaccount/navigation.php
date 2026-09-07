<?php
/**
 * My Account navigation — restyled as an icon sidebar.
 *
 * @package Arankia
 */

defined( 'ABSPATH' ) || exit;

$icon_map = array(
	'dashboard'          => 'user',
	'orders'             => 'box',
	'purchased-products' => 'check',
	'wallet'             => 'wallet',
	'wishlist'           => 'heart',
	'tickets'            => 'ticket',
	'edit-address'       => 'map-pin',
	'payment-methods'    => 'card',
	'edit-account'       => 'user',
	'customer-logout'    => 'logout',
);
?>
<nav class="woocommerce-MyAccount-navigation arankia-account-nav">
	<div class="account-nav-user">
		<?php echo get_avatar( get_current_user_id(), 56 ); ?>
		<div>
			<strong><?php echo esc_html( wp_get_current_user()->display_name ); ?></strong>
			<span><?php echo esc_html( arankia_format_toman( arankia_wallet_get_balance() ) ); ?></span>
		</div>
	</div>
	<ul>
		<?php foreach ( wc_get_account_menu_items() as $endpoint => $label ) : ?>
			<li class="<?php echo esc_attr( wc_get_account_menu_item_classes( $endpoint ) ); ?>">
				<a href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>">
					<?php arankia_icon( isset( $icon_map[ $endpoint ] ) ? $icon_map[ $endpoint ] : 'chevron' ); ?>
					<?php echo esc_html( $label ); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>
