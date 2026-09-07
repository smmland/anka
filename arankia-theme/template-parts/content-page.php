<?php
/**
 * @package Arankia
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'page-content' ); ?>>
	<?php if ( arankia_should_show_title() ) : ?>
		<h1 class="page-title"><?php the_title(); ?></h1>
	<?php endif; ?>
	<div class="entry-content">
		<?php
		the_content();
		wp_link_pages(
			array(
				'before' => '<div class="page-links">' . esc_html__( 'صفحات:', 'arankia' ),
				'after'  => '</div>',
			)
		);
		?>
	</div>
</article>
