<?php
session_start();

// Generate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'Forgot Password';
include './views/layouts/head.php';
include './views/layouts/alert.php';
?>
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
                            <!-- Forgot Password Form -->
                            <div class="col-span-2 flex flex-col justify-center items-center bg-white">
                                <div class="min-w-[450px] px-8" style="width: 450px;">
                                    <!-- Display alerts using displayAlert() -->
                                    <?= displayAlert() ?>
                                    <div class="mb-8">
                                        <h3 class="mb-1">Forgot Password</h3>
                                        <p>Please enter your email address to receive a verification code</p>
                                    </div>
                                    <div>
                                        <form id="forgotPasswordForm" action="./auth/forgotPassAuth.php" method="post" novalidate>
                                            <!-- CSRF Token -->
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                            <div class="form-container vertical">
                                                <div class="form-item vertical">
                                                    <label class="form-label mb-2" for="userEmail">Email</label>
                                                    <div>
                                                        <input
                                                            class="input"
                                                            type="email"
                                                            id="userEmail"
                                                            name="userEmail"
                                                            autocomplete="email"
                                                            placeholder="Email"
                                                            value="<?= htmlspecialchars($_POST['userEmail'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                            required
                                                        >
                                                        <div id="emailError" class="text-red-500 text-sm mt-1 hidden"></div>
                                                    </div>
                                                </div>
                                                <button id="submitButton" class="btn btn-solid w-full" type="submit">Send Email</button>
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
        <!-- Form Validation and Submission Handling -->
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const form = document.getElementById('forgotPasswordForm');
                const submitButton = document.getElementById('submitButton');
                const emailInput = document.getElementById('userEmail');
                const emailError = document.getElementById('emailError');

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
                }
            });
        </script>
    </body>
</html>