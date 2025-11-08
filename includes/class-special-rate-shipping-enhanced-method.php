<?php
/**
 * Enhanced Special Rate Shipping Method with Pouch concept
 * 
 * @package Special_Rate_Shipping
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check if WC_Shipping_Method exists before extending
if ( ! class_exists( 'WC_Shipping_Method' ) ) {
	return;
}

/**
 * Enhanced Shipping Method with Pouch Management
 */
class Special_Rate_Shipping_Enhanced_Method extends WC_Shipping_Method {

	/**
	 * Package types with enhanced configuration
	 *
	 * @var array
	 */
	private $package_types = array(
		'small' => array(
			'name' => 'Small Box',
			'default_rate' => '6.35',
			'max_weight' => 2.0,
			'max_volume' => 0.5,
			'color' => '#28a745'
		),
		'medium' => array(
			'name' => 'Medium Box',
			'default_rate' => '9.35',
			'max_weight' => 5.0,
			'max_volume' => 1.5,
			'color' => '#ffc107'
		),
		'big' => array(
			'name' => 'Big Box',
			'default_rate' => '13.35',
			'max_weight' => 10.0,
			'max_volume' => 5.0,
			'color' => '#dc3545'
		)
	);

	/**
	 * Constructor
	 *
	 * @param int $instance_id Instance ID
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id = 'special_rate_shipping_enhanced_method';
		$this->instance_id = absint( $instance_id );
		$this->method_title = __( 'Special Rate Shipping (Enhanced)', 'special-rate-shipping' );
		$this->method_description = __( 'Advanced shipping method with intelligent packaging, QR code generation, and pouch management.', 'special-rate-shipping' );
		$this->title = $this->get_option( 'title', $this->method_title );
		$this->supports = array( 'shipping-zones', 'instance-settings' );

		$this->init();
	}

	/**
	 * Initialize the shipping method
	 *
	 * @return void
	 */
	public function init() {
		$this->init_form_fields();
		$this->init_settings();

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'wp_ajax_generate_shipping_qr', array( $this, 'generate_shipping_qr_ajax' ) );
	}

	/**
	 * Initialize form fields
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$fields = array(
			'enabled' => array(
				'title' => __( 'Enable/Disable', 'special-rate-shipping' ),
				'type' => 'checkbox',
				'label' => __( 'Enable Enhanced Special Rate Shipping', 'special-rate-shipping' ),
				'default' => 'yes'
			),
			'title' => array(
				'title' => __( 'Method Title', 'special-rate-shipping' ),
				'type' => 'text',
				'description' => __( 'Title shown to customers during checkout.', 'special-rate-shipping' ),
				'default' => __( 'Special Rate (Enhanced)', 'special-rate-shipping' ),
				'desc_tip' => true
			),
			'enable_qr_codes' => array(
				'title' => __( 'Enable QR Codes', 'special-rate-shipping' ),
				'type' => 'checkbox',
				'label' => __( 'Generate QR codes for shipping labels', 'special-rate-shipping' ),
				'default' => 'yes',
				'description' => __( 'Generate QR codes containing shipping and tracking information.', 'special-rate-shipping' )
			),
			'enable_pouch_management' => array(
				'title' => __( 'Enable Pouch Management', 'special-rate-shipping' ),
				'type' => 'checkbox',
				'label' => __( 'Enable advanced pouch container management', 'special-rate-shipping' ),
				'default' => 'yes',
				'description' => __( 'Use intelligent pouch system for optimal package organization.', 'special-rate-shipping' )
			),
			'default_rate' => array(
				'title' => __( 'Default Rate', 'special-rate-shipping' ),
				'type' => 'price',
				'description' => __( 'Fallback rate when no specific rules apply.', 'special-rate-shipping' ),
				'default' => '6.35',
				'desc_tip' => true
			),
			'packages_section' => array(
				'title' => __( 'Package Configuration', 'special-rate-shipping' ),
				'type' => 'title',
				'description' => __( 'Configure package types and their properties.', 'special-rate-shipping' )
			)
		);

		// Add enhanced package configuration
		foreach ( $this->package_types as $package_id => $package_data ) {
			$fields["{$package_id}_title"] = array(
				'title' => $package_data['name'],
				'type' => 'title',
				'description' => sprintf( __( 'Configuration for %s packages', 'special-rate-shipping' ), $package_data['name'] )
			);

			$fields["{$package_id}_rate"] = array(
				'title' => __( 'Rate', 'special-rate-shipping' ),
				'type' => 'price',
				'default' => $package_data['default_rate'],
				'desc_tip' => true
			);

			$fields["{$package_id}_max_weight"] = array(
				'title' => __( 'Max Weight (kg)', 'special-rate-shipping' ),
				'type' => 'number',
				'default' => $package_data['max_weight'],
				'custom_attributes' => array( 'step' => '0.1', 'min' => '0' ),
				'desc_tip' => true
			);

			$fields["{$package_id}_max_items"] = array(
				'title' => __( 'Max Items', 'special-rate-shipping' ),
				'type' => 'number',
				'default' => $package_id === 'small' ? '3' : ( $package_id === 'medium' ? '6' : '12' ),
				'custom_attributes' => array( 'min' => '1' ),
				'desc_tip' => true
			);
		}

		$this->form_fields = $fields;
	}

	/**
	 * Calculate shipping for the given package
	 *
	 * @param array $package Package data
	 * @return void
	 */
	public function calculate_shipping( $package = array() ) {
		try {
			// Create a Pouch for this shipment
			$pouch = $this->create_shipping_pouch( $package );
			
			$cost = $this->calculate_pouch_cost( $pouch );

			$rate = array(
				'id' => $this->get_rate_id(),
				'label' => $this->title,
				'cost' => $cost,
				'calc_tax' => 'per_order',
				'meta_data' => array(
					'pouch_id' => $pouch['id'],
					'packages_used' => $pouch['packages'],
					'qr_enabled' => $this->get_option( 'enable_qr_codes' ) === 'yes'
				)
			);

			$this->add_rate( $rate );

		} catch ( Exception $e ) {
			error_log( 'Enhanced Shipping Method Error: ' . $e->getMessage() );
			
			// Fallback rate
			$this->add_rate( array(
				'id' => $this->get_rate_id(),
				'label' => $this->title,
				'cost' => $this->get_option( 'default_rate', '6.35' ),
				'calc_tax' => 'per_order'
			) );
		}
	}

	/**
	 * Create a shipping pouch for the given package
	 *
	 * @param array $package WooCommerce package data
	 * @return array Pouch data structure
	 */
	private function create_shipping_pouch( $package ) {
		$pouch = array(
			'id' => 'pouch_' . uniqid(),
			'created' => current_time( 'mysql' ),
			'packages' => array(),
			'total_weight' => 0,
			'total_items' => 0,
			'optimization_score' => 0
		);

		// Group items by shipping class
		$items_by_class = $this->group_items_by_shipping_class( $package['contents'] );

		foreach ( $items_by_class as $class_id => $items ) {
			$optimal_packages = $this->optimize_packages_for_class( $class_id, $items );
			$pouch['packages'] = array_merge( $pouch['packages'], $optimal_packages );
		}

		// Calculate totals
		foreach ( $pouch['packages'] as $pkg ) {
			$pouch['total_weight'] += $pkg['weight'];
			$pouch['total_items'] += $pkg['item_count'];
		}

		// Store pouch data for later use
		$this->store_pouch_data( $pouch );

		return $pouch;
	}

	/**
	 * Group cart items by shipping class
	 *
	 * @param array $cart_items Cart items
	 * @return array Items grouped by class
	 */
	private function group_items_by_shipping_class( $cart_items ) {
		$grouped = array();

		foreach ( $cart_items as $cart_item ) {
			$product = wc_get_product( $cart_item['product_id'] );
			if ( ! $product ) continue;

			$class_id = $product->get_shipping_class_id();
			if ( ! isset( $grouped[ $class_id ] ) ) {
				$grouped[ $class_id ] = array();
			}

			$grouped[ $class_id ][] = array(
				'product_id' => $cart_item['product_id'],
				'quantity' => $cart_item['quantity'],
				'weight' => $product->get_weight() ?: 0.5, // Default weight if not set
				'product' => $product
			);
		}

		return $grouped;
	}

	/**
	 * Optimize package selection for a shipping class
	 *
	 * @param int   $class_id Shipping class ID
	 * @param array $items    Items to package
	 * @return array Optimized packages
	 */
	private function optimize_packages_for_class( $class_id, $items ) {
		$packages = array();
		$total_quantity = array_sum( array_column( $items, 'quantity' ) );
		$total_weight = array_sum( array_map( function( $item ) {
			return $item['weight'] * $item['quantity'];
		}, $items ) );

		// Find the most cost-effective package combination
		$best_combination = $this->find_optimal_package_combination( $total_quantity, $total_weight );

		foreach ( $best_combination as $package_type => $count ) {
			if ( $count > 0 ) {
				for ( $i = 0; $i < $count; $i++ ) {
					$packages[] = array(
						'type' => $package_type,
						'name' => $this->package_types[ $package_type ]['name'],
						'rate' => (float) $this->get_option( "{$package_type}_rate", $this->package_types[ $package_type ]['default_rate'] ),
						'weight' => min( $total_weight / $count, $this->package_types[ $package_type ]['max_weight'] ),
						'item_count' => min( $total_quantity / $count, $this->get_option( "{$package_type}_max_items", 5 ) ),
						'color' => $this->package_types[ $package_type ]['color'],
						'qr_code' => $this->get_option( 'enable_qr_codes' ) === 'yes' ? $this->generate_package_qr_code() : null
					);
				}
			}
		}

		return $packages;
	}

	/**
	 * Find optimal package combination using dynamic programming
	 *
	 * @param int   $quantity Total quantity
	 * @param float $weight   Total weight
	 * @return array Package combination
	 */
	private function find_optimal_package_combination( $quantity, $weight ) {
		$combinations = array();

		// Try each package type
		foreach ( $this->package_types as $type => $config ) {
			$max_items = (int) $this->get_option( "{$type}_max_items", 5 );
			$max_weight = (float) $this->get_option( "{$type}_max_weight", $config['max_weight'] );
			$rate = (float) $this->get_option( "{$type}_rate", $config['default_rate'] );

			// Calculate packages needed for this type
			$packages_by_quantity = ceil( $quantity / $max_items );
			$packages_by_weight = ceil( $weight / $max_weight );
			$packages_needed = max( $packages_by_quantity, $packages_by_weight );

			$total_cost = $packages_needed * $rate;

			$combinations[ $type ] = array(
				'packages' => $packages_needed,
				'cost' => $total_cost,
				'efficiency' => $quantity / $total_cost // Items per cost unit
			);
		}

		// Find the most cost-effective combination
		$best_type = array_keys( $combinations, min( $combinations ) )[0];
		
		return array( $best_type => $combinations[ $best_type ]['packages'] );
	}

	/**
	 * Calculate total cost for a pouch
	 *
	 * @param array $pouch Pouch data
	 * @return float Total cost
	 */
	private function calculate_pouch_cost( $pouch ) {
		$total_cost = 0;

		foreach ( $pouch['packages'] as $package ) {
			$total_cost += $package['rate'];
		}

		return $total_cost > 0 ? $total_cost : (float) $this->get_option( 'default_rate', '6.35' );
	}

	/**
	 * Generate QR code for package
	 *
	 * @return string|null QR code data or null if disabled
	 */
	private function generate_package_qr_code() {
		if ( $this->get_option( 'enable_qr_codes' ) !== 'yes' ) {
			return null;
		}

		$qr_data = array(
			'package_id' => 'pkg_' . uniqid(),
			'timestamp' => time(),
			'shipping_method' => $this->id,
			'carrier' => 'Special Rate Shipping'
		);

		return base64_encode( json_encode( $qr_data ) );
	}

	/**
	 * Store pouch data for tracking
	 *
	 * @param array $pouch Pouch data
	 * @return void
	 */
	private function store_pouch_data( $pouch ) {
		// Store in WordPress options or custom table
		$stored_pouches = get_option( 'special_rate_shipping_pouches', array() );
		$stored_pouches[ $pouch['id'] ] = $pouch;
		
		// Keep only last 100 pouches to prevent bloat
		if ( count( $stored_pouches ) > 100 ) {
			$stored_pouches = array_slice( $stored_pouches, -100, 100, true );
		}

		update_option( 'special_rate_shipping_pouches', $stored_pouches );
	}

	/**
	 * AJAX handler for QR code generation
	 *
	 * @return void
	 */
	public function generate_shipping_qr_ajax() {
		check_ajax_referer( 'special_rate_shipping_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Insufficient permissions' );
		}

		$pouch_id = sanitize_text_field( $_POST['pouch_id'] ?? '' );
		
		if ( empty( $pouch_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid pouch ID' ) );
		}

		// Generate QR code image URL
		$qr_url = $this->generate_qr_code_url( $pouch_id );

		wp_send_json_success( array(
			'qr_url' => $qr_url,
			'pouch_id' => $pouch_id
		) );
	}

	/**
	 * Generate QR code URL
	 *
	 * @param string $pouch_id Pouch ID
	 * @return string QR code URL
	 */
	private function generate_qr_code_url( $pouch_id ) {
		$qr_data = array(
			'pouch_id' => $pouch_id,
			'site_url' => get_site_url(),
			'timestamp' => time()
		);

		$data_string = base64_encode( json_encode( $qr_data ) );
		
		// Use the existing QR code library
		$qr_url = SPECIAL_RATE_SHIPPING_PLUGIN_URL . 'includes/lib/qr_img.php?d=' . urlencode( $data_string ) . '&e=M&s=4';

		return $qr_url;
	}

	/**
	 * Get pouch data by ID
	 *
	 * @param string $pouch_id Pouch ID
	 * @return array|null Pouch data or null if not found
	 */
	public function get_pouch_data( $pouch_id ) {
		$stored_pouches = get_option( 'special_rate_shipping_pouches', array() );
		return isset( $stored_pouches[ $pouch_id ] ) ? $stored_pouches[ $pouch_id ] : null;
	}

	/**
	 * Check if method is available
	 *
	 * @param array $package Package data
	 * @return bool
	 */
	public function is_available( $package ) {
		return parent::is_available( $package ) && class_exists( 'WooCommerce' );
	}
}