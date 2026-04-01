<?php
session_start();

// Check if user is logged in and has 'admin' role
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    $_SESSION['message'] = "Access denied. Admin privileges required.";
    $_SESSION['message_type'] = "error";
    header("Location: ./signin.php");
    exit;
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'Edit Patient';
require_once './config/database.php';
require_once './controllers/PatientController.php';
include './views/layouts/alert.php';

$controller = new PatientController($pdo);
$patient = null;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $patient_id = (int)$_GET['id'];
    $patient = $controller->getPatient($patient_id);
    if (!$patient) {
        $_SESSION['message'] = "Patient not found.";
        $_SESSION['message_type'] = "error";
        header("Location: patient.php");
        exit;
    }
    
    // Fetch all active staff
    $stmtStaff = $pdo->query("SELECT id, username FROM users WHERE role = 'staff' AND status = 'active' ORDER BY username ASC");
    $staffMembers = $stmtStaff->fetchAll(PDO::FETCH_ASSOC);

    // Fetch this patient's staff assignments
    $stmtAssigned = $pdo->prepare("SELECT staff_id FROM staff_patient_assignments WHERE patient_id = ?");
    $stmtAssigned->execute([$patient_id]);
    $assignedStaffId = $stmtAssigned->fetchColumn(); 
} else {
    $_SESSION['message'] = "Invalid patient ID.";
    $_SESSION['message_type'] = "error";
    header("Location: patient.php");
    exit;
}

// Handle update action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['message'] = "Invalid security token.";
        $_SESSION['message_type'] = "error";
    } else {
        $result = $controller->updatePatient($_GET['id'], $_POST, $_FILES);
        if (isset($result['errors'])) {
            $_SESSION['message'] = implode(', ', $result['errors']);
            $_SESSION['message_type'] = "error";
        } else {
            $_SESSION['message'] = "Patient updated successfully.";
            $_SESSION['message_type'] = "success";
            header("Location: edit_patient.php?id=" . $patient_id);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include './views/layouts/head.php'; ?>
</head>
<body>
    <div id="root">
        <div class="app-layout-stacked-side flex flex-auto flex-col">
            <div class="flex flex-auto min-w-0">
                <?php include './views/layouts/sidebar.php'; ?>
                <div class="flex flex-col flex-auto min-h-screen min-w-0 relative w-full bg-white border-l border-gray-200 dark:border-gray-700">
                    <?php include './views/layouts/header.php'; ?>
                    <main class="h-full">
                        <div class="page-container relative h-full flex flex-auto flex-col px-4 sm:px-6 md:px-8 py-4 sm:py-6">
                            <div class="container mx-auto">
                                <div class="flex items-center justify-between mb-6">
                                    <div>
                                        <h3 class="text-2xl font-bold text-gray-800">Edit Patient</h3>
                                        <p class="text-gray-500 text-sm">Update patient profile and assignment</p>
                                    </div>
                                    <a href="patient.php" class="btn btn-default btn-sm">
                                        <i class="fas fa-arrow-left mr-2"></i>Back to List
                                    </a>
                                </div>

                                <?= displayAlert() ?>

                                <form action="" method="POST" enctype="multipart/form-data" id="patientForm">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <div class="form-container vertical">
                                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                            <div class="lg:col-span-2">
                                                <div class="card adaptable-card shadow-sm border border-gray-100">
                                                    <div class="card-body p-6">
                                                        <h5 class="flex items-center gap-2 mb-6">
                                                            <div class="p-2 bg-teal-50 text-teal-600 rounded-lg">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                                            </div>
                                                            Basic Information
                                                        </h5>
                                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                            <div class="form-item vertical">
                                                                <label class="form-label mb-2 font-bold text-gray-700">Patient Name</label>
                                                                <input class="input" type="text" name="username" id="username" value="<?= htmlspecialchars($patient['username'] ?? '') ?>" required>
                                                                <span class="text-red-500 text-sm hidden" id="usernameError"></span>
                                                            </div>
                                                            <div class="form-item vertical">
                                                                <label class="form-label mb-2 font-bold text-gray-700">Email Address</label>
                                                                <input readonly class="input bg-gray-50 cursor-not-allowed" type="email" name="email" id="email" value="<?= htmlspecialchars($patient['email'] ?? '') ?>" title="Email cannot be changed">
                                                            </div>
                                                            <div class="form-item vertical">
                                                                <label class="form-label mb-2 font-bold text-gray-700">Phone Number</label>
                                                                <input class="input" type="tel" name="phone" id="phone" value="<?= htmlspecialchars($patient['phone'] ?? '') ?>">
                                                                <span class="text-red-500 text-sm hidden" id="phoneError"></span>
                                                            </div>
                                                            <div class="form-item vertical">
                                                                <label class="form-label mb-2 font-bold text-gray-700">Assigned Staff</label>
                                                                <select name="staff_id" class="input">
                                                                    <option value="">-- No Staff Assigned --</option>
                                                                    <?php foreach($staffMembers as $staff): ?>
                                                                        <option value="<?= $staff['id'] ?>" <?= ($assignedStaffId == $staff['id']) ? 'selected' : '' ?>>
                                                                            <?= htmlspecialchars($staff['username']) ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="lg:col-span-1">
                                                <div class="card adaptable-card shadow-sm border border-gray-100">
                                                    <div class="card-body p-6">
                                                        <h5 class="flex items-center gap-2 mb-6">
                                                            <div class="p-2 bg-teal-50 text-teal-600 rounded-lg">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                            </div>
                                                            Profile Picture
                                                        </h5>
                                                        <div class="flex flex-col items-center">
                                                            <label for="image" class="relative group cursor-pointer block">
                                                                <img id="previewImage" class="w-40 h-40 rounded-full object-cover mb-4 border-4 border-white shadow-md group-hover:opacity-75 transition-opacity" src="<?= !empty($patient['profile_url']) ? htmlspecialchars($patient['profile_url']) : 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0iI2QxZDVkYiI+PHBhdGggZD0iTTEyIDEyYzIuMjEgMCA0LTEuNzkgNC00cy0xLjc5LTQtNC00LTQgMS43OS00IDQgMS43OSA0IDQgNHptMCAyYy0yLjY3IDAtOCAxLjM0LTggNHYyaDE2di0yYzAtMi42Ni01LjMzLTQtOC00eiIvPjwvc3ZnPg==' ?>" alt="" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0iI2QxZDVkYiI+PHBhdGggZD0iTTEyIDEyYzIuMjEgMCA0LTEuNzkgNC00cy0xLjc5LTQtNC00LTQgMS43OS00IDQgMS43OSA0IDQgNHptMCAyYy0yLjY3IDAtOCAxLjM0LTggNHYyaDE2di0yYzAtMi42Ni01LjMzLTQtOC00eiIvPjwvc3ZnPg=='">
                                                                <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity rounded-full bg-black/40">
                                                                    <span class="text-white text-xs font-bold px-3 py-1 border border-white rounded-full"><i class="fas fa-camera mr-1"></i>Change</span>
                                                                </div>
                                                            </label>
                                                            <input type="hidden" name="current_profile_url" value="<?= htmlspecialchars($patient['profile_url'] ?? '') ?>">
                                                            <input class="hidden" type="file" name="image" id="image" accept="image/jpeg,image/png">
                                                            <p class="text-[10px] text-gray-400 mt-2">Recommended: Square image, max 2MB</p>
                                                            <span class="text-red-500 text-sm hidden" id="imageError"></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mt-8 flex justify-end gap-3 sticky bottom-0 bg-white/80 backdrop-blur py-4 border-t">
                                            <a href="patient.php" class="btn btn-default btn-sm px-6">Cancel</a>
                                            <button id="submitButton" class="btn btn-solid btn-sm px-8" type="submit">
                                                <i class="fas fa-save mr-2"></i>Save Changes
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </main>
                    <?php include './views/layouts/footer.php'; ?>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('patientForm');
            const submitButton = document.getElementById('submitButton');
            const usernameInput = document.getElementById('username');
            const imageInput = document.getElementById('image');
            const usernameError = document.getElementById('usernameError');
            const imageError = document.getElementById('imageError');

            // Image preview
            if (imageInput) {
                imageInput.addEventListener('change', function (e) {
                    const file = e.target.files[0];
                    if (file) {
                        if (file.size > 2 * 1024 * 1024) {
                            alert('Image size must be less than 2MB');
                            e.target.value = '';
                            return;
                        }
                        const reader = new FileReader();
                        reader.onload = function (event) {
                            document.getElementById('previewImage').src = event.target.result;
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }

            if (form) {
                form.addEventListener('submit', function (event) {
                    let isValid = true;
                    if (!usernameInput.value.trim()) {
                        usernameError.textContent = 'Name is required.';
                        usernameError.classList.remove('hidden');
                        isValid = false;
                    }
                    if (!isValid) {
                        event.preventDefault();
                    } else {
                        submitButton.disabled = true;
                        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
                    }
                });
            }
        });
    </script>
</body>
</html>