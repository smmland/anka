<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rule-based SEO health checks, independent of any AI provider — this is
 * what powers the "problematic fields" checklist on the review screen and
 * the dashboard's issue counts, and runs even for products that have never
 * been sent to AI.
 */
class AAS_Seo_Checklist {

	const STATUS_OK      = 'ok';
	const STATUS_WARNING = 'warning';
	const STATUS_ERROR   = 'error';

	/**
	 * @return array field_key => array( 'status' => ..., 'messages' => array() )
	 */
	public static function evaluate( $product_id ) {
		$values = AAS_Fields::get_all_current_values( $product_id );
		$result = array();

		$result['post_title'] = self::check_length(
			$values['post_title'], 10, 70,
			__( 'عنوان محصول خالی است.', 'arankia-ai-seo' ),
			__( 'عنوان محصول کوتاه است.', 'arankia-ai-seo' ),
			__( 'عنوان محصول طولانی است.', 'arankia-ai-seo' )
		);

		$result['post_excerpt'] = array(
			'status'   => '' === trim( $values['post_excerpt'] ) ? self::STATUS_WARNING : self::STATUS_OK,
			'messages' => '' === trim( $values['post_excerpt'] ) ? array( __( 'توضیحات کوتاه محصول خالی است.', 'arankia-ai-seo' ) ) : array(),
		);

		$word_count = str_word_count( wp_strip_all_tags( $values['post_content'] ) );
		if ( '' === trim( wp_strip_all_tags( $values['post_content'] ) ) ) {
			$result['post_content'] = array( 'status' => self::STATUS_ERROR, 'messages' => array( __( 'توضیحات کامل محصول خالی است.', 'arankia-ai-seo' ) ) );
		} elseif ( $word_count < 60 ) {
			$result['post_content'] = array( 'status' => self::STATUS_WARNING, 'messages' => array( __( 'توضیحات محصول کوتاه است (محتوای کم برای سئو).', 'arankia-ai-seo' ) ) );
		} else {
			$result['post_content'] = array( 'status' => self::STATUS_OK, 'messages' => array() );
		}

		$result['meta_title'] = self::check_length(
			$values['meta_title'], 30, 60,
			__( 'عنوان متا سئو تنظیم نشده است.', 'arankia-ai-seo' ),
			__( 'عنوان متا سئو کوتاه است.', 'arankia-ai-seo' ),
			__( 'عنوان متا سئو طولانی است و ممکن است در نتایج گوگل بریده شود.', 'arankia-ai-seo' )
		);

		$result['meta_description'] = self::check_length(
			$values['meta_description'], 70, 160,
			__( 'توضیحات متا سئو تنظیم نشده است.', 'arankia-ai-seo' ),
			__( 'توضیحات متا سئو کوتاه است.', 'arankia-ai-seo' ),
			__( 'توضیحات متا سئو طولانی است و ممکن است در نتایج گوگل بریده شود.', 'arankia-ai-seo' )
		);

		$focus_keyword = trim( $values['focus_keyword'] );
		if ( '' === $focus_keyword ) {
			$result['focus_keyword'] = array( 'status' => self::STATUS_ERROR, 'messages' => array( __( 'کلیدواژه کانونی تنظیم نشده است.', 'arankia-ai-seo' ) ) );
		} else {
			$messages = array();
			if ( false === mb_stripos( $values['post_title'], $focus_keyword ) ) {
				$messages[] = __( 'کلیدواژه کانونی در عنوان محصول دیده نمی‌شود.', 'arankia-ai-seo' );
			}
			if ( false === mb_stripos( wp_strip_all_tags( $values['post_content'] ), $focus_keyword ) ) {
				$messages[] = __( 'کلیدواژه کانونی در توضیحات محصول دیده نمی‌شود.', 'arankia-ai-seo' );
			}
			if ( false === mb_stripos( $values['meta_description'], $focus_keyword ) ) {
				$messages[] = __( 'کلیدواژه کانونی در توضیحات متا سئو دیده نمی‌شود.', 'arankia-ai-seo' );
			}
			$result['focus_keyword'] = array(
				'status'   => empty( $messages ) ? self::STATUS_OK : self::STATUS_WARNING,
				'messages' => $messages,
			);
		}

		$result['meta_keywords'] = array(
			'status'   => self::STATUS_OK,
			'messages' => '' === trim( $values['meta_keywords'] )
				? array( __( 'کلیدواژه‌های متا خالی است (این فیلد در رتبه‌بندی گوگل تاثیری ندارد، اختیاری است).', 'arankia-ai-seo' ) )
				: array(),
		);

		if ( ! has_post_thumbnail( $product_id ) ) {
			$result['image_alt'] = array( 'status' => self::STATUS_WARNING, 'messages' => array( __( 'محصول تصویر شاخص ندارد.', 'arankia-ai-seo' ) ) );
		} elseif ( '' === trim( $values['image_alt'] ) ) {
			$result['image_alt'] = array( 'status' => self::STATUS_ERROR, 'messages' => array( __( 'متن جایگزین (ALT) تصویر شاخص خالی است.', 'arankia-ai-seo' ) ) );
		} else {
			$result['image_alt'] = array( 'status' => self::STATUS_OK, 'messages' => array() );
		}

		return $result;
	}

	public static function count_issues( $product_id ) {
		$counts = array( self::STATUS_ERROR => 0, self::STATUS_WARNING => 0 );
		foreach ( self::evaluate( $product_id ) as $field ) {
			if ( isset( $counts[ $field['status'] ] ) ) {
				$counts[ $field['status'] ]++;
			}
		}
		return $counts;
	}

	private static function check_length( $value, $min, $max, $empty_message, $short_message, $long_message ) {
		$value = trim( wp_strip_all_tags( (string) $value ) );
		$len   = mb_strlen( $value );

		if ( '' === $value ) {
			return array( 'status' => self::STATUS_ERROR, 'messages' => array( $empty_message ) );
		}
		if ( $len < $min ) {
			return array( 'status' => self::STATUS_WARNING, 'messages' => array( $short_message ) );
		}
		if ( $len > $max ) {
			return array( 'status' => self::STATUS_WARNING, 'messages' => array( $long_message ) );
		}
		return array( 'status' => self::STATUS_OK, 'messages' => array() );
	}
}
