<?php
// Start session only if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/EmailController.php';

// ==== SIGNUP ====
function signup($username, $email, $password) {
    global $pdo;

    $hashed = md5($password); // Note: not safe for production
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
    return $stmt->execute([$username, $email, $hashed]);
}

function login($email, $password) {
    global $pdo;
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $email = strtolower(trim($email));
    $hashed = md5($password); // Note: MD5 is insecure; consider using password_hash() and password_verify()
    error_log("Email: $email, Input Hashed Password: $hashed");

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = ? AND password = ?");
        $stmt->execute([$email, $hashed]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            // Debug: Check stored password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE LOWER(email) = ?");
            $stmt->execute([$email]);
            $storedPassword = $stmt->fetchColumn();
            error_log("Stored Password for $email: $storedPassword");
            return ['success' => false, 'message' => 'Invalid credentials.'];
        }

        // Check account status
        if ($user['status'] !== 'active') {
            error_log("Account deactivated for email: $email");
            // Store the error message in the session
            $_SESSION['message'] = 'Your account is deactivated. Please contact the admin.';
            $_SESSION['message_type'] = 'error';
            return ['success' => false, 'message' => 'Your account is deactivated. Please contact the admin.'];
        }

        // Store minimal user data in session
        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'] // Ensure 'role' column exists
        ];

        // Generate CSRF token if not exists
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        error_log("Login successful for email: $email");
        return ['success' => true, 'message' => 'Login successful.'];
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        // Store database error in session
        $_SESSION['message'] = 'Database error occurred.';
        $_SESSION['message_type'] = 'error';
        return ['success' => false, 'message' => 'Database error occurred.'];
    }
}



// ==== FORGOT PASSWORD (generate reset token) ====
function generateResetToken(string $email) {
    global $pdo;

    $token = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', time() + 3600); // valid for 1 hour

    $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, token_expiry = ? WHERE email = ?");
    $success = $stmt->execute([$token, $expiry, $email]);

    return $success ? $token : false;
}

// ==== VALIDATE RESET TOKEN ====
function isValidToken($token) {
    global $pdo;
    $currentTime = date('Y-m-d H:i:s', time());
    $stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = ? AND token_expiry > ?");
    $stmt->execute([$token, $currentTime]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// ==== RESET PASSWORD ====
function resetPassword($token, $newPassword) {
    global $pdo;

    $user = isValidToken($token);
    if (!$user) return false;
    
    $hashed = md5($newPassword);
    $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, token_expiry = NULL WHERE id = ?");
    return $stmt->execute([$hashed, $user['id']]);
}

// ==== FORGOT PASSWORD ====
function forgetPassword( $email) {
    global $pdo;

    // Check user existence
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if (!$stmt->fetch()) {
        return false;
    }

    // Generate and save token
    $token = generateResetToken($email);
    if (!$token) return false;
    
    $resetLink = "https://cocoonbaby.online/reset-password.php?token=$token";
    
    $emailCtrl = new EmailController();
    $result = $emailCtrl->sendPasswordResetEmail($email, $resetLink);
    
    return $result['success'];


    // Send reset email
    // return sendResetEmail($email, $token);
}



function registerUser($data, $file)
{
    global $pdo;

    $name = trim($data['name']);
    $email = trim($data['email']);
    $phone = trim($data['phone']);
    $age = intval($data['age']);
    $gender = $data['gender'];
    $role = $data['role'];
    $shift_id = ($role === 'staff' && isset($data['shift_id'])) ? (int)$data['shift_id'] : null;
    $status = 'active'; // default status
    $createdAt = date('Y-m-d H:i:s');
    $updatedAt = $createdAt;

    // Prevent admin registration
    if (strtolower($role) === 'admin') {
        return ['status' => false, 'message' => 'Admin registration is not allowed.'];
    }

    // Check for duplicate email or phone
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
    $stmt->execute([$email, $phone]);
    if ($stmt->fetch()) {
        return ['status' => false, 'message' => 'Email or phone already exists.'];
    }

    // Auto-generate password like: "Firstname@2001"
    $firstname = explode(' ', $name)[0];
    $yearOfBirth = date('Y') - $age;
    $rawPassword = $firstname . '@' . $yearOfBirth;
    $hashedPassword = md5($rawPassword); // ⚠️ Note: use bcrypt or Argon2 in production

    // Handle image upload
    $imagePath = null;
    if ($file && $file['error'] === 0) {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $imageName = uniqid() . '.' . $ext;
        $folder = '../public/img/avatars/';
        $imagePath = $folder . $imageName;
    
        // Make sure the folder exists
        if (!is_dir($folder)) {
            mkdir($folder, 0755, true); // Create directory recursively if not exists
        }
        // Save the file
        move_uploaded_file($file['tmp_name'], $imagePath);
    
        // If you want to store relative path in DB (e.g., 'img/avatars/users/xxxx.png')
        $imagePath = 'public/img/avatars/' . $imageName;
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, phone, profile_url, password, role, shift_id, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $email, $phone, $imagePath, $hashedPassword, $role, $shift_id, $status, $createdAt, $updatedAt]);
        $newUserId = $pdo->lastInsertId();

        // Assign staff if role is patient
        if ($role === 'patient' && !empty($data['staff_id'])) {
            $stmtAssign = $pdo->prepare("INSERT INTO staff_patient_assignments (staff_id, patient_id) VALUES (?, ?)");
            $stmtAssign->execute([$data['staff_id'], $newUserId]);
            
            // Emit notification to newly assigned staff
            require_once 'NotificationController.php';
            $notifCtrl = new NotificationController($pdo);
            $actor_id = $_SESSION['user']['id'] ?? $data['staff_id'];
            
            $stmtS = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmtS->execute([$data['staff_id']]);
            $staffName = $stmtS->fetchColumn() ?: "Staff";

            $notifCtrl->addNotification($data['staff_id'], $actor_id, 'assignment', "Patient $name has been assigned to you", $newUserId);
            
            // Notify all admins
            $stmtAdmins = $pdo->prepare("SELECT id FROM users WHERE role = 'admin'");
            $stmtAdmins->execute();
            $admins = $stmtAdmins->fetchAll(PDO::FETCH_COLUMN);
            foreach ($admins as $adminId) {
                $notifCtrl->addNotification($adminId, $actor_id, 'assignment', "Patient $name has been assigned to $staffName", $newUserId);
            }
        }

        // Send welcome email
        $emailCtrl = new EmailController();
        $emailCtrl->sendWelcomeEmail($email, $name, $name, $rawPassword);

        return ['status' => true, 'message' => 'User registered successfully. Credentials sent to email.'];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}






function sendEmail(
    string $to,
    string $subject,
    string $body,
    string $fromEmail = 'esa@cocoonbaby.com.au',
    string $fromName = 'Cocoonbaby'
): bool {
    $emailCtrl = new EmailController();
    $result = $emailCtrl->sendNotification($to, $subject, $body);
    return $result['success'];
}


?>
