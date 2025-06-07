<?php
/**
 * API Handler Class
 * Handles REST API endpoints and authentication
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vrobo_API_Handler {
    
    private $namespace = 'vrobo/v1';
    
    public function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_filter('rest_pre_dispatch', array($this, 'authenticate_request'), 10, 3);
        
        // External API endpoints (no WordPress auth required)
        add_action('wp_ajax_nopriv_vrobo_external_update', array($this, 'handle_external_update'));
        add_action('wp_ajax_vrobo_external_update', array($this, 'handle_external_update'));
        
        add_action('wp_ajax_nopriv_vrobo_external_status', array($this, 'handle_external_status_update'));
        add_action('wp_ajax_vrobo_external_status', array($this, 'handle_external_status_update'));
        
        add_action('wp_ajax_nopriv_vrobo_external_note', array($this, 'handle_external_note'));
        add_action('wp_ajax_vrobo_external_note', array($this, 'handle_external_note'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Get all orders
        register_rest_route($this->namespace, '/orders', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_orders'),
            'permission_callback' => array($this, 'check_permissions'),
        ));
        
        // Get single order
        register_rest_route($this->namespace, '/orders/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_order'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ),
            ),
        ));
        
        // Update order note
        register_rest_route($this->namespace, '/orders/(?P<id>\d+)/note', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_order_note'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ),
                'note' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_textarea_field',
                ),
            ),
        ));
        
        // Add order comment
        register_rest_route($this->namespace, '/orders/(?P<id>\d+)/comment', array(
            'methods' => 'POST',
            'callback' => array($this, 'add_order_comment'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ),
                'comment' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_textarea_field',
                ),
                'is_customer_note' => array(
                    'default' => false,
                    'sanitize_callback' => 'rest_sanitize_boolean',
                ),
            ),
        ));
        
        // Get order notes
        register_rest_route($this->namespace, '/orders/(?P<id>\d+)/notes', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_order_notes'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ),
            ),
        ));
        
        // Update order status (including custom statuses)
        register_rest_route($this->namespace, '/orders/(?P<id>\d+)/status', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_order_status'),
            'permission_callback' => array($this, 'check_permissions'),
        ));
        
        // Webhook endpoint for external systems
        register_rest_route($this->namespace, '/webhook/orders', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => array($this, 'check_permissions'),
        ));
    }
    
    /**
     * Check API permissions
     */
    public function check_permissions($request) {
        $api_key = $request->get_header('X-API-Key');
        
        if (empty($api_key)) {
            // Check for API key in query parameter as fallback
            $api_key = $request->get_param('api_key');
        }
        
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', 'API key is required', array('status' => 401));
        }
        
        $stored_api_key = get_option('vrobo_wc_api_key');
        
        if ($api_key !== $stored_api_key) {
            return new WP_Error('invalid_api_key', 'Invalid API key', array('status' => 401));
        }
        
        return true;
    }
    
    /**
     * Authenticate REST API requests
     */
    public function authenticate_request($result, $server, $request) {
        // Only authenticate our plugin's routes
        if (strpos($request->get_route(), '/' . $this->namespace) === 0) {
            $auth_result = $this->check_permissions($request);
            if (is_wp_error($auth_result)) {
                return $auth_result;
            }
        }
        
        return $result;
    }
    
    /**
     * Get all orders
     */
    public function get_orders($request) {
        global $wpdb;
        
        $page = $request->get_param('page') ?: 1;
        $per_page = $request->get_param('per_page') ?: 20;
        $offset = ($page - 1) * $per_page;
        
        $orders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `{$wpdb->prefix}vrobo_orders` ORDER BY created_date DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->prefix}vrobo_orders`");
        
        $response = array(
            'orders' => $orders,
            'pagination' => array(
                'page' => $page,
                'per_page' => $per_page,
                'total' => $total,
                'total_pages' => ceil($total / $per_page)
            )
        );
        
        return rest_ensure_response($response);
    }
    
    /**
     * Get single order
     */
    public function get_order($request) {
        $order_id = $request['id'];
        
        // Get from WooCommerce
        $wc_order = wc_get_order($order_id);
        if (!$wc_order) {
            return new WP_Error('order_not_found', 'Order not found', array('status' => 404));
        }
        
        // Get from custom database
        global $wpdb;
        $custom_order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$wpdb->prefix}vrobo_orders` WHERE order_id = %d",
            $order_id
        ));
        
        $response = array(
            'wc_order' => array(
                'id' => $wc_order->get_id(),
                'status' => $wc_order->get_status(),
                'total' => $wc_order->get_total(),
                'currency' => $wc_order->get_currency(),
                'customer_email' => $wc_order->get_billing_email(),
                'customer_name' => $wc_order->get_billing_first_name() . ' ' . $wc_order->get_billing_last_name(),
                'date_created' => $wc_order->get_date_created()->format('Y-m-d H:i:s'),
            ),
            'custom_data' => $custom_order
        );
        
        return rest_ensure_response($response);
    }
    
    /**
     * Update order note via API
     */
    public function update_order_note($request) {
        $order_id = $request['id'];
        $note = $request['note'];
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('order_not_found', 'Order not found', array('status' => 404));
        }
        
        $order->add_order_note($note, false);
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Note added successfully',
            'order_id' => $order_id
        ));
    }
    
    /**
     * Add order comment via API
     */
    public function add_order_comment($request) {
        $order_id = $request['id'];
        $comment = $request['comment'];
        $is_customer_note = $request['is_customer_note'];
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('order_not_found', 'Order not found', array('status' => 404));
        }
        
        $order->add_order_note($comment, $is_customer_note);
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Comment added successfully',
            'order_id' => $order_id,
            'is_customer_note' => $is_customer_note
        ));
    }
    
    /**
     * Get order notes via API
     */
    public function get_order_notes($request) {
        $order_id = $request['id'];
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('order_not_found', 'Order not found', array('status' => 404));
        }
        
        $notes = wc_get_order_notes(array(
            'order_id' => $order_id,
            'limit' => 50
        ));
        
        $formatted_notes = array();
        foreach ($notes as $note) {
            $formatted_notes[] = array(
                'id' => $note->comment_ID,
                'content' => $note->comment_content,
                'date' => $note->comment_date,
                'author' => $note->comment_author,
                'customer_note' => $note->customer_note
            );
        }
        
        return rest_ensure_response($formatted_notes);
    }
    
    /**
     * Handle webhook requests
     */
    public function handle_webhook($request) {
        // Get the raw POST data
        $raw_data = file_get_contents('php://input');
        $body = json_decode($raw_data, true);
        
        if (!$body) {
            http_response_code(400);
            echo json_encode(array('error' => 'Invalid JSON data'));
            exit;
        }
        
        // Process webhook data based on type
        if (isset($body['type']) && $body['type'] === 'order_update') {
            $this->process_order_update_webhook($body);
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Webhook processed successfully'
        ));
    }
    
    /**
     * Process order update webhook
     */
    private function process_order_update_webhook($data) {
        if (!isset($data['order_id'])) {
            return;
        }
        
        $order_id = intval($data['order_id']);
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        // Update order status if provided
        if (isset($data['status'])) {
            $order->update_status($data['status']);
        }
        
        // Add note if provided
        if (isset($data['note'])) {
            $order->add_order_note($data['note'], false);
        }
        
        // Update custom database
        global $wpdb;
        $table_name = $wpdb->prefix . 'vrobo_orders';
        
        $wpdb->update(
            $table_name,
            array(
                'api_status' => 'updated',
                'updated_date' => current_time('mysql')
            ),
            array('order_id' => $order_id),
            array('%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Update order status via API
     */
    public function update_order_status($request) {
        $order_id = $request->get_param('id');
        $new_status = sanitize_text_field($request->get_param('status'));
        
        if (empty($new_status)) {
            return new WP_Error('missing_status', 'Status parameter is required', array('status' => 400));
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('order_not_found', 'Order not found', array('status' => 404));
        }
        
        // List of allowed statuses (including custom ones)
        $allowed_statuses = array(
            'pending',
            'processing', 
            'on-hold',
            'completed',
            'cancelled',
            'refunded',
            'failed',
            'confirmed',    // Custom status
            'support',      // Custom status
            'unclear'       // Custom status
        );
        
        if (!in_array($new_status, $allowed_statuses)) {
            return new WP_Error('invalid_status', 'Invalid order status', array('status' => 400));
        }
        
        // Update order status
        $old_status = $order->get_status();
        $order->update_status($new_status, 'Status updated via Vrobo API');
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Order status updated successfully',
            'data' => array(
                'order_id' => $order_id,
                'old_status' => $old_status,
                'new_status' => $new_status,
                'updated_at' => current_time('mysql')
            )
        ));
    }
    
    /**
     * Verify API key for external requests
     */
    private function verify_external_api_key($provided_key) {
        $stored_key = get_option('vrobo_wc_api_key', '');
        return !empty($stored_key) && $provided_key === $stored_key;
    }
    
    /**
     * Handle external order update (full)
     * Note: This is an external API endpoint that uses API key authentication
     * instead of WordPress nonces, as it's designed for external system integration
     */
    public function handle_external_update() {
        // Verify API key - External API authentication, not WordPress session-based
        $api_key = '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- External API uses API key authentication
        if (isset($_POST['api_key'])) {
            $api_key = sanitize_text_field(wp_unslash($_POST['api_key']));
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- External API uses API key authentication
        } elseif (isset($_GET['api_key'])) {
            $api_key = sanitize_text_field(wp_unslash($_GET['api_key']));
        }
        
        if (!$this->verify_external_api_key($api_key)) {
            wp_send_json_error('Invalid API key', 401);
            return;
        }
        
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- External API uses API key authentication
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        if (!$order_id) {
            wp_send_json_error('Order ID required', 400);
            return;
        }
        
        $vrobo_db = new Vrobo_Database();
        
        // Prepare plugin data - All POST data sanitized after API key verification
        $plugin_data = array();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- External API uses API key authentication
        if (isset($_POST['last_action'])) {
            $plugin_data['last_action'] = sanitize_text_field(wp_unslash($_POST['last_action']));
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- External API uses API key authentication
        if (isset($_POST['tags'])) {
            $plugin_data['tags'] = sanitize_text_field(wp_unslash($_POST['tags']));
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- External API uses API key authentication
        if (isset($_POST['note'])) {
            $plugin_data['note'] = sanitize_textarea_field(wp_unslash($_POST['note']));
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- External API uses API key authentication
        if (isset($_POST['webhook_status'])) {
            $plugin_data['webhook_status'] = sanitize_text_field(wp_unslash($_POST['webhook_status']));
        }
        
        // WooCommerce updates
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- External API uses API key authentication
        $woo_status = isset($_POST['woo_status']) ? sanitize_text_field(wp_unslash($_POST['woo_status'])) : null;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- External API uses API key authentication
        $woo_note = isset($_POST['woo_note']) ? sanitize_textarea_field(wp_unslash($_POST['woo_note'])) : '';
        
        // Update both databases
        $success = $vrobo_db->update_full_order($order_id, $plugin_data, $woo_status, $woo_note);
        
        if ($success) {
            wp_send_json_success(array(
                'message' => 'Order updated successfully',
                'order_id' => $order_id,
                'updated_fields' => array_keys($plugin_data),
                'woo_status_updated' => !empty($woo_status),
                'woo_note_added' => !empty($woo_note)
            ));
        } else {
            wp_send_json_error('Failed to update order', 500);
        }
    }
    
    /**
     * Handle external status update (WooCommerce only)
     * Note: This is an external API endpoint that uses API key authentication
     * instead of WordPress nonces, as it's designed for external system integration
     */
    public function handle_external_status_update() {
        // Verify API key - External API authentication, not WordPress session-based
        $api_key = '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- External API uses API key authentication
        if (isset($_POST['api_key'])) {
            $api_key = sanitize_text_field(wp_unslash($_POST['api_key']));
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- External API uses API key authentication
        } elseif (isset($_GET['api_key'])) {
            $api_key = sanitize_text_field(wp_unslash($_GET['api_key']));
        }
        
        if (!$this->verify_external_api_key($api_key)) {
            wp_send_json_error('Invalid API key', 401);
            return;
        }
        
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- External API uses API key authentication
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- External API uses API key authentication  
        $new_status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- External API uses API key authentication
        $note = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash($_POST['note'])) : '';
        
        if (!$order_id || !$new_status) {
            wp_send_json_error('Order ID and status required', 400);
            return;
        }
        
        $vrobo_db = new Vrobo_Database();
        $success = $vrobo_db->update_woocommerce_order_status($order_id, $new_status, $note);
        
        if ($success) {
            wp_send_json_success(array(
                'message' => 'Order status updated successfully',
                'order_id' => $order_id,
                'new_status' => $new_status
            ));
        } else {
            wp_send_json_error('Failed to update order status', 500);
        }
    }
    
    /**
     * Handle external note addition
     * Note: This is an external API endpoint that uses API key authentication
     * instead of WordPress nonces, as it's designed for external system integration
     */
    public function handle_external_note() {
        // Verify API key - External API authentication, not WordPress session-based
        $api_key = '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- External API uses API key authentication
        if (isset($_POST['api_key'])) {
            $api_key = sanitize_text_field(wp_unslash($_POST['api_key']));
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- External API uses API key authentication
        } elseif (isset($_GET['api_key'])) {
            $api_key = sanitize_text_field(wp_unslash($_GET['api_key']));
        }
        
        if (!$this->verify_external_api_key($api_key)) {
            wp_send_json_error('Invalid API key', 401);
            return;
        }
        
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- External API uses API key authentication
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- External API uses API key authentication
        $note = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash($_POST['note'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- External API uses API key authentication
        $is_customer_note = isset($_POST['is_customer_note']) ? (bool) $_POST['is_customer_note'] : false;
        
        if (!$order_id || !$note) {
            wp_send_json_error('Order ID and note required', 400);
            return;
        }
        
        $vrobo_db = new Vrobo_Database();
        $success = $vrobo_db->add_woocommerce_order_note($order_id, $note, $is_customer_note);
        
        if ($success) {
            wp_send_json_success(array(
                'message' => 'Note added successfully',
                'order_id' => $order_id,
                'is_customer_note' => $is_customer_note
            ));
        } else {
            wp_send_json_error('Failed to add note', 500);
        }
    }
} 