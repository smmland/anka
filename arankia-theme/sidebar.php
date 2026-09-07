<?php
/**
 * @package Arankia
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sidebar_id = is_singular( 'product' ) || is_shop() || is_product_taxonomy() ? 'shop-sidebar' : 'blog-sidebar';

if ( ! is_active_sidebar( $sidebar_id ) ) {
	return;
}
?>
<aside id="secondary" class="site-sidebar widget-area">
	<?php dynamic_sidebar( $sidebar_id ); ?>
</aside>
