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
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<article id="post-<?php the_ID(); ?>" <?php post_class( 'single-article' ); ?>>
					<header class="article-header">
						<?php
						$cats = get_the_category();
						if ( ! empty( $cats ) ) :
							?>
							<a class="article-category" href="<?php echo esc_url( get_category_link( $cats[0]->term_id ) ); ?>"><?php echo esc_html( $cats[0]->name ); ?></a>
						<?php endif; ?>
						<h1 class="article-title"><?php the_title(); ?></h1>
						<div class="article-meta">
							<span class="article-date"><?php echo esc_html( get_the_date() ); ?></span>
							<span class="article-reading-time"><?php echo esc_html( arankia_reading_time() ); ?></span>
							<span class="article-author"><?php esc_html_e( 'نویسنده:', 'arankia' ); ?> <?php the_author(); ?></span>
						</div>
					</header>

					<?php if ( has_post_thumbnail() ) : ?>
						<div class="article-thumb"><?php the_post_thumbnail( 'arankia-blog-thumb' ); ?></div>
					<?php endif; ?>

					<div class="article-content">
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

					<?php
					$tags = get_the_tags();
					if ( $tags ) :
						?>
						<div class="article-tags">
							<?php foreach ( $tags as $tag ) : ?>
								<a href="<?php echo esc_url( get_tag_link( $tag ) ); ?>">#<?php echo esc_html( $tag->name ); ?></a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<div class="author-box">
						<?php echo get_avatar( get_the_author_meta( 'ID' ), 72 ); ?>
						<div class="author-box-body">
							<strong><?php the_author(); ?></strong>
							<p><?php the_author_meta( 'description' ); ?></p>
						</div>
					</div>
				</article>

				<?php
				$related = new WP_Query(
					array(
						'category__in'   => wp_get_post_categories( get_the_ID() ),
						'post__not_in'   => array( get_the_ID() ),
						'posts_per_page' => 3,
						'ignore_sticky_posts' => 1,
					)
				);
				if ( $related->have_posts() ) :
					?>
					<section class="related-posts">
						<h2 class="section-title"><?php esc_html_e( 'مطالب مرتبط', 'arankia' ); ?></h2>
						<div class="posts-grid">
							<?php
							while ( $related->have_posts() ) :
								$related->the_post();
								get_template_part( 'template-parts/content', 'excerpt' );
							endwhile;
							wp_reset_postdata();
							?>
						</div>
					</section>
				<?php endif; ?>

				<?php if ( comments_open() || get_comments_number() ) : ?>
					<div class="article-comments">
						<?php comments_template(); ?>
					</div>
				<?php endif; ?>
				<?php
			endwhile;
			?>
		</main>
		<?php get_sidebar(); ?>
	</div>
</div>
<?php
get_footer();
