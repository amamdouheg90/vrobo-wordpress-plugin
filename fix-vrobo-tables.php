<?php
/**
 * Fix Vrobo Database Tables
 * Place this file in your WordPress root directory and access it via browser
 * Example: https://yoursite.com/fix-vrobo-tables.php
 */

// Load WordPress
require_once('wp-load.php');

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Access denied. Please login as administrator.');
}

echo "<h1>Vrobo Database Table Repair</h1>";

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

echo "<h2>Creating/Updating Tables...</h2>";

$result1 = dbDelta($orders_sql);
$result2 = dbDelta($logs_sql);

echo "<h3>Orders Table Results:</h3>";
echo "<pre>" . print_r($result1, true) . "</pre>";

echo "<h3>Logs Table Results:</h3>";
echo "<pre>" . print_r($result2, true) . "</pre>";

// Check if tables exist now
$orders_exists = $wpdb->get_var("SHOW TABLES LIKE '$orders_table'") == $orders_table;
$logs_exists = $wpdb->get_var("SHOW TABLES LIKE '$logs_table'") == $logs_table;

echo "<h2>Table Status:</h2>";
echo "<p>Orders Table ($orders_table): " . ($orders_exists ? "<strong style='color:green'>EXISTS</strong>" : "<strong style='color:red'>MISSING</strong>") . "</p>";
echo "<p>Logs Table ($logs_table): " . ($logs_exists ? "<strong style='color:green'>EXISTS</strong>" : "<strong style='color:red'>MISSING</strong>") . "</p>";

if ($orders_exists) {
    $order_count = $wpdb->get_var("SELECT COUNT(*) FROM $orders_table");
    echo "<p>Orders in Database: <strong>$order_count</strong></p>";
}

// Check plugin settings
echo "<h2>Plugin Configuration:</h2>";
$api_key = get_option('vrobo_wc_api_key', '');
$api_validated = get_option('vrobo_wc_api_key_validated', 0);
echo "<p>API Key Set: " . (!empty($api_key) ? "<strong style='color:green'>YES</strong>" : "<strong style='color:red'>NO</strong>") . "</p>";
echo "<p>API Validated: " . ($api_validated ? "<strong style='color:green'>YES</strong>" : "<strong style='color:red'>NO</strong>") . "</p>";

echo "<h2>Next Steps:</h2>";
echo "<ul>";
echo "<li>If tables are missing, try deactivating and reactivating the Vrobo plugin</li>";
echo "<li>If API key is not validated, go to Vrobo > Settings and re-enter your API key</li>";
echo "<li>Create a test order to see if it appears in Vrobo > Orders</li>";
echo "<li>Delete this file after running for security</li>";
echo "</ul>";

echo "<p><strong>Done! You can delete this file now for security.</strong></p>";
?> 