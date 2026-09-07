<?php
/**
 * @package Arankia
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<form role="search" method="get" class="arankia-search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="arankia-search-field"><?php esc_html_e( 'جستجو در سایت', 'arankia' ); ?></label>
	<input type="search" id="arankia-search-field" class="search-field" placeholder="<?php esc_attr_e( 'جستجوی محصول یا مقاله...', 'arankia' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>" name="s" />
	<button type="submit" class="search-submit" aria-label="<?php esc_attr_e( 'جستجو', 'arankia' ); ?>"><?php arankia_icon( 'search' ); ?></button>
</form>
