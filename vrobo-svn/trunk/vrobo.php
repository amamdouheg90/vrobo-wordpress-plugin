<?php
/**
 * Plugin Name: Vrobo
 * Plugin URI: https://vrobo.co
 * Description: Custom order management plugin with API integration and automation for e-commerce stores
 * Version: 2.3.0
 * Author: Vrobo
 * License: GPL v2 or later
 * Text Domain: vrobo
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 * Requires Plugins: woocommerce
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('VROBO_WC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VROBO_WC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('VROBO_WC_VERSION', '2.3.0');

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', 'vrobo_wc_woocommerce_missing_notice');
    return;
}

function vrobo_wc_woocommerce_missing_notice() {
    echo '<div class="notice notice-error"><p><strong>Vrobo</strong> requires WooCommerce to be installed and active.</p></div>';
}

// Declare WooCommerce feature compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('orders_cache', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('remote_logging', __FILE__, true);
    }
});

// Main plugin class
class Vrobo {
    
    public function __construct() {
        $this->init_hooks();
        $this->includes();
    }
    
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // Register custom order statuses
        add_action('init', array($this, 'register_custom_order_statuses'));
        add_filter('wc_order_statuses', array($this, 'add_custom_order_statuses'));
        add_filter('woocommerce_reports_order_statuses', array($this, 'include_custom_order_statuses_in_reports'));
    }
    
    private function includes() {
        require_once VROBO_WC_PLUGIN_PATH . 'includes/class-vrobo-order-handler.php';
        require_once VROBO_WC_PLUGIN_PATH . 'includes/class-vrobo-api-handler.php';
        require_once VROBO_WC_PLUGIN_PATH . 'includes/class-vrobo-database.php';
    }
    
    public function init() {
        // Initialize plugin components with output buffering for safety
        ob_start();
        new Vrobo_Order_Handler();
        new Vrobo_API_Handler();
        new Vrobo_Database();
        ob_end_clean();
        
        // WordPress 4.6+ automatically loads translations, no need for load_plugin_textdomain
    }
    
    /**
     * Register custom order statuses
     */
    public function register_custom_order_statuses() {
        // Register confirmed status
        register_post_status('wc-confirmed', array(
            'label' => _x('Confirmed', 'Order status', 'vrobo'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            /* translators: %s: Number of confirmed orders */
            'label_count' => _n_noop('Confirmed <span class="count">(%s)</span>', 'Confirmed <span class="count">(%s)</span>', 'vrobo')
        ));
        
        // Register support status
        register_post_status('wc-support', array(
            'label' => _x('Support', 'Order status', 'vrobo'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            /* translators: %s: Number of support orders */
            'label_count' => _n_noop('Support <span class="count">(%s)</span>', 'Support <span class="count">(%s)</span>', 'vrobo')
        ));
        
        // Register unclear status
        register_post_status('wc-unclear', array(
            'label' => _x('Unclear', 'Order status', 'vrobo'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            /* translators: %s: Number of unclear orders */
            'label_count' => _n_noop('Unclear <span class="count">(%s)</span>', 'Unclear <span class="count">(%s)</span>', 'vrobo')
        ));
    }
    
    /**
     * Add custom order statuses to WooCommerce
     */
    public function add_custom_order_statuses($order_statuses) {
        $new_order_statuses = array();
        
        // Add all existing statuses
        foreach ($order_statuses as $key => $status) {
            $new_order_statuses[$key] = $status;
            
            // Add our custom statuses after pending
            if ('wc-pending' === $key) {
                $new_order_statuses['wc-confirmed'] = _x('Confirmed', 'Order status', 'vrobo');
                $new_order_statuses['wc-support'] = _x('Support', 'Order status', 'vrobo');
                $new_order_statuses['wc-unclear'] = _x('Unclear', 'Order status', 'vrobo');
            }
        }
        
        return $new_order_statuses;
    }
    
    /**
     * Include custom order statuses in reports
     */
    public function include_custom_order_statuses_in_reports($statuses) {
        $statuses[] = 'confirmed';
        $statuses[] = 'support';
        $statuses[] = 'unclear';
        return $statuses;
    }
    
    public function add_admin_menu() {
        // Custom robot icon as SVG data URI
        $robot_icon = 'data:image/svg+xml;base64,' . base64_encode('
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2ZM21 9V7L15 1H9V3H7V1H5V3H3V5H1V7H3V9H1V11H3V13H1V15H3V17H1V19H3V21H5V23H7V21H9V23H11V21H13V23H15V21H17V23H19V21H21V19H23V17H21V15H23V13H21V11H23V9H21ZM9 8H15C16.1 8 17 8.9 17 10V14C17 15.1 16.1 16 15 16H9C7.9 16 7 15.1 7 14V10C7 8.9 7.9 8 9 8ZM9 10V14H15V10H9ZM9.5 11H10.5V13H9.5V11ZM13.5 11H14.5V13H13.5V11Z"/>
            </svg>
        ');
        
        add_menu_page(
            'Vrobo',
            'Vrobo',
            'manage_options',
            'vrobo-woocommerce',
            array($this, 'admin_page'),
            $robot_icon,
            56
        );
        
        add_submenu_page(
            'vrobo-woocommerce',
            'Orders',
            'Orders',
            'manage_options',
            'vrobo-woocommerce',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'vrobo-woocommerce',
            'Settings',
            'Settings',
            'manage_options',
            'vrobo-woocommerce-settings',
            array($this, 'settings_page')
        );
    }
    
    public function admin_page() {
        include VROBO_WC_PLUGIN_PATH . 'templates/admin-orders-table.php';
    }
    
    public function settings_page() {
        include VROBO_WC_PLUGIN_PATH . 'templates/admin-settings.php';
    }
    
    public function enqueue_scripts() {
        wp_enqueue_style('vrobo-wc-style', VROBO_WC_PLUGIN_URL . 'assets/css/style.css', array(), VROBO_WC_VERSION);
        wp_enqueue_script('vrobo-wc-script', VROBO_WC_PLUGIN_URL . 'assets/js/script.js', array('jquery'), VROBO_WC_VERSION, true);
    }
    
    public function admin_enqueue_scripts() {
        // Only enqueue on plugin admin pages
        $current_screen = get_current_screen();
        if (!$current_screen || strpos($current_screen->id, 'vrobo') === false) {
            return;
        }
        
        wp_enqueue_style('vrobo-wc-admin-style', VROBO_WC_PLUGIN_URL . 'assets/css/admin-style.css', array(), VROBO_WC_VERSION);
        wp_enqueue_script('vrobo-wc-admin-script', VROBO_WC_PLUGIN_URL . 'assets/js/admin-script.js', array('jquery'), VROBO_WC_VERSION, true);
        
        // Add nonce for AJAX security
        wp_localize_script('vrobo-wc-admin-script', 'vrobo_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'admin_url' => admin_url(),
            'nonce' => wp_create_nonce('vrobo_nonce')
        ));
        
        // Add custom CSS for order status colors
        wp_add_inline_style('vrobo-wc-admin-style', '
            .order-status.status-confirmed,
            mark.order-status.status-confirmed {
                background: #c8e6c9 !important;
                color: #2e7d32 !important;
            }
            .order-status.status-support,
            mark.order-status.status-support {
                background: #fff3e0 !important;
                color: #ef6c00 !important;
            }
            .order-status.status-unclear,
            mark.order-status.status-unclear {
                background: #ffebee !important;
                color: #c62828 !important;
            }
        ');
    }
}

// Initialize the plugin
new Vrobo();

// Activation hook
register_activation_hook(__FILE__, 'vrobo_wc_activate');
function vrobo_wc_activate() {
    // Suppress any output during activation
    ob_start();
    
    try {
        // Create database tables
        Vrobo_Database::create_tables();
        
        // Flush rewrite rules to ensure custom post statuses work
        flush_rewrite_rules();
    } catch (Exception $e) {
        // Log error but don't output anything
        error_log('Vrobo activation error: ' . $e->getMessage());
    }
    
    // Clean any output buffer
    ob_end_clean();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'vrobo_wc_deactivate');
function vrobo_wc_deactivate() {
    // Clean up if needed
    flush_rewrite_rules();
} 