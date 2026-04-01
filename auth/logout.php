<?php
session_start();

// Check if user is logged in (optional, to prevent unauthorized access)
if (!isset($_SESSION['user'])) {
    header("Location: ../signin.php");
    exit;
}

// Set logout message (optional, for displaying on signin page)
$_SESSION['message'] = "You have been successfully logged out.";
$_SESSION['message_type'] = "success";

// Clear all session data
$_SESSION = [];

// Destroy the session
session_destroy();

// Clear session cookie to ensure client-side session is invalidated
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Redirect to signin page
header("Location: ../signin.php");
exit;
?>