<?php
/**
 * @package Arankia
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( post_password_required() ) {
	return;
}
?>
<div id="comments" class="comments-area">

	<?php if ( have_comments() ) : ?>
		<h2 class="comments-title">
			<?php
			$count = get_comments_number();
			/* translators: %d: number of comments. */
			printf( esc_html( _n( '%d دیدگاه', '%d دیدگاه', $count, 'arankia' ) ), (int) $count );
			?>
		</h2>

		<ol class="comment-list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 56,
				)
			);
			?>
		</ol>

		<?php the_comments_pagination(); ?>
	<?php endif; ?>

	<?php if ( ! comments_open() && get_comments_number() ) : ?>
		<p class="no-comments"><?php esc_html_e( 'امکان ثبت دیدگاه بسته شده است.', 'arankia' ); ?></p>
	<?php endif; ?>

	<?php
	comment_form(
		array(
			'title_reply'        => __( 'دیدگاه خود را بنویسید', 'arankia' ),
			'label_submit'       => __( 'ثبت دیدگاه', 'arankia' ),
			'comment_field'      => '<p class="comment-form-comment"><label for="comment">' . esc_html__( 'متن دیدگاه', 'arankia' ) . '</label><textarea id="comment" name="comment" rows="6" required></textarea></p>',
		)
	);
	?>
</div>
