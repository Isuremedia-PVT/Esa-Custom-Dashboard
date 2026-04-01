<?php
require_once __DIR__ . '/../config/database.php';

class PatientNoteController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getNotesByPatient($patient_id, $page = 1, $perPage = 10, $staff_filter = null, $role = 'admin', $shift_filter = null, $start_date = null, $end_date = null) {
        try {
            $offset = ($page - 1) * $perPage;

            $where = "WHERE pn.patient_id = :patient_id";
            $params = [':patient_id' => (int)$patient_id];

            if ($role === 'staff' && $staff_filter !== null) {
                $where .= " AND pn.staff_id = :staff_id";
                $params[':staff_id'] = (int)$staff_filter;
            }

            if ($shift_filter !== null && $shift_filter !== '') {
                $where .= " AND pn.shift_id = :shift_id_filter";
                $params[':shift_id_filter'] = (int)$shift_filter;
            }

            if ($start_date) {
                $where .= " AND DATE(pn.created_at) >= :start_date";
                $params[':start_date'] = $start_date;
            }
            if ($end_date) {
                $where .= " AND DATE(pn.created_at) <= :end_date";
                $params[':end_date'] = $end_date;
            }

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM patient_notes pn $where");
            foreach ($params as $key => $val) {
                if (is_int($val)) {
                    $stmt->bindValue($key, $val, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $val, PDO::PARAM_STR);
                }
            }
            $stmt->execute();
            $total = $stmt->fetchColumn();

            $query = "SELECT pn.*, u.username as staff_name, nt.name as shift_name, snt.name as staff_shift_name 
                      FROM patient_notes pn 
                      LEFT JOIN users u ON pn.staff_id = u.id 
                      LEFT JOIN note_types nt ON pn.shift_id = nt.id 
                      LEFT JOIN note_types snt ON u.shift_id = snt.id 
                      $where 
                      ORDER BY pn.created_at DESC 
                      LIMIT :offset, :perPage";
            $stmt = $this->pdo->prepare($query);
            foreach ($params as $key => $val) {
                if (is_int($val)) {
                    $stmt->bindValue($key, $val, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $val, PDO::PARAM_STR);
                }
            }
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->bindValue(':perPage', (int)$perPage, PDO::PARAM_INT);
            $stmt->execute();
            $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'notes' => $notes ?: [],
                'total' => $total,
                'currentPage' => $page
            ];
        } catch (PDOException $e) {
            return ['errors' => [$e->getMessage()]];
        }
    }

    public function saveNote($data) {
        $errors = [];
        if (empty($data['patient_id'])) $errors[] = "Patient is required.";
        if (empty($data['staff_id']))   $errors[] = "Staff is required.";
        if (empty($data['note_text']))  $errors[] = "Note text is required.";

        if (empty($errors)) {
            try {
                // shift_id from staff's profile
                $shift_id = !empty($data['shift_id']) ? (int)$data['shift_id'] : null;

                // Fallback 1: Get from DB if session was missing it
                if (empty($shift_id) && !empty($data['staff_id'])) {
                    $stmtUser = $this->pdo->prepare("SELECT shift_id FROM users WHERE id = ?");
                    $stmtUser->execute([$data['staff_id']]);
                    $db_shift = $stmtUser->fetchColumn();
                    if (!empty($db_shift)) {
                        $shift_id = (int)$db_shift;
                    }
                }

                // Fallback 2: Get any valid shift_id from note_types to avoid NOT NULL & FK constraint errors
                if (empty($shift_id)) {
                    $stmtShift = $this->pdo->query("SELECT id FROM note_types ORDER BY id ASC LIMIT 1");
                    $fallback = $stmtShift->fetchColumn();
                    $shift_id = !empty($fallback) ? (int)$fallback : null;
                }

                if (!empty($data['id'])) {
                    // Update existing draft
                    $stmt = $this->pdo->prepare("UPDATE patient_notes SET shift_id = ?, note_text = ?, status = ? WHERE id = ?");
                    $stmt->execute([
                        $shift_id,
                        $data['note_text'],
                        $data['status'] ?? 'final',
                        $data['id']
                    ]);

                    // Trigger Notification for Edit
                    $this->triggerNoteNotification($data['patient_id'], $data['staff_id'], 'note_edited', "Note updated for patient", $data['id']);

                    return ['success' => 'Note updated successfully'];
                } else {
                    $stmt = $this->pdo->prepare("INSERT INTO patient_notes (patient_id, staff_id, shift_id, note_text, status) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $data['patient_id'],
                        $data['staff_id'],
                        $shift_id,
                        $data['note_text'],
                        $data['status'] ?? 'final'
                    ]);
                    $note_id = $this->pdo->lastInsertId();

                    // Trigger Notification for New Note
                    $this->triggerNoteNotification($data['patient_id'], $data['staff_id'], 'note_added', "New note added for patient", $note_id);

                    return ['success' => 'Note saved successfully'];
                }
            } catch (PDOException $e) {
                return ['errors' => [$e->getMessage()]];
            }
        }
        return ['errors' => $errors];
    }

    public function getNoteById($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM patient_notes WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return null;
        }
    }

    public function deleteNote($id, $staff_id, $role) {
        try {
            if ($role === 'admin') {
                $stmt = $this->pdo->prepare("DELETE FROM patient_notes WHERE id = ?");
                $stmt->execute([$id]);
            } else {
                // Staff can only delete their own notes
                $stmt = $this->pdo->prepare("DELETE FROM patient_notes WHERE id = ? AND staff_id = ?");
                $stmt->execute([$id, $staff_id]);
            }
            return ['success' => 'Note deleted successfully'];
        } catch (PDOException $e) {
            return ['errors' => [$e->getMessage()]];
        }
    }
    private function triggerNoteNotification($patient_id, $actor_id, $type, $message_prefix, $reference_id) {
        try {
            require_once 'NotificationController.php';
            $notifCtrl = new NotificationController($this->pdo);

            // Get patient name
            $stmt = $this->pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$patient_id]);
            $patientName = $stmt->fetchColumn() ?: 'Unknown';

            $fullMessage = "$message_prefix: $patientName";

            // 1. Notify all admins (including the one who made the change)
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE role = 'admin'");
            $stmt->execute();
            $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($admins as $adminId) {
                $notifCtrl->addNotification($adminId, $actor_id, $type, $fullMessage, $patient_id);
            }

            // 2. Notify all assigned staff members for this patient (including the one who made the change)
            $stmt = $this->pdo->prepare("SELECT staff_id FROM staff_patient_assignments WHERE patient_id = ?");
            $stmt->execute([$patient_id]);
            $assignedStaff = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($assignedStaff as $staffId) {
                $notifCtrl->addNotification($staffId, $actor_id, $type, $fullMessage, $patient_id);
            }

        } catch (Exception $e) {
            error_log("Note notification trigger failed: " . $e->getMessage());
        }
    }
}
