<?php
/**
 * Runs once when the theme is activated: creates the wallet ledger table,
 * registers rewrite endpoints and flushes rewrite rules.
 *
 * @package Arankia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function arankia_theme_activation() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();
	$table            = $wpdb->prefix . 'arankia_wallet_transactions';

	$sql = "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id BIGINT UNSIGNED NOT NULL,
		type VARCHAR(10) NOT NULL,
		amount DECIMAL(18,2) NOT NULL,
		balance_after DECIMAL(18,2) NOT NULL,
		description VARCHAR(255) NOT NULL DEFAULT '',
		order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		KEY user_id (user_id)
	) {$charset_collate};";

	dbDelta( $sql );

	update_option( 'arankia_wallet_db_version', '1.0' );

	// All custom account endpoints (wallet/wishlist/tickets/purchased-products)
	// register themselves on `init`, which has already run by the time a
	// theme activation request reaches this point — so a plain flush here
	// is enough to pick them all up.
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'arankia_theme_activation' );

/**
 * Safety net: if the wallet table is somehow missing (e.g. migrated site),
 * create it lazily the next time it's needed.
 */
function arankia_maybe_upgrade_db() {
	if ( '1.0' !== get_option( 'arankia_wallet_db_version' ) ) {
		arankia_theme_activation();
	}
}
add_action( 'init', 'arankia_maybe_upgrade_db', 20 );
