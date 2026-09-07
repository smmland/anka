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
	$wallet_table     = $wpdb->prefix . 'arankia_wallet_transactions';
	$news_table       = $wpdb->prefix . 'arankia_newsletter_subscribers';

	$sql = "CREATE TABLE {$wallet_table} (
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
	) {$charset_collate};

	CREATE TABLE {$news_table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		email VARCHAR(190) NOT NULL,
		created_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY email (email)
	) {$charset_collate};";

	dbDelta( $sql );

	update_option( 'arankia_wallet_db_version', '1.1' );

	// All custom account endpoints (wallet/wishlist/tickets/purchased-products)
	// register themselves on `init` — but `init` has already run for THIS
	// request (using the *old* theme's functions.php) by the time
	// `after_switch_theme` fires, so the new theme's add_rewrite_endpoint()
	// calls haven't happened yet and flushing right here would save rules
	// that are missing them. Flag a flush for the very next request instead,
	// once `init` has run with the new theme actually active.
	update_option( 'arankia_needs_rewrite_flush', 1 );
}
add_action( 'after_switch_theme', 'arankia_theme_activation' );

/**
 * Safety net: if the wallet table is somehow missing (e.g. migrated site),
 * create it lazily the next time it's needed.
 */
function arankia_maybe_upgrade_db() {
	if ( '1.1' !== get_option( 'arankia_wallet_db_version' ) ) {
		arankia_theme_activation();
	}
}
add_action( 'init', 'arankia_maybe_upgrade_db', 20 );

/**
 * Runs after every account endpoint has had a chance to register itself on
 * `init` (priority 30, well after the default-priority registrations).
 */
function arankia_maybe_flush_rewrite_rules() {
	if ( get_option( 'arankia_needs_rewrite_flush' ) ) {
		flush_rewrite_rules();
		delete_option( 'arankia_needs_rewrite_flush' );
	}
}
add_action( 'init', 'arankia_maybe_flush_rewrite_rules', 30 );
