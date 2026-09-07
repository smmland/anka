<?php
/**
 * Product loop card — overrides WooCommerce's default template so we can
 * add the wishlist heart button and a cleaner card structure.
 *
 * @package Arankia
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( empty( $product ) || ! $product->is_visible() ) {
	return;
}
?>
<li <?php wc_product_class( 'product-card', $product ); ?>>
	<div class="product-card-media">
		<a href="<?php the_permalink(); ?>" class="product-card-thumb">
			<?php echo $product->get_image( 'arankia-product-thumb' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</a>

		<?php woocommerce_show_product_loop_sale_flash(); ?>

		<?php if ( function_exists( 'arankia_wishlist_button' ) ) : ?>
			<?php arankia_wishlist_button( $product->get_id() ); ?>
		<?php endif; ?>
	</div>

	<div class="product-card-body">
		<?php
		$cats = wc_get_product_category_list( $product->get_id() );
		if ( $cats ) :
			?>
			<div class="product-card-cat"><?php echo wp_strip_all_tags( $cats ); ?></div>
		<?php endif; ?>

		<h3 class="product-card-title">
			<a href="<?php the_permalink(); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
		</h3>

		<?php if ( wc_review_ratings_enabled() && $product->get_average_rating() > 0 ) : ?>
			<div class="product-card-rating"><?php echo wc_get_rating_html( $product->get_average_rating() ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		<?php endif; ?>

		<div class="product-card-price"><?php echo $product->get_price_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>

		<div class="product-card-actions">
			<?php
			woocommerce_template_loop_add_to_cart(
				array(
					'class' => 'button add_to_cart_button ajax_add_to_cart',
				)
			);
			?>
		</div>
	</div>
</li>
