<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD wrapper around the wp_aas_suggestions table.
 */
class AAS_Suggestion {

	const STATUS_PENDING  = 'pending';
	const STATUS_APPLIED  = 'applied';
	const STATUS_REJECTED = 'rejected';

	/**
	 * Replaces any existing pending suggestions for a product with a fresh
	 * batch. $rows is field_key => array( 'old' => ..., 'new' => ... ).
	 */
	public static function replace_pending( $product_id, $batch_id, $provider_id, $rows ) {
		global $wpdb;
		$table = AAS_Install::table_name();

		// Drop previous *pending* suggestions for this product; applied/rejected history is kept.
		$wpdb->delete( $table, array( 'product_id' => $product_id, 'status' => self::STATUS_PENDING ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$now = current_time( 'mysql' );

		foreach ( $rows as $field_key => $values ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'product_id' => $product_id,
					'batch_id'   => $batch_id,
					'field_key'  => $field_key,
					'old_value'  => isset( $values['old'] ) ? $values['old'] : '',
					'new_value'  => isset( $values['new'] ) ? $values['new'] : '',
					'status'     => self::STATUS_PENDING,
					'provider'   => $provider_id,
					'created_at' => $now,
					'updated_at' => $now,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
		}
	}

	public static function get_pending( $product_id ) {
		global $wpdb;
		$table = AAS_Install::table_name();
		return $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$table} WHERE product_id = %d AND status = %s ORDER BY id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$product_id,
			self::STATUS_PENDING
		) );
	}

	public static function get_pending_map( $product_id ) {
		$rows = self::get_pending( $product_id );
		$map  = array();
		foreach ( $rows as $row ) {
			$map[ $row->field_key ] = $row;
		}
		return $map;
	}

	public static function update_status( $id, $status, $reviewed_by = null ) {
		global $wpdb;
		$table = AAS_Install::table_name();
		$data  = array(
			'status'     => $status,
			'updated_at' => current_time( 'mysql' ),
		);
		$format = array( '%s', '%s' );
		if ( null !== $reviewed_by ) {
			$data['reviewed_by'] = $reviewed_by;
			$format[]             = '%d';
		}
		$wpdb->update( $table, $data, array( 'id' => $id ), $format, array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public static function count_pending_products() {
		global $wpdb;
		$table = AAS_Install::table_name();
		return (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT COUNT(DISTINCT product_id) FROM {$table} WHERE status = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			self::STATUS_PENDING
		) );
	}

	public static function get_pending_product_ids( $limit = 50 ) {
		global $wpdb;
		$table = AAS_Install::table_name();
		return $wpdb->get_col( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT DISTINCT product_id FROM {$table} WHERE status = %s ORDER BY product_id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			self::STATUS_PENDING,
			$limit
		) );
	}

	/**
	 * Returns the next product id (after $current_id) that still has pending
	 * suggestions, for the "next" button in the review slider.
	 */
	public static function get_next_pending_product_id( $current_id = 0 ) {
		global $wpdb;
		$table = AAS_Install::table_name();

		$next = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT DISTINCT product_id FROM {$table} WHERE status = %s AND product_id > %d ORDER BY product_id ASC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			self::STATUS_PENDING,
			$current_id
		) );

		if ( $next ) {
			return (int) $next;
		}

		// Wrap around to the first pending product (excluding the current one).
		$first = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT DISTINCT product_id FROM {$table} WHERE status = %s AND product_id != %d ORDER BY product_id ASC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			self::STATUS_PENDING,
			$current_id
		) );

		return $first ? (int) $first : 0;
	}
}
