<?php
require_once './middleware/auth_check.php';

// Restrict access to admin only
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    $_SESSION['message'] = "Sorry, only admins can access this page. Please log in.";
    $_SESSION['message_type'] = "error";
    header("Location: ./signin.php");
    exit();
}

$page_title = 'Dashboard';
include './views/layouts/head.php';
require_once './config/database.php';
require_once './controllers/DashboardController.php';
require_once './controllers/QuestionController.php';
require_once './controllers/StaffController.php';
require_once './controllers/PatientController.php';
include './views/layouts/alert.php';

$controller = new DashboardController($pdo);
$questioncontroller = new QuestionController($pdo);
$staffcontroller = new StaffController($pdo);
$patientcontroller = new PatientController($pdo);
$user_id = $_SESSION['user']['id'];
$role = $_SESSION['user']['role'];

// Fetch question types from database
$questionTypes = $questioncontroller->getQuestionTypes();
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

// Pagination setup
$perPage = 10;
$pagePatients = isset($_GET['page_patients']) ? max(1, (int)$_GET['page_patients']) : 1;
$pageStaff = isset($_GET['page_staff']) ? max(1, (int)$_GET['page_staff']) : 1;
$pageQuestions = isset($_GET['page_questions']) ? max(1, (int)$_GET['page_questions']) : 1;
$offsetPatients = ($pagePatients - 1) * $perPage;
$offsetStaff = ($pageStaff - 1) * $perPage;
$offsetQuestions = ($pageQuestions - 1) * $perPage;

// Fetch data using correct controllers
$totalPatients = count($controller->getPatients(['role' => 'patient']));
$patients = $controller->getPatientsPaginated(['role' => 'patient'], $offsetPatients, $perPage);
$totalPagesPatients = ceil($totalPatients / $perPage);

$totalStaff = count($controller->getPatients(['role' => 'staff']));
$staff = $controller->getPatientsPaginated(['role' => 'staff'], $offsetStaff, $perPage);
$totalPagesStaff = ceil($totalStaff / $perPage);

$questionsData = $questioncontroller->getAllQuestions($pageQuestions, $perPage);
$questions = $questionsData['questions'];
$totalQuestions = $questionsData['total'];
$totalPagesQuestions = ceil($totalQuestions / $perPage);

// Generate CSRF token for form security
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Invalid CSRF token.");
        }

        // Initialize redirect URL
        $redirectUrl = "dashboard.php?page_patients=" . urlencode($pagePatients ?? 1) . "&page_staff=" . urlencode($pageStaff ?? 1) . "&page_questions=" . urlencode($pageQuestions ?? 1) . "&search=" . urlencode($search ?? '');

        // Handle create question
        if (isset($_POST['create_question'])) {
            $data = [
                'question_text' => trim($_POST['question_text'] ?? ''),
                'question_type' => trim($_POST['question_type'] ?? ''),
                'is_mandatory' => isset($_POST['is_mandatory']) ? 1 : 0,
                'options' => isset($_POST['options']) && is_array($_POST['options']) ? array_map('trim', $_POST['options']) : []
            ];
            $result = $questioncontroller->createQuestion($data);
            $_SESSION['message'] = isset($result['success']) ? $result['success'] : implode(', ', $result['errors']);
            $_SESSION['message_type'] = isset($result['success']) ? "success" : "error";
        }

        // Handle patient assignment
        if (isset($_POST['assign_patient']) && isset($_POST['staff_id']) && isset($_POST['patient_id'])) {
            $result = $staffcontroller->assignPatientToStaff((int)$_POST['staff_id'], (int)$_POST['patient_id']);
            $_SESSION['message'] = isset($result['success']) ? $result['success'] : implode(', ', $result['errors']);
            $_SESSION['message_type'] = isset($result['success']) ? "success" : "error";
            $redirectUrl = "dashboard.php?page_patients=" . urlencode($pagePatients ?? 1) . "&page_staff=" . urlencode($pageStaff ?? 1) . "&page_questions=" . urlencode($pageQuestions ?? 1) . "&search=" . urlencode($search ?? '');
        }

        // Regenerate CSRF token after successful submission
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        // Perform the redirect
        header("Location: $redirectUrl");
        exit;
    } catch (Exception $e) {
        error_log("Form processing error: " . $e->getMessage());
        $_SESSION['message'] = "An error occurred: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
        header("Location: dashboard.php?page_patients=" . urlencode($pagePatients ?? 1) . "&page_staff=" . urlencode($pageStaff ?? 1) . "&page_questions=" . urlencode($pageQuestions ?? 1) . "&search=" . urlencode($search ?? ''));
        exit;
    }
}

function renderPagination($currentPage, $totalPages, $type) {
    $maxPagesToShow = 5;
    $startPage = max(1, $currentPage - floor($maxPagesToShow / 2));
    $endPage = min($totalPages, $startPage + $maxPagesToShow - 1);
    if ($endPage - $startPage < $maxPagesToShow - 1) {
        $startPage = max(1, $endPage - $maxPagesToShow + 1);
    }
    ?>
    <div class="pagination_n mt-2" id="<?php echo $type; ?>-pagination">
        <div class="pagination-total">Total <span><?php echo $GLOBALS["total" . ucfirst($type)]; ?></span> Items</div>
        <div class="flex items-center space-x-2">
            <span class="pagination-pager pagination-pager-prev<?php echo $currentPage <= 1 ? ' pagination-pager-disabled' : ''; ?>">
                <a href="?page_<?php echo $type; ?>=<?php echo $currentPage > 1 ? $currentPage - 1 : 1; ?>" class="pagination-link" data-type="<?php echo $type; ?>" data-page="<?php echo $currentPage > 1 ? $currentPage - 1 : 1; ?>">
                    <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 20 20" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                    </svg>
                </a>
            </span>
            <ul class="flex space-x-1">
                <?php
                for ($i = $startPage; $i <= $endPage; $i++) {
                    echo '<li class="pagination-pager' . ($currentPage == $i ? ' active' : '') . '">';
                    echo '<a href="?page_' . $type . '=' . $i . '" class="pagination-link" data-type="' . $type . '" data-page="' . $i . '">' . $i . '</a>';
                    echo '</li>';
                }
                if ($endPage < $totalPages) {
                    echo '<li class="pagination-pager"><span>...</span></li>';
                    echo '<li class="pagination-pager"><a href="?page_' . $type . '=' . $totalPages . '" class="pagination-link" data-type="' . $type . '" data-page="' . $totalPages . '">' . $totalPages . '</a></li>';
                }
                ?>
            </ul>
            <span class="pagination-pager pagination-pager-next<?php echo $currentPage >= $totalPages ? ' pagination-pager-disabled' : ''; ?>">
                <a href="?page_<?php echo $type; ?>=<?php echo $currentPage < $totalPages ? $currentPage + 1 : $totalPages; ?>" class="pagination-link" data-type="<?php echo $type; ?>" data-page="<?php echo $currentPage < $totalPages ? $currentPage + 1 : $totalPages; ?>">
                    <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 20 20" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                    </svg>
                </a>
            </span>
        </div>
    </div>
    <?php
}

?>

<style>
.card-layout-frame { border-radius: 8px; }
.table-container { min-height: 400px; max-height: 400px; -webkit-overflow-scrolling: touch; }
@media (max-width: 640px) { .table-default th, .table-default td { font-size: 0.875rem; padding: 0.5rem; } }
.table-default { width: 100%; }
.table-default tr { cursor: pointer; }
.table-default tr td:last-child { cursor: default; }
.error-message { color: #ef4444; margin-bottom: 1rem; font-size: 13px; }
</style>

<div id="root">
    <div class="app-layout-modern flex flex-auto flex-col">
        <div class="flex flex-auto min-w-0">
            <?php include './views/layouts/sidebar.php'; ?>
            <div class="flex flex-col flex-auto min-h-screen min-w-0 relative w-full bg-white border-l border-gray-200 dark:border-gray-700">
                <?php include './views/layouts/header.php'; ?>
                <?php include './views/layouts/themeSetting.php'; ?>
                <div class="h-full flex flex-auto flex-col justify-between">
                    <main class="h-full">
                        <div class="page-container relative h-full flex flex-auto flex-col px-4 sm:px-6 md:px-8 py-4 sm:py-6">
                            <div class="flex flex-col gap-4">
                                <div>
                                    <h4 class="mb-1">Hello, <?php echo htmlspecialchars($_SESSION['user']['username']); ?>!</h4>
                                </div>
                                <?php echo displayAlert(); ?>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <!-- Total Patients Card -->
                                    <a href="patient.php" class="card card-layout-frame hover:shadow-md transition-all duration-200 group">
                                        <div class="card-body">
                                            <div class="flex items-center gap-4">
                                                <span class="avatar avatar-rounded bg-indigo-50 text-indigo-600 group-hover:bg-indigo-100 transition-colors" style="width:48px;height:48px;min-width:48px;display:flex;align-items:center;justify-content:center;border-radius:50%; overflow:hidden;">
                                                     <span class="avatar-icon text-2xl">
                                                         <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
  <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
</svg>
                                                     </span>
                                                 </span>
                                                <div>
                                                    <div class="flex gap-1.5 items-end mb-2">
                                                        <h3 class="font-bold leading-none"><?php echo htmlspecialchars($totalPatients); ?></h3>
                                                        <p class="font-semibold">Total Patients</p>
                                                    </div>
                                                    <p class="flex items-center gap-1"><span class="text-indigo-600 font-medium text-xs">View all patients →</span></p>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                    <!-- Total Questions Card -->
                                    <a href="question.php" class="card card-layout-frame hover:shadow-md transition-all duration-200 group">
                                        <div class="card-body">
                                            <div class="flex items-center gap-4">
                                                <span class="avatar avatar-rounded bg-emerald-50 text-emerald-600 group-hover:bg-emerald-100 transition-colors" style="width:48px;height:48px;min-width:48px;display:flex;align-items:center;justify-content:center;border-radius:50%; overflow:hidden;">
                                                     <span class="avatar-icon text-2xl">
                                                         <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
  <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
</svg>
                                                     </span>
                                                 </span>
                                                <div>
                                                    <div class="flex gap-1.5 items-end mb-2">
                                                        <h3 class="font-bold leading-none"><?php echo htmlspecialchars($totalQuestions); ?></h3>
                                                        <p class="font-semibold">Total Questions</p>
                                                    </div>
                                                    <p class="flex items-center gap-1"><span class="text-emerald-600 font-medium text-xs">Manage questions →</span></p>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                    <!-- Total Staff Card -->
                                    <a href="staff.php" class="card card-layout-frame hover:shadow-md transition-all duration-200 group">
                                        <div class="card-body">
                                            <div class="flex items-center gap-4">
                                                <span class="avatar avatar-rounded bg-cyan-50 text-cyan-600 group-hover:bg-cyan-100 transition-colors" style="width:48px;height:48px;min-width:48px;display:flex;align-items:center;justify-content:center;border-radius:50%; overflow:hidden;">
                                                     <span class="avatar-icon text-2xl">
                                                         <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
  <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
</svg>
                                                     </span>
                                                 </span>
                                                <div>
                                                    <div class="flex gap-1.5 items-end mb-2">
                                                        <h3 class="font-bold leading-none"><?php echo htmlspecialchars($totalStaff); ?></h3>
                                                        <p class="font-semibold">Total Staff</p>
                                                    </div>
                                                    <p class="flex items-center gap-1"><span class="text-cyan-600 font-medium text-xs">Staff directory →</span></p>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                    <!-- Patients Table -->
                                    <div class="card card-layout-frame">
                                        <div class="card-body">
                                            <div class="flex justify-between items-center mb-4">
                                                <h4>All Patients</h4>
                                                <a type="button" href="new-registration.php?role=patient" class="btn btn-solid btn-sm">Add New Patient</a>
                                            </div>
                                            <div class="table-container table-responsive">
                                                <table class="table-default table-hover" id="patients-table">
                                                    <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Status</th></tr></thead>
                                                    <tbody>
                                                        <?php if (empty($patients)): ?>
                                                            <tr><td colspan="4" class="text-center p-4">No patients found.</td></tr>
                                                        <?php else: ?>
                                                            <?php foreach ($patients as $patient): ?>
                                                                <tr class="<?php echo $patient['status'] === 'active' ? 'cursor-pointer hover:bg-gray-100' : 'cursor-not-allowed'; ?>" 
                                                                    <?php echo $patient['status'] === 'active' ? 'onclick="navigateToPatient(' . htmlspecialchars($patient['id']) . ')"' : ''; ?>>
                                                                    <td class="p-3 truncate">
                                                                        <div class="flex items-center">
                                                                             <?= renderAvatar($patient['profile_url'], $patient['username'], 'w-[28px]', 'text-xs') ?>
                                                                            <span class="ml-2 rtl:mr-2 font-semibold">
                                                                                <?php if ($patient['status'] === 'active'): ?>
                                                                                    <a class="hover:text-primary-600" href="patient_question_details.php?user_id=<?php echo htmlspecialchars($patient['id']); ?>" onclick="event.stopPropagation();"><?php echo htmlspecialchars($patient['username']); ?></a>
                                                                                <?php else: ?>
                                                                                    <?php echo htmlspecialchars($patient['username']); ?>
                                                                                <?php endif; ?>
                                                                            </span>
                                                                        </div>
                                                                    </td>
                                                                    <td class="p-3 truncate"><?php echo htmlspecialchars($patient['email']); ?></td>
                                                                    <td class="p-3 truncate"><?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?></td>
                                                                    <td class="p-3">
                                                                        <div class="flex items-center">
                                                                            <span class="badge-dot <?php echo $patient['status'] === 'active' ? 'bg-emerald-500' : 'bg-rose-500'; ?>"></span>
                                                                            <span class="ml-2 capitalize text-gray-700 font-medium"><?php echo htmlspecialchars($patient['status']); ?></span>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <?php renderPagination($pagePatients, $totalPagesPatients, 'patients'); ?>
                                        </div>
                                    </div>
                                    <!-- Staff Table -->
                                    <div class="card card-layout-frame">
                                        <div class="card-body">
                                            <div class="flex justify-between items-center mb-4">
                                                <h4>All Staff</h4>
                                                <a type="button" href="new-registration.php?role=staff" class="btn btn-solid btn-sm">Add New Staff</a>
                                            </div>
                                            <div class="table-container table-responsive">
                                                <table class="table-default table-hover" id="staff-table">
                                                    <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Status</th></tr></thead>
                                                    <tbody>
                                                        <?php if (empty($staff)): ?>
                                                            <tr><td colspan="4" class="text-center p-4">No staff found.</td></tr>
                                                        <?php else: ?>
                                                            <?php foreach ($staff as $staffMember): ?>
                                                                <tr class="<?php echo $staffMember['status'] === 'active' ? 'cursor-pointer hover:bg-gray-100' : 'cursor-not-allowed'; ?>">
                                                                    <td class="p-3 truncate">
                                                                        <div class="flex items-center">
                                                                             <?= renderAvatar($staffMember['profile_url'], $staffMember['username'], 'w-[28px]', 'text-xs') ?>
                                                                            <span class="ml-2 rtl:mr-2 font-semibold"><?php echo htmlspecialchars($staffMember['username']); ?></span>
                                                                        </div>
                                                                    </td>
                                                                    <td class="p-3 truncate"><?php echo htmlspecialchars($staffMember['email']); ?></td>
                                                                    <td class="p-3 truncate"><?php echo htmlspecialchars($staffMember['phone'] ?? 'N/A'); ?></td>
                                                                    <td class="p-3">
                                                                        <div class="flex items-center">
                                                                            <span class="badge-dot <?php echo $staffMember['status'] === 'active' ? 'bg-emerald-500' : 'bg-rose-500'; ?>"></span>
                                                                            <span class="ml-2 capitalize text-gray-700 font-medium"><?php echo htmlspecialchars($staffMember['status']); ?></span>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <?php renderPagination($pageStaff, $totalPagesStaff, 'staff'); ?>
                                        </div>
                                    </div>
                                </div>
                                <!-- Questions Table -->
                                <div class="grid grid-cols-1">
                                    <div class="card card-layout-frame">
                                        <div class="card-body">
                                            <div class="flex justify-between items-center mb-4">
                                                <h4>All Questions</h4>
                                                <button type="button" data-bs-toggle="modal" data-bs-target="#add-question" class="btn btn-solid btn-sm">Add New Question</button>
                                            </div>
                                            <div class="table-container table-responsive">
                                                <table class="table-default table-hover" id="questions-table">
                                                    <thead><tr><th>Question</th><th>Type</th><th>Status</th></tr></thead>
                                                    <tbody>
                                                        <?php if (empty($questions)): ?>
                                                            <tr><td colspan="3" class="text-center p-4">No questions found.</td></tr>
                                                        <?php else: ?>
                                                            <?php foreach ($questions as $question): ?>
                                                                <tr class="<?php echo $question['status'] === 'active' ? 'cursor-pointer hover:bg-gray-100' : 'cursor-not-allowed'; ?>" 
                                                                    <?php echo $question['status'] === 'active' ? 'onclick="navigateToQuestion(' . htmlspecialchars($question['question_id']) . ')"' : ''; ?>>
                                                                    <td class="p-3 max-w-[300px] whitespace-normal break-words"><?php echo htmlspecialchars($question['question_text']); ?></td>
                                                                    <td class="p-3"><?php echo htmlspecialchars($typeLabels[$question['question_type']] ?? ucfirst($question['question_type'])); ?></td>
                                                                    <td class="p-3">
                                                                        <div class="flex items-center">
                                                                            <span class="badge-dot <?php echo $question['status'] === 'active' ? 'bg-emerald-500' : 'bg-rose-500'; ?>"></span>
                                                                            <span class="ml-2 capitalize text-gray-700 font-medium"><?php echo htmlspecialchars($question['status']); ?></span>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <?php renderPagination($pageQuestions, $totalPagesQuestions, 'questions'); ?>
                                        </div>
                                    </div>
                                </div>
                                <!-- Add Question Modal -->
                                <div class="modal fade modal-premium" id="add-question" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog dialog max-w-lg w-full">
                                        <div class="dialog-content">
                                            <span class="close-btn absolute z-10" role="button" data-bs-dismiss="modal">
                                                <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 20 20" aria-hidden="true" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                                            </span>
                                            <div class="modal-premium-header">
                                                <h4 class="text-xl font-bold">Add New Question</h4>
                                                <p class="modal-desc">Create a new assessment question</p>
                                            </div>
                                            <form action="dashboard.php" method="POST">
                                                <div class="modal-premium-body">
                                                    <div class="mb-4">
                                                        <label class="block text-sm font-bold text-gray-700 mb-2">Question Text</label>
                                                         <input class="w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none transition-all" type="text" name="question_text" placeholder="e.g., Do you have any allergies?" required>
                                                    </div>
                                                    <div class="mb-4">
                                                        <label class="block text-sm font-bold text-gray-700 mb-2">Question Type</label>
                                                         <select class="w-full border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none transition-all" name="question_type" id="question_type" onchange="toggleOptions()">
                                                            <?php foreach ($questionTypes as $type): ?>
                                                                <option value="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($typeLabels[$type] ?? ucfirst($type), ENT_QUOTES, 'UTF-8'); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="mb-5 flex items-start gap-4">
                                                        <label class="premium-switch flex-shrink-0 mt-0.5">
                                                            <input type="checkbox" name="is_mandatory" value="1" checked>
                                                            <span class="premium-switch-slider"></span>
                                                        </label>
                                                        <div class="flex-1">
                                                            <span class="premium-switch-label block font-bold text-gray-700">Mandatory Question</span>
                                                            <p class="text-[11px] text-gray-500 mt-0.5">Patients must provide an answer to this question.</p>
                                                        </div>
                                                    </div>
                                                    <div id="options_container" style="display:none;" class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                                                        <label class="block text-sm font-bold text-gray-700 mb-2">Options</label>
                                                        <div id="options_list" class="mb-3"></div>
                                                        <div class="flex items-center gap-2">
                                                            <input class="input flex-1 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 outline-none transition-all" style="height: 42px;" type="text" id="option_input" placeholder="Enter option">
                                                            <button type="button" class="btn bg-white border border-gray-300 text-gray-700 rounded-lg px-4 hover:bg-gray-50 font-semibold" style="height: 42px;" onclick="addOption()">Add</button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-premium-footer">
                                                    <input type="hidden" name="create_question" value="1">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                    <button type="button" class="btn-modal btn-modal-gray" data-bs-dismiss="modal">Cancel</button>
                                                    <button class="btn-modal bg-emerald-600 text-white hover:bg-emerald-700" type="submit">Create Question</button>
                                                </div>
                                            </form>
                                        </div>
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
</div>
<script>
// dashboard.js
document.addEventListener('DOMContentLoaded', function () {
    // Flag to track form submission
    let isSubmitting = false;

    // Navigate to patient details
    window.navigateToPatient = function(patientId) {
        window.location.href = 'patient_question_details.php?user_id=' + encodeURIComponent(patientId);
    };

    // Navigate to question details (optional, if implemented)
    window.navigateToQuestion = function(questionId) {
        console.log('Navigate to question:', questionId);
    };

    // Handle pagination clicks
    document.querySelectorAll('.pagination-link').forEach(link => {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            const type = this.getAttribute('data-type');
            const page = this.getAttribute('data-page');
            const url = new URL(window.location.href);
            url.searchParams.set(`page_${type}`, page);
            window.location.href = url.toString();
        });
    });

    // Prevent multiple form submissions
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function (e) {
            if (isSubmitting) {
                e.preventDefault();
                return;
            }
            const questionText = this.querySelector('input[name="question_text"]');
            if (questionText && !questionText.value.trim()) {
                e.preventDefault();
                alert('Question text is required.');
                return;
            }
            isSubmitting = true;
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Submitting...';
            }
        });
    });

    // Options handling for add question modal
    let options = [];
    window.toggleOptions = function () {
        const questionType = document.getElementById('question_type').value;
        const optionsContainer = document.getElementById('options_container');
        optionsContainer.style.display = ['dropdown', 'multidropdown', 'checkbox', 'radio'].includes(questionType) ? 'block' : 'none';
        if (!['dropdown', 'multidropdown', 'checkbox', 'radio'].includes(questionType)) {
            options = [];
            document.getElementById('options_list').innerHTML = '';
        }
    };

    window.addOption = function () {
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
    };

    window.removeOption = function (button, optionText) {
        options = options.filter(opt => opt !== optionText);
        button.parentElement.remove();
    };
});
</script>
</body>
</html>

<?php
ob_end_flush(); // Flush output buffer
?>