<?php
/**
 * Order Handler Class
 * Handles WooCommerce order events and sends to external webhook
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vrobo_Order_Handler {
    
    private $webhook_url = 'https://n8n.flq.me/webhook/ebb7aeaf-a7d0-40bf-825b-13cdfbe5418d';
    private $api_key;
    
    public function __construct() {
        $this->api_key = get_option('vrobo_wc_api_key', '');
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Hook into order creation - compatible with HPOS
        add_action('woocommerce_new_order', array($this, 'handle_new_order'), 10, 2);
        
        // Hook only for order cancellation (not all status changes)
        add_action('woocommerce_order_status_cancelled', array($this, 'handle_order_cancelled'), 10, 2);
        
        // HPOS compatible hooks
        add_action('woocommerce_store_api_checkout_order_processed', array($this, 'handle_new_order_hpos'), 10, 1);
        
        // AJAX hooks
        add_action('wp_ajax_vrobo_request_cancel', array($this, 'ajax_request_cancel'));
        add_action('wp_ajax_vrobo_get_orders_table', array($this, 'ajax_get_orders_table'));
    }
    
    /**
     * Handle new order creation (HPOS compatible)
     */
    public function handle_new_order($order_id, $order = null) {
        if (!$order) {
            return;
        }
        
        // Check if webhook is enabled and API key is configured
        if (!$this->api_key || !$this->webhook_url || !get_option('vrobo_wc_api_validated', false)) {
            return;
        }
        
        // Check COD filter setting
        $only_cod = get_option('vrobo_wc_only_cod', false);
        if ($only_cod) {
            $payment_method = $order->get_payment_method();
            if ($payment_method !== 'cod') {
                return;
            }
        }
        
        // Store order in database
        $db_result = $this->store_order_in_db($order_id, $order);
        if (!$db_result) {
            return;
        }
        
        // Check for duplicate prevention
        $transient_key = 'vrobo_order_processed_' . $order_id;
        $already_processed = get_transient($transient_key);
        
        if ($already_processed) {
            return; // Skip if already processed within the last 5 minutes
        }
        
        // Set transient to prevent duplicates (5 minutes)
        set_transient($transient_key, true, 5 * MINUTE_IN_SECONDS);
        
        // Send to webhook
        $this->send_order_to_webhook($order_id, $order);
        
        // Update order status in database
        $this->update_order_status_in_db($order_id, $order->get_status());
    }
    
    /**
     * Handle new order from Store API (HPOS)
     */
    public function handle_new_order_hpos($order) {
        if ($order instanceof WC_Order) {
            $this->handle_new_order($order->get_id(), $order);
        }
    }
    
    /**
     * Handle order cancellations specifically
     */
    public function handle_order_cancelled($order_id, $order = null) {
        // Get order object if not provided
        if (!$order) {
            $order = wc_get_order($order_id);
        }
        
        if (!$order) {
            return;
        }
        
        // Update in custom database
        $this->update_order_status_in_db($order_id, 'cancelled');
        
        // Check if API key is configured and validated
        $api_key = get_option('vrobo_wc_api_key', '');
        $api_key_validated = get_option('vrobo_wc_api_key_validated', 0);
        
        if (empty($api_key) || !$api_key_validated) {
            return;
        }
        
        // Check if we should only send COD orders
        $exclude_paid_orders = get_option('vrobo_wc_exclude_paid_orders', 0);
        $payment_method = $order->get_payment_method();
        
        if ($exclude_paid_orders && $payment_method !== 'cod') {
            // Update webhook status to indicate it was skipped
            global $wpdb;
            $table_name = $wpdb->prefix . 'vrobo_orders';
            
            // Direct database query is necessary here as we're updating a custom plugin table
            // that has no WordPress API equivalent. This is the standard approach for plugin-specific data.
            // No caching used as this is a real-time webhook status update that must be immediate.
            $wpdb->update(
                $table_name,
                array(
                    'webhook_status' => 'skipped',
                    'webhook_response' => 'Non-COD order cancellation excluded from webhook (payment method: ' . $payment_method . ')'
                ),
                array('order_id' => $order_id),
                array('%s', '%s'),
                array('%d')
            );
            
            return;
        }
        
        // Send cancellation notification to webhook
        $this->send_cancellation_to_webhook($order_id, $order);
    }
    
    /**
     * Store order in custom database table (HPOS compatible)
     */
    private function store_order_in_db($order_id, $order) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'vrobo_orders';
        
        // Get order data using HPOS-compatible methods
        $customer_email = $order->get_billing_email();
        $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        $order_status = $order->get_status();
        $order_total = $order->get_total();
        
        // Check if order already exists to avoid duplicates
        // Direct database query is necessary for custom plugin table - no WordPress API equivalent
        $cache_key = 'vrobo_order_exists_' . $order_id;
        $existing = wp_cache_get($cache_key, 'vrobo_orders');
        
        if ($existing === false) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}vrobo_orders WHERE order_id = %d",
                $order_id
            ));
            // Cache the result for 5 minutes to reduce duplicate database queries
            wp_cache_set($cache_key, $existing, 'vrobo_orders', 5 * MINUTE_IN_SECONDS);
        }
        
        if ($existing) {
            return true; // Order already exists
        }
        
        // Direct database query is necessary for custom plugin table - no WordPress API equivalent
        // No caching used as this is a real-time order creation that must be immediate.
        $result = $wpdb->insert(
            $table_name,
            array(
                'order_id' => $order_id,
                'customer_email' => $customer_email,
                'customer_name' => $customer_name,
                'order_status' => $order_status,
                'order_total' => $order_total,
                'created_date' => current_time('mysql'),
                'webhook_status' => 'pending'
            ),
            array('%d', '%s', '%s', '%s', '%f', '%s', '%s')
        );
        
        // Clear cache after successful insert
        if ($result !== false) {
            wp_cache_delete($cache_key, 'vrobo_orders');
        }
        
        return $result !== false;
    }
    
    /**
     * Update order status in custom database
     */
    private function update_order_status_in_db($order_id, $status) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'vrobo_orders';
        
        // Direct database query is necessary for custom plugin table - no WordPress API equivalent
        // No caching used as this is a real-time order status update that must be immediate.
        $result = $wpdb->update(
            $table_name,
            array(
                'order_status' => $status,
                'updated_date' => current_time('mysql')
            ),
            array('order_id' => $order_id),
            array('%s', '%s'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Send order to external webhook
     */
    private function send_order_to_webhook($order_id, $order) {
        $webhook_data = array(
            'event' => 'order.created',
            'domain' => home_url(),
            'api_key' => $this->api_key,
            'timestamp' => current_time('c'),
            'order' => array(
                'id' => $order_id,
                'parent_id' => $order->get_parent_id(),
                'number' => $order->get_order_number(),
                'order_key' => $order->get_order_key(),
                'created_via' => $order->get_created_via(),
                'version' => $order->get_version(),
                'status' => $order->get_status(),
                'currency' => $order->get_currency(),
                'currency_symbol' => get_woocommerce_currency_symbol($order->get_currency()),
                'date_created' => $order->get_date_created() ? $order->get_date_created()->format('c') : '',
                'date_modified' => $order->get_date_modified() ? $order->get_date_modified()->format('c') : '',
                'date_completed' => $order->get_date_completed() ? $order->get_date_completed()->format('c') : null,
                'date_paid' => $order->get_date_paid() ? $order->get_date_paid()->format('c') : null,
                'date_created_gmt' => $order->get_date_created() ? $order->get_date_created()->format('Y-m-d\TH:i:s') : '',
                'date_modified_gmt' => $order->get_date_modified() ? $order->get_date_modified()->format('Y-m-d\TH:i:s') : '',
                'date_completed_gmt' => $order->get_date_completed() ? $order->get_date_completed()->format('Y-m-d\TH:i:s') : null,
                'date_paid_gmt' => $order->get_date_paid() ? $order->get_date_paid()->format('Y-m-d\TH:i:s') : null,
                'discount_total' => $order->get_discount_total(),
                'discount_tax' => $order->get_discount_tax(),
                'shipping_total' => $order->get_shipping_total(),
                'shipping_tax' => $order->get_shipping_tax(),
                'cart_tax' => $order->get_cart_tax(),
                'total' => $order->get_total(),
                'total_tax' => $order->get_total_tax(),
                'prices_include_tax' => $order->get_prices_include_tax(),
                'customer_id' => $order->get_customer_id(),
                'customer_ip_address' => $order->get_customer_ip_address(),
                'customer_user_agent' => $order->get_customer_user_agent(),
                'customer_note' => $order->get_customer_note(),
                'payment_method' => $order->get_payment_method(),
                'payment_method_title' => $order->get_payment_method_title(),
                'transaction_id' => $order->get_transaction_id(),
                'cart_hash' => $order->get_cart_hash(),
                'payment_url' => $order->get_checkout_payment_url(),
                'is_editable' => $order->is_editable(),
                'needs_payment' => $order->needs_payment(),
                'needs_processing' => $order->needs_processing(),
                'billing' => array(
                    'first_name' => $order->get_billing_first_name(),
                    'last_name' => $order->get_billing_last_name(),
                    'company' => $order->get_billing_company(),
                    'address_1' => $order->get_billing_address_1(),
                    'address_2' => $order->get_billing_address_2(),
                    'city' => $order->get_billing_city(),
                    'state' => $order->get_billing_state(),
                    'postcode' => $order->get_billing_postcode(),
                    'country' => $order->get_billing_country(),
                    'email' => $order->get_billing_email(),
                    'phone' => $order->get_billing_phone()
                ),
                'shipping' => array(
                    'first_name' => $order->get_shipping_first_name(),
                    'last_name' => $order->get_shipping_last_name(),
                    'company' => $order->get_shipping_company(),
                    'address_1' => $order->get_shipping_address_1(),
                    'address_2' => $order->get_shipping_address_2(),
                    'city' => $order->get_shipping_city(),
                    'state' => $order->get_shipping_state(),
                    'postcode' => $order->get_shipping_postcode(),
                    'country' => $order->get_shipping_country(),
                    'phone' => $order->get_shipping_phone()
                ),
                'line_items' => array(),
                'shipping_lines' => array(),
                'fee_lines' => array(),
                'coupon_lines' => array(),
                'tax_lines' => array(),
                'refunds' => array(),
                'meta_data' => array()
            )
        );
        
        // Get order line items (products)
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $item_data = array(
                'id' => $item_id,
                'name' => $item->get_name(),
                'product_id' => $item->get_product_id(),
                'variation_id' => $item->get_variation_id(),
                'quantity' => $item->get_quantity(),
                'tax_class' => $item->get_tax_class(),
                'subtotal' => $item->get_subtotal(),
                'subtotal_tax' => $item->get_subtotal_tax(),
                'total' => $item->get_total(),
                'total_tax' => $item->get_total_tax(),
                'taxes' => $item->get_taxes(),
                'meta_data' => array()
            );
            
            // Add product details if available
            if ($product) {
                $item_data['price'] = $product->get_price();
                $item_data['sku'] = $product->get_sku();
                $item_data['weight'] = $product->get_weight();
                $item_data['dimensions'] = array(
                    'length' => $product->get_length(),
                    'width' => $product->get_width(),
                    'height' => $product->get_height()
                );
                
                // Add product image
                $image_id = $product->get_image_id();
                if ($image_id) {
                    $image_src = wp_get_attachment_image_src($image_id, 'full');
                    if ($image_src) {
                        $item_data['image'] = array(
                            'id' => $image_id,
                            'src' => $image_src[0]
                        );
                    }
                }
                
                // Add parent product name for variations
                if ($item->get_variation_id()) {
                    $parent_product = wc_get_product($item->get_product_id());
                    if ($parent_product) {
                        $item_data['parent_name'] = $parent_product->get_name();
                    }
                }
                
                // Add cost of goods if available (from WooCommerce Cost of Goods plugin)
                $cog_cost = $item->get_meta('_wc_cog_item_cost');
                $cog_total_cost = $item->get_meta('_wc_cog_item_total_cost');
                if ($cog_cost) {
                    $item_data['cog_item_cost'] = $cog_cost;
                }
                if ($cog_total_cost) {
                    $item_data['cog_item_total_cost'] = $cog_total_cost;
                }
            }
            
            // Add item meta data with display keys and values
            foreach ($item->get_meta_data() as $meta) {
                $meta_data = array(
                    'id' => $meta->id,
                    'key' => $meta->key,
                    'value' => $meta->value,
                    'display_key' => $meta->key,
                    'display_value' => $meta->value
                );
                
                // Handle attribute display values
                if (strpos($meta->key, 'pa_') === 0) {
                    $attribute_name = wc_attribute_label(str_replace('pa_', '', $meta->key));
                    if ($attribute_name) {
                        $meta_data['display_key'] = $attribute_name;
                    }
                    
                    $term = get_term_by('slug', $meta->value, $meta->key);
                    if ($term) {
                        $meta_data['display_value'] = $term->name;
                    }
                }
                
                $item_data['meta_data'][] = $meta_data;
            }
            
            $webhook_data['order']['line_items'][] = $item_data;
        }
        
        // Get shipping lines
        foreach ($order->get_items('shipping') as $item_id => $item) {
            $shipping_data = array(
                'id' => $item_id,
                'method_title' => $item->get_method_title(),
                'method_id' => $item->get_method_id(),
                'instance_id' => $item->get_instance_id(),
                'total' => $item->get_total(),
                'total_tax' => $item->get_total_tax(),
                'taxes' => $item->get_taxes(),
                'tax_status' => $item->get_tax_status(),
                'meta_data' => array()
            );
            
            // Add shipping meta data
            foreach ($item->get_meta_data() as $meta) {
                $shipping_data['meta_data'][] = array(
                    'id' => $meta->id,
                    'key' => $meta->key,
                    'value' => $meta->value,
                    'display_key' => $meta->key,
                    'display_value' => $meta->value
                );
            }
            
            $webhook_data['order']['shipping_lines'][] = $shipping_data;
        }
        
        // Get fee lines
        foreach ($order->get_items('fee') as $item_id => $item) {
            $webhook_data['order']['fee_lines'][] = array(
                'id' => $item_id,
                'name' => $item->get_name(),
                'tax_class' => $item->get_tax_class(),
                'tax_status' => $item->get_tax_status(),
                'total' => $item->get_total(),
                'total_tax' => $item->get_total_tax(),
                'taxes' => $item->get_taxes()
            );
        }
        
        // Get coupon lines
        foreach ($order->get_items('coupon') as $item_id => $item) {
            $webhook_data['order']['coupon_lines'][] = array(
                'id' => $item_id,
                'code' => $item->get_code(),
                'discount' => $item->get_discount(),
                'discount_tax' => $item->get_discount_tax()
            );
        }
        
        // Get tax lines
        foreach ($order->get_items('tax') as $item_id => $item) {
            $webhook_data['order']['tax_lines'][] = array(
                'id' => $item_id,
                'rate_code' => $item->get_rate_code(),
                'rate_id' => $item->get_rate_id(),
                'label' => $item->get_label(),
                'compound' => $item->get_compound(),
                'rate_percent' => $item->get_rate_percent(),
                'tax_total' => $item->get_tax_total(),
                'shipping_tax_total' => $item->get_shipping_tax_total()
            );
        }
        
        // Get refunds
        foreach ($order->get_refunds() as $refund) {
            $webhook_data['order']['refunds'][] = array(
                'id' => $refund->get_id(),
                'date_created' => $refund->get_date_created() ? $refund->get_date_created()->format('c') : '',
                'amount' => $refund->get_amount(),
                'reason' => $refund->get_reason()
            );
        }
        
        // Get order meta data
        foreach ($order->get_meta_data() as $meta) {
            $webhook_data['order']['meta_data'][] = array(
                'id' => $meta->id,
                'key' => $meta->key,
                'value' => $meta->value
            );
        }
        
        // Send webhook
        $response = wp_remote_post($this->webhook_url, array(
            'body' => json_encode($webhook_data),
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'Vrobo-WooCommerce/' . VROBO_WC_VERSION
            ),
            'timeout' => 30
        ));
        
        // Update webhook status based on response
        global $wpdb;
        $table_name = $wpdb->prefix . 'vrobo_orders';
        
        if (is_wp_error($response)) {
            $webhook_status = 'failed';
            $webhook_response = $response->get_error_message();
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $webhook_status = ($response_code >= 200 && $response_code < 300) ? 'sent' : 'failed';
            $webhook_response = wp_remote_retrieve_body($response);
        }
        
        // Direct database query is necessary for custom plugin table - no WordPress API equivalent
        // No caching used as this is a real-time webhook status update that must be immediate.
        $wpdb->update(
            $table_name,
            array(
                'webhook_status' => $webhook_status,
                'webhook_response' => $webhook_response
            ),
            array('order_id' => $order_id),
            array('%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Send cancellation notification to webhook (manual cancellations only)
     */
    private function send_cancellation_to_webhook($order_id, $order) {
        $current_user = wp_get_current_user();
        $cancelled_by = $current_user->exists() ? $current_user->user_email : 'system';
        
        $webhook_data = array(
            'event' => 'order.cancelled',
            'domain' => home_url(),
            'api_key' => $this->api_key,
            'order_id' => $order_id,
            'cancelled_by' => $cancelled_by,
            'cancelled_at' => current_time('c'),
            'cancellation_type' => 'manual' // Indicates this was a manual cancellation
        );
        
        $response = wp_remote_post($this->webhook_url, array(
            'body' => json_encode($webhook_data),
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'Vrobo-WooCommerce/' . VROBO_WC_VERSION
            ),
            'timeout' => 30
        ));
        
        // Update webhook status in database
        global $wpdb;
        $table_name = $wpdb->prefix . 'vrobo_orders';
        
        if (is_wp_error($response)) {
            $webhook_status = 'failed';
            $webhook_response = $response->get_error_message();
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $webhook_status = ($response_code >= 200 && $response_code < 300) ? 'cancelled' : 'failed';
            $webhook_response = wp_remote_retrieve_body($response);
        }
        
        // Direct database query is necessary for custom plugin table - no WordPress API equivalent
        // No caching used as this is a real-time webhook status update that must be immediate.
        $wpdb->update(
            $table_name,
            array(
                'webhook_status' => $webhook_status,
                'webhook_response' => $webhook_response,
                'updated_date' => current_time('mysql')
            ),
            array('order_id' => $order_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * AJAX handler for cancel request
     */
    public function ajax_request_cancel() {
        // Validate nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'vrobo_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        
        if (!$order_id) {
            wp_send_json_error('Invalid order ID');
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error('Order not found');
            return;
        }
        
        // Update the webhook status to cancelled in our database
        global $wpdb;
        $table_name = $wpdb->prefix . 'vrobo_orders';
        // Direct database query is necessary for custom plugin table - no WordPress API equivalent
        // No caching used as this is a real-time cancellation status update that must be immediate.
        $result = $wpdb->update(
            $table_name,
            array(
                'webhook_status' => 'cancelled',
                'webhook_response' => 'Order cancelled by user',
                'updated_date' => current_time('mysql')
            ),
            array('order_id' => $order_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to update order status');
            return;
        }
        
        // Add order note
        $order->add_order_note('Order cancelled via Vrobo system by user: ' . wp_get_current_user()->user_email);
        
        // Send cancel notification to webhook (optional)
        $this->send_cancellation_to_webhook($order_id, $order);
        
        wp_send_json_success('Order cancelled successfully');
    }
    
    /**
     * AJAX handler for getting orders table data
     */
    public function ajax_get_orders_table() {
        // Validate nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'vrobo_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        global $wpdb;
        
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 20;
        $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        $status_filter = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';
        $offset = ($page - 1) * $per_page;
        
        // Create cache key based on query parameters
        $cache_key = 'vrobo_orders_table_' . md5(serialize(array($page, $per_page, $search, $status_filter)));
        $cached_result = wp_cache_get($cache_key, 'vrobo_orders');
        
        if ($cached_result !== false) {
            wp_send_json_success($cached_result);
            return;
        }
        
        // Build queries based on conditions to avoid dynamic WHERE clause construction
        $orders = null;
        $total = 0;
        
        // Execute queries based on filter conditions - Direct database queries necessary for custom plugin table
        if (!empty($search) && !empty($status_filter)) {
            // Both search and status filter
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            
            switch ($status_filter) {
                case 'confirmed':
                    $orders = $wpdb->get_results($wpdb->prepare(
                        "SELECT * FROM `{$wpdb->prefix}vrobo_orders` WHERE (customer_name LIKE %s OR customer_email LIKE %s OR CAST(order_id AS CHAR) LIKE %s) AND webhook_status = %s ORDER BY created_date DESC LIMIT %d OFFSET %d",
                        $search_term, $search_term, $search_term, 'sent', $per_page, $offset
                    ));
                    $total = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM `{$wpdb->prefix}vrobo_orders` WHERE (customer_name LIKE %s OR customer_email LIKE %s OR CAST(order_id AS CHAR) LIKE %s) AND webhook_status = %s",
                        $search_term, $search_term, $search_term, 'sent'
                    ));
                    break;
                case 'cancelled':
                    $orders = $wpdb->get_results($wpdb->prepare(
                        "SELECT * FROM `{$wpdb->prefix}vrobo_orders` WHERE (customer_name LIKE %s OR customer_email LIKE %s OR CAST(order_id AS CHAR) LIKE %s) AND (order_status = %s OR webhook_status = %s) ORDER BY created_date DESC LIMIT %d OFFSET %d",
                        $search_term, $search_term, $search_term, 'cancelled', 'cancelled', $per_page, $offset
                    ));
                    $total = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM `{$wpdb->prefix}vrobo_orders` WHERE (customer_name LIKE %s OR customer_email LIKE %s OR CAST(order_id AS CHAR) LIKE %s) AND (order_status = %s OR webhook_status = %s)",
                        $search_term, $search_term, $search_term, 'cancelled', 'cancelled'
                    ));
                    break;
                case 'support':
                    $orders = $wpdb->get_results($wpdb->prepare(
                        "SELECT * FROM `{$wpdb->prefix}vrobo_orders` WHERE (customer_name LIKE %s OR customer_email LIKE %s OR CAST(order_id AS CHAR) LIKE %s) AND webhook_status = %s ORDER BY created_date DESC LIMIT %d OFFSET %d",
                        $search_term, $search_term, $search_term, 'failed', $per_page, $offset
                    ));
                    $total = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM `{$wpdb->prefix}vrobo_orders` WHERE (customer_name LIKE %s OR customer_email LIKE %s OR CAST(order_id AS CHAR) LIKE %s) AND webhook_status = %s",
                        $search_term, $search_term, $search_term, 'failed'
                    ));
                    break;
                case 'unclear':
                    $orders = $wpdb->get_results($wpdb->prepare(
                        "SELECT * FROM `{$wpdb->prefix}vrobo_orders` WHERE (customer_name LIKE %s OR customer_email LIKE %s OR CAST(order_id AS CHAR) LIKE %s) AND webhook_status = %s ORDER BY created_date DESC LIMIT %d OFFSET %d",
                        $search_term, $search_term, $search_term, 'skipped', $per_page, $offset
                    ));
                    $total = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM `{$wpdb->prefix}vrobo_orders` WHERE (customer_name LIKE %s OR customer_email LIKE %s OR CAST(order_id AS CHAR) LIKE %s) AND webhook_status = %s",
                        $search_term, $search_term, $search_term, 'skipped'
                    ));
                    break;
            }
        } elseif (!empty($search)) {
            // Search only
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $orders = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM `{$wpdb->prefix}vrobo_orders` WHERE customer_name LIKE %s OR customer_email LIKE %s OR CAST(order_id AS CHAR) LIKE %s ORDER BY created_date DESC LIMIT %d OFFSET %d",
                $search_term, $search_term, $search_term, $per_page, $offset
            ));
            $total = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM `{$wpdb->prefix}vrobo_orders` WHERE customer_name LIKE %s OR customer_email LIKE %s OR CAST(order_id AS CHAR) LIKE %s",
                $search_term, $search_term, $search_term
            ));
        } elseif (!empty($status_filter)) {
            // Status filter only
            switch ($status_filter) {
                case 'confirmed':
                    $orders = $wpdb->get_results($wpdb->prepare(
                        "SELECT * FROM `{$wpdb->prefix}vrobo_orders` WHERE webhook_status = %s ORDER BY created_date DESC LIMIT %d OFFSET %d",
                        'sent', $per_page, $offset
                    ));
                    $total = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM `{$wpdb->prefix}vrobo_orders` WHERE webhook_status = %s",
                        'sent'
                    ));
                    break;
                case 'cancelled':
                    $orders = $wpdb->get_results($wpdb->prepare(
                        "SELECT * FROM `{$wpdb->prefix}vrobo_orders` WHERE order_status = %s OR webhook_status = %s ORDER BY created_date DESC LIMIT %d OFFSET %d",
                        'cancelled', 'cancelled', $per_page, $offset
                    ));
                    $total = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM `{$wpdb->prefix}vrobo_orders` WHERE order_status = %s OR webhook_status = %s",
                        'cancelled', 'cancelled'
                    ));
                    break;
                case 'support':
                    $orders = $wpdb->get_results($wpdb->prepare(
                        "SELECT * FROM `{$wpdb->prefix}vrobo_orders` WHERE webhook_status = %s ORDER BY created_date DESC LIMIT %d OFFSET %d",
                        'failed', $per_page, $offset
                    ));
                    $total = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM `{$wpdb->prefix}vrobo_orders` WHERE webhook_status = %s",
                        'failed'
                    ));
                    break;
                case 'unclear':
                    $orders = $wpdb->get_results($wpdb->prepare(
                        "SELECT * FROM `{$wpdb->prefix}vrobo_orders` WHERE webhook_status = %s ORDER BY created_date DESC LIMIT %d OFFSET %d",
                        'skipped', $per_page, $offset
                    ));
                    $total = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM `{$wpdb->prefix}vrobo_orders` WHERE webhook_status = %s",
                        'skipped'
                    ));
                    break;
            }
        } else {
            // No filters
            $orders = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM `{$wpdb->prefix}vrobo_orders` ORDER BY created_date DESC LIMIT %d OFFSET %d",
                $per_page, $offset
            ));
            $total = $wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->prefix}vrobo_orders`");
        }
        
        // Check for database errors
        if ($wpdb->last_error) {
            wp_send_json_error('Database error');
            return;
        }
        
        $response = array(
            'orders' => $orders,
            'pagination' => array(
                'page' => $page,
                'per_page' => $per_page,
                'total' => intval($total),
                'total_pages' => ceil($total / $per_page)
            )
        );
        
        // Cache the admin table results for 30 seconds to improve performance
        wp_cache_set($cache_key, $response, 'vrobo_orders', 30);
        
        wp_send_json_success($response);
    }
} 