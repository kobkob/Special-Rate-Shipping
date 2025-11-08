<?php

declare(strict_types=1);

namespace KobKob\SpecialRateShipping\Shipping;

use WC_Shipping_Method;
use WC_Product_Factory;

/**
 * Special Rate Shipping Method class
 *
 * @package KobKob\SpecialRateShipping\Shipping
 * @since 2.0.0
 */
class SpecialRateShippingMethod extends WC_Shipping_Method
{
    /**
     * Package types configuration
     *
     * @var array
     */
    private array $package_types = [
        'small' => ['name' => 'Small Box', 'default_rate' => '6.35'],
        'medium' => ['name' => 'Medium Box', 'default_rate' => '9.35'],
        'big' => ['name' => 'Big Box', 'default_rate' => '13.35']
    ];

    /**
     * Constructor
     *
     * @param int $instance_id Instance ID
     */
    public function __construct(int $instance_id = 0)
    {
        $this->id = 'special_rate_shipping_method';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('Special Rate Shipping', 'special-rate-shipping');
        $this->method_description = __('Custom shipping rates based on product types and quantities with intelligent packaging optimization.', 'special-rate-shipping');
        $this->title = $this->get_option('title', $this->method_title);
        $this->supports = ['shipping-zones', 'instance-settings'];

        $this->init();
    }

    /**
     * Initialize the shipping method
     *
     * @return void
     * @since 2.0.0
     */
    public function init(): void
    {
        // Load the settings API
        $this->init_form_fields();
        $this->init_settings();

        // Save settings
        add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);
    }

    /**
     * Initialize form fields for the shipping method settings
     *
     * @return void
     * @since 2.0.0
     */
    public function init_form_fields(): void
    {
        $fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'special-rate-shipping'),
                'type' => 'checkbox',
                'label' => __('Enable Special Rate Shipping', 'special-rate-shipping'),
                'default' => 'yes'
            ],
            'title' => [
                'title' => __('Method Title', 'special-rate-shipping'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'special-rate-shipping'),
                'default' => __('Special Rate', 'special-rate-shipping'),
                'desc_tip' => true
            ],
            'default_rate' => [
                'title' => __('Default Rate', 'special-rate-shipping'),
                'type' => 'price',
                'description' => __('Default shipping rate when no specific package rules apply.', 'special-rate-shipping'),
                'default' => '6.35',
                'desc_tip' => true
            ],
            'packages_section' => [
                'title' => __('Package Types', 'special-rate-shipping'),
                'type' => 'title',
                'description' => __('Configure different package types and their rates.', 'special-rate-shipping')
            ]
        ];

        // Add package configuration fields
        foreach ($this->package_types as $package_id => $package_data) {
            $fields["{$package_id}_rate"] = [
                'title' => sprintf(__('%s Rate', 'special-rate-shipping'), $package_data['name']),
                'type' => 'price',
                'description' => sprintf(__('Shipping rate for %s packages.', 'special-rate-shipping'), $package_data['name']),
                'default' => $package_data['default_rate'],
                'desc_tip' => true
            ];
        }

        // Add shipping class configuration
        $fields['classes_section'] = [
            'title' => __('Shipping Class Configuration', 'special-rate-shipping'),
            'type' => 'title',
            'description' => __('Configure package limits for each shipping class.', 'special-rate-shipping')
        ];

        $shipping_classes = WC()->shipping->get_shipping_classes();
        foreach ($shipping_classes as $shipping_class) {
            $class_id = $shipping_class->term_id;
            $class_name = $shipping_class->name;

            $fields["{$class_id}_section"] = [
                'title' => $class_name,
                'type' => 'title',
                'description' => $shipping_class->description ?: sprintf(__('Settings for %s shipping class', 'special-rate-shipping'), $class_name)
            ];

            foreach ($this->package_types as $package_id => $package_data) {
                $fields["{$class_id}_{$package_id}_enabled"] = [
                    'title' => sprintf(__('Enable %s', 'special-rate-shipping'), $package_data['name']),
                    'type' => 'checkbox',
                    'label' => sprintf(__('Allow %s packages for %s', 'special-rate-shipping'), $package_data['name'], $class_name),
                    'default' => 'yes'
                ];

                $fields["{$class_id}_{$package_id}_max_items"] = [
                    'title' => sprintf(__('%s Max Items', 'special-rate-shipping'), $package_data['name']),
                    'type' => 'number',
                    'description' => sprintf(__('Maximum items per %s package for %s', 'special-rate-shipping'), $package_data['name'], $class_name),
                    'default' => $package_id === 'small' ? '5' : ($package_id === 'medium' ? '10' : '15'),
                    'custom_attributes' => ['min' => '1'],
                    'desc_tip' => true
                ];
            }
        }

        $this->form_fields = $fields;
    }

    /**
     * Calculate shipping for the given package
     *
     * @param array $package Package data
     * @return void
     * @since 2.0.0
     */
    public function calculate_shipping($package = []): void
    {
        try {
            $cost = $this->calculate_package_cost($package);

            $rate = [
                'id' => $this->get_rate_id(),
                'label' => $this->title,
                'cost' => $cost,
                'calc_tax' => 'per_order',
                'meta_data' => [
                    'shipping_method' => $this->id,
                    'calculated_at' => current_time('mysql')
                ]
            ];

            $this->add_rate($rate);

        } catch (\Exception $e) {
            // Log error and fall back to default rate
            error_log('Special Rate Shipping Error: ' . $e->getMessage());
            
            $fallback_rate = [
                'id' => $this->get_rate_id(),
                'label' => $this->title,
                'cost' => $this->get_option('default_rate', '6.35'),
                'calc_tax' => 'per_order'
            ];

            $this->add_rate($fallback_rate);
        }
    }

    /**
     * Calculate the total cost for the package
     *
     * @param array $package Package data
     * @return float Total shipping cost
     * @since 2.0.0
     */
    private function calculate_package_cost(array $package): float
    {
        if (empty($package['contents'])) {
            return (float) $this->get_option('default_rate', '6.35');
        }

        $total_cost = 0.0;
        $items_by_class = $this->group_items_by_shipping_class($package['contents']);

        foreach ($items_by_class as $class_id => $items) {
            $cost = $this->calculate_class_shipping_cost($class_id, $items);
            $total_cost += $cost;
        }

        return $total_cost > 0 ? $total_cost : (float) $this->get_option('default_rate', '6.35');
    }

    /**
     * Group cart items by shipping class
     *
     * @param array $cart_items Cart items
     * @return array Items grouped by shipping class
     * @since 2.0.0
     */
    private function group_items_by_shipping_class(array $cart_items): array
    {
        $grouped = [];
        $product_factory = new WC_Product_Factory();

        foreach ($cart_items as $cart_item) {
            $product = $product_factory->get_product($cart_item['product_id']);
            if (!$product) {
                continue;
            }

            $shipping_class_id = $product->get_shipping_class_id();
            if (!isset($grouped[$shipping_class_id])) {
                $grouped[$shipping_class_id] = [];
            }

            $grouped[$shipping_class_id][] = [
                'product_id' => $cart_item['product_id'],
                'quantity' => $cart_item['quantity'],
                'product' => $product
            ];
        }

        return $grouped;
    }

    /**
     * Calculate shipping cost for a specific shipping class
     *
     * @param int $class_id Shipping class ID
     * @param array $items Items in this shipping class
     * @return float Shipping cost for this class
     * @since 2.0.0
     */
    private function calculate_class_shipping_cost(int $class_id, array $items): float
    {
        $total_quantity = array_sum(array_column($items, 'quantity'));
        if ($total_quantity === 0) {
            return 0.0;
        }

        // Find the most cost-effective packaging solution
        $best_cost = PHP_FLOAT_MAX;
        
        foreach ($this->package_types as $package_id => $package_data) {
            if (!$this->is_package_enabled_for_class($class_id, $package_id)) {
                continue;
            }

            $cost = $this->calculate_cost_for_package_type($class_id, $package_id, $total_quantity);
            if ($cost < $best_cost) {
                $best_cost = $cost;
            }
        }

        return $best_cost !== PHP_FLOAT_MAX ? $best_cost : 0.0;
    }

    /**
     * Check if a package type is enabled for a shipping class
     *
     * @param int $class_id Shipping class ID
     * @param string $package_id Package type ID
     * @return bool Whether the package is enabled
     * @since 2.0.0
     */
    private function is_package_enabled_for_class(int $class_id, string $package_id): bool
    {
        return $this->get_option("{$class_id}_{$package_id}_enabled", 'yes') === 'yes';
    }

    /**
     * Calculate cost for a specific package type
     *
     * @param int $class_id Shipping class ID
     * @param string $package_id Package type ID
     * @param int $total_quantity Total quantity to ship
     * @return float Total cost for this package type
     * @since 2.0.0
     */
    private function calculate_cost_for_package_type(int $class_id, string $package_id, int $total_quantity): float
    {
        $max_items = (int) $this->get_option("{$class_id}_{$package_id}_max_items", '5');
        $package_rate = (float) $this->get_option("{$package_id}_rate", $this->package_types[$package_id]['default_rate']);

        if ($max_items <= 0) {
            return PHP_FLOAT_MAX; // Invalid configuration
        }

        $required_packages = (int) ceil($total_quantity / $max_items);
        
        return $required_packages * $package_rate;
    }

    /**
     * Validate form fields
     *
     * @param string $key Field key
     * @param string $value Field value
     * @return string Validated value
     * @since 2.0.0
     */
    public function validate_price_field($key, $value): string
    {
        $value = wc_format_decimal(trim(stripslashes($value)));
        return $value < 0 ? '0' : $value;
    }

    /**
     * Validate number field
     *
     * @param string $key Field key
     * @param string $value Field value
     * @return string Validated value
     * @since 2.0.0
     */
    public function validate_number_field($key, $value): string
    {
        $value = absint($value);
        return $value < 1 ? '1' : (string) $value;
    }

    /**
     * Check if the shipping method is available
     *
     * @param array $package Package data
     * @return bool Whether the method is available
     * @since 2.0.0
     */
    public function is_available($package): bool
    {
        if (!parent::is_available($package)) {
            return false;
        }

        // Check if WooCommerce is properly loaded
        if (!function_exists('WC') || !WC()->shipping) {
            return false;
        }

        return true;
    }
}