<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAS_Provider_Anthropic implements AAS_AI_Provider {

	private $api_key;
	private $model;
	private $base_url = 'https://api.anthropic.com/v1';

	public function __construct( $settings = array() ) {
		$this->api_key = isset( $settings['api_key'] ) ? trim( $settings['api_key'] ) : '';
		$this->model    = ! empty( $settings['model'] ) ? $settings['model'] : 'claude-3-5-haiku-latest';
	}

	public function id() {
		return 'anthropic';
	}

	public function label() {
		return __( 'Anthropic (Claude)', 'arankia-ai-seo' );
	}

	public function is_configured() {
		return '' !== $this->api_key;
	}

	public function generate_json( $system_prompt, $user_prompt ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'aas_no_key', __( 'کلید API Claude تنظیم نشده است.', 'arankia-ai-seo' ) );
		}

		$body = array(
			'model'      => $this->model,
			'max_tokens' => 2048,
			'system'     => $system_prompt,
			'messages'   => array(
				array( 'role' => 'user', 'content' => $user_prompt ),
			),
		);

		$response = wp_remote_post( $this->base_url . '/messages', array(
			'timeout' => 60,
			'headers' => array(
				'Content-Type'      => 'application/json',
				'x-api-key'         => $this->api_key,
				'anthropic-version' => '2023-06-01',
			),
			'body' => wp_json_encode( $body ),
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'aas_http_error', sprintf(
				/* translators: %s: error message */
				__( 'خطا در ارتباط با Claude: %s', 'arankia-ai-seo' ),
				$response->get_error_message()
			) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $data ) && isset( $data['error']['message'] ) ? $data['error']['message'] : $raw;
			return new WP_Error( 'aas_api_error', sprintf(
				/* translators: %s: error message */
				__( 'خطای Claude: %s', 'arankia-ai-seo' ),
				wp_strip_all_tags( (string) $message )
			) );
		}

		$content = isset( $data['content'][0]['text'] ) ? $data['content'][0]['text'] : '';

		return AAS_Provider_Helper::extract_json( $content, $this->label() );
	}
}
