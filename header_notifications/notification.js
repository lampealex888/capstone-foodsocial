/*
 * Description: Notification Widget
 * Author: Wafik
 * Created @ 3/8/2024
 * append this in header.php: <script src='<?php bloginfo('template_directory'); ?>/js/header_notifications/notification.js' type='text/javascript'></script>
 */
(function ($) {
    class Notification {
        constructor(id, createdAt, type, isRead, invokerId, invoker_username, avatar) {
            this.id = id;
            this.createdAt = new Date(createdAt);
            this.type = type;
            this.isRead = isRead === "1";
            this.invokerId = invokerId;
            this.invoker_username = invoker_username;
            this.avatar = avatar;
            this.message = this.generateMessage();
        }

        generateMessage() {
            switch (this.type) {
                case 'newFollower':
                    return `${this.invoker_username} followed you.`;
                case 'newComment':
                    return `${this.invoker_username} commented on your post.`;
                case 'newReply':
                    return `${this.invoker_username} replied to your comment.`;
                case 'newCreatorRecipe':
                    return `${this.invoker_username} posted a new recipe.`;
                case 'newReviewOrRating':
                    return `${this.invoker_username} left a review or rating on your recipe.`;
                default:
                    return "You have a new notification!";
            }
        }

        formatTimestamp() {
            return this.createdAt.toLocaleString();
        }
    }

    class NotificationsWidget {
        constructor(element, options) {
            this.element = $(element);
            this.options = { ...options };
            this.init();
        }

        async init() {
            this.$notificationCount = this.element.find('#notification-count');
            this.$notificationsDropdown = this.element.find('#notifications-dropdown');
            this.$dropdownElements = this.element.find('#dropdown-elements');
            this.$loadingIndicator = this.element.find('#loading-indicator');
            this.notificationsArray = [];

            // Adjusted click event listener
            this.element.on('click', () => {
                if (this.$notificationsDropdown.is(':visible')) {
                    this.closeDropdown();
                } else {
                    this.openDropdown();
                }
            });
            this.updateNotificationCount();
            setInterval(() => this.updateNotificationCount(), 10000);
        }

        updateNotificationCount() {
            $.ajax({
                url: this.options.ajaxurl,
                type: 'GET',
                data: { action: 'jondaley_get_unread_notifications_count' },
            }).done((response) => {
                const num = parseInt(response.data.count, 10);
                this.$notificationCount.text(num > 10 ? '10+' : num);
                $('#notification-bell > a > i').toggleClass('bell-icon-with-notifications bell-icon-blink', num > 0);
            }).fail((xhr, status, error) => {
                console.error("Error fetching notifications count:", status, error);
            });
            //console.log(this.notificationsArray);
        }

        openDropdown() {
            this.$notificationsDropdown.show();
            // Check if the dropdown is now visible
            if (this.$notificationsDropdown.is(':visible')) {
                // If it's visible, fetch new notifications
                this.fetchNewNotifications().done(() => {
                    // Once new notifications are fetched, display them
                    this.displayNotificationsFromArray();
                });
            }
        }

        closeDropdown() {
            this.$notificationsDropdown.hide(); // Explicitly hide the dropdown

            // Assuming markAllNotificationsAsRead returns a jQuery Deferred object or a Promise
            this.markAllNotificatonsAsRead().done(() => {
                this.$notificationCount.text('0'); // Set notification count to '0' after marking as read
                $('#notification-bell > a > i').removeClass('bell-icon-with-notifications bell-icon-blink');
                //console.log("Dropdown closed, and notifications marked as read.");
            });
        }

        // Highly inefficent but a decent start (will consider caching in the future)
        displayNotificationsFromArray() {
            this.$dropdownElements.empty();
            if (this.notificationsArray.length === 0) {
                this.onNoNotifications();
            } else {
                this.notificationsArray.forEach(notification => {
                    this.appendNotificationItem(notification);
                });
            }
        }

        onNoNotifications() {
            this.$dropdownElements.empty();
            const $notificationItem = $(`
                <div class="notification-item" id="no-notification" style="padding: 10px; text-align: center; pointer-events: none;">
                    <div>
                        <span class="notification-message" style="color: #666;">Nothing going on here!</span>
                    </div>
                </div>
            `);

            this.$dropdownElements.append($notificationItem);
        }

        onErrorNotifications() {
            console.log("onErrorNotifications was called!");
            this.$dropdownElements.empty();
            const $notificationItem = $(`
            <div class="notification-item" id="notification-error" style="padding: 10px; text-align: center; pointer-events: none;">
                <div>
                    <span class="notification-message" style="color: #666;">Failed to load notifications!</span>
                </div>
            </div>
        `);

            this.$dropdownElements.append($notificationItem);
        }

        // TBI
        // initIntersectionObserver() {
        //     const sentinel = document.querySelector('#notifications-sentinel');
        //     this.observer = new IntersectionObserver(entries => {
        //         entries.forEach(entry => {
        //             if (entry.isIntersecting) this.fetchNewNotifications();
        //         });
        //     }, { threshold: 0.1 });
        //     this.observer.observe(sentinel);
        // }

        fetchNewNotifications() {
            // this.loadingMore = true;
            // this.$loadingIndicator.show();

            return $.ajax({
                url: this.options.ajaxurl,
                type: 'GET',
                data: { action: 'jondaley_get_notifications', batch_size: this.options.batchSize, page_no: this.options.pageIndex },
            }).done((response) => {
                this.onFetchSuccess(response);
                //console.log(response.data);
            }).fail((xhr, status, error) => {
                this.onFetchError(status, error);
            });
        }

        onFetchSuccess(response) {
            if (response.success) {
                const notifications = response.data.notifications || [];
                this.notificationsArray = notifications.map(item => new Notification(item.id, item.created_at, item.type, item.isRead, item.invoker_id, item.invoker_username));
                this.options.pageIndex += notifications.length > 0 ? 1 : 0;
            }
            // this.loadingMore = false;
            // this.$loadingIndicator.hide();
        }

        onFetchError(status, error) {
            console.error("Error fetching notifications:", status, error);
            // this.loadingMore = false;
            // this.$loadingIndicator.hide();
            this.onErrorNotifications();
        }

        appendNotificationItem(notification) {
            const notificationStyle = notification.isRead ? '' : 'new-notification';
            const formattedTime = notification.formatTimestamp();

            // A place holder image for now
            const avatarImgUrl = 'https://th.bing.com/th?q=Default+User+Avatar&w=120&h=120&c=1&rs=1&qlt=90&cb=1&dpr=1.4&pid=InlineBlock&mkt=en-US&cc=US&setlang=en&adlt=moderate&t=1&mw=247';

            const $notificationItem = $(`
            <div class="notification-item ${notificationStyle}" data-notification-id="${notification.id}" style="padding: 10px; border: 1px solid #ccc; border-radius: 5px; background-color: #f9f9f9;">
                <img src="${avatarImgUrl}" alt="User Avatar" style="width: 40px; height: 40px; border-radius: 20px; float: left; margin-right: 10px;" />
                <div style="overflow: hidden;">
                    <span class="notification-message" style="font-size: 14px; font-weight: bold; color: #333;">${notification.message}</span>
                    <br>
                    <span class="notification-time" style="font-size: 12px; color: #666;">${formattedTime}</span>
                </div>
            </div>
        `);


            this.$dropdownElements.append($notificationItem);
        }

        markAllNotificatonsAsRead() {
            return $.ajax({
                url: this.options.ajaxurl,
                type: 'POST',
                data: { action: 'jondaley_mark_notifications_as_read' }
            }).done((response) => {
                if (response.success) {
                    //console.log(response.data);
                }
            }).fail((xhr, status, error) => {
                console.error("Error marking notifications as read:", status, error);
            });
        }
    }

    $(function () {
        const widget = new NotificationsWidget('#notification-bell', { ajaxurl: ajaxurl });
    });

})(jQuery);