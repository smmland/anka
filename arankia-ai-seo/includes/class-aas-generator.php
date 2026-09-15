<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orchestrates one "run AI SEO on this product" pass: builds the prompt,
 * calls the provider, sanitizes the response, and stores it as a pending
 * suggestion batch. Never writes to the live product — that only happens
 * once an admin approves a field on the review screen.
 */
class AAS_Generator {

	const STATUS_QUEUED   = 'queued';
	const STATUS_PROCESSING = 'processing';
	const STATUS_PENDING_REVIEW = 'pending_review';
	const STATUS_ERROR    = 'error';

	/**
	 * @param int         $product_id
	 * @param string|null $provider_id Defaults to the active provider from settings.
	 * @return array|WP_Error array( 'batch_id' => ..., 'provider' => ... ) on success.
	 */
	public static function run( $product_id, $provider_id = null ) {
		$product_id = absint( $product_id );

		if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
			return new WP_Error( 'aas_invalid_product', __( 'محصول معتبر نیست.', 'arankia-ai-seo' ) );
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			return new WP_Error( 'aas_no_woocommerce', __( 'ووکامرس فعال نیست.', 'arankia-ai-seo' ) );
		}

		$provider_id = $provider_id ? $provider_id : AAS_Settings::get( 'aas_general', 'active_provider', 'openai' );
		$provider    = AAS_Provider_Factory::get( $provider_id );

		if ( ! $provider ) {
			return new WP_Error( 'aas_unknown_provider', __( 'سرویس هوش مصنوعی انتخاب‌شده نامعتبر است.', 'arankia-ai-seo' ) );
		}

		if ( ! $provider->is_configured() ) {
			$error = new WP_Error( 'aas_not_configured', sprintf(
				/* translators: %s: provider label */
				__( 'سرویس %s تنظیم نشده است (کلید API یا آدرس را در تنظیمات وارد کنید).', 'arankia-ai-seo' ),
				$provider->label()
			) );
			self::mark_error( $product_id, $error );
			return $error;
		}

		update_post_meta( $product_id, '_aas_status', self::STATUS_PROCESSING );

		$field_keys = AAS_Fields::enabled_field_keys();
		if ( empty( $field_keys ) ) {
			$error = new WP_Error( 'aas_no_fields', __( 'هیچ فیلدی برای تولید سئو فعال نیست (در تنظیمات فعال کنید).', 'arankia-ai-seo' ) );
			self::mark_error( $product_id, $error );
			return $error;
		}

		$prompts = AAS_Prompt_Builder::build( $product_id, $field_keys );
		$result  = $provider->generate_json( $prompts['system'], $prompts['user'] );

		if ( is_wp_error( $result ) ) {
			self::mark_error( $product_id, $result );
			return $result;
		}

		$rows = array();
		foreach ( $field_keys as $field_key ) {
			if ( ! array_key_exists( $field_key, $result ) ) {
				continue;
			}

			$new_value = AAS_Fields::sanitize_value( $field_key, $result[ $field_key ] );
			if ( '' === $new_value ) {
				continue; // AI had nothing to propose for this field; leave it out of the batch.
			}

			$old_value = AAS_Fields::get_current_value( $product_id, $field_key );

			if ( $new_value === $old_value ) {
				continue; // No change worth reviewing.
			}

			$rows[ $field_key ] = array( 'old' => $old_value, 'new' => $new_value );
		}

		$batch_id = substr( md5( $product_id . '-' . microtime( true ) . '-' . wp_rand() ), 0, 16 );

		AAS_Suggestion::replace_pending( $product_id, $batch_id, $provider->id(), $rows );

		update_post_meta( $product_id, '_aas_status', empty( $rows ) ? 'no_changes' : self::STATUS_PENDING_REVIEW );
		update_post_meta( $product_id, '_aas_last_batch_id', $batch_id );
		update_post_meta( $product_id, '_aas_last_provider', $provider->id() );
		update_post_meta( $product_id, '_aas_last_generated', current_time( 'mysql' ) );
		delete_post_meta( $product_id, '_aas_last_error' );

		return array( 'batch_id' => $batch_id, 'provider' => $provider->id(), 'fields' => array_keys( $rows ) );
	}

	private static function mark_error( $product_id, WP_Error $error ) {
		update_post_meta( $product_id, '_aas_status', self::STATUS_ERROR );
		update_post_meta( $product_id, '_aas_last_error', $error->get_error_message() );
	}
}
