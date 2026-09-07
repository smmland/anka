<?php
/**
 * @package Arankia
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="container page-container">
	<div class="<?php echo esc_attr( arankia_blog_wrapper_class() ); ?>">
		<main id="main" class="site-main">
			<?php arankia_breadcrumb(); ?>
			<header class="archive-header">
				<h1 class="archive-title"><?php the_archive_title(); ?></h1>
				<?php the_archive_description( '<div class="archive-description">', '</div>' ); ?>
			</header>

			<?php if ( have_posts() ) : ?>
				<div class="posts-grid">
					<?php
					while ( have_posts() ) :
						the_post();
						get_template_part( 'template-parts/content', get_post_type() );
					endwhile;
					?>
				</div>
				<?php arankia_pagination(); ?>
			<?php else : ?>
				<?php get_template_part( 'template-parts/content', 'none' ); ?>
			<?php endif; ?>
		</main>
		<?php get_sidebar(); ?>
	</div>
</div>
<?php
get_footer();
