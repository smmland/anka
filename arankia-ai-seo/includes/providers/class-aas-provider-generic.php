<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generic adapter for any OpenAI-compatible chat-completions endpoint —
 * covers most Iranian AI gateways (which typically mirror the OpenAI API
 * shape) without needing a dedicated adapter per service. Configure the
 * base URL, auth header, and model from the settings screen.
 */
class AAS_Provider_Generic implements AAS_AI_Provider {

	private $label;
	private $api_key;
	private $model;
	private $base_url;
	private $endpoint_path;
	private $auth_header_name;
	private $auth_header_prefix;

	public function __construct( $settings = array() ) {
		$this->label              = ! empty( $settings['label'] ) ? $settings['label'] : __( 'سرویس هوش مصنوعی سفارشی', 'arankia-ai-seo' );
		$this->api_key            = isset( $settings['api_key'] ) ? trim( $settings['api_key'] ) : '';
		$this->model              = isset( $settings['model'] ) ? $settings['model'] : '';
		$this->base_url           = isset( $settings['base_url'] ) ? untrailingslashit( trim( $settings['base_url'] ) ) : '';
		$this->endpoint_path      = ! empty( $settings['endpoint_path'] ) ? $settings['endpoint_path'] : '/chat/completions';
		$this->auth_header_name   = ! empty( $settings['auth_header_name'] ) ? $settings['auth_header_name'] : 'Authorization';
		$this->auth_header_prefix = isset( $settings['auth_header_prefix'] ) ? $settings['auth_header_prefix'] : 'Bearer ';
	}

	public function id() {
		return 'generic';
	}

	public function label() {
		return $this->label;
	}

	public function is_configured() {
		return '' !== $this->base_url && '' !== $this->api_key && '' !== $this->model;
	}

	public function generate_json( $system_prompt, $user_prompt ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'aas_no_key', sprintf(
				/* translators: %s: provider label */
				__( 'تنظیمات سرویس %s کامل نیست (آدرس، کلید یا مدل خالی است).', 'arankia-ai-seo' ),
				$this->label
			) );
		}

		$body = array(
			'model'       => $this->model,
			'temperature' => 0.4,
			'messages'    => array(
				array( 'role' => 'system', 'content' => $system_prompt ),
				array( 'role' => 'user', 'content' => $user_prompt ),
			),
		);

		$headers = array(
			'Content-Type' => 'application/json',
		);
		$headers[ $this->auth_header_name ] = $this->auth_header_prefix . $this->api_key;

		$url = $this->base_url . '/' . ltrim( $this->endpoint_path, '/' );

		$response = wp_remote_post( $url, array(
			'timeout' => 60,
			'headers' => $headers,
			'body'    => wp_json_encode( $body ),
		) );

		return AAS_Provider_Helper::parse_openai_style_response( $response, $this->label );
	}
}
