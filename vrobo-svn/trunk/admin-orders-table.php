<?php
/**
 * Admin Orders Table Template - Simplified for Viewing and Cancel Requests
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get database instance
$db = new Vrobo_Database();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <!-- Search and Filters -->
    <div class="vrobo-filters-container">
        <div class="vrobo-search-section">
            <h3><?php esc_html_e('Search', 'vrobo'); ?></h3>
            <div class="vrobo-search-wrapper">
                <input type="text" id="vrobo-search" placeholder="<?php esc_attr_e('Search by order #, name, phone, or email', 'vrobo'); ?>" class="vrobo-search-input" value="" />
                <button type="button" id="vrobo-search-btn" class="vrobo-search-button"><?php esc_html_e('Search', 'vrobo'); ?></button>
                <button type="button" id="vrobo-clear-btn" class="vrobo-clear-button"><?php esc_html_e('Clear', 'vrobo'); ?></button>
            </div>
        </div>
        
        <div class="vrobo-status-section">
            <h3><?php esc_html_e('Status', 'vrobo'); ?></h3>
            <div class="vrobo-status-filters">
                <button type="button" class="vrobo-status-filter active" data-status=""><?php esc_html_e('Reset', 'vrobo'); ?></button>
                <button type="button" class="vrobo-status-filter" data-status="confirmed"><?php esc_html_e('Confirmed', 'vrobo'); ?></button>
                <button type="button" class="vrobo-status-filter" data-status="cancelled"><?php esc_html_e('Cancelled', 'vrobo'); ?></button>
                <button type="button" class="vrobo-status-filter" data-status="support"><?php esc_html_e('Support', 'vrobo'); ?></button>
                <button type="button" class="vrobo-status-filter" data-status="unclear"><?php esc_html_e('Unclear', 'vrobo'); ?></button>
            </div>
        </div>
    </div>
    
    <!-- Loading indicator -->
    <div id="vrobo-loading" class="vrobo-loading" style="display: none;">
        <span class="spinner is-active"></span>
        <?php esc_html_e('Loading orders...', 'vrobo'); ?>
    </div>
    
    <!-- Orders Table -->
    <div id="vrobo-orders-container" class="vrobo-table-container">
        <table class="vrobo-orders-table" id="vrobo-orders-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Order', 'vrobo'); ?></th>
                    <th><?php esc_html_e('Customer', 'vrobo'); ?></th>
                    <th><?php esc_html_e('Status', 'vrobo'); ?></th>
                    <th><?php esc_html_e('Last Action', 'vrobo'); ?></th>
                    <th><?php esc_html_e('Tags', 'vrobo'); ?></th>
                    <th><?php esc_html_e('Note', 'vrobo'); ?></th>
                    <th><?php esc_html_e('Created', 'vrobo'); ?></th>
                    <th><?php esc_html_e('Last Update', 'vrobo'); ?></th>
                    <th><?php esc_html_e('Actions', 'vrobo'); ?></th>
                </tr>
            </thead>
            <tbody id="vrobo-orders-tbody">
                <!-- Orders will be loaded here via AJAX -->
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <div class="vrobo-pagination-container">
        <div class="vrobo-pagination-info">
            <span><?php esc_html_e('Show', 'vrobo'); ?></span>
            <select id="vrobo-per-page" class="vrobo-per-page-select">
                <option value="10">10</option>
                <option value="20" selected>20</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
            <span><?php esc_html_e('items per page', 'vrobo'); ?></span>
        </div>
        <div class="vrobo-pagination-controls" id="vrobo-pagination-controls">
            <!-- Pagination will be generated here -->
        </div>
    </div>
</div> 