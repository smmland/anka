<?php
/**
 * Theme setup: supports, menus, sidebars, image sizes.
 *
 * @package Arankia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function arankia_setup() {
	load_theme_textdomain( 'arankia', ARANKIA_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' )
	);
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 64,
			'width'       => 220,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	// WooCommerce.
	add_theme_support( 'woocommerce' );
	add_theme_support(
		'wc-product-gallery-zoom',
	);
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );

	// Elementor.
	add_theme_support( 'elementor' );

	register_nav_menus(
		array(
			'primary' => __( 'منوی اصلی هدر', 'arankia' ),
			'mobile'  => __( 'منوی موبایل', 'arankia' ),
			'footer'  => __( 'منوی فوتر', 'arankia' ),
		)
	);

	set_post_thumbnail_size( 600, 600, true );
	add_image_size( 'arankia-product-thumb', 500, 500, true );
	add_image_size( 'arankia-blog-thumb', 640, 420, true );
	add_image_size( 'arankia-hero', 1600, 700, true );

	$GLOBALS['content_width'] = 1140;
}
add_action( 'after_setup_theme', 'arankia_setup' );

function arankia_widgets_init() {
	register_sidebar(
		array(
			'name'          => __( 'سایدبار وبلاگ', 'arankia' ),
			'id'            => 'blog-sidebar',
			'before_widget' => '<div id="%1$s" class="widget %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<h3 class="widget-title">',
			'after_title'   => '</h3>',
		)
	);

	register_sidebar(
		array(
			'name'          => __( 'سایدبار فروشگاه', 'arankia' ),
			'id'            => 'shop-sidebar',
			'before_widget' => '<div id="%1$s" class="widget %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<h3 class="widget-title">',
			'after_title'   => '</h3>',
		)
	);

	for ( $i = 1; $i <= 4; $i++ ) {
		register_sidebar(
			array(
				/* translators: %d: footer column number. */
				'name'          => sprintf( __( 'ستون فوتر %d', 'arankia' ), $i ),
				'id'            => 'footer-' . $i,
				'before_widget' => '<div id="%1$s" class="widget %2$s">',
				'after_widget'  => '</div>',
				'before_title'  => '<h4 class="footer-widget-title">',
				'after_title'   => '</h4>',
			)
		);
	}
}
add_action( 'widgets_init', 'arankia_widgets_init' );

/**
 * "مخفی کردن عنوان صفحه" برای صفحاتی که کامل با المنتور طراحی می‌شوند.
 */
function arankia_hide_title_metabox() {
	add_meta_box(
		'arankia_hide_title',
		__( 'نمایش عنوان صفحه', 'arankia' ),
		'arankia_hide_title_render',
		array( 'page', 'post' ),
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'arankia_hide_title_metabox' );

function arankia_hide_title_render( $post ) {
	wp_nonce_field( 'arankia_hide_title_nonce', 'arankia_hide_title_nonce' );
	$hide = get_post_meta( $post->ID, '_arankia_hide_title', true );
	?>
	<label>
		<input type="checkbox" name="arankia_hide_title" value="1" <?php checked( $hide, '1' ); ?> />
		<?php esc_html_e( 'عنوان این صفحه نمایش داده نشود (برای صفحات ساخته‌شده با المنتور)', 'arankia' ); ?>
	</label>
	<?php
}

function arankia_hide_title_save( $post_id ) {
	if ( ! isset( $_POST['arankia_hide_title_nonce'] ) || ! wp_verify_nonce( $_POST['arankia_hide_title_nonce'], 'arankia_hide_title_nonce' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	update_post_meta( $post_id, '_arankia_hide_title', isset( $_POST['arankia_hide_title'] ) ? '1' : '' );
}
add_action( 'save_post', 'arankia_hide_title_save' );
