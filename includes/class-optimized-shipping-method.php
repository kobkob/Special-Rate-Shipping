<?php
/**
 * Optimized Special Rate Shipping Method
 *
 * @package Special_Rate_Shipping
 * @subpackage Optimized_Shipping_Method
 * @since 2.0.0
 * 
 * A centralized, clean shipping method that uses the Package Optimizer
 * for intelligent package selection and cost calculation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'WC_Shipping_Method' ) ) {
	return; // WooCommerce not loaded
}

class Optimized_Shipping_Method extends WC_Shipping_Method {
	
	/**
	 * Package Optimizer instance
	 * @var Package_Optimizer
	 */
	private $optimizer;
	
	/**
	 * Constructor
	 *
	 * @param int $instance_id Instance ID
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id = 'optimized_special_rate';
		$this->instance_id = absint( $instance_id );
		$this->method_title = __( 'Special Rate Shipping (Optimized)', 'special-rate-shipping' );
		$this->method_description = __( 'Intelligent package optimization for best shipping rates using multiple package types.', 'special-rate-shipping' );
		$this->supports = array( 'shipping-zones', 'instance-settings' );
		$this->title = $this->get_option( 'title', $this->method_title );
		
		// Initialize Package Optimizer
		$use_usps_rates = $this->get_option( 'use_usps_rates', 'no' ) === 'yes';
		$this->optimizer = new Package_Optimizer( $use_usps_rates );
		
		$this->init();
	}
	
	/**
	 * Initialize the shipping method
	 */
	public function init() {
		$this->init_form_fields();
		$this->init_settings();
		
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}
	
	/**
	 * Initialize form fields
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title' => __( 'Enable/Disable', 'special-rate-shipping' ),
				'type' => 'checkbox',
				'label' => __( 'Enable Special Rate Shipping (Optimized)', 'special-rate-shipping' ),
				'default' => 'yes'
			),
			'title' => array(
				'title' => __( 'Method Title', 'special-rate-shipping' ),
				'type' => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'special-rate-shipping' ),
				'default' => __( 'Special Rate', 'special-rate-shipping' ),
				'desc_tip' => true
			),
			'use_usps_rates' => array(
				'title' => __( 'Use USPS Real-time Rates', 'special-rate-shipping' ),
				'type' => 'checkbox',
				'label' => __( 'Get real-time rates from USPS API (requires API configuration)', 'special-rate-shipping' ),
				'description' => __( 'When enabled, the system will fetch live rates from USPS. Otherwise, it will use configured rates.', 'special-rate-shipping' ),
				'default' => 'no',
				'desc_tip' => true
			),
			'show_package_details' => array(
				'title' => __( 'Show Package Details', 'special-rate-shipping' ),
				'type' => 'checkbox',
				'label' => __( 'Show optimization details to customers', 'special-rate-shipping' ),
				'description' => __( 'Display the number of packages and optimization information in the shipping method description.', 'special-rate-shipping' ),
				'default' => 'no',
				'desc_tip' => true
			),
			'fallback_rate' => array(
				'title' => __( 'Fallback Rate', 'special-rate-shipping' ),
				'type' => 'price',
				'description' => __( 'Rate to use when optimization fails or no packages are configured.', 'special-rate-shipping' ),
				'default' => '9.35',
				'desc_tip' => true
			),
			'package_config_section' => array(
				'title' => __( 'Package Configuration', 'special-rate-shipping' ),
				'type' => 'title',
				'description' => __( 'Configure package types, rates, and limits. Changes here will affect all shipping classes.', 'special-rate-shipping' )
			)
		);
		
		// Add package configuration fields
		$package_types = $this->optimizer->get_package_types();
		foreach ( $package_types as $package_id => $package_config ) {
			$this->form_fields[ "{$package_id}_enabled" ] = array(
				'title' => sprintf( __( 'Enable %s', 'special-rate-shipping' ), $package_config['name'] ),
				'type' => 'checkbox',
				'label' => sprintf( __( 'Allow %s packages', 'special-rate-shipping' ), $package_config['name'] ),
				'default' => 'yes'
			);
			
			$this->form_fields[ "{$package_id}_rate" ] = array(
				'title' => sprintf( __( '%s Rate', 'special-rate-shipping' ), $package_config['name'] ),
				'type' => 'price',
				'description' => sprintf( __( 'Shipping rate for %s packages.', 'special-rate-shipping' ), $package_config['name'] ),
				'default' => $package_config['default_rate'],
				'desc_tip' => true
			);
			
			$this->form_fields[ "{$package_id}_max_items" ] = array(
				'title' => sprintf( __( '%s Max Items', 'special-rate-shipping' ), $package_config['name'] ),
				'type' => 'number',
				'description' => sprintf( __( 'Maximum items per %s package.', 'special-rate-shipping' ), $package_config['name'] ),
				'default' => $package_config['default_max_items'],
				'custom_attributes' => array( 'min' => '1' ),
				'desc_tip' => true
			);
		}
		
		// Add shipping class specific configuration
		$this->form_fields['class_config_section'] = array(
			'title' => __( 'Shipping Class Override', 'special-rate-shipping' ),
			'type' => 'title',
			'description' => __( 'Override package settings for specific shipping classes. Leave empty to use default settings above.', 'special-rate-shipping' )
		);
		
		$shipping_classes = WC()->shipping->get_shipping_classes();
		foreach ( $shipping_classes as $shipping_class ) {
			$class_id = $shipping_class->term_id;
			$class_name = $shipping_class->name;
			
			$this->form_fields[ "class_{$class_id}_section" ] = array(
				'title' => $class_name,
				'type' => 'title',
				'description' => $shipping_class->description ?: sprintf( __( 'Settings for %s shipping class', 'special-rate-shipping' ), $class_name )
			);
			
			foreach ( $package_types as $package_id => $package_config ) {
				$this->form_fields[ "class_{$class_id}_{$package_id}_enabled" ] = array(
					'title' => sprintf( __( '%s for %s', 'special-rate-shipping' ), $package_config['name'], $class_name ),
					'type' => 'select',
					'options' => array(
						'' => __( 'Use Default', 'special-rate-shipping' ),
						'yes' => __( 'Enabled', 'special-rate-shipping' ),
						'no' => __( 'Disabled', 'special-rate-shipping' )
					),
					'default' => ''
				);
				
				$this->form_fields[ "class_{$class_id}_{$package_id}_max_items" ] = array(
					'title' => sprintf( __( '%s Max Items for %s', 'special-rate-shipping' ), $package_config['name'], $class_name ),
					'type' => 'number',
					'description' => __( 'Leave empty to use default setting.', 'special-rate-shipping' ),
					'custom_attributes' => array( 'min' => '1' ),
					'desc_tip' => true
				);
			}
		}
	}
	
	/**
	 * Calculate shipping
	 *
	 * @param array $package WooCommerce package
	 */
	public function calculate_shipping( $package = array() ) {
		if ( empty( $package['contents'] ) ) {
			$this->add_fallback_rate();
			return;
		}
		
		try {
			// Sync settings with global options for Package Optimizer
			$this->sync_settings_to_global_options();
			
			// Calculate shipping using Package Optimizer
			$cost = $this->optimizer->calculate_woocommerce_shipping( $package );
			
			// Create rate
			$rate = array(
				'id' => $this->get_rate_id(),
				'label' => $this->title,
				'cost' => $cost,
				'calc_tax' => 'per_order'
			);
			
			// Add package details to label if enabled
			if ( $this->get_option( 'show_package_details', 'no' ) === 'yes' ) {
				$items = array();
				foreach ( $package['contents'] as $cart_item ) {
					$items[] = array(
						'product_id' => $cart_item['product_id'],
						'quantity' => $cart_item['quantity']
					);
				}
				
				$from_address = array( 'postcode' => get_option( 'srs_sender_postcode', '' ) );
				$to_address = array( 'postcode' => $package['destination']['postcode'] ?? '' );
				$optimization = $this->optimizer->calculate_optimal_packaging( $items, $from_address, $to_address );
				
				if ( $optimization['total_packages'] > 0 ) {
					$rate['meta_data'] = array(
						'packages' => $optimization['total_packages'],
						'optimization_method' => $optimization['calculation_method']
					);
					
					$rate['label'] .= sprintf( 
						__( ' (%d packages)', 'special-rate-shipping' ), 
						$optimization['total_packages'] 
					);
				}
			}
			
			$this->add_rate( $rate );
			
		} catch ( Exception $e ) {
			// Log error and add fallback rate
			error_log( 'Optimized Shipping Method Error: ' . $e->getMessage() );
			$this->add_fallback_rate();
		}
	}
	
	/**
	 * Add fallback rate when optimization fails
	 */
	private function add_fallback_rate() {
		$rate = array(
			'id' => $this->get_rate_id(),
			'label' => $this->title,
			'cost' => $this->get_option( 'fallback_rate', '9.35' ),
			'calc_tax' => 'per_order'
		);
		
		$this->add_rate( $rate );
	}
	
	/**
	 * Sync local settings to global options for Package Optimizer
	 */
	private function sync_settings_to_global_options() {
		$package_types = $this->optimizer->get_package_types();
		
		// Sync package rates and settings
		foreach ( $package_types as $package_id => $package_config ) {
			$enabled = $this->get_option( "{$package_id}_enabled", 'yes' );
			$rate = $this->get_option( "{$package_id}_rate", $package_config['default_rate'] );
			$max_items = $this->get_option( "{$package_id}_max_items", $package_config['default_max_items'] );
			
			update_option( "srs_0_{$package_id}_enabled", $enabled );
			update_option( "srs_{$package_id}_rate", $rate );
			update_option( "srs_0_{$package_id}_max_items", $max_items );
		}
		
		// Sync shipping class overrides
		$shipping_classes = WC()->shipping->get_shipping_classes();
		foreach ( $shipping_classes as $shipping_class ) {
			$class_id = $shipping_class->term_id;
			
			foreach ( $package_types as $package_id => $package_config ) {
				$class_enabled = $this->get_option( "class_{$class_id}_{$package_id}_enabled", '' );
				$class_max_items = $this->get_option( "class_{$class_id}_{$package_id}_max_items", '' );
				
				if ( $class_enabled !== '' ) {
					update_option( "srs_{$class_id}_{$package_id}_enabled", $class_enabled );
				}
				
				if ( $class_max_items !== '' ) {
					update_option( "srs_{$class_id}_{$package_id}_max_items", intval( $class_max_items ) );
				}
			}
		}
		
		// Sync USPS settings
		update_option( 'srs_enable_package_optimization', $this->get_option( 'use_usps_rates', 'no' ) );
	}
	
	/**
	 * Check if method is available
	 *
	 * @param array $package Package data
	 * @return bool
	 */
	public function is_available( $package ) {
		if ( ! parent::is_available( $package ) ) {
			return false;
		}
		
		// Ensure Package Optimizer is available
		return class_exists( 'Package_Optimizer' );
	}
}