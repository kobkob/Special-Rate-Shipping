<?php
/**
 * Plugin Name: Special Rate Shipping
 * Plugin URI: https://www.kobkob.org/
 * Description: WooCommerce extension providing custom shipping rates based on product types and quantities with intelligent packaging optimization.
 * Version: 2.0.0
 * Requires at least: 5.0
 * Requires PHP: 8.1
 * Tested up to: 6.4
 * WC requires at least: 6.0
 * WC tested up to: 8.5
 * Author: Kobkob LLC
 * Author URI: https://www.kobkob.org/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: special-rate-shipping
 * Domain Path: /languages/
 * Network: false
 *
 * @package KobKob\SpecialRateShipping
 * @author Monsenhor Ricardo Filipo <filipo@kobkob.org>
 * @since 2.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'SPECIAL_RATE_SHIPPING_VERSION', '2.0.0' );
define( 'SPECIAL_RATE_SHIPPING_PLUGIN_FILE', __FILE__ );
define( 'SPECIAL_RATE_SHIPPING_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SPECIAL_RATE_SHIPPING_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SPECIAL_RATE_SHIPPING_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Check PHP version
if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action( 'admin_notices', function () {
		echo '<div class="error"><p>' . 
			sprintf(
				/* translators: %1$s: Required PHP version, %2$s: Current PHP version */
				esc_html__( 'Special Rate Shipping requires PHP version %1$s or higher. You are running version %2$s.', 'special-rate-shipping' ),
				'8.1',
				PHP_VERSION
			) . 
			'</p></div>';
	});
	return;
}

// Check if WooCommerce is active (including multisite support)
if ( ! special_rate_shipping_is_woocommerce_active() ) {
	add_action( 'admin_notices', function () {
		echo '<div class="error"><p>' . 
			esc_html__( 'Special Rate Shipping requires WooCommerce to be installed and active.', 'special-rate-shipping' ) . 
			'</p></div>';
	});
	return;
}

/**
 * Check if WooCommerce is active (including multisite support)
 *
 * @return bool
 */
function special_rate_shipping_is_woocommerce_active() {
	// Check for single site
	if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
		return true;
	}
	
	// Check for multisite
	if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
		require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
	}
	
	if ( is_multisite() && is_plugin_active_for_network( 'woocommerce/woocommerce.php' ) ) {
		return true;
	}
	
	return false;
}

// Load plugin class files after WooCommerce is ready
add_action( 'woocommerce_loaded', 'special_rate_shipping_load_classes' );

function special_rate_shipping_load_classes() {
	try {
		// Load plugin class files - compatible approach
		require_once SPECIAL_RATE_SHIPPING_PLUGIN_DIR . 'includes/class-special-rate-shipping.php';
		require_once SPECIAL_RATE_SHIPPING_PLUGIN_DIR . 'includes/class-special-rate-shipping-settings.php';
		require_once SPECIAL_RATE_SHIPPING_PLUGIN_DIR . 'includes/class-special-rate-shipping-method.php';
		require_once SPECIAL_RATE_SHIPPING_PLUGIN_DIR . 'includes/class-special-rate-shipping-enhanced-method.php';

		// Load plugin libraries
		require_once SPECIAL_RATE_SHIPPING_PLUGIN_DIR . 'includes/lib/class-special-rate-shipping-admin-api.php';
		require_once SPECIAL_RATE_SHIPPING_PLUGIN_DIR . 'includes/lib/class-special-rate-shipping-post-type.php';
		require_once SPECIAL_RATE_SHIPPING_PLUGIN_DIR . 'includes/lib/class-special-rate-shipping-taxonomy.php';

		// Initialize the plugin after classes are loaded
		special_rate_shipping();
		
		// Declare WooCommerce compatibility
		add_action( 'before_woocommerce_init', function() {
			if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
			}
		});
		
	} catch ( Exception $e ) {
		add_action( 'admin_notices', function() use ( $e ) {
			echo '<div class="error"><p>' . 
				sprintf( 
					/* translators: %s: Error message */
					esc_html__( 'Special Rate Shipping failed to load: %s', 'special-rate-shipping' ),
					esc_html( $e->getMessage() )
				) . 
				'</p></div>';
		});
	}
}

/**
 * Returns the main instance of Special_Rate_Shipping to prevent the need to use globals.
 *
 * @since  2.0.0
 * @return Special_Rate_Shipping
 */
function special_rate_shipping() {
	$instance = Special_Rate_Shipping::instance( __FILE__, '2.0.0' );

	if ( is_null( $instance->settings ) ) {
		$instance->settings = Special_Rate_Shipping_Settings::instance( $instance );
	}

	return $instance;
}

// Note: Plugin initialization is now called from special_rate_shipping_load_classes()
