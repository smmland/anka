<?php
/**
 * Thin, defensive wrapper around the Behpardakht Mellat (بانک ملت) SOAP web service.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BMG_API {

	const WSDL_URL     = 'https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl';
	const STARTPAY_URL = 'https://bpm.shaparak.ir/pgwchannel/startpay.mellat';

	/** @var string */
	private $terminal_id;

	/** @var string */
	private $username;

	/** @var string */
	private $password;

	public function __construct( $terminal_id, $username, $password ) {
		$this->terminal_id = $terminal_id;
		$this->username    = $username;
		$this->password    = $password;
	}

	/**
	 * @throws Exception
	 */
	private function get_soap_client() {
		if ( ! extension_loaded( 'soap' ) ) {
			throw new Exception( __( 'اکستنشن PHP SOAP روی سرور فعال نیست.', 'bank-mellat-gateway' ) );
		}

		return new SoapClient(
			self::WSDL_URL,
			array(
				'encoding'           => 'UTF-8',
				'exceptions'         => true,
				'connection_timeout' => 15,
				'cache_wsdl'         => WSDL_CACHE_NONE,
				'stream_context'     => stream_context_create(
					array(
						'ssl' => array(
							'verify_peer'      => true,
							'verify_peer_name' => true,
							'allow_self_signed' => false,
						),
					)
				),
			)
		);
	}

	private function base_params() {
		return array(
			'terminalId'   => (int) $this->terminal_id,
			'userName'     => (string) $this->username,
			'userPassword' => (string) $this->password,
		);
	}

	/**
	 * Step 1: ask the bank for a RefId to redirect the payer to.
	 *
	 * @throws Exception
	 * @return array{res_code:string,ref_id:string}
	 */
	public function request_payment( $order_id, $amount_rial, $callback_url, $payer_id = 0, $additional_data = '' ) {
		$client = $this->get_soap_client();
		$now    = new DateTime( 'now', new DateTimeZone( 'Asia/Tehran' ) );

		$params = array_merge(
			$this->base_params(),
			array(
				'orderId'        => (int) $order_id,
				'amount'         => (int) $amount_rial,
				'localDate'      => $now->format( 'Ymd' ),
				'localTime'      => $now->format( 'His' ),
				'additionalData' => mb_substr( (string) $additional_data, 0, 100 ),
				'callBackUrl'    => $callback_url,
				'payerId'        => (int) $payer_id,
			)
		);

		$response = $client->bpPayRequest( $params );
		$result   = isset( $response->return ) ? (string) $response->return : '';
		$parts    = explode( ',', $result );

		return array(
			'res_code' => isset( $parts[0] ) ? trim( $parts[0] ) : '',
			'ref_id'   => isset( $parts[1] ) ? trim( $parts[1] ) : '',
		);
	}

	/**
	 * Step 2: verify the transaction server-to-server (must precede settlement).
	 *
	 * @throws Exception
	 * @return string result code, "0" means success
	 */
	public function verify( $order_id, $sale_order_id, $sale_reference_id ) {
		$client = $this->get_soap_client();
		$params = array_merge(
			$this->base_params(),
			array(
				'orderId'         => (int) $order_id,
				'saleOrderId'     => (int) $sale_order_id,
				'saleReferenceId' => (string) $sale_reference_id,
			)
		);

		$response = $client->bpVerifyRequest( $params );
		return isset( $response->return ) ? trim( (string) $response->return ) : '';
	}

	/**
	 * Step 3: settle (capture) a verified transaction.
	 *
	 * @throws Exception
	 * @return string result code, "0" (or "45" already settled) means success
	 */
	public function settle( $order_id, $sale_order_id, $sale_reference_id ) {
		$client = $this->get_soap_client();
		$params = array_merge(
			$this->base_params(),
			array(
				'orderId'         => (int) $order_id,
				'saleOrderId'     => (int) $sale_order_id,
				'saleReferenceId' => (string) $sale_reference_id,
			)
		);

		$response = $client->bpSettleRequest( $params );
		return isset( $response->return ) ? trim( (string) $response->return ) : '';
	}

	/**
	 * Roll back a verified-but-not-settled transaction.
	 *
	 * @throws Exception
	 * @return string result code
	 */
	public function reverse( $order_id, $sale_order_id, $sale_reference_id ) {
		$client = $this->get_soap_client();
		$params = array_merge(
			$this->base_params(),
			array(
				'orderId'         => (int) $order_id,
				'saleOrderId'     => (int) $sale_order_id,
				'saleReferenceId' => (string) $sale_reference_id,
			)
		);

		$response = $client->bpReversalRequest( $params );
		return isset( $response->return ) ? trim( (string) $response->return ) : '';
	}

	/**
	 * Human readable messages for the bank's numeric result codes.
	 */
	public static function get_error_message( $code ) {
		$messages = array(
			'11' => __( 'شماره کارت نامعتبر است.', 'bank-mellat-gateway' ),
			'12' => __( 'موجودی کافی نیست.', 'bank-mellat-gateway' ),
			'13' => __( 'رمز واردشده نامعتبر است.', 'bank-mellat-gateway' ),
			'14' => __( 'تعداد دفعات واردکردن رمز بیش از حد مجاز است.', 'bank-mellat-gateway' ),
			'15' => __( 'کارت نامعتبر است.', 'bank-mellat-gateway' ),
			'16' => __( 'دفعات برداشت وجه بیش از حد مجاز است.', 'bank-mellat-gateway' ),
			'17' => __( 'کاربر از انجام تراکنش منصرف شد.', 'bank-mellat-gateway' ),
			'18' => __( 'تاریخ انقضای کارت گذشته است.', 'bank-mellat-gateway' ),
			'19' => __( 'مبلغ برداشت وجه بیش از حد مجاز است.', 'bank-mellat-gateway' ),
			'21' => __( 'پذیرنده فروشگاهی نامعتبر است.', 'bank-mellat-gateway' ),
			'23' => __( 'خطای امنیتی رخ داده است.', 'bank-mellat-gateway' ),
			'24' => __( 'اطلاعات کاربری پذیرنده نامعتبر است.', 'bank-mellat-gateway' ),
			'25' => __( 'مبلغ نامعتبر است.', 'bank-mellat-gateway' ),
			'31' => __( 'پاسخ نامعتبر است.', 'bank-mellat-gateway' ),
			'32' => __( 'فرمت اطلاعات وارد شده صحیح نمی‌باشد.', 'bank-mellat-gateway' ),
			'33' => __( 'حساب نامعتبر است.', 'bank-mellat-gateway' ),
			'34' => __( 'خطای سیستمی در بانک رخ داده است.', 'bank-mellat-gateway' ),
			'35' => __( 'تاریخ نامعتبر است.', 'bank-mellat-gateway' ),
			'41' => __( 'شماره درخواست تکراری است.', 'bank-mellat-gateway' ),
			'42' => __( 'تراکنش سرویس Verify یافت نشد.', 'bank-mellat-gateway' ),
			'43' => __( 'قبلاً درخواست Verify داده شده است.', 'bank-mellat-gateway' ),
			'44' => __( 'درخواست Verify یافت نشد.', 'bank-mellat-gateway' ),
			'45' => __( 'تراکنش قبلاً Settle شده است.', 'bank-mellat-gateway' ),
			'46' => __( 'تراکنش Settle یافت نشد.', 'bank-mellat-gateway' ),
			'47' => __( 'تراکنش Settle یافت نشد.', 'bank-mellat-gateway' ),
			'48' => __( 'تراکنش Reverse شده است.', 'bank-mellat-gateway' ),
			'49' => __( 'برگشت تراکنش امکان‌پذیر نیست.', 'bank-mellat-gateway' ),
			'51' => __( 'تراکنش تکراری است.', 'bank-mellat-gateway' ),
			'54' => __( 'تراکنش مرجع یافت نشد.', 'bank-mellat-gateway' ),
			'55' => __( 'تراکنش نامعتبر است.', 'bank-mellat-gateway' ),
			'61' => __( 'خطا در واریز.', 'bank-mellat-gateway' ),
			'111' => __( 'صادرکننده کارت نامعتبر است.', 'bank-mellat-gateway' ),
			'112' => __( 'خطای سوییچ صادرکننده کارت.', 'bank-mellat-gateway' ),
			'113' => __( 'پاسخی از صادرکننده کارت دریافت نشد.', 'bank-mellat-gateway' ),
			'114' => __( 'دارنده کارت مجاز به انجام این تراکنش نیست.', 'bank-mellat-gateway' ),
		);

		if ( isset( $messages[ (string) $code ] ) ) {
			return $messages[ (string) $code ];
		}

		return sprintf( __( 'پرداخت با خطا مواجه شد (کد خطا: %s).', 'bank-mellat-gateway' ), $code );
	}
}
