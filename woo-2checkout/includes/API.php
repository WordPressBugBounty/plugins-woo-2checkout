<?php
/**
 * 2Checkout Payment Gateway Integration.
 * This file contains the API class for integrating with the Verifone (2Checkout) payment gateway.
 * It provides functionality for secure payment processing, webhook handling, and signature validation
 * within the WordPress environment.
 *
 * @package    \StorePress\TwoCheckoutPaymentGateway
 * @version    1.0.0
 * @link       https://verifone.cloud/docs/2checkout/API-Integration
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace StorePress\TwoCheckoutPaymentGateway;

defined( 'ABSPATH' ) || die( 'Keep Silent' );
/**
 * API Class for 2Checkout Payment Gateway Integration
 *
 * This class handles API communication with the Verifone (2Checkout) payment gateway
 * within the WordPress environment. It handles API communication with the Verifone (2Checkout) payment gateway.
 *  Provides comprehensive functionality for payment processing, including:
 *  - JWT token generation
 *  - Signature validation
 *  - IPN (Instant Payment Notification) handling
 *  - LCN (License Change Notification) processing
 *  - Buy link generation
 *  - Security hash verification
 *
 *  This class supports modern hashing algorithms (SHA256/SHA3-256) and maintains
 *  backwards compatibility with legacy MD5 hashing where required.
 *
 * @package    StorePress\TwoCheckoutPaymentGateway
 * @since      1.0.0
 * @author     StorePress
 * @link       https://verifone.cloud/docs/2checkout/API-Integration/01Start-using-the-2Checkout-API/2Checkout-API-general-information/Migration_guide_SHA2_SHA3
 */
class API {


	use Common;

	/**
	 * HTTP POST method constant
	 *
	 * Used for creating new resources via the API.
	 *
	 * @var string
	 */
	const POST = 'POST';
	/**
	 * HTTP GET method constant
	 *
	 * Used for retrieving resources via the API.
	 *
	 * @var string
	 */
	const GET = 'GET';
	/**
	 * HTTP PUT method constant
	 *
	 * Used for updating existing resources via the API.
	 *
	 * @var string
	 */
	const PUT = 'PUT';
	/**
	 * HTTP DELETE method constant
	 *
	 * Used for removing resources via the API.
	 *
	 * @var string
	 */
	const DELETE = 'DELETE';

	/**
	 * Merchant code for API authentication
	 *
	 * The unique identifier provided by 2Checkout/Verifone for merchant authentication.
	 *
	 * @var string
	 * @access protected
	 */
	protected string $merchant_code;

	/**
	 * Secret key for API authentication
	 *
	 * The secret key provided by 2Checkout/Verifone for secure API communication.
	 *
	 * @var string
	 * @access protected
	 */
	protected string $secret_key;

	/**
	 * Initialize the API client
	 *
	 * Creates a new instance of the API client with the provided authentication credentials.
	 * This class supports modern hashing algorithms (SHA256/SHA3-256) for enhanced security,
	 * deprecating the legacy MD5 hashing method.
	 *
	 * @param string $merchant_code The merchant code provided by Verifone/2Checkout.
	 * @param string $secret_key   The secret key for API authentication.
	 *
	 * @see https://verifone.cloud/docs/2checkout/API-Integration/Webhooks/IPN_and_LCN_URL_settings
	 */
	public function __construct( string $merchant_code, string $secret_key ) {
		$this->merchant_code = $merchant_code;
		$this->secret_key    = $secret_key;
	}

	/**
	 * Return singleton instance of Class.
	 * The instance will be created if it does not exist yet.
	 *
	 * @param string $merchant_code The merchant code provided by Verifone/2Checkout.
	 * @param string $secret_key The secret key for API authentication.
	 *
	 * @return self The main instance.
	 * @since 1.0.0
	 */
	public static function instance( string $merchant_code, string $secret_key ): self {
		static $instance = null;

		if ( is_null( $instance ) ) {
			$instance = new self( $merchant_code, $secret_key );
		}

		return $instance;
	}

	/**
	 * Get the hashing algorithm based on signature presence
	 *
	 * Determines which hashing algorithm to use based on the presence of
	 * different signature types in the provided data.
	 *
	 * @param array<string, string> $data The data array containing signature information or false.
	 *
	 * @return string The hashing algorithm to use ('sha256', 'sha3-256', or 'md5')
	 * @since 1.0.0
	 */
	public function get_hashing_algorithm( array $data = array() ): string {

		if ( ! empty( $data['SIGNATURE_SHA2_256'] ) ) {
			return 'sha256';
		}

		if ( ! empty( $data['SIGNATURE_SHA3_256'] ) ) {
			return 'sha3-256';
		}

		return 'md5';
	}

	/**
	 * Get the returned hash from the data
	 *
	 * Retrieves the appropriate hash value from the data array based on
	 * available signature types.
	 *
	 * @param array<string, string> $data The data array containing hash information or false.
	 *
	 * @return string The hash value if found, null otherwise
	 * @since 1.0.0
	 */
	public function get_returned_hash( array $data = array() ): string {

		if ( ! empty( $data['SIGNATURE_SHA2_256'] ) ) {
			return $data['SIGNATURE_SHA2_256'];
		}

		if ( ! empty( $data['SIGNATURE_SHA3_256'] ) ) {
			return $data['SIGNATURE_SHA3_256'];
		}

		return $data['HASH'];
	}

	/**
	 *  Creates a JWT token using HS512 algorithm for secure API communication.
	 *  The token includes header, payload, and signature components.
	 *
	 *  WooCommerce "JsonWebToken" Class can only generate token using HS256 algorithm.
	 *
	 * @param string $merchant_id Merchant ID.
	 * @param int    $iat issued at, must be current timestamp since the UNIX epoch.
	 * @param int    $exp expiration time, must be in UNIX timestamp format from future.
	 * @param string $buy_link_secret_word Buy-link Secret Word.
	 *
	 * @return string
	 * @see: https://verifone.cloud/docs/2checkout/Documentation/07Commerce/2Checkout-ConvertPlus/How-to-generate-a-JSON-Web-Token-JWT
	 */
	public function generate_jwt_token( string $merchant_id, int $iat, int $exp, string $buy_link_secret_word ): string {

		$header    = $this->encode(
			wp_json_encode(
				array(
					'alg' => 'HS512',
					'typ' => 'JWT',
				)
			)
		);
		$payload   = $this->encode(
			wp_json_encode(
				array(
					'sub' => $merchant_id,
					'iat' => $iat,
					'exp' => $exp,
				)
			)
		);
		$signature = $this->encode( hash_hmac( 'sha512', "$header.$payload", $buy_link_secret_word, true ) );

		return implode(
			'.',
			array(
				$header,
				$payload,
				$signature,
			)
		);
	}

	/**
	 * Encode data for JWT token generation
	 *
	 * Encodes the provided data using base64 and replaces specific characters
	 * to make it URL safe.
	 *
	 * @param string $data The data to encode.
	 *
	 * @return string The encoded data.
	 * @access private
	 * @since 1.0.0
	 */
	private function encode( string $data ): string {

		return str_replace(
			array( '+', '/', '=' ),
			array( '-', '_', '' ),
			base64_encode( $data ) // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		);
	}

	/**
	 * Generate signature for ConvertPlus buy link parameters
	 *
	 * Creates a signature for ConvertPlus buy link parameters using SHA256.
	 * Only specific parameters are included in signature generation.
	 *
	 * @param array<string, mixed> $params               Array of buy link parameters.
	 * @param string               $buy_link_secret_word Secret word for signature generation.
	 *
	 * @return string Generated signature hash
	 *
	 * @link https://verifone.cloud/docs/2checkout/Documentation/07Commerce/2Checkout-ConvertPlus/ConvertPlus_URL_parameters
	 * @link https://verifone.cloud/docs/2checkout/Documentation/07Commerce/2Checkout-ConvertPlus/ConvertPlus_Buy-Links_Signature
	 */
	public function convertplus_buy_link_signature( array $params, string $buy_link_secret_word ): string {

		// ConvertPlus parameters that require a signature.
		$signature_params = array(
			'return-url',
			'return-type',
			// 'back-url',
			'expiration',
			'order-ext-ref',
			'item-ext-ref',
			'customer-ref',
			'customer-ref',
			'customer-ext-ref',
			// 'lock',
			'currency',
			'prod',
			'price',
			'qty',
			// 'tangible',
			'type',
			'opt',
			'description',
			'recurrence',
			'duration',
			'renewal-price',
		);

		$filtered_params = array_filter(
			$params,
			function ( $key ) use ( $signature_params ) {
				return in_array( $key, $signature_params, true );
			},
			ARRAY_FILTER_USE_KEY
		);

		$serialize_string = $this->convertplus_serialize( $filtered_params );

		// Should use Algorithm sha256 here.
		return hash_hmac( 'sha256', $serialize_string, $buy_link_secret_word );
	}

	/**
	 * Generate complete ConvertPlus buy link URL.
	 *
	 * Creates a complete checkout URL with all necessary parameters and signature.
	 * Automatically sets expiration time if not provided.
	 *
	 * @param array<string, mixed> $params               Parameters for the buy link.
	 * @param string               $merchant_code        Merchant identifier.
	 * @param string               $buy_link_secret_word Secret word for signature.
	 *
	 * @return string Complete checkout URL
	 */
	public function convertplus_buy_link( array $params, string $merchant_code, string $buy_link_secret_word ): string {

		$pre_data = array( 'merchant' => $merchant_code );
		$data     = array_merge( $pre_data, $params );

		if ( ! isset( $data['expiration'] ) ) {
			$data['expiration'] = absint( time() + ( HOUR_IN_SECONDS * 5 ) ); // 5 hours; 60 mins; 60 secs
		}

		$data['signature'] = $this->convertplus_buy_link_signature( $data, $buy_link_secret_word );

		return 'https://secure.2checkout.com/checkout/buy/?' . http_build_query( $data );
	}

	/**
	 * Get signature from 2Checkout API
	 *
	 * Retrieves a signature from the 2Checkout API using JWT authentication.
	 * Handles error responses and notifications.
	 *
	 * @param array<string, mixed> $params               Parameters to be signed.
	 * @param string               $buy_link_secret_word Secret word for JWT generation.
	 *
	 * @return string|false Signature if successful, false on failure
	 *
	 * @link https://knowledgecenter.2checkout.com/Documentation/07Commerce/2Checkout-ConvertPlus/How-to-use-2Checkout-Signature-Generation-API-Endpoint#PHP_23
	 */
	public function get_signature( array $params, string $buy_link_secret_word ) {

		$jwt_token = $this->generate_jwt_token( $this->merchant_code, time(), time() + 3600, $buy_link_secret_word );

		$response = wp_remote_post(
			'https://secure.2checkout.com/checkout/api/encrypt/generate/signature',
			array(
				'headers' => array(
					'content-type'   => 'application/json',
					'cache-control'  => 'no-cache',
					'merchant-token' => $jwt_token,
				),
				'body'    => wp_json_encode( $params ),
			)
		);

		$response_body = wp_remote_retrieve_body( $response );
		$response_data = json_decode( $response_body );

		if ( isset( $response_data->signature ) ) {
			return $response_data->signature;
		}

		if ( isset( $response_data->error_code ) ) {
			wc_add_notice( $response_data->message, 'error' );

			return false;
		}

		wc_add_notice( '2Checkout: Unable to get signature response from signature generation API.', 'error' );

		return false;
	}

	/**
	 * Serialize parameters for signature generation
	 *
	 * Creates a serialized string from parameters for signature generation.
	 * Sorts parameters by key and prepends length to values.
	 *
	 * @param array<string, mixed> $params Parameters to serialize.
	 *
	 * @return string Serialized parameter string
	 */
	public function convertplus_serialize( array $params ): string {

		ksort( $params );

		$map_data = array_map(
			function ( $value ) {
				$str_value = (string) $value;
				return strlen( $str_value ) . $str_value;
			},
			$params
		);

		return implode( '', $map_data );
	}

	/**
	 * Validate IPN/LCN hash signature
	 *
	 * Verifies the authenticity of IPN (Instant Payment Notification) or
	 * LCN (License Change Notification) requests.
	 *
	 * @param array<string, mixed> $post_data  POST data from the notification.
	 * @param string               $secret_key Secret key for hash verification.
	 *
	 * @return bool True if hash is valid, false otherwise
	 *
	 * @link https://verifone.cloud/docs/2checkout/API-Integration/Webhooks/06Instant_Payment_Notification_%2528IPN%2529/Calculate-the-IPN-HASH-signature
	 */
	public function is_valid_ipn_lcn_hash( array $post_data, string $secret_key ): bool {

		$ipn_hash = $this->get_returned_hash( $post_data );

		$generate_string = $this->generate_base_string_for_hash( $post_data );

		$get_algo    = $this->get_hashing_algorithm( $post_data );
		$server_hash = hash_hmac( $get_algo, $generate_string, $secret_key );

		return hash_equals( $server_hash, $ipn_hash );
	}

	/**
	 * Generate base string for hash calculation
	 *
	 * Creates a base string from parameters for hash calculation.
	 * Handles nested arrays and excludes hash-related parameters.
	 *
	 * @param array<string, mixed> $params Parameters to process.
	 *
	 * @return string Generated base string
	 */
	public function generate_base_string_for_hash( array $params ): string {

		$string = '';

		unset( $params['HASH'], $params['SIGNATURE_SHA2_256'], $params['SIGNATURE_SHA3_256'] );

		foreach ( $params as $value ) {

			if ( is_array( $value ) ) {
				$string .= $this->generate_base_string_for_hash( $value );
			} else {
				$string .= strlen( $value ) . $value;
			}
		}

		return $string;
	}

	/**
	 * Get allowed HTML tags for receipt response
	 *
	 * Defines the allowed HTML tags and their attributes for use in
	 * receipt response sanitization.
	 *
	 * @return array<string, mixed>
	 * @since 1.0.0
	 */
	public function kses_receipt_response_allowed_html(): array {
		return array(
			'epayment' => array(),
			'sig'      => array(
				'algo' => array(),
				'date' => array(),
			),
		);
	}

	/**
	 * Generate IPN receipt response
	 *
	 * Creates a receipt response for IPN notifications in XML format.
	 * Supports multiple hash algorithms (MD5, SHA256, SHA3-256).
	 *
	 * @param array<string, mixed> $post_data   POST data from the IPN.
	 * @param string|bool          $secret_key Optional secret key, uses instance key if false.
	 *
	 * @return string|false XML receipt response or false on failure
	 *
	 * @link https://verifone.cloud/docs/2checkout/API-Integration/Webhooks/08License_Change_Notification_%2528LCN%2529/LCN-read-receipt-response-for-2Checkout
	 * @link https://verifone.cloud/docs/2checkout/API-Integration/Webhooks/IPN_and_LCN_URL_settings
	 * @link https://verifone.cloud/docs/2checkout/API-Integration/Webhooks/06Instant_Payment_Notification_%2528IPN%2529/Calculate-the-IPN-HASH-signature
	 */
	public function ipn_receipt_response( array $post_data, $secret_key = false ) {
		// <EPAYMENT>DATE|HASH</EPAYMENT>
		// <sig algo="sha256" date="DATE">HASH</sig>
		// <sig algo="sha3-256" date="DATE">HASH</sig>

		if ( empty( $post_data['IPN_PID'] ) || empty( $post_data['IPN_PNAME'] ) ) {
			return false;
		}

		// Response issuing date (server time) in the YmdHis format (ex: 20081117145935).

		$receipt_date = gmdate( 'YmdHis' );

		$ipn_receipt = array(
			$post_data['IPN_PID'][0],
			$post_data['IPN_PNAME'][0],
			$post_data['IPN_DATE'],
			// IPN date in the YmdHis format (ex: 20081117145935).
			$receipt_date,
		);

		// CUSTOM IPN AND LCN CONFIGURATIONS.
		if ( ! $secret_key ) {
			$secret_key = $this->secret_key;
		}

		$receipt_return = implode(
			'',
			array_map(
				function ( $value ) {
					return strlen( stripslashes( $value ) ) . stripslashes( $value );
				},
				$ipn_receipt
			)
		);

		$get_algo     = $this->get_hashing_algorithm( $post_data );
		$receipt_hash = hash_hmac( $get_algo, $receipt_return, $secret_key );

		if ( $this->is_valid_ipn_lcn_hash( $post_data, $secret_key ) ) {

			if ( 'md5' === $get_algo ) {
				return sprintf( '<EPAYMENT>%s|%s</EPAYMENT>', $receipt_date, $receipt_hash );
			}

			// sha256 and sha3-256.
			return sprintf( '<sig algo="%s" date="%s">%s</sig>', $get_algo, $receipt_date, $receipt_hash );

		} else {
			return false;
		}
	}

	/**
	 * Generate return URL signature
	 *
	 * Creates a signature for return URL validation using SHA256.
	 *
	 * @param array<string, mixed> $params               Return URL parameters.
	 * @param string               $buy_link_secret_word Secret word for signature.
	 *
	 * @return string|false Generated signature or false if parameters invalid.
	 *
	 * @link https://verifone.cloud/docs/2checkout/Documentation/07Commerce/2Checkout-ConvertPlus/Signature_validation_for_return_URL_via_ConvertPlus
	 * @link https://verifone.cloud/docs/2checkout/Documentation/07Commerce/InLine-Checkout-Guide/Signature_validation_for_return_URL_via_InLine_checkout
	 */
	public function generate_return_signature( array $params, string $buy_link_secret_word ) {

		if ( empty( $params ) || empty( $params['signature'] ) ) {
			return false;
		}

		// Remove signature key from params list.
		unset( $params['signature'], $params['wc-api'] );
		$serialize_string = $this->convertplus_serialize( $params );

		// Should use Algorithm sha256 here.
		return hash_hmac( 'sha256', $serialize_string, $buy_link_secret_word );
	}

	/**
	 * Validate return URL signature
	 *
	 * Verifies the authenticity of return URL signatures.
	 *
	 * @param array<string, mixed> $params               Return URL parameters including signature.
	 * @param string               $buy_link_secret_word Secret word for signature validation.
	 *
	 * @return bool True if signature is valid, false otherwise.
	 *
	 * @link https://verifone.cloud/docs/2checkout/Documentation/07Commerce/InLine-Checkout-Guide/Signature_validation_for_return_URL_via_InLine_checkout
	 */
	public function is_valid_return_signature( array $params, string $buy_link_secret_word ): bool {

		if ( empty( $params ) || empty( $params['signature'] ) ) {
			return false;
		}

		$return_signature = sanitize_text_field( $params['signature'] );

		// Remove signature key from params list.
		unset( $params['signature'], $params['wc-api'] );
		$serialize_string = $this->convertplus_serialize( $params );
		// Should use Algorithm sha256 here.
		$generated_signature = hash_hmac( 'sha256', $serialize_string, $buy_link_secret_word );

		return hash_equals( $generated_signature, $return_signature );
	}
}
