<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$general = get_option( 'asl_general' );

if ( empty( $general['delete_data_on_uninstall'] ) ) {
	return;
}

$options = array( 'asl_general', 'asl_melipayamak', 'asl_recaptcha', 'asl_design', 'asl_messages' );

foreach ( $options as $option ) {
	delete_option( $option );
}

if ( ! empty( $general['login_page_id'] ) ) {
	wp_delete_post( $general['login_page_id'], true );
}
