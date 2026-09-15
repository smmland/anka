<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns a WooCommerce product into a system/user prompt pair that asks the
 * AI for strict JSON containing only the enabled SEO field keys.
 */
class AAS_Prompt_Builder {

	public static function build( $product_id, $field_keys ) {
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;

		$field_labels = array();
		foreach ( $field_keys as $key ) {
			$field_labels[ $key ] = AAS_Fields::label( $key );
		}

		$system = self::build_system_prompt( $field_labels );
		$user   = self::build_user_prompt( $product_id, $product, $field_keys );

		return array( 'system' => $system, 'user' => $user );
	}

	private static function build_system_prompt( $field_labels ) {
		$keys_list = array();
		foreach ( $field_labels as $key => $label ) {
			$keys_list[] = "\"{$key}\" ({$label})";
		}

		$extra = trim( (string) AAS_Settings::get( 'aas_general', 'extra_instructions', '' ) );

		$lines   = array();
		$lines[] = 'شما یک متخصص سئوی فارسی برای فروشگاه‌های اینترنتی (ووکامرس) هستید.';
		$lines[] = 'بر اساس اطلاعات محصولی که کاربر می‌دهد، بهترین مقادیر سئو را پیشنهاد بده.';
		$lines[] = 'فقط بر اساس اطلاعات واقعی داده‌شده بنویس؛ هیچ ویژگی یا ادعای نادرست درباره محصول اضافه نکن.';
		$lines[] = 'خروجی را دقیقاً و فقط به صورت یک JSON معتبر (بدون Markdown، بدون توضیح اضافه، بدون متن قبل یا بعد از JSON) برگردان.';
		$lines[] = 'کلیدهای JSON دقیقاً باید این‌ها باشند: ' . implode( '، ', $keys_list ) . '.';
		$lines[] = 'اگر برای فیلدی پیشنهاد نداری، مقدار آن را رشته خالی "" بگذار؛ کلید را حذف نکن.';
		$lines[] = 'راهنمای هر فیلد در صورت وجود در لیست کلیدها: عنوان متا سئو حداکثر ۶۰ کاراکتر و توضیحات متا سئو بین ۷۰ تا ۱۶۰ کاراکتر و شامل کلیدواژه کانونی باشد؛ کلیدواژه کانونی باید یک عبارت کوتاه و پرجست‌وجو مرتبط با محصول باشد؛ توضیحات کامل محصول باید طبیعی، جذاب و شامل کلیدواژه کانونی باشد.';
		$lines[] = 'تمام مقادیر باید به زبان فارسی باشند.';

		if ( '' !== $extra ) {
			$lines[] = 'دستورالعمل اضافه از طرف مدیر سایت: ' . $extra;
		}

		return implode( "\n", $lines );
	}

	private static function build_user_prompt( $product_id, $product, $field_keys ) {
		$title       = get_the_title( $product_id );
		$categories  = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) );
		$tags        = wp_get_post_terms( $product_id, 'product_tag', array( 'fields' => 'names' ) );
		$content     = wp_strip_all_tags( get_post_field( 'post_content', $product_id ) );
		$excerpt     = wp_strip_all_tags( get_post_field( 'post_excerpt', $product_id ) );
		$sku         = $product && method_exists( $product, 'get_sku' ) ? $product->get_sku() : '';
		$price       = $product && method_exists( $product, 'get_price' ) ? $product->get_price() : '';
		$attributes  = array();

		if ( $product && method_exists( $product, 'get_attributes' ) ) {
			foreach ( $product->get_attributes() as $attribute ) {
				if ( is_a( $attribute, 'WC_Product_Attribute' ) ) {
					$attributes[] = wc_attribute_label( $attribute->get_name() ) . ': ' . implode( '، ', $attribute->get_options() );
				}
			}
		}

		$content = mb_substr( $content, 0, 3500 );
		$excerpt = mb_substr( $excerpt, 0, 800 );

		$lines   = array();
		$lines[] = 'اطلاعات محصول:';
		$lines[] = '- عنوان فعلی: ' . ( $title ? $title : '(خالی)' );
		if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
			$lines[] = '- دسته‌بندی: ' . implode( '، ', $categories );
		}
		if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) {
			$lines[] = '- برچسب‌ها: ' . implode( '، ', $tags );
		}
		if ( '' !== (string) $sku ) {
			$lines[] = '- کد محصول (SKU): ' . $sku;
		}
		if ( '' !== (string) $price ) {
			$lines[] = '- قیمت: ' . $price;
		}
		if ( ! empty( $attributes ) ) {
			$lines[] = '- ویژگی‌ها: ' . implode( ' | ', $attributes );
		}
		$lines[] = '- توضیحات کوتاه فعلی: ' . ( $excerpt ? $excerpt : '(خالی)' );
		$lines[] = '- توضیحات کامل فعلی: ' . ( $content ? $content : '(خالی)' );

		$lines[] = '';
		$lines[] = 'مقادیر سئوی فعلی (برای اطلاع، در صورت مناسب بودن می‌توانی همان را دوباره پیشنهاد بدهی):';
		foreach ( $field_keys as $key ) {
			if ( in_array( $key, array( 'post_title', 'post_excerpt', 'post_content' ), true ) ) {
				continue;
			}
			$current = AAS_Fields::get_current_value( $product_id, $key );
			$lines[] = '- ' . AAS_Fields::label( $key ) . ': ' . ( '' !== $current ? $current : '(خالی)' );
		}

		$lines[] = '';
		$lines[] = 'حالا خروجی JSON را فقط با همین کلیدها برگردان: ' . implode( '، ', $field_keys );

		return implode( "\n", $lines );
	}
}
