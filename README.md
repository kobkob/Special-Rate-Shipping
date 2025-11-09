# Special Rate Shipping - WordPress Plugin

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-8.0%2B-purple.svg)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://php.net/)
[![USPS](https://img.shields.io/badge/USPS_API-Integrated-orange.svg)](https://developer.usps.com/)
[![License](https://img.shields.io/badge/License-GPL--2.0%2B-green.svg)](LICENSE)

**The most advanced WooCommerce shipping solution with intelligent package optimization, USPS API integration, and automated pouch management system.**

## ✨ **Version 2.0 - Major Release Features**

### 🎯 **Intelligent Package Optimization Engine**
- **Advanced Algorithm**: Finds the most cost-effective package combinations using sophisticated optimization
- **5 Package Types**: Small Box, Medium Box, Big Box, Envelope, Flat Rate Box
- **Mixed Packaging**: Automatically combines different package types for optimal pricing
- **Weight & Dimension Aware**: Considers product weights and dimensions for realistic packaging
- **Memory Efficient**: Uses PHP generators for handling large product combinations

### 📦 **Automated Pouch Management System**
- **Auto-Creation**: Automatically creates shipping pouches from WooCommerce orders
- **Visual Interface**: Professional admin interface with Bootstrap styling and visual package breakdown
- **Barcode Generation**: Automatic barcode creation with QR code integration
- **Status Tracking**: Complete pouch lifecycle management (New → Packed → Shipped → Delivered)
- **Order Integration**: Bi-directional linking between orders and pouches

### 🚚 **USPS API Integration**
- **Real-time Rates**: Live USPS rates with intelligent fallbacks
- **Label Generation**: Professional USPS shipping labels (PDF download)
- **Tracking Integration**: Automatic tracking number assignment and display
- **OAuth2 Authentication**: Secure, modern USPS API authentication
- **Sandbox/Production**: Full development and production environment support

### 🎨 **Modern Admin Interface**
- **Bootstrap UI**: Professional, responsive interface with modern styling
- **Visual Package Breakdown**: Interactive displays showing optimization results
- **Dashboard Analytics**: Comprehensive statistics and package optimization insights
- **AJAX Operations**: Smooth, real-time operations without page reloads
- **Error Handling**: Graceful error handling with user-friendly messages

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

## 🚀 **Quick Start Guide**

### 1️⃣ **Basic Setup** (5 minutes)

1. **Activate Plugin**: Go to **Plugins > Installed Plugins** and activate Special Rate Shipping
2. **Configure USPS API** (optional): Navigate to **Settings > Special Rate Shipping**
   ```
   USPS Client ID: [Your USPS API Client ID]
   USPS Client Secret: [Your USPS API Secret]
   Environment: Sandbox (for testing) or Production
   ```
3. **Add Shipping Method**: Go to **WooCommerce > Settings > Shipping**
   - Select a shipping zone or create new one
   - Add **"Special Rate Shipping (Optimized)"** method
   - Configure package rates and limits

### 2️⃣ **Package Configuration**

| Package Type | Default Rate | Max Weight | Dimensions (L×W×H) | Best For |
|--------------|--------------|------------|-------------------|----------|
| **Small Box** | $6.35 | 2.0 lbs | 8×6×4 inches | Small items, accessories |
| **Medium Box** | $9.35 | 5.0 lbs | 12×8×6 inches | Standard products |
| **Big Box** | $13.35 | 10.0 lbs | 16×12×8 inches | Large items, multiple products |
| **Envelope** | $4.50 | 0.5 lbs | 12×9×1 inches | Documents, flat items |
| **Flat Rate** | $8.45 | 70.0 lbs | 12×8×6 inches | Heavy items, bulk shipping |

### 3️⃣ **Advanced Features**

#### 🧠 **Intelligent Optimization**
The system automatically:
- ✅ Analyzes product weights and dimensions
- ✅ Groups items by shipping class
- ✅ Tests all possible package combinations
- ✅ Selects the most cost-effective solution
- ✅ Provides real-time USPS rates (if configured)

#### 📦 **Automated Pouch Creation**
When customers complete orders:
- ✅ Pouches are automatically created
- ✅ Products are optimally distributed across packages
- ✅ Barcodes are generated for tracking
- ✅ Shipping addresses are parsed and validated
- ✅ Integration with WooCommerce order management

#### 🎨 **Professional Admin Interface**
- ✅ Bootstrap-powered responsive design
- ✅ Visual package breakdown with charts
- ✅ Real-time optimization statistics
- ✅ AJAX-powered smooth operations
- ✅ Comprehensive error handling

## ⚙️ **Configuration Options**

### 🗺️ **USPS API Configuration**

1. **Get USPS Credentials**: Register at [USPS Developer Portal](https://developer.usps.com)
2. **Plugin Settings**: Navigate to **Settings > Special Rate Shipping > USPS API Configuration**
3. **Configure**:
   ```
   API Key (Client ID): Your USPS application Client ID
   API Secret: Your USPS application Client Secret  
   Environment: 
     • Sandbox (api-cat.usps.com) - for testing
     • Production (api.usps.com) - for live transactions
   Debug Mode: Enable for troubleshooting
   ```

### 🏢 **Sender Information**
Configure your business address for shipping labels:
```
First Name: [Your first name]
Last Name: [Your last name] 
Company: [Your business name]
Address: [Complete business address]
City, State, ZIP: [Your location]
Phone: [Business phone number]
Email: [Contact email]
```

### 📦 **Shipping Class Configuration**

Customize package limits per shipping class:

```php
// Example: Electronics Shipping Class
Small Box: 3 items max, enabled
Medium Box: 6 items max, enabled  
Big Box: 12 items max, enabled
Envelope: Disabled
Flat Rate: 8 items max, enabled

// Example: Fragile Items Shipping Class
Small Box: 1 item max, enabled
Medium Box: 2 items max, enabled
Big Box: Disabled
Envelope: Disabled  
Flat Rate: 1 item max, enabled
```

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