<?php
require_once './middleware/auth_check.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in
if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
    $_SESSION['message'] = "Please log in to view your details.";
    $_SESSION['message_type'] = "error";
    header("Location: ./signin.php");
    exit;
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'User Details';
include './views/layouts/head.php';
require_once './config/database.php';
include './views/layouts/alert.php';

try {
    $user = $_SESSION['user'];
    $user_id = $user['id'];

    // Fetch user details with assigned staff
    $stmt = $pdo->prepare("
        SELECT u.*, s.username as assigned_staff 
        FROM users u 
        LEFT JOIN staff_patient_assignments spa ON u.id = spa.patient_id 
        LEFT JOIN users s ON spa.staff_id = s.id 
        WHERE u.id = :id
    ");
    $stmt->execute(['id' => $user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_data) {
        $_SESSION['message'] = "User details not found.";
        $_SESSION['message_type'] = "error";
        header("Location: ./signin.php");
        exit;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit;
}
?>

<style>
    /* Premium Badges */
    .patient-header-badge {
        background: linear-gradient(135deg, #d1fae5, #a7f3d0);
        color: #059669; font-size: 11px; padding: 3px 12px;
        border-radius: 20px; font-weight: 700; letter-spacing: 0.04em;
        border: 1px solid #6ee7b7; text-transform: uppercase;
    }
    
    /* Modern Submit Button */
    .submit-answer-btn {
        background: linear-gradient(135deg, #5eaa94 0%, #3d8b74 100%);
        color: white; padding: 10px 22px; border-radius: 10px;
        font-weight: 700; font-size: 13px; letter-spacing: 0.02em;
        transition: all 0.25s ease; box-shadow: 0 4px 12px rgba(94,170,148,0.35);
        display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
    }
    .submit-answer-btn:hover {
        background: linear-gradient(135deg, #4a9a84 0%, #2f7860 100%);
        color: white; transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(94,170,148,0.45); text-decoration: none;
    }
    .submit-answer-btn:active { transform: translateY(0); }

    /* View All Logs Button */
    .view-all-logs-btn {
        background: #f0fdf9; color: #10b981; padding: 10px 22px; border-radius: 10px;
        font-weight: 700; font-size: 13px; letter-spacing: 0.02em;
        transition: all 0.25s ease; border: 1px solid #10b981;
        display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
    }
    .view-all-logs-btn:hover {
        background: #10b981; color: white;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25); text-decoration: none;
    }
    .view-all-logs-btn:active { transform: translateY(0); }

    /* Polished Table Header */
    .logs-table-header { background: linear-gradient(135deg, #5ba590 0%, #4a9a84 100%) !important; }
    .logs-table-header th { color: rgba(255,255,255,0.95) !important; font-weight: 700; text-transform: uppercase; font-size: 11px; letter-spacing: 0.08em; padding: 14px 16px !important; border: none !important; }

    /* Premium User Details Card */
    .user-details-card { border: 1px solid #e9f5f1; border-radius: 16px; overflow: hidden; background: #fff; box-shadow: 0 2px 14px rgba(0,0,0,0.05); padding: 0; }
    .user-card-header { background: linear-gradient(135deg, #5ba590 0%, #3d8b74 100%); padding: 22px 24px; position: relative; }
    .user-card-body { padding: 22px 24px; }
    
    .detail-item { display: flex; align-items: center; gap: 12px; color: #64748b; margin-bottom: 14px; font-size: 13.5px; }
    .detail-icon { width: 32px; height: 32px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 13px; }
    .detail-icon.mail { background: #eff6ff; color: #3b82f6; }
    .detail-icon.phone { background: #f0fdf4; color: #22c55e; }
    
    /* Role & Status Pill Badges */
    .role-badge { font-size: 11px; font-weight: 700; padding: 4px 12px; border-radius: 20px; letter-spacing: 0.04em; text-transform: uppercase; }
    .role-badge.patient { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
    .role-badge.staff { background: #eff6ff; color: #3b82f6; border: 1px solid #bfdbfe; }
    .role-badge.admin { background: #eef2ff; color: #6366f1; border: 1px solid #c7d2fe; }
    .status-badge { font-size: 11px; font-weight: 700; padding: 4px 12px; border-radius: 20px; background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; text-transform: uppercase; }

    /* Custom Pagination */
    .custom-pagination-container { display: flex; align-items: center; gap: 8px; padding: 10px 0; }
    .custom-page-btn { min-width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; border-radius: 10px; font-size: 13px; font-weight: 700; color: #5ba590; background: transparent; border: 1px solid transparent; transition: all 0.2s; cursor: pointer; }
    .custom-page-btn.active { background: linear-gradient(135deg, #5ba590, #4a9a84); color: white !important; box-shadow: 0 4px 10px rgba(91,165,144,0.3); border-color: transparent; }
    .custom-page-btn:hover:not(.active):not(:disabled) { background: rgba(91,165,144,0.1); border-color: rgba(91,165,144,0.2); transform: translateY(-1px); }
    .custom-page-btn:disabled { color: #d1d5db; cursor: not-allowed; }
    .custom-pagination-container span { color: #5ba590; font-weight: 700; }

    /* New Pagination Style */
    .pagination-container {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .pagination-btn {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        font-weight: 700;
        transition: all 0.2s;
        background: transparent;
        cursor: pointer;
        border: none;
        font-size: 13px;
    }

    .pagination-btn.active {
        background: #66b19c;
        color: white;
        box-shadow: 0 4px 10px rgba(102, 177, 156, 0.3);
    }

    .pagination-btn.inactive {
        color: #66b19c;
    }

    .pagination-btn:hover:not(.active) {
        background: rgba(102, 177, 156, 0.1);
    }

    .pagination-arrow {
        color: #66b19c;
        font-size: 1rem;
        padding: 0 4px;
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
    /* View button - Cute & Modern (Icon Only) */
    .view-btn {
        display: inline-flex; align-items: center; justify-content: center;
        width: 30px; height: 30px; border-radius: 50%;
        background: #f0fdf9; color: #10b981; border: 1px solid #a7f3d0;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        box-shadow: 0 1px 3px rgba(16, 185, 129, 0.1); text-decoration: none;
    }
    .view-btn:hover {
        background: #10b981; color: white; border-color: transparent;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);
        transform: scale(1.1); text-decoration: none;
    }
    .view-btn:active { transform: scale(0.95); }
</style>

<div id="root">
    <div class="app-layout-modern flex flex-auto flex-col">
        <div class="flex flex-auto min-w-0">
            <?php include './views/layouts/sidebar.php'; ?>
            <div class="flex flex-col flex-auto min-h-screen min-w-0 relative w-full bg-white border-l border-gray-100">
                <?php include './views/layouts/header.php'; ?>
                <div class="h-full flex flex-auto flex-col">
                    <main class="h-full">
                        <div class="page-container relative h-full flex flex-auto flex-col px-6 py-8">
                            <div class="container mx-auto">
                                <!-- Title Section -->
                                <div class="mb-8">
                                    <div class="flex items-center gap-3 mb-2">
                                        <h2 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($user_data['username']) ?></h2>
                                        <span class="patient-header-badge">active</span>
                                    </div>
                                    <div class="text-gray-400 text-sm flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" class="opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2-2v12a2 2 0 002 2z" /></svg>
                                        <?= date('Y-m-d H:i:s', strtotime($user_data['created_at'])) ?>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                                    <!-- Left Column: Logs -->
                                    <div class="lg:col-span-2">
                                        <div class="flex justify-between items-center mb-6">
                                            <div>
                                                <h3 class="text-xl font-bold text-gray-800">All Answer Logs</h3>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <a href="logs.php?user_id=<?= $user_id ?>" class="view-all-logs-btn">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16A2 2 0 014 12z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 18h7" />
                                                    </svg>
                                                    View All Logs
                                                </a>
                                                <a href="patient_question_details.php?user_id=<?= $user_id ?>" class="submit-answer-btn">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                                                    Submit Answer
                                                </a>
                                            </div>
                                        </div>

                                        <div class="overflow-hidden rounded-xl border border-gray-100 shadow-sm">
                                            <table class="w-full text-left">
                                                <thead class="logs-table-header">
                                                    <tr>
                                                        <th class="text-center">LOG</th>
                                                        <th class="text-center text-left px-6">ASSESSED BY</th>
                                                        <th class="text-center">SUBMISSION TIME</th>
                                                        <th class="text-center">ACTIONS</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="logsTableBody" class="divide-y divide-gray-50">
                                                    <!-- Loaded by JS -->
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <!-- Pagination Info -->
                                        <div class="mt-6 flex items-center justify-between text-gray-500 text-sm">
                                            <div id="logsTable_info">Showing 0 to 0 of 0 entries</div>
                                            <div id="logsTable_paginate" class="flex items-center gap-2">
                                                <!-- Loaded by JS -->
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Right Column: User Details -->
                                    <div class="lg:col-span-1">
                                        <div class="user-details-card mb-6">
                                            <!-- Card Header -->
                                            <div class="user-card-header">
                                                <div class="flex items-center gap-4">
                                                    <div class="w-14 h-14 rounded-full overflow-hidden border-2 border-white/40 shadow flex-shrink-0">
                                                        <img id="profileImageDisplay" src="<?= !empty($user_data['profile_url']) ? htmlspecialchars($user_data['profile_url']) : 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0iI2QxZDVkYiI+PHBhdGggZD0iTTEyIDEyYzIuMjEgMCA0LTEuNzkgNC00cy0xLjc5LTQtNC00LTQgMS43OS00IDQgMS43OSA0IDQgNHptMCAyYy0yLjY3IDAtOCAxLjM0LTggNHYyaDE2di0yYzAtMi42Ni01LjMzLTQtOC00eiIvPjwvc3ZnPg==' ?>" class="w-full h-full object-cover" alt="" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0iI2QxZDVkYiI+PHBhdGggZD0iTTEyIDEyYzIuMjEgMCA0LTEuNzkgNC00cy0xLjc5LTQtNC00LTQgMS43OS00IDQgMS43OSA0IDQgNHptMCAyYy0yLjY3IDAtOCAxLjM0LTggNHYyaDE2di0yYzAtMi42Ni01LjMzLTQtOC00eiIvPjwvc3ZnPg=='">
                                                    </div>
                                                    <div>
                                                        <div class="font-bold text-white text-[15px]"><?= htmlspecialchars($user_data['username']) ?></div>
                                                        <div class="text-white/70 text-xs mt-0.5 tracking-wide">ID · <?= htmlspecialchars($user_data['id']) ?></div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Card Body -->
                                            <div class="user-card-body">
                                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-4">Contact Info</p>
                                                <div class="space-y-3 mb-6">
                                                    <div class="detail-item">
                                                        <div class="detail-icon mail"><i class="fas fa-envelope"></i></div>
                                                        <span class="truncate font-medium"><?= htmlspecialchars($user_data['email'] ?? 'Not provided') ?></span>
                                                    </div>
                                                    <div class="detail-item">
                                                        <div class="detail-icon phone"><i class="fas fa-phone"></i></div>
                                                        <span class="font-medium"><?= htmlspecialchars($user_data['phone'] ?? 'Not provided') ?></span>
                                                    </div>
                                                </div>

                                                <div class="border-t border-gray-100 pt-5 mt-2">
                                                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-4">Role & Status</p>
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-gray-500 font-bold text-sm">Role</span>
                                                        <span class="role-badge <?= htmlspecialchars($user_data['role']) ?>"><?= htmlspecialchars($user_data['role']) ?></span>
                                                    </div>
                                                    <div class="flex items-center justify-between mt-3">
                                                        <span class="text-gray-500 font-bold text-sm">Status</span>
                                                        <span class="status-badge"><?= htmlspecialchars($user_data['status'] ?? 'active') ?></span>
                                                    </div>
                                                    <div class="flex items-center justify-between mt-3">
                                                        <span class="text-gray-500 font-bold text-sm">Assigned Staff</span>
                                                        <span class="text-sm font-bold text-emerald-600"><?= htmlspecialchars($user_data['assigned_staff'] ?? 'Not Assigned') ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </main>
                    
                    <footer class="footer flex flex-auto items-center h-16 px-6 border-t border-gray-100">
                        <div class="flex items-center justify-between w-full text-xs text-gray-400">
                            <span>Copyright © <?= date('Y') ?> <span class="font-bold">Cocoonbaby</span></span>
                            <div class="flex gap-4">
                                <a href="javascript:void(0)">Terms & Conditions</a>
                                <a href="javascript:void(0)">Privacy & Policy</a>
                            </div>
                        </div>
                    </footer>
                </div>
            </div>
        </div>
    </div>

    <!-- Core Vendors and App JS -->
    <script src="./public/js/vendors.min.js"></script>
    <script src="./public/js/app.min.js"></script>
    
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Notifications -->
    <script src="./public/js/notifications.js?v=1.3"></script>
    <script>
    $(document).ready(function() {
        const userId = <?= json_encode($user_id) ?>;
        const csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;

        function loadAnswerLogs(page = 1) {
            const logsTableBody = $('#logsTableBody');
            logsTableBody.html('<tr><td colspan="4" class="p-8 text-center"><i class="fas fa-spinner fa-spin text-gray-300 text-2xl"></i></td></tr>');

            $.ajax({
                url: 'api/api.php?action=get_answer_logs',
                method: 'GET',
                data: { user_id: userId, page: page, limit: 5 },
                headers: { 'X-CSRF-Token': csrfToken },
                dataType: 'json'
            }).done(function(response) {
                logsTableBody.empty();

                if (!response.success || !response.data || response.data.length === 0) {
                    logsTableBody.append('<tr><td colspan="4" class="p-8 text-center text-gray-400 italic">No logs available.</td></tr>');
                    $('#logsTable_info').text('Showing 0 to 0 of 0 entries');
                    $('#logsTable_paginate').empty();
                    return;
                }

                const startCount = ((page - 1) * 5) + 1;
                const endCount = Math.min(page * 5, response.totalLogs);
                $('#logsTable_info').html(`Showing <span class="font-bold text-gray-700">${startCount} to ${endCount}</span> of <span class="font-bold text-gray-700">${response.totalLogs}</span> entries`);

                $.each(response.data, function(i, log) {
                    const serialNumber = ((page - 1) * 5) + i + 1;
                    
                    logsTableBody.append(`
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="p-4 text-center text-sm font-semibold text-gray-700">#${serialNumber}</td>
                            <td class="p-4 px-6">
                                <div class="flex items-center">
                                    <div class="h-8 w-8 rounded-full bg-teal-50 text-teal-600 flex items-center justify-center mr-3 border border-teal-100/50">
                                        <i class="fas fa-user text-[12px]"></i>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-bold text-gray-800 text-sm">${log.username || 'System'}</span>
                                        <div class="flex items-center gap-1.5 mt-0.5">
                                            <span class="role-badge ${log.submitted_role || 'patient'}" style="width: fit-content; line-height: 1.2;">
                                                ${log.submitted_role || 'Patient'}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4 text-center text-sm text-gray-500 font-medium">
                                ${new Date(log.created_at.replace(/-/g, "/")).toLocaleDateString('en-AU', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'Asia/Kolkata' })}
                                <div class="text-[10px] text-gray-400 font-normal">
                                    ${new Date(log.created_at.replace(/-/g, "/")).toLocaleTimeString('en-AU', { hour: '2-digit', minute: '2-digit', hour12: true, timeZone: 'Asia/Kolkata' })}
                                </div>
                            </td>
                            <td class="p-4 text-center">
                                <a href="view_log.php?user_id=${userId}&submission_id=${log.submission_id}" class="view-btn" title="View Details">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    `);
                });
                renderPagination(response.totalLogs, page);
            }).fail(function(xhr) {
                logsTableBody.html('<tr><td colspan="4" class="p-8 text-center text-red-400">Failed to load logs. Please refresh.</td></tr>');
            });
        }

        function renderPagination(total, current) {
            const pages = Math.ceil(total / 5);
            const paginationContainer = $('#logsTable_paginate');
            paginationContainer.empty();
            
            if (pages <= 1 && total === 0) return;

            let html = '<div class="pagination-container">';
            
            // Prev Arrow
            html += `<div class="pagination-arrow ${current === 1 ? 'disabled' : ''}" data-page="${current - 1}">
                        <i class="fas fa-chevron-left"></i>
                    </div>`;

            let start = Math.max(1, current - 2);
            let end = Math.min(pages, start + 4);
            if (end - start < 4) start = Math.max(1, end - 4);

            if (start > 1) {
                html += `<button class="pagination-btn inactive" data-page="1">1</button>`;
                if (start > 2) html += `<span class="text-gray-300">...</span>`;
            }

            for (let i = start; i <= end; i++) {
                html += `<button class="pagination-btn ${i === current ? 'active' : 'inactive'}" data-page="${i}">${i}</button>`;
            }

            if (end < pages) {
                if (end < pages - 1) html += `<span class="text-gray-300">...</span>`;
                html += `<button class="pagination-btn inactive" data-page="${pages}">${pages}</button>`;
            }

            // Next Arrow
            html += `<div class="pagination-arrow ${current === pages ? 'disabled' : ''}" data-page="${current + 1}">
                        <i class="fas fa-chevron-right"></i>
                    </div>`;

            html += '</div>';
            
            paginationContainer.html(html);

            $('.pagination-btn, .pagination-arrow:not(.disabled)').on('click', function(e) {
                e.preventDefault();
                const newPage = parseInt($(this).data('page'));
                if (newPage && newPage >= 1 && newPage <= pages && newPage !== current) {
                    loadAnswerLogs(newPage);
                }
            });
        }

        loadAnswerLogs(1);
    });
    </script>
</body>
</html>