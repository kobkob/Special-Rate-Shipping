<?php

declare(strict_types=1);

namespace KobKob\SpecialRateShipping\Frontend;

/**
 * Frontend Manager class
 *
 * @package KobKob\SpecialRateShipping\Frontend
 * @since 2.0.0
 */
class FrontendManager
{
    /**
     * Initialize frontend functionality
     *
     * @return void
     * @since 2.0.0
     */
    public function init(): void
    {
        // Add shipping method information to checkout
        add_action('woocommerce_review_order_after_shipping', [$this, 'display_shipping_info']);

        // Add cart totals information
        add_action('woocommerce_cart_totals_after_shipping', [$this, 'display_cart_shipping_info']);

        // Handle AJAX requests from frontend
        add_action('wp_ajax_nopriv_special_rate_shipping_estimate', [$this, 'handle_estimate_ajax']);
        add_action('wp_ajax_special_rate_shipping_estimate', [$this, 'handle_estimate_ajax']);
    }

    /**
     * Display additional shipping information on checkout
     *
     * @return void
     * @since 2.0.0
     */
    public function display_shipping_info(): void
    {
        if (!WC()->session || !WC()->cart) {
            return;
        }

        $chosen_methods = WC()->session->get('chosen_shipping_methods');
        if (empty($chosen_methods)) {
            return;
        }

        foreach ($chosen_methods as $method_id) {
            if (strpos($method_id, 'special_rate_shipping_method') !== false) {
                $this->render_shipping_details();
                break;
            }
        }
    }

    /**
     * Display shipping information in cart
     *
     * @return void
     * @since 2.0.0
     */
    public function display_cart_shipping_info(): void
    {
        if (!WC()->cart) {
            return;
        }

        $packages = WC()->cart->get_shipping_packages();
        if (empty($packages)) {
            return;
        }

        foreach ($packages as $package) {
            if (!empty($package['contents'])) {
                $this->render_cart_shipping_details($package);
                break;
            }
        }
    }

    /**
     * Render shipping details
     *
     * @return void
     * @since 2.0.0
     */
    private function render_shipping_details(): void
    {
        if (!WC()->cart || WC()->cart->is_empty()) {
            return;
        }

        echo '<div class="special-rate-shipping-info" style="margin-top: 10px; font-size: 0.9em; color: #666;">';
        echo '<p><em>' . esc_html__('Shipping rates calculated based on product types and intelligent packaging optimization.', 'special-rate-shipping') . '</em></p>';
        echo '</div>';
    }

    /**
     * Render cart shipping details
     *
     * @param array $package Shipping package
     * @return void
     * @since 2.0.0
     */
    private function render_cart_shipping_details(array $package): void
    {
        $item_count = array_sum(array_column($package['contents'], 'quantity'));
        
        if ($item_count > 0) {
            echo '<div class="special-rate-shipping-cart-info" style="margin-top: 5px; font-size: 0.85em;">';
            echo '<p>' . sprintf(
                /* translators: %d: Number of items */
                esc_html__('Shipping calculated for %d items with optimized packaging.', 'special-rate-shipping'),
                $item_count
            ) . '</p>';
            echo '</div>';
        }
    }

    /**
     * Handle shipping estimate AJAX request
     *
     * @return void
     * @since 2.0.0
     */
    public function handle_estimate_ajax(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'special_rate_shipping_nonce')) {
            wp_send_json_error(['message' => __('Invalid security token.', 'special-rate-shipping')]);
        }

        // Sanitize input
        $country = sanitize_text_field($_POST['country'] ?? '');
        $state = sanitize_text_field($_POST['state'] ?? '');
        $postcode = sanitize_text_field($_POST['postcode'] ?? '');

        if (empty($country)) {
            wp_send_json_error(['message' => __('Country is required.', 'special-rate-shipping')]);
        }

        try {
            $estimate = $this->calculate_shipping_estimate($country, $state, $postcode);
            wp_send_json_success($estimate);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Calculate shipping estimate
     *
     * @param string $country Country code
     * @param string $state State code
     * @param string $postcode Postal code
     * @return array Shipping estimate data
     * @since 2.0.0
     */
    private function calculate_shipping_estimate(string $country, string $state, string $postcode): array
    {
        if (!WC()->cart || WC()->cart->is_empty()) {
            throw new \Exception(__('Cart is empty.', 'special-rate-shipping'));
        }

        // Get cart contents
        $cart_contents = WC()->cart->get_cart();
        if (empty($cart_contents)) {
            throw new \Exception(__('No items in cart.', 'special-rate-shipping'));
        }

        // Create mock package
        $package = [
            'contents' => $cart_contents,
            'destination' => [
                'country' => $country,
                'state' => $state,
                'postcode' => $postcode
            ]
        ];

        // Try to calculate with our shipping method
        if (class_exists('KobKob\\SpecialRateShipping\\Shipping\\SpecialRateShippingMethod')) {
            $shipping_method = new \KobKob\SpecialRateShipping\Shipping\SpecialRateShippingMethod();
            
            // Calculate rates
            $rates = [];
            ob_start();
            $shipping_method->calculate_shipping($package);
            $output = ob_get_clean();

            // Get the calculated rates
            $method_rates = $shipping_method->rates ?? [];
            
            foreach ($method_rates as $rate) {
                $rates[] = [
                    'id' => $rate->id,
                    'label' => $rate->label,
                    'cost' => wc_price($rate->cost),
                    'cost_raw' => $rate->cost
                ];
            }

            if (!empty($rates)) {
                return [
                    'rates' => $rates,
                    'message' => __('Shipping rates calculated successfully.', 'special-rate-shipping')
                ];
            }
        }

        return [
            'rates' => [],
            'message' => __('No shipping rates available for this destination.', 'special-rate-shipping')
        ];
    }

    /**
     * Add shipping calculator widget
     *
     * @return void
     * @since 2.0.0
     */
    public function add_shipping_calculator(): void
    {
        if (!is_cart() && !is_checkout()) {
            return;
        }

        ?>
        <div id="special-rate-shipping-calculator" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border: 1px solid #ddd;">
            <h4><?php esc_html_e('Estimate Shipping', 'special-rate-shipping'); ?></h4>
            <form id="shipping-estimate-form">
                <p>
                    <label for="estimate-country"><?php esc_html_e('Country:', 'special-rate-shipping'); ?></label>
                    <select id="estimate-country" name="country" required>
                        <option value=""><?php esc_html_e('Select Country', 'special-rate-shipping'); ?></option>
                        <?php
                        $countries = WC()->countries->get_allowed_countries();
                        foreach ($countries as $code => $name) {
                            echo '<option value="' . esc_attr($code) . '">' . esc_html($name) . '</option>';
                        }
                        ?>
                    </select>
                </p>
                <p>
                    <label for="estimate-state"><?php esc_html_e('State/Province:', 'special-rate-shipping'); ?></label>
                    <input type="text" id="estimate-state" name="state" />
                </p>
                <p>
                    <label for="estimate-postcode"><?php esc_html_e('Postcode:', 'special-rate-shipping'); ?></label>
                    <input type="text" id="estimate-postcode" name="postcode" />
                </p>
                <p>
                    <button type="submit" class="button"><?php esc_html_e('Calculate Shipping', 'special-rate-shipping'); ?></button>
                </p>
            </form>
            <div id="shipping-estimate-results"></div>
        </div>
        <?php
    }
}