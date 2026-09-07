<?php
/**
 * @package Arankia
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="no-results">
	<h2><?php esc_html_e( 'موردی یافت نشد', 'arankia' ); ?></h2>
	<p><?php esc_html_e( 'متأسفانه محتوایی مطابق با جستجوی شما پیدا نشد.', 'arankia' ); ?></p>
	<?php get_search_form(); ?>
</div>
