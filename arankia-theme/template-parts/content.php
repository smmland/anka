<?php
/**
 * @package Arankia
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'post-card' ); ?>>
	<a class="post-card-thumb" href="<?php the_permalink(); ?>">
		<?php if ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'arankia-blog-thumb' ); ?>
		<?php else : ?>
			<span class="post-card-thumb-placeholder"></span>
		<?php endif; ?>
	</a>
	<div class="post-card-body">
		<?php
		$cats = get_the_category();
		if ( ! empty( $cats ) ) :
			?>
			<a class="post-card-category" href="<?php echo esc_url( get_category_link( $cats[0]->term_id ) ); ?>"><?php echo esc_html( $cats[0]->name ); ?></a>
		<?php endif; ?>
		<h2 class="post-card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		<div class="post-card-meta">
			<span><?php echo esc_html( get_the_date() ); ?></span>
			<span><?php echo esc_html( arankia_reading_time() ); ?></span>
		</div>
		<div class="post-card-excerpt"><?php the_excerpt(); ?></div>
		<a class="post-card-more" href="<?php the_permalink(); ?>"><?php esc_html_e( 'ادامه مطلب', 'arankia' ); ?></a>
	</div>
</article>
