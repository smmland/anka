<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared response-parsing helpers used by the OpenAI-compatible adapters
 * (OpenAI itself and the generic Iranian-gateway adapter both speak the
 * same "chat/completions" response shape).
 */
class AAS_Provider_Helper {

	public static function parse_openai_style_response( $response, $provider_label ) {
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'aas_http_error', sprintf(
				/* translators: 1: provider label, 2: error message */
				__( 'خطا در ارتباط با %1$s: %2$s', 'arankia-ai-seo' ),
				$provider_label,
				$response->get_error_message()
			) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $data ) && isset( $data['error']['message'] ) ? $data['error']['message'] : $raw;
			return new WP_Error( 'aas_api_error', sprintf(
				/* translators: 1: provider label, 2: error message */
				__( 'خطای %1$s: %2$s', 'arankia-ai-seo' ),
				$provider_label,
				wp_strip_all_tags( (string) $message )
			) );
		}

		$content = isset( $data['choices'][0]['message']['content'] ) ? $data['choices'][0]['message']['content'] : '';

		return self::extract_json( $content, $provider_label );
	}

	public static function extract_json( $text, $provider_label ) {
		$text = trim( (string) $text );

		// Strip ```json ... ``` or ``` ... ``` fences if the model wrapped its reply.
		if ( preg_match( '/```(?:json)?\s*(\{.*\})\s*```/s', $text, $matches ) ) {
			$text = $matches[1];
		}

		// Fall back to grabbing the outermost {...} block.
		if ( '' !== $text && '{' !== substr( $text, 0, 1 ) ) {
			$start = strpos( $text, '{' );
			$end   = strrpos( $text, '}' );
			if ( false !== $start && false !== $end && $end > $start ) {
				$text = substr( $text, $start, $end - $start + 1 );
			}
		}

		$decoded = json_decode( $text, true );

		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'aas_invalid_json', sprintf(
				/* translators: %s: provider label */
				__( 'پاسخ %s به صورت JSON معتبر نبود.', 'arankia-ai-seo' ),
				$provider_label
			) );
		}

		return $decoded;
	}
}
