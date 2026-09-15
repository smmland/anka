<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-aas-fields.php';
require_once __DIR__ . '/includes/class-aas-settings.php';

$general = get_option( 'aas_general', array() );

if ( empty( $general['delete_data_on_uninstall'] ) ) {
	return;
}

global $wpdb;

$table = $wpdb->prefix . 'aas_suggestions';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

delete_option( 'aas_general' );
delete_option( 'aas_providers' );
delete_option( 'aas_db_version' );

$product_ids = get_posts( array(
	'post_type'      => 'product',
	'posts_per_page' => -1,
	'fields'         => 'ids',
	'post_status'    => 'any',
) );

foreach ( $product_ids as $product_id ) {
	delete_post_meta( $product_id, '_aas_status' );
	delete_post_meta( $product_id, '_aas_last_batch_id' );
	delete_post_meta( $product_id, '_aas_last_provider' );
	delete_post_meta( $product_id, '_aas_last_generated' );
	delete_post_meta( $product_id, '_aas_last_error' );
}
