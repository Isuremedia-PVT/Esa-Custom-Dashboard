<?php
require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/PatientController.php';

class PatientDiseaseController extends PatientController {
    
    public function __construct($pdo) {
        parent::__construct($pdo);
    }

    public function isPatientAssignedToStaff($patient_id, $staff_id) {
        return parent::isPatientAssignedToStaff($patient_id, $staff_id);
    }

    // Check if user is admin
    public function isAdmin() {
        return isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin';
    }

    // Check if user is staff
    private function isStaff() {
        return isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'staff';
    }

    // Check if user is patient
    private function isPatient() {
        return isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'patient';
    }

    // Check if user is active
    // Using parent's isUserActive

    // Using parent's getPatients

    // Get user details
    public function getUserDetails($user_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT u.username, u.email, u.phone, s.username as assigned_staff 
                FROM users u 
                LEFT JOIN staff_patient_assignments spa ON u.id = spa.patient_id 
                LEFT JOIN users s ON spa.staff_id = s.id 
                WHERE u.id = ? AND u.status = 'active'
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            die("Query failed: " . $e->getMessage());
        }
    }

    // Get all questions with options
    public function getAllQuestions($shift_id = null) {
        try {
            $query = "SELECT question_id, question_text, question_type, shift_id, is_mandatory FROM questions WHERE status = 'active'";
            $params = [];
            if ($shift_id !== null && $shift_id > 0) {
                $query .= " AND shift_id = ?";
                $params[] = (int)$shift_id;
            }
            $query .= " ORDER BY created_at DESC";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($questions as &$question) {
                if (in_array($question['question_type'], ['dropdown', 'multidropdown', 'checkbox', 'radio'])) {
                    $stmt = $this->pdo->prepare("SELECT option_text FROM question_options WHERE question_id = ? ORDER BY option_id");
                    $stmt->execute([$question['question_id']]);
                    $question['options'] = array_values(array_filter($stmt->fetchAll(PDO::FETCH_COLUMN), function($opt) { return trim($opt) !== ''; }));
                } else {
                    $question['options'] = [];
                }
            }
            return $questions;
        } catch (PDOException $e) {
            die("Query failed: " . $e->getMessage());
        }
    }

    // Get selected questions for a patient with options and answers
    public function getPatientQuestions($user_id, $shift_id = null) {
        if (!is_int($user_id) || $user_id <= 0) {
            return ['success' => false, 'message' => 'Invalid user ID', 'code' => 400];
        }
        try {
            $query = "
                SELECT pq.question_id, q.question_text, q.question_type, q.is_mandatory,
                       a.answer_text, a.updated_at as last_answered, u.username as last_submitted_by
                FROM patient_questions pq
                JOIN questions q ON pq.question_id = q.question_id
                LEFT JOIN answers a ON pq.user_id = a.user_id AND pq.question_id = a.question_id
                LEFT JOIN users u ON a.submitted_by = u.id
                WHERE pq.user_id = ? AND pq.status = 'active' AND q.status = 'active'
            ";
            $params = [(int)$user_id];
            
            if ($shift_id !== null && $shift_id > 0) {
                $query .= " AND (pq.shift_id = ? OR (pq.shift_id IS NULL AND q.shift_id = ?))";
                $params[] = (int)$shift_id;
                $params[] = (int)$shift_id;
            }
            
            $query .= " ORDER BY pq.created_at DESC";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($questions as &$question) {
                // Fetch Options
                if (in_array($question['question_type'], ['dropdown', 'multidropdown', 'checkbox', 'radio'])) {
                    $optStmt = $this->pdo->prepare("SELECT option_text FROM question_options WHERE question_id = ? ORDER BY option_id");
                    $optStmt->execute([$question['question_id']]);
                    $question['options'] = array_values(array_filter($optStmt->fetchAll(PDO::FETCH_COLUMN), function($opt) { return trim($opt) !== ''; }));
                } else {
                    $question['options'] = [];
                }

                // Collaborative Logic: Check for a local "Draft" first
                if ($shift_id !== null && $shift_id > 0) {
                    $draftStmt = $this->pdo->prepare("
                        SELECT al.answer_text, al.created_at as last_answered, u.username as last_submitted_by
                        FROM answer_logs al
                        LEFT JOIN users u ON al.submitted_by = u.id
                        WHERE al.user_id = ? AND al.question_id = ? AND al.shift_id = ? AND al.status = 'draft'
                        ORDER BY al.created_at DESC LIMIT 1
                    ");
                    $draftStmt->execute([(int)$user_id, (int)$question['question_id'], (int)$shift_id]);
                    $draft = $draftStmt->fetch(PDO::FETCH_ASSOC);

                    if ($draft) {
                        $question['answer_text'] = $draft['answer_text'];
                        $question['last_answered'] = $draft['last_answered'];
                        $question['last_submitted_by'] = $draft['last_submitted_by'];
                    }
                }
                
                // If answer_text is still empty after draft check, it remains the one from the original JOIN (latest active)
            }
            return $questions;
        } catch (PDOException $e) {
            error_log('getPatientQuestions error: ' . $e->getMessage() . ' | User ID: ' . $user_id);
            return ['success' => false, 'message' => 'Database error occurred', 'code' => 500];
        }
    }
public function getQuestionTypes() {
    try {
        error_log('getQuestionTypes: Executing query for question_type column');
        $stmt = $this->pdo->query("SHOW COLUMNS FROM questions WHERE Field = 'question_type'");
        $column = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$column) {
            error_log('getQuestionTypes: Question type column not found');
            return ['success' => false, 'message' => 'Question type column not found', 'code' => 404];
        }
        
        error_log('getQuestionTypes: Column data - ' . json_encode($column));
        preg_match("/^enum\((.*)\)$/", $column['Type'], $matches);
        if (!isset($matches[1])) {
            error_log('getQuestionTypes: Invalid ENUM type format - ' . $column['Type']);
            return ['success' => false, 'message' => 'Invalid ENUM type format', 'code' => 500];
        }

        $enumValues = array_map(function($value) {
            return trim($value, "'");
        }, explode(',', $matches[1]));
        error_log('getQuestionTypes: ENUM values - ' . json_encode($enumValues));

        $typeLabels = [
            'text' => 'Text',
            'textarea' => 'Textarea',
            'textbox_list' => 'Textbox List',
            'dropdown' => 'Dropdown',
            'multidropdown' => 'Multi-Select Dropdown',
            'checkbox' => 'Checkbox',
            'radio' => 'Radio'
        ];

        $questionTypes = array_map(function($type) use ($typeLabels) {
            return [
                'type_name' => $type,
                'display_name' => $typeLabels[$type] ?? ucfirst($type)
            ];
        }, $enumValues);

        error_log('getQuestionTypes: Final output - ' . json_encode($questionTypes));
        return $questionTypes;
    } catch (PDOException $e) {
        error_log('getQuestionTypes: PDO error - ' . $e->getMessage());
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage(), 'code' => 500];
    }
}
    public function addQuestion($data) {
    try {
        

        if (empty($data['question_text'])) {
            error_log('addQuestion: Question text is required');
            return ['success' => false, 'message' => 'Question text is required.', 'code' => 400];
        }

        $questionTypes = $this->getQuestionTypes();
        if (isset($questionTypes['success']) && !$questionTypes['success']) {
            error_log('addQuestion: Failed to validate question type - ' . $questionTypes['message']);
            return ['success' => false, 'message' => 'Failed to validate question type: ' . $questionTypes['message'], 'code' => 500];
        }

        $validTypes = array_column($questionTypes, 'type_name');
        error_log('addQuestion: Valid types - ' . json_encode($validTypes));
        error_log('addQuestion: Submitted question_type - ' . $data['question_type']);
        if (empty($data['question_type']) || !in_array($data['question_type'], $validTypes)) {
            error_log('addQuestion: Invalid question type - ' . $data['question_type']);
            return ['success' => false, 'message' => 'Invalid question type.', 'code' => 400];
        }

        if (in_array($data['question_type'], ['dropdown', 'multidropdown', 'checkbox', 'radio']) && empty($data['options'])) {
            error_log('addQuestion: Options required for ' . $data['question_type']);
            return ['success' => false, 'message' => 'At least one option is required for ' . $data['question_type'] . ' questions.', 'code' => 400];
        }
        if (in_array($data['question_type'], ['text', 'textarea', 'textbox_list']) && !empty($data['options'])) {
            error_log('addQuestion: Options not allowed for ' . $data['question_type']);
            return ['success' => false, 'message' => 'Options are not allowed for ' . $data['question_type'] . ' questions.', 'code' => 400];
        }

        $this->pdo->beginTransaction();

        $note_type_id = (int)($data['note_type_id'] ?? 1);
        $is_mandatory = (int)($data['is_mandatory'] ?? 0);
        $stmt = $this->pdo->prepare("INSERT INTO questions (question_text, question_type, note_type_id, is_mandatory, status, created_at, updated_at) VALUES (?, ?, ?, ?, 'active', NOW(), NOW())");
        $stmt->execute([$data['question_text'], $data['question_type'], $note_type_id, $is_mandatory]);
        $question_id = $this->pdo->lastInsertId();

        if (in_array($data['question_type'], ['dropdown', 'multidropdown', 'checkbox', 'radio']) && !empty($data['options'])) {
            $stmt = $this->pdo->prepare("INSERT INTO question_options (question_id, option_text) VALUES (?, ?)");
            foreach ($data['options'] as $option) {
                $stmt->execute([$question_id, $option]);
            }
        }

        $this->pdo->commit();
        error_log('addQuestion: Question created successfully, ID: ' . $question_id);
        return ['success' => true, 'message' => 'Question created successfully.', 'code' => 200];
    } catch (PDOException $e) {
        $this->pdo->rollBack();
        error_log('addQuestion: PDO error - ' . $e->getMessage());
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage(), 'code' => 500];
    }
}
    // Assign questions to patient
    public function assignQuestionsToPatient($user_id, $question_ids, $shift_id = null) {
        if (!$this->isAdmin()) {
            return ['success' => false, 'message' => 'Unauthorized', 'code' => 403];
        }
        if (!is_int($user_id) || $user_id <= 0) {
            return ['success' => false, 'message' => 'Invalid user ID', 'code' => 400];
        }
        $question_ids = array_filter(array_map('intval', (array)$question_ids));
        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            if ($stmt->fetchColumn() == 0) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'User not found', 'code' => 404];
            }
            if (!empty($question_ids)) {
                $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
                $stmt = $this->pdo->prepare("SELECT question_id FROM questions WHERE question_id IN ($placeholders)");
                $stmt->execute($question_ids);
                $valid_question_ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'question_id');
                $invalid_questions = array_diff($question_ids, $valid_question_ids);
                if (!empty($invalid_questions)) {
                    $this->pdo->rollBack();
                    return ['success' => false, 'message' => 'Invalid question IDs: ' . implode(', ', $invalid_questions), 'code' => 400];
                }
            }
            $stmt = $this->pdo->prepare("SELECT question_id FROM patient_questions WHERE user_id = ? AND status = 'active'");
            $stmt->execute([$user_id]);
            $current_question_ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'question_id');
            $questions_to_add = array_diff($question_ids, $current_question_ids);
            $questions_to_remove = array_diff($current_question_ids, $question_ids);
            if (!empty($questions_to_add)) {
                $stmt = $this->pdo->prepare("INSERT INTO patient_questions (user_id, question_id, status, created_at, shift_id) VALUES (?, ?, 'active', NOW(), ?)");
                foreach ($questions_to_add as $question_id) {
                    $stmt->execute([$user_id, $question_id, $shift_id]);
                }
            }
            if (!empty($questions_to_remove)) {
                $placeholders = implode(',', array_fill(0, count($questions_to_remove), '?'));
                $stmt = $this->pdo->prepare("DELETE FROM patient_questions WHERE user_id = ? AND question_id IN ($placeholders)");
                $stmt->execute(array_merge([$user_id], $questions_to_remove));
            }
            $this->pdo->commit();
            return ['success' => true, 'message' => 'Questions assigned successfully', 'code' => 200];
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log('assignQuestionsToPatient error: ' . $e->getMessage() . ' | User ID: ' . $user_id . ' | Question IDs: ' . implode(', ', $question_ids));
            return ['success' => false, 'message' => 'Database error occurred', 'code' => 500];
        }
    }

    // Unassign questions from patient
    public function unassignQuestionsFromPatient($user_id, $question_ids) {
        if (!$this->isAdmin()) {
            return ['success' => false, 'message' => 'Unauthorized', 'code' => 403];
        }
        if (!is_int($user_id) || $user_id <= 0) {
            return ['success' => false, 'message' => 'Invalid user ID', 'code' => 400];
        }
        $question_ids = array_filter(array_map('intval', (array)$question_ids));
        if (empty($question_ids)) {
            return ['success' => false, 'message' => 'No valid questions selected for unassignment', 'code' => 400];
        }
        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            if ($stmt->fetchColumn() == 0) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'User not found', 'code' => 404];
            }
            $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
            $stmt = $this->pdo->prepare("DELETE FROM patient_questions WHERE user_id = ? AND question_id IN ($placeholders)");
            $stmt->execute(array_merge([$user_id], $question_ids));
            $this->pdo->commit();
            return ['success' => true, 'message' => 'Questions unassigned successfully', 'code' => 200];
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log('unassignQuestionsFromPatient error: ' . $e->getMessage() . ' | User ID: ' . $user_id . ' | Question IDs: ' . implode(', ', $question_ids));
            return ['success' => false, 'message' => 'Database error occurred', 'code' => 500];
        }
    }

    // Get patient answers
    public function getPatientAnswers($user_id) {
        if (!is_int($user_id) || $user_id <= 0) {
            return ['success' => false, 'message' => 'Invalid user ID', 'code' => 400];
        }
        try {
            $stmt = $this->pdo->prepare("
                SELECT question_id, answer_text 
                FROM answers 
                WHERE user_id = ? AND status = 'active'
                AND created_at = (
                    SELECT MAX(created_at)
                    FROM answers a2
                    WHERE a2.user_id = answers.user_id
                    AND a2.question_id = answers.question_id
                    AND a2.status = 'active'
                )
            ");
            $stmt->execute([$user_id]);
            $answers = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $answers[$row['question_id']] = $row['answer_text'];
            }
            return $answers;
        } catch (PDOException $e) {
            error_log('getPatientAnswers error: ' . $e->getMessage() . ' | User ID: ' . $user_id);
            return ['success' => false, 'message' => 'Database error occurred', 'code' => 500];
        }
    }

public function saveAnswers($user_id, $answers, $files = [], $status = 'final', $shift_id = 1) {
    // Validate status
    $status = in_array($status, ['draft', 'final']) ? $status : 'final';
    $shift_id = (int)$shift_id > 0 ? (int)$shift_id : 1;

    // Validate session
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role'])) {
        return ['success' => false, 'message' => 'Session not initialized. Please log in.', 'code' => 401];
    }

    // Validate user ID
    if (!is_int($user_id) || $user_id <= 0) {
        return ['success' => false, 'message' => 'Invalid user ID', 'code' => 400];
    }

    // Check user permissions
    if (!$this->isPatient() && !$this->isStaff() && !$this->isAdmin()) {
        return ['success' => false, 'message' => 'Unauthorized', 'code' => 403];
    }

    // Sanitize and validate answers
    $all_answers = (array)$answers;
    if (empty($all_answers) && empty($files) && $status === 'final') {
        return ['success' => false, 'message' => 'No answers provided', 'code' => 400];
    }

    // Validation for mandatory fields
    if ($status === 'final') {
        try {
            $stmt = $this->pdo->prepare("
                SELECT q.question_id, q.question_text 
                FROM questions q 
                JOIN patient_questions pq ON q.question_id = pq.question_id 
                WHERE pq.user_id = ? AND q.is_mandatory = 1 AND q.status = 'active'
                AND (pq.shift_id = ? OR (pq.shift_id IS NULL AND q.shift_id = ?))
            ");
            $stmt->execute([$user_id, $shift_id, $shift_id]);
            $mandatory_questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($mandatory_questions as $mq) {
                $qid = $mq['question_id'];
                $has_val = false;
                if (isset($all_answers[$qid]) && !empty($all_answers[$qid])) $has_val = true;
                if (isset($files[$qid]) && !empty($files[$qid])) $has_val = true;

                if (!$has_val) {
                    return ['success' => false, 'message' => "Question is mandatory: " . $mq['question_text'], 'code' => 400];
                }
            }
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Validation error', 'code' => 500];
        }
    }

    try {
        $this->pdo->beginTransaction();

        // Get submission ID
        $submission_id = null;
        if ($status === 'draft') {
            // Check for existing draft for this user and shift
            $stmt = $this->pdo->prepare("SELECT submission_id FROM answer_logs WHERE user_id = ? AND shift_id = ? AND status = 'draft' ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$user_id, $shift_id]);
            $submission_id = $stmt->fetchColumn();
        }

        if (!$submission_id) {
            $stmt = $this->pdo->prepare("SELECT COALESCE(MAX(submission_id), 0) + 1 FROM answer_logs WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $submission_id = (int)$stmt->fetchColumn();
        }

        // If it's an existing submission, clear previous logs to avoid duplicates
        if ($submission_id) {
            $stmt = $this->pdo->prepare("DELETE FROM answer_logs WHERE submission_id = ? AND user_id = ?");
            $stmt->execute([$submission_id, $user_id]);
            
            // Should we delete files too? Probably not if they are reused, but usually a new file is uploaded.
            // For now let's just leave files as they are physically deleted or handled separately.
        }

        // Batch fetch all relevant question mapping and existing answers
        $qids = array_unique(array_merge(array_keys($all_answers), array_keys($files)));
        $qids = array_filter($qids, function($id) { return (int)$id > 0; });
        
        $question_types = [];
        $existing_answers = [];
        if (!empty($qids)) {
            $placeholders = implode(',', array_fill(0, count($qids), '?'));
            $stmt = $this->pdo->prepare("SELECT question_id, question_type FROM questions WHERE question_id IN ($placeholders) AND status = 'active'");
            $stmt->execute($qids);
            $question_types = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $stmt = $this->pdo->prepare("SELECT question_id, id FROM answers WHERE user_id = ? AND question_id IN ($placeholders)");
            $stmt->execute(array_merge([$user_id], $qids));
            $existing_answers = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        }

        $submitted_by = $_SESSION['user']['id'] ?? null;
        $log_inserts = [];
        $log_params = [];

        // Process text answers
        foreach ($all_answers as $question_id => $answer_text) {
            $question_id = (int)$question_id;
            if (!isset($question_types[$question_id])) continue;

            // Update or Insert into main answers table
            if (isset($existing_answers[$question_id])) {
                $stmt = $this->pdo->prepare("UPDATE answers SET answer_text = ?, submitted_by = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$answer_text, $submitted_by, $existing_answers[$question_id]]);
            } else {
                $stmt = $this->pdo->prepare("INSERT INTO answers (user_id, question_id, answer_text, status, submitted_by) VALUES (?, ?, ?, 'active', ?)");
                $stmt->execute([$user_id, $question_id, $answer_text, $submitted_by]);
            }

            // Prepare for batch log insert
            $log_inserts[] = "(?, ?, ?, ?, ?, NOW(), ?, ?)";
            array_push($log_params, $user_id, $question_id, $answer_text, $submission_id, $status, $submitted_by, $shift_id);
        }

        // Process file uploads
        foreach ($files as $question_id => $file) {
            $question_id = (int)$question_id;
            if ($question_id <= 0 || !isset($question_types[$question_id]) || $file['error'] !== UPLOAD_ERR_OK) continue;
            if (!in_array($question_types[$question_id], ['file', 'upload_image', 'upload_file'])) continue;

            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $safe_name = time() . '_' . $user_id . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
            $relative_path = 'public/Uploads/' . $safe_name;
            
            if (move_uploaded_file($file['tmp_name'], __DIR__ . '/../' . $relative_path)) {
                $stmt = $this->pdo->prepare("INSERT INTO files (user_id, question_id, submission_id, file_path, file_type, file_name, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
                $stmt->execute([$user_id, $question_id, $submission_id, $relative_path, $file['type'], $safe_name]);

                if (isset($existing_answers[$question_id])) {
                    $stmt = $this->pdo->prepare("UPDATE answers SET answer_text = ?, submitted_by = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$relative_path, $submitted_by, $existing_answers[$question_id]]);
                } else {
                    $stmt = $this->pdo->prepare("INSERT INTO answers (user_id, question_id, answer_text, status, submitted_by) VALUES (?, ?, ?, 'active', ?)");
                    $stmt->execute([$user_id, $question_id, $relative_path, $submitted_by]);
                }

                $log_inserts[] = "(?, ?, ?, ?, ?, NOW(), ?, ?)";
                array_push($log_params, $user_id, $question_id, $relative_path, $submission_id, $status, $submitted_by, $shift_id);
            }
        }

        // Bulk insert logs
        if (!empty($log_inserts)) {
            $sql = "INSERT INTO answer_logs (user_id, question_id, answer_text, submission_id, status, created_at, submitted_by, shift_id) VALUES " . implode(',', $log_inserts);
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($log_params);
        }

        $this->pdo->commit();

        // Feature 5: In-App Notification on Final Submission
        if ($status === 'final') {
            $this->triggerFormNotification($user_id, $submission_id, 'form_submitted', "Form submitted by patient", $shift_id);
        }

        $msg = $status === 'draft' ? "Progress saved as draft." : "Answers submitted successfully.";
        return ['success' => true, 'message' => $msg, 'submission_id' => $submission_id, 'code' => 200];
    } catch (PDOException $e) {
        $this->pdo->rollBack();
        return ['success' => false, 'message' => 'Database error occurred: ' . $e->getMessage(), 'code' => 500];
    } catch (Exception $e) {
        $this->pdo->rollBack();
        return ['success' => false, 'message' => 'An error occurred', 'code' => 500];
    }
}

    // In your controller or database class
public function getAnswerLogs($user_id, $page = 1, $limit = 10, $shift_id = null, $start_date = null, $end_date = null) {
    // Validate inputs
    if (!is_int($user_id) || $user_id <= 0) {
        error_log("Invalid user ID: $user_id");
        return ['success' => false, 'message' => 'Invalid user ID', 'code' => 400];
    }

    if (!is_int($page) || $page < 1) {
        error_log("Invalid page number: $page, defaulting to 1");
        $page = 1;
    }

    if (!is_int($limit) || $limit < 1) {
        error_log("Invalid limit: $limit, defaulting to 10");
        $limit = 10;
    }

    try {
        // Verify PDO connection
        if (!$this->pdo instanceof PDO) {
            error_log('PDO connection is not initialized');
            return ['success' => false, 'message' => 'Database connection failed', 'code' => 500];
        }

        // Calculate offset
        $offset = ($page - 1) * $limit;

        // Debug: Log query parameters
        error_log("Executing getAnswerLogs with user_id: $user_id, limit: $limit, offset: $offset");

        // Check if tables exist
        $table_check = $this->pdo->query("SHOW TABLES LIKE 'answer_logs'");
        if ($table_check->rowCount() === 0) {
            error_log("Table 'answer_logs' does not exist");
            return ['success' => false, 'message' => 'Table answer_logs not found', 'code' => 500];
        }

        // Total count for pagination
        $session_role = $_SESSION['user']['role'] ?? '';
        $session_user_id = $_SESSION['user']['id'] ?? 0;

        $where = "WHERE al.user_id = :user_id";
        $params = [':user_id' => (int)$user_id];

        if ($shift_id !== null && $shift_id !== '') {
            $where .= " AND al.shift_id = :shift_id";
            $params[':shift_id'] = (int)$shift_id;
        }

        if ($start_date) {
            $where .= " AND DATE(al.created_at) >= :start_date";
            $params[':start_date'] = $start_date;
        }
        if ($end_date) {
            $where .= " AND DATE(al.created_at) <= :end_date";
            $params[':end_date'] = $end_date;
        }

        $count_query = "SELECT COUNT(DISTINCT al.submission_id) FROM answer_logs al LEFT JOIN users su ON al.submitted_by = su.id $where";

        $stmt = $this->pdo->prepare($count_query);
        foreach ($params as $key => $val) {
            if (is_int($val)) {
                $stmt->bindValue($key, $val, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $val, PDO::PARAM_STR);
            }
        }
        $stmt->execute();
        $totalLogs = (int) $stmt->fetchColumn();

        // Also get total count for sequential numbering if needed
        $stmtTotal = $this->pdo->prepare("SELECT COUNT(DISTINCT submission_id) FROM answer_logs WHERE user_id = :user_id");
        $stmtTotal->bindValue(':user_id', (int)$user_id, PDO::PARAM_INT);
        $stmtTotal->execute();
        $overallTotal = (int) $stmtTotal->fetchColumn();

        $totalPages = max(1, ceil($totalLogs / $limit));

        // Fetch paginated logs
        $logs_query = "
            SELECT al.submission_id, MAX(al.created_at) as created_at, 
                   COALESCE(su.username, u.username) AS username,
                   su.role AS submitted_role,
                   nt.name AS shift_name
            FROM answer_logs al
            LEFT JOIN users u ON al.user_id = u.id
            LEFT JOIN users su ON al.submitted_by = su.id
            LEFT JOIN note_types nt ON al.shift_id = nt.id
            $where
            GROUP BY al.submission_id
            ORDER BY al.submission_id DESC, created_at DESC
            LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($logs_query);
        
        foreach ($params as $key => $val) {
            if (is_int($val)) {
                $stmt->bindValue($key, $val, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $val, PDO::PARAM_STR);
            }
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        error_log("Found " . count($logs) . " logs for user_id: $user_id");

        return [
            'success' => true,
            'data' => $logs,
            'totalLogs' => $totalLogs,
            'overallTotal' => $overallTotal,
            'itemsPerPage' => $limit,
            'currentPage' => $page,
            'totalPages' => $totalPages
        ];
    } catch (PDOException $e) {
        $error_message = 'getAnswerLogs error: ' . $e->getMessage() . 
                        ' | User ID: ' . $user_id . 
                        ' | Code: ' . $e->getCode() . 
                        ' | File: ' . $e->getFile() . 
                        ' | Line: ' . $e->getLine();
        error_log($error_message);
        return ['success' => false, 'message' => 'Database error occurred: ' . $e->getMessage(), 'code' => 500];
    } catch (Exception $e) {
        error_log('Unexpected error in getAnswerLogs: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Unexpected error occurred', 'code' => 500];
    }
}

public function getPatientSubmissionLogs($userId, $submissionId = null, $page = 1, $perPage = 10) {
        try {
            if (!is_int($userId) || $userId <= 0) {
                error_log("Invalid user ID: $userId");
                return ['success' => false, 'message' => 'Invalid user ID', 'code' => 400];
            }
            if ($submissionId !== null && (!is_int($submissionId) || $submissionId <= 0)) {
                error_log("Invalid submission ID: $submissionId");
                return ['success' => false, 'message' => 'Invalid submission ID', 'code' => 400];
            }
            if (!is_int($page) || $page < 1) {
                error_log("Invalid page number: $page, defaulting to 1");
                $page = 1;
            }
            if (!is_int($perPage) || $perPage < 1 || $perPage > 100) {
                error_log("Invalid perPage: $perPage, defaulting to 10");
                $perPage = 10;
            }

            $stmt = $this->pdo->prepare("SELECT status FROM users WHERE id = ? AND role = 'patient'");
            $stmt->execute([$userId]);
            $status = $stmt->fetchColumn();
            if ($status !== 'active') {
                error_log("User $userId is not an active patient");
                return ['success' => false, 'message' => 'Only active patients have accessible submission logs', 'code' => 403];
            }

            $offset = ($page - 1) * $perPage;
            $query = "
                SELECT al.log_id, al.submission_id, al.question_id, q.question_text, q.question_type, 
                       al.answer_text, al.created_at, al.status,
                       u_sub.username AS admin_name,
                       nt.name AS note_type_name,
                       f.file_id, f.file_path, f.file_type, f.file_name
                FROM answer_logs al
                LEFT JOIN questions q ON al.question_id = q.question_id
                LEFT JOIN users u_sub ON al.submitted_by = u_sub.id
                LEFT JOIN note_types nt ON al.shift_id = nt.id
                LEFT JOIN files f ON al.user_id = f.user_id AND al.question_id = f.question_id AND al.submission_id = f.submission_id
                WHERE al.user_id = :user_id AND q.status = 'active'
            ";
            $params = [':user_id' => $userId];

            if ($submissionId !== null) {
                $query .= " AND al.submission_id = :submission_id";
                $params[':submission_id'] = $submissionId;
                $query .= " ORDER BY al.log_id ASC LIMIT :offset, :perPage";
            } else {
                $query .= " ORDER BY al.submission_id DESC LIMIT :offset, :perPage";
            }

            error_log("Executing query: $query with params: " . json_encode($params));

            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            if ($submissionId !== null) {
                $stmt->bindValue(':submission_id', $submissionId, PDO::PARAM_INT);
            }
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
            $stmt->execute();
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Fetch options for each question for correct rendering
            foreach ($logs as &$log) {
                if (in_array($log['question_type'], ['dropdown', 'multidropdown', 'checkbox', 'radio'])) {
                    $optStmt = $this->pdo->prepare("SELECT option_text FROM question_options WHERE question_id = ? ORDER BY option_id");
                    $optStmt->execute([$log['question_id']]);
                    $log['options'] = $optStmt->fetchAll(PDO::FETCH_COLUMN);
                } else {
                    $log['options'] = [];
                }
            }
            error_log("Fetched logs with options: " . count($logs));

            $countQuery = "SELECT COUNT(*) FROM answer_logs al JOIN questions q ON al.question_id = q.question_id WHERE al.user_id = :user_id AND q.status = 'active'";
            $countParams = [':user_id' => $userId];
            if ($submissionId !== null) {
                $countQuery .= " AND al.submission_id = :submission_id";
                $countParams[':submission_id'] = $submissionId;
            }

            $countStmt = $this->pdo->prepare($countQuery);
            foreach ($countParams as $key => $value) {
                $countStmt->bindValue($key, $value, PDO::PARAM_INT);
            }
            $countStmt->execute();
            $totalLogs = (int)$countStmt->fetchColumn();
            $totalPages = max(1, ceil($totalLogs / $perPage));

            return [
                'success' => true,
                'data' => $logs,
                'totalLogs' => $totalLogs,
                'totalPages' => $totalPages,
                'currentPage' => $page
            ];
        } catch (PDOException $e) {
            $error_message = 'getPatientSubmissionLogs error: ' . $e->getMessage() . 
                            ' | User ID: ' . $userId . 
                            ' | Submission ID: ' . ($submissionId ?? 'null') . 
                            ' | Code: ' . $e->getCode();
            error_log($error_message);
            return ['success' => false, 'message' => 'Database error occurred: ' . $e->getMessage(), 'code' => 500];
        }
    }


    // Using parent's getNoteTypes

    private function triggerFormNotification($patient_id, $submission_id, $type, $message_prefix, $note_type_id) {
        try {
            require_once 'NotificationController.php';
            $notifCtrl = new NotificationController($this->pdo);

            // Get patient name
            $stmt = $this->pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$patient_id]);
            $patientName = $stmt->fetchColumn() ?: 'Unknown';

            // Get actor (for form submissions, it's usually the patient themselves)
            $actor_id = $_SESSION['user']['id'];

            $fullMessage = "$message_prefix: $patientName";

            // 1. Notify all admins (including the one who made the change)
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE role = 'admin'");
            $stmt->execute();
            $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($admins as $adminId) {
                $notifCtrl->addNotification($adminId, $actor_id, $type, $fullMessage, $submission_id);
            }

            // 2. Notify all assigned staff members for this patient (including the one who made the change)
            $stmt = $this->pdo->prepare("SELECT staff_id FROM staff_patient_assignments WHERE patient_id = ?");
            $stmt->execute([$patient_id]);
            $assignedStaff = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($assignedStaff as $staffId) {
                $notifCtrl->addNotification($staffId, $actor_id, $type, $fullMessage, $submission_id);
            }

        } catch (Exception $e) {
            error_log("Form notification trigger failed: " . $e->getMessage());
        }
    }



    public function __destruct() {
        // PDO closes connection automatically
    }
}
?>