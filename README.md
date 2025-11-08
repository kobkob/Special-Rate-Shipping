# Special Rate Shipping - WordPress Plugin

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-8.0%2B-purple.svg)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL--2.0%2B-green.svg)](LICENSE)

A modern WooCommerce extension providing intelligent shipping rates based on product types and quantities with sophisticated packaging optimization algorithms.

## 🚀 Features

- **Intelligent Package Optimization**: Automatically selects the most cost-effective combination of package types
- **Flexible Shipping Classes**: Configure different packaging rules for various product types
- **Three Package Types**: Small, Medium, and Big boxes with customizable rates and item limits
- **Modern Architecture**: Built with PHP 8.1+, proper namespacing, and current WordPress standards
- **Real-time Calculation**: Dynamic shipping cost calculation based on cart contents
- **Admin Interface**: Intuitive settings with real-time validation and testing tools
- **Debug Support**: Built-in debugging tools for development and troubleshooting
- **Mobile Responsive**: Fully responsive admin interface

## 📋 Requirements

- **WordPress**: 6.0 or higher
- **WooCommerce**: 8.0 or higher
- **PHP**: 8.1 or higher
- **Node.js**: 16.0+ (for development)
- **Composer**: 2.0+ (for development)

## 🔧 Installation

### From WordPress Admin

1. Upload the plugin files to `/wp-content/plugins/special-rate-shipping/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to WooCommerce > Settings > Shipping > Special Rate Shipping to configure

### For Development

```bash
# Clone the repository
git clone https://github.com/kobkob/special-rate-shipping.git
cd special-rate-shipping

# Install PHP dependencies
composer install

# Install Node.js dependencies  
npm install

# Build assets for development
npm run build:dev

# Build assets for production
npm run build
```

## ⚙️ Configuration

### Basic Setup

1. Go to **WooCommerce > Settings > Shipping**
2. Click on a shipping zone or create a new one
3. Add **Special Rate Shipping** method
4. Configure package types and rates:
   - **Small Box**: Default rate $6.35
   - **Medium Box**: Default rate $9.35  
   - **Big Box**: Default rate $13.35

### Advanced Configuration

#### Shipping Classes

Configure how many items of each shipping class can fit in different package types:

```php
// Example: Electronics shipping class
Small Box: Max 3 items
Medium Box: Max 6 items  
Big Box: Max 12 items
```

The plugin automatically calculates the most cost-effective packaging solution.

#### Package Optimization Algorithm

The plugin uses an intelligent algorithm that:
1. Groups cart items by shipping class
2. Calculates packaging requirements for each group
3. Evaluates all possible package combinations
4. Selects the most cost-effective solution
5. Applies the total shipping cost

## 🛠 Development

### Project Structure

```
special-rate-shipping/
├── src/                          # PHP source files (PSR-4 autoloaded)
│   ├── Plugin.php               # Main plugin class
│   ├── Admin/                   # Admin functionality
│   ├── Frontend/                # Frontend functionality  
│   ├── Shipping/                # Shipping method implementation
│   └── Assets/                  # Asset management
├── assets/
│   ├── src/                     # Source assets
│   │   ├── js/                  # JavaScript source files
│   │   └── scss/                # Sass stylesheets
│   └── dist/                    # Compiled assets (generated)
├── tests/                       # PHPUnit tests
├── vendor/                      # Composer dependencies
├── node_modules/                # NPM dependencies
└── languages/                   # Translation files
```

### Build Commands

```bash
# Development
npm run dev          # Build and watch for changes
npm run build:dev    # Build development version
npm run build        # Build production version

# Code Quality
npm run lint         # Lint JS and CSS
npm run lint:js      # Lint JavaScript only
npm run lint:css     # Lint CSS only
npm run format       # Format code with Prettier

# Testing
npm run test         # Run JavaScript tests
composer test        # Run PHP tests
composer phpcs       # PHP CodeSniffer
composer phpstan     # Static analysis
```

### PHP Standards

The plugin follows:
- **PSR-4** autoloading
- **WordPress Coding Standards**
- **PHP 8.1+** features (typed properties, constructor promotion)
- **Modern PHP practices** (declare strict_types, proper error handling)

### JavaScript Standards

- **ES6+** syntax with Babel transpilation
- **WordPress ESLint** configuration
- **jQuery** for WordPress compatibility
- **Modern async/await** patterns

## 🧪 Testing

### PHP Tests

```bash
# Run all tests
composer test

# Run specific test suite
./vendor/bin/phpunit tests/Unit/
./vendor/bin/phpunit tests/Integration/

# Generate coverage report
./vendor/bin/phpunit --coverage-html tests/coverage/
```

### JavaScript Tests

```bash
# Run Jest tests
npm run test

# Watch mode
npm run test:watch
```

## 📊 Performance

- **Optimized Calculations**: Efficient packaging algorithms with O(n) complexity
- **Smart Caching**: Shipping calculations are cached to improve performance
- **Minimal Database Queries**: Uses WordPress transients for temporary data
- **Asset Optimization**: Minified CSS/JS with webpack optimization

## 🌐 Internationalization

The plugin is translation-ready with:
- **Text Domain**: `special-rate-shipping`
- **POT File**: Generated automatically from source code
- **Languages Folder**: `/languages/` for translation files

To contribute translations, use tools like Poedit or Loco Translate.

## 🐛 Debugging

### Enable Debug Mode

Add to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Debug Features

- **Admin Meta Boxes**: Show shipping calculation details on orders
- **Console Logging**: JavaScript debug information
- **Error Logging**: PHP errors logged to debug.log
- **Test Tools**: Built-in shipping calculation testing

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Make changes following coding standards
4. Run tests: `composer test && npm test`
5. Commit changes: `git commit -am 'Add amazing feature'`
6. Push to branch: `git push origin feature/amazing-feature`
7. Open a Pull Request

### Code Style

Please follow:
- [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- [WordPress JavaScript Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/javascript/)

## 📝 Changelog

### 2.0.0 (2025-01-18)

**Major Rewrite - Modern WordPress Plugin**

- 🔄 **Complete modernization** with PHP 8.1+ and current WordPress standards
- 🏗️ **New architecture** using proper namespacing and PSR-4 autoloading
- 🎨 **Modern build system** replacing Grunt with Webpack
- 🧪 **Testing framework** with PHPUnit and Jest
- 🔒 **Enhanced security** with proper sanitization and nonce verification
- 📱 **Responsive design** for mobile-friendly admin interface
- ⚡ **Performance improvements** with optimized algorithms
- 🌐 **Better internationalization** support
- 🐛 **Debugging tools** for development and troubleshooting

### 1.0.0 (2012-12-13)

- Initial release

## 📄 License

This plugin is licensed under the [GPL v2 or later](LICENSE).

## 👥 Credits

**Created by**: [Kobkob LLC](https://www.kobkob.org/)  
**Author**: Monsenhor Ricardo Filipo <filipo@kobkob.org>  
**Modernized**: 2025

Special thanks to the WordPress and WooCommerce communities for their excellent documentation and best practices.

---

**Made with ❤️ for the WordPress community**