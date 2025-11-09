<?php
/**
 * Package Optimizer Class
 *
 * @package Special_Rate_Shipping
 * @subpackage Package_Optimizer
 * @since 2.0.0
 * 
 * Handles all package optimization calculations in one centralized place:
 * - Determines optimal package combinations for best pricing
 * - Supports multiple package types (small, medium, big, envelope, flat rate)
 * - Calculates shipping costs for WooCommerce integration
 * - Generates package configurations for pouches
 * - USPS API integration for real-time rates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Package_Optimizer {
	
	/**
	 * Available package types with default configurations
	 * @var array
	 */
	private $package_types = array(
		'small_box' => array(
			'name' => 'Small Box',
			'default_rate' => 6.35,
			'default_max_items' => 3,
			'dimensions' => array( 'length' => 8, 'width' => 6, 'height' => 4 ),
			'max_weight' => 2.0, // lbs
			'usps_service' => 'GROUND_ADVANTAGE'
		),
		'medium_box' => array(
			'name' => 'Medium Box', 
			'default_rate' => 9.35,
			'default_max_items' => 6,
			'dimensions' => array( 'length' => 12, 'width' => 8, 'height' => 6 ),
			'max_weight' => 5.0, // lbs
			'usps_service' => 'GROUND_ADVANTAGE'
		),
		'big_box' => array(
			'name' => 'Big Box',
			'default_rate' => 13.35,
			'default_max_items' => 12,
			'dimensions' => array( 'length' => 16, 'width' => 12, 'height' => 8 ),
			'max_weight' => 10.0, // lbs
			'usps_service' => 'GROUND_ADVANTAGE'
		),
		'envelope' => array(
			'name' => 'Envelope',
			'default_rate' => 4.50,
			'default_max_items' => 1,
			'dimensions' => array( 'length' => 12, 'width' => 9, 'height' => 1 ),
			'max_weight' => 0.5, // lbs
			'usps_service' => 'FIRST_CLASS'
		),
		'flat_rate' => array(
			'name' => 'Flat Rate Box',
			'default_rate' => 8.45,
			'default_max_items' => 8,
			'dimensions' => array( 'length' => 12, 'width' => 8, 'height' => 6 ),
			'max_weight' => 70.0, // lbs (USPS limit)
			'usps_service' => 'PRIORITY'
		)
	);
	
	/**
	 * USPS API instance
	 * @var USPS_API|null
	 */
	private $usps_api = null;
	
	/**
	 * Enable real-time USPS rates
	 * @var bool
	 */
	private $use_usps_rates = false;
	
	/**
	 * Constructor
	 *
	 * @param bool $use_usps_rates Whether to use real-time USPS rates
	 */
	public function __construct( $use_usps_rates = false ) {
		$this->use_usps_rates = $use_usps_rates;
		
		if ( $use_usps_rates && class_exists( 'USPS_API' ) ) {
			$api_key = get_option( 'srs_usps_api_key' );
			$api_secret = get_option( 'srs_usps_api_secret' );
			$environment = get_option( 'srs_usps_environment', 'sandbox' );
			$debug = get_option( 'srs_debug_mode', false );
			
			if ( $api_key && $api_secret ) {
				$this->usps_api = new USPS_API( $api_key, $api_secret, $environment, $debug );
			}
		}
	}
	
	/**
	 * Calculate optimal packaging for a set of items
	 *
	 * @param array $items Array of items with quantities and shipping classes
	 * @param array $from_address Sender address for USPS rates (optional)
	 * @param array $to_address Recipient address for USPS rates (optional)
	 * @return array Optimized package configuration
	 */
	public function calculate_optimal_packaging( $items, $from_address = null, $to_address = null ) {
		if ( empty( $items ) ) {
			return array(
				'packages' => array(),
				'total_cost' => 0,
				'total_packages' => 0,
				'optimization_details' => array()
			);
		}
		
		// Group items by shipping class
		$items_by_class = $this->group_items_by_shipping_class( $items );
		
		$all_packages = array();
		$total_cost = 0;
		$optimization_details = array();
		
		foreach ( $items_by_class as $class_id => $class_items ) {
			$class_optimization = $this->optimize_shipping_class( $class_id, $class_items, $from_address, $to_address );
			
			$all_packages = array_merge( $all_packages, $class_optimization['packages'] );
			$total_cost += $class_optimization['cost'];
			$optimization_details[ $class_id ] = $class_optimization['details'];
		}
		
		return array(
			'packages' => $all_packages,
			'total_cost' => $total_cost,
			'total_packages' => count( $all_packages ),
			'optimization_details' => $optimization_details,
			'calculation_method' => $this->use_usps_rates ? 'usps_api' : 'fixed_rates'
		);
	}
	
	/**
	 * Group items by shipping class
	 *
	 * @param array $items Items to group
	 * @return array Items grouped by shipping class ID
	 */
	private function group_items_by_shipping_class( $items ) {
		$grouped = array();
		
		foreach ( $items as $item ) {
			$product_id = isset( $item['product_id'] ) ? $item['product_id'] : $item['id'];
			$quantity = isset( $item['quantity'] ) ? $item['quantity'] : 1;
			
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}
			
			$shipping_class_id = $product->get_shipping_class_id();
			if ( $shipping_class_id === '' ) {
				$shipping_class_id = 0; // Default class
			}
			
			if ( ! isset( $grouped[ $shipping_class_id ] ) ) {
				$grouped[ $shipping_class_id ] = array();
			}
			
			$grouped[ $shipping_class_id ][] = array(
				'product_id' => $product_id,
				'product' => $product,
				'quantity' => $quantity,
				'weight' => $product->get_weight() ? floatval( $product->get_weight() ) : 0.1,
				'dimensions' => array(
					'length' => $product->get_length() ? floatval( $product->get_length() ) : 1,
					'width' => $product->get_width() ? floatval( $product->get_width() ) : 1,
					'height' => $product->get_height() ? floatval( $product->get_height() ) : 1
				)
			);
		}
		
		return $grouped;
	}
	
	/**
	 * Optimize packaging for a specific shipping class
	 *
	 * @param int $class_id Shipping class ID
	 * @param array $items Items in this shipping class
	 * @param array $from_address Sender address
	 * @param array $to_address Recipient address
	 * @return array Optimization result
	 */
	private function optimize_shipping_class( $class_id, $items, $from_address = null, $to_address = null ) {
		$total_quantity = array_sum( array_column( $items, 'quantity' ) );
		$total_weight = array_sum( array_map( function( $item ) {
			return $item['weight'] * $item['quantity'];
		}, $items ) );
		
		if ( $total_quantity === 0 ) {
			return array(
				'packages' => array(),
				'cost' => 0,
				'details' => array( 'message' => 'No items to optimize' )
			);
		}
		
		$enabled_packages = $this->get_enabled_packages_for_class( $class_id );
		if ( empty( $enabled_packages ) ) {
			return array(
				'packages' => array(),
				'cost' => 0,
				'details' => array( 'message' => 'No enabled package types for shipping class ' . $class_id )
			);
		}
		
		// Try all possible package combinations to find the most cost-effective
		$best_solution = null;
		$best_cost = PHP_FLOAT_MAX;
		
		foreach ( $this->generate_package_combinations( $total_quantity, $total_weight, $enabled_packages ) as $combination ) {
			$cost = $this->calculate_combination_cost( $combination, $from_address, $to_address );
			
			if ( $cost < $best_cost ) {
				$best_cost = $cost;
				$best_solution = $combination;
			}
		}
		
		if ( ! $best_solution ) {
			// Fallback: use medium box
			$best_solution = array(
				array(
					'type' => 'medium_box',
					'count' => 1,
					'items' => $total_quantity,
					'weight' => $total_weight
				)
			);
			$best_cost = $this->get_package_rate( 'medium_box' );
		}
		
		// Convert solution to package format
		$packages = array();
		foreach ( $best_solution as $package_config ) {
			for ( $i = 0; $i < $package_config['count']; $i++ ) {
				$packages[] = array(
					'type' => $package_config['type'],
					'name' => $this->package_types[ $package_config['type'] ]['name'],
					'items_count' => intval( $package_config['items'] / $package_config['count'] ),
					'weight' => $package_config['weight'] / $package_config['count'],
					'dimensions' => $this->package_types[ $package_config['type'] ]['dimensions'],
					'rate' => $this->get_package_rate( $package_config['type'] ),
					'shipping_class_id' => $class_id
				);
			}
		}
		
		return array(
			'packages' => $packages,
			'cost' => $best_cost,
			'details' => array(
				'total_quantity' => $total_quantity,
				'total_weight' => $total_weight,
				'optimization_tried' => count( iterator_to_array( $this->generate_package_combinations( $total_quantity, $total_weight, $enabled_packages ) ) ),
				'best_solution' => $best_solution
			)
		);
	}
	
	/**
	 * Get enabled package types for a shipping class
	 *
	 * @param int $class_id Shipping class ID
	 * @return array Enabled package configurations
	 */
	private function get_enabled_packages_for_class( $class_id ) {
		$enabled = array();
		
		foreach ( $this->package_types as $package_id => $package_config ) {
			// Check if package is enabled for this class
			$enabled_option = get_option( "srs_{$class_id}_{$package_id}_enabled", 'yes' );
			
			if ( $enabled_option === 'yes' ) {
				$max_items = intval( get_option( "srs_{$class_id}_{$package_id}_max_items", $package_config['default_max_items'] ) );
				
				$enabled[ $package_id ] = array_merge( $package_config, array(
					'max_items' => max( 1, $max_items ),
					'rate' => $this->get_package_rate( $package_id )
				) );
			}
		}
		
		return $enabled;
	}
	
	/**
	 * Get the rate for a package type
	 *
	 * @param string $package_id Package type ID
	 * @return float Package rate
	 */
	private function get_package_rate( $package_id ) {
		if ( ! isset( $this->package_types[ $package_id ] ) ) {
			return 0.0;
		}
		
		$setting_key = "srs_{$package_id}_rate";
		$rate = get_option( $setting_key, $this->package_types[ $package_id ]['default_rate'] );
		
		return floatval( $rate );
	}
	
	/**
	 * Generate all possible package combinations for given quantity and weight
	 *
	 * @param int $quantity Total quantity
	 * @param float $weight Total weight
	 * @param array $enabled_packages Available package types
	 * @return Generator Package combinations
	 */
	private function generate_package_combinations( $quantity, $weight, $enabled_packages ) {
		// Simple greedy approach: try each package type individually first
		foreach ( $enabled_packages as $package_id => $package_config ) {
			if ( $weight <= $package_config['max_weight'] ) {
				$packages_needed = intval( ceil( $quantity / $package_config['max_items'] ) );
				
				yield array(
					array(
						'type' => $package_id,
						'count' => $packages_needed,
						'items' => $quantity,
						'weight' => $weight
					)
				);
			}
		}
		
		// More complex combinations (for future enhancement)
		// This could include mixed package types for even better optimization
		yield from $this->generate_mixed_combinations( $quantity, $weight, $enabled_packages );
	}
	
	/**
	 * Generate mixed package combinations
	 *
	 * @param int $quantity Remaining quantity
	 * @param float $weight Remaining weight
	 * @param array $enabled_packages Available packages
	 * @param array $current_combination Current combination being built
	 * @return Generator Mixed combinations
	 */
	private function generate_mixed_combinations( $quantity, $weight, $enabled_packages, $current_combination = array() ) {
		// Base case: no items left
		if ( $quantity <= 0 ) {
			if ( ! empty( $current_combination ) ) {
				yield $current_combination;
			}
			return;
		}
		
		// Try each package type
		foreach ( $enabled_packages as $package_id => $package_config ) {
			$items_per_package = min( $package_config['max_items'], $quantity );
			$weight_per_package = min( $package_config['max_weight'], $weight );
			
			if ( $items_per_package > 0 && $weight_per_package > 0 ) {
				$new_combination = $current_combination;
				$new_combination[] = array(
					'type' => $package_id,
					'count' => 1,
					'items' => $items_per_package,
					'weight' => $weight_per_package
				);
				
				// Recursively try with remaining items
				$remaining_quantity = $quantity - $items_per_package;
				$remaining_weight = $weight - $weight_per_package;
				
				if ( $remaining_quantity <= 0 ) {
					yield $new_combination;
				} else {
					// Limit recursion depth to prevent infinite loops
					if ( count( $new_combination ) < 10 ) {
						yield from $this->generate_mixed_combinations( 
							$remaining_quantity, 
							$remaining_weight, 
							$enabled_packages, 
							$new_combination 
						);
					}
				}
			}
		}
	}
	
	/**
	 * Calculate cost for a package combination
	 *
	 * @param array $combination Package combination
	 * @param array $from_address Sender address
	 * @param array $to_address Recipient address
	 * @return float Total cost
	 */
	private function calculate_combination_cost( $combination, $from_address = null, $to_address = null ) {
		$total_cost = 0;
		
		foreach ( $combination as $package_config ) {
			$package_type = $package_config['type'];
			$count = $package_config['count'];
			
			if ( $this->use_usps_rates && $this->usps_api && $from_address && $to_address ) {
				// Use USPS API for real-time rates
				$rate = $this->get_usps_rate( $package_type, $package_config, $from_address, $to_address );
			} else {
				// Use configured rates
				$rate = $this->get_package_rate( $package_type );
			}
			
			$total_cost += $rate * $count;
		}
		
		return $total_cost;
	}
	
	/**
	 * Get USPS rate for a package
	 *
	 * @param string $package_type Package type ID
	 * @param array $package_config Package configuration
	 * @param array $from_address Sender address
	 * @param array $to_address Recipient address
	 * @return float USPS rate or fallback rate
	 */
	private function get_usps_rate( $package_type, $package_config, $from_address, $to_address ) {
		if ( ! $this->usps_api ) {
			return $this->get_package_rate( $package_type );
		}
		
		$package_info = $this->package_types[ $package_type ];
		
		$rate_data = array(
			'from_zip' => $from_address['postcode'],
			'to_zip' => $to_address['postcode'],
			'package' => array_merge( $package_info['dimensions'], array(
				'weight' => $package_config['weight']
			) ),
			'service_type' => $package_info['usps_service']
		);
		
		$usps_response = $this->usps_api->get_rates( $rate_data );
		
		if ( is_wp_error( $usps_response ) ) {
			// Fallback to configured rate
			return $this->get_package_rate( $package_type );
		}
		
		// Extract rate from USPS response
		if ( isset( $usps_response['rates'] ) && ! empty( $usps_response['rates'] ) ) {
			$rate = $usps_response['rates'][0]['price'] ?? null;
			if ( $rate && is_numeric( $rate ) ) {
				return floatval( $rate );
			}
		}
		
		// Fallback to configured rate
		return $this->get_package_rate( $package_type );
	}
	
	/**
	 * Get package types configuration
	 *
	 * @return array Package types
	 */
	public function get_package_types() {
		return $this->package_types;
	}
	
	/**
	 * Calculate shipping cost for WooCommerce cart/package
	 *
	 * @param array $wc_package WooCommerce package
	 * @return float Calculated shipping cost
	 */
	public function calculate_woocommerce_shipping( $wc_package ) {
		if ( empty( $wc_package['contents'] ) ) {
			return floatval( get_option( 'srs_default_rate', '6.35' ) );
		}
		
		// Convert WooCommerce package to our format
		$items = array();
		foreach ( $wc_package['contents'] as $item_key => $cart_item ) {
			$items[] = array(
				'product_id' => $cart_item['product_id'],
				'quantity' => $cart_item['quantity'],
				'data' => $cart_item['data']
			);
		}
		
		// Get addresses
		$from_address = null;
		$to_address = null;
		
		if ( $this->use_usps_rates ) {
			// Build from address from settings
			$from_address = array(
				'postcode' => get_option( 'srs_sender_postcode', '' )
			);
			
			// Build to address from package
			$to_address = array(
				'postcode' => $wc_package['destination']['postcode'] ?? ''
			);
		}
		
		$optimization = $this->calculate_optimal_packaging( $items, $from_address, $to_address );
		
		return $optimization['total_cost'];
	}
	
	/**
	 * Generate package configuration for pouch creation
	 *
	 * @param array $product_ids Product IDs with quantities
	 * @param array $recipient_address Recipient address
	 * @return array Package configuration for pouch
	 */
	public function generate_pouch_configuration( $product_ids, $recipient_address = null ) {
		// Convert product IDs to items format
		$items = array();
		foreach ( $product_ids as $product_id ) {
			if ( is_array( $product_id ) ) {
				// Format: array('product_id' => 123, 'quantity' => 2)
				$items[] = array(
					'product_id' => $product_id['product_id'],
					'quantity' => $product_id['quantity'] ?? 1
				);
			} else {
				// Format: simple product ID
				$items[] = array(
					'product_id' => $product_id,
					'quantity' => 1
				);
			}
		}
		
		// Get sender address for USPS rates
		$from_address = null;
		if ( $this->use_usps_rates ) {
			$from_address = array(
				'postcode' => get_option( 'srs_sender_postcode', '' )
			);
		}
		
		$optimization = $this->calculate_optimal_packaging( $items, $from_address, $recipient_address );
		
		// Determine primary package type (most common or largest)
		$primary_package_type = 'medium_box'; // default
		if ( ! empty( $optimization['packages'] ) ) {
			$package_types_count = array();
			foreach ( $optimization['packages'] as $package ) {
				$type = $package['type'];
				$package_types_count[ $type ] = ( $package_types_count[ $type ] ?? 0 ) + 1;
			}
			
			// Get most common package type
			$primary_package_type = array_search( max( $package_types_count ), $package_types_count );
		}
		
		return array(
			'package_type' => $primary_package_type,
			'packages' => $optimization['packages'],
			'total_cost' => $optimization['total_cost'],
			'total_packages' => $optimization['total_packages'],
			'optimization_details' => $optimization['optimization_details']
		);
	}
}