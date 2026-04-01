<?php
require_once './middleware/auth_check.php';
// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Check if user is logged in and role is valid
$valid_roles = ['admin', 'staff', 'patient'];
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'] ?? '', $valid_roles)) {
    $_SESSION['message'] = "Please log in to access the dashboard.";
    $_SESSION['message_type'] = "error";
    header("Location: ./signin.php");
    exit;
}
// Set role flags
$is_admin = $_SESSION['user']['role'] === 'admin';
$is_staff = $_SESSION['user']['role'] === 'staff';
$is_patient = $_SESSION['user']['role'] === 'patient';
// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$page_title = 'Answer Logs';
require_once './config/database.php';
require_once './controllers/PatientController.php';
include './views/layouts/head.php';
include './views/layouts/alert.php';
// Initialize controller with PDO
$controller = new PatientController($pdo);
// Validate user_id from GET parameter
$user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
if ($user_id === false || $user_id === null) {
    // For patients, use their own ID; for admin/staff, user_id is required
    $user_id = $is_patient ? $_SESSION['user']['id'] : null;
}

// Staff permission check: can only see assigned patients
if ($is_staff && $user_id) {
    if (!$controller->isPatientAssignedToStaff($user_id, $_SESSION['user']['id'])) {
        $_SESSION['message'] = "Unauthorized access. This patient is not assigned to you.";
        $_SESSION['message_type'] = "error";
        header("Location: ./dashboard.php");
        exit;
    }
}
// Determine back link based on user role
$back_link = '';
if ($is_admin) {
    $back_link = "patient.php?user_id=" . htmlspecialchars($user_id ?? '', ENT_QUOTES, 'UTF-8');
} elseif ($is_staff) {
    $back_link = "dashboard.php";
} elseif ($is_patient) {
    $back_link = "patient-details.php?user_id=" . htmlspecialchars($user_id ?? '', ENT_QUOTES, 'UTF-8');
}

// Block access to inactive patient profiles (except for administrators)
if (isset($user_id) && $user_id !== null && !$is_admin) {
    if (!$controller->isUserActive($user_id)) {
        $_SESSION['message'] = "Access denied. This patient is inactive.";
        $_SESSION['message_type'] = "error";
        header("Location: ./dashboard.php");
        exit;
    }
}

// Fetch all available shifts for filtering
$stmtShifts = $pdo->query("SELECT id, name FROM note_types ORDER BY id ASC");
$shifts = $stmtShifts->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    /* Role Pill Badges (Matching Patient Window) */
    .role-badge { font-size: 11px; font-weight: 700; padding: 4px 12px; border-radius: 20px; letter-spacing: 0.04em; text-transform: uppercase; }
    .role-badge.patient { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
    .role-badge.staff { background: #eff6ff; color: #3b82f6; border: 1px solid #bfdbfe; }
    .role-badge.admin { background: #eef2ff; color: #6366f1; border: 1px solid #c7d2fe; }

    /* Custom Pagination (Matching Other Pages) */
    .custom-pagination-container { display: flex; align-items: center; gap: 8px; padding: 10px 0; }
    .custom-page-btn { min-width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; font-size: 13px; font-weight: 700; color: #5ba590; background: transparent; border: 1px solid transparent; transition: all 0.2s; cursor: pointer; }
    .custom-page-btn.active { background: linear-gradient(135deg, #5ba590, #4a9a84); color: white !important; box-shadow: 0 4px 10px rgba(91,165,144,0.3); border-color: transparent; }
    .custom-page-btn:hover:not(.active):not(:disabled) { background: rgba(91,165,144,0.1); border-color: rgba(91,165,144,0.2); transform: translateY(-1px); }
    .custom-page-btn:disabled { color: #d1d5db; cursor: not-allowed; }
    .custom-pagination-container span { color: #5ba590; font-weight: 700; }

    .filter-container select, .filter-container input[type="date"] {
        background-color: #f9fafb;
        border: 1px solid #d1d5db;
        color: #111827;
        font-size: 0.875rem;
        border-radius: 0.5rem;
        padding: 0.625rem;
        height: 38px;
        outline: none;
        transition: all 0.2s;
    }

    .filter-container select:focus, .filter-container input[type="date"]:focus {
        border-color: #10b981;
        ring: 2px rgba(16, 185, 129, 0.2);
    }

    .filter-container select {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
        background-position: right 0.5rem center;
        background-repeat: no-repeat;
        background-size: 1.5em 1.5em;
        padding-right: 2.5rem;
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
    }

    /* New Pagination Style */
    .pagination-container {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .pagination-btn {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        font-weight: 700;
        transition: all 0.2s;
        background: transparent;
        cursor: pointer;
        border: none;
        font-size: 14px;
    }

    .pagination-btn.active {
        background: #66b19c;
        color: white;
        box-shadow: 0 4px 12px rgba(102, 177, 156, 0.35);
    }

    .pagination-btn.inactive {
        color: #66b19c;
    }

    .pagination-btn:hover:not(.active) {
        background: rgba(102, 177, 156, 0.1);
    }

    .pagination-arrow {
        color: #66b19c;
        font-size: 1.1rem;
        padding: 0 5px;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
    }

    .pagination-arrow:hover:not(.disabled) {
        transform: scale(1.1);
    }

    .pagination-arrow.disabled {
        color: #d1d5db;
        cursor: not-allowed;
        opacity: 0.5;
    }
</style>

<body class="bg-gray-50">
    <div id="root">
        <div class="app-layout-modern flex flex-col min-h-screen">
            <div class="flex flex-auto min-w-0">
                <?php include './views/layouts/sidebar.php'; ?>
                <div class="main-content flex flex-col flex-auto min-h-screen min-w-0 relative w-full bg-white border-l border-gray-200">
                    <?php include './views/layouts/header.php'; ?>
                    <main class="h-full">
                        <div class="page-container relative h-full flex flex-auto flex-col px-4 sm:px-6 md:px-8 py-4 sm:py-6">
                            <div class="mb-10 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-6 border-b pb-6">
                                <div>
                                    <h3 class="text-2xl font-bold text-gray-800">Assessment Logs</h3>
                                    <p class="text-gray-500 text-sm font-medium">History of assessment submissions and responses</p>
                                </div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <div class="filter-container flex flex-wrap items-center gap-4">
                                        <div class="flex items-center gap-3">
                                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Shift</span>
                                            <select id="shiftFilter" class="min-w-[140px]">
                                                <option value="">All Shifts</option>
                                                <?php foreach ($shifts as $s): ?>
                                                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="flex items-center gap-4">
                                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">From</span>
                                            <input type="date" id="startDate">
                                        </div>
                                        <div class="flex items-center gap-4">
                                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">To</span>
                                            <input type="date" id="endDate">
                                        </div>
                                    </div>
                                    <a href="<?php echo $back_link; ?>" class="btn btn-outline btn-sm h-[38px] flex items-center">
                                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                        </svg>
                                        Back
                                    </a>
                                    <?php if ($is_admin || $is_staff): ?>
                                        <a href="export_logs.php?user_id=<?= $user_id ?>" class="btn btn-solid btn-sm h-[38px] flex items-center">
                                            <i class="fas fa-file-excel mr-1.5 opacity-70"></i>Export Data
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?= displayAlert() ?>

                            <div class="bg-white shadow-sm rounded-xl border border-gray-200 overflow-hidden">
                                <div class="table-container overflow-x-auto">
                                    <table class="w-full text-left">
                                        <thead>
                                            <tr class="bg-gray-50/50 border-b border-gray-200">
                                                <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-[11px] w-[180px] text-left">Submission ID</th>
                                                <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-[11px] text-left">Assessed By</th>
                                                <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-[11px] text-left">Shift</th>
                                                <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-[11px] text-left">Submission Time</th>
                                                <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-[11px] text-left w-[180px]">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="logsTableBody" class="divide-y divide-gray-100">
                                            <!-- Logs will be loaded here via AJAX -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="p-6 mt-10 flex flex-col md:flex-row justify-between items-center gap-4">
                                <div id="logsTable_info" class="text-[13px] font-medium text-gray-400"></div>
                                <div id="logsTable_paginate" class="flex items-center space-x-1"></div>
                            </div>
                        </div>
                    </main>
                    <?php include './views/layouts/footer.php'; ?>
                </div>
            </div>
        </div>
    </div>
   <script>
$(document).ready(function() {
    const userId = <?= json_encode($user_id ?? 0) ?>;
    const csrfToken = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
    const itemsPerPage = 10;
    let currentPage = 1;
    let totalLogs = 0;
    // Helper function for AJAX requests
    function makeAjaxRequest(url, method, data, contentType = 'application/x-www-form-urlencoded; charset=UTF-8') {
        return $.ajax({
            url: url,
            method: method,
            data: data,
            contentType: contentType,
            headers: { 'X-CSRF-Token': csrfToken },
            dataType: 'json'
        }).fail(function(xhr, status, error) {
            console.error('AJAX error:', {
                status: xhr.status,
                statusText: xhr.statusText,
                response: xhr.responseJSON || xhr.responseText,
                error: error
            });
            showError('Error: ' + (xhr.responseJSON?.message || 'An unexpected error occurred'));
        });
    }
    // Show error message
    function showError(message) {
        $('#logsTableBody').html(`<tr><td colspan="5" class="border border-blue-700 p-2 text-center text-sm text-gray-500">${message}</td></tr>`);
        $('#logsTable_paginate').empty();
        $('#logsTable_info').text('Showing 0 to 0 of 0 entries');
    }
    // Load answer logs and pagination
    function loadAnswerLogs(page = 1) {
        if (!userId || !Number.isInteger(userId) || userId <= 0) {
            console.warn('Invalid user ID:', userId);
            showError('Invalid user ID');
            return;
        }
        // Ensure page is within valid range
        currentPage = Math.max(1, page);
        makeAjaxRequest('api/api.php?action=get_answer_logs', 'GET', {
            user_id: userId,
            page: currentPage,
            limit: itemsPerPage,
            shift_id: $('#shiftFilter').val(),
            start_date: $('#startDate').val(),
            end_date: $('#endDate').val()
        }).done(function(response) {
            console.log('API Response:', response);
            const logsTableBody = $('#logsTableBody');
            const pagination = $('#logsTable_paginate');
            const info = $('#logsTable_info');
            logsTableBody.empty();
            pagination.empty();

            if (response.success && Array.isArray(response.data) && response.data.length > 0) {
                response.data.forEach((log, index) => {
                    const dt = new Date(log.created_at);
                    const formattedDate = dt.toLocaleDateString('en-AU', { 
                        day: '2-digit', month: '2-digit', year: 'numeric',
                        timeZone: 'Asia/Kolkata'
                    });
                    const formattedTimeOnly = dt.toLocaleTimeString('en-AU', { 
                        hour: '2-digit', minute: '2-digit', hour12: true,
                        timeZone: 'Asia/Kolkata'
                    });
                    
                    const shiftHtml = log.shift_name 
                        ? `<span class="px-2 py-1 bg-[#e6f4f1] text-[#0f766e] text-[11px] font-bold uppercase tracking-wider rounded border border-[#ccf0e6]">${log.shift_name}</span>`
                        : `<span class="text-gray-400">—</span>`;

                    const serialNumber = ((currentPage - 1) * itemsPerPage) + index + 1;

                    logsTableBody.append(`
                        <tr class="hover:bg-gray-50/50 transition-all duration-200 border-b border-gray-100 last:border-0">
                            <td class="px-6 py-4">
                                <span class="text-gray-900 font-semibold text-[13px]">
                                    #${serialNumber}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <div class="h-8 w-8 rounded-full bg-teal-50 text-teal-600 flex items-center justify-center mr-3 border border-teal-100/50">
                                        <i class="fas fa-user text-[12px]"></i>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-bold text-gray-800 text-[14px]">${log.username || 'System Admin'}</span>
                                        <div class="flex items-center gap-1.5 mt-0.5">
                                            <span class="role-badge ${log.submitted_role || 'patient'}" style="width: fit-content; line-height: 1.2;">
                                                ${log.submitted_role || 'Patient'}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                ${shiftHtml}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2 text-gray-600">
                                    <span class="text-[13px] font-medium">${formattedDate}</span>
                                    <span class="text-gray-300">•</span>
                                    <span class="text-[12px] text-gray-500 font-medium">${formattedTimeOnly}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-left">
                                <a href="view_log.php?user_id=${userId}&submission_id=${log.submission_id}" 
                                   class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-emerald-50 text-emerald-500 hover:bg-emerald-500 hover:text-white hover:scale-110 active:scale-95 transition-all duration-200 shadow-sm border border-emerald-100" 
                                   title="View Details">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    `);
                });

                const startCount = ((currentPage - 1) * itemsPerPage) + 1;
                const endCount = Math.min(currentPage * itemsPerPage, response.totalLogs);
                info.html(`Showing <span class="font-bold text-gray-700">${startCount} to ${endCount}</span> of <span class="font-bold text-gray-700">${response.totalLogs}</span> entries`);

                renderPagination(response.totalLogs, currentPage);
            } else {
                logsTableBody.html('<tr><td colspan="5" class="text-center py-4 text-gray-500">No logs found.</td></tr>');
                info.text('Showing 0 to 0 of 0 entries');
                renderPagination(0, 1);
            }
        });
    }
    // Render pagination
    function renderPagination(totalItems, currentPage) {
        const pagination = $('#logsTable_paginate');
        const totalPages = Math.ceil(totalItems / itemsPerPage) || 1;
        pagination.empty();
        
        if (totalPages <= 1 && totalItems === 0) return;

        let html = '<div class="pagination-container">';
        
        // Prev Arrow
        html += `<div class="pagination-arrow ${currentPage === 1 ? 'disabled' : ''}" data-page="${currentPage - 1}">
                    <i class="fas fa-chevron-left text-xs"></i>
                </div>`;

        let start = Math.max(1, currentPage - 2);
        let end = Math.min(totalPages, start + 4);
        if (end - start < 4) start = Math.max(1, end - 4);

        if (start > 1) {
            html += `<button class="pagination-btn inactive" data-page="1">1</button>`;
            if (start > 2) html += `<span class="text-gray-300">...</span>`;
        }

        for (let i = start; i <= end; i++) {
            html += `<button class="pagination-btn ${i === currentPage ? 'active' : 'inactive'}" data-page="${i}">${i}</button>`;
        }

        if (end < totalPages) {
            if (end < totalPages - 1) html += `<span class="text-gray-300">...</span>`;
            html += `<button class="pagination-btn inactive" data-page="${totalPages}">${totalPages}</button>`;
        }

        // Next Arrow
        html += `<div class="pagination-arrow ${currentPage === totalPages ? 'disabled' : ''}" data-page="${currentPage + 1}">
                    <i class="fas fa-chevron-right text-xs"></i>
                </div>`;

        html += '</div>';
        
        pagination.html(html);

        $('.pagination-btn, .pagination-arrow:not(.disabled)').on('click', function(e) {
            e.preventDefault();
            const newPage = parseInt($(this).data('page'));
            if (newPage && newPage >= 1 && newPage <= totalPages && newPage !== currentPage) {
                currentPage = newPage;
                loadAnswerLogs(currentPage);
            }
        });
    }
    // Event listener for filter
    $('#shiftFilter, #startDate, #endDate').on('change', function() {
        currentPage = 1;
        loadAnswerLogs(currentPage);
    });

    // Initial load
    loadAnswerLogs(currentPage);
});
</script>
</body>
</html>