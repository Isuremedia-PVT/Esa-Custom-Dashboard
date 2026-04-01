<?php
// alert.php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('displayAlert')) {
    /**
     * Displays an alert based on session message and type.
     *
     * @return string HTML for the alert, including CSS and JS if a message exists, or empty string if no message.
     */
    function displayAlert(): string
    {
    // Check if message exists in session
    if (!isset($_SESSION['message']) || empty($_SESSION['message'])) {
        return '';
    }

    // Sanitize message and determine alert type
    $message = htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8');
    $alertType = isset($_SESSION['message_type']) && $_SESSION['message_type'] === 'error' ? 'alert-danger' : 'alert-success';

    // Define SVG icon based on alert type
    $iconPath = $alertType === 'alert-danger'
        ? '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>'
        : '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>';

    // Generate HTML for the alert
    $html = <<<HTML

<div class="alert alert-dismissible fade show {$alertType}" role="alert">
    <div class="alert-content">
        <span class="alert-icon">
            <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 20 20" aria-hidden="true" height="1rem" width="1rem" xmlns="http://www.w3.org/2000/svg">
                {$iconPath}
            </svg>
        </span>
        <div>{$message}</div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">
        <span class="close-btn">
            <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 20 20" aria-hidden="true" height="1rem" width="1rem" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
            </svg>
        </span>
    </button>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
});
</script>
HTML;

    // Clear session variables after generating the alert
    unset($_SESSION['message'], $_SESSION['message_type']);

    return $html;
    }
}
?>