<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAS_Ajax {

	public function __construct() {
		add_action( 'wp_ajax_aas_generate', array( $this, 'generate' ) );
		add_action( 'wp_ajax_aas_bulk_generate', array( $this, 'bulk_generate' ) );
		add_action( 'wp_ajax_aas_apply', array( $this, 'apply' ) );
		add_action( 'wp_ajax_aas_reject_all', array( $this, 'reject_all' ) );
	}

	private function check_access() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'arankia-ai-seo' ) ), 403 );
		}
		check_ajax_referer( AAS_Admin::ADMIN_NONCE, 'nonce' );
	}

	public function generate() {
		$this->check_access();

		$product_id  = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$provider_id = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : null;

		$result = AAS_Generator::run( $product_id, $provider_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message'      => empty( $result['fields'] )
				? __( 'هوش مصنوعی تغییری نسبت به وضعیت فعلی پیشنهاد نداد.', 'arankia-ai-seo' )
				: __( 'پیشنهاد سئو با موفقیت تولید شد.', 'arankia-ai-seo' ),
			'redirect_url' => admin_url( 'admin.php?page=' . AAS_Admin::PAGE_REVIEW . '&product_id=' . $product_id ),
		) );
	}

	public function bulk_generate() {
		$this->check_access();

		$product_ids = isset( $_POST['product_ids'] ) && is_array( $_POST['product_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['product_ids'] ) ) : array();
		$provider_id = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : null;
		$product_ids = array_filter( array_unique( $product_ids ) );

		if ( empty( $product_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'هیچ محصولی انتخاب نشده است.', 'arankia-ai-seo' ) ) );
		}

		$count = AAS_Queue::enqueue_bulk( $product_ids, $provider_id );

		wp_send_json_success( array(
			/* translators: %d: number of products */
			'message' => sprintf( __( '%d محصول در صف تولید سئو قرار گرفت.', 'arankia-ai-seo' ), $count ),
		) );
	}

	public function apply() {
		$this->check_access();

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$batch_id   = isset( $_POST['batch_id'] ) ? sanitize_text_field( wp_unslash( $_POST['batch_id'] ) ) : '';
		$accepted   = isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['fields'] ) ) : array();

		if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
			wp_send_json_error( array( 'message' => __( 'محصول معتبر نیست.', 'arankia-ai-seo' ) ) );
		}

		$pending = AAS_Suggestion::get_pending( $product_id );
		$user_id = get_current_user_id();
		$applied = array();

		foreach ( $pending as $row ) {
			if ( in_array( $row->field_key, $accepted, true ) ) {
				AAS_Fields::save_value( $product_id, $row->field_key, $row->new_value );
				AAS_Suggestion::update_status( $row->id, AAS_Suggestion::STATUS_APPLIED, $user_id );
				$applied[] = $row->field_key;
			} else {
				AAS_Suggestion::update_status( $row->id, AAS_Suggestion::STATUS_REJECTED, $user_id );
			}
		}

		update_post_meta( $product_id, '_aas_status', 'reviewed' );

		$next_id = AAS_Suggestion::get_next_pending_product_id( $product_id );

		wp_send_json_success( array(
			/* translators: %d: number of applied fields */
			'message'      => sprintf( __( '%d فیلد با موفقیت روی محصول اعمال شد.', 'arankia-ai-seo' ), count( $applied ) ),
			'next_id'      => $next_id,
			'redirect_url' => $next_id
				? admin_url( 'admin.php?page=' . AAS_Admin::PAGE_REVIEW . '&product_id=' . $next_id )
				: admin_url( 'admin.php?page=' . AAS_Admin::PAGE_DASHBOARD ),
		) );
	}

	public function reject_all() {
		$this->check_access();

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

		if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
			wp_send_json_error( array( 'message' => __( 'محصول معتبر نیست.', 'arankia-ai-seo' ) ) );
		}

		$pending = AAS_Suggestion::get_pending( $product_id );
		$user_id = get_current_user_id();

		foreach ( $pending as $row ) {
			AAS_Suggestion::update_status( $row->id, AAS_Suggestion::STATUS_REJECTED, $user_id );
		}

		update_post_meta( $product_id, '_aas_status', 'reviewed' );

		$next_id = AAS_Suggestion::get_next_pending_product_id( $product_id );

		wp_send_json_success( array(
			'message'      => __( 'همه پیشنهادها رد شدند.', 'arankia-ai-seo' ),
			'next_id'      => $next_id,
			'redirect_url' => $next_id
				? admin_url( 'admin.php?page=' . AAS_Admin::PAGE_REVIEW . '&product_id=' . $next_id )
				: admin_url( 'admin.php?page=' . AAS_Admin::PAGE_DASHBOARD ),
		) );
	}
}
