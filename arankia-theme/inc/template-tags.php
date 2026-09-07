<?php
/**
 * Helper template tags shared across theme templates.
 *
 * @package Arankia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Breadcrumb (used on blog/shop pages when WooCommerce breadcrumb isn't active).
 */
function arankia_breadcrumb() {
	if ( is_front_page() ) {
		return;
	}

	echo '<nav class="arankia-breadcrumb" aria-label="' . esc_attr__( 'مسیر صفحه', 'arankia' ) . '"><ol>';
	echo '<li><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'خانه', 'arankia' ) . '</a></li>';

	if ( is_singular( 'post' ) ) {
		$cats = get_the_category();
		if ( ! empty( $cats ) ) {
			echo '<li><a href="' . esc_url( get_category_link( $cats[0]->term_id ) ) . '">' . esc_html( $cats[0]->name ) . '</a></li>';
		}
		echo '<li aria-current="page">' . esc_html( get_the_title() ) . '</li>';
	} elseif ( is_category() || is_tag() || is_tax() ) {
		echo '<li aria-current="page">' . esc_html( single_term_title( '', false ) ) . '</li>';
	} elseif ( is_search() ) {
		/* translators: %s: search query. */
		echo '<li aria-current="page">' . esc_html( sprintf( __( 'نتایج جستجو برای: %s', 'arankia' ), get_search_query() ) ) . '</li>';
	} elseif ( is_page() ) {
		echo '<li aria-current="page">' . esc_html( get_the_title() ) . '</li>';
	} elseif ( is_404() ) {
		echo '<li aria-current="page">' . esc_html__( 'یافت نشد', 'arankia' ) . '</li>';
	}

	echo '</ol></nav>';
}

/**
 * Should the page title render? (respects the "hide title" checkbox + Elementor canvas templates).
 */
function arankia_should_show_title() {
	if ( is_singular() ) {
		$hidden = get_post_meta( get_the_ID(), '_arankia_hide_title', true );
		if ( '1' === $hidden ) {
			return false;
		}

		$template = get_page_template_slug();
		if ( 'elementor_canvas' === $template || 'elementor_header_footer' === $template ) {
			return false;
		}
	}

	return true;
}

/**
 * CSS class for the blog content wrapper — falls back to a single, full-width
 * column when the blog sidebar has no widgets so the layout never leaves a
 * dead empty column.
 */
function arankia_blog_wrapper_class() {
	return is_active_sidebar( 'blog-sidebar' ) ? 'content-with-sidebar' : 'full-width-shop';
}

/**
 * Pagination wrapper.
 */
function arankia_pagination() {
	the_posts_pagination(
		array(
			'mid_size'  => 2,
			'prev_text' => __( '&raquo; قبلی', 'arankia' ),
			'next_text' => __( 'بعدی &laquo;', 'arankia' ),
		)
	);
}

/**
 * Excerpt length tweak for the blog loop.
 */
function arankia_excerpt_length( $length ) {
	return is_admin() ? $length : 26;
}
add_filter( 'excerpt_length', 'arankia_excerpt_length', 999 );

function arankia_excerpt_more( $more ) {
	return is_admin() ? $more : '&hellip;';
}
add_filter( 'excerpt_more', 'arankia_excerpt_more' );

/**
 * Reading time estimate for articles.
 */
function arankia_reading_time( $post_id = null ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$content = get_post_field( 'post_content', $post_id );
	$words   = str_word_count( wp_strip_all_tags( $content ) );
	$minutes = max( 1, (int) ceil( $words / 200 ) );

	/* translators: %d: minutes to read. */
	return sprintf( _n( '%d دقیقه مطالعه', '%d دقیقه مطالعه', $minutes, 'arankia' ), $minutes );
}

/**
 * Format a Toman amount consistently across the theme (wallet, invoices, dashboard cards).
 */
function arankia_format_toman( $amount ) {
	return number_format_i18n( (float) $amount ) . ' ' . __( 'تومان', 'arankia' );
}

/**
 * Small inline SVG icon helper — keeps markup free of external icon-font dependencies.
 */
function arankia_icon( $name ) {
	$icons = array(
		'user'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="8" r="3.5"/><path d="M4.5 20c1.4-3.6 4.4-5.5 7.5-5.5s6.1 1.9 7.5 5.5"/></svg>',
		'cart'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 4h2l2.4 12.2a2 2 0 0 0 2 1.6h7.6a2 2 0 0 0 2-1.6L21 8H6"/><circle cx="10" cy="21" r="1.4"/><circle cx="17" cy="21" r="1.4"/></svg>',
		'heart'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 20s-7.5-4.6-10-9.3C.4 7 2 3.5 5.6 3.1c2-.2 3.8.8 5.4 2.7 1.6-1.9 3.4-2.9 5.4-2.7C20 3.5 21.6 7 20 10.7 19.5 8 12 20 12 20z"/></svg>',
		'search'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>',
		'wallet'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18"/><circle cx="16.5" cy="14" r="1.2" fill="currentColor" stroke="none"/></svg>',
		'ticket'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v1.5a1.7 1.7 0 0 0 0 3V15a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1.5a1.7 1.7 0 0 0 0-3V9z"/></svg>',
		'box'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3.5 7.5 12 3l8.5 4.5v9L12 21l-8.5-4.5v-9z"/><path d="M3.5 7.5 12 12l8.5-4.5M12 12v9"/></svg>',
		'map-pin'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 21s7-6.3 7-11.5A7 7 0 0 0 5 9.5C5 14.7 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.3"/></svg>',
		'card'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M2.5 9.5h19"/></svg>',
		'logout'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/></svg>',
		'chevron'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M15 6l-6 6 6 6"/></svg>',
		'menu'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 7h16M4 12h16M4 17h16"/></svg>',
		'close'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M6 6l12 12M18 6L6 18"/></svg>',
		'print'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M6 9V3h12v6M6 18H4a1 1 0 0 1-1-1v-5a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1h-2M6 14h12v7H6z"/></svg>',
		'clock'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg>',
		'check'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 12.5l5 5L20 7"/></svg>',
	);

	if ( isset( $icons[ $name ] ) ) {
		echo '<span class="arankia-icon arankia-icon--' . esc_attr( $name ) . '">' . $icons[ $name ] . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
