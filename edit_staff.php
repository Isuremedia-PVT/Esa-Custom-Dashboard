<?php
require_once './middleware/auth_check.php';

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

$page_title = 'Edit Staff';
require_once './config/database.php';
require_once './controllers/StaffController.php';
include './views/layouts/alert.php';
$controller = new StaffController($pdo);
$staff = null;

// Fetch shifts for form
$stmt = $pdo->query("SELECT id, name FROM note_types ORDER BY id ASC");
$shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $staff = $controller->getStaffById($_GET['id']);
    if (!$staff) {
        $_SESSION['message'] = "Staff not found.";
        $_SESSION['message_type'] = "error";
        header("Location: staff.php");
        exit;
    }
} else {
    $_SESSION['message'] = "Invalid staff ID.";
    $_SESSION['message_type'] = "error";
    header("Location: staff.php");
    exit;
}

// Handle update action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['message'] = "Invalid CSRF token.";
        $_SESSION['message_type'] = "error";
    } else {
        $data = $_POST;
        $file = $_FILES;
        $result = $controller->updateStaff($_GET['id'], $data, $file);
        if (isset($result['errors'])) {
            $_SESSION['message'] = implode(', ', $result['errors']);
            $_SESSION['message_type'] = "error";
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
                                <h3 class="mb-4">Edit Staff</h3>
                                <form action="" method="POST" enctype="multipart/form-data" id="staffForm">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <div class="form-container vertical">
                                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                                            <div class="lg:col-span-2">
                                                <div class="card adaptable-card !border-b pb-6 py-4 rounded-br-none rounded-bl-none">
                                                    <div class="card-body">
                                                         <?= displayAlert() ?>
                                                        <h5>Basic Information</h5>
                                                        <p class="mb-6">Edit staff information</p>
                                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                            <div class="form-item vertical">
                                                                <label class="form-label mb-2">Name</label>
                                                                <input class="input" type="text" name="username" id="username" value="<?= htmlspecialchars($staff['username'] ?? '') ?>" required>
                                                                <span class="text-red-500 text-sm hidden" id="usernameError"></span>
                                                            </div>
                                                            <div class="form-item vertical">
                                                                <label class="form-label mb-2">Email</label>
                                                                <input readonly class="input" type="email" name="email" id="email" value="<?= htmlspecialchars($staff['email'] ?? '') ?>" required>
                                                                <span class="text-red-500 text-sm hidden" id="emailError"></span>
                                                            </div>
                                                            <div class="form-item vertical">
                                                                <label class="form-label mb-2">Phone</label>
                                                                <input class="input" type="tel" name="phone" id="phone" value="<?= htmlspecialchars($staff['phone'] ?? '') ?>">
                                                                <span class="text-red-500 text-sm hidden" id="phoneError"></span>
                                                            </div>
                                                            <div class="form-item vertical" style="display: none;">
                                                                <input type="hidden" id="shift_id" name="shift_id" value="<?= htmlspecialchars($staff['shift_id'] ?? '') ?>">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="lg:col-span-1">
                                                <div class="card adaptable-card mb-4">
                                                    <div class="card-body">
                                                        <h5>Profile Image</h5>
                                                        <p class="mb-6">Update profile image</p>
                                                        <div class="form-item vertical">
                                                            <label class="form-label"></label>
                                                            <div>
                                                                <div class="upload upload-draggable hover:border-primary-600">
                                                                    <input class="upload-input draggable" type="file" name="image" id="image" accept="image/jpeg,image/png">
                                                                    <div class="my-16 text-center">
                                                                        <img src="<?= !empty($staff['profile_url']) ? htmlspecialchars($staff['profile_url']) : 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0iI2QxZDVkYiI+PHBhdGggZD0iTTEyIDEyYzIuMjEgMCA0LTEuNzkgNC00cy0xLjc5LTQtNC00LTQgMS43OS00IDQgMS43OSA0IDQgNHptMCAyYy0yLjY3IDAtOCAxLjM0LTggNHYyaDE2di0yYzAtMi42Ni01LjMzLTQtOC00eiIvPjwvc3ZnPg==' ?>" alt="" class="mx-auto w-32 h-32 object-cover rounded-full" id="previewImage" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0iI2QxZDVkYiI+PHBhdGggZD0iTTEyIDEyYzIuMjEgMCA0LTEuNzkgNC00cy0xLjc5LTQtNC00LTQgMS43OS00IDQgMS43OSA0IDQgNHptMCAyYy0yLjY3IDAtOCAxLjM0LTggNHYyaDE2di0yYzAtMi42Ni01LjMzLTQtOC00eiIvPjwvc3ZnPg=='">
                                                                        <input type="hidden" name="current_profile_url" value="<?= htmlspecialchars($staff['profile_url'] ?? '') ?>">
                                                                        <p class="font-semibold">
                                                                            <span class="text-gray-800 dark:text-white">Drop new image here, or</span>
                                                                            <span class="text-blue-500">browse</span>
                                                                        </p>
                                                                        <p class="mt-1 opacity-60 dark:text-white">Support: jpeg, png</p>
                                                                        <span class="text-red-500 text-sm hidden" id="imageError"></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="stickyFooter" class="sticky -bottom-1 -mx-8 px-8 flex items-center justify-end py-4">
                                            <div class="md:flex items-center">
                                                <button class="btn btn-default btn-sm ltr:mr-2 rtl:ml-2" type="button" onclick="window.location.href='staff.php'">Cancel</button>
                                                <button class="btn btn-solid btn-sm" type="submit" id="submitButton">
                                                    <span class="flex items-center justify-center">
                                                        <span class="text-lg">
                                                            <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 1024 1024" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                                                <path d="M893.3 293.3L730.7 130.7c-7.5-7.5-16.7-13-26.7-16V112H144c-17.7 0-32 14.3-32 32v736c0 17.7 14.3 32 32 32h736c17.7 0 32-14.3 32-32V338.5c0-17-6.7-33.2-18.7-45.2zM384 184h256v104H384V184zm456 656H184V184h136v136c0 17.7 14.3 32 32 32h320c17.7 0 32-14.3 32-32V205.8l136 136V840zM512 442c-79.5 0-144 64.5-144 144s64.5 144 144 144 144-64.5 144-144-64.5-144-144-144zm0 224c-44.2 0-80-35.8-80-80s35.8-80 80-80 80 35.8 80 80-35.8 80-80 80z"></path>
                                                            </svg>
                                                        </span>
                                                        <span class="ltr:ml-1 rtl:mr-1">Save</span>
                                                    </span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </main>
                        <?php include './views/layouts/footer.php'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        // Form validation and submit button handling
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('staffForm');
            const submitButton = document.getElementById('submitButton');
            const usernameInput = document.getElementById('username');
            const emailInput = document.getElementById('email');
            const phoneInput = document.getElementById('phone');
            const shiftInput = document.getElementById('shift_id');
            const imageInput = document.getElementById('image');
            const usernameError = document.getElementById('usernameError');
            const emailError = document.getElementById('emailError');
            const phoneError = document.getElementById('phoneError');
            const shiftError = document.getElementById('shiftError');
            const imageError = document.getElementById('imageError');

            // Image preview with client-side validation
            imageInput.addEventListener('change', function (e) {
                const file = e.target.files[0];
                if (file) {
                    const allowedTypes = ['image/jpeg', 'image/png'];
                    const maxSize = 2 * 1024 * 1024; // 2MB
                    if (!allowedTypes.includes(file.type)) {
                        imageError.textContent = 'Only JPEG or PNG files are allowed.';
                        imageError.classList.remove('hidden');
                        e.target.value = '';
                        return;
                    }
                    if (file.size > maxSize) {
                        imageError.textContent = 'File size must be less than 2MB.';
                        imageError.classList.remove('hidden');
                        e.target.value = '';
                        return;
                    }
                    imageError.classList.add('hidden');
                    const reader = new FileReader();
                    reader.onload = function (event) {
                        document.getElementById('previewImage').src = event.target.result;
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Form validation function
            function validateForm() {
                let isValid = true;

                // Reset error messages
                usernameError.classList.add('hidden');
                emailError.classList.add('hidden');
                phoneError.classList.add('hidden');
                shiftError.classList.add('hidden');
                imageError.classList.add('hidden');

                // Username validation
                if (!usernameInput.value.trim()) {
                    usernameError.textContent = 'Name is required.';
                    usernameError.classList.remove('hidden');
                    isValid = false;
                } else if (usernameInput.value.length < 2) {
                    usernameError.textContent = 'Name must be at least 2 characters long.';
                    usernameError.classList.remove('hidden');
                    isValid = false;
                }

                // Email validation (even though readonly, validate for completeness)
                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailPattern.test(emailInput.value)) {
                    emailError.textContent = 'Please enter a valid email address.';
                    emailError.classList.remove('hidden');
                    isValid = false;
                }

                // Phone validation (if provided)
                if (phoneInput.value.trim()) {
                    const phonePattern = /^\+?[\d\s-]{10,}$/;
                    if (!phonePattern.test(phoneInput.value)) {
                        phoneError.textContent = 'Please enter a valid phone number.';
                        phoneError.classList.remove('hidden');
                        isValid = false;
                    }
                }

                // Shift validation - disabled for staff per user request
                // if (!shiftInput.value) {
                //     shiftError.textContent = 'Please assign a shift.';
                //     shiftError.classList.remove('hidden');
                //     isValid = false;
                // }

                return isValid;
            }

            // Form submit handling
            if (form && submitButton) {
                form.addEventListener('submit', function (event) {
                    if (!validateForm()) {
                        event.preventDefault();
                    } else {
                        submitButton.disabled = true;
                        submitButton.querySelector('span:nth-child(2)').textContent = 'Sending...';
                        submitButton.classList.add('opacity-50', 'cursor-not-allowed');
                    }
                });
            }
        });
    </script>
</body>
</html>