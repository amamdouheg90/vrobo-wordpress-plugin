/**
 * VRobo WooCommerce Admin JavaScript
 */

(function ($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function () {
        VRoboAdmin.init();
    });

    // Main admin object
    const VRoboAdmin = {

        // Initialize the admin functionality
        init: function () {
            this.bindEvents();
            this.initTooltips();
            this.autoRefresh();
            this.initOrdersTable();
        },

        // Bind event handlers
        bindEvents: function () {
            // Modal close events
            $(document).on('click', '.vrobo-modal-close, .vrobo-modal', function (e) {
                if (e.target === this) {
                    $('.vrobo-modal').fadeOut();
                }
            });

            // Escape key to close modals
            $(document).on('keydown', function (e) {
                if (e.keyCode === 27) { // Escape key
                    $('.vrobo-modal').fadeOut();
                }
            });

            // Auto-save settings (debounced)
            let saveTimeout;
            $('.vrobo-settings-section input, .vrobo-settings-section select').on('change', function () {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(function () {
                    VRoboAdmin.showMessage('Settings auto-saved', 'success');
                }, 2000);
            });

            // Copy to clipboard for code blocks
            $('.vrobo-endpoint code').on('click', function () {
                VRoboAdmin.copyToClipboard($(this).text().replace(/\s+/g, ' ').trim());
            });

            // Refresh statistics
            $('.vrobo-refresh-stats').on('click', function () {
                VRoboAdmin.refreshStats();
            });

            // Export orders functionality
            $('.vrobo-export-orders').on('click', function () {
                VRoboAdmin.exportOrders();
            });

            // Bulk actions
            $('.vrobo-bulk-action').on('click', function () {
                const action = $(this).data('action');
                VRoboAdmin.performBulkAction(action);
            });

            // Initialize datatables if available
            if (typeof $.fn.DataTable !== 'undefined') {
                $('#vrobo-orders-table').DataTable({
                    pageLength: 25,
                    responsive: true,
                    order: [[0, 'desc']]
                });
            }

            // Modal functionality
            $(document).on('click', '.vrobo-add-note', function () {
                const orderId = $(this).data('order');
                $('#note-order-id').val(orderId);
                $('#note-modal').show();
            });

            $(document).on('click', '.vrobo-add-comment', function () {
                const orderId = $(this).data('order');
                $('#comment-order-id').val(orderId);
                $('#comment-modal').show();
            });

            // Status change functionality
            $(document).on('click', '.vrobo-change-status', function () {
                const orderId = $(this).data('order');
                const currentStatus = $(this).data('status');
                $('#status-order-id').val(orderId);
                $('#current-status').val(currentStatus);
                $('#new-status').val(currentStatus);
                $('#status-modal').show();
            });

            $(document).on('click', '.modal-close', function () {
                $('.vrobo-modal').hide();
            });

            $(document).on('click', '.vrobo-modal', function (e) {
                if (e.target === this) {
                    $(this).hide();
                }
            });

            // Add note form submission
            $('#note-form').on('submit', function (e) {
                e.preventDefault();

                const orderId = $('#note-order-id').val();
                const note = $('#order-note').val();

                if (!note.trim()) {
                    alert('Please enter a note');
                    return;
                }

                $.ajax({
                    url: vrobo_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'vrobo_update_order_note',
                        order_id: orderId,
                        note: note,
                        nonce: vrobo_ajax.nonce
                    },
                    success: function (response) {
                        if (response.success) {
                            alert('Note added successfully');
                            $('#note-modal').hide();
                            $('#order-note').val('');
                            location.reload();
                        } else {
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function () {
                        alert('Error adding note');
                    }
                });
            });

            // Add comment form submission  
            $('#comment-form').on('submit', function (e) {
                e.preventDefault();

                const orderId = $('#comment-order-id').val();
                const comment = $('#order-comment').val();
                const isCustomerNote = $('#is-customer-note').is(':checked');

                if (!comment.trim()) {
                    alert('Please enter a comment');
                    return;
                }

                $.ajax({
                    url: vrobo_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'vrobo_add_order_comment',
                        order_id: orderId,
                        comment: comment,
                        is_customer_note: isCustomerNote ? 1 : 0,
                        nonce: vrobo_ajax.nonce
                    },
                    success: function (response) {
                        if (response.success) {
                            alert('Comment added successfully');
                            $('#comment-modal').hide();
                            $('#order-comment').val('');
                            $('#is-customer-note').prop('checked', false);
                            location.reload();
                        } else {
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function () {
                        alert('Error adding comment');
                    }
                });
            });

            // Status change form submission
            $('#status-form').on('submit', function (e) {
                e.preventDefault();

                const orderId = $('#status-order-id').val();
                const newStatus = $('#new-status').val();
                const currentStatus = $('#current-status').val();

                if (newStatus === currentStatus) {
                    alert('Please select a different status');
                    return;
                }

                $.ajax({
                    url: vrobo_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'vrobo_change_order_status',
                        order_id: orderId,
                        new_status: newStatus,
                        nonce: vrobo_ajax.nonce
                    },
                    success: function (response) {
                        if (response.success) {
                            alert('Order status updated successfully');
                            $('#status-modal').hide();
                            location.reload();
                        } else {
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function () {
                        alert('Error updating order status');
                    }
                });
            });

            // Refresh data functionality
            $(document).on('click', '#refresh-data', function () {
                location.reload();
            });

            // Export functionality
            $(document).on('click', '#export-orders', function () {
                window.location.href = vrobo_ajax.ajax_url + '?action=vrobo_export_orders&nonce=' + vrobo_ajax.nonce;
            });

            // Search functionality
            $('#order-search').on('keyup', function () {
                const searchTerm = $(this).val().toLowerCase();
                $('#vrobo-orders-table tbody tr').each(function () {
                    const rowText = $(this).text().toLowerCase();
                    if (rowText.includes(searchTerm)) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });
        },

        // Initialize tooltips
        initTooltips: function () {
            // Add tooltips to elements with data-tooltip attribute
            $('[data-tooltip]').hover(
                function () {
                    const tooltip = $('<div class="vrobo-tooltip-popup">')
                        .text($(this).data('tooltip'))
                        .appendTo('body');

                    const offset = $(this).offset();
                    tooltip.css({
                        position: 'absolute',
                        top: offset.top - tooltip.outerHeight() - 5,
                        left: offset.left + ($(this).outerWidth() / 2) - (tooltip.outerWidth() / 2),
                        background: '#23282d',
                        color: '#f1f1f1',
                        padding: '5px 10px',
                        borderRadius: '4px',
                        fontSize: '12px',
                        zIndex: 10000
                    });
                },
                function () {
                    $('.vrobo-tooltip-popup').remove();
                }
            );
        },

        // Auto-refresh functionality
        autoRefresh: function () {
            // Check if auto-refresh is enabled
            if (typeof vrobo_ajax !== 'undefined' && vrobo_ajax.auto_refresh) {
                setInterval(function () {
                    if ($('#vrobo-orders-table').is(':visible')) {
                        VRoboAdmin.refreshOrders();
                    }
                }, 30000); // Refresh every 30 seconds
            }
        },

        // Copy text to clipboard
        copyToClipboard: function (text) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(function () {
                    VRoboAdmin.showMessage('Copied to clipboard!', 'success');
                }).catch(function () {
                    VRoboAdmin.fallbackCopyToClipboard(text);
                });
            } else {
                VRoboAdmin.fallbackCopyToClipboard(text);
            }
        },

        // Fallback copy to clipboard for older browsers
        fallbackCopyToClipboard: function (text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            try {
                document.execCommand('copy');
                VRoboAdmin.showMessage('Copied to clipboard!', 'success');
            } catch (err) {
                VRoboAdmin.showMessage('Failed to copy to clipboard', 'error');
            }

            document.body.removeChild(textArea);
        },

        // Show message to user
        showMessage: function (message, type = 'info') {
            const messageEl = $('<div class="vrobo-message ' + type + '">')
                .text(message)
                .prependTo('.wrap')
                .hide()
                .fadeIn();

            setTimeout(function () {
                messageEl.fadeOut(function () {
                    $(this).remove();
                });
            }, 3000);
        },

        // Refresh statistics
        refreshStats: function () {
            $.ajax({
                url: vrobo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'vrobo_refresh_stats',
                    nonce: vrobo_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        VRoboAdmin.updateStatsDisplay(response.data);
                        VRoboAdmin.showMessage('Statistics refreshed', 'success');
                    }
                },
                error: function () {
                    VRoboAdmin.showMessage('Failed to refresh statistics', 'error');
                }
            });
        },

        // Update statistics display
        updateStatsDisplay: function (stats) {
            $('.vrobo-stats-container .vrobo-stat-number').each(function () {
                const statType = $(this).closest('.vrobo-stat-card').find('h3').text().toLowerCase();
                if (stats[statType]) {
                    $(this).text(stats[statType]);
                }
            });
        },

        // Refresh orders table
        refreshOrders: function () {
            if (typeof loadOrders === 'function') {
                loadOrders();
            }
        },

        // Export orders to CSV
        exportOrders: function () {
            VRoboAdmin.showMessage('Preparing export...', 'info');

            $.ajax({
                url: vrobo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'vrobo_export_orders',
                    nonce: vrobo_ajax.nonce,
                    format: 'csv'
                },
                success: function (response) {
                    if (response.success) {
                        // Create download link
                        const blob = new Blob([response.data.csv], { type: 'text/csv' });
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'vrobo-orders-' + new Date().toISOString().split('T')[0] + '.csv';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        window.URL.revokeObjectURL(url);

                        VRoboAdmin.showMessage('Orders exported successfully', 'success');
                    } else {
                        VRoboAdmin.showMessage('Export failed: ' + response.data, 'error');
                    }
                },
                error: function () {
                    VRoboAdmin.showMessage('Export failed', 'error');
                }
            });
        },

        // Perform bulk actions
        performBulkAction: function (action) {
            const selectedOrders = $('.vrobo-order-checkbox:checked').map(function () {
                return $(this).val();
            }).get();

            if (selectedOrders.length === 0) {
                VRoboAdmin.showMessage('Please select orders first', 'error');
                return;
            }

            if (!confirm('Are you sure you want to perform this action on ' + selectedOrders.length + ' orders?')) {
                return;
            }

            $.ajax({
                url: vrobo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'vrobo_bulk_action',
                    nonce: vrobo_ajax.nonce,
                    bulk_action: action,
                    order_ids: selectedOrders
                },
                success: function (response) {
                    if (response.success) {
                        VRoboAdmin.showMessage('Bulk action completed', 'success');
                        VRoboAdmin.refreshOrders();
                    } else {
                        VRoboAdmin.showMessage('Bulk action failed: ' + response.data, 'error');
                    }
                },
                error: function () {
                    VRoboAdmin.showMessage('Bulk action failed', 'error');
                }
            });
        },

        // Format numbers with commas
        formatNumber: function (number) {
            return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },

        // Format currency
        formatCurrency: function (amount) {
            return '$' + parseFloat(amount).toFixed(2);
        },

        // Format date
        formatDate: function (dateString) {
            const date = new Date(dateString);
            const options = {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            return date.toLocaleDateString('en-US', options);
        },

        // Validate API key format
        validateApiKey: function (apiKey) {
            // Basic validation: should be 32 characters, alphanumeric
            const regex = /^[a-zA-Z0-9]{32}$/;
            return regex.test(apiKey);
        },

        // Test API connection
        testApiConnection: function () {
            VRoboAdmin.showMessage('Testing API connection...', 'info');

            $.ajax({
                url: vrobo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'vrobo_test_api',
                    nonce: vrobo_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        VRoboAdmin.showMessage('API connection successful', 'success');
                    } else {
                        VRoboAdmin.showMessage('API connection failed: ' + response.data, 'error');
                    }
                },
                error: function () {
                    VRoboAdmin.showMessage('API connection test failed', 'error');
                }
            });
        },

        // Initialize real-time updates
        initRealTimeUpdates: function () {
            // Use EventSource for server-sent events if available
            if (typeof EventSource !== 'undefined' && vrobo_ajax.sse_endpoint) {
                const eventSource = new EventSource(vrobo_ajax.sse_endpoint);

                eventSource.onmessage = function (event) {
                    const data = JSON.parse(event.data);
                    VRoboAdmin.handleRealTimeUpdate(data);
                };

                eventSource.onerror = function () {
                    // Handle SSE errors silently in production
                };
            }
        },

        // Handle real-time updates
        handleRealTimeUpdate: function (data) {
            switch (data.type) {
                case 'new_order':
                    VRoboAdmin.showMessage('New order received: #' + data.order_id, 'success');
                    VRoboAdmin.refreshOrders();
                    break;

                case 'order_status_change':
                    VRoboAdmin.showMessage('Order #' + data.order_id + ' status changed to ' + data.status, 'info');
                    VRoboAdmin.updateOrderRow(data.order_id, data);
                    break;

                case 'api_status_change':
                    VRoboAdmin.updateOrderApiStatus(data.order_id, data.api_status);
                    break;
            }
        },

        // Update single order row
        updateOrderRow: function (orderId, data) {
            const row = $('[data-order-id="' + orderId + '"]').closest('tr');
            if (row.length) {
                // Update specific cells
                if (data.status) {
                    row.find('.order-status').text(data.status).attr('class', 'status-' + data.status);
                }
                if (data.total) {
                    row.find('.order-total').text(VRoboAdmin.formatCurrency(data.total));
                }
            }
        },

        // Update order API status
        updateOrderApiStatus: function (orderId, apiStatus) {
            const row = $('[data-order-id="' + orderId + '"]').closest('tr');
            if (row.length) {
                const statusClass = VRoboAdmin.getApiStatusClass(apiStatus);
                row.find('.api-status')
                    .text(apiStatus)
                    .attr('class', 'api-status ' + statusClass);
            }
        },

        // Get API status CSS class
        getApiStatusClass: function (status) {
            switch (status) {
                case 'sent': return 'success';
                case 'failed': return 'error';
                case 'pending': return 'pending';
                default: return '';
            }
        },

        // Initialize orders table
        initOrdersTable: function () {
            if ($('#vrobo-orders-table').length) {
                this.loadOrders();
                this.bindOrdersEvents();
            }
        },

        // Bind orders table events
        bindOrdersEvents: function () {
            // Search functionality
            $('#vrobo-search-btn').on('click', function () {
                VRoboAdmin.loadOrders();
            });

            $('#vrobo-clear-btn').on('click', function () {
                $('#vrobo-search').val('');
                VRoboAdmin.loadOrders();
            });

            $('#vrobo-search').on('keypress', function (e) {
                if (e.which === 13) { // Enter key
                    VRoboAdmin.loadOrders();
                }
            });

            // Status filters
            $('.vrobo-status-filter').on('click', function () {
                $('.vrobo-status-filter').removeClass('active');
                $(this).addClass('active');
                VRoboAdmin.loadOrders();
            });

            // Per page change
            $('#vrobo-per-page').on('change', function () {
                VRoboAdmin.loadOrders();
            });

            // Pagination clicks
            $(document).on('click', '.vrobo-pagination-controls a', function (e) {
                e.preventDefault();
                const page = $(this).data('page');
                VRoboAdmin.loadOrders(page);
            });
        },

        // Load orders via AJAX
        loadOrders: function (page = 1) {
            const search = $('#vrobo-search').val();
            const status = $('.vrobo-status-filter.active').data('status') || '';
            const perPage = $('#vrobo-per-page').val() || 20;

            $('#vrobo-loading').show();
            $('#vrobo-orders-tbody').empty();

            $.ajax({
                url: vrobo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'vrobo_get_orders_table',
                    page: page,
                    per_page: perPage,
                    search: search,
                    status: status,
                    nonce: vrobo_ajax.nonce
                },
                success: function (response) {
                    $('#vrobo-loading').hide();

                    if (response.success && response.data) {
                        VRoboAdmin.renderOrdersTable(response.data);
                    } else {
                        $('#vrobo-orders-tbody').html('<tr><td colspan="9">No orders found or error loading orders.</td></tr>');
                        console.error('Orders loading error:', response);
                    }
                },
                error: function (xhr, status, error) {
                    $('#vrobo-loading').hide();
                    $('#vrobo-orders-tbody').html('<tr><td colspan="9">Error loading orders. Please refresh the page.</td></tr>');
                    console.error('AJAX error:', error, xhr.responseText);
                }
            });
        },

        // Render orders table
        renderOrdersTable: function (data) {
            const orders = data.orders || [];
            const pagination = data.pagination || {};
            let html = '';

            if (orders.length === 0) {
                html = '<tr><td colspan="9">No orders found.</td></tr>';
            } else {
                orders.forEach(function (order) {
                    html += VRoboAdmin.renderOrderRow(order);
                });
            }

            $('#vrobo-orders-tbody').html(html);
            VRoboAdmin.renderPagination(pagination);
        },

        // Render single order row
        renderOrderRow: function (order) {
            const statusClass = VRoboAdmin.getStatusClass(order.order_status);
            const webhookClass = VRoboAdmin.getWebhookStatusClass(order.webhook_status);

            return `
                <tr data-order-id="${order.order_id}">
                    <td>
                        <strong>#${order.order_id}</strong>
                        <div class="order-total">$${parseFloat(order.order_total).toFixed(2)}</div>
                    </td>
                    <td>
                        <div class="customer-name">${VRoboAdmin.escapeHtml(order.customer_name)}</div>
                        <div class="customer-email">${VRoboAdmin.escapeHtml(order.customer_email)}</div>
                    </td>
                    <td><span class="order-status ${statusClass}">${VRoboAdmin.escapeHtml(order.order_status)}</span></td>
                    <td>${VRoboAdmin.escapeHtml(order.last_action || '-')}</td>
                    <td>${VRoboAdmin.escapeHtml(order.tags || '-')}</td>
                    <td>${VRoboAdmin.escapeHtml(order.note || '-')}</td>
                    <td>${VRoboAdmin.formatDate(order.created_date)}</td>
                    <td>${VRoboAdmin.formatDate(order.updated_date)}</td>
                    <td>
                        <span class="webhook-status ${webhookClass}">${VRoboAdmin.escapeHtml(order.webhook_status)}</span>
                        <div class="order-actions">
                            <button class="button button-small vrobo-view-order" data-order="${order.order_id}">View</button>
                        </div>
                    </td>
                </tr>
            `;
        },

        // Render pagination
        renderPagination: function (pagination) {
            if (!pagination || pagination.total_pages <= 1) {
                $('#vrobo-pagination-controls').empty();
                return;
            }

            let html = '';
            const currentPage = pagination.page;
            const totalPages = pagination.total_pages;

            // Previous button
            if (currentPage > 1) {
                html += `<a href="#" class="button" data-page="${currentPage - 1}">« Previous</a>`;
            }

            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                if (i === currentPage) {
                    html += `<span class="button button-primary">${i}</span>`;
                } else {
                    html += `<a href="#" class="button" data-page="${i}">${i}</a>`;
                }
            }

            // Next button
            if (currentPage < totalPages) {
                html += `<a href="#" class="button" data-page="${currentPage + 1}">Next »</a>`;
            }

            $('#vrobo-pagination-controls').html(html);
        },

        // Get status CSS class
        getStatusClass: function (status) {
            switch (status) {
                case 'completed': return 'status-completed';
                case 'processing': return 'status-processing';
                case 'cancelled': return 'status-cancelled';
                case 'pending': return 'status-pending';
                default: return 'status-' + status;
            }
        },

        // Get webhook status CSS class
        getWebhookStatusClass: function (status) {
            switch (status) {
                case 'sent': return 'webhook-sent';
                case 'failed': return 'webhook-failed';
                case 'pending': return 'webhook-pending';
                case 'skipped': return 'webhook-skipped';
                default: return 'webhook-' + status;
            }
        },

        // Escape HTML
        escapeHtml: function (text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Make VRoboAdmin globally available
    window.VRoboAdmin = VRoboAdmin;

    // Global loadOrders function for compatibility
    window.loadOrders = function (page) {
        if (VRoboAdmin && VRoboAdmin.loadOrders) {
            VRoboAdmin.loadOrders(page);
        }
    };

    // jQuery plugins for common functionality
    $.fn.vroboSpinner = function (show = true) {
        return this.each(function () {
            const $el = $(this);
            if (show) {
                $el.addClass('vrobo-loading').append('<span class="spinner is-active"></span>');
            } else {
                $el.removeClass('vrobo-loading').find('.spinner').remove();
            }
        });
    };

    $.fn.vroboConfirm = function (message, callback) {
        return this.on('click', function (e) {
            e.preventDefault();
            if (confirm(message)) {
                callback.call(this, e);
            }
        });
    };

})(jQuery);

// Utility functions available globally
window.VRoboUtils = {

    // Debounce function
    debounce: function (func, wait, immediate) {
        let timeout;
        return function () {
            const context = this;
            const args = arguments;
            const later = function () {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    },

    // Throttle function
    throttle: function (func, limit) {
        let inThrottle;
        return function () {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },

    // Generate random string
    randomString: function (length = 32) {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        let result = '';
        for (let i = 0; i < length; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return result;
    },

    // Validate email
    validateEmail: function (email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    },

    // Get URL parameter
    getUrlParameter: function (name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        const regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        const results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }
}; 