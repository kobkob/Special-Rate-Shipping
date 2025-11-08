<?php

declare(strict_types=1);

namespace KobKob\SpecialRateShipping\Shipping;

/**
 * Shipping Manager class
 *
 * @package KobKob\SpecialRateShipping\Shipping
 * @since 2.0.0
 */
class ShippingManager
{
    /**
     * Initialize shipping functionality
     *
     * @return void
     * @since 2.0.0
     */
    public function init(): void
    {
        // Register shipping method
        add_action('woocommerce_shipping_init', [$this, 'init_shipping_method']);
        add_filter('woocommerce_shipping_methods', [$this, 'add_shipping_method']);

        // Clear cache on cart update
        add_action('woocommerce_cart_updated', [$this, 'clear_shipping_cache']);
    }

    /**
     * Initialize the shipping method class
     *
     * @return void
     * @since 2.0.0
     */
    public function init_shipping_method(): void
    {
        require_once __DIR__ . '/SpecialRateShippingMethod.php';
    }

    /**
     * Add shipping method to WooCommerce
     *
     * @param array $methods Existing shipping methods
     * @return array Modified methods array
     * @since 2.0.0
     */
    public function add_shipping_method(array $methods): array
    {
        $methods['special_rate_shipping_method'] = SpecialRateShippingMethod::class;
        return $methods;
    }

    /**
     * Clear shipping cache when cart is updated
     *
     * @return void
     * @since 2.0.0
     */
    public function clear_shipping_cache(): void
    {
        // Clear WooCommerce shipping cache
        if (function_exists('WC') && WC()->session) {
            WC()->session->set('shipping_for_package_0', null);
        }

        // Clear any custom caches
        wp_cache_delete('special_rate_shipping_rates', 'shipping');
    }
}