$(document).ready(function() {
    let unreadPollingTimeout = null;
    let isFetchingUnread = false;

    function fetchUnreadCount() {
        if (isFetchingUnread) return;
        isFetchingUnread = true;

        $.get('api/api.php?action=get_unread_count')
            .done(function (response) {
                const badge = $('#notification-badge');
                if (response.success && response.count > 0) {
                    const label = response.count > 9 ? '9+' : response.count;
                    badge.text(label).css('display', 'flex');
                } else {
                    badge.css('display', 'none');
                }
            })
            .always(function() {
                isFetchingUnread = false;
                startPolling();
            });
    }

    function startPolling() {
        if (unreadPollingTimeout) clearTimeout(unreadPollingTimeout);
        
        // Stop polling if the page is hidden
        if (document.hidden) {
            unreadPollingTimeout = setTimeout(startPolling, 5000); // Check again in 5s if we are back
            return;
        }

        unreadPollingTimeout = setTimeout(fetchUnreadCount, 15000); // 15s instead of 10s
    }

    function fetchNotifications() {
        const list = $('#notification-list');
        list.html('<div class="p-4 text-center text-gray-400"><span class="loader-sm inline-block"></span></div>');

        $.get('api/api.php?action=get_notifications&unread_only=true', function (response) {
            if (response.success && response.data.length > 0) {
                list.empty();
                response.data.forEach(notif => {
                    const timeAgo = formatTimeAgo(new Date(notif.created_at));
                    const isRead = notif.is_read == 1;
                    const item = $(`
                        <div class="px-4 py-3.5 border-b border-gray-50 transition-colors ${!isRead ? 'bg-teal-50/40' : ''}" data-id="${notif.id}">
                            <div style="display:flex; gap:14px; align-items:flex-start;">
                                <div style="width:36px; height:36px; min-width:36px; border-radius:50%; background:#f0fdf4; color:#059669; display:flex; align-items:center; justify-content:center; flex-shrink:0; aspect-ratio:1/1; overflow:hidden;">
                                    <i class="${getIconForType(notif.type)}" style="font-size:14px;"></i>
                                </div>
                                <div style="flex:1; min-width:0;">
                                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:4px;">
                                        <p style="font-size:13px; font-weight:${!isRead ? '700' : '500'}; color:#1f2937; line-height:1.5; margin:0; padding-right:12px;">${notif.message}</p>
                                    </div>
                                    <div style="display:flex; justify-content:space-between; align-items:center;">
                                        <span style="font-size:11px; color:#9ca3af; font-weight:500; display:flex; align-items:center; gap:4px;">
                                            <i class="far fa-clock" style="font-size:10px;"></i> ${timeAgo}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `);



                    list.append(item);
                });
            } else {
                list.html('<div class="p-8 text-center text-gray-400"><i class="far fa-bell text-2xl mb-2 opacity-20 block"></i><span class="text-sm">No notifications</span></div>');
            }
        });
    }

    function getIconForType(type) {
        switch (type) {
            case 'form_submitted': return 'fas fa-file-medical';
            case 'note_added': return 'fas fa-notes-medical';
            case 'note_edited': return 'fas fa-edit';
            case 'assignment': return 'fas fa-user-tag';
            default: return 'fas fa-info-circle';
        }
    }

    function formatTimeAgo(date) {
        const seconds = Math.floor((new Date() - date) / 1000);
        let interval = seconds / 31536000;
        if (interval > 1) return Math.floor(interval) + "y ago";
        interval = seconds / 2592000;
        if (interval > 1) return Math.floor(interval) + "mo ago";
        interval = seconds / 86400;
        if (interval > 1) return Math.floor(interval) + "d ago";
        interval = seconds / 3600;
        if (interval > 1) return Math.floor(interval) + "h ago";
        interval = seconds / 60;
        if (interval > 1) return Math.floor(interval) + "m ago";
        return "Just now";
    }

    // Initialize
    fetchUnreadCount();
    $('#notification-dropdown-toggle').on('show.bs.dropdown', fetchNotifications);

    $('#mark-all-read').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $.post('api/api.php?action=mark_notifications_read', function (response) {
            if (response.success) {
                fetchUnreadCount();
                fetchNotifications();
            }
        });
    });

    // Handle visibility change to stop/start polling
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            fetchUnreadCount();
        }
    });
});
