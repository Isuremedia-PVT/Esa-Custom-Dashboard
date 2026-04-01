<?php
require_once './middleware/auth_check.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in and has 'admin' role
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    $_SESSION['message'] = "Access denied. Admin privileges required.";
    $_SESSION['message_type'] = "error";
    header("Location: ./signin.php");
    exit;
}

require_once './config/database.php';
require_once './controllers/ShiftController.php';

$controller = new ShiftController($pdo);

// Get pagination and search parameters
$page = isset($_POST['page']) && is_numeric($_POST['page']) && $_POST['page'] > 0 
    ? (int)$_POST['page'] 
    : (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 
        ? (int)$_GET['page'] 
        : 1);
$search = isset($_POST['search']) ? trim($_POST['search']) : (isset($_GET['search']) ? trim($_GET['search']) : '');
$perPage = 10;

// Fetch shifts for display
$result = $controller->getAllShifts($page, $perPage, $search);
if (isset($result['errors'])) {
    $_SESSION['message'] = implode(', ', $result['errors']);
    $_SESSION['message_type'] = "error";
    $shifts = [];
    $totalShifts = 0;
    $currentPage = 1;
} else {
    $shifts = $result['shifts'];
    $totalShifts = $result['total'];
    $currentPage = $result['currentPage'];
}
$totalPages = ceil($totalShifts / $perPage);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirectPage = $currentPage;

    if (isset($_POST['create_shift'])) {
        $data = [
            'name' => $_POST['name'],
            'description' => $_POST['description']
        ];
        $result = $controller->createShift($data);
        if (isset($result['success'])) {
            $_SESSION['message'] = $result['success'];
            $_SESSION['message_type'] = "success";
        } elseif (isset($result['errors'])) {
            $_SESSION['message'] = implode(', ', $result['errors']);
            $_SESSION['message_type'] = "error";
        }
        header("Location: shifts.php?page=$redirectPage&search=" . urlencode($search));
        exit;
    }

    if (isset($_POST['update_shift'])) {
        $data = [
            'name' => $_POST['name'],
            'description' => $_POST['description']
        ];
        $result = $controller->updateShift($_POST['shift_id'], $data);
        if (isset($result['success'])) {
            $_SESSION['message'] = $result['success'];
            $_SESSION['message_type'] = "success";
        } elseif (isset($result['errors'])) {
            $_SESSION['message'] = implode(', ', $result['errors']);
            $_SESSION['message_type'] = "error";
        }
        header("Location: shifts.php?page=$redirectPage&search=" . urlencode($search));
        exit;
    }

    if (isset($_POST['delete_shift'])) {
        $result = $controller->deleteShift($_POST['shift_id']);
        if (isset($result['success'])) {
            $_SESSION['message'] = $result['success'];
            $_SESSION['message_type'] = "success";
        } elseif (isset($result['errors'])) {
            $_SESSION['message'] = implode(', ', $result['errors']);
            $_SESSION['message_type'] = "error";
        }
        header("Location: shifts.php?page=$redirectPage&search=" . urlencode($search));
        exit;
    }
}

// Include layout files
$page_title = 'Shifts';
include './views/layouts/head.php';
include './views/layouts/alert.php';
?>

<style>
.pagination { display: flex; justify-content: space-between; align-items: center; width: 100%; }
.pagination-total { font-size: 1rem; color: #4b5563; }
.pagination .flex { gap: 0.5rem; }
.pagination-pager a, .pagination-pager span { padding: 0.5rem 1rem; border-radius: 0.25rem; text-decoration: none; color: #66b19c; }
.pagination-pager.active a { background-color: #66b19c; color: white; }
.pagination-pager-disabled a { color: #ccc; cursor: not-allowed; }
.pagination-pager:hover a:not(.pagination-pager-disabled a) { background-color: #e0f2f1; }
.dataTables_filter input[type="search"] { border: 1px solid #d1d5db; border-radius: 0.375rem; padding: 0.25rem 0.5rem; }

/* New Pagination Style */
.pagination-container {
    display: flex;
    align-items: center;
    gap: 12px;
}

.pagination-btn {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.2s;
    background: transparent;
    cursor: pointer;
    border: none;
    font-size: 12px;
    text-decoration: none;
}

.pagination-btn.active {
    background: #66b19c;
    color: white !important;
    box-shadow: 0 4px 8px rgba(102, 177, 156, 0.2);
    font-weight: 600;
}

.pagination-btn.inactive {
    color: #64748b !important;
}

.pagination-btn:hover:not(.active) {
    background: rgba(102, 177, 156, 0.1);
}

.pagination-arrow {
    color: #66b19c !important;
    font-size: 1rem;
    padding: 0 4px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    text-decoration: none;
}

.pagination-arrow:hover:not(.disabled) {
    transform: scale(1.1);
}

.pagination-arrow.disabled {
    color: #d1d5db !important;
    cursor: not-allowed;
    opacity: 0.5;
    pointer-events: none;
}
</style>

<div id="root">
    <div class="app-layout-modern flex flex-auto flex-col">
        <div class="flex flex-auto min-w-0">
            <?php include './views/layouts/sidebar.php'; ?>
            <div class="flex flex-col flex-auto min-h-screen min-w-0 relative w-full bg-white border-l border-gray-200">
                <?php include './views/layouts/header.php'; ?>
                <div class="h-full flex flex-auto flex-col justify-between">
                    <main class="h-full">
                        <div class="page-container relative h-full flex flex-auto flex-col px-4 sm:px-6 md:px-8 py-4 sm:py-6">
                            <div class="container mx-auto">
                                <div class="mb-10 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-6 border-b pb-6">
                                    <div>
                                        <h3 class="text-xl font-bold text-gray-800">Shifts / Note Types</h3>
                                        <p class="text-gray-500 text-[12px]">Manage shift categories and note types for the system.</p>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <form action="shifts.php" method="GET" class="flex items-center gap-2">
                                            <span class="text-sm font-medium text-gray-500">Search:</span>
                                            <div class="relative">
                                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <svg class="h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                                                    </svg>
                                                </div>
                                                <input type="search" name="search" class="w-64 pl-10 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none transition-all" placeholder="Search shifts..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" oninput="this.form.submit()">
                                                <input type="hidden" name="page" value="1">
                                            </div>
                                        </form>
                                        <button type="button" data-bs-toggle="modal" data-bs-target="#addShiftModal" class="btn btn-solid btn-sm">Add New Shift</button>
                                    </div>
                                </div>
                                <?= displayAlert() ?>
                                <div class="bg-white shadow-sm rounded-xl border border-gray-200 overflow-hidden">
                                    <table class="w-full text-left">
                                        <thead>
                                            <tr class="bg-gray-50/50 border-b border-gray-200">
                                                <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-[11px]">Name</th>
                                                <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-[11px]">Description</th>
                                                <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-[11px] text-left">Created At</th>
                                                <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-[11px] text-left">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($shifts)): ?>
                                                <tr><td colspan="4" class="text-center p-8 text-gray-500">No shifts found.</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($shifts as $shift): ?>
                                                    <tr class="border-b border-gray-100 hover:bg-gray-50/80 transition-all duration-200 group">
                                                        <td class="px-6 py-4 font-semibold text-gray-800 whitespace-nowrap"><?= htmlspecialchars($shift['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td class="px-6 py-4 text-gray-500 max-w-md truncate"><?= htmlspecialchars($shift['description'] ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td class="px-6 py-4 text-gray-400 text-center whitespace-nowrap text-xs"><?= date('M d, Y', strtotime($shift['created_at'])) ?></td>
                                                        <td class="px-6 py-4 text-right">
                                                            <div class="flex items-center space-x-2 justify-end">
                                                                <button class="action-btn action-btn-blue" 
                                                                        data-bs-toggle="modal" data-bs-target="#editShiftModal"
                                                                        data-id="<?= $shift['id'] ?>"
                                                                        data-name="<?= htmlspecialchars($shift['name'], ENT_QUOTES, 'UTF-8') ?>"
                                                                        data-description="<?= htmlspecialchars($shift['description'], ENT_QUOTES, 'UTF-8') ?>"
                                                                        aria-label="Edit Shift">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                                    </svg>
                                                                </button>
                                                                <button class="action-btn action-btn-red"
                                                                        data-bs-toggle="modal" data-bs-target="#deleteShiftModal"
                                                                        data-id="<?= $shift['id'] ?>"
                                                                        data-name="<?= htmlspecialchars($shift['name'], ENT_QUOTES, 'UTF-8') ?>"
                                                                        aria-label="Delete Shift">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4m-4 4v12m4-12v12" />
                                                                    </svg>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <?php if ($totalPages > 1): ?>
                                <div class="pagination mt-10">
                                    <div class="pagination-total text-sm text-gray-500">Total <?= $totalShifts ?> Shifts</div>
                                    <div class="pagination-container">
                                        <!-- Previous Button -->
                                        <a href="?page=<?php echo $currentPage > 1 ? $currentPage - 1 : 1; ?>&search=<?php echo urlencode($search); ?>" 
                                           class="pagination-arrow <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>

                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" 
                                               class="pagination-btn <?= $currentPage == $i ? 'active' : 'inactive' ?>">
                                                <?= $i ?>
                                            </a>
                                        <?php endfor; ?>

                                        <!-- Next Button -->
                                        <a href="?page=<?php echo $currentPage < $totalPages ? $currentPage + 1 : $totalPages; ?>&search=<?php echo urlencode($search); ?>" 
                                           class="pagination-arrow <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </main>


<!-- Add Modal -->
<div class="modal fade modal-premium" id="addShiftModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog dialog">
        <div class="dialog-content">
            <span class="close-btn absolute z-10" role="button" data-bs-dismiss="modal">
                <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 20 20" aria-hidden="true" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                </svg>
            </span>
            <div class="modal-premium-header">
                <h4 class="text-xl font-bold">Add New Shift</h4>
                <p class="modal-desc">Create a new shift or note type</p>
            </div>
            <form action="shifts.php" method="POST">
                <div class="modal-premium-body">
                    <div class="mb-4">
                        <label class="block text-sm font-bold text-gray-700 mb-2">Shift Name</label>
                        <input type="text" name="name" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none transition-all" placeholder="e.g., Morning Shift" required>
                    </div>
                    <div class="mb-0">
                        <label class="block text-sm font-bold text-gray-700 mb-2">Description</label>
                        <textarea name="description" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none transition-all" placeholder="Enter shift details..." rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-premium-footer">
                    <button type="button" data-bs-dismiss="modal" class="btn-modal btn-modal-gray">Cancel</button>
                    <button type="submit" name="create_shift" class="btn-modal bg-teal-600 text-white hover:bg-teal-700">Save Shift</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade modal-premium" id="editShiftModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog dialog">
        <div class="dialog-content">
            <span class="close-btn absolute z-10" role="button" data-bs-dismiss="modal">
                <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 20 20" aria-hidden="true" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                </svg>
            </span>
            <div class="modal-premium-header">
                <h4 class="text-xl font-bold">Edit Shift</h4>
                <p class="modal-desc">Modify shift details</p>
            </div>
            <form action="shifts.php" method="POST">
                <div class="modal-premium-body">
                    <input type="hidden" name="shift_id" id="edit_shift_id">
                    <div class="mb-4">
                        <label class="block text-sm font-bold text-gray-700 mb-2">Shift Name</label>
                        <input type="text" name="name" id="edit_name" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none transition-all" required>
                    </div>
                    <div class="mb-0">
                        <label class="block text-sm font-bold text-gray-700 mb-2">Description</label>
                        <textarea name="description" id="edit_description" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none transition-all" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-premium-footer">
                    <button type="button" data-bs-dismiss="modal" class="btn-modal btn-modal-gray">Cancel</button>
                    <button type="submit" name="update_shift" class="btn-modal bg-teal-600 text-white hover:bg-teal-700">Update Shift</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade modal-premium" id="deleteShiftModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog dialog">
        <div class="dialog-content">
            <span class="close-btn absolute z-10" role="button" data-bs-dismiss="modal">
                <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 20 20" aria-hidden="true" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                </svg>
            </span>
            <div class="modal-premium-body">
                <div class="modal-icon-container modal-icon-container-red">
                    <svg stroke="currentColor" fill="none" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </div>
                <h4 class="text-xl font-bold">Delete Shift</h4>
                <p class="modal-desc">Are you sure you want to delete <span id="delete_shift_name" class="font-bold text-gray-900"></span>? This action cannot be undone.</p>
            </div>
            <form action="shifts.php" method="POST">
                <div class="modal-premium-footer">
                    <input type="hidden" name="shift_id" id="delete_shift_id">
                    <input type="hidden" name="delete_shift" value="1">
                    <button type="button" data-bs-dismiss="modal" class="btn-modal btn-modal-gray">No, Cancel</button>
                    <button type="submit" name="delete_shift" class="btn-modal bg-red-600 text-white hover:bg-red-700">Yes, Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Edit Modal
    var editModal = document.getElementById('editShiftModal');
    editModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        document.getElementById('edit_shift_id').value = button.getAttribute('data-id');
        document.getElementById('edit_name').value = button.getAttribute('data-name');
        document.getElementById('edit_description').value = button.getAttribute('data-description');
    });

    // Delete Modal
    var deleteModal = document.getElementById('deleteShiftModal');
    deleteModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        document.getElementById('delete_shift_id').value = button.getAttribute('data-id');
        document.getElementById('delete_shift_name').textContent = button.getAttribute('data-name');
    });
});
</script>

<?php include './views/layouts/footer.php'; ?>
