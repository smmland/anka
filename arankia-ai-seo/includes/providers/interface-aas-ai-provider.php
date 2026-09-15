<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract every AI provider adapter must implement so the generator engine
 * can call any of them interchangeably.
 */
interface AAS_AI_Provider {

	/**
	 * Machine id, e.g. "openai", "anthropic", "generic".
	 */
	public function id();

	/**
	 * Human readable label shown in admin dropdowns.
	 */
	public function label();

	/**
	 * Whether this provider has the minimum config (API key etc.) to be used.
	 */
	public function is_configured();

	/**
	 * Sends the prompt to the provider and returns the parsed JSON response
	 * as an associative array, or a WP_Error on failure (network, API, or
	 * invalid JSON in the model's reply).
	 *
	 * @param string $system_prompt
	 * @param string $user_prompt
	 * @return array|WP_Error
	 */
	public function generate_json( $system_prompt, $user_prompt );
}
