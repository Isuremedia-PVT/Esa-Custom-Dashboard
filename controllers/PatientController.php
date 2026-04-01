<?php
require_once __DIR__ . '/../config/database.php';

class PatientController {
    protected $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function isPatientAssignedToStaff($patient_id, $staff_id) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM staff_patient_assignments WHERE staff_id = ? AND patient_id = ?");
        $stmt->execute([$staff_id, $patient_id]);
        return $stmt->fetchColumn() > 0;
    }

    public function isUserActive($user_id) {
        $stmt = $this->pdo->prepare("SELECT status FROM users WHERE id = :id");
        $stmt->execute(['id' => $user_id]);
        $status = $stmt->fetchColumn();
        return $status === 'active';
    }

    public function getPatients($page = 1, $perPage = 10, $search = '', $statusFilter = '') {
        try {
            // Calculate offset
            $offset = ($page - 1) * $perPage;

            // Build conditions
            $conditions = "WHERE role = 'patient'";
            $params = [];

            if (!empty($search)) {
                $conditions .= " AND (username LIKE :search OR email LIKE :search OR phone LIKE :search)";
                $params[':search'] = '%' . $search . '%';
            }

            if (!empty($statusFilter)) {
                $conditions .= " AND status = :status";
                $params[':status'] = $statusFilter;
            }

            // Get total number of patients
            $query = "SELECT COUNT(*) FROM users $conditions";
            $stmt = $this->pdo->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->execute();
            $totalPatients = $stmt->fetchColumn();
            $totalPages = ceil($totalPatients / $perPage);

            // Fetch patients for the current page
            // NEW ONE ON TOP: We use created_at DESC or id DESC. 
            // We also maintain the active first priority if no status filter is active
            $order = !empty($statusFilter) ? "created_at DESC" : "FIELD(status, 'active', 'inactive'), created_at DESC";
            
            $query = "SELECT id, username, email, phone, profile_url, status 
                      FROM users 
                      $conditions 
                      ORDER BY $order
                      LIMIT :offset, :perPage";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->bindValue(':perPage', (int)$perPage, PDO::PARAM_INT);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
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
            die("Query failed: " . $e->getMessage());
        }
    }

    public function getPatient($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT id, username, email, phone, profile_url, status 
                                         FROM users 
                                         WHERE id = ? AND role = 'patient'");
            $stmt->execute([$id]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($patient) {
                $stmt = $this->pdo->prepare("SELECT patient_disease_id, name, type, icd10_code, description, date_diagnosed 
                                             FROM patient_diseases 
                                             WHERE user_id = ? AND (status = 'active' OR status IS NULL)");
                $stmt->execute([$id]);
                $patient['diseases'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            return $patient ?: null;
        } catch (PDOException $e) {
            die("Query failed: " . $e->getMessage());
        }
    }

    public function updatePatient($id, $data, $file) {
        $errors = [];

        // Check if patient is active
        $stmt = $this->pdo->prepare("SELECT status FROM users WHERE id = ? AND role = 'patient'");
        $stmt->execute([$id]);
        $status = $stmt->fetchColumn();

        if ($status !== 'active') {
            $errors[] = "Only active patients can be updated.";
            return ['errors' => $errors];
        }

        $username = trim($data['username'] ?? '');
        $phone = trim($data['phone'] ?? '') ?: null;
        $profile_url = $data['current_profile_url'] ?? null;
    // Username validation
    if (empty($username)) {
        $errors[] = "Username is required.";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters long.";
    }

    // Phone validation
    if (!empty($phone) && !preg_match('/^\+?[\d\s-]{10,15}$/', $phone)) {
        $errors[] = "Invalid phone number format.";
    }

        // Handle profile image upload
        if (!empty($file['image']['name']) && $file['image']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png'];
            $max_size = 5 * 1024 * 1024; // 5MB
            $upload_dir = 'public/img/avatars/';

            if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
                $errors[] = "Failed to create upload directory.";
            } elseif (!is_writable($upload_dir)) {
                $errors[] = "Upload directory is not writable.";
            } else {
                if (!in_array($file['image']['type'], $allowed_types)) {
                    $errors[] = "Only JPEG or PNG files are allowed.";
                } elseif ($file['image']['size'] > $max_size) {
                    $errors[] = "Image size must be less than 5MB.";
                } else {
                    $ext = pathinfo($file['image']['name'], PATHINFO_EXTENSION);
                    $filename = uniqid() . '.' . $ext;
                    $profile_url = $upload_dir . $filename;

                    if (!move_uploaded_file($file['image']['tmp_name'], $profile_url)) {
                        $errors[] = "Failed to upload image.";
                    } elseif ($data['current_profile_url'] && file_exists($data['current_profile_url'])) {
                        unlink($data['current_profile_url']);
                    }
                }
            }
        } elseif ($file['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $errors[] = "Image upload error: " . $file['image']['error'];
        }

        // Update patient in database
        if (empty($errors)) {
            $this->pdo->beginTransaction();
            try {
                $query = "UPDATE users SET username = ?, phone = ?, profile_url = ?, updated_at = CURRENT_TIMESTAMP 
                          WHERE id = ? AND role = 'patient'";
                $params = [$username,$phone, $profile_url, $id];

                $stmt = $this->pdo->prepare($query);
                $stmt->execute($params);

                // Update staff assignment
                if (isset($data['staff_id'])) {
                    $stmtCheck = $this->pdo->prepare("SELECT staff_id FROM staff_patient_assignments WHERE patient_id = ?");
                    $stmtCheck->execute([$id]);
                    $oldStaffId = $stmtCheck->fetchColumn();
                    $newStaffId = !empty($data['staff_id']) ? (int)$data['staff_id'] : null;

                    if ($oldStaffId != $newStaffId) {
                        // Remove existing assignments for this patient
                        $stmtDelete = $this->pdo->prepare("DELETE FROM staff_patient_assignments WHERE patient_id = ?");
                        $stmtDelete->execute([$id]);
    
                        // Add new assignment if a staff member was selected
                        if ($newStaffId) {
                            $stmtInsert = $this->pdo->prepare("INSERT INTO staff_patient_assignments (staff_id, patient_id) VALUES (?, ?)");
                            $stmtInsert->execute([$newStaffId, $id]);
                            
                            // Emit notification to newly assigned staff
                            require_once 'NotificationController.php';
                            $notifCtrl = new NotificationController($this->pdo);
                            $actor_id = $_SESSION['user']['id'] ?? $newStaffId;
                            
                            $stmtS = $this->pdo->prepare("SELECT username FROM users WHERE id = ?");
                            $stmtS->execute([$newStaffId]);
                            $staffName = $stmtS->fetchColumn() ?: "Staff";

                            $notifCtrl->addNotification($newStaffId, $actor_id, 'assignment', "Patient $username has been assigned to you", $id);
                            
                            // Notify all admins
                            $stmtAdmins = $this->pdo->prepare("SELECT id FROM users WHERE role = 'admin'");
                            $stmtAdmins->execute();
                            $admins = $stmtAdmins->fetchAll(PDO::FETCH_COLUMN);
                            foreach ($admins as $adminId) {
                                $notifCtrl->addNotification($adminId, $actor_id, 'assignment', "Patient $username has been assigned to $staffName", $id);
                            }
                        }
                    }
                }

                $this->pdo->commit();
                return ['success' => 'Patient updated successfully'];
            } catch (Exception $e) {
                $this->pdo->rollBack();
                $errors[] = "Database error: " . $e->getMessage();
            }
        }

        return ['errors' => $errors];
    }

    public function deletePatient($id) {
        $this->pdo->beginTransaction();
        try {
            // Check if patient exists and is active
            $stmt = $this->pdo->prepare("SELECT status FROM users WHERE id = ? AND role = 'patient'");
            $stmt->execute([$id]);
            $status = $stmt->fetchColumn();

            if ($status !== 'active') {
                return ['errors' => ["Only active patients can be deleted."]];
            }

            // Update status to inactive
            $stmt = $this->pdo->prepare("UPDATE users SET status = 'inactive', updated_at = CURRENT_TIMESTAMP 
                                         WHERE id = ? AND role = 'patient'");
            $stmt->execute([$id]);

            $this->pdo->commit();
            return ['success' => 'Patient deleted successfully'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['errors' => ["Error: " . $e->getMessage()]];
        }
    }

    public function activatePatient($id) {
        $this->pdo->beginTransaction();
        try {
            // Check if patient exists and is inactive
            $stmt = $this->pdo->prepare("SELECT status FROM users WHERE id = ? AND role = 'patient'");
            $stmt->execute([$id]);
            $status = $stmt->fetchColumn();

            if ($status !== 'inactive') {
                return ['errors' => ["Only inactive patients can be activated."]];
            }

            // Update status to active
            $stmt = $this->pdo->prepare("UPDATE users SET status = 'active', updated_at = CURRENT_TIMESTAMP 
                                         WHERE id = ? AND role = 'patient'");
            $stmt->execute([$id]);

            $this->pdo->commit();
            return ['success' => 'Patient activated successfully'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['errors' => ["Error: " . $e->getMessage()]];
        }
    }

    public function getNoteTypes() {
        try {
            $stmt = $this->pdo->query("SELECT id, name, description FROM note_types ORDER BY id ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('getNoteTypes error: ' . $e->getMessage());
            return [];
        }
    }
}
?>