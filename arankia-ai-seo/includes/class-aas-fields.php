<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single source of truth for which SEO fields the plugin can read/generate/write,
 * where each one lives (post field, postmeta, Yoast meta, featured image alt text),
 * and how to sanitize AI-generated values before they are stored or applied.
 */
class AAS_Fields {

	public static function all() {
		return array(
			'post_title' => array(
				'label' => __( 'عنوان محصول', 'arankia-ai-seo' ),
				'type'  => 'text',
				'group' => 'content',
			),
			'post_excerpt' => array(
				'label' => __( 'توضیحات کوتاه', 'arankia-ai-seo' ),
				'type'  => 'textarea',
				'group' => 'content',
			),
			'post_content' => array(
				'label' => __( 'توضیحات کامل محصول', 'arankia-ai-seo' ),
				'type'  => 'html',
				'group' => 'content',
			),
			'meta_title' => array(
				'label' => __( 'عنوان متا سئو (Yoast)', 'arankia-ai-seo' ),
				'type'  => 'text',
				'group' => 'seo',
			),
			'meta_description' => array(
				'label' => __( 'توضیحات متا سئو (Yoast)', 'arankia-ai-seo' ),
				'type'  => 'textarea',
				'group' => 'seo',
			),
			'focus_keyword' => array(
				'label' => __( 'کلیدواژه کانونی (Yoast)', 'arankia-ai-seo' ),
				'type'  => 'text',
				'group' => 'seo',
			),
			'meta_keywords' => array(
				'label' => __( 'کلیدواژه‌های متا', 'arankia-ai-seo' ),
				'type'  => 'text',
				'group' => 'seo',
			),
			'image_alt' => array(
				'label' => __( 'متن جایگزین تصویر شاخص (ALT)', 'arankia-ai-seo' ),
				'type'  => 'text',
				'group' => 'seo',
			),
		);
	}

	public static function label( $field_key ) {
		$all = self::all();
		return isset( $all[ $field_key ] ) ? $all[ $field_key ]['label'] : $field_key;
	}

	public static function type( $field_key ) {
		$all = self::all();
		return isset( $all[ $field_key ] ) ? $all[ $field_key ]['type'] : 'text';
	}

	public static function enabled_field_keys() {
		$enabled = AAS_Settings::get( 'aas_general', 'enabled_fields', array() );
		$keys    = array();
		foreach ( self::all() as $key => $meta ) {
			if ( ! empty( $enabled[ $key ] ) ) {
				$keys[] = $key;
			}
		}
		return $keys;
	}

	public static function get_current_value( $product_id, $field_key ) {
		switch ( $field_key ) {
			case 'post_title':
				return (string) get_post_field( 'post_title', $product_id );
			case 'post_excerpt':
				return (string) get_post_field( 'post_excerpt', $product_id );
			case 'post_content':
				return (string) get_post_field( 'post_content', $product_id );
			case 'meta_title':
				return (string) get_post_meta( $product_id, '_yoast_wpseo_title', true );
			case 'meta_description':
				return (string) get_post_meta( $product_id, '_yoast_wpseo_metadesc', true );
			case 'focus_keyword':
				return (string) get_post_meta( $product_id, '_yoast_wpseo_focuskw', true );
			case 'meta_keywords':
				return (string) get_post_meta( $product_id, '_yoast_wpseo_metakeywords', true );
			case 'image_alt':
				$thumb_id = get_post_thumbnail_id( $product_id );
				return $thumb_id ? (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) : '';
		}
		return '';
	}

	public static function get_all_current_values( $product_id ) {
		$values = array();
		foreach ( array_keys( self::all() ) as $field_key ) {
			$values[ $field_key ] = self::get_current_value( $product_id, $field_key );
		}
		return $values;
	}

	/**
	 * Sanitizes a raw AI-provided value according to the field's type.
	 */
	public static function sanitize_value( $field_key, $raw_value ) {
		$raw_value = is_scalar( $raw_value ) ? (string) $raw_value : '';
		$raw_value = trim( $raw_value );

		if ( 'html' === self::type( $field_key ) ) {
			return wp_kses_post( $raw_value );
		}

		if ( 'textarea' === self::type( $field_key ) ) {
			return sanitize_textarea_field( $raw_value );
		}

		return sanitize_text_field( $raw_value );
	}

	/**
	 * Writes an already-sanitized value to its destination (post field, postmeta, or attachment meta).
	 */
	public static function save_value( $product_id, $field_key, $value ) {
		switch ( $field_key ) {
			case 'post_title':
				wp_update_post( array( 'ID' => $product_id, 'post_title' => $value ) );
				return true;
			case 'post_excerpt':
				wp_update_post( array( 'ID' => $product_id, 'post_excerpt' => $value ) );
				return true;
			case 'post_content':
				wp_update_post( array( 'ID' => $product_id, 'post_content' => $value ) );
				return true;
			case 'meta_title':
				update_post_meta( $product_id, '_yoast_wpseo_title', $value );
				return true;
			case 'meta_description':
				update_post_meta( $product_id, '_yoast_wpseo_metadesc', $value );
				return true;
			case 'focus_keyword':
				update_post_meta( $product_id, '_yoast_wpseo_focuskw', $value );
				return true;
			case 'meta_keywords':
				update_post_meta( $product_id, '_yoast_wpseo_metakeywords', $value );
				return true;
			case 'image_alt':
				$thumb_id = get_post_thumbnail_id( $product_id );
				if ( $thumb_id ) {
					update_post_meta( $thumb_id, '_wp_attachment_image_alt', $value );
					return true;
				}
				return false;
		}
		return false;
	}
}
