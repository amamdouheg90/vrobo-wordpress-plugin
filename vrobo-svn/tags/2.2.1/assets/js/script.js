/**
 * VRobo WooCommerce Frontend JavaScript
 */

(function ($) {
    'use strict';

    $(document).ready(function () {
        VRoboFrontend.init();
    });

    const VRoboFrontend = {

        init: function () {
            this.bindEvents();
            this.initNotifications();
        },

        bindEvents: function () {
            // Any frontend event handlers can go here
            $('.vrobo-order-widget').on('click', function () {
                // Handle order widget interactions
            });
        },

        initNotifications: function () {
            // Initialize any frontend notifications
            if (typeof vrobo_frontend !== 'undefined' && vrobo_frontend.notifications) {
                vrobo_frontend.notifications.forEach(function (notification) {
                    VRoboFrontend.showNotification(notification.message, notification.type);
                });
            }
        },

        showNotification: function (message, type = 'info') {
            const notification = $('<div class="vrobo-notification ' + type + '">')
                .text(message)
                .appendTo('body');

            setTimeout(function () {
                notification.addClass('show');
            }, 100);

            setTimeout(function () {
                notification.removeClass('show');
                setTimeout(function () {
                    notification.remove();
                }, 300);
            }, 3000);
        }
    };

    window.VRoboFrontend = VRoboFrontend;

})(jQuery); 