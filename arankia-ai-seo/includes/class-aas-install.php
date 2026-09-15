<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles DB table creation/upgrades for the suggestions table.
 */
class AAS_Install {

	const DB_VERSION = '1.0.0';

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'aas_suggestions';
	}

	public static function activate() {
		self::create_tables();
		AAS_Settings::install_defaults();
		update_option( 'aas_db_version', self::DB_VERSION );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'aas_db_version' ) !== self::DB_VERSION ) {
			self::create_tables();
			AAS_Settings::install_defaults();
			update_option( 'aas_db_version', self::DB_VERSION );
		}
	}

	private static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT UNSIGNED NOT NULL,
			batch_id VARCHAR(40) NOT NULL,
			field_key VARCHAR(50) NOT NULL,
			old_value LONGTEXT NULL,
			new_value LONGTEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			provider VARCHAR(50) NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			reviewed_by BIGINT UNSIGNED NULL,
			PRIMARY KEY  (id),
			KEY product_id (product_id),
			KEY batch_id (batch_id),
			KEY status (status)
		) {$charset_collate};";

		dbDelta( $sql );
	}
}
