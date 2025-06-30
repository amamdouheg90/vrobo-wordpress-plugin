# Vrobo

**Contributors:** vrobo  
**Tags:** orders, automation, api, integration, ecommerce  
**Requires at least:** 5.0  
**Tested up to:** 6.8  
**Requires PHP:** 7.4  
**Stable tag:** 2.3.0  
**License:** GPLv2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

Custom order management plugin with API integration and automation for e-commerce stores.

## Description

Vrobo is a powerful plugin that enables seamless integration between your e-commerce store and external automation systems. It provides comprehensive order management, webhook notifications, and API endpoints for external system integration.

## External Services

This plugin connects to external Vrobo services to provide order automation and management functionality. 

### Vrobo Webhook Service

**What it does:** Sends order data to Vrobo's automation platform for processing and management.

**When data is sent:** 
- When new orders are created in WooCommerce
- When orders are cancelled 
- During API key validation and registration

**Data sent:**
- Order details (ID, customer name, email, status, total amount)
- Site domain and basic WordPress/WooCommerce version information
- API credentials for authentication

**Service provider:** Vrobo (https://vrobo.co)
- **Terms of Service:** https://vrobo.co/terms
- **Privacy Policy:** https://vrobo.co/privacy

**External endpoints used:**
- Order webhook: n8n.flq.me/webhook/ebb7aeaf-a7d0-40bf-825b-13cdfbe5418d
- Registration webhook: n8n.flq.me/webhook/f8f04b03-3592-47f1-9db5-478910137a64

This integration is required for the plugin's core functionality. No data is sent without user configuration of API credentials, and users can disable order transmission by not configuring the API key.

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

### 2.3.0
- Added complete WooCommerce order status color support
- Fixed status colors not appearing in the Status column
- Added status-completed and status-processing CSS classes
- All order statuses now display with proper colors

### 2.2.9
- Improved confirmed status color to use better green (#00a32a)
- Enhanced visual appearance of confirmed orders in the table

### 2.2.8
- Fixed Status column to show WooCommerce order status instead of webhook status
- Removed webhook status display from table interface
- Status column now correctly displays: completed, processing, pending, cancelled, etc.
- Cleaned up unused webhook status functions

### 2.2.7
- Removed Status column from orders table for cleaner interface
- Updated status color scheme to only include 4 relevant statuses:
  - Confirmed: Green colors
  - Cancelled: Red colors  
  - Pending: Gray colors
  - Support: Yellow/orange colors
- Removed unused status colors (completed, processing, refunded)

### 2.2.6
- Fixed search functionality by removing non-existent customer_phone column from queries
- Resolved database errors that were preventing search from working
- Search now properly works with existing database columns: customer_name, customer_email, order_id

### 2.2.5
- Fixed search functionality following WordPress database best practices
- Resolved LIMIT/OFFSET parameter issues in wpdb::prepare() statements
- Improved error reporting with detailed database error messages
- Enhanced query sanitization using absint() for integer values

### 2.2.4
- Removed dark mode support that was breaking the UI styling
- Fixed status filtering to use tags column instead of webhook status
- Added phone number search functionality to search queries
- Status filters now properly search for tags containing the status name
- Improved search to include customer phone numbers in all search operations

### 2.2.3
- Fixed status filter functionality with proper default case handling
- Added working view button that opens WooCommerce order edit page
- Completely modernized admin UI with contemporary design system
- Implemented CSS custom properties for consistent theming
- Added modern shadows, gradients, and smooth animations
- Improved responsive design for mobile devices
- Enhanced status indicators with better visual hierarchy
- Added dark mode support for better accessibility

### 2.2.2
- Added comprehensive documentation for external service usage
- Improved security documentation for external API endpoints
- Fixed JSON output to use wp_send_json_error instead of echo json_encode
- Enhanced API key authentication for webhook endpoints
- WordPress.org compliance improvements

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