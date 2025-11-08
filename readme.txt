=== Special Rate Shipping ===
Contributors: kobkob
Tags: woocommerce, shipping, ecommerce, rates, packages, optimization
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.1
Stable tag: 2.0.0
WC requires at least: 8.0
WC tested up to: 8.5
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Intelligent WooCommerce shipping rates with automatic package optimization for Small, Medium, and Big boxes.

== Description ==

Special Rate Shipping provides intelligent shipping calculations for WooCommerce with sophisticated packaging optimization algorithms. The plugin automatically selects the most cost-effective combination of package types based on your products and shipping classes.

**Key Features:**

* **Intelligent Package Optimization**: Automatically calculates the most cost-effective packaging solution
* **Three Package Types**: Small, Medium, and Big boxes with customizable rates and item limits
* **Flexible Shipping Classes**: Configure different packaging rules for various product types
* **Real-time Calculation**: Dynamic shipping cost calculation based on cart contents
* **Modern Architecture**: Built with PHP 8.1+, proper namespacing, and current WordPress standards
* **Mobile Responsive**: Fully responsive admin interface
* **Debug Support**: Built-in debugging tools for development and troubleshooting

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/special-rate-shipping/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to WooCommerce > Settings > Shipping > Special Rate Shipping to configure
4. Set up your package types and rates:
   - Small Box: Default rate $6.35
   - Medium Box: Default rate $9.35
   - Big Box: Default rate $13.35

== Requirements ==

* WordPress 6.0 or higher
* WooCommerce 8.0 or higher
* PHP 8.1 or higher

== Frequently Asked Questions ==

= How does the package optimization work? =

The plugin groups cart items by shipping class, calculates packaging requirements for each group, evaluates all possible package combinations, and selects the most cost-effective solution.

= Can I customize the package types? =

Yes, you can configure custom rates and item limits for Small, Medium, and Big package types through the WooCommerce shipping settings.

= Is it compatible with other shipping methods? =

Yes, Special Rate Shipping works alongside other WooCommerce shipping methods and can be enabled per shipping zone.

== Changelog ==

= 2.0.0 (2025-01-18) =

**Major Rewrite - Modern WordPress Plugin**

* Complete modernization with PHP 8.1+ and current WordPress standards
* New architecture using proper namespacing and PSR-4 autoloading
* Modern build system replacing Grunt with Webpack
* Testing framework with PHPUnit and Jest
* Enhanced security with proper sanitization and nonce verification
* Responsive design for mobile-friendly admin interface
* Performance improvements with optimized algorithms
* Better internationalization support
* Debugging tools for development and troubleshooting

= 1.0.0 (2012-12-13) =
* Initial release

== Upgrade Notice ==

= 2.0.0 =
Major update with modern architecture and enhanced features. Requires PHP 8.1+ and WordPress 6.0+.
