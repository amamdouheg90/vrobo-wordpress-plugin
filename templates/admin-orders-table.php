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
            <h3>Search</h3>
            <div class="vrobo-search-wrapper">
                <input type="text" id="vrobo-search" placeholder="Search by order #, name, phone, or email" class="vrobo-search-input" value="" />
                <button type="button" id="vrobo-search-btn" class="vrobo-search-button">Search</button>
                <button type="button" id="vrobo-clear-btn" class="vrobo-clear-button">Clear</button>
            </div>
        </div>
        
        <div class="vrobo-status-section">
            <h3>Status</h3>
            <div class="vrobo-status-filters">
                <button type="button" class="vrobo-status-filter active" data-status="">Reset</button>
                <button type="button" class="vrobo-status-filter" data-status="confirmed">Confirmed</button>
                <button type="button" class="vrobo-status-filter" data-status="cancelled">Cancelled</button>
                <button type="button" class="vrobo-status-filter" data-status="support">Support</button>
                <button type="button" class="vrobo-status-filter" data-status="unclear">Unclear</button>
            </div>
        </div>
    </div>
    
    <!-- Loading indicator -->
    <div id="vrobo-loading" class="vrobo-loading" style="display: none;">
        <span class="spinner is-active"></span>
        Loading orders...
    </div>
    
    <!-- Orders Table -->
    <div id="vrobo-orders-container" class="vrobo-table-container">
        <table class="vrobo-orders-table" id="vrobo-orders-table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Status</th>
                    <th>Last Action</th>
                    <th>Tags</th>
                    <th>Note</th>
                    <th>Created</th>
                    <th>Last Update</th>
                    <th>Actions</th>
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
            <span>Show</span>
            <select id="vrobo-per-page" class="vrobo-per-page-select">
                <option value="10">10</option>
                <option value="20" selected>20</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
            <span>items per page</span>
        </div>
        <div class="vrobo-pagination-controls" id="vrobo-pagination-controls">
            <!-- Pagination will be generated here -->
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    let currentPage = 1;
    let currentSearch = '';
    let currentPerPage = 20;
    let currentStatus = '';
    
    // Load orders on page load
    loadOrders();
    
    // Manual search functionality
    $('#vrobo-search-btn').on('click', function() {
        currentSearch = $('#vrobo-search').val();
        currentPage = 1;
        loadOrders();
    });
    
    // Clear search functionality
    $('#vrobo-clear-btn').on('click', function() {
        $('#vrobo-search').val('');
        currentSearch = '';
        currentPage = 1;
        loadOrders();
    });
    
    // Allow Enter key to trigger search
    $('#vrobo-search').on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            currentSearch = $(this).val();
            currentPage = 1;
            loadOrders();
        }
    });
    
    // Status filter functionality
    $('.vrobo-status-filter').on('click', function() {
        $('.vrobo-status-filter').removeClass('active');
        $(this).addClass('active');
        currentStatus = $(this).data('status');
        currentPage = 1;
        loadOrders();
    });
    
    // Per page selection
    $('#vrobo-per-page').on('change', function() {
        currentPerPage = $(this).val();
        currentPage = 1;
        loadOrders();
    });
    
    // Load orders function
    function loadOrders() {
        $('#vrobo-loading').show();
        $('#vrobo-orders-container').hide();
        
        $.ajax({
            url: vrobo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'vrobo_get_orders_table',
                nonce: vrobo_ajax.nonce,
                page: currentPage,
                per_page: currentPerPage,
                search: currentSearch,
                status: currentStatus
            },
            success: function(response) {
                if (response.success) {
                    displayOrders(response.data.orders);
                    displayPagination(response.data.pagination);
                } else {
                    alert('Error loading orders: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                alert('Error loading orders. Please try again.');
            },
            complete: function() {
                $('#vrobo-loading').hide();
                $('#vrobo-orders-container').show();
            }
        });
    }
    
    // Display orders in table
    function displayOrders(orders) {
        let tbody = $('#vrobo-orders-tbody');
        tbody.empty();
        
        if (orders.length === 0) {
            tbody.append('<tr><td colspan="9" class="no-orders">No orders found.</td></tr>');
            return;
        }
        
        orders.forEach(function(order) {
            let lastAction = getLastAction(order.webhook_status);
            
            let row = `
                <tr>
                    <td>
                        <a href="#" class="order-link" data-order-id="${order.order_id}">#${order.order_id}</a>
                    </td>
                    <td>
                        <div class="customer-info">
                            <div class="customer-name">${order.customer_name || '-'}</div>
                            <div class="customer-email">${order.customer_email || '-'}</div>
                        </div>
                    </td>
                    <td>
                        <span class="order-status-circle ${order.order_status}"></span>
                        ${order.order_status}
                    </td>
                    <td>
                        <span class="last-action-circle ${order.webhook_status}"></span>
                        ${order.last_action || lastAction}
                    </td>
                    <td>${order.tags || '-'}</td>
                    <td>${order.note || '-'}</td>
                    <td>${formatDate(order.created_date)}</td>
                    <td>${formatDate(order.updated_date || order.created_date)}</td>
                    <td>
                        <button class="vrobo-action-btn cancel-order" data-order-id="${order.order_id}" title="Request Cancellation">
                            Cancel
                        </button>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });
    }
    
    // Display pagination
    function displayPagination(pagination) {
        if (pagination.total_pages <= 1) {
            $('#vrobo-pagination-controls').empty();
            return;
        }
        
        let paginationHtml = '';
        
        // Previous button
        if (pagination.page > 1) {
            paginationHtml += `<button class="vrobo-page-btn" data-page="${pagination.page - 1}">&lt;</button>`;
        }
        
        // Page numbers
        let startPage = Math.max(1, pagination.page - 2);
        let endPage = Math.min(pagination.total_pages, pagination.page + 2);
        
        for (let i = startPage; i <= endPage; i++) {
            let activeClass = i === pagination.page ? 'active' : '';
            paginationHtml += `<button class="vrobo-page-btn ${activeClass}" data-page="${i}">${i}</button>`;
        }
        
        // Next button
        if (pagination.page < pagination.total_pages) {
            paginationHtml += `<button class="vrobo-page-btn" data-page="${pagination.page + 1}">&gt;</button>`;
        }
        
        $('#vrobo-pagination-controls').html(paginationHtml);
    }
    
    // Pagination click handlers
    $(document).on('click', '.vrobo-page-btn', function() {
        currentPage = parseInt($(this).data('page'));
        loadOrders();
    });
    
    // Helper functions
    function getLastAction(webhookStatus) {
        const actions = {
            'sent': 'Sent to Vrobo',
            'failed': 'Webhook Failed',
            'pending': 'Pending',
            'skipped': 'Skipped',
            'cancelled': 'Cancelled'
        };
        return actions[webhookStatus] || 'Unknown';
    }
    
    function formatDate(dateString) {
        if (!dateString) return '-';
        let date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        });
    }
    
    // Action button handlers
    $(document).on('click', '.cancel-order', function() {
        let orderId = $(this).data('order-id');
        
        if (confirm('Are you sure you want to cancel this order?')) {
            cancelOrder(orderId);
        }
    });
    
    $(document).on('click', '.order-link', function(e) {
        e.preventDefault();
        let orderId = $(this).data('order-id');
        window.open('<?php echo esc_url(admin_url('post.php?action=edit&post=')); ?>' + orderId, '_blank');
    });
    
    // Cancel order function
    function cancelOrder(orderId) {
        $.ajax({
            url: vrobo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'vrobo_request_cancel',
                nonce: vrobo_ajax.nonce,
                order_id: orderId
            },
            success: function(response) {
                if (response.success) {
                    alert('Order cancelled successfully!');
                    loadOrders(); // Refresh the table
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Error cancelling order. Please try again.');
            }
        });
    }
});
</script>

<style>
/* Reset and base styles */
.wrap {
    margin: 20px 20px 0 2px;
}

/* Filters Container */
.vrobo-filters-container {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 24px;
    margin-bottom: 20px;
}

.vrobo-search-section h3,
.vrobo-status-section h3 {
    margin: 0 0 12px 0;
    font-size: 14px;
    font-weight: 600;
    color: #333;
}

.vrobo-search-wrapper {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-bottom: 20px;
}

.vrobo-search-input {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.vrobo-search-button,
.vrobo-clear-button {
    padding: 8px 16px;
    border: 1px solid #ddd;
    background: #f7f7f7;
    border-radius: 4px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}

.vrobo-search-button {
    background: #2271b1;
    color: white;
    border-color: #2271b1;
}

.vrobo-search-button:hover {
    background: #135e96;
    border-color: #135e96;
}

.vrobo-clear-button:hover {
    background: #e7e7e7;
}

.vrobo-status-filters {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.vrobo-status-filter {
    padding: 6px 16px;
    border: 1px solid #ddd;
    background: #f7f7f7;
    border-radius: 20px;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s;
}

.vrobo-status-filter:hover {
    background: #e7e7e7;
}

.vrobo-status-filter.active {
    background: #2271b1;
    color: white;
    border-color: #2271b1;
}

/* Table Container */
.vrobo-table-container {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 20px;
}

.vrobo-orders-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.vrobo-orders-table th {
    background: #f8f9fa;
    padding: 12px 16px;
    text-align: left;
    font-weight: 600;
    color: #555;
    border-bottom: 1px solid #ddd;
    font-size: 13px;
}

.vrobo-orders-table td {
    padding: 16px;
    border-bottom: 1px solid #eee;
    vertical-align: top;
}

.vrobo-orders-table tr:hover {
    background: #f8f9fa;
}

/* Order link */
.order-link {
    color: #2271b1;
    text-decoration: none;
    font-weight: 600;
}

.order-link:hover {
    text-decoration: underline;
}

/* Customer info */
.customer-info {
    line-height: 1.4;
}

.customer-name {
    font-weight: 500;
    color: #333;
}

.customer-email {
    font-size: 12px;
    color: #666;
}

/* Status circles */
.order-status-circle,
.last-action-circle {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 8px;
}

.order-status-circle.completed,
.last-action-circle.sent {
    background: #46b450;
}

.order-status-circle.processing {
    background: #00a0d2;
}

.order-status-circle.pending,
.last-action-circle.pending {
    background: #ffb900;
}

.order-status-circle.cancelled,
.last-action-circle.failed {
    background: #dc3232;
}

.last-action-circle.skipped {
    background: #666;
}

.last-action-circle.cancelled {
    background: #dc3232;
}

/* Action button */
.vrobo-action-btn {
    padding: 6px 12px;
    border: 1px solid #dc3232;
    background: #fff;
    color: #dc3232;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.2s;
}

.vrobo-action-btn:hover {
    background: #dc3232;
    color: #fff;
}

/* Pagination */
.vrobo-pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
}

.vrobo-pagination-info {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #666;
}

.vrobo-per-page-select {
    padding: 4px 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.vrobo-pagination-controls {
    display: flex;
    gap: 4px;
}

.vrobo-page-btn {
    padding: 6px 12px;
    border: 1px solid #ddd;
    background: #fff;
    cursor: pointer;
    border-radius: 4px;
    font-size: 14px;
}

.vrobo-page-btn:hover {
    background: #f0f0f0;
}

.vrobo-page-btn.active {
    background: #2271b1;
    color: white;
    border-color: #2271b1;
}

/* Loading */
.vrobo-loading {
    text-align: center;
    padding: 40px;
    color: #666;
}

/* No orders */
.no-orders {
    text-align: center;
    padding: 40px;
    color: #666;
    font-style: italic;
}

/* Modal styles - REMOVED: Now using simple confirm dialog */
</style> 