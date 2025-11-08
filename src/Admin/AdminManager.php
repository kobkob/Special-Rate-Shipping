<?php

declare(strict_types=1);

namespace KobKob\SpecialRateShipping\Admin;

/**
 * Admin Manager class
 *
 * @package KobKob\SpecialRateShipping\Admin
 * @since 2.0.0
 */
class AdminManager
{
    /**
     * Initialize admin functionality
     *
     * @return void
     * @since 2.0.0
     */
    public function init(): void
    {
        // Add admin notices
        add_action('admin_notices', [$this, 'admin_notices']);

        // Add settings link to plugins page
        add_filter('plugin_action_links_' . SPECIAL_RATE_SHIPPING_PLUGIN_BASENAME, [$this, 'plugin_action_links']);

        // Handle admin AJAX requests
        add_action('wp_ajax_special_rate_shipping_test', [$this, 'handle_test_ajax']);
        
        // Add meta boxes for debugging (if WP_DEBUG is enabled)
        if (WP_DEBUG) {
            add_action('add_meta_boxes', [$this, 'add_debug_meta_boxes']);
        }
    }

    /**
     * Display admin notices
     *
     * @return void
     * @since 2.0.0
     */
    public function admin_notices(): void
    {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            $this->display_notice(
                __('Special Rate Shipping requires WooCommerce to be installed and active.', 'special-rate-shipping'),
                'error'
            );
            return;
        }

        // Check plugin version upgrade
        $stored_version = get_option('special_rate_shipping_version', '1.0.0');
        if (version_compare($stored_version, SPECIAL_RATE_SHIPPING_VERSION, '<')) {
            $this->display_notice(
                sprintf(
                    /* translators: %s: Plugin version */
                    __('Special Rate Shipping has been updated to version %s. Please check your shipping settings.', 'special-rate-shipping'),
                    SPECIAL_RATE_SHIPPING_VERSION
                ),
                'info'
            );
        }
    }

    /**
     * Display a notice
     *
     * @param string $message Notice message
     * @param string $type Notice type (error, warning, success, info)
     * @return void
     * @since 2.0.0
     */
    private function display_notice(string $message, string $type = 'info'): void
    {
        $class = 'notice notice-' . $type;
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    }

    /**
     * Add settings link to plugin action links
     *
     * @param array $links Existing action links
     * @return array Modified action links
     * @since 2.0.0
     */
    public function plugin_action_links(array $links): array
    {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=wc-settings&tab=shipping&section=special_rate_shipping_method'),
            esc_html__('Settings', 'special-rate-shipping')
        );

        array_unshift($links, $settings_link);

        return $links;
    }

    /**
     * Handle test AJAX request
     *
     * @return void
     * @since 2.0.0
     */
    public function handle_test_ajax(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'special_rate_shipping_nonce')) {
            wp_send_json_error(['message' => __('Invalid security token.', 'special-rate-shipping')]);
        }

        // Check capabilities
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'special-rate-shipping')]);
        }

        // Perform test calculation
        $result = $this->test_shipping_calculation();

        if ($result['success']) {
            wp_send_json_success($result['data']);
        } else {
            wp_send_json_error($result['data']);
        }
    }

    /**
     * Test shipping calculation
     *
     * @return array Test result
     * @since 2.0.0
     */
    private function test_shipping_calculation(): array
    {
        try {
            // Mock package data for testing
            $test_package = [
                'contents' => [
                    [
                        'product_id' => 1,
                        'quantity' => 2
                    ]
                ]
            ];

            // Test if shipping method can be instantiated
            if (class_exists('KobKob\\SpecialRateShipping\\Shipping\\SpecialRateShippingMethod')) {
                $shipping_method = new \KobKob\SpecialRateShipping\Shipping\SpecialRateShippingMethod();
                
                return [
                    'success' => true,
                    'data' => [
                        'message' => __('Shipping method is working correctly.', 'special-rate-shipping'),
                        'method_id' => $shipping_method->id,
                        'method_title' => $shipping_method->method_title
                    ]
                ];
            }

            return [
                'success' => false,
                'data' => ['message' => __('Shipping method class not found.', 'special-rate-shipping')]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => ['message' => $e->getMessage()]
            ];
        }
    }

    /**
     * Add debug meta boxes
     *
     * @return void
     * @since 2.0.0
     */
    public function add_debug_meta_boxes(): void
    {
        if (!WP_DEBUG) {
            return;
        }

        add_meta_box(
            'special-rate-shipping-debug',
            __('Special Rate Shipping Debug', 'special-rate-shipping'),
            [$this, 'render_debug_meta_box'],
            'shop_order',
            'side',
            'default'
        );
    }

    /**
     * Render debug meta box
     *
     * @param \WP_Post $post Post object
     * @return void
     * @since 2.0.0
     */
    public function render_debug_meta_box(\WP_Post $post): void
    {
        if (!WP_DEBUG) {
            return;
        }

        $order = wc_get_order($post->ID);
        if (!$order) {
            echo '<p>' . esc_html__('Invalid order.', 'special-rate-shipping') . '</p>';
            return;
        }

        $shipping_methods = $order->get_shipping_methods();
        $special_rate_methods = array_filter($shipping_methods, function($method) {
            return strpos($method->get_method_id(), 'special_rate_shipping') !== false;
        });

        if (empty($special_rate_methods)) {
            echo '<p>' . esc_html__('No Special Rate Shipping methods found in this order.', 'special-rate-shipping') . '</p>';
            return;
        }

        echo '<div style="font-family: monospace; font-size: 12px;">';
        echo '<h4>' . esc_html__('Shipping Debug Info:', 'special-rate-shipping') . '</h4>';
        
        foreach ($special_rate_methods as $method) {
            echo '<p><strong>' . esc_html__('Method:', 'special-rate-shipping') . '</strong> ' . esc_html($method->get_name()) . '</p>';
            echo '<p><strong>' . esc_html__('Cost:', 'special-rate-shipping') . '</strong> ' . wc_price($method->get_total()) . '</p>';
            
            $meta_data = $method->get_meta_data();
            if (!empty($meta_data)) {
                echo '<p><strong>' . esc_html__('Meta Data:', 'special-rate-shipping') . '</strong></p>';
                echo '<pre>' . esc_html(print_r($meta_data, true)) . '</pre>';
            }
        }
        echo '</div>';
    }
}