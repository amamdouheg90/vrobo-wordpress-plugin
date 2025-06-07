<?php
/**
 * Database Handler Class
 * Handles custom database table creation and operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vrobo_Database {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Hook for AJAX actions
        add_action('wp_ajax_vrobo_get_orders_table', array($this, 'ajax_get_orders_table'));
        add_action('wp_ajax_vrobo_delete_order', array($this, 'ajax_delete_order'));
        add_action('wp_ajax_vrobo_sync_order', array($this, 'ajax_sync_order'));
    }
    
    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Orders table
        $orders_table = $wpdb->prefix . 'vrobo_orders';
        $orders_sql = "CREATE TABLE $orders_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            customer_email varchar(255) NOT NULL,
            customer_name varchar(255) NOT NULL,
            order_status varchar(50) NOT NULL,
            order_total decimal(10,2) NOT NULL,
            webhook_status varchar(50) DEFAULT 'pending',
            webhook_response text,
            last_action varchar(255) DEFAULT '',
            tags text DEFAULT '',
            note text DEFAULT '',
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY webhook_status (webhook_status),
            KEY created_date (created_date)
        ) $charset_collate;";
        
        // Logs table  
        $logs_table = $wpdb->prefix . 'vrobo_logs';
        $logs_sql = "CREATE TABLE $logs_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            log_level varchar(20) NOT NULL,
            message text NOT NULL,
            context text,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY log_level (log_level),
            KEY created_date (created_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($orders_sql);
        dbDelta($logs_sql);
    }
    
    /**
     * Get orders from custom table
     */
    public function get_orders($limit = 20, $offset = 0, $search = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'vrobo_orders';
        $params = array();
        
        if (!empty($search)) {
            $where = "WHERE customer_email LIKE %s OR customer_name LIKE %s OR order_id = %d";
            $search_param = '%' . $wpdb->esc_like($search) . '%';
            $params = array($search_param, $search_param, intval($search));
        }
        
        // Build query properly without direct variable interpolation
        if (!empty($search)) {
            $search_param = '%' . $wpdb->esc_like($search) . '%';
            $orders = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM `{$wpdb->prefix}vrobo_orders` WHERE customer_email LIKE %s OR customer_name LIKE %s OR order_id = %d ORDER BY created_date DESC LIMIT %d OFFSET %d",
                $search_param,
                $search_param,
                intval($search),
                $limit,
                $offset
            ));
        } else {
            $orders = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM `{$wpdb->prefix}vrobo_orders` ORDER BY created_date DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            ));
        }
        
        return $orders;
    }
    
    /**
     * Get total orders count
     */
    public function get_orders_count($search = '') {
        global $wpdb;
        
        if (!empty($search)) {
            $search_param = '%' . $wpdb->esc_like($search) . '%';
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM `{$wpdb->prefix}vrobo_orders` WHERE customer_email LIKE %s OR customer_name LIKE %s OR order_id = %d",
                $search_param,
                $search_param,
                intval($search)
            ));
        } else {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->prefix}vrobo_orders`");
        }
        
        return intval($count);
    }
    
    /**
     * Get single order from custom table
     */
    public function get_order($order_id) {
        global $wpdb;
        
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$wpdb->prefix}vrobo_orders` WHERE order_id = %d",
            $order_id
        ));
        
        return $order;
    }
    
    /**
     * Update order in custom table
     */
    public function update_order($order_id, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'vrobo_orders';
        
        $data['updated_date'] = current_time('mysql');
        
        $result = $wpdb->update(
            $table_name,
            $data,
            array('order_id' => $order_id),
            null,
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Update Last Action for an order in plugin database
     */
    public function update_last_action($order_id, $last_action) {
        return $this->update_order($order_id, array('last_action' => $last_action));
    }
    
    /**
     * Update Tags for an order in plugin database
     */
    public function update_tags($order_id, $tags) {
        // If tags is an array, convert to comma-separated string
        if (is_array($tags)) {
            $tags = implode(',', $tags);
        }
        return $this->update_order($order_id, array('tags' => $tags));
    }
    
    /**
     * Update Note for an order in plugin database
     */
    public function update_note($order_id, $note) {
        return $this->update_order($order_id, array('note' => $note));
    }
    
    /**
     * Update WooCommerce order status
     */
    public function update_woocommerce_order_status($order_id, $new_status, $note = '') {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return false;
        }
        
        // Update the order status
        $order->update_status($new_status, $note);
        
        // Also update in our plugin database
        $this->update_order($order_id, array('order_status' => $new_status));
        
        return true;
    }
    
    /**
     * Add note to WooCommerce order
     */
    public function add_woocommerce_order_note($order_id, $note, $is_customer_note = false) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return false;
        }
        
        // Add note to WooCommerce order
        $order->add_order_note($note, $is_customer_note);
        
        return true;
    }
    
    /**
     * Update both plugin database and WooCommerce order
     */
    public function update_full_order($order_id, $plugin_data = array(), $woo_status = null, $woo_note = '') {
        $success = true;
        
        // Update plugin database
        if (!empty($plugin_data)) {
            $success = $this->update_order($order_id, $plugin_data) && $success;
        }
        
        // Update WooCommerce order status if provided
        if ($woo_status) {
            $success = $this->update_woocommerce_order_status($order_id, $woo_status, $woo_note) && $success;
        }
        
        // Add WooCommerce note if provided (and not already added via status update)
        if ($woo_note && !$woo_status) {
            $success = $this->add_woocommerce_order_note($order_id, $woo_note) && $success;
        }
        
        return $success;
    }
    
    /**
     * Delete order from custom table
     */
    public function delete_order($order_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'vrobo_orders';
        
        $result = $wpdb->delete(
            $table_name,
            array('order_id' => $order_id),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Log action
     */
    public function log($order_id, $action, $message, $level = 'info') {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'vrobo_logs';
        
        $wpdb->insert(
            $logs_table,
            array(
                'order_id' => $order_id,
                'action' => $action,
                'message' => $message,
                'level' => $level,
                'created_date' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Get logs for an order
     */
    public function get_order_logs($order_id, $limit = 50) {
        global $wpdb;
        
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `{$wpdb->prefix}vrobo_logs` WHERE order_id = %d ORDER BY created_date DESC LIMIT %d",
            $order_id,
            $limit
        ));
        
        return $logs;
    }
    
    /**
     * AJAX handler for getting orders table
     */
    public function ajax_get_orders_table() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'vrobo_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 20;
        $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        
        $offset = ($page - 1) * $per_page;
        $orders = $this->get_orders($per_page, $offset, $search);
        $total = $this->get_orders_count($search);
        
        $response = array(
            'orders' => $orders,
            'pagination' => array(
                'page' => $page,
                'per_page' => $per_page,
                'total' => $total,
                'total_pages' => ceil($total / $per_page)
            )
        );
        
        wp_send_json_success($response);
    }
    
    /**
     * AJAX handler for deleting an order
     */
    public function ajax_delete_order() {
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
        
        if ($this->delete_order($order_id)) {
            $this->log($order_id, 'delete', 'Order deleted from Vrobo database');
            wp_send_json_success('Order deleted successfully');
        } else {
            wp_send_json_error('Failed to delete order');
        }
    }
    
    /**
     * AJAX handler for syncing an order
     */
    public function ajax_sync_order() {
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
        
        // Get WooCommerce order
        $wc_order = wc_get_order($order_id);
        if (!$wc_order) {
            wp_send_json_error('WooCommerce order not found');
            return;
        }
        
        // Update custom database with current WooCommerce data
        $data = array(
            'customer_email' => $wc_order->get_billing_email(),
            'customer_name' => $wc_order->get_billing_first_name() . ' ' . $wc_order->get_billing_last_name(),
            'order_status' => $wc_order->get_status(),
            'order_total' => $wc_order->get_total(),
            'webhook_status' => 'synced'
        );
        
        if ($this->update_order($order_id, $data)) {
            $this->log($order_id, 'sync', 'Order synced with WooCommerce data');
            wp_send_json_success('Order synced successfully');
        } else {
            wp_send_json_error('Failed to sync order');
        }
    }
    
    /**
     * Get database statistics
     */
    public function get_stats() {
        global $wpdb;
        
        $stats = array(
            'total_orders' => $wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->prefix}vrobo_orders`"),
            'pending_api' => $wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->prefix}vrobo_orders` WHERE webhook_status = 'pending'"),
            'sent_api' => $wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->prefix}vrobo_orders` WHERE webhook_status = 'sent'"),
            'failed_api' => $wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->prefix}vrobo_orders` WHERE webhook_status = 'failed'"),
            'skipped_api' => $wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->prefix}vrobo_orders` WHERE webhook_status = 'skipped'"),
            'total_revenue' => $wpdb->get_var("SELECT SUM(order_total) FROM `{$wpdb->prefix}vrobo_orders`"),
            'recent_orders' => $wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->prefix}vrobo_orders` WHERE created_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")
        );
        
        return $stats;
    }
} 