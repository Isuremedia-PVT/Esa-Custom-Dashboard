<?php
require_once './config/database.php';

class StaffController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getStaffById($id, $restrictToStaff = true) {
        try {
            $query = "SELECT u.id, u.username, u.email, u.phone, u.profile_url, u.status, u.role, u.shift_id, nt.name AS shift_name 
                      FROM users u 
                      LEFT JOIN note_types nt ON u.shift_id = nt.id
                      WHERE u.id = :id";
            if ($restrictToStaff) {
                $query .= " AND u.role = 'staff'";
            } else {
                $query .= " AND u.role IN ('admin', 'staff')";
            }
            error_log("getStaffById Query: $query");
            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log("Query failed in getStaffById: " . $e->getMessage());
            return ['errors' => ["Query failed: " . $e->getMessage()]];
        }
    }

    public function getStaff($page = 1, $limit = 10, $search = '', $statusFilter = '') {
        try {
            $offset = ($page - 1) * $limit;
            $limit = (int)$limit;
            $offset = (int)$offset;
            if ($limit < 1 || $offset < 0) {
                return ['errors' => ['Invalid pagination parameters']];
            }

            $conditions = "WHERE role = 'staff'";
            $params = [];

            if (!empty($search)) {
                $conditions .= " AND (username LIKE :search_username OR email LIKE :search_email OR phone LIKE :search_phone)";
                $params[':search_username'] = '%' . $search . '%';
                $params[':search_email'] = '%' . $search . '%';
                $params[':search_phone'] = '%' . $search . '%';
            }

            if (!empty($statusFilter)) {
                $conditions .= " AND status = :status";
                $params[':status'] = $statusFilter;
            }

            // ORDER BY: If status filtered, newest first. If not, active first then newest.
            $order = !empty($statusFilter) ? "created_at DESC" : "FIELD(status, 'active', 'inactive'), created_at DESC";

            $countQuery = "SELECT COUNT(*) FROM users $conditions";
            $query = "SELECT id, username, email, phone, profile_url, status 
                      FROM users 
                      $conditions 
                      ORDER BY $order
                      LIMIT $limit OFFSET $offset";

            // Execute count query
            $stmt = $this->pdo->prepare($countQuery);
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value, PDO::PARAM_STR);
            }
            $stmt->execute();
            $totalStaff = $stmt->fetchColumn();
            $totalPages = ceil($totalStaff / $limit);

            // Execute main query
            $stmt = $this->pdo->prepare($query);
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value, PDO::PARAM_STR);
            }
            $stmt->execute();
            $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'staff' => $staff ?: [],
                'totalStaff' => $totalStaff,
                'totalPages' => $totalPages,
                'currentPage' => $page
            ];
        } catch (PDOException $e) {
            error_log("Query failed in getStaff: " . $e->getMessage());
            return ['errors' => ["Query failed: " . $e->getMessage()]];
        }
    }

    public function updateStaff($id, $data, $file) {
        $errors = [];
        $query = "SELECT status FROM users WHERE id = :id AND role = 'staff'";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $status = $stmt->fetchColumn();

        if ($status !== 'active') {
            $errors[] = "Only active staff members can be updated.";
            return ['errors' => $errors];
        }

        $username = trim($data['username'] ?? '');
        $phone = trim($data['phone'] ?? '') ?: null;
        $profile_url = $data['current_profile_url'] ?? null;
        $shift_id = isset($data['shift_id']) && $data['shift_id'] !== '' ? (int)$data['shift_id'] : null;

        // Username validation
        if (empty($username)) {
            $errors[] = "Username is required.";
        } elseif (strlen($username) < 3) {
            $errors[] = "Username must be at least 3 characters long.";
        } elseif (!preg_match('/^[a-zA-Z0-9_\s]+$/', $username)) {
            $errors[] = "Username can only contain letters, numbers, spaces, and underscores.";
        }

        // Phone validation
        if (!empty($phone) && !preg_match('/^\+?[\d\s-]{10,15}$/', $phone)) {
            $errors[] = "Invalid phone number format.";
        }

        if (!empty($file['image']['name']) && $file['image']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png'];
            $max_size = 5 * 1024 * 1024;
            $upload_dir = 'public/img/avatars/';
            $filename = basename(uniqid() . '.' . pathinfo($file['image']['name'], PATHINFO_EXTENSION));
            $profile_url = $upload_dir . $filename;

            if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
                $errors[] = "Failed to create upload directory.";
            } elseif (!is_writable($upload_dir)) {
                $errors[] = "Upload directory is not writable.";
            } elseif (!in_array($file['image']['type'], $allowed_types)) {
                $errors[] = "Only JPEG or PNG files are allowed.";
            } elseif ($file['image']['size'] > $max_size) {
                $errors[] = "Image size must be less than 5MB.";
            } elseif (!move_uploaded_file($file['image']['tmp_name'], $profile_url)) {
                $errors[] = "Failed to upload image.";
            } elseif ($data['current_profile_url'] && file_exists($data['current_profile_url']) && is_file($data['current_profile_url'])) {
                unlink($data['current_profile_url']);
            }
        } elseif ($file['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $errors[] = "Image upload error: " . $file['image']['error'];
        }

        if (empty($errors)) {
            $this->pdo->beginTransaction();
            try {
                $query = "UPDATE users SET username = :username, phone = :phone, profile_url = :profile_url, shift_id = :shift_id, updated_at = CURRENT_TIMESTAMP 
                          WHERE id = :id AND role = 'staff'";
                error_log("updateStaff Query: $query");
                $stmt = $this->pdo->prepare($query);
                $stmt->bindValue(':username', $username, PDO::PARAM_STR);
                $stmt->bindValue(':phone', $phone, PDO::PARAM_STR);
                $stmt->bindValue(':profile_url', $profile_url, PDO::PARAM_STR);
                $stmt->bindValue(':shift_id', $shift_id, PDO::PARAM_INT);
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                $this->pdo->commit();
                $_SESSION['message'] = 'Staff updated successfully';
                $_SESSION['message_type'] = 'success';
                header("Location: staff.php");
                exit;

            } catch (Exception $e) {
                $this->pdo->rollBack();
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
        return ['errors' => $errors];
    }

    public function deleteStaff($id) {
        $this->pdo->beginTransaction();
        try {
            $query = "SELECT status FROM users WHERE id = :id AND role = 'staff'";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $status = $stmt->fetchColumn();

            if ($status !== 'active') {
                return ['errors' => ["Only active staff members can be deleted."]];
            }

            $query = "UPDATE users SET status = 'inactive', updated_at = CURRENT_TIMESTAMP 
                      WHERE id = :id AND role = 'staff'";
            error_log("deleteStaff Query: $query");
            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $this->pdo->commit();
            return ['success' => 'Staff member deleted successfully'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['errors' => ["Error: " . $e->getMessage()]];
        }
    }

    public function activateStaff($id) {
        $this->pdo->beginTransaction();
        try {
            $query = "SELECT status FROM users WHERE id = :id AND role = 'staff'";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $status = $stmt->fetchColumn();

            if ($status !== 'inactive') {
                return ['errors' => ["Only inactive staff members can be activated."]];
            }

            $query = "UPDATE users SET status = 'active', updated_at = CURRENT_TIMESTAMP 
                      WHERE id = :id AND role = 'staff'";
            error_log("activateStaff Query: $query");
            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $this->pdo->commit();
            return ['success' => 'Staff member activated successfully'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['errors' => ["Error: " . $e->getMessage()]];
        }
    }

    public function assignPatientToStaff($staff_id, $patient_id) {
        $this->pdo->beginTransaction();
        try {
            $query = "SELECT status FROM users WHERE id = :staff_id AND role = 'staff'";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(':staff_id', $staff_id, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->fetchColumn() !== 'active') {
                return ['errors' => ["Only active staff members can be assigned patients."]];
            }

            $query = "SELECT status FROM users WHERE id = :patient_id AND role = 'patient'";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(':patient_id', $patient_id, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->fetchColumn() !== 'active') {
                return ['errors' => ["Only active patients can be assigned."]];
            }

            $query = "SELECT COUNT(*) FROM staff_patient_assignments WHERE staff_id = :staff_id AND patient_id = :patient_id";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(':staff_id', $staff_id, PDO::PARAM_INT);
            $stmt->bindValue(':patient_id', $patient_id, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->fetchColumn() > 0) {
                return ['errors' => ["Patient is already assigned to this staff member."]];
            }

            $query = "INSERT INTO staff_patient_assignments (staff_id, patient_id) VALUES (:staff_id, :patient_id)";
            error_log("assignPatientToStaff Query: $query");
            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(':staff_id', $staff_id, PDO::PARAM_INT);
            $stmt->bindValue(':patient_id', $patient_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Send notification for new assignment
            require_once 'NotificationController.php';
            $notifCtrl = new NotificationController($this->pdo);
            $actor_id = $_SESSION['user']['id'] ?? $staff_id;
            
            // Get patient name
            $stmtP = $this->pdo->prepare("SELECT username FROM users WHERE id = :patient_id");
            $stmtP->bindValue(':patient_id', $patient_id, PDO::PARAM_INT);
            $stmtP->execute();
            $patientName = $stmtP->fetchColumn() ?: "Unknown Patient";
            $stmtS = $this->pdo->prepare("SELECT username FROM users WHERE id = :staff_id");
            $stmtS->bindValue(':staff_id', $staff_id, PDO::PARAM_INT);
            $stmtS->execute();
            $staffName = $stmtS->fetchColumn() ?: "Staff";
            
            $notifCtrl->addNotification($staff_id, $actor_id, 'assignment', "Patient $patientName has been assigned to you", $patient_id);

            // Notify all admins
            $stmtAdmins = $this->pdo->prepare("SELECT id FROM users WHERE role = 'admin'");
            $stmtAdmins->execute();
            $admins = $stmtAdmins->fetchAll(PDO::FETCH_COLUMN);
            foreach ($admins as $adminId) {
                $notifCtrl->addNotification($adminId, $actor_id, 'assignment', "Patient $patientName has been assigned to $staffName", $patient_id);
            }

            $this->pdo->commit();
            return ['success' => 'Patient assigned successfully'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['errors' => ["Error: " . $e->getMessage()]];
        }
    }

    public function unassignPatientFromStaff($staff_id, $patient_id) {
        $this->pdo->beginTransaction();
        try {
            $query = "SELECT COUNT(*) FROM staff_patient_assignments WHERE staff_id = :staff_id AND patient_id = :patient_id";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(':staff_id', $staff_id, PDO::PARAM_INT);
            $stmt->bindValue(':patient_id', $patient_id, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->fetchColumn() == 0) {
                return ['errors' => ["Patient is not assigned to this staff member."]];
            }

            $query = "DELETE FROM staff_patient_assignments WHERE staff_id = :staff_id AND patient_id = :patient_id";
            error_log("unassignPatientFromStaff Query: $query");
            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(':staff_id', $staff_id, PDO::PARAM_INT);
            $stmt->bindValue(':patient_id', $patient_id, PDO::PARAM_INT);
            $stmt->execute();

            $this->pdo->commit();
            return ['success' => 'Patient unassigned successfully'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['errors' => ["Error: " . $e->getMessage()]];
        }
    }

    public function getAssignedPatients($staff_id, $page = 1, $limit = 10, $search = '', $statusFilter = '') {
        try {
            $offset = ($page - 1) * $limit;
            $limit = (int)$limit;
            $offset = (int)$offset;
            if ($limit < 1 || $offset < 0) {
                return ['errors' => ['Invalid pagination parameters']];
            }

            $conditions = "INNER JOIN staff_patient_assignments spa ON u.id = spa.patient_id 
                           WHERE spa.staff_id = :staff_id AND u.role = 'patient'";
            $params = [':staff_id' => $staff_id];

            if (!empty($search)) {
                $conditions .= " AND (u.username LIKE :search_username OR u.email LIKE :search_email OR u.phone LIKE :search_phone)";
                $params[':search_username'] = '%' . $search . '%';
                $params[':search_email'] = '%' . $search . '%';
                $params[':search_phone'] = '%' . $search . '%';
            }

            if (!empty($statusFilter)) {
                $conditions .= " AND u.status = :status";
                $params[':status'] = $statusFilter;
            }

            // ORDER BY newest first
            $order = !empty($statusFilter) ? "u.created_at DESC" : "FIELD(u.status, 'active', 'inactive'), u.created_at DESC";

            $countQuery = "SELECT COUNT(*) FROM users u $conditions";
            $query = "SELECT u.id, u.username, u.email, u.phone, u.profile_url, u.status 
                      FROM users u 
                      $conditions 
                      ORDER BY $order
                      LIMIT $limit OFFSET $offset";

            // Execute count query
            $stmt = $this->pdo->prepare($countQuery);
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            $totalPatients = $stmt->fetchColumn();
            $totalPages = ceil($totalPatients / $limit);

            // Execute main query
            $stmt = $this->pdo->prepare($query);
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'patients' => $patients ?: [],
                'totalPatients' => $totalPatients,
                'totalPages' => $totalPages,
                'currentPage' => $page
            ];
        } catch (PDOException $e) {
            error_log("Query failed in getAssignedPatients: " . $e->getMessage());
            return ['errors' => ["Query failed: " . $e->getMessage()]];
        }
    }

    public function getUnassignedPatients($page = 1, $limit = 10, $search = '') {
        try {
            $offset = ($page - 1) * $limit;
            // Validate limit and offset
            $limit = (int)$limit;
            $offset = (int)$offset;
            if ($limit < 1 || $offset < 0) {
                return ['errors' => ['Invalid pagination parameters']];
            }

            $query = "SELECT id, username, email, phone, profile_url, status 
                      FROM users 
                      WHERE role = 'patient' AND status = 'active'";
            $countQuery = "SELECT COUNT(*) FROM users WHERE role = 'patient' AND status = 'active'";
            $params = [];

            if (!empty($search)) {
                $query .= " AND (username LIKE :search_username OR email LIKE :search_email OR phone LIKE :search_phone)";
                $countQuery .= " AND (username LIKE :search_username OR email LIKE :search_email OR phone LIKE :search_phone)";
                $params[':search_username'] = '%' . $search . '%';
                $params[':search_email'] = '%' . $search . '%';
                $params[':search_phone'] = '%' . $search . '%';
            }

            $query .= " LIMIT $limit OFFSET $offset";

            // Debug query
            error_log("getUnassignedPatients Query: $query");
            error_log("getUnassignedPatients Params: " . print_r($params, true));

            // Execute count query
            $stmt = $this->pdo->prepare($countQuery);
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value, PDO::PARAM_STR);
            }
            $stmt->execute();
            $totalPatients = $stmt->fetchColumn();
            $totalPages = ceil($totalPatients / $limit);

            // Execute main query
            $stmt = $this->pdo->prepare($query);
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value, PDO::PARAM_STR);
            }
            $stmt->execute();
            $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'patients' => $patients ?: [],
                'totalPatients' => $totalPatients,
                'totalPages' => $totalPages,
                'currentPage' => $page
            ];
        } catch (PDOException $e) {
            error_log("Query failed in getUnassignedPatients: " . $e->getMessage());
            return ['errors' => ["Query failed: " . $e->getMessage()]];
        }
    }

    public function getPatientsForAssignment($staffId, $search = '') {
        try {
            $query = "SELECT u.id, u.username AS name, IF(spa.staff_id IS NOT NULL, 1, 0) AS is_assigned
                      FROM users u
                      LEFT JOIN staff_patient_assignments spa ON u.id = spa.patient_id AND spa.staff_id = :staffId
                      WHERE u.role = 'patient' AND u.status = 'active'
                      AND (u.username LIKE :search OR u.email LIKE :search)";
            error_log("getPatientsForAssignment Query: $query");
            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(':staffId', $staffId, PDO::PARAM_INT);
            $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Query failed in getPatientsForAssignment: " . $e->getMessage());
            return ['error' => 'Error fetching patients: ' . $e->getMessage()];
        }
    }
}
?>