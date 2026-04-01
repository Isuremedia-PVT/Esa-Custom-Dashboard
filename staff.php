<?php
require_once './middleware/auth_check.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin', 'staff'])) {
    $_SESSION['message'] = "Access denied. Please sign in.";
    $_SESSION['message_type'] = "error";
    header("Location: ./signin.php");
    exit;
}

$page_title = $_SESSION['user']['role'] === 'admin' ? 'Staff' : 'Assigned Patients';
include './views/layouts/head.php';
require_once './config/database.php';
require_once './controllers/StaffController.php';
include './views/layouts/alert.php';

$controller = new StaffController($pdo);
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$limit = 10;

if ($_SESSION['user']['role'] === 'admin') {
    $result = $controller->getStaff($page, $limit, $search, $statusFilter);
    $items = $result['staff'];
    $totalItems = $result['totalStaff'];
} else {
    $result = $controller->getAssignedPatients($_SESSION['user']['id'], $page, $limit, $search, $statusFilter);
    $items = $result['patients'];
    $totalItems = $result['totalPatients'];
}
$totalPages = $result['totalPages'];
$pageItems = $result['currentPage'];

// Handle delete action (admin only)
if ($_SESSION['user']['role'] === 'admin' && isset($_POST['delete_staff']) && isset($_POST['staff_id'])) {
    $result = $controller->deleteStaff($_POST['staff_id']);
    if (isset($result['success'])) {
        $_SESSION['message'] = $result['success'];
        $_SESSION['message_type'] = "success";
    } elseif (isset($result['errors'])) {
        $_SESSION['message'] = implode(', ', $result['errors']);
        $_SESSION['message_type'] = "error";
    }
    header("Location: staff.php?page=$page&search=" . urlencode($search));
    exit;
}

// Handle activate action (admin only)
if ($_SESSION['user']['role'] === 'admin' && isset($_POST['activate_staff']) && isset($_POST['staff_id'])) {
    $result = $controller->activateStaff($_POST['staff_id']);
    if (isset($result['success'])) {
        $_SESSION['message'] = $result['success'];
        $_SESSION['message_type'] = "success";
    } elseif (isset($result['errors'])) {
        $_SESSION['message'] = implode(', ', $result['errors']);
        $_SESSION['message_type'] = "error";
    }
    header("Location: staff.php?page=$page&search=" . urlencode($search));
    exit;
}

?>

<style>
.pagination { display: flex; justify-content: space-between; align-items: center; width: 100%; }
.pagination-total { font-size: 1rem; color: #4b5563; }
.pagination .flex { gap: 0.5rem; }
.pagination-pager a, .pagination-pager span { padding: 0.5rem 1rem; border-radius: 0.25rem; text-decoration: none; color: #66b19c; }
.pagination-pager.active a { background-color: #66b19c; color: white; }
.pagination-pager-disabled a { color: #66b19c; cursor: not-allowed; }
.pagination-pager:hover a:not(.pagination-pager-disabled a) { background-color: #66b19c; }
.dataTables_header_end { display: flex; align-items: center; }
.dataTables_filter { display: inline-flex; align-items: center; }
.dataTables_filter label { margin-right: 0.5rem; white-space: nowrap; }
.dataTables_filter input[type="search"] { margin-right: 0; }
.btn-solid { margin-left: 1rem; }
.action-btn { font-size: 1.1rem; }

@media screen and (max-width: 768px) {
    .f-mb { flex-direction: column; gap: 15px; }
    .dataTables_header_end.flex.items-center { flex-direction: column; gap: 15px; }
}
</style>

<div id="root">
    <div class="app-layout-modern flex flex-auto flex-col">
        <div class="flex flex-auto min-w-0">
            <?php include './views/layouts/sidebar.php'; ?>
            <div class="flex flex-col flex-auto min-h-screen min-w-0 relative w-full bg-white border-l border-gray-200 dark:border-gray-700">
                <?php include './views/layouts/header.php'; ?>
                <div class="h-full flex flex-auto flex-col justify-between">
                    <main class="h-full">
                        <div class="page-container relative h-full flex flex-auto flex-col px-4 sm:px-6 md:px-8 py-4 sm:py-6">
                            <div class="container mx-auto">
                                <div class="mb-4">
                                    <div class="mb-10 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-6 border-b pb-6">
                                        <div>
                                            <h3 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($page_title) ?></h3>
                                            <p class="text-gray-500 text-sm">Manage <?= $_SESSION['user']['role'] === 'admin' ? 'medical staff members' : 'assigned patients' ?>.</p>
                                        </div>
                                        <div class="flex items-center gap-4">
                                            <form id="search-form" action="staff.php" method="GET" class="flex items-center gap-3">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-sm font-medium text-gray-500 whitespace-nowrap">Status:</span>
                                                    <select name="status" class="w-32 py-2 pl-3 pr-8 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none transition-all appearance-none bg-no-repeat bg-[right_0.5rem_center] bg-[length:1em_1em]" style="background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%236B7280%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.4-12.8z%22/%3E%3C/svg%3E');" onchange="this.form.submit()">
                                                        <option value="">All Status</option>
                                                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                                                        <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                    </select>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <span class="text-sm font-medium text-gray-500 whitespace-nowrap">Search:</span>
                                                    <div class="relative">
                                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                            <svg class="h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                                <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                                                            </svg>
                                                        </div>
                                                        <input type="search" name="search" id="search-input" class="w-56 pl-10 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none transition-all" placeholder="Search..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" oninput="debounceSearch()">
                                                        <input type="hidden" name="page" value="1">
                                                    </div>
                                                </div>
                                            </form>
                                            <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                                                <a href="new-registration.php?role=staff" class="btn btn-solid btn-sm">Add New Staff</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?= displayAlert() ?>
                                </div>

                                <div class="bg-white shadow-sm rounded-xl border border-gray-200 overflow-hidden">
                                    <div class="table-container overflow-x-auto">
                                        <table id="data-table" class="w-full text-left">
                                            <thead>
                                                <tr class="bg-gray-50/50 border-b border-gray-200">
                                                    <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-[11px] text-left">Name</th>
                                                    <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-[11px] text-left">Email</th>
                                                    <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-[11px] text-left">Phone</th>
                                                    <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-[11px] text-left">Status</th>
                                                    <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-[11px] text-left">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($items)): ?>
                                                    <tr><td colspan="<?= $_SESSION['user']['role'] === 'admin' ? 5 : 4 ?>" class="text-center p-12 text-gray-400 font-medium">No <?= $_SESSION['user']['role'] === 'admin' ? 'staff' : 'patients' ?> found.</td></tr>
                                                <?php else: ?>
                                                    <?php foreach ($items as $item): ?>
                                                        <tr class="border-b border-gray-100 hover:bg-gray-50/80 transition-all duration-200 group" 
                                                            data-id="<?= isset($item['id']) ? htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8') : '' ?>">
                                                            <td class="px-6 py-4">
                                                                <div class="flex items-center">
                                                                    <?= renderAvatar($item['profile_url'], $item['username'], 'w-[32px] h-[32px]', 'text-xs') ?>
                                                                    <div class="ml-3">
                                                                        <span class="font-bold text-gray-800 block leading-tight">
                                                                            <?= isset($item['username']) && $item['username'] !== null ? htmlspecialchars($item['username'], ENT_QUOTES, 'UTF-8') : 'N/A' ?>
                                                                        </span>
                                                                        <span class="text-[11px] text-gray-400 font-bold uppercase tracking-tighter">ID: #<?= str_pad($item['id'] ?? '0', 4, '0', STR_PAD_LEFT) ?></span>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td class="px-6 py-4">
                                                                <span class="text-[13px] text-gray-600 font-medium"><?= isset($item['email']) && $item['email'] !== null ? htmlspecialchars($item['email'], ENT_QUOTES, 'UTF-8') : 'N/A' ?></span>
                                                            </td>
                                                            <td class="px-6 py-4">
                                                                <span class="text-[13px] text-gray-500"><?= isset($item['phone']) && $item['phone'] !== null ? htmlspecialchars($item['phone'], ENT_QUOTES, 'UTF-8') : 'N/A' ?></span>
                                                            </td>
                                                            <td class="px-6 py-4">
                                                                <div class="flex items-center">
                                                                    <span class="w-2 h-2 rounded-full <?= isset($item['status']) && $item['status'] === 'active' ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.4)]' : 'bg-rose-500 shadow-[0_0_8px_rgba(244,63,94,0.4)]' ?>"></span>
                                                                    <span class="ml-2.5 text-[13px] font-semibold tracking-tight <?= isset($item['status']) && $item['status'] === 'active' ? 'text-gray-700' : 'text-gray-400' ?>">
                                                                        <?= isset($item['status']) && $item['status'] !== null ? ucfirst(htmlspecialchars($item['status'], ENT_QUOTES, 'UTF-8')) : 'N/A' ?>
                                                                    </span>
                                                                </div>
                                                            </td>
                                                            <td class="px-6 py-4">
                                                                <div class="flex items-center space-x-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                                                    <?php if (isset($item['status']) && $item['status'] === 'active'): ?>
                                                                        <a href="edit_staff.php?id=<?= isset($item['id']) ? htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8') : '' ?>&action=edit" class="action-btn action-btn-blue" onclick="event.stopPropagation();" title="Edit staff">
                                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                                            </svg>
                                                                        </a>
                                                                        <button type="button" class="action-btn action-btn-red" data-bs-toggle="modal" data-bs-target="#deletestaff" data-entity-id="<?= isset($item['id']) ? htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8') : '' ?>" data-entity-text="<?= isset($item['username']) && $item['username'] !== null ? htmlspecialchars($item['username'], ENT_QUOTES, 'UTF-8') : 'N/A' ?>" data-entity-status="<?= isset($item['status']) && $item['status'] !== null ? htmlspecialchars($item['status'], ENT_QUOTES, 'UTF-8') : 'N/A' ?>" onclick="event.stopPropagation();" title="Delete staff">
                                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4m-4 4v12m4-12v12" />
                                                                            </svg>
                                                                        </button>
                                                                        <a href="view_staff.php?id=<?= isset($item['id']) ? htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8') : '' ?>" class="action-btn action-btn-green" onclick="event.stopPropagation();" title="View staff">
                                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                                            </svg>
                                                                        </a>
                                                                    <?php elseif (isset($item['status']) && $item['status'] === 'inactive'): ?>
                                                                        <span class="action-btn action-btn-disabled" title="Edit disabled for inactive staff">
                                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                                            </svg>
                                                                        </span>
                                                                        <button type="button" class="action-btn action-btn-green" data-bs-toggle="modal" data-bs-target="#activatestaff" data-entity-id="<?= isset($item['id']) ? htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8') : '' ?>" data-entity-text="<?= isset($item['username']) && $item['username'] !== null ? htmlspecialchars($item['username'], ENT_QUOTES, 'UTF-8') : 'N/A' ?>" data-entity-status="<?= isset($item['status']) && $item['status'] !== null ? htmlspecialchars($item['status'], ENT_QUOTES, 'UTF-8') : 'N/A' ?>" onclick="event.stopPropagation();" title="Activate staff">
                                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                                            </svg>
                                                                        </button>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="pagination mt-10 flex justify-between items-center" id="data-pagination">
                                    <div class="pagination-total">Total <span><?= $totalItems ?></span> Items</div>
                                    <div class="flex items-center space-x-2">
                                        <span class="pagination-pager pagination-pager-prev<?php echo $pageItems <= 1 ? ' pagination-pager-disabled' : ''; ?>">
                                            <a href="?page=<?php echo $pageItems > 1 ? $pageItems - 1 : 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>" class="pagination-link" data-type="<?= $_SESSION['user']['role'] === 'admin' ? 'staff' : 'patient' ?>" data-page="<?php echo $pageItems > 1 ? $pageItems - 1 : 1; ?>">
                                                <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 20 20" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                </svg>
                                            </a>
                                        </span>
                                        <ul class="flex space-x-1">
                                            <?php
                                            $maxPagesToShow = 5;
                                            $startPage = max(1, $pageItems - floor($maxPagesToShow / 2));
                                            $endPage = min($totalPages, $startPage + $maxPagesToShow - 1);
                                            if ($endPage - $startPage < $maxPagesToShow - 1) {
                                                $startPage = max(1, $endPage - $maxPagesToShow + 1);
                                            }
                                            if ($startPage > 1) {
                                                echo '<li class="pagination-pager"><a href="?page=1&search=' . urlencode($search) . '&status=' . urlencode($statusFilter) . '" class="pagination-link" data-page="1">1</a></li>';
                                                if ($startPage > 2) {
                                                    echo '<li class="pagination-pager"><span>...</span></li>';
                                                }
                                            }
                                            for ($i = $startPage; $i <= $endPage; $i++) {
                                                echo '<li class="pagination-pager' . ($pageItems == $i ? ' active' : '') . '">';
                                                echo '<a href="?page=' . $i . '&search=' . urlencode($search) . '&status=' . urlencode($statusFilter) . '" class="pagination-link" data-page="' . $i . '">' . $i . '</a>';
                                                echo '</li>';
                                            }
                                            if ($endPage < $totalPages) {
                                                if ($endPage < $totalPages - 1) {
                                                    echo '<li class="pagination-pager"><span>...</span></li>';
                                                }
                                                echo '<li class="pagination-pager"><a href="?page=' . $totalPages . '&search=' . urlencode($search) . '&status=' . urlencode($statusFilter) . '" class="pagination-link" data-page="' . $totalPages . '">' . $totalPages . '</a></li>';
                                            }
                                            ?>
                                        </ul>
                                        <span class="pagination-pager pagination-pager-next<?php echo $pageItems >= $totalPages ? ' pagination-pager-disabled' : ''; ?>">
                                            <a href="?page=<?php echo $pageItems < $totalPages ? $pageItems + 1 : $totalPages; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>" class="pagination-link" data-page="<?php echo $pageItems < $totalPages ? $pageItems + 1 : $totalPages; ?>">
                                                <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 20 20" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                                </svg>
                                            </a>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </main>
                    <?php
                    $entityName = 'staff';
                    $actionUrl = "staff.php?page=$pageItems&search=" . urlencode($search);
                    include './views/layouts/delete_model.php';
                    include './views/layouts/footer.php';
                    ?>
                    <script>
                        let searchTimeout;
                        function debounceSearch() {
                            clearTimeout(searchTimeout);
                            searchTimeout = setTimeout(() => {
                                document.getElementById('search-form').submit();
                            }, 500);
                        }
                    </script>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
ob_end_flush();
?>