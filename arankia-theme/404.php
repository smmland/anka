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
	<div class="error-404-wrap">
		<h1 class="error-code">۴۰۴</h1>
		<h2><?php esc_html_e( 'صفحه مورد نظر پیدا نشد', 'arankia' ); ?></h2>
		<p><?php esc_html_e( 'ممکن است آدرس اشتباه وارد شده باشد یا این صفحه حذف شده باشد.', 'arankia' ); ?></p>
		<?php get_search_form(); ?>
		<a class="btn btn-primary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'بازگشت به صفحه اصلی', 'arankia' ); ?></a>
	</div>
</div>
<?php
get_footer();
