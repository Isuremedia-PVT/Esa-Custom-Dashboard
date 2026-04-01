<?php
require_once './middleware/auth_check.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Generate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is logged in
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    $_SESSION['message'] = "Access denied. Please sign in.";
    $_SESSION['message_type'] = "error";
    header("Location: ./signin.php");
    exit;
}

// Include database configuration and StaffController
require_once './config/database.php';
require_once './controllers/StaffController.php';

// Initialize database connection and controller
$controller = new StaffController($pdo);

// Fetch logged-in user details from the database
try {
    $user = $controller->getStaffById($_SESSION['user']['id'], false); // Allow admin or staff
    if (!$user || !in_array($user['role'], ['admin', 'staff']) || $user['status'] !== 'active') {
        $_SESSION['message'] = "Access denied. Please sign in or ensure your account is active.";
        $_SESSION['message_type'] = "error";
        // Clear session to prevent loop
        session_unset();
        session_destroy();
        header("Location: ./signin.php");
        exit;
    }
} catch (Exception $e) {
    $_SESSION['message'] = "Error verifying user: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
    // Clear session to prevent loop
    session_unset();
    session_destroy();
    header("Location: ./signin.php");
    exit;
}

// Check if staff_id is provided and valid
$staff_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if ($staff_id <= 0) {
    $_SESSION['message'] = "Invalid staff ID.";
    $_SESSION['message_type'] = "error";
    header("Location: staff.php");
    exit;
}

// Fetch staff details and verify status
try {
    $staff = $controller->getStaffById($staff_id, true); // Restrict to staff only
    if (!$staff || $staff['status'] !== 'active') {
        $_SESSION['message'] = "Staff member not found or not active.";
        $_SESSION['message_type'] = "error";
        header("Location: staff.php");
        exit;
    }
} catch (Exception $e) {
    $_SESSION['message'] = "Error fetching staff details: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
    header("Location: staff.php");
    exit;
}

// Restrict staff to view only their own details
if ($user['role'] === 'staff' && $_SESSION['user']['id'] !== $staff_id) {
    $_SESSION['message'] = "Access denied. You can only view your own details.";
    $_SESSION['message_type'] = "error";
    header("Location: staff.php");
    exit;
}

$page_title = 'View Staff';
include './views/layouts/head.php';
include './views/layouts/alert.php';

// Fetch assigned patients
$page = max(1, isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1);
$search = filter_input(INPUT_GET, 'search', FILTER_UNSAFE_RAW) ?? '';
$search = trim($search); // Remove leading/trailing whitespace
$search = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $search); // Allow letters, numbers, spaces, and hyphens
$limit = 10;
try {
    $result = $controller->getAssignedPatients($staff_id, $page, $limit, $search);
    if (isset($result['errors'])) {
        $_SESSION['message'] = implode(', ', $result['errors']);
        $_SESSION['message_type'] = "error";
        header("Location: view_staff.php?id=$staff_id");
        exit;
    }
    $patients = $result['patients'];
    $totalPatients = $result['totalPatients'] ?? 0; // Ensure totalPatients is 0 if not set
    $totalPages = $result['totalPages'] ?? 0; // Ensure totalPages is 0 if not set
    $pageItems = $result['currentPage'] ?? 1; // Default to 1 if not set
} catch (Exception $e) {
    $_SESSION['message'] = "Error fetching assigned patients: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
    header("Location: view_staff.php?id=$staff_id");
    exit;
}

// Handle unassign action (admin only)
if ($user['role'] === 'admin' && isset($_POST['unassign_patient']) && isset($_POST['staff_id']) && isset($_POST['patient_id'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("CSRF Token Mismatch: POST=" . ($_POST['csrf_token'] ?? 'unset') . ", SESSION=" . $_SESSION['csrf_token']);
        $_SESSION['message'] = "Invalid CSRF token.";
        $_SESSION['message_type'] = "error";
    } elseif ($staff['status'] !== 'active') {
        $_SESSION['message'] = "Only active staff members can have patients unassigned.";
        $_SESSION['message_type'] = "error";
    } else {
        $result = $controller->unassignPatientFromStaff($_POST['staff_id'], $_POST['patient_id']);
        if (isset($result['success'])) {
            $_SESSION['message'] = $result['success'];
            $_SESSION['message_type'] = "success";
        } elseif (isset($result['errors'])) {
            $_SESSION['message'] = implode(', ', $result['errors']);
            $_SESSION['message_type'] = "error";
        }
    }
    header("Location: view_staff.php?id=$staff_id&page=$page&search=" . urlencode($search));
    exit;
}

// Handle assign action (admin only)
if ($user['role'] === 'admin' && isset($_POST['assign_patient']) && isset($_POST['staff_id']) && isset($_POST['patient_id'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("CSRF Token Mismatch: POST=" . ($_POST['csrf_token'] ?? 'unset') . ", SESSION=" . $_SESSION['csrf_token']);
        $_SESSION['message'] = "Invalid CSRF token.";
        $_SESSION['message_type'] = "error";
    } elseif ($staff['status'] !== 'active') {
        $_SESSION['message'] = "Only active staff members can have patients assigned.";
        $_SESSION['message_type'] = "error";
    } else {
        $result = $controller->assignPatientToStaff($_POST['staff_id'], $_POST['patient_id']);
        if (isset($result['success'])) {
            $_SESSION['message'] = $result['success'];
            $_SESSION['message_type'] = "success";
        } elseif (isset($result['errors'])) {
            $_SESSION['message'] = implode(', ', $result['errors']);
            $_SESSION['message_type'] = "error";
        }
    }
    header("Location: view_staff.php?id=$staff_id&page=$page&search=" . urlencode($search));
    exit;
}

// Fetch patients for assignment dropdown (admin only)
$patientsForAssignment = [];
if ($_SESSION['user']['role'] === 'admin') {
    $allPatients = $controller->getPatientsForAssignment($staff_id);
    if (!isset($allPatients['error']) && is_array($allPatients)) {
        foreach ($allPatients as $p) {
            if ($p['is_assigned'] == 0) {
                $patientsForAssignment[] = $p;
            }
        }
    }
}

// Handle delete staff (admin only)
if ($user['role'] === 'admin' && isset($_POST['delete_staff']) && isset($_POST['staff_id'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("CSRF Token Mismatch: POST=" . ($_POST['csrf_token'] ?? 'unset') . ", SESSION=" . $_SESSION['csrf_token']);
        $_SESSION['message'] = "Invalid CSRF token.";
        $_SESSION['message_type'] = "error";
    } elseif ($staff['status'] !== 'active') {
        $_SESSION['message'] = "Only active staff members can be deleted.";
        $_SESSION['message_type'] = "error";
    } else {
        $result = $controller->deleteStaff($_POST['staff_id']);
        if (isset($result['success'])) {
            $_SESSION['message'] = $result['success'];
            $_SESSION['message_type'] = "success";
            header("Location: staff.php");
            exit;
        } elseif (isset($result['errors'])) {
            $_SESSION['message'] = implode(', ', $result['errors']);
            $_SESSION['message_type'] = "error";
        }
    }
    header("Location: view_staff.php?id=$staff_id&page=$page&search=" . urlencode($search));
    exit;
}

// Handle unassign staff (admin only)
if ($user['role'] === 'admin' && isset($_POST['unassign_staff']) && isset($_POST['staff_id'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("CSRF Token Mismatch: POST=" . ($_POST['csrf_token'] ?? 'unset') . ", SESSION=" . $_SESSION['csrf_token']);
        $_SESSION['message'] = "Invalid CSRF token.";
        $_SESSION['message_type'] = "error";
    } elseif ($staff['status'] !== 'active') {
        $_SESSION['message'] = "Only active staff members can be unassigned.";
        $_SESSION['message_type'] = "error";
    } else {
        $result = $controller->unassignStaff($_POST['staff_id']);
        if (isset($result['success'])) {
            $_SESSION['message'] = $result['success'];
            $_SESSION['message_type'] = "success";
        } elseif (isset($result['errors'])) {
            $_SESSION['message'] = implode(', ', $result['errors']);
            $_SESSION['message_type'] = "error";
        }
    }
    header("Location: view_staff.php?id=$staff_id&page=$page&search=" . urlencode($search));
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
.modal-dialog { max-width: 90%; margin: 1.75rem auto; }
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
                                 <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                                    <div>
                                        <div class="flex items-center mb-2">
                                            <h3>
                                                <span>Staff</span>
                                                <span class="ltr:ml-2 rtl:mr-2">#<?= htmlspecialchars($staff_id) ?></span>
                                            </h3>
                                            <div class="tag border-0 rounded-md ltr:ml-2 rtl:mr-2 <?= $staff['status'] === 'active' ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600' ?>">
                                                <?= htmlspecialchars(ucfirst($staff['status'] ?? 'Active')) ?>
                                            </div>
                                        </div>
                                        <span class="flex items-center">
                                            <svg stroke="currentColor" fill="none" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" class="text-lg" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                            </svg>
                                            <span class="ltr:ml-1 rtl:mr-1">Joined <?= isset($staff['created_at']) ? htmlspecialchars(date('M d, Y', strtotime($staff['created_at']))) : 'Recently' ?></span>
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                                            <a href="staff.php" class="btn btn-default">Back to Staff List</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?= displayAlert() ?>

                                <div class="xl:flex gap-4">
                                    <!-- Staff Details Side -->
                                    <div class="xl:max-w-[360px] w-full mb-4 xl:mb-0">
                                        <div class="card card-layout-frame" role="presentation">
                                            <div class="card-body">
                                                <div class="flex flex-col items-center pb-6 border-b border-gray-200 dark:border-gray-700">
                                                    <span class="avatar avatar-circle mb-4 <?= empty($staff['profile_url']) ? 'bg-emerald-100 text-emerald-600 flex items-center justify-center font-bold' : '' ?>" style="width: 90px; height: 90px; min-width: 90px; font-size: 36px;">
                                                        <?php if (!empty($staff['profile_url'])): ?>
                                                            <img class="avatar-img avatar-circle" style="width: 100%; height: 100%; object-fit: cover;" src="<?= htmlspecialchars($staff['profile_url']) ?>" loading="lazy" alt="Profile picture">
                                                        <?php else: ?>
                                                            <?= htmlspecialchars(strtoupper(substr($staff['username'] ?? 'U', 0, 1))) ?>
                                                        <?php endif; ?>
                                                    </span>
                                                    <h4 class="font-bold text-xl text-gray-900 dark:text-gray-100 mb-1">
                                                        <?= htmlspecialchars($staff['username'] ?? 'Unknown') ?>
                                                    </h4>
                                                    <div class="text-sm text-gray-500 font-medium">
                                                        Staff ID: #<?= htmlspecialchars($staff_id) ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="pt-6">
                                                    <h6 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-4">Contact Information</h6>
                                                    
                                                    <div class="flex items-center gap-4 mb-4">
                                                        <div class="flex items-center justify-center w-10 h-10 rounded-full bg-emerald-50 text-emerald-600">
                                                            <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 20 20" aria-hidden="true" class="text-xl" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                                                <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"></path>
                                                                <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"></path>
                                                            </svg>
                                                        </div>
                                                        <div>
                                                            <div class="text-xs text-gray-500">Email Address</div>
                                                            <div class="font-semibold text-gray-800 break-all"><?= htmlspecialchars($staff['email'] ?? 'N/A') ?></div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="flex items-center gap-4">
                                                        <div class="flex items-center justify-center w-10 h-10 rounded-full bg-blue-50 text-blue-600">
                                                            <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 20 20" aria-hidden="true" class="text-xl" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                                                <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"></path>
                                                            </svg>
                                                        </div>
                                                        <div>
                                                            <div class="text-xs text-gray-500">Phone Number</div>
                                                            <div class="font-semibold text-gray-800"><?= htmlspecialchars($staff['phone'] ?? 'N/A') ?></div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <hr class="my-6 border-gray-200">
                                                
                                                <h6 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-4">Account Details</h6>
                                                
                                                <div class="flex justify-between items-center mb-3">
                                                    <span class="text-sm font-medium text-gray-500">System Role</span>
                                                    <span class="font-semibold text-gray-800 px-2.5 py-0.5 rounded-md bg-gray-100"><?= htmlspecialchars(ucfirst($staff['role'] ?? 'Staff')) ?></span>
                                                </div>
                                                
                                                <div class="flex justify-between items-center mb-3">
                                                    <span class="text-sm font-medium text-gray-500">Current Status</span>
                                                    <span class="flex items-center font-semibold text-sm capitalize <?= $staff['status'] === 'active' ? 'text-emerald-600' : 'text-rose-600' ?>">
                                                        <span class="w-2 h-2 rounded-full mr-2 <?= $staff['status'] === 'active' ? 'bg-emerald-500' : 'bg-rose-500' ?>"></span>
                                                        <?= htmlspecialchars($staff['status'] ?? 'N/A') ?>
                                                    </span>
                                                </div>

                                                <div class="flex justify-between items-center mt-3 pt-3 border-t border-gray-100">
                                                    <span class="text-sm font-medium text-gray-500">Assigned Shift</span>
                                                    <span class="font-semibold text-gray-800 px-2.5 py-0.5 rounded-md bg-blue-50 text-blue-600">
                                                        <?= htmlspecialchars($staff['shift_name'] ?? 'Not Assigned') ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Assigned Patients Side -->
                                    <div class="w-full">
                                        <div class="card adaptable-card">
                                    <div class="card-body">
                                        <div class="mb-4">
                                            <div class="flex justify-between items-center mb-4 f-mb">
                                                <h4>Assigned Patients</h4>
                                                <div class="dataTables_header_end flex items-center">
                                                    <div id="data-table_filter" class="dataTables_filter inline-flex items-center">
                                                        <form action="view_staff.php" method="GET" class="flex items-center" id="searchForm">
                                                            <label class="mr-2 hidden md:block">Search:</label>
                                                            <input type="search" name="search" class="input input-sm" placeholder="Search patients..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" id="searchInput" autocomplete="off">
                                                            <input type="hidden" name="id" value="<?= $staff_id ?>">
                                                            <input type="hidden" name="page" value="1">
                                                        </form>
                                                    </div>
                                                    <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                                                        <button type="button" class="btn btn-solid btn-sm px-3" id="openAssignModalBtn">
                                                            Assign Patient
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="table-container overflow-x-auto">
                                            <table id="data-table" class="table-default table-hover data-table dataTable no-footer">
                                                <thead>
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Email</th>
                                                        <th>Phone</th>
                                                        <th>Status</th>
                                                        <th class="w-24 text-right pr-4">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (empty($patients)): ?>
                                                        <tr><td colspan="5" class="text-center">No patients assigned.</td></tr>
                                                    <?php else: ?>
                                                        <?php foreach ($patients as $patient): ?>
                                                            <tr class="<?= $patient['status'] === 'active' ? 'cursor-pointer hover:bg-gray-100' : 'cursor-not-allowed' ?>" 
                                                                data-id="<?= $patient['id'] ?>">
                                                                <td>
                                                                    <div class="flex items-center">
                                                                        <span class="avatar avatar-circle <?= empty($patient['profile_url']) ? 'bg-emerald-100 text-emerald-600 flex items-center justify-center font-semibold' : '' ?>" style="width: 32px; height: 32px; min-width: 32px; font-size: 14px; border-radius: 50%;">
                                                                            <?php if (!empty($patient['profile_url'])): ?>
                                                                                <img class="avatar-img avatar-circle" style="width: 100%; height: 100%; object-fit: cover;" src="<?= htmlspecialchars($patient['profile_url']) ?>" loading="lazy" alt="Profile">
                                                                            <?php else: ?>
                                                                                <?= htmlspecialchars(strtoupper(substr($patient['username'] ?? 'U', 0, 1))) ?>
                                                                            <?php endif; ?>
                                                                        </span>
                                                                        <span class="ml-2 rtl:mr-2 font-semibold">
                                                                            <?= htmlspecialchars($patient['username']) ?>
                                                                        </span>
                                                                    </div>
                                                                </td>
                                                                <td><?= htmlspecialchars($patient['email']) ?></td>
                                                                <td><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></td>
                                                                <td>
                                                                    <div class="flex items-center">
                                                                        <span class="badge-dot <?= $patient['status'] === 'active' ? 'bg-emerald-500' : 'bg-red-500' ?>"></span>
                                                                        <span class="ml-2 rtl:mr-2 capitalize"><?= htmlspecialchars($patient['status'] ?? 'N/A') ?></span>
                                                                    </div>
                                                                </td>
                                                                <td class="p-3 text-right pr-4">
                                                                    <div class="flex justify-end space-x-3 items-center">
                                                                        <a href="patient_question_details.php?user_id=<?= $patient['id'] ?>" class="action-btn action-btn-blue" title="Patient Questions">
                                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                                                            </svg>
                                                                        </a>
                                                                        <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                                                                            <a href="#" class="action-btn action-btn-red unassign-patient" 
                                                                               data-toggle="modal" data-target="#unassignpatient" 
                                                                               data-entity-id="<?= $patient['id'] ?>" 
                                                                               data-entity-text="<?= htmlspecialchars($patient['username']) ?>" 
                                                                               data-staff-id="<?= $staff_id ?>" title="Unassign Patient">
                                                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                                                </svg>
                                                                            </a>
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
                                </div>

                                <!-- Pagination -->
                                <?php if ($totalPatients > 0): ?>
                                <div class="pagination mt-4 flex justify-between items-center" id="data-pagination">
                                    <div class="pagination-total">Total <span><?= $totalPatients ?></span> Patients</div>
                                    <div class="flex items-center space-x-2">
                                        <span class="pagination-pager pagination-pager-prev<?php echo $pageItems <= 1 ? ' pagination-pager-disabled' : ''; ?>">
                                            <a href="?id=<?= $staff_id ?>&page=<?php echo $pageItems > 1 ? $pageItems - 1 : 1; ?>&search=<?php echo urlencode($search); ?>" class="pagination-link" data-type="patient" data-page="<?php echo $pageItems > 1 ? $pageItems - 1 : 1; ?>">
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
                                                echo '<li class="pagination-pager"><a href="?id=' . $staff_id . '&page=1&search=' . urlencode($search) . '" class="pagination-link" data-type="patient" data-page="1">1</a></li>';
                                                if ($startPage > 2) {
                                                    echo '<li class="pagination-pager"><span>...</span></li>';
                                                }
                                            }
                                            for ($i = $startPage; $i <= $endPage; $i++) {
                                                echo '<li class="pagination-pager' . ($pageItems == $i ? ' active' : '') . '">';
                                                echo '<a href="?id=' . $staff_id . '&page=' . $i . '&search=' . urlencode($search) . '" class="pagination-link" data-type="patient" data-page="' . $i . '">' . $i . '</a>';
                                                echo '</li>';
                                            }
                                            if ($endPage < $totalPages) {
                                                if ($endPage < $totalPages - 1) {
                                                    echo '<li class="pagination-pager"><span>...</span></li>';
                                                }
                                                echo '<li class="pagination-pager"><a href="?id=' . $staff_id . '&page=' . $totalPages . '&search=' . urlencode($search) . '" class="pagination-link" data-type="patient" data-page="' . $totalPages . '">' . $totalPages . '</a></li>';
                                            }
                                            ?>
                                        </ul>
                                        <span class="pagination-pager pagination-pager-next<?php echo $pageItems >= $totalPages ? ' pagination-pager-disabled' : ''; ?>">
                                            <a href="?id=<?= $staff_id ?>&page=<?php echo $pageItems < $totalPages ? $pageItems + 1 : $totalPages; ?>&search=<?php echo urlencode($search); ?>" class="pagination-link" data-type="patient" data-page="<?php echo $pageItems < $totalPages ? $pageItems + 1 : $totalPages; ?>">
                                                <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 20 20" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                                </svg>
                                            </a>
                                        </span>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="pagination mt-4 flex justify-between items-center" id="data-pagination">
                                    <div class="pagination-total">Total <span>0</span> Patients</div>
                                </div>
                                 <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </main>
                    <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                    <!-- Unassign Patient Modal -->
                    <div id="unassignPatientModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50 flex items-center justify-center modal-premium" aria-hidden="true">
                        <div class="dialog rounded-lg max-w-lg w-full m-4">
                            <div class="dialog-content">
                                <span class="close-btn absolute z-10" role="button" data-dismiss="modal">
                                    <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 20 20" aria-hidden="true" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                    </svg>
                                </span>
                                <div class="modal-premium-body">
                                    <div class="modal-icon-container modal-icon-container-red">
                                        <svg stroke="currentColor" fill="none" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7a4 4 0 11-8 0 4 4 0 018 0zM9 14a6 6 0 00-6 6v1h12v-1a6 6 0 00-6-6zM21 12h-6"></path>
                                        </svg>
                                    </div>
                                    <h4>Unassign Patient</h4>
                                    <p class="modal-desc">Are you sure you want to unassign this patient from this staff member?</p>
                                    <div class="flex justify-center mt-4">
                                        <p id="unassign_patient_text" class="font-bold text-gray-900"></p>
                                    </div>
                                </div>
                                <form action="view_staff.php?id=<?= $staff_id ?>&page=<?= $pageItems ?>&search=<?= urlencode($search) ?>" method="POST">
                                    <div class="modal-premium-footer">
                                        <input type="hidden" name="patient_id" id="unassign_patient_id">
                                        <input type="hidden" name="staff_id" value="<?= $staff_id ?>">
                                        <input type="hidden" name="unassign_patient" value="1">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <button type="button" class="btn-modal btn-modal-gray" data-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn-modal bg-red-600 text-white hover:bg-red-700">Yes, Unassign</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Assign Patient Modal -->
                    <div id="assignPatientModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50 flex items-center justify-center modal-premium" aria-hidden="true">
                        <div class="dialog rounded-lg max-w-lg w-full m-4">
                            <div class="dialog-content">
                                <span class="close-btn absolute z-10" role="button" data-dismiss="modal">
                                    <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 20 20" aria-hidden="true" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                    </svg>
                                </span>
                                <div class="modal-premium-body">
                                    <div class="modal-icon-container modal-icon-container-blue">
                                        <svg stroke="currentColor" fill="none" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                                        </svg>
                                    </div>
                                    <h4>Assign Patient</h4>
                                    <p class="modal-desc mb-6">Select a patient to assign to this staff member.</p>
                                    
                                    <form action="view_staff.php?id=<?= $staff_id ?>&page=<?= $pageItems ?>&search=<?= urlencode($search) ?>" method="POST" id="assignForm">
                                        <div class="form-item vertical mb-0">
                                            <label class="form-label font-bold text-gray-700 mb-2">Patient Name</label>
                                            <select name="patient_id" class="input w-full border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" required>
                                                <option value="">-- Choose a Patient --</option>
                                                <?php foreach ($patientsForAssignment as $p): ?>
                                                    <option value="<?= htmlspecialchars($p['id']) ?>"><?= htmlspecialchars($p['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                </div>
                                <div class="modal-premium-footer">
                                    <input type="hidden" name="staff_id" value="<?= $staff_id ?>">
                                    <input type="hidden" name="assign_patient" value="1">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <button type="button" class="btn-modal btn-modal-gray" data-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn-modal bg-blue-600 text-white hover:bg-blue-700">Assign Patient</button>
                                </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php
                    $entityName = 'staff';
                    $actionUrl = "view_staff.php?id=$staff_id&page=$pageItems&search=" . urlencode($search);
                    include './views/layouts/delete_model.php';
                    include './views/layouts/footer.php';
                    ?>
                    <script>
                        // Debounce function for search input
                        function debounce(func, wait) {
                            let timeout;
                            return function executedFunction(...args) {
                                const later = () => {
                                    clearTimeout(timeout);
                                    func(...args);
                                };
                                clearTimeout(timeout);
                                timeout = setTimeout(later, wait);
                            };
                        }

                        // Search input handler
                        document.getElementById('searchInput').addEventListener('input', debounce(function() {
                            document.getElementById('searchForm').submit();
                        }, 500));

                        // Row click handler for patient details
                        document.querySelectorAll('tr.cursor-pointer').forEach(row => {
                            row.addEventListener('click', function(event) {
                                if (event.target.closest('a, span, .assign-dropdown')) {
                                    return;
                                }
                                const id = this.getAttribute('data-id');
                                if (id) {
                                    window.location.href = `patient_question_details.php?user_id=${id}`;
                                }
                            });
                        });

                        <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                        // Open Assign Patient Modal
                        const openAssignModalBtn = document.getElementById('openAssignModalBtn');
                        if (openAssignModalBtn) {
                            openAssignModalBtn.addEventListener('click', function() {
                                document.getElementById('assignPatientModal').classList.remove('hidden');
                            });
                        }

                        document.querySelectorAll('.unassign-patient').forEach(button => {
                            button.addEventListener('click', function(event) {
                                event.stopPropagation(); // Prevent row click
                                const entityId = this.getAttribute('data-entity-id');
                                const entityText = this.getAttribute('data-entity-text');
                                const staffId = this.getAttribute('data-staff-id');
                                document.getElementById('unassign_patient_id').value = entityId;
                                document.getElementById('unassign_patient_text').textContent = entityText;
                                document.getElementById('unassignpatient').classList.remove('hidden');
                            });
                        });

                        // Close modal handler
                        document.querySelectorAll('[data-dismiss="modal"]').forEach(button => {
                            button.addEventListener('click', function() {
                                const unassignModal = document.getElementById('unassignpatient');
                                if (unassignModal) unassignModal.classList.add('hidden');
                                
                                const assignModal = document.getElementById('assignPatientModal');
                                if (assignModal) assignModal.classList.add('hidden');
                            });
                        });
                        <?php endif; ?>
                    </script>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
ob_end_flush();
?>