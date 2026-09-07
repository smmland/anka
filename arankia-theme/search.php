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
				<h1 class="archive-title">
					<?php
					/* translators: %s: search query. */
					printf( esc_html__( 'نتایج جستجو برای: %s', 'arankia' ), '<span>' . esc_html( get_search_query() ) . '</span>' );
					?>
				</h1>
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
