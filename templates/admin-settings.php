<?php
/**
 * Admin Settings Template - Simplified for API Key Configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

// Clear any existing API key value to ensure clean start (one-time cleanup)
if (!get_option('vrobo_wc_api_key_cleared', false)) {
    update_option('vrobo_wc_api_key', '');
    update_option('vrobo_wc_api_key_validated', 0);
    update_option('vrobo_wc_api_key_cleared', true);
}

// Handle form submission
if (isset($_POST['connect_api_key']) && isset($_POST['vrobo_settings_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['vrobo_settings_nonce'])), 'vrobo_settings_action')) {
    if (isset($_POST['vrobo_api_key'])) {
        $api_key = sanitize_text_field(wp_unslash($_POST['vrobo_api_key']));
        
        if (!empty($api_key)) {
            update_option('vrobo_wc_api_key', $api_key);
            
            // Send registration to external webhook and wait for validation
            $registration_result = vrobo_send_registration($api_key);
            
            if ($registration_result['success']) {
                if ($registration_result['is_valid']) {
                    update_option('vrobo_wc_api_key_validated', 1);
                    echo '<div class="notice notice-success"><p>API key connected and validated successfully!</p></div>';
                } else {
                    update_option('vrobo_wc_api_key_validated', 0);
                    echo '<div class="notice notice-error"><p>API key saved but validation failed. The API key is not valid.</p></div>';
                }
            } else {
                update_option('vrobo_wc_api_key_validated', 0);
                echo '<div class="notice notice-warning"><p>API key saved but validation request failed: ' . esc_html($registration_result['message']) . '</p></div>';
            }
        } else {
            // Clear API key if empty
            update_option('vrobo_wc_api_key', '');
            update_option('vrobo_wc_api_key_validated', 0);
            echo '<div class="notice notice-info"><p>API key cleared.</p></div>';
        }
    }
}

// Handle COD settings separately
if (isset($_POST['submit_cod']) && isset($_POST['vrobo_cod_settings_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['vrobo_cod_settings_nonce'])), 'vrobo_cod_settings_action')) {
    // Update exclude paid orders setting
    $exclude_paid_orders = isset($_POST['vrobo_exclude_paid_orders']) ? 1 : 0;
    update_option('vrobo_wc_exclude_paid_orders', $exclude_paid_orders);
    echo '<div class="notice notice-success"><p>COD settings saved successfully!</p></div>';
}

// Generate WooCommerce API credentials if they don't exist
$wc_consumer_key = get_option('vrobo_wc_consumer_key');
$wc_consumer_secret = get_option('vrobo_wc_consumer_secret');

if (empty($wc_consumer_key) || empty($wc_consumer_secret)) {
    $wc_credentials = vrobo_generate_wc_api_credentials();
    if ($wc_credentials) {
        update_option('vrobo_wc_consumer_key', $wc_credentials['consumer_key']);
        update_option('vrobo_wc_consumer_secret', $wc_credentials['consumer_secret']);
        $wc_consumer_key = $wc_credentials['consumer_key'];
        $wc_consumer_secret = $wc_credentials['consumer_secret'];
    }
}

// Get current settings
$api_key = get_option('vrobo_wc_api_key', '');
$api_key_validated = get_option('vrobo_wc_api_key_validated', 0);
$exclude_paid_orders = get_option('vrobo_wc_exclude_paid_orders', 0);

/**
 * Send registration data to external webhook and validate API key
 */
function vrobo_send_registration($api_key) {
    $wc_consumer_key = get_option('vrobo_wc_consumer_key');
    $wc_consumer_secret = get_option('vrobo_wc_consumer_secret');
    
    $registration_data = array(
        'domain' => home_url(),
        'api_key' => $api_key,
        'consumer_key' => $wc_consumer_key,
        'consumer_secret' => $wc_consumer_secret,
        'wc_version' => WC()->version,
        'plugin_version' => VROBO_WC_VERSION,
        'registered_at' => current_time('c')
    );
    
    $response = wp_remote_post('https://n8n.flq.me/webhook/f8f04b03-3592-47f1-9db5-478910137a64', array(
        'body' => json_encode($registration_data),
        'headers' => array(
            'Content-Type' => 'application/json',
            'User-Agent' => 'Vrobo-WooCommerce/' . VROBO_WC_VERSION
        ),
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) {
        return array('success' => false, 'message' => $response->get_error_message(), 'is_valid' => false);
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    if ($response_code >= 200 && $response_code < 300) {
        $response_data = json_decode($response_body, true);
        
        // Check if response follows the expected format
        if (isset($response_data['topic']) && $response_data['topic'] === 'AUTH') {
            if (isset($response_data['payload']['isValid'])) {
                $is_valid = $response_data['payload']['isValid'];
                
                return array(
                    'success' => true, 
                    'is_valid' => $is_valid,
                    'message' => 'API key validation completed - ' . ($is_valid ? 'Valid' : 'Invalid')
                );
            } else {
                return array('success' => false, 'message' => 'Response missing isValid field', 'is_valid' => false);
            }
        } else {
            return array('success' => false, 'message' => 'Response missing topic AUTH field', 'is_valid' => false);
        }
    } else {
        return array('success' => false, 'message' => 'HTTP ' . $response_code, 'is_valid' => false);
    }
}

/**
 * Generate WooCommerce API credentials
 */
function vrobo_generate_wc_api_credentials() {
    // Generate random key and secret
    $consumer_key = 'ck_' . wc_rand_hash();
    $consumer_secret = 'cs_' . wc_rand_hash();
    
    // Check if user has permission to create API keys
    if (!current_user_can('manage_woocommerce')) {
        return false;
    }
    
    try {
        global $wpdb;
        
        // This direct database call is necessary for WooCommerce API key creation
        // as WooCommerce doesn't provide a public API for programmatic key generation.
        // This is the standard method used by WooCommerce itself for API key creation.
        // No caching used as this is a one-time API key creation operation.
        $result = $wpdb->insert(
            $wpdb->prefix . 'woocommerce_api_keys',
            array(
                'user_id' => get_current_user_id(),
                'description' => 'Vrobo Integration',
                'permissions' => 'read_write',
                'consumer_key' => wc_api_hash($consumer_key),
                'consumer_secret' => $consumer_secret,
                'nonces' => maybe_serialize(array()),
                'last_access' => null,
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            return array(
                'consumer_key' => $consumer_key,
                'consumer_secret' => $consumer_secret
            );
        }
        
    } catch (Exception $e) {
        // Return false on error without exposing details
        return false;
    }
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="vrobo-settings-container">
        
        <!-- API Configuration -->
        <div class="vrobo-settings-section">
            <h2>Vrobo API Key Connection</h2>
            <p>Enter your Vrobo API key to connect and validate your account.</p>
            
            <form method="post" action="">
                <?php wp_nonce_field('vrobo_settings_action', 'vrobo_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="vrobo_api_key">Vrobo API Key</label>
                        </th>
                        <td>
                            <input type="text" id="vrobo_api_key" name="vrobo_api_key" 
                                   value="<?php echo esc_attr($api_key); ?>" class="regular-text" 
                                   placeholder="Enter your Vrobo API key" />
                            <p class="description">
                                Enter the API key provided by Vrobo to enable order synchronization.
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Connect API Key', 'primary', 'connect_api_key'); ?>
            </form>
        </div>
        
        <!-- COD Settings -->
        <div class="vrobo-settings-section">
            <h2>Order Filtering Settings</h2>
            <p>Configure which orders should be sent to Vrobo.</p>
            
            <form method="post" action="">
                <?php wp_nonce_field('vrobo_cod_settings_action', 'vrobo_cod_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="vrobo_exclude_paid_orders">Send Only COD Orders</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="vrobo_exclude_paid_orders" name="vrobo_exclude_paid_orders" value="1" 
                                       <?php checked($exclude_paid_orders, 1); ?> />
                                Only send COD orders to webhook
                            </label>
                            <p class="description">
                                If enabled, only orders with Cash on Delivery (COD) payment method will be sent to the Vrobo webhook. Orders paid via other payment methods will be skipped.
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Save COD Settings', 'secondary', 'submit_cod'); ?>
            </form>
        </div>
        
    </div>
</div>

<style>
.vrobo-settings-container {
    max-width: 800px;
}

.vrobo-settings-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.vrobo-settings-section h2 {
    margin-top: 0;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

.vrobo-integration-info ol, .vrobo-integration-info ul {
    margin-left: 20px;
}

.vrobo-integration-info li {
    margin-bottom: 8px;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Simple form validation - no loading states since we're using regular POST
    $('form').on('submit', function(e) {
        var form = $(this);
        
        // Only validate the API key form
        if (form.find('input[name="connect_api_key"]').length > 0) {
            var apiKey = $('#vrobo_api_key').val().trim();
            
            if (apiKey === '') {
                alert('Please enter an API key before connecting.');
                e.preventDefault();
                return false;
            }
        }
    });
});
</script> 