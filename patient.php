<?php
require_once './middleware/auth_check.php';

// Enable error logging instead of displaying errors to prevent output
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/home4/ukblin1/stage.isuremedia.com/clients/esa/error.log'); // Adjust path as needed
error_reporting(E_ALL);

// Check if user is logged in and has 'admin' role
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    $_SESSION['message'] = "Access denied. Admin privileges required.";
    $_SESSION['message_type'] = "error";
    header("Location: ./signin.php");
    exit;
}

require_once './config/database.php';
require_once './controllers/PatientController.php';

// Initialize controller
$controller = new PatientController($pdo);

// Get current page, search query and status filter from query string
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$perPage = 10; // Number of patients per page
$data = $controller->getPatients($page, $perPage, $search, $statusFilter);
$patients = $data['patients'];
$totalPatients = $data['totalPatients'];
$totalPages = $data['totalPages'];
$pagePatients = $data['currentPage'];

// Handle delete action
if (isset($_POST['delete_patient']) && isset($_POST['patient_id'])) {
    $result = $controller->deletePatient($_POST['patient_id']);
    if (isset($result['success'])) {
        $_SESSION['message'] = $result['success'];
        $_SESSION['message_type'] = "success";
    } elseif (isset($result['errors'])) {
        $_SESSION['message'] = implode(', ', $result['errors']);
        $_SESSION['message_type'] = "error";
    }
    header("Location: patient.php?page=$pagePatients&search=" . urlencode($search));
    exit;
}

// Handle activate action
if (isset($_POST['activate_patient']) && isset($_POST['patient_id'])) {
    $result = $controller->activatePatient($_POST['patient_id']);
    if (isset($result['success'])) {
        $_SESSION['message'] = $result['success'];
        $_SESSION['message_type'] = "success";
    } elseif (isset($result['errors'])) {
        $_SESSION['message'] = implode(', ', $result['errors']);
        $_SESSION['message_type'] = "error";
    }
    header("Location: patient.php?page=$pagePatients&search=" . urlencode($search));
    exit;
}

// Set page title and include output-generating files after all redirects
$page_title = 'Patient';
include './views/layouts/head.php';
include './views/layouts/alert.php';
?>
<style>
.custom-pagination-container {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
}

.custom-page-btn {
    min-width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    border: none;
    background: transparent;
    color: #66b19c;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.2s ease;
    cursor: pointer;
}

.custom-page-btn:hover:not(.active):not(:disabled) {
    background: rgba(102, 177, 156, 0.1);
}

.custom-page-btn.active {
    background: #66b19c;
    color: white !important;
}

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
.custom-page-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
    color: #cbd5e1;
}

.custom-pagination-container span {
    color: #66b19c;
    font-weight: 600;
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

@media screen and (max-width:768px){
    .f-mb {
        flex-direction: column;
        gap:15px;
    }
    .dataTables_header_end.flex.items-center {
        flex-direction: column;
        gap:15px;
    }
}
</style>
<div id="root">
    <!-- App Layout -->
    <div class="app-layout-modern flex flex-auto flex-col">
        <div class="flex flex-auto min-w-0">
            <!-- Side Nav start -->
            <?php include './views/layouts/sidebar.php'; ?>
            <!-- Side Nav end -->

            <!-- Header Nav start -->
            <div class="flex flex-col flex-auto min-h-screen min-w-0 relative w-full bg-white border-l border-gray-200 dark:border-gray-700">
                <?php include './views/layouts/header.php'; ?>
                <div class="h-full flex flex-auto flex-col justify-between">
                    <!-- Content start -->
                    <main class="h-full">
                        <div class="page-container relative h-full flex flex-auto flex-col px-4 sm:px-6 md:px-8 py-4 sm:py-6">
                            <div class="container mx-auto">
                                <div class="mb-4">
                                <div class="mb-10 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-6 border-b pb-6">
                                    <h3 class="text-2xl font-bold text-gray-800">All Patients</h3>
                                    <div class="flex items-center gap-4">
                                        <form action="patient.php" method="GET" class="flex items-center gap-3">
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
                                                    <input type="search" name="search" class="w-56 pl-10 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none transition-all" placeholder="Name, email, phone..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" oninput="this.form.submit()">
                                                </div>
                                            </div>
                                            <input type="hidden" name="page" value="1">
                                        </form>
                                        <div class="flex items-center gap-2">
                                            <a href="export_patients.php" class="btn btn-outline btn-sm">
                                                <i class="fas fa-file-excel mr-1.5 opacity-70"></i>Export
                                            </a>
                                            <a href="new-registration.php?role=patient" class="btn btn-solid btn-sm">Add New Patient</a>
                                        </div>
                                    </div>
                                </div>
                                    <?= displayAlert() ?>
                                </div>

                                <div class="bg-white shadow-sm rounded-xl border border-gray-200 table-container overflow-x-auto">
                                    <table id="patients-data-table" class="w-full text-left">
                                        <thead>
                                            <tr class="bg-gray-50/50 border-b border-gray-200">
                                                <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-[11px]">Patient Name</th>
                                                <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-[11px]">Contact Information</th>
                                                <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-[11px]">Status</th>
                                                <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-[11px] text-left">Action</th>
                                            </tr>
                                        </thead>
                                                <tbody>
                                                    <?php if (empty($patients)): ?>
                                                        <tr><td colspan="4" class="text-center p-12 text-gray-400 font-medium">No patients found matching your search.</td></tr>
                                                    <?php else: ?>
                                                        <?php foreach ($patients as $patient): ?>
                                                            <tr class="border-b border-gray-100 hover:bg-gray-50/80 transition-all duration-200 group <?php echo $patient['status'] === 'active' ? 'cursor-pointer' : 'cursor-not-allowed opacity-75'; ?>" 
                                                                data-patient-id="<?= $patient['id'] ?>">
                                                                <td class="px-6 py-4">
                                                                    <div class="flex items-center">
                                                                        <?= renderAvatar($patient['profile_url'], $patient['username'], 'w-9 h-9', 'text-sm') ?>
                                                                        <div class="ml-3 px-2">
                                                                            <span class="text-gray-900 font-bold block leading-tight">
                                                                                <?= htmlspecialchars($patient['username']) ?>
                                                                            </span>
                                                                            <span class="text-gray-400 text-[11px] uppercase font-bold tracking-tight">ID: #<?= $patient['id'] ?></span>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <td class="px-6 py-4 font-medium text-gray-600">
                                                                    <div class="flex flex-col">
                                                                        <span class="text-[13px]"><?= htmlspecialchars($patient['email']) ?></span>
                                                                        <span class="text-[11px] text-gray-400 font-bold uppercase tracking-wider"><?= htmlspecialchars($patient['phone'] ?? 'No contact') ?></span>
                                                                    </div>
                                                                </td>
                                                                <td class="px-6 py-4">
                                                                    <div class="flex items-center">
                                                                        <span class="w-2 h-2 rounded-full <?php echo $patient['status'] === 'active' ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.4)]' : 'bg-rose-500 shadow-[0_0_8px_rgba(244,63,94,0.4)]'; ?>"></span>
                                                                        <span class="ml-2.5 text-[13px] font-semibold tracking-tight <?php echo $patient['status'] === 'active' ? 'text-gray-700' : 'text-gray-400'; ?>">
                                                                            <?php echo htmlspecialchars($patient['status'] === 'active' ? 'Active' : 'Inactive', ENT_QUOTES, 'UTF-8'); ?>
                                                                        </span>
                                                                    </div>
                                                                </td>
                                                                <td class="px-6 py-4 text-right">
                                                                    <div class="flex items-center space-x-2 justify-end opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                                                         <?php if ($patient['status'] === 'active'): ?>
                                                                             <a href="edit_patient.php?id=<?= $patient['id'] ?>&action=edit" class="action-btn action-btn-blue" onclick="event.stopPropagation();" title="Edit Patient">
                                                                                 <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                                                 </svg>
                                                                             </a>
                                                                             <button type="button" class="action-btn action-btn-red" data-bs-toggle="modal" data-bs-target="#deletepatient" data-entity-id="<?= $patient['id'] ?>" data-entity-text="<?= htmlspecialchars($patient['username']) ?>" data-entity-status="<?= $patient['status'] ?>" onclick="event.stopPropagation();" title="Delete Patient">
                                                                                 <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4m-4 4v12m4-12v12" />
                                                                                 </svg>
                                                                             </button>
                                                                             <a href="logs.php?user_id=<?= $patient['id'] ?>" class="action-btn action-btn-gray" onclick="event.stopPropagation();" aria-label="View log" title="View Logs">
                                                                                 <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V8a2 2 0 00-2-2h-4m-2-2v2m0 0H6m4 0h4" />
                                                                                 </svg>
                                                                             </a>
                                                                             <a href="patient_question_details.php?user_id=<?= $patient['id'] ?>" class="action-btn action-btn-blue" onclick="event.stopPropagation();" title="Patient Questions">
                                                                                 <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                                                                 </svg>
                                                                             </a>
                                                                              <a href="patient_documents.php?user_id=<?= $patient['id'] ?>" class="action-btn action-btn-green" onclick="event.stopPropagation();" title="Documents">
                                                                                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                                                                  </svg>
                                                                              </a>
                                                                        <?php elseif ($patient['status'] === 'inactive'): ?>
                                                                             <span class="action-btn action-btn-disabled" title="Edit disabled for inactive patients">
                                                                                 <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                                                 </svg>
                                                                             </span>
                                                                             <button type="button" class="action-btn action-btn-green" data-bs-toggle="modal" data-bs-target="#activatepatient" data-entity-id="<?= $patient['id'] ?>" data-entity-text="<?= htmlspecialchars($patient['username']) ?>" data-entity-status="<?= $patient['status'] ?>" onclick="event.stopPropagation();" title="Activate Patient">
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
                            <div class="px-6 py-4 mt-10 flex flex-col md:flex-row justify-between items-center gap-4">
                                <div class="text-[13px] font-medium text-gray-400">
                                    Showing <span class="text-gray-700 font-bold"><?= count($patients) ?></span> of <span class="text-gray-700 font-bold"><?= $totalPatients ?></span> patients
                                </div>
                                <div class="pagination-container">
                                    <!-- Previous Button -->
                                    <a href="?page=<?php echo $pagePatients > 1 ? $pagePatients - 1 : 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>" 
                                       class="pagination-arrow <?php echo $pagePatients <= 1 ? 'disabled' : ''; ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>

                                    <?php
                                    $max_visible = 5;
                                    $start = max(1, $pagePatients - 2);
                                    $end = min($totalPages, $start + 4);
                                    if ($end - $start < 4) $start = max(1, $end - 4);

                                    if ($start > 1): ?>
                                        <a href="?page=1&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>" class="pagination-btn inactive">1</a>
                                        <?php if ($start > 2): ?><span class="text-gray-300">...</span><?php endif; ?>
                                    <?php endif;

                                    for ($i = $start; $i <= $end; $i++): ?>
                                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>" 
                                           class="pagination-btn <?php echo $i == $pagePatients ? 'active' : 'inactive'; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor;

                                    if ($end < $totalPages): ?>
                                        <?php if ($end < $totalPages - 1): ?><span class="text-gray-300">...</span><?php endif; ?>
                                        <a href="?page=<?php echo $totalPages; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>" class="pagination-btn inactive"><?php echo $totalPages; ?></a>
                                    <?php endif; ?>

                                    <!-- Next Button -->
                                    <a href="?page=<?php echo $pagePatients < $totalPages ? $pagePatients + 1 : $totalPages; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>" 
                                       class="pagination-arrow <?php echo $pagePatients >= $totalPages ? 'disabled' : ''; ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </div>
                            </div>
                            </div>
                        </div>
                    </main>
                    <?php
                    // Include modal with patient-specific parameters
                    $entityName = 'patient';
                    $actionUrl = 'patient.php?page=' . $pagePatients . '&search=' . urlencode($search);
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

                        document.querySelectorAll('tr.cursor-pointer').forEach(row => {
                            row.addEventListener('click', function(event) {
                                if (event.target.closest('a') || event.target.closest('span')) {
                                    return;
                                }
                                const patientId = this.getAttribute('data-patient-id');
                                if (patientId) {
                                    window.location.href = `patient_question_details.php?user_id=${patientId}`;
                                }
                            });
                        });
                    </script>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>