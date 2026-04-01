<?php
require_once './middleware/auth_check.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in and role is valid
$valid_roles = ['admin', 'staff', 'patient'];
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role']) || !in_array($_SESSION['user']['role'], $valid_roles)) {
    error_log("Invalid or missing session data: " . var_export($_SESSION['user'] ?? [], true));
    $_SESSION['message'] = "Please log in to access the dashboard.";
    $_SESSION['message_type'] = "error";
    header("Location: ./signin.php");
    exit;
}

// Validate session user ID
if (!isset($_SESSION['user']['id']) || !is_numeric($_SESSION['user']['id']) || (int)$_SESSION['user']['id'] <= 0) {
    error_log("Invalid session user ID: " . var_export($_SESSION['user']['id'] ?? 'undefined', true));
    $_SESSION['message'] = "Session data corrupted. Please log in again.";
    $_SESSION['message_type'] = "error";
    header("Location: ./signin.php");
    exit;
}

// Set role flags
$is_admin = $_SESSION['user']['role'] === 'admin';
$is_staff = $_SESSION['user']['role'] === 'staff';
$is_patient = $_SESSION['user']['role'] === 'patient';

require_once './controllers/PatientController.php';
require_once './views/layouts/alert.php';

// Get user_id: Prioritize GET for admins/staff, use session for patients
$user_id = (int)$_SESSION['user']['id']; // Default to session user_id

if ($is_admin && isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $user_id = (int)$_GET['user_id'];
    error_log("Admin overriding user_id: $user_id");
} elseif ($is_staff && isset($_GET['user_id'])) {
    $requested_patient_id = (int)$_GET['user_id'];
    $controller = new PatientController($pdo);
    if (!$controller->isPatientAssignedToStaff($requested_patient_id, $_SESSION['user']['id'])) {
        error_log("Unauthorized staff access: staff_id=" . $_SESSION['user']['id'] . " to patient_id=" . $requested_patient_id);
        $_SESSION['message'] = "Unauthorized access. This patient is not assigned to you.";
        $_SESSION['message_type'] = "error";
        header("Location: ./dashboard.php");
        exit;
    }
    $user_id = $requested_patient_id;
    error_log("Staff access validated for patient_id: $user_id");
} elseif ($is_patient && isset($_GET['user_id']) && (int)$_GET['user_id'] !== (int)$_SESSION['user']['id']) {
    error_log("Unauthorized patient access: GET user_id=" . ($_GET['user_id'] ?? 'undefined') . ", session_user_id=" . $_SESSION['user']['id']);
    $_SESSION['message'] = "Unauthorized access to another user's data.";
    $_SESSION['message_type'] = "error";
    header("Location: ./dashboard.php");
    exit;
} else {
    error_log("Using default session user_id: $user_id");
}

if (!isset($controller)) {
    $controller = new PatientController($pdo);
}
$note_types = $controller->getNoteTypes();

// Check if target user is active (exclude admins from this check)
if (isset($user_id) && !$is_admin) {
    if (!isset($controller)) {
        $controller = new PatientController($pdo);
    }
    if (!$controller->isUserActive($user_id)) {
        $_SESSION['message'] = "Cannot access details for an inactive patient.";
        $_SESSION['message_type'] = "error";
        header("Location: ./dashboard.php");
        exit;
    }
}

// Log user_id details for debugging
error_log("Processed user_id: $user_id");
error_log("Session user_id: " . $_SESSION['user']['id']);
error_log("User role: " . $_SESSION['user']['role']);

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Page setup
$page_title = 'Patient Questionnaires';
?>


    <?php include './views/layouts/head.php'; ?>
    <style>
        body { background-color: #f9fafb; color: #1f2937; }
        .page-container { padding: 2rem; max-width: 1200px; margin: 0 auto; }
        .card { background: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.06); margin-bottom: 1.5rem; overflow: hidden; }
        .btn-solid { background-color: #66b19c; color: white; padding: 0.5rem 1.5rem; border-radius: 0.375rem; font-weight: 600; transition: background 0.2s; }
        .btn-solid:hover { background-color: #559a86; }
        .btn-outline { border: 1px solid #d1d5db; color: #374151; padding: 0.5rem 1.5rem; border-radius: 0.375rem; font-weight: 600; background: white; }
        .btn-outline:hover { background: #f3f4f6; }
        .input { width: 100%; border: 1px solid #d1d5db; border-radius: 0.375rem; padding: 0.5rem 0.75rem; margin-top: 0.25rem; }
        .form-label { font-weight: 500; font-size: 0.8125rem; color: #475569; }
        
        /* SweetAlert Premium Styling */
        .btn-swal-confirm { background-color: #10b981 !important; color: white !important; padding: 12px 28px !important; border-radius: 12px !important; font-weight: 700 !important; font-size: 14px !important; border: none !important; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25) !important; transition: all 0.2s !important; cursor: pointer !important; margin: 0 8px !important; }
        .btn-swal-confirm:hover { background-color: #059669 !important; transform: translateY(-1px) !important; box-shadow: 0 6px 16px rgba(16, 185, 129, 0.35) !important; }
        .btn-swal-cancel { background-color: #f3f4f6 !important; color: #4b5563 !important; padding: 12px 28px !important; border-radius: 12px !important; font-weight: 700 !important; font-size: 14px !important; border: 1px solid #e5e7eb !important; transition: all 0.2s !important; cursor: pointer !important; margin: 0 8px !important; }
        .btn-swal-cancel:hover { background-color: #e5e7eb !important; color: #1f2937 !important; }
        .swal2-actions { margin-top: 1.5rem !important; }
        .swal2-popup { border-radius: 24px !important; padding: 2rem !important; font-family: inherit !important; }
        .swal2-title { font-weight: 800 !important; color: #1f2937 !important; }
        
        /* Modern Search and Filter Classes */
        .modern-search-wrap { position: relative; width: 100%; display: flex; align-items: center; margin-bottom: 20px; }
        .modern-search-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 10px; background-color: #ecfdf5; color: #059669; z-index: 10; font-size: 13px; pointer-events: none; transition: all 0.2s; }
        .modern-search-wrap:focus-within .modern-search-icon { background-color: #10b981; color: white; }
        .modern-input { width: 100%; border: 1px solid #e2e8f0; border-radius: 12px; height: 52px; padding: 0 16px 0 64px !important; background-color: #ffffff; font-size: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); transition: all 0.2s; outline: none; margin: 0; display: block; }
        .modern-input:focus { border-color: #66b19c; box-shadow: 0 0 0 4px rgba(102, 177, 156, 0.1); }
        .modern-select { appearance: none; -webkit-appearance: none; width: 100%; border: 1px solid #e2e8f0; border-radius: 12px; height: 52px; padding: 0 40px 0 64px !important; background-color: #ffffff; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #4b5563; box-shadow: 0 1px 3px rgba(0,0,0,0.04); transition: all 0.2s; outline: none; cursor: pointer; margin: 0; display: block; line-height: 50px; }
        .modern-select:focus { border-color: #66b19c; box-shadow: 0 0 0 4px rgba(102, 177, 156, 0.1); }
        .modern-search-wrap:hover .modern-select-icon { background-color: #10b981; color: white; }
        .modern-chev { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: #9ca3af; pointer-events: none; transition: all 0.2s; font-size: 14px; }
        .modern-search-wrap:hover .modern-chev { color: #10b981; }
        .question-item { background: white; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: all 0.3s ease; }
        .question-item:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); border-color: #66b19c; }
        .question-label-container { border-bottom: 1px solid #f3f4f6; padding-bottom: 1rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; items-start: flex-start; }
        .remove-question { color: #9ca3af; transition: color 0.2s; padding: 0.5rem; }
        .patient-info-tile { background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 0.875rem; padding: 1rem 1.25rem; display: flex; align-items: center; gap: 0.875rem; transition: all 0.2s; }
        .patient-info-tile:hover { border-color: #d1fae5; background: #f0fdf4; box-shadow: 0 4px 12px rgba(102,177,156,0.08); transform: translateY(-1px); }
        .patient-info-icon { width: 40px; height: 40px; border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .patient-info-icon.teal { background: linear-gradient(135deg, #d1fae5, #a7f3d0); color: #059669; }
        .patient-info-icon.blue { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #2563eb; }
        .patient-info-icon.purple { background: linear-gradient(135deg, #ede9fe, #ddd6fe); color: #7c3aed; }
        .patient-info-icon svg { width: 18px; height: 18px; }
        .patient-info-content { flex: 1; min-width: 0; }
        .patient-info-label { font-size: 0.6rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em; display: block; margin-bottom: 0.25rem; }
        .patient-info-value { font-size: 0.8125rem; font-weight: 600; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; }

        .input-premium { width: 100%; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 0.75rem 1rem; background-color: #f8fafc; transition: all 0.2s; font-size: 0.8125rem; }
        .input-premium:focus { background-color: white; border-color: #66b19c; box-shadow: 0 0 0 4px rgba(102, 177, 156, 0.1); outline: none; }
        .checkbox-item { display: flex; align-items: center; padding: 0.875rem 1.125rem; background: #ffffff; border: 1px solid #f1f5f9; border-radius: 0.875rem; margin-bottom: 0.75rem; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.02); }
        .checkbox-item:hover { background: #f0fdf4; border-color: #bbf7d0; transform: translateY(-1px); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .checkbox-item input[type="checkbox"], .checkbox-item input[type="radio"] { width: 1.125rem; height: 1.125rem; border: 2px solid #e2e8f0; border-radius: 0.375rem; color: #66b19c; margin-right: 0.875rem; transition: all 0.2s; cursor: pointer; flex-shrink: 0; }
        .checkbox-item input[type="radio"] { border-radius: 50%; }
        .checkbox-item input:checked { border-color: #66b19c; background-color: #66b19c; }
        .checkbox-item span { font-size: 0.875rem !important; font-weight: 500 !important; color: #334155 !important; user-select: none; flex: 1; display: block !important; opacity: 1 !important; visibility: visible !important; }
        .checkbox-item input:checked + span { color: #065f46 !important; font-weight: 600 !important; }
        .sticky-footer { position: sticky; bottom: 1.5rem; background: rgba(255, 255, 255, 0.9); border: 1px solid rgba(229, 231, 235, 0.5); padding: 0.875rem 1.5rem; z-index: 100; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1); border-radius: 1.25rem; width: fit-content; margin-left: auto; margin-right: 2rem; backdrop-filter: blur(8px); }
        .question-card { margin-bottom: 1rem; padding: 1.25rem !important; border: 1px solid #e5e7eb; border-radius: 1rem; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); background: white; display: flex; align-items: center; gap: 1rem; }
        .question-card:hover { transform: translateY(-3px); border-color: #66b19c; box-shadow: 0 10px 15px -3px rgba(102, 177, 156, 0.1), 0 4px 6px -4px rgba(102, 177, 156, 0.1); }
        .question-card.assigned { border: 1px solid #66b19c; background: #f0fdf4 !important; box-shadow: 0 4px 6px -1px rgba(102, 177, 156, 0.1); }
        .question-card.assigned .icon-box { background: #66b19c; color: white; box-shadow: 0 4px 6px rgba(102, 177, 156, 0.2); }
        .question-card.assigned p { color: #065f46; font-weight: 700; }
        .icon-box { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: all 0.2s; font-size: 1.1rem; }
        .icon-box-blue { background: #eff6ff; color: #3b82f6; }
        .icon-box-emerald { background: #ecfdf5; color: #10b981; }
        .icon-box-purple { background: #faf5ff; color: #8b5cf6; }
        .icon-box-amber { background: #fffbeb; color: #f59e0b; }
        .icon-box-rose { background: #fff1f2; color: #f43f5e; }
        .icon-box-gray { background: #f9fafb; color: #6b7280; }
        .sidebar { background: #f8fafc; border-left: 1px solid #f1f5f9; width: 400px; overflow-y: auto; padding: 2rem 1.5rem !important; }
        .question-list-container { display: flex; flex-direction: column; gap: 1rem; padding-top: 1.5rem; }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 10px; }

        /* Patient Overview Premium Card */
        .patient-overview-card { background: white; border-radius: 0.75rem; border: 1px solid #e5e7eb; box-shadow: 0 1px 4px rgba(0,0,0,0.06); margin-bottom: 1.5rem; overflow: hidden; }
        .patient-overview-header { background: linear-gradient(135deg, #66b19c 0%, #4a9a84 100%); padding: 1rem 1.5rem; display: flex; align-items: center; gap: 0.75rem; }
        .patient-overview-header i { color: rgba(255,255,255,0.85); font-size: 18px; }
        .patient-overview-header h4 { color: white; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; margin: 0; }
        .patient-overview-body { padding: 1.25rem 1.5rem; }
        .patient-info-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
        @media (max-width: 768px) { .patient-info-grid { grid-template-columns: 1fr; } }

        /* Select2 Premium Overrides */
        .select2-container--default .select2-selection--multiple {
            border: 1px solid #e2e8f0 !important;
            border-radius: 0.625rem !important;
            padding: 4px 10px !important;
            background-color: #f8fafc !important;
            min-height: 44px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: flex-start !important;
            transition: all 0.2s !important;
            box-shadow: none !important;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__rendered {
            display: flex !important;
            flex-wrap: wrap !important;
            gap: 6px !important;
            padding: 0 !important;
            margin: 0 !important;
            list-style: none !important;
            align-items: center !important;
            width: auto !important;
            flex: 1 !important;
        }
        .select2-container--default.select2-container--focus .select2-selection--multiple {
            background-color: white !important;
            border-color: #66b19c !important;
            box-shadow: 0 0 0 4px rgba(102, 177, 156, 0.1) !important;
            outline: none !important;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #f0fdf4 !important;
            border: 1px solid #bbf7d0 !important;
            border-radius: 6px !important;
            padding: 4px 12px 4px 10px !important;
            color: #166534 !important;
            font-size: 13px !important;
            font-weight: 700 !important;
            margin: 2px 0 !important;
            display: flex !important;
            align-items: center !important;
            line-height: 1.2 !important;
            position: relative !important;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            color: #166534 !important;
            border: none !important;
            margin-right: 10px !important;
            margin-left: 0 !important;
            font-size: 18px !important;
            background: none !important;
            opacity: 0.5 !important;
            transition: all 0.2s !important;
            display: inline-block !important;
            position: static !important;
            float: none !important;
            cursor: pointer !important;
            line-height: 1 !important;
            padding: 0 2px !important;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
            color: #ef4444 !important;
            background: rgba(239, 68, 68, 0.1) !important;
            border-radius: 4px !important;
            opacity: 1 !important;
        }
        .select2-container--default .select2-search--inline {
            flex-grow: 1 !important;
            margin: 0 !important;
            display: inline-flex !important;
            align-items: center !important;
        }
        .select2-container--default .select2-search--inline .select2-search__field {
            margin: 0 !important;
            padding: 0 !important;
            font-family: inherit !important;
            font-size: 14px !important;
            height: 24px !important;
            line-height: 24px !important;
            background: transparent !important;
            border: 0 !important;
            outline: 0 !important;
            box-shadow: none !important;
            width: 100% !important;
            vertical-align: middle !important;
        }
        .select2-container--default .select2-selection__clear {
            border: none !important;
            background: none !important;
            padding: 0 4px !important;
            margin-left: 8px !important;
            color: #cbd5e1 !important;
            cursor: pointer !important;
            font-size: 20px !important;
            transition: color 0.2s !important;
            line-height: 1 !important;
        }
        .select2-container--default .select2-selection__clear:hover {
            color: #ef4444 !important;
        }
    </style>
    <div id="root">
        <div class="app-layout-modern flex flex-auto flex-col min-h-screen">
            <div class="flex flex-auto min-w-0">
                <?php include './views/layouts/sidebar.php'; ?>
                <div class="flex flex-col flex-auto min-h-screen min-w-0 relative w-full bg-white border-l border-gray-200">
                    <?php include './views/layouts/header.php'; ?>
                    <div id="alertContainer" class="p-4"><?= displayAlert() ?></div>
                    
                    <main class="h-full flex flex-col md:flex-row overflow-hidden">
                        <!-- Main Content -->
                        <div class="flex-1 overflow-y-auto page-container custom-scrollbar">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="text-2xl font-bold text-gray-800">Questionnaire</h3>
                                <div class="flex gap-2 items-center">
                                    <?php if ($is_patient): ?>
                                        <a href="patient-details.php" class="btn btn-default btn-sm bg-gray-100 hover:bg-gray-200 text-gray-700 border-none">
                                            <i class="fas fa-history mr-2"></i> Back to History
                                        </a>
                                    <?php else: ?>
                                        <a href="patient_notes.php?user_id=<?= $user_id ?>" class="btn btn-default btn-sm">Patient Notes</a>
                                        <a href="logs.php?user_id=<?= $user_id ?>" class="btn btn-default btn-sm">Logs</a>
                                        <a href="patient_documents.php?user_id=<?= $user_id ?>" class="btn btn-default btn-sm">Documents</a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Patient Overview Premium Card -->
                            <div class="patient-overview-card">
                                <div class="patient-overview-header">
                                    <i class="fas fa-user-circle"></i>
                                    <h4>Patient Overview</h4>
                                </div>
                                <div class="patient-overview-body">
                                    <div class="patient-info-grid">
                                        <!-- Full Name -->
                                        <div class="patient-info-tile">
                                            <div class="patient-info-icon teal">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            </div>
                                            <div class="patient-info-content">
                                                <span class="patient-info-label">Full Name</span>
                                                <span class="patient-info-value" id="patientNameDisplay">—</span>
                                                <input type="hidden" id="patientName" />
                                            </div>
                                        </div>
                                        <!-- Email Address -->
                                        <div class="patient-info-tile">
                                            <div class="patient-info-icon blue">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                            </div>
                                            <div class="patient-info-content">
                                                <span class="patient-info-label">Email Address</span>
                                                <span class="patient-info-value" id="patientEmailDisplay">—</span>
                                                <input type="hidden" id="patientEmail" />
                                            </div>
                                        </div>
                                        <!-- Contact Number -->
                                        <div class="patient-info-tile">
                                            <div class="patient-info-icon purple">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                                            </div>
                                            <div class="patient-info-content">
                                                <span class="patient-info-label">Contact Number</span>
                                                <span class="patient-info-value" id="patientPhoneDisplay">—</span>
                                                <input type="hidden" id="patientPhone" />
                                            </div>
                                        </div>
                                        <!-- Assigned Staff -->
                                        <div class="patient-info-tile">
                                            <div class="patient-info-icon rose">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                            </div>
                                            <div class="patient-info-content">
                                                <span class="patient-info-label">Assigned Staff</span>
                                                <span class="patient-info-value" id="assignedStaffDisplay">Not Assigned</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <form id="questionnaireForm" method="POST" enctype="multipart/form-data">
                                <div class="mt-10 mb-6 flex justify-between items-center">
                                    <div>
                                        <h4 class="text-xl font-bold text-gray-800">Assigned Questions</h4>
                                        <p class="text-sm text-gray-500">Please complete all assessment fields below</p>
                                    </div>
                                    <div>
                                        <select id="patientShiftFilter" class="input p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none w-48">
                                            <option value="">All Shifts</option>
                                            <?php foreach ($note_types as $type): ?>
                                                <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div id="selectedQuestionnaires" class="space-y-6">
                                    <!-- Questions loaded here -->
                                    <p class="text-gray-500 italic">Loading questions...</p>
                                </div>
                            </form>
                        </div>

                        <!-- Right Sidebar for Admins -->
                        <?php if ($is_admin): ?>
                         <div class="sidebar p-6 hidden lg:flex flex-col">
                             <div class="mb-8">
                                 <h4 class="text-xl font-bold text-gray-800">Manage Questions</h4>
                             </div>
                             
                             <div class="mb-6">
                                 <button type="button" data-bs-toggle="modal" data-bs-target="#addquestion" class="btn-solid w-full">
                                     <i class="fas fa-plus-circle mr-2"></i>
                                     Create New Question
                                 </button>
                             </div>
  
                             <!-- Enhanced Search & Filter Panel -->
                             <div class="mb-8 shadow-sm relative overflow-hidden group" style="background-color: #ffffff; padding: 1.5rem; border-radius: 1.5rem; border: 1px solid #f3f4f6;">
                                 <div style="position: absolute; top: -2.5rem; right: -2.5rem; width: 10rem; height: 10rem; background-color: #ecfdf5; border-radius: 9999px; opacity: 0.5; transition: transform 0.7s;" class="group-hover:scale-110"></div>
                                 
                                 <div style="position: relative; margin-bottom: 1.5rem;">
                                     <h5 style="font-size: 10px; font-weight: 800; color: #059669; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem; margin-top: 0;">
                                         <i class="fas fa-search" style="font-size: 11px;"></i> SMART DISCOVERY
                                     </h5>
                                     <p style="font-size: 11px; color: #9ca3af; font-weight: 500; margin: 0;">Quickly find and filter assessment tools</p>
                                 </div>

                                 <div style="margin-top: 1.5rem; position: relative;">
                                     <div style="position: relative; width: 100%; display: flex; align-items: center; margin-bottom: 16px;">
                                         <div style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; border-radius: 10px; background-color: #ecfdf5; color: #059669; z-index: 10; font-size: 12px; pointer-events: none;">
                                             <i class="fas fa-search"></i>
                                         </div>
                                         <input type="text" id="questionSearch" placeholder="Search questions..." 
                                                style="width: 100% !important; box-sizing: border-box !important; border: 1px solid #e2e8f0; border-radius: 14px; height: 50px !important; min-height: 50px !important; padding-left: 54px !important; padding-right: 16px !important; padding-top: 0 !important; padding-bottom: 0 !important; background-color: #ffffff; font-size: 13px; box-shadow: 0 1px 2px rgba(0,0,0,0.02); outline: none; margin: 0 !important; display: block !important; line-height: 48px !important; font-weight: 400 !important;" 
                                                onfocus="this.style.borderColor='#66b19c'; this.style.boxShadow='0 0 0 4px rgba(102, 177, 156, 0.1)';" 
                                                onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='0 1px 2px rgba(0,0,0,0.02)';"/>
                                     </div>

                                     <div style="position: relative; width: 100%; display: flex; align-items: center; margin-bottom: 12px;">
                                         <div style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; border-radius: 10px; background-color: #ecfdf5; color: #059669; z-index: 10; font-size: 12px; pointer-events: none;">
                                             <i class="fas fa-filter"></i>
                                         </div>
                                         <select id="statusFilter" 
                                                 style="appearance: none !important; -webkit-appearance: none !important; width: 100% !important; box-sizing: border-box !important; border: 1px solid #e2e8f0; border-radius: 14px; height: 50px !important; min-height: 50px !important; padding-left: 54px !important; padding-right: 40px !important; padding-top: 0 !important; padding-bottom: 0 !important; background-color: #ffffff; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #4b5563; box-shadow: 0 1px 2px rgba(0,0,0,0.02); outline: none; cursor: pointer; margin: 0 !important; display: block !important; line-height: 48px !important;"
                                                 onfocus="this.style.borderColor='#66b19c'; this.style.boxShadow='0 0 0 4px rgba(102, 177, 156, 0.1)';" 
                                                 onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='0 1px 2px rgba(0,0,0,0.02)';">
                                             <option value="all">Display All</option>
                                             <option value="assigned">Assigned Only</option>
                                             <option value="unassigned">Unassigned Only</option>
                                         </select>
                                         <div style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: #9ca3af; pointer-events: none; font-size: 14px;">
                                             <i class="fas fa-chevron-down"></i>
                                         </div>
                                     </div>

                                     <div style="position: relative; width: 100%; display: flex; align-items: center; margin-bottom: 0;">
                                         <div style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; border-radius: 10px; background-color: #f0fdf4; color: #059669; z-index: 10; font-size: 12px; pointer-events: none;">
                                             <i class="fas fa-clock"></i>
                                         </div>
                                         <select id="patientShiftFilter" 
                                                 style="appearance: none !important; -webkit-appearance: none !important; width: 100% !important; box-sizing: border-box !important; border: 1px solid #e2e8f0; border-radius: 14px; height: 50px !important; min-height: 50px !important; padding-left: 54px !important; padding-right: 40px !important; padding-top: 0 !important; padding-bottom: 0 !important; background-color: #ffffff; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #4b5563; box-shadow: 0 1px 2px rgba(0,0,0,0.02); outline: none; cursor: pointer; margin: 0 !important; display: block !important; line-height: 48px !important;"
                                                 onfocus="this.style.borderColor='#66b19c'; this.style.boxShadow='0 0 0 4px rgba(102, 177, 156, 0.1)';" 
                                                 onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='0 1px 2px rgba(0,0,0,0.02)';">
                                             <?php foreach ($note_types as $type): ?>
                                                 <option value="<?= (int)$type['id'] ?>"><?= htmlspecialchars($type['name']) ?></option>
                                             <?php endforeach; ?>
                                         </select>
                                         <div style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: #9ca3af; pointer-events: none; font-size: 14px;">
                                             <i class="fas fa-chevron-down"></i>
                                         </div>
                                     </div>
                                 </div>
                             </div>

                             <div id="questionList" class="flex-1 overflow-y-auto custom-scrollbar pr-2 -mr-2 question-list-container">
                                 <!-- Dynamic items -->
                             </div>
 
                             <div class="flex justify-between items-center pt-6 mt-6 border-t border-gray-100">
                                 <button id="prevPage" class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:bg-white hover:text-emerald-500 transition-all border border-transparent hover:border-gray-100"><i class="fas fa-chevron-left"></i></button>
                                 <span id="pageInfo" class="text-[10px] font-bold text-gray-400 uppercase">Page 1</span>
                                 <button id="nextPage" class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:bg-white hover:text-emerald-500 transition-all border border-transparent hover:border-gray-100"><i class="fas fa-chevron-right"></i></button>
                            </div>
                        </div>
                        <?php endif; ?>
                    </main>

                    <!-- Sticky Footer Actions -->
                    <div class="sticky-footer flex justify-end items-center gap-3 bg-white/80 backdrop-blur-md border border-gray-200/50">
                        <button type="button" id="saveDraftButton" class="btn border border-emerald-200 text-emerald-600 bg-white hover:bg-emerald-50 px-6 rounded-xl font-bold transition-all active:scale-95">Save as Draft</button>
                        <button type="submit" form="questionnaireForm" id="saveAnswersButton" class="btn bg-emerald-500 hover:bg-emerald-600 text-white px-8 rounded-xl font-bold shadow-lg shadow-emerald-100 transition-all active:scale-95">Final Submission</button>
                    </div>
                    <?php if ($is_admin): ?>
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
                                        <p class="modal-desc">Create a new assessment tool for this patient</p>
                                    </div>
                                    <div id="alertContainerModal" class="px-6 pt-4"></div>
                                    <form id="addQuestionForm" method="POST">
                                        <div class="modal-premium-body">
                                            <div class="mb-4">
                                                <label class="block text-sm font-bold text-gray-700 mb-2">Question Text</label>
                                                <input class="input w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none transition-all" type="text" name="question_text" placeholder="e.g., Blood Pressure" required>
                                            </div>
                                             <div class="mb-4">
                                                <label class="block text-sm font-bold text-gray-700 mb-2">Question Type</label>
                                                <select class="input w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none transition-all" name="question_type" id="question_type" onchange="toggleOptions()">
                                                    <option value="">Select a question type</option>
                                                </select>
                                            </div>
                                            <div class="mb-5 flex items-start gap-4">
                                                <label class="premium-switch flex-shrink-0 mt-0.5">
                                                    <input type="checkbox" name="is_mandatory" value="1" id="is_mandatory_cb" checked>
                                                    <span class="premium-switch-slider"></span>
                                                </label>
                                                <div class="flex-1">
                                                    <span class="premium-switch-label block font-bold text-gray-700">Mandatory Question</span>
                                                    <p class="text-[11px] text-gray-500 mt-0.5">Patient must provide an answer to this question.</p>
                                                </div>
                                            </div>

                                            <div id="options_container" style="display: none;" class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                                                <label class="block text-sm font-bold text-gray-700 mb-2">Options</label>
                                                <div id="options_list" class="mb-3"></div>
                                                <div class="flex items-center space-x-2">
                                                    <input class="input flex-1 p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none transition-all" type="text" id="option_input" placeholder="Enter option">
                                                    <button type="button" class="btn bg-white border border-gray-300 text-gray-700 rounded-lg px-3 py-2 hover:bg-gray-50 font-medium" onclick="addOption()">Add</button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-premium-footer">
                                            <input type="hidden" name="create_question" value="1">
                                            <input type="hidden" name="csrf_token" id="csrf_token_modal" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <button type="button" class="btn-modal btn-modal-gray" data-bs-dismiss="modal">Cancel</button>
                                            <button class="btn-modal bg-emerald-600 text-white hover:bg-emerald-700" type="submit" id="submitQuestion">Add Question</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Confirmation Modal -->
                        <div class="modal fade modal-premium" id="confirmModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog dialog max-w-sm w-full mt-20">
                                <div class="dialog-content">
                                    <span class="close-btn absolute z-10" role="button" data-bs-dismiss="modal">
                                        <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 20 20" aria-hidden="true" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                        </svg>
                                    </span>
                                    <div class="modal-premium-header">
                                        <h4 class="text-xl font-bold" id="confirmModalTitle">Are you sure?</h4>
                                        <p class="modal-desc" id="confirmModalDesc"></p>
                                    </div>
                                    <div class="modal-premium-footer flex justify-end gap-2 p-4">
                                        <button type="button" class="btn-modal btn-modal-gray" data-bs-dismiss="modal">Cancel</button>
                                        <button type="button" class="btn-modal bg-emerald-600 text-white hover:bg-emerald-700" id="confirmModalBtn">Confirm</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
    <?php include './views/layouts/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
$(document).ready(function() {
    // Debug: Log PHP variables to confirm values
    console.log('PHP user_id:', <?php echo json_encode($user_id ?? 'undefined'); ?>);
    console.log('PHP session user_id:', <?php echo json_encode($_SESSION['user']['id'] ?? 'undefined'); ?>);
    console.log('PHP GET user_id:', <?php echo json_encode($_GET['user_id'] ?? 'undefined'); ?>);

    // Verify jQuery and Select2 are loaded
    console.log('jQuery loaded:', typeof $ !== 'undefined');
    console.log('Select2 loaded:', typeof $.fn.select2 !== 'undefined');

    // Initialize variables from PHP
    const isAdmin = <?php echo json_encode($is_admin ?? false); ?>;
    const isStaff = <?php echo json_encode($is_staff ?? false); ?>;
    const isPatient = <?php echo json_encode($is_patient ?? false); ?>;
    const userId = <?php echo (int)$user_id; ?>;
    console.log("userId:", userId);
    const csrfToken = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;

    // Debug: Log all initialized variables
    console.log('Initialized variables:', { isAdmin, isStaff, isPatient, userId, csrfToken });

    // Question type UI metadata (icons and colors)
    const questionUI = {
        text: { icon: 'fas fa-pencil-alt', color: 'blue' },
        textarea: { icon: 'fas fa-align-left', color: 'blue' },
        textbox_list: { icon: 'fas fa-list-ul', color: 'purple' },
        dropdown: { icon: 'fas fa-chevron-circle-down', color: 'amber' },
        multidropdown: { icon: 'fas fa-check-double', color: 'emerald' },
        checkbox: { icon: 'fas fa-check-square', color: 'emerald' },
        radio: { icon: 'fas fa-dot-circle', color: 'emerald' },
        file: { icon: 'fas fa-paperclip', color: 'rose' },
        upload_image: { icon: 'fas fa-image', color: 'rose' },
        upload_file: { icon: 'fas fa-file-alt', color: 'rose' }
    };

    function getQuestionIcon(type, assigned = false) {
        const ui = questionUI[type] || { icon: 'fas fa-question-circle', color: 'gray' };
        if (assigned) return `<i class="${ui.icon} text-white"></i>`;
        return `<i class="${ui.icon}"></i>`;
    }

    function getIconBoxClass(type) {
        const ui = questionUI[type] || { color: 'gray' };
        return `icon-box-${ui.color}`;
    }

    // Form and element references
    const questionnaireForm = $('#questionnaireForm');
    const addQuestionForm = $('#addQuestionForm');
    const questionSidebar = $('#questionSidebar');
    const selectedQuestionnaires = $('#selectedQuestionnaires');
    let options = [];

    // Initialize Select2 for multidropdown with fallback
    function initializeSelect2(element) {
        if (typeof $.fn.select2 !== 'undefined') {
            $(element).select2({
                placeholder: 'Select options',
                allowClear: true,
                width: '100%'
            });
        } else {
            console.warn('Select2 not loaded; using default multi-select');
            $(element).css({
                'height': 'auto',
                'min-height': '100px'
            });
        }
    }

    // Helper function for AJAX requests
    function makeAjaxRequest(url, method, data, contentType = 'application/json; charset=UTF-8') {
        console.log('Making AJAX request:', { url, method, data, contentType });
        return $.ajax({
            url: url,
            method: method,
            data: data,
            contentType: contentType,
            headers: { 'X-CSRF-Token': csrfToken },
            dataType: 'json',
            error: function(xhr) {
                console.error('AJAX error:', xhr.status, xhr.responseText);
                showError('Error: ' + (xhr.responseJSON?.message || 'Failed to communicate with server (Status: ' + xhr.status + ')'));
            }
        });
    }

    // Show error message
    function showError(message) {
        $('#alertContainer').html(`
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `);
        $('html, body, .page-container').animate({ scrollTop: 0 }, 500);
    }

    // Show success message
    function showSuccess(message) {
        $('#alertContainer').html(`
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `);
        $('html, body, .page-container').animate({ scrollTop: 0 }, 500);
    }

    // Toggle options container
    window.toggleOptions = function() {
        const questionType = $('#question_type').val();
        const optionsContainer = $('#options_container');
        optionsContainer.css('display', ['dropdown', 'multidropdown', 'checkbox', 'radio'].includes(questionType) ? 'block' : 'none');
        options = [];
        $('#options_list').empty();
    }

    // Add option to the list
    window.addOption = function() {
        const optionInput = $('#option_input').val().trim();
        if (!optionInput) {
            showError('Please enter an option.');
            return;
        }
        if (options.includes(optionInput)) {
            showError('This option already exists.');
            return;
        }
        options.push(optionInput);
        const optionsList = $('#options_list');
        const optionDiv = $('<div>').addClass('flex items-center space-x-2 mb-2');
        optionDiv.html(`
            <span class="text-sm text-gray-700">${$('<div>').text(optionInput).html()}</span>
            <input type="hidden" name="options[]" value="${$('<div>').text(optionInput).html()}">
            <button type="button" class="text-red-600 hover:text-red-800" onclick="removeOption(this, '${$('<div>').text(optionInput).html()}')">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        `);
        optionsList.append(optionDiv);
        $('#option_input').val('');
    }

    // Remove option from the list
    window.removeOption = function(button, optionText) {
        options = options.filter(opt => opt !== optionText);
        $(button).parent().remove();
    }

    // Load user details
    function loadUserDetails() {
        console.log("data")
        console.log(userId)
        makeAjaxRequest('api/api.php?action=get_user_details', 'GET', { user_id: userId })
            .done(function(response) {
                if (response.success) {
                    const name = response.data.username || '';
                    const email = response.data.email || '';
                    const phone = response.data.phone || '';
                    $('#patientName').val(name);
                    $('#patientEmail').val(email);
                    $('#patientPhone').val(phone);
                    $('#patientNameDisplay').text(name || '—');
                    $('#patientEmailDisplay').text(email || '—');
                    $('#patientPhoneDisplay').text(phone || '—');
                    $('#assignedStaffDisplay').text(response.data.assigned_staff || 'Not Assigned');
                } else {
                    showError('Failed to load user details: ' + response.message);
                }
            });
    }

    // Pagination variables
    let currentPage = 1;
    const questionsPerPage = 10;
    let totalQuestions = 0;

    // Load questions with pagination, search and filters
    function loadQuestions() {
        const searchTerm = $('#questionSearch').val()?.toLowerCase() || '';
        const statusFilter = $('#statusFilter').val() || 'all';
        const questionList = $('#questionList');
        questionList.empty();
        const paginationControls = $('#paginationControls');
        const prevPage = $('#prevPage');
        const nextPage = $('#nextPage');
        const pageInfo = $('#pageInfo');
        const noteTypeId = '';

        makeAjaxRequest('api/api.php?action=get_questions', 'GET', { note_type_id: noteTypeId })
            .done(function(questionsResponse) {
                if (!Array.isArray(questionsResponse) || questionsResponse.length === 0) {
                    questionList.append('<div class="p-8 text-center bg-gray-50/50 rounded-2xl"><p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest">No questions available</p></div>');
                    return;
                }

                makeAjaxRequest('api/api.php?action=get_patient_questions', 'GET', { user_id: userId })
                    .done(function(patientQuestionsResponse) {
                        const patientQuestionIds = Array.isArray(patientQuestionsResponse) ? patientQuestionsResponse.map(q => parseInt(q.question_id)) : [];
                        
                        let filteredQuestions = questionsResponse.filter(q => {
                            const matchesSearch = q.question_text.toLowerCase().includes(searchTerm);
                            const isAssigned = patientQuestionIds.includes(parseInt(q.question_id));
                            const matchesStatus = statusFilter === 'all' || 
                                               (statusFilter === 'assigned' && isAssigned) || 
                                               (statusFilter === 'unassigned' && !isAssigned);
                            return matchesSearch && matchesStatus;
                        });

                        totalQuestions = filteredQuestions.length;
                        const totalPages = Math.ceil(totalQuestions / questionsPerPage);
                        if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
                        const startIndex = (currentPage - 1) * questionsPerPage;
                        const paginatedQuestions = filteredQuestions.slice(startIndex, startIndex + questionsPerPage);

                        if (paginatedQuestions.length === 0) {
                            questionList.append('<div class="p-8 text-center bg-gray-50/50 rounded-2xl"><p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest">No matching questions</p></div>');
                            pageInfo.text('No items');
                            prevPage.prop('disabled', true);
                            nextPage.prop('disabled', true);
                            return;
                        }

                        paginatedQuestions.forEach(question => {
                            const escapedText = $('<div>').text(question.question_text).html();
                            const isAssigned = patientQuestionIds.includes(parseInt(question.question_id));
                            const iconHtml = getQuestionIcon(question.question_type, isAssigned);
                            const iconBoxClass = isAssigned ? '' : getIconBoxClass(question.question_type);
                            
                            questionList.append(`
                                <div class="question-card shadow-sm transition-all cursor-pointer ${isAssigned ? 'assigned' : 'hover:border-emerald-300'}" 
                                     data-question-id="${parseInt(question.question_id)}" 
                                     data-question-text="${escapedText}"
                                     data-question-type="${question.question_type}">
                                    <div class="icon-box ${iconBoxClass}">
                                        ${iconHtml}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-[14px] font-bold leading-normal mb-1.5 text-gray-800">${escapedText}</p>
                                        <p class="text-[9px] uppercase font-bold tracking-[0.2em] ${isAssigned ? 'text-emerald-600/60' : 'text-gray-400'}">${question.question_type.replace('_', ' ')}</p>
                                    </div>
                                    <div class="shrink-0 ml-3">
                                        ${isAssigned ? '<i class="fas fa-check-circle text-emerald-500 text-xl"></i>' : '<i class="fas fa-plus-circle text-gray-200 group-hover:text-emerald-400 transition-colors text-xl"></i>'}
                                    </div>
                                </div>
                            `);
                        });

                        pageInfo.text(`Page ${currentPage} of ${totalPages}`);
                        prevPage.prop('disabled', currentPage === 1);
                        nextPage.prop('disabled', currentPage === totalPages || totalPages === 0);
                    });
            });
    }

    // Pagination and filter event listeners are consolidated at the end of the script

    // Add question to form
    function addQuestionToForm(question) {
        console.log('Adding question to form:', question);
        
        // Remove empty state placeholders if they exist
        // Remove any element that is not a question-item to ensure placeholders are gone
        selectedQuestionnaires.children(':not(.question-item)').remove();
        
        const escapedText = $('<div>').text(question.question_text).html();
        let inputHtml = '';
        switch (question.question_type) {
            case 'text':
                inputHtml = `
                    <input
                        type="text"
                        name="answers[${parseInt(question.question_id)}]"
                        class="input-premium"
                        value="${question.answer_text || ''}"
                        placeholder="Type answer here..."
                    >
                `;
                break;
            case 'textarea':
                inputHtml = `
                    <textarea
                        name="answers[${parseInt(question.question_id)}]"
                        class="input-premium min-h-[120px]"
                        rows="4"
                        placeholder="Provide details here..."
                    >${question.answer_text || ''}</textarea>
                `;
                break;
            case 'textbox_list':
                inputHtml = `
                    <div class="space-y-3 textbox-list" data-question-id="${parseInt(question.question_id)}">
                        ${(function() {
                            const values = question.answer_text ? question.answer_text.split(',') : [''];
                            return values.map(val => `
                                <div class="flex items-center space-x-2 mt-1">
                                    <input
                                        type="text"
                                        name="answers[${parseInt(question.question_id)}][]"
                                        class="input w-full p-3 text-sm border-gray-200 rounded-lg focus:ring-emerald-500"
                                        value="${val}"
                                        placeholder="Type here..."
                                    >
                                    ${values.length > 1 ? `<button type="button" class="w-8 h-8 flex items-center justify-center text-rose-500 hover:bg-rose-50 rounded-lg transition-colors remove-textbox" onclick="$(this).parent().remove()"><i class="fas fa-minus-circle"></i></button>` : ''}
                                </div>
                            `).join('');
                        })()}
                        <button type="button" class="mt-2 text-[12px] font-bold text-emerald-600 hover:text-emerald-700 flex items-center gap-1" onclick="addTextbox(${parseInt(question.question_id)})">
                            <i class="fas fa-plus-circle"></i> Add Another Item
                        </button>
                    </div>
                `;
                break;
            case 'dropdown':
                inputHtml = `
                    <select name="answers[${parseInt(question.question_id)}]" class="input-premium">
                        <option value="">Select an option</option>
                        ${Array.isArray(question.options) ? question.options.map(option => {
                            const escapedOption = $('<div>').text(option).html();
                            const selected = question.answer_text === option ? 'selected' : '';
                            return `<option value="${escapedOption}" ${selected}>${escapedOption}</option>`;
                        }).join('') : ''}
                    </select>
                `;
                break;
            case 'multidropdown':
                inputHtml = `
                    <select multiple name="answers[${parseInt(question.question_id)}][]" class="input-premium select2-multidropdown">
                        ${Array.isArray(question.options) ? question.options.map(option => {
                            const escapedOption = $('<div>').text(option).html();
                            const selectedValues = question.answer_text ? question.answer_text.split(',') : [];
                            const selected = selectedValues.includes(option) ? 'selected' : '';
                            return `<option value="${escapedOption}" ${selected}>${escapedOption}</option>`;
                        }).join('') : ''}
                    </select>
                `;
                break;
            case 'checkbox':
                inputHtml = '<div class="space-y-4 mt-2">';
                if (Array.isArray(question.options)) {
                    question.options.filter(opt => opt && opt.trim() !== '').forEach(option => {
                        const escapedOption = $('<div>').text(option).html();
                        const isOther = option.toLowerCase().includes('other') || option.includes('...');
                        const selectedValues = question.answer_text ? question.answer_text.split(',').map(v => v.trim()) : [];
                        
                        // Check if this specific base option is checked
                        const isChecked = selectedValues.some(v => v === option.trim() || v.startsWith(option.trim() + ':'));
                        const checkedAttr = isChecked ? 'checked' : '';
                        
                        // Extract specific value if it was "Other: value"
                        let specifyValue = '';
                        if (isOther && isChecked) {
                             const otherToken = selectedValues.find(v => v.startsWith(option.trim() + ':'));
                             if (otherToken) {
                                 specifyValue = otherToken.substring(option.trim().length + 1).trim();
                             }
                        }

                        inputHtml += `
                            <div class="option-wrapper">
                                <label class="checkbox-item mb-1">
                                    <input type="checkbox" name="answers[${parseInt(question.question_id)}][]" value="${escapedOption}" ${checkedAttr} onchange="$(this).closest('.option-wrapper').find('.specify-input').toggleClass('hidden', !this.checked)">
                                    <span>${escapedOption}</span>
                                </label>
                                ${isOther ? `<input type="text" class="specify-input input-premium text-sm py-2 ml-10 w-[calc(100%-2.5rem)] ${isChecked ? '' : 'hidden'}" placeholder="Please specify details..." value="${specifyValue}">` : ''}
                            </div>
                        `;
                    });
                }
                inputHtml += '</div>';
                break;
            case 'radio':
                inputHtml = '<div class="space-y-4 mt-2">';
                if (Array.isArray(question.options)) {
                    question.options.filter(opt => opt && opt.trim() !== '').forEach(option => {
                        const escapedOption = $('<div>').text(option).html();
                        const isOther = option.toLowerCase().includes('other') || option.includes('...');
                        
                        // Check if this specific base option is selected
                        const isChecked = question.answer_text === option.trim() || (question.answer_text && question.answer_text.startsWith(option.trim() + ':'));
                        const checkedAttr = isChecked ? 'checked' : '';
                        
                        let specifyValue = '';
                        if (isOther && isChecked) {
                            if (question.answer_text.startsWith(option.trim() + ':')) {
                                specifyValue = question.answer_text.substring(option.trim().length + 1).trim();
                            }
                        }

                        inputHtml += `
                            <div class="option-wrapper">
                                <label class="checkbox-item mb-1">
                                    <input type="radio" name="answers[${parseInt(question.question_id)}]" value="${escapedOption}" ${checkedAttr} onchange="$(this).closest('.space-y-4').find('.specify-input').addClass('hidden'); if(this.checked) $(this).closest('.option-wrapper').find('.specify-input').removeClass('hidden')">
                                    <span>${escapedOption}</span>
                                </label>
                                ${isOther ? `<input type="text" class="specify-input input-premium text-sm py-2 ml-10 w-[calc(100%-2.5rem)] ${isChecked ? '' : 'hidden'}" placeholder="Please specify details..." value="${specifyValue}">` : ''}
                            </div>
                        `;
                    });
                }
                inputHtml += '</div>';
                break;
            case 'file':
            case 'upload_image':
            case 'upload_file':
                inputHtml = `
                    <div class="file-input-container">
                        <input
                            type="file"
                            name="file_answers[${parseInt(question.question_id)}]"
                            class="input"
                            accept="${question.question_type === 'upload_file' ? '.pdf,.doc,.docx' : 'image/*'}"
                        >
                        <div class="file-preview-container mt-2"></div>
                    </div>
                `;
                break;
            default:
                inputHtml = `<p class="text-red-500 text-sm">Unsupported: ${question.question_type}</p>`;
        }

        const iconHtml = getQuestionIcon(question.question_type);
        const iconBoxClass = getIconBoxClass(question.question_type);

        selectedQuestionnaires.append(`
            <div class="question-item bg-white border border-gray-100 rounded-2xl p-6 mb-6 shadow-sm hover:shadow-md transition-all group" data-question-id="${parseInt(question.question_id)}" data-is-mandatory="${question.is_mandatory == 1 ? '1' : '0'}">
                <div class="question-label-container flex items-start gap-4 mb-6 pb-4 border-b border-gray-50">
                    <div class="icon-box ${iconBoxClass} shadow-sm">
                        ${iconHtml}
                    </div>
                    <div class="flex-1 min-w-0">
                        <label class="text-[17px] font-bold text-gray-800 leading-snug block">
                            ${escapedText}
                            ${question.is_mandatory == 1 ? '<span class="text-rose-500 ml-0.5" title="Required">*</span>' : ''}
                        </label>
                        <div class="flex items-center gap-2 mt-1.5">
                            <p class="text-[10px] uppercase font-extrabold text-gray-400 tracking-[0.1em]">${question.question_type.replace('_', ' ')}</p>
                            ${question.last_submitted_by ? `
                                <span class="text-[10px] text-gray-300">•</span>
                                <p class="text-[10px] font-bold text-emerald-500/70 italic">
                                    Last saved by ${question.last_submitted_by} ${question.last_answered ? 'at ' + new Date(question.last_answered).toLocaleString('en-IN', { day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit', hour12:true }) : ''}
                                </p>
                            ` : ''}
                        </div>
                    </div>
                    ${isAdmin ? `
                        <button type="button" class="remove-question w-8 h-8 rounded-full flex items-center justify-center text-gray-300 hover:bg-rose-50 hover:text-rose-500 transition-all" data-question-id="${parseInt(question.question_id)}" title="Remove Question">
                            <i class="fas fa-trash-alt text-[14px]"></i>
                        </button>
                    ` : ''}
                </div>
                <div class="input-container relative">
                    ${inputHtml}
                </div>
            </div>
        `);

        if (question.question_type === 'multidropdown') {
            initializeSelect2(`.question-item[data-question-id="${question.question_id}"] .select2-multidropdown`);
        }
        if (['file', 'upload_image', 'upload_file'].includes(question.question_type)) {
            $(`.question-item[data-question-id="${question.question_id}"] input[type="file"]`).on('change', function() {
                const previewContainer = $(this).siblings('.file-preview-container').empty();
                const file = this.files[0];
                if (file && file.type.startsWith('image/') && ['file', 'upload_image'].includes(question.question_type)) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewContainer.append(`<img src="${e.target.result}" class="file-preview" alt="File preview">`);
                    };
                    reader.readAsDataURL(file);
                } else if (file) {
                    previewContainer.append(`<span class="text-sm text-gray-600">${file.name}</span>`);
                }
            });
        }
    }

    // Load selected questionnaires with answer fields
    function loadSelectedQuestions() {
        selectedQuestionnaires.empty();
        const shiftId = $('#patientShiftFilter').val() || ''; // Use the selected filter
        // Normal mode: Load current assigned questions
        makeAjaxRequest('api/api.php?action=get_patient_questions', 'GET', { user_id: userId, shift_id: shiftId })
            .done(function(response) {
                console.log('loadSelectedQuestions response:', response);
                if (!Array.isArray(response) || response.length === 0) {
                    selectedQuestionnaires.html('<div class="p-10 text-center bg-gray-50/50 rounded-2xl border-2 border-dashed border-gray-100"><p class="text-gray-500 text-sm">No questions selected for this shift. ' + (isAdmin ? 'Use the panel on the right to assign questions.' : 'Please contact an admin to assign questions.') + '</p></div>');
                    return;
                }
                response.forEach(question => {
                    addQuestionToForm(question);
                });
            })
            .fail(function(xhr) {
                selectedQuestionnaires.append('<p class="text-red-500 text-sm">Error loading selected questions.</p>');
            });
    }

    function loadQuestionTypes() {
        makeAjaxRequest('api/api.php?action=get_question_types', 'GET', {})
            .done(function(response) {
                console.log('get_question_types response:', response);
                if (response.success && Array.isArray(response.data)) {
                    const questionTypeSelect = $('#question_type');
                    questionTypeSelect.empty();
                    questionTypeSelect.append('<option value="">Select a question type</option>');
                    response.data.forEach(type => {
                        const typeName = type.type_name || type;
                        const displayName = type.display_name || typeName.charAt(0).toUpperCase() + typeName.slice(1);
                        questionTypeSelect.append(`
                            <option value="${typeName}">${displayName}</option>
                        `);
                    });
                } else {
                    showError('Failed to load question types: ' + (response.message || 'Unknown error'));
                }
            })
            .fail(function(xhr) {
                showError('Failed to load question types: ' + (xhr.responseJSON?.message || 'Server error'));
            });
    }

    // Load question types when the modal is shown
    $('#addquestion').on('show.bs.modal', function() {
        loadQuestionTypes();
    });

    $('#addQuestionForm').on('submit', function(e) {
        e.preventDefault();
        const submitButton = $('#submitQuestion');
        submitButton.prop('disabled', true);

        const questionText = $('input[name="question_text"]').val().trim();
        const questionType = $('#question_type').val();
        const csrfToken = $('#csrf_token').val();

        if (!questionText) {
            showError('Question text is required.');
            submitButton.prop('disabled', false);
            return;
        }
        if (!questionType) {
            showError('Please select a question type.');
            submitButton.prop('disabled', false);
            return;
        }

        const noteTypeId = 1;

        const isMandatory = $('#is_mandatory_cb').is(':checked') ? 1 : 0;
        const data = {
            question_text: questionText,
            question_type: questionType,
            note_type_id: noteTypeId,
            is_mandatory: isMandatory,
            create_question: 1,
            options: ['dropdown', 'multidropdown', 'checkbox', 'radio'].includes(questionType) ? options : [],
            csrf_token: csrfToken
        };

        makeAjaxRequest('api/api.php?action=add_question', 'POST', JSON.stringify(data), 'application/json; charset=UTF-8')
            .done(function(response) {
                if (response.success) {
                    $('#addquestion').modal('hide');
                    showSuccess(response.message || 'Question added successfully.');
                    loadQuestions();
                    $('#addQuestionForm')[0].reset();
                    toggleOptions();
                    $('#options_list').empty();
                    options = [];
                } else {
                    showError('Failed to add question: ' + (response.message || 'Unknown error'));
                }
            })
            .fail(function(xhr) {
                let errorMessage = 'Server error';
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMessage = response.message || 'Server error';
                } catch (e) {
                    errorMessage = 'Failed to parse server response';
                }
                showError('Failed to add question: ' + errorMessage);
            })
            .always(function() {
                submitButton.prop('disabled', false);
            });
    });

    // Reset form on modal close
    $('#addquestion').on('hidden.bs.modal', function() {
        $('#addQuestionForm')[0].reset();
        $('#options_list').empty();
        options = [];
        toggleOptions();
        $('#alertContainer').empty(); // Clear any alerts
    });

    // Add textbox for textbox_list
    window.addTextbox = function(questionId) {
        const container = $(`.textbox-list[data-question-id="${questionId}"]`);
        container.append(`
            <div class="flex items-center space-x-2 mt-2">
                <input
                    type="text"
                    name="answers[${questionId}][]"
                    class="input w-full p-3 text-sm border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    placeholder="Type here..."
                >
                <button type="button" class="w-8 h-8 flex items-center justify-center text-rose-500 hover:bg-rose-50 rounded-lg transition-colors remove-textbox" onclick="$(this).parent().remove()">
                    <i class="fas fa-minus-circle"></i>
                </button>
            </div>
        `);
    };



    // Update selected question in DOM and assign/unassign
    function updateSelectedQuestion(questionId, questionText, add, shiftId = null) {
        console.log('Updating question state:', { questionId, questionText, add, shiftId });
        questionId = parseInt(questionId);
        if (isNaN(questionId)) {
            console.error('Invalid question ID:', questionId);
            showError('Invalid question ID.');
            return $.Deferred().reject().promise();
        }

        return makeAjaxRequest('api/api.php?action=get_patient_questions', 'GET', { user_id: userId })
            .then(function(patientQuestionsResponse) {
                console.log('get_patient_questions in updateSelectedQuestion:', patientQuestionsResponse);
                let existingQuestionIds = Array.isArray(patientQuestionsResponse)
                    ? patientQuestionsResponse.map(q => parseInt(q.question_id))
                    : [];

                let updatedQuestionIds;
                if (add) {
                    if (existingQuestionIds.includes(questionId)) {
                        showError('Question is already assigned.');
                        return $.Deferred().reject().promise();
                    }
                    updatedQuestionIds = [...existingQuestionIds, questionId];
                } else {
                    updatedQuestionIds = existingQuestionIds.filter(id => id !== questionId);
                }

                return makeAjaxRequest('api/api.php?action=assign_questions', 'POST', JSON.stringify({
                    user_id: userId,
                    shift_id: shiftId,
                    question_ids: updatedQuestionIds
                }), 'application/json; charset=UTF-8')
                    .then(function(response) {
                        if (response.success) {
                            showSuccess(response.message || (add ? 'Question assigned successfully' : 'Question unassigned successfully'));
                            if (add) {
                                return makeAjaxRequest('api/api.php?action=get_questions', 'GET', {})
                                    .then(function(allQuestions) {
                                        console.log('get_questions in updateSelectedQuestion:', allQuestions);
                                        const question = allQuestions.find(q => parseInt(q.question_id) === questionId);
                                        if (!question) {
                                            showError('Question not found.');
                                            return $.Deferred().reject().promise();
                                        }
                                        if (!selectedQuestionnaires.find(`.question-item[data-question-id="${questionId}"]`).length) {
                                            addQuestionToForm(question);
                                            // Ensure the newly assigned question is moved to the top of selectedQuestionnaires
                                            const questionElement = selectedQuestionnaires.find(`.question-item[data-question-id="${questionId}"]`);
                                            if (questionElement.length) {
                                                questionElement.detach().prependTo(selectedQuestionnaires); // Move to top
                                            }
                                            $(`#questionList .question-card[data-question-id="${questionId}"]`)
                                                .addClass('assigned')
                                                .find('.icon-box').removeClass().addClass('icon-box'); // assigned style handles icon box
                                            loadQuestions(); // Thoroughly refresh the list to ensure all state is correct
                                        }
                                    }, function(xhr) {
                                        showError('Failed to fetch question details.');
                                        return $.Deferred().reject().promise();
                                    });
                            } else {
                                selectedQuestionnaires.find(`.question-item[data-question-id="${questionId}"]`).remove();
                                $(`#questionList .question-card[data-question-id="${questionId}"]`)
                                    .removeClass('assigned');
                                loadQuestions(); // Thoroughly refresh the list
                                if (selectedQuestionnaires.children().length === 0) {
                                    selectedQuestionnaires.append(`
                                        <div class="p-12 text-center bg-gray-50/50 rounded-[2rem] border-2 border-dashed border-gray-100">
                                            <div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-sm">
                                                <i class="fas fa-clipboard-list text-2xl text-gray-300"></i>
                                            </div>
                                            <p class="text-sm font-bold text-gray-500">No questions selected</p>
                                            <p class="text-xs text-gray-400 mt-1">
                                                ${isAdmin ? 'Use the assignment panel on the right to build the evaluation.' : 'Please contact an admin to assign questions.'}
                                            </p>
                                        </div>
                                    `);
                                }
                            }
                        } else {
                            showError('Failed to ' + (add ? 'assign' : 'unassign') + ' question: ' + (response.message || 'Unknown error'));
                            return $.Deferred().reject().promise();
                        }
                    }, function(xhr) {
                        showError('Failed to ' + (add ? 'assign' : 'unassign') + ' question: ' + (xhr.responseJSON?.message || 'Server error'));
                        return $.Deferred().reject().promise();
                    });
            }, function(xhr) {
                showError('Failed to fetch current assignments.');
                return $.Deferred().reject().promise();
            });
    }

// Find the saveAnswers function in your existing code and replace it with this updated version:

function saveAnswers(status = 'final') {
    const noteTypeId = $('#patientShiftFilter').val() || '';
    // Show loading state on appropriate button
    const submitButton = status === 'draft' ? $('#saveDraftButton') : $('#saveAnswersButton');
    const originalButtonText = submitButton.html();
    
    submitButton.prop('disabled', true).html(`
        <span class="flex items-center justify-center">
            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            ${status === 'draft' ? 'Saving...' : 'Submitting...'}
        </span>
    `);

    const formData = new FormData();
    formData.append('user_id', userId);
    formData.append('csrf_token', csrfToken);
    formData.append('status', status);
    formData.append('note_type_id', noteTypeId);

    const answers = {};
    const fileInputs = [];
    let missingMandatory = false;
    let missingQuestions = [];

    $('.question-item').each(function() {
        const questionId = parseInt($(this).data('question-id'));
        if (!questionId || questionId <= 0) return true;

        const isMandatory = $(this).data('is-mandatory') == '1';
        const questionText = $(this).find('label').text().replace('*', '').trim();

        const questionType = $(this).find('select[multiple]').length ? 'multidropdown' :
                             $(this).find('input[type="checkbox"]').length ? 'checkbox' :
                             $(this).find('input[type="radio"]').length ? 'radio' :
                             $(this).find('textarea').length ? 'textarea' :
                             $(this).find('input[type="text"]').attr('name')?.includes('[]') ? 'textbox_list' :
                             $(this).find('select').length ? 'dropdown' :
                             $(this).find('input[type="file"]').length ? 
                             ($(this).find('input[type="file"]').attr('accept') === 'image/*' ? 'upload_image' : 'upload_file') : 'text';
        let answerText = '';
        if (['text', 'textarea'].includes(questionType)) {
            answerText = $(this).find('input[type="text"], textarea').val().trim();
        } else if (questionType === 'textbox_list') {
            const inputs = $(this).find('input[type="text"]').map(function() {
                return $(this).val().trim();
            }).get().filter(val => val !== '');
            answerText = inputs.join(',');
        } else if (questionType === 'dropdown') {
            answerText = $(this).find('select').val() || '';
        } else if (questionType === 'multidropdown') {
            const selected = $(this).find('select[multiple]').val() || [];
            answerText = selected.join(',');
        } else if (questionType === 'checkbox') {
            const selected = [];
            $(this).find('.option-wrapper').each(function() {
                const cb = $(this).find('input[type="checkbox"]');
                if (cb.is(':checked')) {
                    let val = cb.val();
                    const specify = $(this).find('.specify-input');
                    if (specify.length && specify.val().trim() !== '') {
                        val += ': ' + specify.val().trim();
                    }
                    selected.push(val);
                }
            });
            answerText = selected.join(',');
        } else if (questionType === 'radio') {
            const selectedWrapper = $(this).find('input[type="radio"]:checked').closest('.option-wrapper');
            if (selectedWrapper.length) {
                const rb = selectedWrapper.find('input[type="radio"]');
                answerText = rb.val();
                const specify = selectedWrapper.find('.specify-input');
                if (specify.length && specify.val().trim() !== '') {
                    answerText += ': ' + specify.val().trim();
                }
            } else {
                answerText = '';
            }
        } else if (['file', 'upload_image', 'upload_file'].includes(questionType)) {
            const fileInput = $(this).find('input[type="file"]')[0];
            if (fileInput.files.length > 0) {
                formData.append(`file_answers[${questionId}]`, fileInput.files[0]);
                fileInputs.push({ questionId, fileName: fileInput.files[0].name });
                answerText = fileInput.files[0].name;
            }
        }

        if (status === 'final' && isMandatory && !answerText) {
            missingMandatory = true;
            missingQuestions.push(questionText);
            $(this).addClass('border-red-300 bg-red-50/20');
        } else {
            $(this).removeClass('border-red-300 bg-red-50/20');
        }

        if (answerText && !['file', 'upload_image', 'upload_file'].includes(questionType)) {
            answers[questionId] = answerText;
        }
    });

    if (status === 'final' && missingMandatory) {
        showError('The following mandatory questions are missing: ' + missingQuestions.join(', '));
        submitButton.prop('disabled', false).html(originalButtonText);
        return;
    }

    if (Object.keys(answers).length === 0 && fileInputs.length === 0) {
        showError('Please provide at least one answer or file.');
        submitButton.prop('disabled', false).html(originalButtonText);
        return;
    }
    formData.append('answers', JSON.stringify(answers));

    $.ajax({
        url: 'api/api.php?action=save_answers',
        method: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        headers: { 'X-CSRF-Token': csrfToken },
        dataType: 'json'
    }).done(function(response) {
        if (response.success) {
            showSuccess(response.message);
            if (status === 'final') {
                loadSelectedQuestions();
                $('.question-item input[type="file"]').val('');
                $('.file-preview-container').empty();
            }
            $('html, body').animate({ scrollTop: 0 }, 500);
        } else {
            showError('Failed to save answers: ' + response.message);
        }
    }).fail(function(xhr) {
        showError('Failed to save answers: ' + (xhr.responseJSON?.message || 'Server error'));
    }).always(function() {
        submitButton.prop('disabled', false).html(originalButtonText);
    });
}

    // Handle questionnaire form submission (Final)
    questionnaireForm.on('submit', function(e) {
        e.preventDefault();
        saveAnswers('final');
    });

    // Handle Save as Draft
    $('#saveDraftButton').on('click', function() {
        saveAnswers('draft');
    });

    // Note Types unused.

    // Reset form on modal close
    $('#addquestion').on('hidden.bs.modal', function() {
        addQuestionForm[0].reset();
        $('#options_list').empty();
        options = [];
        toggleOptions();
    });

    // Confirmation Modal Helper
    // Upgrade confirmAction to use SweetAlert2 for consistency and better styling
    async function confirmAction(title, message, type = 'warning') {
        const isDanger = title.toLowerCase().includes('unassign') || title.toLowerCase().includes('remove') || title.toLowerCase().includes('delete');
        
        const result = await Swal.fire({
            title: title,
            text: message,
            icon: type,
            showCancelButton: true,
            confirmButtonText: 'Yes, Proceed',
            cancelButtonText: 'Cancel',
            confirmButtonColor: isDanger ? '#e11d48' : '#10b981', // Rose-600 for danger, Emerald-500 for normal
            cancelButtonColor: '#f3f4f6',
            customClass: {
                confirmButton: 'btn-swal-confirm',
                cancelButton: 'btn-swal-cancel'
            },
            hideClass: {
                popup: 'animate__animated animate__fadeOutUp animate__faster'
            }
        });
        return result.isConfirmed;
    }

    $('#questionList').on('click', '.question-card', async function() {
        if (!isAdmin || $(this).hasClass('loading')) return;
        
        const questionId = parseInt($(this).data('question-id'));
        const questionText = $(this).data('question-text');
        const isAssigned = $(this).hasClass('assigned');
        
        if (isAssigned) {
            if (await confirmAction('Confirm Unassign', `Are you sure you want to unassign "${questionText}"?`)) {
                $(this).addClass('loading');
                updateSelectedQuestion(questionId, questionText, false).always(() => {
                    $(this).removeClass('loading');
                });
            }
        } else {
            let shiftOptions = '<option value="">Generic (No specific shift)</option>';
            <?php foreach ($note_types as $type): ?>
                shiftOptions += `<option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['name']); ?></option>`;
            <?php endforeach; ?>
            
            const result = await Swal.fire({
                title: 'Assign Question',
                html: `
                    <p class="mb-4 text-gray-600">Assign "${questionText}" to which shift?</p>
                    <select id="swal-shift-select" class="input p-3 border border-gray-300 rounded-lg w-full focus:ring-2 focus:ring-emerald-500 outline-none">
                        ${shiftOptions}
                    </select>
                `,
                showCancelButton: true,
                confirmButtonText: 'Confirm Assignment',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#ef4444',
                customClass: {
                    confirmButton: 'btn-swal-confirm',
                    cancelButton: 'btn-swal-cancel'
                },
                preConfirm: () => {
                    return document.getElementById('swal-shift-select').value;
                }
            });

            if (result.isConfirmed) {
                $(this).addClass('loading');
                updateSelectedQuestion(questionId, questionText, true, result.value).always(() => {
                    $(this).removeClass('loading');
                });
            }
        }
    });

    $('#selectedQuestionnaires').on('click', '.remove-question', async function() {
        if (!isAdmin) return;
        const questionId = parseInt($(this).data('question-id'));
        const questionText = $(this).closest('.question-item').find('label').text().trim();
        
        if (await confirmAction('Confirm Removal', `Are you sure you want to remove "${questionText}" from assigned questions?`)) {
            updateSelectedQuestion(questionId, questionText, false);
        }
    });

    // Handle search and status filters for questions
    $('#questionSearch').on('input', function() {
        currentPage = 1;
        loadQuestions();
    });

    $('#statusFilter').on('change', function() {
        currentPage = 1;
        loadQuestions();
    });

    // Handle shift filter for patient's assigned questions
    $('#patientShiftFilter').on('change', function() {
        loadSelectedQuestions();
    });

    // Consolidated pagination event listeners
    $('#prevPage').on('click', function() {
        if (currentPage > 1) {
            currentPage--;
            loadQuestions();
        }
    });

    $('#nextPage').on('click', function() {
        const totalPages = Math.ceil(totalQuestions / questionsPerPage);
        if (currentPage < totalPages) {
            currentPage++;
            loadQuestions();
        }
    });

    // Initial loads
    loadUserDetails();
    loadQuestions();
    loadSelectedQuestions();

    $('#question_type').on('change', toggleOptions);
    toggleOptions();
});
</script>