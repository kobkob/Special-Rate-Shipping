<?php

declare(strict_types=1);

namespace KobKob\SpecialRateShipping\Assets;

/**
 * Asset Manager class
 *
 * @package KobKob\SpecialRateShipping\Assets
 * @since 2.0.0
 */
class AssetManager
{
    /**
     * Asset version for cache busting
     *
     * @var string
     */
    private string $version;

    /**
     * Asset URL base
     *
     * @var string
     */
    private string $asset_url;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->version = SPECIAL_RATE_SHIPPING_VERSION;
        $this->asset_url = SPECIAL_RATE_SHIPPING_PLUGIN_URL . 'assets/';
    }

    /**
     * Initialize asset management
     *
     * @return void
     * @since 2.0.0
     */
    public function init(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    /**
     * Enqueue frontend assets
     *
     * @return void
     * @since 2.0.0
     */
    public function enqueue_frontend_assets(): void
    {
        // Only load on WooCommerce pages
        if (!$this->is_woocommerce_page()) {
            return;
        }

        $this->enqueue_style(
            'special-rate-shipping-frontend',
            'dist/css/frontend-style.css',
            [],
            'Frontend styles for Special Rate Shipping'
        );

        $this->enqueue_script(
            'special-rate-shipping-frontend',
            'dist/js/frontend.js',
            ['jquery'],
            'Frontend scripts for Special Rate Shipping'
        );
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     * @return void
     * @since 2.0.0
     */
    public function enqueue_admin_assets(string $hook): void
    {
        // Only load on WooCommerce settings and plugin pages
        if (!$this->is_woocommerce_admin_page($hook)) {
            return;
        }

        $this->enqueue_style(
            'special-rate-shipping-admin',
            'dist/css/admin-style.css',
            [],
            'Admin styles for Special Rate Shipping'
        );

        $this->enqueue_script(
            'special-rate-shipping-admin',
            'dist/js/admin.js',
            ['jquery'],
            'Admin scripts for Special Rate Shipping'
        );

        // Enqueue settings script if on settings page
        if ($this->is_plugin_settings_page($hook)) {
            $this->enqueue_script(
                'special-rate-shipping-settings',
                'dist/js/settings.js',
                ['jquery', 'wp-color-picker'],
                'Settings scripts for Special Rate Shipping'
            );

            // Enqueue WordPress color picker
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_media();
        }
    }

    /**
     * Enqueue a stylesheet
     *
     * @param string $handle Script handle
     * @param string $src Source path relative to assets directory
     * @param array $deps Dependencies
     * @param string $description Description for debugging
     * @return void
     * @since 2.0.0
     */
    private function enqueue_style(string $handle, string $src, array $deps = [], string $description = ''): void
    {
        $file_path = SPECIAL_RATE_SHIPPING_PLUGIN_DIR . 'assets/' . $src;
        $file_url = $this->asset_url . $src;

        // Fallback to non-dist version if dist doesn't exist
        if (!file_exists($file_path)) {
            $fallback_src = str_replace('dist/', '', $src);
            $fallback_path = SPECIAL_RATE_SHIPPING_PLUGIN_DIR . 'assets/' . $fallback_src;
            
            if (file_exists($fallback_path)) {
                $file_url = $this->asset_url . $fallback_src;
            } else {
                return; // Skip if no file found
            }
        }

        wp_enqueue_style($handle, $file_url, $deps, $this->version);

        // Add description as inline comment for debugging
        if ($description && WP_DEBUG) {
            wp_add_inline_style($handle, "/* {$description} */");
        }
    }

    /**
     * Enqueue a script
     *
     * @param string $handle Script handle
     * @param string $src Source path relative to assets directory
     * @param array $deps Dependencies
     * @param string $description Description for debugging
     * @return void
     * @since 2.0.0
     */
    private function enqueue_script(string $handle, string $src, array $deps = [], string $description = ''): void
    {
        $file_path = SPECIAL_RATE_SHIPPING_PLUGIN_DIR . 'assets/' . $src;
        $file_url = $this->asset_url . $src;

        // Fallback to non-dist version if dist doesn't exist
        if (!file_exists($file_path)) {
            $fallback_src = str_replace('dist/js/', 'js/', $src);
            $fallback_path = SPECIAL_RATE_SHIPPING_PLUGIN_DIR . 'assets/' . $fallback_src;
            
            if (file_exists($fallback_path)) {
                $file_url = $this->asset_url . $fallback_src;
            } else {
                return; // Skip if no file found
            }
        }

        wp_enqueue_script($handle, $file_url, $deps, $this->version, true);

        // Add localized data for scripts
        if (strpos($handle, 'admin') !== false || strpos($handle, 'settings') !== false) {
            wp_localize_script($handle, 'specialRateShipping', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('special_rate_shipping_nonce'),
                'strings' => [
                    'error' => __('An error occurred. Please try again.', 'special-rate-shipping'),
                    'success' => __('Settings saved successfully.', 'special-rate-shipping'),
                ],
            ]);
        }
    }

    /**
     * Check if current page is a WooCommerce page
     *
     * @return bool
     * @since 2.0.0
     */
    private function is_woocommerce_page(): bool
    {
        if (!function_exists('is_woocommerce')) {
            return false;
        }

        return is_woocommerce() || is_cart() || is_checkout() || is_account_page();
    }

    /**
     * Check if current admin page is WooCommerce related
     *
     * @param string $hook Current page hook
     * @return bool
     * @since 2.0.0
     */
    private function is_woocommerce_admin_page(string $hook): bool
    {
        $wc_pages = [
            'woocommerce_page_wc-settings',
            'woocommerce_page_wc-status',
            'woocommerce_page_wc-addons',
        ];

        return in_array($hook, $wc_pages, true) || 
               strpos($hook, 'woocommerce') !== false ||
               (isset($_GET['page']) && strpos($_GET['page'], 'wc-') !== false);
    }

    /**
     * Check if current page is plugin settings page
     *
     * @param string $hook Current page hook
     * @return bool
     * @since 2.0.0
     */
    private function is_plugin_settings_page(string $hook): bool
    {
        return $hook === 'woocommerce_page_wc-settings' &&
               isset($_GET['tab']) && $_GET['tab'] === 'shipping' &&
               isset($_GET['section']) && $_GET['section'] === 'special_rate_shipping_method';
    }

    /**
     * Get asset URL
     *
     * @param string $path Asset path
     * @return string Full asset URL
     * @since 2.0.0
     */
    public function get_asset_url(string $path): string
    {
        return $this->asset_url . ltrim($path, '/');
    }

    /**
     * Get asset version
     *
     * @return string
     * @since 2.0.0
     */
    public function get_version(): string
    {
        return $this->version;
    }
}