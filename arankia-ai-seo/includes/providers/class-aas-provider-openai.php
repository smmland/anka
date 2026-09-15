<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAS_Provider_OpenAI implements AAS_AI_Provider {

	private $api_key;
	private $model;
	private $base_url = 'https://api.openai.com/v1';

	public function __construct( $settings = array() ) {
		$this->api_key = isset( $settings['api_key'] ) ? trim( $settings['api_key'] ) : '';
		$this->model    = ! empty( $settings['model'] ) ? $settings['model'] : 'gpt-4o-mini';
	}

	public function id() {
		return 'openai';
	}

	public function label() {
		return __( 'OpenAI (ChatGPT)', 'arankia-ai-seo' );
	}

	public function is_configured() {
		return '' !== $this->api_key;
	}

	public function generate_json( $system_prompt, $user_prompt ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'aas_no_key', __( 'کلید API OpenAI تنظیم نشده است.', 'arankia-ai-seo' ) );
		}

		$body = array(
			'model'           => $this->model,
			'temperature'     => 0.4,
			'response_format' => array( 'type' => 'json_object' ),
			'messages'        => array(
				array( 'role' => 'system', 'content' => $system_prompt ),
				array( 'role' => 'user', 'content' => $user_prompt ),
			),
		);

		$response = wp_remote_post( $this->base_url . '/chat/completions', array(
			'timeout' => 60,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
			),
			'body' => wp_json_encode( $body ),
		) );

		return AAS_Provider_Helper::parse_openai_style_response( $response, $this->label() );
	}
}
