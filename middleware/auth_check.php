<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$base_dir = __DIR__ . '/..';
require_once $base_dir . '/config/database.php';

// Helper for relative path to signin.php
$current_script = $_SERVER['SCRIPT_NAME'];
$is_api = (strpos($current_script, '/api/') !== false);
$signin_path = $is_api ? '../signin.php' : './signin.php';

if (isset($_SESSION['user']['id'])) {
    try {
        $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user']['id']]);
        $status = $stmt->fetchColumn();

        if ($status !== 'active') {
            session_unset();
            session_destroy();
            
            if ($is_api) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Your account is deactivated.']);
                exit;
            }

            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['message'] = "Your account is deactivated. Please contact the admin.";
            $_SESSION['message_type'] = "error";
            header("Location: " . $signin_path);
            exit;
        }
    } catch (PDOException $e) {
        error_log("Auth check error: " . $e->getMessage());
    }
}

