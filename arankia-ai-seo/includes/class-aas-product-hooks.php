<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Auto-triggers AI SEO generation when a new product is published, if the
 * admin has turned that on in settings. Generation always lands as a
 * pending suggestion batch — nothing is ever applied to the live product
 * without an explicit approval on the review screen.
 */
class AAS_Product_Hooks {

	public function __construct() {
		add_action( 'transition_post_status', array( $this, 'on_status_transition' ), 10, 3 );
	}

	public function on_status_transition( $new_status, $old_status, $post ) {
		if ( 'product' !== $post->post_type ) {
			return;
		}

		if ( 'publish' === $new_status && 'publish' !== $old_status ) {
			if ( ! AAS_Settings::get( 'aas_general', 'auto_trigger', 0 ) ) {
				return;
			}

			// Avoid re-queuing a product that already has a batch waiting for review.
			$status = get_post_meta( $post->ID, '_aas_status', true );
			if ( in_array( $status, array( AAS_Generator::STATUS_PENDING_REVIEW, AAS_Generator::STATUS_QUEUED, AAS_Generator::STATUS_PROCESSING ), true ) ) {
				return;
			}

			AAS_Queue::enqueue( $post->ID, null, 10 );
		}
	}
}
