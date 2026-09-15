<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin wp-cron wrapper so product-publish hooks and bulk "generate for all"
 * actions don't block the request that triggered them — each product is
 * processed by a scheduled single event that calls AAS_Generator::run().
 */
class AAS_Queue {

	const EVENT_HOOK = 'aas_run_generation_event';

	public function __construct() {
		add_action( self::EVENT_HOOK, array( __CLASS__, 'process_event' ), 10, 2 );
	}

	public static function process_event( $product_id, $provider_id = null ) {
		AAS_Generator::run( $product_id, $provider_id );
	}

	public static function enqueue( $product_id, $provider_id = null, $delay_seconds = 0 ) {
		update_post_meta( $product_id, '_aas_status', AAS_Generator::STATUS_QUEUED );
		wp_schedule_single_event( time() + max( 0, (int) $delay_seconds ), self::EVENT_HOOK, array( $product_id, $provider_id ) );
	}

	/**
	 * Schedules a whole list of products with a small stagger between each,
	 * so we don't fire a burst of simultaneous API calls.
	 */
	public static function enqueue_bulk( array $product_ids, $provider_id = null ) {
		$delay_step = max( 5, (int) AAS_Settings::get( 'aas_general', 'bulk_delay_seconds', 20 ) );
		$offset     = 0;

		foreach ( $product_ids as $product_id ) {
			self::enqueue( $product_id, $provider_id, $offset );
			$offset += $delay_step;
		}

		return count( $product_ids );
	}

	public static function clear_scheduled_events() {
		$timestamp = wp_next_scheduled( self::EVENT_HOOK );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::EVENT_HOOK );
			$timestamp = wp_next_scheduled( self::EVENT_HOOK );
		}
	}
}
