<?php
/**
 * USPS API Integration Class
 *
 * @package Special_Rate_Shipping
 * @subpackage USPS_API
 * @since 2.0.0
 * 
 * Handles USPS API integration for shipping labels following USPS best practices:
 * - REST API endpoints (preferred over deprecated XML)
 * - Proper authentication with API keys
 * - Rate limiting and error handling
 * - Sandbox/Production environment support
 * - Label generation and tracking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class USPS_API {
	
	/**
	 * USPS API Base URLs
	 */
	const SANDBOX_BASE_URL = 'https://api-cat.usps.com';
	const PRODUCTION_BASE_URL = 'https://api.usps.com';
	
	/**
	 * API endpoints
	 */
	const ENDPOINT_TOKEN = '/oauth2/v3/token';
	const ENDPOINT_LABELS = '/logistics/v3/label';
	const ENDPOINT_RATES = '/logistics/v3/rates';
	const ENDPOINT_TRACKING = '/logistics/v3/tracking';
	
	/**
	 * The API key
	 * @var string
	 */
	private $api_key;
	
	/**
	 * The API secret
	 * @var string
	 */
	private $api_secret;
	
	/**
	 * Environment (sandbox or production)
	 * @var string
	 */
	private $environment;
	
	/**
	 * Access token for API requests
	 * @var string
	 */
	private $access_token;
	
	/**
	 * Token expiration time
	 * @var int
	 */
	private $token_expires;
	
	/**
	 * Debug mode flag
	 * @var bool
	 */
	private $debug;
	
	/**
	 * Constructor
	 *
	 * @param string $api_key USPS API Key
	 * @param string $api_secret USPS API Secret
	 * @param string $environment Environment (sandbox|production)
	 * @param bool $debug Enable debug mode
	 */
	public function __construct( $api_key = '', $api_secret = '', $environment = 'sandbox', $debug = false ) {
		$this->api_key = $api_key;
		$this->api_secret = $api_secret;
		$this->environment = $environment;
		$this->debug = $debug;
		
		// Try to load stored token
		$this->load_stored_token();
	}
	
	/**
	 * Get the base URL for API requests
	 *
	 * @return string
	 */
	private function get_base_url() {
		return $this->environment === 'production' ? self::PRODUCTION_BASE_URL : self::SANDBOX_BASE_URL;
	}
	
	/**
	 * Get or refresh access token
	 *
	 * @return string|WP_Error
	 */
	public function get_access_token() {
		// Check if we have a valid token
		if ( $this->access_token && $this->token_expires && time() < $this->token_expires ) {
			return $this->access_token;
		}
		
		// Request new token
		$token_response = $this->request_access_token();
		
		if ( is_wp_error( $token_response ) ) {
			return $token_response;
		}
		
		$this->access_token = $token_response['access_token'];
		$this->token_expires = time() + $token_response['expires_in'] - 60; // 60 second buffer
		
		// Store token for reuse
		$this->store_token();
		
		return $this->access_token;
	}
	
	/**
	 * Request access token from USPS
	 *
	 * @return array|WP_Error
	 */
	private function request_access_token() {
		$url = $this->get_base_url() . self::ENDPOINT_TOKEN;
		
		$headers = array(
			'Content-Type' => 'application/x-www-form-urlencoded',
			'Authorization' => 'Basic ' . base64_encode( $this->api_key . ':' . $this->api_secret )
		);
		
		$body = array(
			'grant_type' => 'client_credentials'
		);
		
		$response = wp_remote_post( $url, array(
			'headers' => $headers,
			'body' => http_build_query( $body ),
			'timeout' => 30
		) );
		
		if ( is_wp_error( $response ) ) {
			$this->log_error( 'Token request failed', $response->get_error_message() );
			return $response;
		}
		
		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		
		if ( $response_code !== 200 ) {
			$error_message = sprintf( 'Token request failed with status %d: %s', $response_code, $response_body );
			$this->log_error( 'Token request failed', $error_message );
			return new WP_Error( 'usps_token_error', $error_message );
		}
		
		$data = json_decode( $response_body, true );
		
		if ( ! isset( $data['access_token'] ) ) {
			$error_message = 'Invalid token response: ' . $response_body;
			$this->log_error( 'Token response invalid', $error_message );
			return new WP_Error( 'usps_token_invalid', $error_message );
		}
		
		$this->log_debug( 'Access token obtained successfully' );
		
		return $data;
	}
	
	/**
	 * Create shipping label
	 *
	 * @param array $label_data Label creation data
	 * @return array|WP_Error
	 */
	public function create_label( $label_data ) {
		$token = $this->get_access_token();
		
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		
		$url = $this->get_base_url() . self::ENDPOINT_LABELS;
		
		$headers = array(
			'Content-Type' => 'application/json',
			'Authorization' => 'Bearer ' . $token
		);
		
		// Validate and format label data
		$formatted_data = $this->format_label_data( $label_data );
		
		if ( is_wp_error( $formatted_data ) ) {
			return $formatted_data;
		}
		
		$response = wp_remote_post( $url, array(
			'headers' => $headers,
			'body' => json_encode( $formatted_data ),
			'timeout' => 60
		) );
		
		if ( is_wp_error( $response ) ) {
			$this->log_error( 'Label creation request failed', $response->get_error_message() );
			return $response;
		}
		
		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		
		if ( $response_code !== 200 && $response_code !== 201 ) {
			$error_message = sprintf( 'Label creation failed with status %d: %s', $response_code, $response_body );
			$this->log_error( 'Label creation failed', $error_message );
			return new WP_Error( 'usps_label_error', $error_message );
		}
		
		$data = json_decode( $response_body, true );
		
		if ( ! $data ) {
			$error_message = 'Invalid label response: ' . $response_body;
			$this->log_error( 'Label response invalid', $error_message );
			return new WP_Error( 'usps_label_invalid', $error_message );
		}
		
		$this->log_debug( 'Label created successfully', $data );
		
		return $data;
	}
	
	/**
	 * Get shipping rates
	 *
	 * @param array $rate_data Rate request data
	 * @return array|WP_Error
	 */
	public function get_rates( $rate_data ) {
		$token = $this->get_access_token();
		
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		
		$url = $this->get_base_url() . self::ENDPOINT_RATES;
		
		$headers = array(
			'Content-Type' => 'application/json',
			'Authorization' => 'Bearer ' . $token
		);
		
		// Format rate request data
		$formatted_data = $this->format_rate_data( $rate_data );
		
		if ( is_wp_error( $formatted_data ) ) {
			return $formatted_data;
		}
		
		$response = wp_remote_post( $url, array(
			'headers' => $headers,
			'body' => json_encode( $formatted_data ),
			'timeout' => 30
		) );
		
		if ( is_wp_error( $response ) ) {
			$this->log_error( 'Rate request failed', $response->get_error_message() );
			return $response;
		}
		
		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		
		if ( $response_code !== 200 ) {
			$error_message = sprintf( 'Rate request failed with status %d: %s', $response_code, $response_body );
			$this->log_error( 'Rate request failed', $error_message );
			return new WP_Error( 'usps_rate_error', $error_message );
		}
		
		$data = json_decode( $response_body, true );
		
		if ( ! $data ) {
			$error_message = 'Invalid rate response: ' . $response_body;
			$this->log_error( 'Rate response invalid', $error_message );
			return new WP_Error( 'usps_rate_invalid', $error_message );
		}
		
		$this->log_debug( 'Rates retrieved successfully' );
		
		return $data;
	}
	
	/**
	 * Track package
	 *
	 * @param string $tracking_number Tracking number
	 * @return array|WP_Error
	 */
	public function track_package( $tracking_number ) {
		$token = $this->get_access_token();
		
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		
		$url = $this->get_base_url() . self::ENDPOINT_TRACKING . '/' . urlencode( $tracking_number );
		
		$headers = array(
			'Authorization' => 'Bearer ' . $token
		);
		
		$response = wp_remote_get( $url, array(
			'headers' => $headers,
			'timeout' => 30
		) );
		
		if ( is_wp_error( $response ) ) {
			$this->log_error( 'Tracking request failed', $response->get_error_message() );
			return $response;
		}
		
		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		
		if ( $response_code !== 200 ) {
			$error_message = sprintf( 'Tracking request failed with status %d: %s', $response_code, $response_body );
			$this->log_error( 'Tracking request failed', $error_message );
			return new WP_Error( 'usps_tracking_error', $error_message );
		}
		
		$data = json_decode( $response_body, true );
		
		if ( ! $data ) {
			$error_message = 'Invalid tracking response: ' . $response_body;
			$this->log_error( 'Tracking response invalid', $error_message );
			return new WP_Error( 'usps_tracking_invalid', $error_message );
		}
		
		$this->log_debug( 'Tracking retrieved successfully' );
		
		return $data;
	}
	
	/**
	 * Format label data according to USPS API requirements
	 *
	 * @param array $data Raw label data
	 * @return array|WP_Error
	 */
	private function format_label_data( $data ) {
		// Required fields validation
		$required_fields = array( 'from_address', 'to_address', 'package' );
		
		foreach ( $required_fields as $field ) {
			if ( empty( $data[ $field ] ) ) {
				return new WP_Error( 'usps_missing_field', sprintf( 'Missing required field: %s', $field ) );
			}
		}
		
		// Format according to USPS API specification
		$formatted = array(
			'imageInfo' => array(
				'imageType' => isset( $data['label_format'] ) ? $data['label_format'] : 'PDF',
				'labelType' => isset( $data['label_type'] ) ? $data['label_type'] : 'SHIPPING_LABEL'
			),
			'fromAddress' => $this->format_address( $data['from_address'] ),
			'toAddress' => $this->format_address( $data['to_address'] ),
			'packageDescription' => $this->format_package( $data['package'] ),
			'processingParameters' => array(
				'deliveryOption' => isset( $data['service_type'] ) ? $data['service_type'] : 'GROUND_ADVANTAGE',
				'mailingDate' => date( 'Y-m-d' )
			)
		);
		
		// Add optional fields
		if ( isset( $data['insurance'] ) ) {
			$formatted['processingParameters']['insurance'] = $data['insurance'];
		}
		
		if ( isset( $data['tracking'] ) && $data['tracking'] ) {
			$formatted['processingParameters']['trackingOption'] = 'TRACKING_INCLUDED';
		}
		
		if ( isset( $data['signature'] ) && $data['signature'] ) {
			$formatted['processingParameters']['signatureOption'] = 'ADULT_SIGNATURE';
		}
		
		return $formatted;
	}
	
	/**
	 * Format rate data according to USPS API requirements
	 *
	 * @param array $data Raw rate data
	 * @return array|WP_Error
	 */
	private function format_rate_data( $data ) {
		// Required fields validation
		$required_fields = array( 'from_zip', 'to_zip', 'package' );
		
		foreach ( $required_fields as $field ) {
			if ( empty( $data[ $field ] ) ) {
				return new WP_Error( 'usps_missing_field', sprintf( 'Missing required field: %s', $field ) );
			}
		}
		
		$formatted = array(
			'originZIPCode' => $data['from_zip'],
			'destinationZIPCode' => $data['to_zip'],
			'weight' => isset( $data['package']['weight'] ) ? $data['package']['weight'] : 1,
			'length' => isset( $data['package']['length'] ) ? $data['package']['length'] : 12,
			'width' => isset( $data['package']['width'] ) ? $data['package']['width'] : 8,
			'height' => isset( $data['package']['height'] ) ? $data['package']['height'] : 6,
			'mailClass' => isset( $data['service_type'] ) ? $data['service_type'] : 'GROUND_ADVANTAGE',
			'priceType' => 'RETAIL'
		);
		
		return $formatted;
	}
	
	/**
	 * Format address for USPS API
	 *
	 * @param array $address Address data
	 * @return array
	 */
	private function format_address( $address ) {
		return array(
			'firstName' => isset( $address['first_name'] ) ? $address['first_name'] : '',
			'lastName' => isset( $address['last_name'] ) ? $address['last_name'] : '',
			'firm' => isset( $address['company'] ) ? $address['company'] : '',
			'address1' => isset( $address['address_1'] ) ? $address['address_1'] : '',
			'address2' => isset( $address['address_2'] ) ? $address['address_2'] : '',
			'city' => isset( $address['city'] ) ? $address['city'] : '',
			'state' => isset( $address['state'] ) ? $address['state'] : '',
			'ZIPCode' => isset( $address['postcode'] ) ? $address['postcode'] : '',
			'ZIPPlus4' => isset( $address['postcode_plus4'] ) ? $address['postcode_plus4'] : ''
		);
	}
	
	/**
	 * Format package information for USPS API
	 *
	 * @param array $package Package data
	 * @return array
	 */
	private function format_package( $package ) {
		return array(
			'weight' => isset( $package['weight'] ) ? floatval( $package['weight'] ) : 1.0,
			'length' => isset( $package['length'] ) ? floatval( $package['length'] ) : 12.0,
			'width' => isset( $package['width'] ) ? floatval( $package['width'] ) : 8.0,
			'height' => isset( $package['height'] ) ? floatval( $package['height'] ) : 6.0,
			'girth' => isset( $package['girth'] ) ? floatval( $package['girth'] ) : 0.0
		);
	}
	
	/**
	 * Store access token in WordPress options
	 */
	private function store_token() {
		update_option( 'usps_api_token', array(
			'access_token' => $this->access_token,
			'expires' => $this->token_expires,
			'environment' => $this->environment
		) );
	}
	
	/**
	 * Load stored access token from WordPress options
	 */
	private function load_stored_token() {
		$stored = get_option( 'usps_api_token' );
		
		if ( $stored && 
			 isset( $stored['access_token'] ) && 
			 isset( $stored['expires'] ) && 
			 isset( $stored['environment'] ) &&
			 $stored['environment'] === $this->environment ) {
			
			$this->access_token = $stored['access_token'];
			$this->token_expires = $stored['expires'];
		}
	}
	
	/**
	 * Log debug information
	 *
	 * @param string $message Debug message
	 * @param mixed $data Optional data to log
	 */
	private function log_debug( $message, $data = null ) {
		if ( ! $this->debug ) {
			return;
		}
		
		$log_message = 'USPS API Debug: ' . $message;
		if ( $data ) {
			$log_message .= ' | Data: ' . json_encode( $data );
		}
		
		error_log( $log_message );
	}
	
	/**
	 * Log error information
	 *
	 * @param string $message Error message
	 * @param mixed $data Optional error data
	 */
	private function log_error( $message, $data = null ) {
		$log_message = 'USPS API Error: ' . $message;
		if ( $data ) {
			$log_message .= ' | Error: ' . $data;
		}
		
		error_log( $log_message );
	}
	
	/**
	 * Test API connection
	 *
	 * @return bool|WP_Error
	 */
	public function test_connection() {
		$token = $this->get_access_token();
		
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		
		$this->log_debug( 'API connection test successful' );
		return true;
	}
}