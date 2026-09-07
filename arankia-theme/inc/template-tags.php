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
		'chevron-right' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M9 6l6 6-6 6"/></svg>',
		'truck'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M2 6h11v10H2z"/><path d="M13 10h4l4 3.5V16h-8z"/><circle cx="6.5" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/></svg>',
		'shield'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/><path d="M9 12l2 2 4-4"/></svg>',
		'badge'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="10" r="6"/><path d="M9 15.5L8 21l4-2 4 2-1-5.5"/></svg>',
		'headset'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 13v-1a8 8 0 0 1 16 0v1"/><rect x="2.5" y="13" width="4" height="6" rx="1.4"/><rect x="17.5" y="13" width="4" height="6" rx="1.4"/><path d="M20 19v1a2 2 0 0 1-2 2h-3"/></svg>',
		'receipt'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M6 3h12v18l-2.5-1.5L13 21l-2.5-1.5L8 21l-2-1.5V3z"/><path d="M9 8h6M9 12h6"/></svg>',
		'star'      => '<svg viewBox="0 0 24 24"><path fill="currentColor" stroke="none" d="M12 2.5l2.9 6 6.6.7-4.9 4.5 1.3 6.5L12 16.9 6.1 20.2l1.3-6.5-4.9-4.5 6.6-.7z"/></svg>',
		'arrow-up'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 19V5M6 11l6-6 6 6"/></svg>',
		'home'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 11l8-7 8 7v9a1 1 0 0 1-1 1h-4v-6h-6v6H5a1 1 0 0 1-1-1z"/></svg>',
		'grid'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/></svg>',
		'headphone' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 13v-1a8 8 0 0 1 16 0v1"/><rect x="2.5" y="13" width="4" height="6" rx="1.4"/><rect x="17.5" y="13" width="4" height="6" rx="1.4"/></svg>',
		'kettle'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M5 10h11a3 3 0 0 1 0 6h-1"/><path d="M5 10a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-5a2 2 0 0 0-2-2z"/><path d="M9 10V6a2 2 0 0 1 4 0"/></svg>',
		'shoe'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 17c0-2 1.5-3 3-4l4-3.5c1-.9 2-1.5 3.5-1.5h1.8c.7 0 1.2.5 1.2 1.2v2.1c0 .7.4 1.3 1 1.6l3 1.6c1 .5 1.5 1 1.5 2v1z"/><path d="M3 17h18v2H3z"/></svg>',
		'watch'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="5.5"/><path d="M9 3h6l-.7 4.5h-4.6zM9 21h6l-.7-4.5h-4.6z"/><path d="M12 9.5V12l1.6 1.2"/></svg>',
		'lamp'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M7 4h10l-2.5 7h-5z"/><path d="M12 11v6"/><path d="M8 21h8l-1-2H9z"/></svg>',
		'dumbbell'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 9v6M2 10v4M20 9v6M22 10v4"/><path d="M7 12h10"/><rect x="5.5" y="8.5" width="3" height="7" rx="1"/><rect x="15.5" y="8.5" width="3" height="7" rx="1"/></svg>',
		'blocks'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="4" y="12" width="6" height="6" rx="1"/><circle cx="16" cy="7" r="3"/><rect x="13" y="12" width="6" height="6" rx="1"/></svg>',
		'shirt'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M8 4L4 7l2 3 2-1.3V20h8V8.7L18 10l2-3-4-3-2 2h-4z"/></svg>',
		'leaf'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 20C4 10 12 4 20 4c0 8-6 16-16 16z"/><path d="M6.5 17.5C10 13.5 13 10 17 6"/></svg>',
		'tractor'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 15h4l1-4h6v4h2l3-3v3"/><circle cx="7" cy="18" r="2.6"/><circle cx="17" cy="18" r="3.6"/><path d="M14 11V7h2"/></svg>',
		'watering-can' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 12h9a3 3 0 0 1 0 6H6a2 2 0 0 1-2-2z"/><path d="M13 12l6-3"/><path d="M13 9V6a2 2 0 0 1 2-2h1"/><path d="M6 9V7"/></svg>',
		'spray'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M9 4h4v3H9z"/><path d="M10 7v3"/><path d="M7 10h6a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2v-7a2 2 0 0 1 1-2z"/><path d="M17 8l2-1M18 11h2.2M17 14l2 1"/></svg>',
		'flask'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M9 3h6"/><path d="M10 3v6l-5 9a2 2 0 0 0 2 3h10a2 2 0 0 0 2-3l-5-9V3"/><path d="M8 15h8"/></svg>',
		'book'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H12v17H6.5A2.5 2.5 0 0 0 4 22.5z"/><path d="M20 5.5A2.5 2.5 0 0 0 17.5 3H12v17h5.5a2.5 2.5 0 0 1 2.5 2.5"/></svg>',
	);

	if ( isset( $icons[ $name ] ) ) {
		echo '<span class="arankia-icon arankia-icon--' . esc_attr( $name ) . '">' . $icons[ $name ] . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}

/**
 * Single source of truth for every editable landing-page default (hero copy,
 * testimonials, stats, newsletter). Both inc/customizer.php (so the Customizer
 * UI shows sensible starting text) and front-page.php (so the homepage looks
 * right on a fresh install, before anyone has opened the Customizer) read
 * from here — get_theme_mod()'s own default argument is what actually renders
 * on a normal page load, so the two must never drift apart.
 */
function arankia_home_default( $key ) {
	$defaults = array(
		'hero_title'    => __( 'از بذر تا برداشت، همه‌چیز را از آرانکیا بخواهید.', 'arankia' ),
		'hero_subtitle' => __( 'ابزار، ادوات، کود و ملزومات کشت با ضمانت اصالت، کیف‌پول اختصاصی و فاکتور رسمی برای هر سفارش — خریدی که کشاورزان به آن اعتماد دارند.', 'arankia' ),

		'stat_value_1' => '۱۲,۰۰۰+',
		'stat_label_1' => __( 'مشتری راضی', 'arankia' ),
		'stat_value_2' => '۴.۸',
		'stat_label_2' => __( 'امتیاز از ۵', 'arankia' ),
		'stat_value_3' => '۲۴',
		'stat_label_3' => __( 'ساعت میانگین ارسال', 'arankia' ),
		'stat_value_4' => '۹۸٪',
		'stat_label_4' => __( 'رضایت از پشتیبانی', 'arankia' ),

		'testimonial_text_1' => __( 'از سفارش تا تحویل کمتر از دو روز طول کشید. فاکتور رسمی هم گرفتم که برام مهم بود.', 'arankia' ),
		'testimonial_name_1' => __( 'نگین توکلی', 'arankia' ),
		'testimonial_city_1' => __( 'تهران', 'arankia' ),
		'testimonial_text_2' => __( 'کیف‌پول آرانکیا خیلی به‌دردم خورد؛ دیگه هر بار کارت نمی‌کشم و پرداخت آنی انجام می‌شه.', 'arankia' ),
		'testimonial_name_2' => __( 'امیر رضایی', 'arankia' ),
		'testimonial_city_2' => __( 'اصفهان', 'arankia' ),
		'testimonial_text_3' => __( 'یک بار مشکل در سایز کفش داشتم، تیکت زدم و ظرف یک روز جواب و راه‌حل گرفتم.', 'arankia' ),
		'testimonial_name_3' => __( 'سارا کریمی', 'arankia' ),
		'testimonial_city_3' => __( 'شیراز', 'arankia' ),

		'newsletter_title' => __( 'به باشگاه مشتریان آرانکیا بپیوندید', 'arankia' ),
		'newsletter_text'  => __( 'با عضویت در خبرنامه، از تخفیف‌های زودهنگام و محصولات جدید آرانکیا باخبر شوید.', 'arankia' ),

		'announce_text' => __( 'ارسال رایگان برای سفارش‌های بالای ۲,۰۰۰,۰۰۰ تومان — تا پایان این هفته', 'arankia' ),
	);

	return isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
}

/**
 * Deterministic icon + tint pair for a category/product that has no photo
 * yet (fresh catalogs, empty terms) — same id always gets the same look,
 * so the storefront still reads as designed rather than empty. Icon pool
 * is geared toward a farm-tools-and-supplies catalog (Arankia's vertical).
 *
 * @return array{icon:string,tint:string} tint is one of primary|gold|danger.
 */
function arankia_visual_placeholder( $id ) {
	$icons = array( 'tractor', 'leaf', 'watering-can', 'spray', 'flask', 'book', 'box', 'wallet' );
	$tints = array( 'primary', 'gold', 'danger' );

	$icon_index = abs( (int) $id ) % count( $icons );
	$tint_index = abs( (int) $id ) % count( $tints );

	return array(
		'icon' => $icons[ $icon_index ],
		'tint' => $tints[ $tint_index ],
	);
}

/**
 * Same idea as arankia_visual_placeholder(), but for a product *category*
 * where we actually have a meaningful name to read — so a term called
 * «کنترل آفات» gets the spray-can icon instead of a random one, and only
 * falls back to the deterministic hash when no keyword matches.
 *
 * @param WP_Term $term
 * @return array{icon:string,tint:string}
 */
function arankia_home_category_visual( $term ) {
	$keyword_map = array(
		'leaf'         => array( 'کشت', 'رشد', 'بذر', 'نهال', 'کود' ),
		'watering-can' => array( 'آبیاری', 'آب' ),
		'spray'        => array( 'آفت', 'سم' ),
		'tractor'      => array( 'ابزار', 'ادوات', 'ماشین' ),
		'flask'        => array( 'آزمایشگاه', 'دقیق', 'تست' ),
		'book'         => array( 'آموزش', 'کتاب' ),
	);

	foreach ( $keyword_map as $icon => $keywords ) {
		foreach ( $keywords as $keyword ) {
			if ( false !== mb_strpos( $term->name, $keyword ) ) {
				$tints = array( 'primary', 'gold', 'danger' );
				return array(
					'icon' => $icon,
					'tint' => $tints[ abs( (int) $term->term_id ) % count( $tints ) ],
				);
			}
		}
	}

	return arankia_visual_placeholder( $term->term_id );
}
