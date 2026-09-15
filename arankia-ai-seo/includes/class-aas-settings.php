<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central place for option groups, defaults, and read helpers.
 * Mirrors the pattern used by the arankia-sms-login plugin.
 */
class AAS_Settings {

	const GROUPS = array( 'aas_general', 'aas_providers' );

	public static function defaults() {
		return array(
			'aas_general' => array(
				'auto_trigger'         => 0,
				'active_provider'      => 'openai',
				'enabled_fields'       => array_fill_keys( array_keys( AAS_Fields::all() ), 1 ),
				'extra_instructions'   => '',
				'bulk_delay_seconds'   => 20,
				'delete_data_on_uninstall' => 0,
			),
			'aas_providers' => array(
				'openai' => array(
					'enabled' => 0,
					'api_key' => '',
					'model'   => 'gpt-4o-mini',
				),
				'anthropic' => array(
					'enabled' => 0,
					'api_key' => '',
					'model'   => 'claude-3-5-haiku-latest',
				),
				'generic' => array(
					'enabled'          => 0,
					'label'            => '',
					'base_url'         => '',
					'endpoint_path'    => '/chat/completions',
					'api_key'          => '',
					'model'            => '',
					'auth_header_name' => 'Authorization',
					'auth_header_prefix' => 'Bearer ',
				),
			),
		);
	}

	public static function install_defaults() {
		foreach ( self::defaults() as $group => $values ) {
			if ( false === get_option( $group, false ) ) {
				add_option( $group, $values );
			}
		}
	}

	public static function get_group( $group ) {
		$defaults = self::defaults();
		if ( ! isset( $defaults[ $group ] ) ) {
			return array();
		}
		$stored = get_option( $group, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return self::deep_merge( $defaults[ $group ], $stored );
	}

	public static function get( $group, $key, $fallback = null ) {
		$values = self::get_group( $group );
		return array_key_exists( $key, $values ) ? $values[ $key ] : $fallback;
	}

	public static function get_provider_settings( $provider_id ) {
		$providers = self::get_group( 'aas_providers' );
		return isset( $providers[ $provider_id ] ) ? $providers[ $provider_id ] : array();
	}

	private static function deep_merge( $defaults, $stored ) {
		foreach ( $defaults as $key => $value ) {
			if ( is_array( $value ) && isset( $stored[ $key ] ) && is_array( $stored[ $key ] ) ) {
				$stored[ $key ] = self::deep_merge( $value, $stored[ $key ] );
			} elseif ( ! array_key_exists( $key, $stored ) ) {
				$stored[ $key ] = $value;
			}
		}
		return $stored;
	}
}
