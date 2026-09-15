<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAS_Provider_Factory {

	public static function get_ids() {
		return array( 'openai', 'anthropic', 'generic' );
	}

	/**
	 * @param string $provider_id
	 * @return AAS_AI_Provider|null
	 */
	public static function get( $provider_id ) {
		$settings = AAS_Settings::get_provider_settings( $provider_id );

		switch ( $provider_id ) {
			case 'openai':
				return new AAS_Provider_OpenAI( $settings );
			case 'anthropic':
				return new AAS_Provider_Anthropic( $settings );
			case 'generic':
				return new AAS_Provider_Generic( $settings );
		}

		return null;
	}

	/**
	 * Returns provider instances that are both enabled in settings and have
	 * the minimum configuration to actually be called.
	 *
	 * @return AAS_AI_Provider[]
	 */
	public static function get_configured() {
		$providers = array();
		$settings  = AAS_Settings::get_group( 'aas_providers' );

		foreach ( self::get_ids() as $id ) {
			if ( empty( $settings[ $id ]['enabled'] ) ) {
				continue;
			}
			$provider = self::get( $id );
			if ( $provider && $provider->is_configured() ) {
				$providers[ $id ] = $provider;
			}
		}

		return $providers;
	}
}
