<?php
/**
 * Elementor + Elementor Pro Theme Builder integration.
 *
 * Every page/post/product built by this theme can be fully edited with
 * Elementor. When Elementor Pro's Theme Builder is active and a header /
 * footer template is assigned to a location, that template is used;
 * otherwise the theme's own header.php / footer.php render as a
 * dependable fallback so the site never breaks without Pro.
 *
 * @package Arankia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function arankia_register_elementor_locations( $elementor_theme_manager ) {
	$elementor_theme_manager->register_all_core_location();
}
add_action( 'elementor/theme/register_locations', 'arankia_register_elementor_locations' );

/**
 * Outputs the Elementor Pro header location if one is assigned, otherwise
 * falls back to the theme's built-in header markup.
 */
function arankia_header_location() {
	if ( did_action( 'elementor/loaded' ) && class_exists( '\ElementorPro\Plugin' ) && function_exists( 'elementor_theme_do_location' ) && elementor_theme_do_location( 'header' ) ) {
		return;
	}
	get_template_part( 'template-parts/header/site-header' );
}

function arankia_footer_location() {
	if ( did_action( 'elementor/loaded' ) && class_exists( '\ElementorPro\Plugin' ) && function_exists( 'elementor_theme_do_location' ) && elementor_theme_do_location( 'footer' ) ) {
		return;
	}
	get_template_part( 'template-parts/footer/site-footer' );
}

/**
 * Remove the default page title on Elementor Canvas / hidden-title pages,
 * and strip the theme's content padding so full-width Elementor sections
 * touch the edges as expected.
 */
function arankia_body_class( $classes ) {
	if ( is_singular() ) {
		$template = get_page_template_slug();
		if ( 'elementor_canvas' === $template || 'elementor_header_footer' === $template ) {
			$classes[] = 'arankia-elementor-canvas';
		}
		if ( class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->documents->get( get_the_ID() )->is_built_with_elementor() ) {
			$classes[] = 'arankia-built-with-elementor';
		}
	}
	return $classes;
}
add_filter( 'body_class', 'arankia_body_class' );

/**
 * Register an extra "تمام عرض" (Full width, header+footer, no sidebar) page
 * template so Elementor pages that don't use Elementor Canvas still get a
 * clean, sidebar-free content area.
 */
function arankia_page_templates( $templates ) {
	$templates['page-templates/template-fullwidth.php'] = __( 'آرانکیا - تمام عرض (بدون سایدبار)', 'arankia' );
	return $templates;
}
add_filter( 'theme_page_templates', 'arankia_page_templates' );
