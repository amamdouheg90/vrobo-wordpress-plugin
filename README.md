# Vrobo

**Contributors:** vrobo  
**Tags:** orders, automation, api, integration, ecommerce  
**Requires at least:** 5.0  
**Tested up to:** 6.8  
**Requires PHP:** 7.4  
**Stable tag:** 2.2.1  
**License:** GPLv2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

Custom order management plugin with API integration and automation for e-commerce stores.

## Description

Vrobo is a powerful plugin that enables seamless integration between your e-commerce store and external automation systems. It provides comprehensive order management, webhook notifications, and API endpoints for external system integration.

### Features

- **Order Automation**: Automatic webhook notifications for new orders and cancellations
- **Custom Order Statuses**: Confirmed, Support, and Unclear statuses for better order management  
- **API Integration**: RESTful API endpoints with secure authentication
- **External API Support**: Update orders from external systems via API
- **Advanced Search**: Search orders by customer, email, or order number
- **HPOS Compatible**: Full support for High-Performance Order Storage
- **Comprehensive Logging**: Detailed logging for debugging and monitoring
- **COD Filtering**: Option to send only Cash on Delivery orders to webhook
- **Custom Database**: Efficient custom database tables for order tracking

### API Endpoints

The plugin provides secure API endpoints for external integration:

- **Complete Order Update**: Update both plugin database and e-commerce order
- **Status Update**: Change order status  
- **Add Notes**: Add admin or customer notes to orders
- **Bulk Operations**: Process multiple orders simultaneously

## Installation

1. Upload the plugin files to `/wp-content/plugins/vrobo/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Configure the plugin via your admin settings
4. Add your API key and webhook URL
5. Test the webhook connection

## Frequently Asked Questions

### Does this plugin require an e-commerce plugin?

Yes, this plugin is designed to work with e-commerce platforms like WooCommerce.

### Is this plugin compatible with HPOS?

Yes, the plugin is fully compatible with High-Performance Order Storage systems.

### Can I use custom order statuses?

Yes, the plugin adds three custom order statuses: Confirmed, Support, and Unclear.

## Screenshots

1. Main orders management interface
2. Plugin settings page
3. Custom order statuses
4. API integration examples

## Changelog

### 2.2.1
- Removed WooCommerce branding from plugin name and descriptions
- Fixed admin interface display for tags, notes, and last action
- Improved API response handling
- Enhanced security and validation
- WordPress.org compliance updates

### 2.2.0  
- Added external API endpoints for remote system integration
- Implemented comprehensive order update methods
- Added support for tags, notes, and last action tracking
- Enhanced webhook duplicate prevention

### 2.1.0
- Added manual search functionality with debounced input
- Implemented custom order statuses (Confirmed, Support, Unclear)
- Added HPOS compatibility
- Enhanced error handling and logging

### 2.0.0
- Complete rewrite for improved performance
- Added REST API endpoints
- Implemented webhook integration
- Added custom database tables

### 1.0.0
- Initial release

## License

This plugin is licensed under the GPLv2 or later license. You can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation.

## Support

For support and documentation, please visit our website or contact our support team. 