<?php
/**
 * @package Arankia
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$show_title = arankia_should_show_title();
?>

<div class="container page-container <?php echo $show_title ? '' : 'arankia-no-title'; ?>">
	<?php if ( $show_title ) : ?>
		<div class="page-title-wrap">
			<h1 class="page-title"><?php the_title(); ?></h1>
		</div>
	<?php endif; ?>
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>
</div>
<?php
get_footer();
