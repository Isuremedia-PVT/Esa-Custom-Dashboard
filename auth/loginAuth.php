<?php
session_start();
require_once '../controllers/AuthController.php';
// Check if user is already logged in
if (isset($_SESSION['user']['role'])) {
    switch ($_SESSION['user']['role']) {
        case 'patient':
            header("Location: ../patient-details.php");
            break;
        case 'staff':
            header("Location: ../view_staff.php?id=" . $_SESSION['user']['id']);
            break;
        default:
            header("Location: ../dashboard.php");
            break;
    }
}
// Ensure this script only handles POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../signin.php");
    exit;
}

// Sanitize and validate input
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$password = trim($_POST['password'] ?? '');

// Validate inputs
if (empty($email) || empty($password) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['message'] = "Please provide a valid email and password.";
    $_SESSION['message_type'] = "error";
    header("Location: ../signin.php");
    exit;
}

try {
    $loginResult = login($email, $password);
    
    if ($loginResult['success']) {
        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);
        
        $_SESSION['message'] = $loginResult['message'];
        $_SESSION['message_type'] = "success";

        // Redirect based on user role
        switch ($_SESSION['user']['role']) {
            case 'patient':
                header("Location: ../patient-details.php");
                break;
            case 'staff':
                 header("Location: ../view_staff.php?id=" . $_SESSION['user']['id']);
                break;
            default:
                header("Location: ../dashboard.php");
                break;
        }
        exit;
    } else {
        $_SESSION['message'] = "Invalid email or password.";
        $_SESSION['message_type'] = "error";
        header("Location: ../signin.php");
        exit;
    }
} catch (Exception $e) {
    $_SESSION['message'] = "An error occurred during login. Please try again.";
    $_SESSION['message_type'] = "error";
    header("Location: ../signin.php");
    exit;
}