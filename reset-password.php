<?php
session_start();

// Generate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'Reset Password';
include './views/layouts/head.php';
include './views/layouts/alert.php'; // Include alert.php for displayAlert()
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include './views/layouts/head.php'; // Include head once ?>
</head>
<body>
    <!-- App Start -->
    <div id="root">
        <!-- App Layout -->
        <div class="app-layout-blank flex flex-auto flex-col h-[100vh]">
            <div class="h-full flex flex-auto flex-col justify-between">
                <main class="h-full">
                    <div class="page-container relative h-full flex flex-auto flex-col">
                        <div class="grid lg:grid-cols-3 h-full">
                            <!-- Left Sidebar (Visible on LG and above) -->
                            <div class="bg-no-repeat bg-cover py-6 px-16 flex-col justify-between hidden lg:flex" style="background-image: url('public/img/others/bg.webp');">
                                <div class="logo w-[250px]">
                                    <img src="public/img/logo/logo.png" alt="Elstar logo">
                                </div>
                                <div>
                                </div>
                                <a href="#" class="text-white">Copyright © <?php echo date('Y'); ?> <span class="font-semibold">Cocoonbaby</span></a>
                            </div>
                            <!-- Reset Password Form -->
                            <div class="col-span-2 flex flex-col justify-center items-center bg-white">
                                <div class="w-[450px] px-8" style="width: 450px;">
                                    <!-- Display alerts using displayAlert() -->
                                    <?= displayAlert() ?>
                                    <div class="mb-8">
                                        <h3 class="mb-1">Set New Password</h3>
                                        <p>Your new password must be different from your previous password</p>
                                    </div>
                                    <div>
                                        <form id="resetPasswordForm" action="./auth/resetPassAuth.php" method="post" novalidate>
                                            <!-- CSRF Token -->
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                            <!-- Token from URL -->
                                            <input type="hidden" name="token" value="<?= htmlspecialchars($_GET['token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                            <div class="form-container vertical">
                                                <div class="form-item vertical">
                                                    <label class="form-label mb-2" for="password">Password</label>
                                                    <div>
                                                        <span class="input-wrapper">
                                                            <input
                                                                class="input pr-8"
                                                                type="password"
                                                                id="password"
                                                                name="password"
                                                                autocomplete="new-password"
                                                                placeholder="Password"
                                                                required
                                                            >
                                                            <div class="input-suffix-end">
                                                                <button type="button" class="cursor-pointer text-xl password-toggle" aria-label="Toggle password visibility" id="togglePasswordBtn">
                                                                    <svg
                                                                        stroke="currentColor"
                                                                        fill="none"
                                                                        stroke-width="2"
                                                                        viewBox="0 0 24 24"
                                                                        aria-hidden="true"
                                                                        height="1em"
                                                                        width="1em"
                                                                        xmlns="http://www.w3.org/2000/svg"
                                                                        class="eye-icon"
                                                                    >
                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                                                                    </svg>
                                                                </button>
                                                            </div>
                                                        </span>
                                                        <div id="passwordError" class="text-red-500 text-sm mt-1 hidden"></div>
                                                    </div>
                                                </div>
                                                <div class="form-item vertical">
                                                    <label class="form-label mb-2" for="conPassword">Confirm Password</label>
                                                    <div>
                                                        <span class="input-wrapper">
                                                            <input
                                                                class="input pr-8"
                                                                type="password"
                                                                id="conPassword"
                                                                name="conPassword"
                                                                autocomplete="new-password"
                                                                placeholder="Confirm Password"
                                                                required
                                                            >
                                                            <div class="input-suffix-end">
                                                                <button type="button" class="cursor-pointer text-xl password-toggle" aria-label="Toggle password visibility" id="toggleConPasswordBtn">
                                                                    <svg
                                                                        stroke="currentColor"
                                                                        fill="none"
                                                                        stroke-width="2"
                                                                        viewBox="0 0 24 24"
                                                                        aria-hidden="true"
                                                                        height="1em"
                                                                        width="1em"
                                                                        xmlns="http://www.w3.org/2000/svg"
                                                                        class="eye-icon"
                                                                    >
                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                                                                    </svg>
                                                                </button>
                                                            </div>
                                                        </span>
                                                        <div id="conPasswordError" class="text-red-500 text-sm mt-1 hidden"></div>
                                                    </div>
                                                </div>
                                                <button id="submitButton" class="btn btn-solid w-full" type="submit">Submit</button>
                                                <div class="mt-4 text-center">
                                                    <span>Back to </span>
                                                    <a class="text-primary-600 hover:underline" href="signin.php">Sign In</a>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>

        <!-- Core Vendors JS -->
        <script src="public/js/vendors.min.js"></script>
        <!-- Core JS -->
        <script src="public/js/app.min.js"></script>
        <!-- Toggle Eye and Form Validation -->
        <script>
            // Toggle password visibility function
            function togglePassword(inputId, eyeIcon) {
                const input = document.getElementById(inputId);
                if (input.type === 'password') {
                    input.type = 'text';
                    eyeIcon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>`;
                } else {
                    input.type = 'password';
                    eyeIcon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>`;
                }
            }

            document.addEventListener('DOMContentLoaded', function () {
                const form = document.getElementById('resetPasswordForm');
                const submitButton = document.getElementById('submitButton');
                const passwordInput = document.getElementById('password');
                const conPasswordInput = document.getElementById('conPassword');
                const passwordError = document.getElementById('passwordError');
                const conPasswordError = document.getElementById('conPasswordError');
                const togglePasswordBtn = document.getElementById('togglePasswordBtn');
                const toggleConPasswordBtn = document.getElementById('toggleConPasswordBtn');

                // Attach toggle password event listeners
                if (togglePasswordBtn) {
                    togglePasswordBtn.addEventListener('click', () => togglePassword('password', togglePasswordBtn.querySelector('.eye-icon')));
                }
                if (toggleConPasswordBtn) {
                    toggleConPasswordBtn.addEventListener('click', () => togglePassword('conPassword', toggleConPasswordBtn.querySelector('.eye-icon')));
                }

                function validatePassword(password) {
                    const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
                    return passwordRegex.test(password);
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

                    // Password validation
                    if (!passwordInput.value) {
                        showError(passwordError, 'Password is required');
                        isValid = false;
                    } else if (!validatePassword(passwordInput.value)) {
                        showError(passwordError, 'Password must be at least 8 characters long and include one uppercase, one lowercase, one number, and one special character');
                        isValid = false;
                    } else {
                        hideError(passwordError);
                    }

                    // Confirm password validation
                    if (!conPasswordInput.value) {
                        showError(conPasswordError, 'Confirm password is required');
                        isValid = false;
                    } else if (conPasswordInput.value !== passwordInput.value) {
                        showError(conPasswordError, 'Passwords do not match');
                        isValid = false;
                    } else {
                        hideError(conPasswordError);
                    }

                    return isValid;
                }

                if (form && submitButton) {
                    form.addEventListener('submit', function (event) {
                        if (!validateForm()) {
                            event.preventDefault();
                        } else {
                            submitButton.disabled = true;
                            submitButton.textContent = 'Processing...';
                            submitButton.classList.add('opacity-50', 'cursor-not-allowed');
                        }
                    });

                    // Real-time validation
                    passwordInput.addEventListener('input', validateForm);
                    conPasswordInput.addEventListener('input', validateForm);
                }
            });
        </script>
    </body>
</html>