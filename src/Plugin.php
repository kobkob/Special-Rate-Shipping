<?php

declare(strict_types=1);

namespace KobKob\SpecialRateShipping;

use KobKob\SpecialRateShipping\Admin\AdminManager;
use KobKob\SpecialRateShipping\Frontend\FrontendManager;
use KobKob\SpecialRateShipping\Shipping\ShippingManager;
use KobKob\SpecialRateShipping\Assets\AssetManager;

/**
 * Main Plugin class
 *
 * @package KobKob\SpecialRateShipping
 * @since 2.0.0
 */
final class Plugin
{
    /**
     * Plugin version
     *
     * @var string
     */
    public const VERSION = SPECIAL_RATE_SHIPPING_VERSION;

    /**
     * Text domain
     *
     * @var string
     */
    public const TEXT_DOMAIN = 'special-rate-shipping';

    /**
     * Admin manager instance
     *
     * @var AdminManager|null
     */
    private ?AdminManager $admin_manager = null;

    /**
     * Frontend manager instance
     *
     * @var FrontendManager|null
     */
    private ?FrontendManager $frontend_manager = null;

    /**
     * Shipping manager instance
     *
     * @var ShippingManager|null
     */
    private ?ShippingManager $shipping_manager = null;

    /**
     * Asset manager instance
     *
     * @var AssetManager|null
     */
    private ?AssetManager $asset_manager = null;

    /**
     * Initialize the plugin
     *
     * @return void
     * @since 2.0.0
     */
    public function init(): void
    {
        // Load text domain
        add_action('plugins_loaded', [$this, 'load_textdomain']);

        // Initialize managers
        $this->init_managers();

        // Register activation/deactivation hooks
        register_activation_hook(SPECIAL_RATE_SHIPPING_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(SPECIAL_RATE_SHIPPING_PLUGIN_FILE, [$this, 'deactivate']);

        // Hook into WooCommerce init
        add_action('woocommerce_loaded', [$this, 'init_woocommerce_integration']);

        // Add plugin action links
        add_filter('plugin_action_links_' . SPECIAL_RATE_SHIPPING_PLUGIN_BASENAME, [$this, 'plugin_action_links']);
    }

    /**
     * Initialize managers
     *
     * @return void
     * @since 2.0.0
     */
    private function init_managers(): void
    {
        $this->asset_manager = new AssetManager();
        $this->asset_manager->init();

        if (is_admin()) {
            $this->admin_manager = new AdminManager();
            $this->admin_manager->init();
        } else {
            $this->frontend_manager = new FrontendManager();
            $this->frontend_manager->init();
        }
    }

    /**
     * Initialize WooCommerce integration
     *
     * @return void
     * @since 2.0.0
     */
    public function init_woocommerce_integration(): void
    {
        $this->shipping_manager = new ShippingManager();
        $this->shipping_manager->init();
    }

    /**
     * Load plugin text domain for translations
     *
     * @return void
     * @since 2.0.0
     */
    public function load_textdomain(): void
    {
        load_plugin_textdomain(
            self::TEXT_DOMAIN,
            false,
            dirname(SPECIAL_RATE_SHIPPING_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Plugin activation hook
     *
     * @return void
     * @since 2.0.0
     */
    public function activate(): void
    {
        // Check WooCommerce is active
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(SPECIAL_RATE_SHIPPING_PLUGIN_BASENAME);
            wp_die(
                esc_html__('Special Rate Shipping requires WooCommerce to be installed and active.', self::TEXT_DOMAIN),
                esc_html__('Plugin Activation Error', self::TEXT_DOMAIN),
                ['back_link' => true]
            );
        }

        // Store plugin version
        update_option('special_rate_shipping_version', self::VERSION);

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation hook
     *
     * @return void
     * @since 2.0.0
     */
    public function deactivate(): void
    {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Add plugin action links
     *
     * @param array $links Existing links
     * @return array Modified links
     * @since 2.0.0
     */
    public function plugin_action_links(array $links): array
    {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=wc-settings&tab=shipping&section=special_rate_shipping_method'),
            esc_html__('Settings', self::TEXT_DOMAIN)
        );

        array_unshift($links, $settings_link);

        return $links;
    }

    /**
     * Get plugin directory path
     *
     * @return string
     * @since 2.0.0
     */
    public function get_plugin_dir(): string
    {
        return SPECIAL_RATE_SHIPPING_PLUGIN_DIR;
    }

    /**
     * Get plugin URL
     *
     * @return string
     * @since 2.0.0
     */
    public function get_plugin_url(): string
    {
        return SPECIAL_RATE_SHIPPING_PLUGIN_URL;
    }

    /**
     * Get asset manager instance
     *
     * @return AssetManager|null
     * @since 2.0.0
     */
    public function get_asset_manager(): ?AssetManager
    {
        return $this->asset_manager;
    }

    /**
     * Get admin manager instance
     *
     * @return AdminManager|null
     * @since 2.0.0
     */
    public function get_admin_manager(): ?AdminManager
    {
        return $this->admin_manager;
    }

    /**
     * Get frontend manager instance
     *
     * @return FrontendManager|null
     * @since 2.0.0
     */
    public function get_frontend_manager(): ?FrontendManager
    {
        return $this->frontend_manager;
    }

    /**
     * Get shipping manager instance
     *
     * @return ShippingManager|null
     * @since 2.0.0
     */
    public function get_shipping_manager(): ?ShippingManager
    {
        return $this->shipping_manager;
    }
}