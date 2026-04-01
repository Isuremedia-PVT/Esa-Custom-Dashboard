<?php
session_start();
require_once '../controllers/AuthController.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['userEmail'] ?? '');

    if (empty($email)) {
        $_SESSION['message'] = 'Email is required.';
        $_SESSION['message_type'] = 'error';
    } elseif (forgetPassword($email)) {
        $_SESSION['message'] = 'Password reset link sent to your email.';
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = 'Invalid email or failed to send email.';
        $_SESSION['message_type'] = 'error';
    }

    header("Location: ../forgot-password.php");
    exit;
}