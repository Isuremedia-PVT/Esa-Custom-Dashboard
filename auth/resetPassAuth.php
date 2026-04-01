<?php
session_start();
require_once '../controllers/AuthController.php';
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = trim($_POST['password'] ?? '');
    $conPassword = trim($_POST['conPassword'] ?? '');
    $token = trim($_POST['token'] ?? '');
    $csrf_token = trim($_POST['csrf_token'] ?? '');

    // Validate CSRF token
    if (empty($csrf_token) || $csrf_token !== $_SESSION['csrf_token']) {
        $_SESSION['message'] = 'Invalid CSRF token.';
        $_SESSION['message_type'] = 'error';
        header("Location: ../reset-password.php?token=" . urlencode($token));
        exit;
    }

    // Validate input
    if (empty($password) || empty($conPassword)) {
        $_SESSION['message'] = 'All fields are required.';
        $_SESSION['message_type'] = 'error';
    } elseif ($password !== $conPassword) {
        $_SESSION['message'] = 'Passwords do not match.';
        $_SESSION['message_type'] = 'error';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
        $_SESSION['message'] = 'Password must be at least 8 characters long and include one uppercase letter, one lowercase letter, one number, and one special character.';
        $_SESSION['message_type'] = 'error';
    } else {
        $data = resetPassword($token, $password);
        if ($data === true) {
            $_SESSION['message'] = 'Password reset successful. Please log in.';
            $_SESSION['message_type'] = 'success';
            header('Location: ../signin.php');
            exit;
        } else {
            $_SESSION['message'] = $data ?: 'Failed to reset password. The token may be invalid or expired.';
            $_SESSION['message_type'] = 'error';
        }
    }

    header("Location: ../reset-password.php?token=" . urlencode($token));
    exit;
}