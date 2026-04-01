<?php
require_once './middleware/auth_check.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$valid_roles = ['admin', 'staff', 'patient'];
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'] ?? '', $valid_roles)) {
    $_SESSION['message'] = "Please log in to access the dashboard.";
    $_SESSION['message_type'] = "error";
    header("Location: ./signin.php");
    exit;
}
$is_admin = $_SESSION['user']['role'] === 'admin';
$is_staff = $_SESSION['user']['role'] === 'staff';
$is_patient = $_SESSION['user']['role'] === 'patient';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$page_title = 'Submission Details';
require_once './config/database.php';
include './views/layouts/head.php';
include './views/layouts/alert.php';
$user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
$submission_id = filter_input(INPUT_GET, 'submission_id', FILTER_VALIDATE_INT);

if ($user_id === false || $user_id === null) {
    if ($submission_id !== false && $submission_id !== null) {
        $stmtUser = $pdo->prepare("SELECT user_id FROM answer_logs WHERE submission_id = ? LIMIT 1");
        $stmtUser->execute([$submission_id]);
        $fetched_user = $stmtUser->fetchColumn();
        if ($fetched_user) {
            $user_id = (int)$fetched_user;
        }
    }
    if ($user_id === false || $user_id === null) {
        $user_id = $is_patient ? $_SESSION['user']['id'] : null;
    }
}

// Staff permission check: can only see assigned patients
if ($is_staff && $user_id) {
    require_once './controllers/PatientController.php';
    $controller_staff = new PatientController($pdo);
    if (!$controller_staff->isPatientAssignedToStaff($user_id, $_SESSION['user']['id'])) {
        $_SESSION['message'] = "Unauthorized access. This patient is not assigned to you.";
        $_SESSION['message_type'] = "error";
        header("Location: ./dashboard.php");
        exit;
    }
}
if ($submission_id === false || $submission_id === null) {
    $_SESSION['message'] = "Invalid submission ID.";
    $_SESSION['message_type'] = "error";
    header("Location: ./logs.php?user_id=" . urlencode($user_id ?? ''));
    exit;
}
if ($is_patient && $user_id !== $_SESSION['user']['id']) {
    $_SESSION['message'] = "Unauthorized access.";
    $_SESSION['message_type'] = "error";
    header("Location: ./logs.php?user_id=" . urlencode($_SESSION['user']['id']));
    exit;
}

// Block access to inactive patient profiles (except for administrators)
if (isset($user_id) && !$is_admin) {
    require_once './controllers/PatientController.php';
    $controller = new PatientController($pdo);
    if (!$controller->isUserActive($user_id)) {
        $_SESSION['message'] = "Access denied. This patient is inactive.";
        $_SESSION['message_type'] = "error";
        header("Location: ./dashboard.php");
        exit;
    }
}
?>
<style>
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
        text-decoration: none;
    }

    .pagination-btn.active {
        background: #66b19c;
        color: white !important;
        box-shadow: 0 4px 10px rgba(102, 177, 156, 0.3);
    }

    .pagination-btn.inactive {
        color: #66b19c !important;
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
<body class="bg-gray-50">
    <div id="root">
        <div class="app-layout-modern flex flex-col min-h-screen">
            <div class="flex flex-auto min-w-0">
                <?php include './views/layouts/sidebar.php'; ?>
                <div class="main-content flex flex-col flex-auto min-h-screen min-w-0 relative w-full bg-white border-l border-gray-200">
                    <?php include './views/layouts/header.php'; ?>
                    <main class="h-full">
                        <div class="page-container relative h-full flex flex-auto flex-col px-4 sm:px-6 md:px-8 py-4 sm:py-6">
                            <div class="flex justify-between items-center mb-4">
                                <h2 class="text-xl font-semibold text-gray-800">Submission Details</h2>
                                <?= displayAlert() ?>
                            </div>
                            <div class="card adaptable-card">
                                <div class="card-body">
                                    <div id="logDetails" class="space-y-6">
                                        <!-- Log entries will be rendered here as cards -->
                                    </div>
                                    <div class="mt-4 flex flex-col md:flex-row justify-between items-center gap-4">
                                        <div id="logDetails_info" class="text-sm text-gray-600"></div>
                                        <div id="logDetails_paginate" class="flex justify-end"></div>
                                    </div>
                                    <div class="mt-6">
                                        <a href="logs.php?user_id=<?php echo htmlspecialchars($user_id ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-default btn-sm">Back to Logs</a>
                                    </div>
                                </div>
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
    console.log('Document ready, initializing view_log.php');
    const userId = <?php echo json_encode($user_id ?? 0); ?>;
    const submissionId = <?php echo json_encode($submission_id ?? 0); ?>;
    const csrfToken = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
    const itemsPerPage = 10;
    let currentPage = 1;
    let isLoading = false;
    let totalLogs = 0;
    function makeAjaxRequest(url, method, data) {
        console.log('Making AJAX request:', { url, method, data });
        return $.ajax({
            url: url,
            method: method,
            data: data,
            headers: { 'X-CSRF-Token': csrfToken },
            dataType: 'json'
        }).fail(function(xhr) {
            console.error('AJAX error:', {
                status: xhr.status,
                statusText: xhr.statusText,
                response: xhr.responseJSON || xhr.responseText
            });
            showError(xhr.responseJSON?.message || 'An unexpected error occurred. Please try again.');
        });
    }
    function showError(message) {
        $('#logDetails').html(`<div class="p-4 text-center text-sm text-red-600">${message}</div>`);
        $('#logDetails_info').text('Showing 0 to 0 of 0 entries');
        $('#logDetails_paginate').empty();
    }
    function loadSubmissionDetails(page = 1) {
        if (isLoading) {
            console.warn('Previous request still in progress, skipping...');
            return;
        }
        isLoading = true;
        console.log('loadSubmissionDetails called with page:', page);
        if (!userId || !submissionId) {
            console.warn('Invalid parameters:', { userId, submissionId });
            showError('Invalid user or submission ID');
            isLoading = false;
            return;
        }
        currentPage = Math.max(1, page);
        makeAjaxRequest('api/api.php?action=view_logs', 'GET', {
            user_id: userId,
            submission_id: submissionId,
            page: currentPage,
            limit: itemsPerPage
        }).done(function(response) {
            console.log('API Response:', response);
            const logDetails = $('#logDetails');
            const pagination = $('#logDetails_paginate');
            const info = $('#logDetails_info');
            logDetails.empty();
            pagination.empty();
            if (!response.success || !Array.isArray(response.data) || response.data.length === 0) {
                logDetails.append('<div class="p-8 text-center text-sm text-gray-500 bg-gray-50 rounded-lg">No details available for this submission.</div>');
                info.text('Showing 0 to 0 of 0 entries');
                totalLogs = 0;
                renderPagination(0, 1);
                isLoading = false;
                return;
            }
            totalLogs = response.totalLogs || 0;
            const totalPages = Math.max(1, Math.ceil(totalLogs / itemsPerPage));
            if (currentPage > totalPages) {
                currentPage = totalPages;
                loadSubmissionDetails(currentPage);
                isLoading = false;
                return;
            }
            const startEntry = totalLogs === 0 ? 0 : (currentPage - 1) * itemsPerPage + 1;
            const endEntry = Math.min(currentPage * itemsPerPage, totalLogs);
            info.text(`Showing ${startEntry} to ${endEntry} of ${totalLogs} entries`);
            $.each(response.data, function(index, log) {
                const escapedQuestion = $('<div>').text(log.question_text || 'Unknown Question').html();
                let answerHtml = '';
                
                let filePath = log.file_path || (log.answer_text && log.answer_text.includes('public/Uploads/') ? log.answer_text : null);
                
                if ((log.question_type === 'upload_image' || log.question_type === 'file') && filePath && filePath.match(/\.(jpeg|jpg|gif|png|webp|bmp)$/i)) {
                    const imageUrl = filePath;
                    answerHtml = `
                        <div class="mt-2 rounded-lg overflow-hidden border border-gray-100 shadow-sm inline-block">
                            <img src="${imageUrl}" alt="${$('<div>').text(log.file_name || 'Image').html()}"
                                 class="object-contain max-w-full md:max-w-[400px] h-auto"
                            />
                        </div>
                    `;
                } else if ((log.question_type === 'upload_file' || log.question_type === 'file') && filePath) {
                    const fileUrl = filePath;
                    const fileName = log.file_name || 'Document';
                    answerHtml = `
                        <a href="${fileUrl}" target="_blank" class="inline-flex items-center px-4 py-2 bg-emerald-50 text-emerald-700 rounded-lg border border-emerald-100 hover:bg-emerald-100 transition-colors font-semibold text-sm mt-1">
                            <i class="fas fa-file-download mr-2"></i> View / Download Document
                        </a>
                    `;
                } else if (log.answer_text) {
                    // For comma separated values (like checkbox/multi-dropdown), render as badges
                    if (['checkbox', 'multidropdown', 'textbox_list'].includes(log.question_type)) {
                        const values = log.answer_text.split(',');
                        answerHtml = `<div class="flex flex-wrap gap-2 mt-1">`;
                        values.forEach(val => {
                           if (val.trim()) {
                               answerHtml += `<span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-md text-xs font-bold border border-gray-200">${$('<div>').text(val.trim()).html()}</span>`;
                           }
                        });
                        answerHtml += `</div>`;
                    } else {
                        answerHtml = `<p class="leading-relaxed text-gray-700 font-medium whitespace-pre-wrap">${$('<div>').text(log.answer_text).html()}</p>`;
                    }
                } else {
                    answerHtml = `<span class="text-gray-400 italic">No answer provided</span>`;
                }

                logDetails.append(`
                    <div class="bg-white border border-gray-100 rounded-xl p-5 md:p-6 mb-6 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-start gap-4">
                            <div class="hidden md:flex h-10 w-10 shrink-0 rounded-full bg-emerald-50 text-emerald-500 items-center justify-center font-bold text-sm">
                                ${index + 1 + (currentPage -1) * itemsPerPage}
                            </div>
                            <div class="flex-1">
                                <h4 class="text-[13px] uppercase tracking-wider text-gray-400 font-extrabold mb-3">Question</h4>
                                <h3 class="text-[16px] font-bold text-gray-800 leading-snug mb-5">${escapedQuestion}</h3>
                                
                                <div class="mt-4 pt-4 border-t border-gray-100">
                                    <h4 class="text-[11px] uppercase tracking-wider text-gray-400 font-extrabold mb-2">Answer</h4>
                                    <div class="text-[15px]">
                                        ${answerHtml}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `);
            });
            renderPagination(totalLogs, currentPage);
            isLoading = false;
        }).fail(function() {
            isLoading = false;
        });
    }
    function renderPagination(totalItems, currentPage) {
        const pagination = $('#logDetails_paginate');
        const totalPages = Math.max(1, Math.ceil(totalItems / itemsPerPage));
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
                loadSubmissionDetails(currentPage);
                // Scroll to top of card for better UX when paginating long list
                $('html, body').animate({
                    scrollTop: $(".adaptable-card").offset().top - 100
                }, 500);
            }
        });
    }
    loadSubmissionDetails(currentPage);
});
</script>
</body>
</html>