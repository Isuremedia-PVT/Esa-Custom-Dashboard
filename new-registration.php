<?php
require_once './middleware/auth_check.php';

// Check if user is logged in and has 'admin' role
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    $_SESSION['message'] = "Access denied. Admin privileges required.";
    $_SESSION['message_type'] = "error";
    header("Location: ./signin.php");
    exit;
}

$role = isset($_GET['role']) && in_array($_GET['role'], ['staff', 'patient']) ? $_GET['role'] : '';

require_once './config/database.php';
$stmt = $pdo->query("SELECT id, name FROM note_types ORDER BY id ASC");
$shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmtStaff = $pdo->query("SELECT id, username FROM users WHERE role = 'staff' AND status = 'active' ORDER BY username ASC");
$staffMembers = $stmtStaff->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'New Staff';
include './views/layouts/head.php';
include './views/layouts/alert.php'; // Include alert.php for displayAlert()
?>

<!-- App Start-->
<div id="root">
    <!-- App Layout-->
    <div class="app-layout-stacked-side flex flex-auto flex-col">
        <div class="flex flex-auto min-w-0">
            <!-- Stacked Side Nav start -->
            <?php include './views/layouts/sidebar.php'; ?>
            <!-- Stacked Side Nav end -->

            <!-- Header Nav start -->
            <div class="flex flex-col flex-auto min-h-screen min-w-0 relative w-full bg-white border-l border-gray-200 dark:border-gray-700">
                <?php include './views/layouts/header.php'; ?>
                <!-- Popup -->
                <div class="h-full flex flex-auto flex-col justify-between">
                    <!-- Content start -->
                    <main class="h-full">
                        <div class="page-container relative h-full flex flex-auto flex-col px-4 sm:px-6 md:px-8 py-4 sm:py-6">
                            <div class="container mx-auto">
                                <h3 class="mb-4">Add New User</h3>
                                <!-- Display alerts using displayAlert() -->
                                <?= displayAlert() ?>
                                
                                <form action="./auth/registerUserAuth.php" method="POST" enctype="multipart/form-data" id="userForm">
                                    <div class="form-container vertical">
                                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                                            <div class="lg:col-span-2">
                                                <div class="card adaptable-card !border-b pb-6 py-4 rounded-br-none rounded-bl-none">
                                                    <div class="card-body">
                                                        <h5>Basic Information</h5>
                                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                            <div class="form-item vertical">
                                                                <label class="form-label mb-2">Name</label>
                                                                <div>
                                                                    <input
                                                                        class="input"
                                                                        type="text"
                                                                        name="name"
                                                                        autocomplete="off"
                                                                        placeholder="Name"
                                                                        value=""
                                                                        required
                                                                    >
                                                                    <div id="nameError" class="text-red-500 text-sm mt-1 hidden"></div>
                                                                </div>
                                                            </div>
                                                            <div class="form-item vertical">
                                                                <label class="form-label mb-2">Email</label>
                                                                <input
                                                                    class="input"
                                                                    type="email"
                                                                    name="email"
                                                                    autocomplete="off"
                                                                    placeholder="Email"
                                                                    value=""
                                                                    required
                                                                >
                                                                <div id="emailError" class="text-red-500 text-sm mt-1 hidden"></div>
                                                            </div>
                                                            <div class="form-item vertical">
                                                                <label class="form-label mb-2">Phone</label>
                                                                <input
                                                                    class="input"
                                                                    type="tel"
                                                                    name="phone"
                                                                    autocomplete="off"
                                                                    placeholder="Phone"
                                                                    value=""
                                                                >
                                                                <div id="phoneError" class="text-red-500 text-sm mt-1 hidden"></div>
                                                            </div>
                                                            <div class="form-item vertical">
                                                                <label class="form-label mb-2">Age</label>
                                                                <input
                                                                    class="input"
                                                                    type="number"
                                                                    name="age"
                                                                    autocomplete="off"
                                                                    placeholder="Age"
                                                                    value=""
                                                                    min="1"
                                                                >
                                                                <div id="ageError" class="text-red-500 text-sm mt-1 hidden"></div>
                                                            </div>
                                                            <div class="form-item vertical">
                                                                <label class="form-label mb-2" for="gender">Gender</label>
                                                                <select class="input" id="gender" name="gender" required>
                                                                    <option value="" disabled selected>Choose a Gender</option>
                                                                    <option value="male">Male</option>
                                                                    <option value="female">Female</option>
                                                                    <option value="other">Other</option>
                                                                </select>
                                                                <div id="genderError" class="text-red-500 text-sm mt-1 hidden"></div>
                                                            </div>
                                                            <div class="form-item vertical">
                                                                <label class="form-label mb-2" for="role">Staff/Patient</label>
                                                                <select class="input" id="role" name="role" required>
                                                                    <option value="" disabled <?php echo $role === '' ? 'selected' : ''; ?>>Choose type of User</option>
                                                                    <option value="staff" <?php echo $role === 'staff' ? 'selected' : ''; ?>>Staff</option>
                                                                    <option value="patient" <?php echo $role === 'patient' ? 'selected' : ''; ?>>Patient</option>
                                                                </select>
                                                                <div id="roleError" class="text-red-500 text-sm mt-1 hidden"></div>
                                                            </div>
                                                            <div class="form-item vertical" id="shiftContainer" style="display: none;">
                                                                <label class="form-label mb-2" for="shift_id">Assign Shift</label>
                                                                <select class="input" id="shift_id" name="shift_id">
                                                                    <option value="" disabled selected>Choose a Shift</option>
                                                                    <?php foreach ($shifts as $shift): ?>
                                                                        <option value="<?= $shift['id'] ?>"><?= htmlspecialchars($shift['name']) ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                                <div id="shiftError" class="text-red-500 text-sm mt-1 hidden"></div>
                                                            </div>
                                                            <div class="form-item vertical" id="staffContainer" style="display: <?php echo $role === 'patient' ? 'block' : 'none'; ?>;">
                                                                <label class="form-label mb-2" for="staff_id">Assign Staff</label>
                                                                <select class="input" id="staff_id" name="staff_id">
                                                                    <option value="" disabled selected>Choose Staff (Optional)</option>
                                                                    <?php foreach ($staffMembers as $s): ?>
                                                                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['username']) ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                                <div id="staffError" class="text-red-500 text-sm mt-1 hidden"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="lg:col-span-1">
                                                <div class="card adaptable-card mb-4">
                                                    <div class="card-body">
                                                        <h5>Profile Image</h5>
                                                        <p class="mb-6">Add or change image for the user</p>
                                                        <div class="form-item vertical">
                                                            <label class="form-label"></label>
                                                            <div>
                                                                <div class="upload upload-draggable hover:border-primary-600">
                                                                    <input
                                                                        class="upload-input draggable"
                                                                        type="file"
                                                                        title=""
                                                                        value=""
                                                                        id="imageUpload"
                                                                        name="image"
                                                                        accept="image/jpeg,image/png"
                                                                    >
                                                                    <div class="my-16 text-center">
                                                                        <img src="public/img/others/upload.png" alt="" class="mx-auto w-32 h-32 object-cover rounded-full" id="previewImage">
                                                                        <p class="font-semibold">
                                                                            <span class="text-gray-800 dark:text-white">Drop your image here, or</span>
                                                                            <span class="text-blue-500">browse</span>
                                                                        </p>
                                                                        <p class="mt-1 opacity-60 dark:text-white">Support: jpeg, png</p>
                                                                    </div>
                                                                </div>
                                                                <div id="imageError" class="text-red-500 text-sm mt-1 hidden"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="stickyFooter" class="sticky -bottom-1 -mx-8 px-8 flex items-center justify-end py-4">
                                            <div class="md:flex items-center">
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
                        </div>
                    </main>
                    <?php include './views/layouts/footer.php'; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    // document.getElementById('userForm').addEventListener('submit', function() {
    //     const submitButton = document.getElementById('submitButton');
    //     submitButton.disabled = true;
    //     submitButton.innerHTML = `
    //         <span class="flex items-center justify-center">
    //             <span class="text-lg">
    //                 <svg class="animate-spin h-5 w-5 mr-2" viewBox="0 0 24 24">
    //                     <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
    //                     <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    //                 </svg>
    //             </span>
    //             <span>Saving...</span>
    //         </span>`;
    // });
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('userForm');
        const submitButton = document.getElementById('submitButton');
        const nameInput = document.querySelector('input[name="name"]');
        const emailInput = document.querySelector('input[name="email"]');
        const phoneInput = document.querySelector('input[name="phone"]');
        const ageInput = document.querySelector('input[name="age"]');
        const genderInput = document.getElementById('gender');
        const roleInput = document.getElementById('role');
        const imageInput = document.getElementById('imageUpload');

        // Error message containers
        const nameError = document.getElementById('nameError');
        const emailError = document.getElementById('emailError');
        const phoneError = document.getElementById('phoneError');
        const ageError = document.getElementById('ageError');
        const genderError = document.getElementById('genderError');
        const roleError = document.getElementById('roleError');
        const imageError = document.getElementById('imageError');

        function validateEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        function validatePhone(phone) {
            const phoneRegex = /^\+?\d{10,15}$/;
            return phoneRegex.test(phone);
        }

        function showError(element, message) {
            element.textContent = message;
            element.classList.remove('hidden');
        }

        function hideError(element) {
            element.textContent = '';
            element.classList.add('hidden');
        }

        function validateForm() {
            let isValid = true;

            // Name validation
            if (!nameInput.value.trim()) {
                showError(nameError, 'Name is required');
                isValid = false;
            } else {
                hideError(nameError);
            }

            // Email validation
            if (!emailInput.value.trim()) {
                showError(emailError, 'Email is required');
                isValid = false;
            } else if (!validateEmail(emailInput.value.trim())) {
                showError(emailError, 'Please enter a valid email address');
                isValid = false;
            } else {
                hideError(emailError);
            }

            // Phone validation (optional)
            if (phoneInput.value.trim() && !validatePhone(phoneInput.value.trim())) {
                showError(phoneError, 'Please enter a valid phone number (10-15 digits)');
                isValid = false;
            } else {
                hideError(phoneError);
            }

            // Age validation (optional)
            if (ageInput.value && (isNaN(ageInput.value) || ageInput.value < 1)) {
                showError(ageError, 'Age must be a positive number');
                isValid = false;
            } else {
                hideError(ageError);
            }

            // Gender validation
            if (!genderInput.value) {
                showError(genderError, 'Gender is required');
                isValid = false;
            } else {
                hideError(genderError);
            }

            // Role validation
            if (!roleInput.value) {
                showError(roleError, 'Role is required');
                isValid = false;
            } else {
                hideError(roleError);
            }

            // Shift Validation logic - disabled for staff per user request
            // const shiftInput = document.getElementById('shift_id');
            // const shiftError = document.getElementById('shiftError');
            // if (roleInput.value === 'staff' && !shiftInput.value) {
            //     showError(shiftError, 'Shift assigned is required for staff');
            //     isValid = false;
            // } else if (shiftError) {
            //     hideError(shiftError);
            // }

            // Image validation (optional)
            if (imageInput.files.length > 0) {
                const file = imageInput.files[0];
                const validTypes = ['image/jpeg', 'image/png'];
                if (!validTypes.includes(file.type)) {
                    showError(imageError, 'Please upload a valid image (JPEG or PNG)');
                    isValid = false;
                } else {
                    hideError(imageError);
                }
            } else {
                hideError(imageError);
            }

            return isValid;
        }

        if (form && submitButton) {
            form.addEventListener('submit', function (event) {
                if (!validateForm()) {
                    event.preventDefault();
                } else {
                    submitButton.disabled = true;
                    submitButton.textContent = 'Sending...';
                    submitButton.classList.add('opacity-50', 'cursor-not-allowed');
                }
            });

            // Real-time validation
            nameInput.addEventListener('input', validateForm);
            emailInput.addEventListener('input', validateForm);
            phoneInput.addEventListener('input', validateForm);
            ageInput.addEventListener('input', validateForm);
            genderInput.addEventListener('change', validateForm);
            roleInput.addEventListener('change', function(e) {
                const shiftContainer = document.getElementById('shiftContainer');
                const staffContainer = document.getElementById('staffContainer');
                
                if (e.target.value === 'staff') {
                    shiftContainer.style.display = 'none';
                    staffContainer.style.display = 'none';
                    document.getElementById('staff_id').value = '';
                } else if (e.target.value === 'patient') {
                    shiftContainer.style.display = 'none';
                    document.getElementById('shift_id').value = '';
                    staffContainer.style.display = 'block';
                } else {
                    shiftContainer.style.display = 'none';
                    staffContainer.style.display = 'none';
                    document.getElementById('shift_id').value = '';
                    document.getElementById('staff_id').value = '';
                }
                validateForm();
            });
            imageInput.addEventListener('change', function (e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function (event) {
                        document.getElementById('previewImage').src = event.target.result;
                    };
                    reader.readAsDataURL(file);
                }
                validateForm();
            });
        }
    });
</script>
</body>
</html>