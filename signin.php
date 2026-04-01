<?php
session_start();

// Check if user is already logged in
if (isset($_SESSION['user']['role'])) {
    switch ($_SESSION['user']['role']) {
        case 'patient':
            header("Location: patient-details.php");
            break;
        case 'staff':
            header("Location: view_staff.php?id=" . $_SESSION['user']['id']);
            break;
        default:
            header("Location: dashboard.php");
            break;
    }
}

// Generate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'Login'; // Set page title for head.php
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
                                    <img src="public/img/logo/logo.png" alt="Cocoon Baby Logo" style="max-height: 60px; width: auto;">
                                </div>
                                <div>
                                </div>
                            <span class="text-white opacity-80 text-sm">Copyright © <?php echo date('Y'); ?> <span class="font-semibold">Cocoonbaby</span></span>
                            </div>
                            <!-- Login Form -->
                            <div class="col-span-2 flex flex-col justify-center items-center bg-white">
                                <div class="xl:min-w-[450px] px-8" style="width: 450px;">
                                    <!-- Display alerts -->
                                    <?php include './views/layouts/alert.php'; ?>
                                    <?= displayAlert() ?>

                                    <div class="mb-8">
                                        <h3 class="mb-1">Welcome back!</h3>
                                        <p>Please enter your credentials to sign in!</p>
                                    </div>
                                    <div>
                                        <form action="./auth/loginAuth.php" method="post" id="loginForm" novalidate>
                                            <!-- CSRF Token -->
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                            <div class="form-container vertical">
                                                <div class="form-item vertical">
                                                    <label class="form-label mb-2" for="email">Email</label>
                                                    <div>
                                                        <input
                                                            class="input"
                                                            type="email"
                                                            id="email"
                                                            name="email"
                                                            autocomplete="email"
                                                            placeholder="Email"
                                                            value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                            required
                                                        >
                                                        <div id="emailError" class="text-red-500 text-sm mt-1 hidden"></div>
                                                    </div>
                                                </div>
                                                <div class="form-item vertical">
                                                    <label class="form-label mb-2" for="password">Password</label>
                                                    <div>
                                                        <span class="input-wrapper">
                                                            <input
                                                                class="input pr-8"
                                                                type="password"
                                                                id="password"
                                                                name="password"
                                                                autocomplete="current-password"
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
                                                <div class="flex justify-between mb-6">
                                                    <a class="text-primary-600 hover:underline" href="./forgot-password.php">Forgot Password?</a>
                                                </div>
                                                <button class="btn btn-solid w-full" type="submit" id="submitButton">Sign In</button>
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
    </div>

    <!-- Core Vendors JS -->
    <script src="public/js/vendors.min.js"></script>
    <!-- Core JS -->
    <script src="public/js/app.min.js"></script>
    <!-- Password Toggle and Form Validation -->
    <script>
        // Define togglePassword globally
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.querySelector('.eye-icon');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
            } else {
                passwordInput.type = 'password';
                eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>';
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('loginForm');
            const submitButton = document.getElementById('submitButton');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            const emailError = document.getElementById('emailError');
            const passwordError = document.getElementById('passwordError');
            const togglePasswordBtn = document.getElementById('togglePasswordBtn');

            // Attach togglePassword event listener
            if (togglePasswordBtn) {
                togglePasswordBtn.addEventListener('click', togglePassword);
            }

            function validateEmail(email) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(email);
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

                // Email validation
                if (!emailInput.value) {
                    showError(emailError, 'Email is required');
                    isValid = false;
                } else if (!validateEmail(emailInput.value)) {
                    showError(emailError, 'Please enter a valid email address');
                    isValid = false;
                } else {
                    hideError(emailError);
                }

                // Password validation
                if (!passwordInput.value) {
                    showError(passwordError, 'Password is required');
                    isValid = false;
                } else if (passwordInput.value.length < 8) {
                    showError(passwordError, 'Password must be at least 8 characters long');
                    isValid = false;
                } else {
                    hideError(passwordError);
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
                emailInput.addEventListener('input', validateForm);
                passwordInput.addEventListener('input', validateForm);
            }
        });
    </script>
</body>
</html>