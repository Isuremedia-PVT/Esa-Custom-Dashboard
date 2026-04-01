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
require_once './controllers/QuestionController.php';

$controller = new QuestionController($pdo);

// Fetch question types from database
$questionTypes = $controller->getQuestionTypes();
if (isset($questionTypes['errors'])) {
    $_SESSION['message'] = implode(', ', $questionTypes['errors']);
    $_SESSION['message_type'] = "error";
    $questionTypes = [];
}

// Define display labels for question types
$typeLabels = [
    'text' => 'Single Line Field',
    'textarea' => 'Multiline Field',
    'textbox_list' => 'Text Box List',
    'dropdown' => 'Single Dropdown',
    'multidropdown' => 'Multi Dropdown',
    'checkbox' => 'Checkbox',
    'radio' => 'Radiobox',
    'upload_image' => 'Upload Image',
    'upload_file' => 'Upload File'
];

// Get pagination and search parameters
$page = isset($_POST['page']) && is_numeric($_POST['page']) && $_POST['page'] > 0 
    ? (int)$_POST['page'] 
    : (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 
        ? (int)$_GET['page'] 
        : 1);
$search = isset($_POST['search']) ? trim($_POST['search']) : (isset($_GET['search']) ? trim($_GET['search']) : '');
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$perPage = 10;

// Fetch questions for display
$result = $controller->getAllQuestions($page, $perPage, $search, $statusFilter);
if (isset($result['errors'])) {
    $_SESSION['message'] = implode(', ', $result['errors']);
    $_SESSION['message_type'] = "error";
    $questions = [];
    $totalQuestions = 0;
    $currentPage = 1;
} else {
    $questions = $result['questions'];
    $totalQuestions = $result['total'];
    $currentPage = $result['currentPage'];
}
$totalPages = ceil($totalQuestions / $perPage);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Default redirect page is the current page
    $redirectPage = $currentPage;

    if (isset($_POST['create_question'])) {
        $data = [
            'question_text' => $_POST['question_text'],
            'question_type' => $_POST['question_type'],
            'is_mandatory' => isset($_POST['is_mandatory']) ? 1 : 0,
            'options' => isset($_POST['options']) ? array_filter($_POST['options'], 'trim') : []
        ];
        $result = $controller->createQuestion($data);
        if (isset($result['success'])) {
            $_SESSION['message'] = $result['success'];
            $_SESSION['message_type'] = "success";
            // Recalculate pagination to check if total pages changed
            $result = $controller->getAllQuestions($redirectPage, $perPage, $search);
            if (!isset($result['errors'])) {
                $totalQuestions = $result['total'];
                $totalPages = ceil($totalQuestions / $perPage);
                // If current page exceeds total pages, redirect to last page
                $redirectPage = min($redirectPage, $totalPages);
            }
        } elseif (isset($result['errors'])) {
            $_SESSION['message'] = implode(', ', $result['errors']);
            $_SESSION['message_type'] = "error";
        }
        header("Location: question.php?page=$redirectPage&search=" . urlencode($search));
        exit;
    }

    if (isset($_POST['update_question'])) {
        $data = [
            'question_text' => $_POST['question_text'],
            'question_type' => $_POST['question_type'],
            'is_mandatory' => isset($_POST['is_mandatory']) ? 1 : 0,
            'options' => isset($_POST['options']) ? array_filter($_POST['options'], 'trim') : []
        ];
        $result = $controller->updateQuestion($_POST['question_id'], $data);
        if (isset($result['success'])) {
            $_SESSION['message'] = $result['success'];
            $_SESSION['message_type'] = "success";
        } elseif (isset($result['errors'])) {
            $_SESSION['message'] = implode(', ', $result['errors']);
            $_SESSION['message_type'] = "error";
        }
        header("Location: question.php?page=$redirectPage&search=" . urlencode($search));
        exit;
    }

    if (isset($_POST['delete_question'])) {
        $result = $controller->deleteQuestion($_POST['question_id']);
        if (isset($result['success'])) {
            $_SESSION['message'] = $result['success'];
            $_SESSION['message_type'] = "success";
            // Recalculate pagination to check if total pages changed
            $result = $controller->getAllQuestions($redirectPage, $perPage, $search);
            if (!isset($result['errors'])) {
                $totalQuestions = $result['total'];
                $totalPages = ceil($totalQuestions / $perPage);
                // If current page exceeds total pages, redirect to last page
                $redirectPage = min($redirectPage, $totalPages);
            }
        } elseif (isset($result['errors'])) {
            $_SESSION['message'] = implode(', ', $result['errors']);
            $_SESSION['message_type'] = "error";
        }
        header("Location: question.php?page=$redirectPage&search=" . urlencode($search));
        exit;
    }

    if (isset($_POST['activate_question'])) {
        $result = $controller->activateQuestion($_POST['question_id']);
        if (isset($result['success'])) {
            $_SESSION['message'] = $result['success'];
            $_SESSION['message_type'] = "success";
        } elseif (isset($result['errors'])) {
            $_SESSION['message'] = implode(', ', $result['errors']);
            $_SESSION['message_type'] = "error";
        }
        header("Location: question.php?page=$redirectPage&search=" . urlencode($search));
        exit;
    }
}

// Include layout files
$page_title = 'Questions';
include './views/layouts/head.php';
include './views/layouts/alert.php';
?>

<!-- Rest of your HTML and JavaScript remains unchanged -->
<style>
.pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

.pagination-total {
    font-size: 1rem;
    color: #4b5563;
}

.pagination .flex {
    gap: 0.5rem;
}

.pagination-pager a, .pagination-pager span {
    padding: 0.5rem 1rem;
    border-radius: 0.25rem;
    text-decoration: none;
    color: #66b19c;
}

.pagination-pager.active a {
    background-color: #66b19c;
    color: white;
}

.pagination-pager-disabled a {
    color: #66b19c;
    cursor: not-allowed;
}

.pagination-pager:hover a:not(.pagination-pager-disabled a) {
    background-color: #66b19c;
}

.dataTables_header_end {
    display: flex;
    align-items: center;
}

.dataTables_filter {
    display: inline-flex;
    align-items: center;
}

.dataTables_filter label {
    margin-right: 0.5rem;
    white-space: nowrap;
}

.dataTables_filter input[type="search"] {
    margin-right: 0;
}

.btn-solid {
    margin-left: 1rem;
}

@media screen and (max-width:768px) {
    .f-mb {
        flex-direction: column;
        gap: 15px;
    }
    .dataTables_header_end.flex.items-center {
        flex-direction: column;
        gap: 15px;
    }
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
                                <div class="mb-10 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-6 border-b pb-6">
                                    <h3 class="text-2xl font-bold text-gray-800">Questions</h3>
                                    <div class="flex items-center gap-4">
                                        <form action="question.php" method="GET" class="flex items-center gap-3">
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
                                                    <input type="search" name="search" class="w-64 pl-10 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none transition-all" placeholder="Search questions..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" oninput="debounceSearch()">
                                                    <input type="hidden" name="page" value="1">
                                                </div>
                                            </div>
                                        </form>
                                        <button type="button" data-bs-toggle="modal" data-bs-target="#addquestion" class="btn btn-solid btn-sm">Add New Question</button>
                                    </div>
                                </div>
                                <?= displayAlert() ?>
                                <div class="bg-white shadow-sm rounded-xl border border-gray-200 overflow-hidden">
                                    <div class="table-container">
                                        <table id="questions-data-table" class="w-full text-left">
                                            <thead>
                                                <tr class="bg-gray-50/50 border-b border-gray-200">
                                                    <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-[11px]">Question Text</th>
                                                    <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-[11px]">Type</th>
                                                    <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-[11px]">Required</th>
                                                    <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-[11px]">Options</th>
                                                    <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-[11px]">Status</th>
                                                    <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-[11px] text-left">Action</th>
                                                </tr>
                                            </thead>
                                                <tbody>
                                                    <?php if (empty($questions)): ?>
                                                        <tr><td colspan="6" class="text-center p-12 text-gray-400 font-medium">No questions found matching your search.</td></tr>
                                                    <?php else: ?>
                                                        <?php foreach ($questions as $question): ?>
                                                            <tr class="border-b border-gray-100 hover:bg-gray-50/80 transition-all duration-200 group">
                                                                <td class="px-6 py-4">
                                                                    <span class="text-gray-900 font-medium block leading-relaxed max-w-xl">
                                                                        <?php echo htmlspecialchars($question['question_text'], ENT_QUOTES, 'UTF-8'); ?>
                                                                    </span>
                                                                </td>
                                                                 <td class="px-6 py-4">
                                                                    <span class="text-gray-600 text-[13px]">
                                                                        <?php echo htmlspecialchars($typeLabels[$question['question_type']] ?? ucfirst($question['question_type']), ENT_QUOTES, 'UTF-8'); ?>
                                                                    </span>
                                                                </td>
                                                                <td class="px-6 py-4">
                                                                    <?php if ($question['is_mandatory']): ?>
                                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-rose-100 text-rose-800">
                                                                            Required
                                                                        </span>
                                                                    <?php else: ?>
                                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">
                                                                            Optional
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="px-6 py-4">
                                                                    <?php if (!empty($question['options'])): ?>
                                                                        <ul class="space-y-1">
                                                                            <?php foreach ($question['options'] as $option): ?>
                                                                                <li class="flex items-start text-gray-500 text-[12.5px]">
                                                                                    <span class="mr-2 mt-1.5 w-1 h-1 rounded-full bg-gray-400 block"></span>
                                                                                    <span><?php echo htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?></span>
                                                                                </li>
                                                                            <?php endforeach; ?>
                                                                        </ul>
                                                                    <?php else: ?>
                                                                        <span class="text-gray-300">-</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="px-6 py-4">
                                                                    <div class="flex items-center">
                                                                        <span class="w-2 h-2 rounded-full <?php echo $question['status'] === 'active' ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.4)]' : 'bg-rose-500 shadow-[0_0_8px_rgba(244,63,94,0.4)]'; ?>"></span>
                                                                        <span class="ml-2.5 text-[13px] font-semibold tracking-tight <?php echo $question['status'] === 'active' ? 'text-gray-700' : 'text-gray-400'; ?>">
                                                                            <?php echo htmlspecialchars($question['status'] === 'active' ? 'Active' : 'Inactive', ENT_QUOTES, 'UTF-8'); ?>
                                                                        </span>
                                                                    </div>
                                                                </td>
                                                                <td class="px-6 py-4 text-right">
                                                                    <div class="flex items-center space-x-2 justify-end opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                                                        <?php if ($question['status'] === 'active'): ?>
                                                                            <button type="button"
                                                                               class="action-btn action-btn-blue" 
                                                                               data-bs-toggle="modal" 
                                                                               data-bs-target="#editquestion" 
                                                                               data-question-id="<?php echo htmlspecialchars($question['question_id'], ENT_QUOTES, 'UTF-8'); ?>" 
                                                                               data-question-text="<?php echo htmlspecialchars($question['question_text'], ENT_QUOTES, 'UTF-8'); ?>" 
                                                                               data-question-type="<?php echo htmlspecialchars($question['question_type'], ENT_QUOTES, 'UTF-8'); ?>" 
                                                                               data-question-status="<?php echo htmlspecialchars($question['status'], ENT_QUOTES, 'UTF-8'); ?>" 
                                                                                 data-question-mandatory="<?php echo $question['is_mandatory'] ? '1' : '0'; ?>" 
                                                                               data-question-options="<?php echo htmlspecialchars(json_encode($question['options'] ?? []), ENT_QUOTES, 'UTF-8'); ?>" 
                                                                               aria-label="Edit Question">
                                                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                                                </svg>
                                                                            </button>
                                                                            <button type="button"
                                                                               class="action-btn action-btn-red" 
                                                                               data-bs-toggle="modal" 
                                                                               data-bs-target="#deletequestion" 
                                                                               data-question-id="<?php echo htmlspecialchars($question['question_id'], ENT_QUOTES, 'UTF-8'); ?>" 
                                                                               data-question-text="<?php echo htmlspecialchars($question['question_text'], ENT_QUOTES, 'UTF-8'); ?>" 
                                                                               data-question-status="<?php echo htmlspecialchars($question['status'], ENT_QUOTES, 'UTF-8'); ?>" 
                                                                               aria-label="Delete Question">
                                                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4m-4 4v12m4-12v12" />
                                                                                </svg>
                                                                            </button>
                                                                        <?php else: ?>
                                                                            <span class="action-btn action-btn-disabled" title="Edit disabled for inactive questions">
                                                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                                                </svg>
                                                                            </span>
                                                                            <button type="button"
                                                                               class="action-btn action-btn-green" 
                                                                               data-bs-toggle="modal" 
                                                                               data-bs-target="#activatequestion" 
                                                                               data-question-id="<?php echo htmlspecialchars($question['question_id'], ENT_QUOTES, 'UTF-8'); ?>" 
                                                                               data-question-text="<?php echo htmlspecialchars($question['question_text'], ENT_QUOTES, 'UTF-8'); ?>" 
                                                                               data-question-status="<?php echo htmlspecialchars($question['status'], ENT_QUOTES, 'UTF-8'); ?>" 
                                                                               aria-label="Activate Question">
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
                                </div>
                                <!-- Pagination -->
                                <div class="pagination mt-10 flex justify-between items-center" id="questions-pagination">
                                    <div class="pagination-total">Total <span><?php echo $totalQuestions; ?></span> Items</div>
                                    <div class="flex items-center space-x-2">
                                        <span class="pagination-pager pagination-pager-prev<?php echo $currentPage <= 1 ? ' pagination-pager-disabled' : ''; ?>">
                                            <a href="?page=<?php echo $currentPage > 1 ? $currentPage - 1 : 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>" class="pagination-link" data-type="questions" data-page="<?php echo $currentPage > 1 ? $currentPage - 1 : 1; ?>">
                                                <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 20 20" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                </svg>
                                            </a>
                                        </span>
                                        <ul class="flex space-x-1">
                                            <?php
                                            $maxPagesToShow = 5;
                                            $startPage = max(1, $currentPage - floor($maxPagesToShow / 2));
                                            $endPage = min($totalPages, $startPage + $maxPagesToShow - 1);
                                            if ($endPage - $startPage < $maxPagesToShow - 1) {
                                                $startPage = max(1, $endPage - $maxPagesToShow + 1);
                                            }
                                            if ($startPage > 1) {
                                                echo '<li class="pagination-pager"><a href="?page=1&search=' . urlencode($search) . '&status=' . urlencode($statusFilter) . '" class="pagination-link" data-type="questions" data-page="1">1</a></li>';
                                                if ($startPage > 2) {
                                                    echo '<li class="pagination-pager"><span>...</span></li>';
                                                }
                                            }
                                            for ($i = $startPage; $i <= $endPage; $i++) {
                                                echo '<li class="pagination-pager' . ($currentPage == $i ? ' active' : '') . '">';
                                                echo '<a href="?page=' . $i . '&search=' . urlencode($search) . '&status=' . urlencode($statusFilter) . '" class="pagination-link" data-type="questions" data-page="' . $i . '">' . $i . '</a>';
                                                echo '</li>';
                                            }
                                            if ($endPage < $totalPages) {
                                                if ($endPage < $totalPages - 1) {
                                                    echo '<li class="pagination-pager"><span>...</span></li>';
                                                }
                                                echo '<li class="pagination-pager"><a href="?page=' . $totalPages . '&search=' . urlencode($search) . '&status=' . urlencode($statusFilter) . '" class="pagination-link" data-type="questions" data-page="' . $totalPages . '">' . $totalPages . '</a></li>';
                                            }
                                            ?>
                                        </ul>
                                        <span class="pagination-pager pagination-pager-next<?php echo $currentPage >= $totalPages ? ' pagination-pager-disabled' : ''; ?>">
                                            <a href="?page=<?php echo $currentPage < $totalPages ? $currentPage + 1 : $totalPages; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>" class="pagination-link" data-type="questions" data-page="<?php echo $currentPage < $totalPages ? $currentPage + 1 : $totalPages; ?>">
                                                <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 20 20" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                                </svg>
                                            </a>
                                        </span>
                                    </div>
                                </div>
                                <!-- Add Question Modal -->
                                <div class="modal fade modal-premium" id="addquestion" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog dialog max-w-lg w-full">
                                        <div class="dialog-content">
                                            <span class="close-btn absolute z-10" role="button" data-bs-dismiss="modal">
                                                <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 20 20" aria-hidden="true" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                                </svg>
                                            </span>
                                            <div class="modal-premium-header">
                                                <h4 class="text-xl font-bold">Add New Question</h4>
                                                <p class="modal-desc">Create a new assessment question</p>
                                            </div>
                                            <form action="question.php" method="POST" id="add-question-form">
                                                <div class="modal-premium-body">
                                                    <div class="mb-4">
                                                        <label class="block text-sm font-bold text-gray-700 mb-2">Question Text</label>
                                                        <input class="input w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none transition-all" type="text" name="question_text" placeholder="e.g., Do you have any allergies?" required>
                                                    </div>
                                                    <div class="mb-4">
                                                        <label class="block text-sm font-bold text-gray-700 mb-2">Question Type</label>
                                                        <select class="input w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none transition-all" name="question_type" id="question_type" onchange="toggleOptions()">
                                                            <?php foreach ($questionTypes as $type): ?>
                                                                <option value="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>">
                                                                    <?php echo htmlspecialchars($typeLabels[$type] ?? ucfirst($type), ENT_QUOTES, 'UTF-8'); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="mb-5 flex items-start gap-4">
                                                        <label class="premium-switch flex-shrink-0 mt-0.5">
                                                            <input type="checkbox" name="is_mandatory" checked>
                                                            <span class="premium-switch-slider"></span>
                                                        </label>
                                                        <div class="flex-1">
                                                            <span class="premium-switch-label block font-bold text-gray-700">Mandatory Question</span>
                                                            <p class="text-[11px] text-gray-500 mt-0.5">Patients must provide an answer to this question.</p>
                                                        </div>
                                                    </div>
                                                    <div id="options_container" style="display: none;" class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                                                        <label class="block text-sm font-bold text-gray-700 mb-2">Options</label>
                                                        <div id="options_list" class="mb-3"></div>
                                                        <div class="flex items-center gap-2">
                                                            <input class="input flex-1 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none transition-all p-3" type="text" id="option_input" placeholder="Enter option">
                                                            <button type="button" class="btn bg-white border border-gray-300 text-gray-700 rounded-lg px-4 hover:bg-gray-50 font-semibold h-12" onclick="addOption()">Add</button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-premium-footer">
                                                    <input type="hidden" name="create_question" value="1">
                                                    <input type="hidden" name="page" value="<?php echo $currentPage; ?>">
                                                    <button type="button" class="btn-modal btn-modal-gray" data-bs-dismiss="modal">Cancel</button>
                                                    <button class="btn-modal bg-emerald-600 text-white hover:bg-emerald-700" type="submit" id="add-question-submit">Create Question</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Edit Question Modal -->
                                <div class="modal fade modal-premium" id="editquestion" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog dialog max-w-lg w-full">
                                        <div class="dialog-content">
                                            <span class="close-btn absolute z-10" role="button" data-bs-dismiss="modal">
                                                <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 20 20" aria-hidden="true" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                                </svg>
                                            </span>
                                            <div class="modal-premium-header">
                                                <h4 class="text-xl font-bold">Edit Question</h4>
                                                <p class="modal-desc">Update question details and options</p>
                                            </div>
                                            <form action="question.php" method="POST" id="edit-question-form">
                                                <div class="modal-premium-body">
                                                    <div class="mb-4">
                                                        <label class="block text-sm font-bold text-gray-700 mb-2">Question Text</label>
                                                        <input class="input w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none transition-all" type="text" name="question_text" id="edit_question_text" required>
                                                    </div>
                                                    <div class="mb-4">
                                                        <label class="block text-sm font-bold text-gray-700 mb-2">Question Type</label>
                                                        <select class="input w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none transition-all" name="question_type" id="edit_question_type" onchange="toggleEditOptions()">
                                                            <?php foreach ($questionTypes as $type): ?>
                                                                <option value="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>">
                                                                    <?php echo htmlspecialchars($typeLabels[$type] ?? ucfirst($type), ENT_QUOTES, 'UTF-8'); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="mb-5 flex items-start gap-4">
                                                        <label class="premium-switch flex-shrink-0 mt-0.5">
                                                            <input type="checkbox" name="is_mandatory" id="edit_is_mandatory">
                                                            <span class="premium-switch-slider"></span>
                                                        </label>
                                                        <div class="flex-1">
                                                            <span class="premium-switch-label block font-bold text-gray-700">Mandatory Question</span>
                                                            <p class="text-[11px] text-gray-500 mt-0.5">Patients must provide an answer to this question.</p>
                                                        </div>
                                                    </div>
                                                    <div id="edit_options_container" style="display: none;" class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                                                        <label class="block text-sm font-bold text-gray-700 mb-2">Options</label>
                                                        <div id="edit_options_list" class="mb-3"></div>
                                                        <div class="flex items-center gap-2">
                                                            <input class="input flex-1 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none transition-all" style="height: 42px;" type="text" id="edit_option_input" placeholder="Enter option">
                                                            <button type="button" class="btn bg-white border border-gray-300 text-gray-700 rounded-lg px-4 hover:bg-gray-50 font-semibold" style="height: 42px;" onclick="addEditOption()">Add</button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-premium-footer">
                                                    <input type="hidden" name="question_id" id="edit_question_id">
                                                    <input type="hidden" name="update_question" value="1">
                                                    <input type="hidden" name="page" value="<?php echo $currentPage; ?>">
                                                    <button type="button" class="btn-modal btn-modal-gray" data-bs-dismiss="modal">Cancel</button>
                                                    <button class="btn-modal bg-emerald-600 text-white hover:bg-emerald-700" type="submit" id="edit-question-submit">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Delete Question Modal -->
                                <div class="modal fade modal-premium" id="deletequestion" tabindex="-1" aria-hidden="true">
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
                                                <h4 class="text-xl font-bold">Delete Question</h4>
                                                <p class="modal-desc">Are you sure you want to delete this question? This action cannot be undone.</p>
                                                <div class="flex justify-center mt-4">
                                                    <p id="delete_question_text" class="font-bold text-gray-900 text-center"></p>
                                                </div>
                                            </div>
                                            <form action="question.php" method="POST" id="delete-question-form">
                                                <div class="modal-premium-footer">
                                                    <input type="hidden" name="question_id" id="delete_question_id">
                                                    <input type="hidden" name="delete_question" value="1">
                                                    <input type="hidden" name="page" value="<?php echo $currentPage; ?>">
                                                    <button type="button" class="btn-modal btn-modal-gray" data-bs-dismiss="modal">Cancel</button>
                                                    <button class="btn-modal bg-red-600 text-white hover:bg-red-700" type="submit" id="delete-question-submit">Yes, Delete</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Activate Question Modal -->
                                <div class="modal fade modal-premium" id="activatequestion" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog dialog">
                                        <div class="dialog-content">
                                            <span class="close-btn absolute z-10" role="button" data-bs-dismiss="modal">
                                                <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 20 20" aria-hidden="true" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                                </svg>
                                            </span>
                                            <div class="modal-premium-body">
                                                <div class="modal-icon-container modal-icon-container-green">
                                                    <svg stroke="currentColor" fill="none" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                </div>
                                                <h4 class="text-xl font-bold">Activate Question</h4>
                                                <p class="modal-desc">Are you sure you want to activate this question?</p>
                                                <div class="flex justify-center mt-4">
                                                    <p id="activate_question_text" class="font-bold text-gray-900 text-center"></p>
                                                </div>
                                            </div>
                                            <form action="question.php" method="POST" id="activate-question-form">
                                                <div class="modal-premium-footer">
                                                    <input type="hidden" name="question_id" id="activate_question_id">
                                                    <input type="hidden" name="activate_question" value="1">
                                                    <input type="hidden" name="page" value="<?php echo $currentPage; ?>">
                                                    <button type="button" class="btn-modal btn-modal-gray" data-bs-dismiss="modal">Cancel</button>
                                                    <button class="btn-modal bg-green-600 text-white hover:bg-green-700" type="submit" id="activate-question-submit">Activate Now</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </main>
                    <?php
                    $entityName = 'question';
                    $actionUrl = 'question.php?page=' . $currentPage . '&search=' . urlencode($search);
                    include './views/layouts/footer.php';
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let searchTimeout;
function debounceSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const form = document.querySelector('form[action="question.php"]');
        if (form) form.submit();
    }, 500);
}

let options = [];

function toggleOptions() {
    const questionType = document.getElementById('question_type').value;
    const optionsContainer = document.getElementById('options_container');
    optionsContainer.style.display = (['dropdown', 'multidropdown', 'checkbox', 'radio'].includes(questionType)) ? 'block' : 'none';
    if (!['dropdown', 'multidropdown', 'checkbox', 'radio'].includes(questionType)) {
        options = [];
        document.getElementById('options_list').innerHTML = '';
    }
}

function addOption() {
    const optionInput = document.getElementById('option_input').value.trim();
    if (!optionInput) {
        alert('Please enter an option.');
        return;
    }
    if (options.includes(optionInput)) {
        alert('This option already exists.');
        return;
    }
    options.push(optionInput);
    const optionsList = document.getElementById('options_list');
    const optionDiv = document.createElement('div');
    optionDiv.className = 'flex items-center space-x-2 mb-2';
    optionDiv.innerHTML = `
        <input type="hidden" name="options[]" value="${optionInput}">
        <span>${optionInput}</span>
        <button type="button" class="text-red-600 hover:text-red-800" onclick="removeOption(this, '${optionInput}')">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    `;
    optionsList.appendChild(optionDiv);
    document.getElementById('option_input').value = '';
}

function removeOption(button, optionText) {
    options = options.filter(opt => opt !== optionText);
    button.parentElement.remove();
}

let editOptions = [];

function toggleEditOptions() {
    const questionType = document.getElementById('edit_question_type').value;
    const optionsContainer = document.getElementById('edit_options_container');
    optionsContainer.style.display = (['dropdown', 'multidropdown', 'checkbox', 'radio'].includes(questionType)) ? 'block' : 'none';
    if (!['dropdown', 'multidropdown', 'checkbox', 'radio'].includes(questionType)) {
        editOptions = [];
        document.getElementById('edit_options_list').innerHTML = '';
    }
}

function addEditOption() {
    const optionInput = document.getElementById('edit_option_input').value.trim();
    if (!optionInput) {
        alert('Please enter an option.');
        return;
    }
    if (editOptions.includes(optionInput)) {
        alert('This option already exists.');
        return;
    }
    editOptions.push(optionInput);
    const optionsList = document.getElementById('edit_options_list');
    const optionDiv = document.createElement('div');
    optionDiv.className = 'flex items-center space-x-2 mb-2';
    optionDiv.innerHTML = `
        <input type="hidden" name="options[]" value="${optionInput}">
        <span>${optionInput}</span>
        <button type="button" class="text-red-600 hover:text-red-800" onclick="removeEditOption(this, '${optionInput}')">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    `;
    optionsList.appendChild(optionDiv);
    document.getElementById('edit_option_input').value = '';
}

function removeEditOption(button, optionText) {
    editOptions = editOptions.filter(opt => opt !== optionText);
    button.parentElement.remove();
}

document.addEventListener('DOMContentLoaded', function () {
    // Prevent multiple submissions for add question form
    const addQuestionForm = document.getElementById('add-question-form');
    if (addQuestionForm) {
        addQuestionForm.addEventListener('submit', function(event) {
            const submitButton = document.getElementById('add-question-submit');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Submitting...';
            }
        });
    }

    // Prevent multiple submissions for edit question form
    const editQuestionForm = document.getElementById('edit-question-form');
    if (editQuestionForm) {
        editQuestionForm.addEventListener('submit', function(event) {
            const submitButton = document.getElementById('edit-question-submit');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Submitting...';
            }
        });
    }

    // Prevent multiple submissions for delete question form
    const deleteQuestionForm = document.getElementById('delete-question-form');
    if (deleteQuestionForm) {
        deleteQuestionForm.addEventListener('submit', function(event) {
            const submitButton = document.getElementById('delete-question-submit');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Deleting...';
            }
        });
    }

    // Prevent multiple submissions for activate question form
    const activateQuestionForm = document.getElementById('activate-question-form');
    if (activateQuestionForm) {
        activateQuestionForm.addEventListener('submit', function(event) {
            const submitButton = document.getElementById('activate-question-submit');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Activating...';
            }
        });
    }

    // Handle Edit modal
    const editModal = document.getElementById('editquestion');
    editModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const questionId = button.getAttribute('data-question-id');
        const questionText = button.getAttribute('data-question-text');
        const questionType = button.getAttribute('data-question-type');
        const questionOptions = button.getAttribute('data-question-options');
        const questionStatus = button.getAttribute('data-question-status');
        const questionMandatory = button.getAttribute('data-question-mandatory');

        if (questionStatus === 'active') {
            const inputQuestionId = editModal.querySelector('#edit_question_id');
            const inputQuestionText = editModal.querySelector('#edit_question_text');
            const inputQuestionType = editModal.querySelector('#edit_question_type');
            const optionsContainer = editModal.querySelector('#edit_options_container');
            const optionsList = editModal.querySelector('#edit_options_list');

            inputQuestionId.value = questionId;
            inputQuestionText.value = questionText;
            inputQuestionType.value = questionType;
            
            const inputMandatory = editModal.querySelector('#edit_is_mandatory');
            if (inputMandatory) {
                inputMandatory.checked = (questionMandatory === '1');
            }

            optionsContainer.style.display = (['dropdown', 'multidropdown', 'checkbox', 'radio'].includes(questionType)) ? 'block' : 'none';
            optionsList.innerHTML = '';
            editOptions = JSON.parse(questionOptions || '[]');
            editOptions.forEach(option => {
                const optionDiv = document.createElement('div');
                optionDiv.className = 'flex items-center space-x-2 mb-2';
                optionDiv.innerHTML = `
                    <input type="hidden" name="options[]" value="${option}">
                    <span>${option}</span>
                    <button type="button" class="text-red-600 hover:text-red-800" onclick="removeEditOption(this, '${option}')">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                `;
                optionsList.appendChild(optionDiv);
            });
        } else {
            event.preventDefault();
            alert('Cannot edit an inactive question.');
        }
    });

    // Handle Delete modal
    const deleteModal = document.getElementById('deletequestion');
    deleteModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const questionId = button.getAttribute('data-question-id');
        const questionText = button.getAttribute('data-question-text');
        const questionStatus = button.getAttribute('data-question-status');

        if (questionStatus === 'active') {
            const inputQuestionId = deleteModal.querySelector('#delete_question_id');
            const displayQuestionText = deleteModal.querySelector('#delete_question_text');
            inputQuestionId.value = questionId;
            displayQuestionText.textContent = questionText;
        } else {
            event.preventDefault();
            alert('Cannot delete an inactive question.');
        }
    });

    // Handle Activate modal
    const activateModal = document.getElementById('activatequestion');
    activateModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const questionId = button.getAttribute('data-question-id');
        const questionText = button.getAttribute('data-question-text');
        const questionStatus = button.getAttribute('data-question-status');

        if (questionStatus === 'inactive') {
            const inputQuestionId = activateModal.querySelector('#activate_question_id');
            const displayQuestionText = activateModal.querySelector('#activate_question_text');
            inputQuestionId.value = questionId;
            displayQuestionText.textContent = questionText;
        } else {
            event.preventDefault();
            alert('Cannot activate an active question.');
        }
    });
});
</script>
</body>
</html>