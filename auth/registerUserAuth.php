<?php
session_start();
require_once '../controllers/AuthController.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = trim($_POST['name'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $phone  = trim($_POST['phone'] ?? '');
    $age    = trim($_POST['age'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $role   = trim($_POST['role'] ?? '');
    $shift_id = isset($_POST['shift_id']) && $_POST['shift_id'] !== '' ? (int)$_POST['shift_id'] : null;
    $image  = $_FILES['image'] ?? null;
    
    $missingFields = [];

    if (empty($name))   $missingFields[] = 'Name';
    if (empty($email))  $missingFields[] = 'Email';
    if (empty($phone))  $missingFields[] = 'Phone';
    if (empty($age))    $missingFields[] = 'Age';
    if (empty($gender)) $missingFields[] = 'Gender';
    if (empty($role))   $missingFields[] = 'Role';
    
    if (!empty($missingFields)) {
        $_SESSION['message'] = 'Please fill in the following fields: ' . implode(', ', $missingFields) . '.';
        $_SESSION['message_type'] = 'error';
        header("Location: ../new-registration.php");
        exit;
    }

    // Validation
    // if (empty($name) || empty($email) || empty($phone) || empty($age) || empty($gender) || empty($role)) {
    //     $_SESSION['message'] = 'All fields are required.';
    //     $_SESSION['message_type'] = 'error';
    //     header("Location: ../new-registration.php");
    //     exit;
    // }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['message'] = 'Invalid email format.';
        $_SESSION['message_type'] = 'error';
        header("Location: ../new-registration.php");
        exit;
    }

    if (!is_numeric($age) || $age < 0 || $age > 150) {
        $_SESSION['message'] = 'Invalid age.';
        $_SESSION['message_type'] = 'error';
        header("Location: ../new-registration.php");
        exit;
    }

    if (!in_array($gender, ['male', 'female', 'other'])) {
        $_SESSION['message'] = 'Invalid gender selected.';
        $_SESSION['message_type'] = 'error';
        header("Location: ../new-registration.php");
        exit;
    }

    if (!in_array($role, ['staff', 'patient'])) {
        $_SESSION['message'] = 'Invalid user role selected.';
        $_SESSION['message_type'] = 'error';
        header("Location: ../new-registration.php");
        exit;
    }

    if ($role === 'admin') {
        $_SESSION['message'] = 'You cannot register as admin.';
        $_SESSION['message_type'] = 'error';
        header("Location: ../new-registration.php");
        exit;
    }

    // Image validation (optional but recommended)
    if ($image && $image['error'] === 0) {
        $ext = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png'];

        if (!in_array($ext, $allowed)) {
            $_SESSION['message'] = 'Invalid image format. Allowed: JPG, JPEG, PNG.';
            $_SESSION['message_type'] = 'error';
            header("Location: ../new-registration.php");
            exit;
        }
    }
    
    
    $result = registerUser($_POST, $image);

    $_SESSION['message'] = $result['message'];
    $_SESSION['message_type'] = $result['status'] ? 'success' : 'error';
    header("Location: ../new-registration.php");
    exit;
}