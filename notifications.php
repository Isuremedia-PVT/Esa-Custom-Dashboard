<?php
session_start();

// Verify user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: signin.php");
    exit;
}

require_once './config/database.php';
require_once './controllers/NotificationController.php';

$notifCtrl = new NotificationController($pdo);
$user_id = $_SESSION['user']['id'];

// Handle Mark as Read Actions
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $notifCtrl->deleteNotification($user_id, (int)$_GET['id']);
    header("Location: notifications.php");
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'mark_read' && isset($_GET['id'])) {
    $notifCtrl->markAsRead($user_id, (int)$_GET['id']);
    header("Location: notifications.php");
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'mark_all_read') {
    $notifCtrl->markAsRead($user_id);
    header("Location: notifications.php");
    exit;
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$perPage = 15;

$result = $notifCtrl->getNotificationsPaginated($user_id, $page, $perPage);
$notifications = $result['data'] ?? [];
$totalNotifs = $result['total'] ?? 0;
$totalPages = ceil($totalNotifs / $perPage);

// Include layout files
$page_title = 'All Notifications';
include './views/layouts/head.php';
require_once './views/layouts/alert.php';

function formatTimeAgo($datetimeStr) {
    if (!$datetimeStr) return "Unknown";
    $time = strtotime($datetimeStr);
    $diff = time() - $time;
    if ($diff < 60) return "Just now";
    if ($diff < 3600) return floor($diff / 60) . "m ago";
    if ($diff < 86400) return floor($diff / 3600) . "h ago";
    if ($diff < 2592000) return floor($diff / 86400) . "d ago";
    if ($diff < 31536000) return floor($diff / 2592000) . "mo ago";
    return floor($diff / 31536000) . "y ago";
}

function getIconForType($type) {
    switch ($type) {
        case 'form_submitted': return 'fas fa-file-medical';
        case 'note_added': return 'fas fa-notes-medical';
        case 'note_edited': return 'fas fa-edit';
        case 'assignment': return 'fas fa-user-tag';
        default: return 'fas fa-info-circle';
    }
}

function getLinkForNotification($notif) {
    if (strpos($notif['type'], 'form') !== false) {
        return "view_log.php?user_id=" . urlencode($notif['actor_id']) . "&submission_id=" . urlencode($notif['reference_id']);
    } else if (strpos($notif['type'], 'note') !== false) {
        return "patient_notes.php?user_id=" . urlencode($notif['reference_id']);
    } else if (strpos($notif['type'], 'assign') !== false) {
        return "patient-details.php?user_id=" . urlencode($notif['reference_id']);
    }
    return "#";
}
?>

<style>
.pagination { display: flex; justify-content: space-between; align-items: center; width: 100%; }
.pagination-total { font-size: 1rem; color: #4b5563; }
.pagination .flex { gap: 0.5rem; }
.pagination-pager a, .pagination-pager span { padding: 0.5rem 1rem; border-radius: 0.25rem; text-decoration: none; color: #66b19c; }
.pagination-pager.active a { background-color: #66b19c; color: white; }
</style>

<div class="app-layout-modern flex flex-auto flex-col">
    <div class="flex flex-auto min-w-0">
        <?php include './views/layouts/sidebar.php'; ?>
        <div class="flex flex-col flex-auto min-h-screen min-w-0 relative w-full bg-white border-l border-gray-200">
            <?php include './views/layouts/header.php'; ?>
            <div class="px-4 mt-4"><?= displayAlert() ?></div>
            
            <div class="h-full flex flex-auto flex-col">
                <main class="h-full">
                    <div class="page-container relative h-full flex flex-auto flex-col px-4 sm:px-6 md:px-8 py-4 sm:py-6">
                        
                        <!-- Header Area -->
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900 drop-shadow-sm">Notifications</h1>
                                <p class="text-md text-gray-500 mt-1">View all your recent alerts and activity.</p>
                            </div>
                            <div class="flex gap-3">
                                <?php if ($totalNotifs > 0): ?>
                                    <a href="?action=mark_all_read" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-5 py-2.5 rounded-lg text-sm font-semibold transition-all">
                                        <i class="fas fa-check-double mr-2"></i> Mark All as Read
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Card Container View -->
                        <div class="bg-white rounded-2xl p-2 sm:p-6">
                            <?php if (empty($notifications)): ?>
                                <div class="text-center py-12">
                                    <div class="inline-flex items-center justify-center rounded-full bg-gray-50 mb-4 flex-shrink-0" style="width: 64px; height: 64px; min-width: 64px; min-height: 64px;">
                                        <i class="far fa-bell text-2xl text-gray-400"></i>
                                    </div>
                                    <h3 class="text-lg font-medium text-gray-900">No Notifications</h3>
                                    <p class="text-gray-500 mt-1">You're all caught up!</p>
                                </div>
                            <?php else: ?>
                                <div class="space-y-4">
                                    <?php foreach ($notifications as $notif): 
                                        $isRead = $notif['is_read'] == 1;
                                        $link = getLinkForNotification($notif);
                                    ?>
                                        <div class="p-4 rounded-xl border border-gray-100 flex items-start gap-4 transition-colors relative hover:shadow-md
                                            <?= !$isRead ? 'bg-teal-50/20 shadow-sm border-teal-100' : 'bg-white hover:bg-gray-50' ?>">
                                            
                                            <!-- Icon -->
                                            <div class="rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mt-1 flex-shrink-0" style="width: 40px; height: 40px; min-width: 40px; min-height: 40px;">
                                                <i class="<?= getIconForType($notif['type']) ?>"></i>
                                            </div>
                                            
                                            <!-- Content -->
                                            <div class="flex-1">
                                                <p class="text-sm <?= !$isRead ? 'font-bold' : 'font-medium' ?> text-gray-800">
                                                    <?= htmlspecialchars($notif['message']) ?>
                                                </p>
                                                <p class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                                                    <i class="far fa-clock"></i> <?= formatTimeAgo($notif['created_at']) ?>
                                                </p>
                                            </div>
                                            
                                            <!-- Actions -->
                                            <div class="flex items-center gap-2">
                                                <?php if (!$isRead): ?>
                                                    <span title="Unread" class="h-2.5 w-2.5 rounded-full bg-teal-500 mr-1 shadow-sm shadow-teal-500/50"></span>
                                                    <a href="?action=mark_read&id=<?= $notif['id'] ?>" class="text-[11px] font-bold text-teal-600 hover:text-teal-800 uppercase">Mark Read</a>
                                                <?php endif; ?>
                                                <a href="<?= $link ?>" class="bg-gray-900 hover:bg-gray-800 text-white px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors">
                                                    View
                                                </a>
                                                <a href="?action=delete&id=<?= $notif['id'] ?>" onclick="return confirm('Delete this notification?')" class="text-gray-400 hover:text-red-600 p-1.5 transition-colors" title="Delete">
                                                    <i class="fas fa-trash-alt text-sm"></i>
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Render Pagination -->
                                <?php if ($totalPages > 1): ?>
                                    <div class="pagination mt-8 border-t border-gray-100 pt-6">
                                        <div class="pagination-total">
                                            Showing page <?= $page ?> of <?= $totalPages ?> (<?= $totalNotifs ?> total)
                                        </div>
                                        <div class="flex">
                                            <?php if ($page > 1): ?>
                                                <div class="pagination-pager">
                                                    <a href="?page=<?= $page - 1 ?>" class="border border-gray-200">Previous</a>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                                <div class="pagination-pager <?= $i === $page ? 'active' : '' ?>">
                                                    <a href="?page=<?= $i ?>" class="<?= $i === $page ? '' : 'border border-gray-200' ?>"><?= $i ?></a>
                                                </div>
                                            <?php endfor; ?>
                                            
                                            <?php if ($page < $totalPages): ?>
                                                <div class="pagination-pager">
                                                    <a href="?page=<?= $page + 1 ?>" class="border border-gray-200">Next</a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                            <?php endif; ?>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>
</div>

<?php 
// Include footer scripts but we might not need everything, footer.php is fine
include './views/layouts/footer.php'; 
?>
