<?php
require_once './config/database.php';

class QuestionController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getQuestionTypes() {
        try {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM questions WHERE Field = 'question_type'");
            $column = $stmt->fetch(PDO::FETCH_ASSOC);
            // Extract ENUM values from the Type column (e.g., "enum('text','textarea',...)"
            preg_match("/^enum\((.*)\)$/", $column['Type'], $matches);
            $enumValues = array_map(function($value) {
                return trim($value, "'");
            }, explode(',', $matches[1]));
            return $enumValues;
        } catch (PDOException $e) {
            return ['errors' => ['Failed to fetch question types: ' . $e->getMessage()]];
        }
    }

    public function getAllQuestions($page = 1, $perPage = 10, $search = '', $statusFilter = '') {
        try {
            $offset = ($page - 1) * $perPage;
            
            $conditions = "WHERE question_text LIKE :search";
            $params = [':search' => '%' . $search . '%'];

            if (!empty($statusFilter)) {
                $conditions .= " AND status = :status";
                $params[':status'] = $statusFilter;
            }

            // ORDER BY: If status filtered, newest first. If not, active first then newest.
            $order = !empty($statusFilter) ? "created_at DESC" : "FIELD(status, 'active', 'inactive') DESC, created_at DESC";

            // Prepare the query with search and status conditions
            $query = "SELECT * FROM questions $conditions ORDER BY $order LIMIT :limit OFFSET :offset";
            $stmt = $this->pdo->prepare($query);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch options for each question
            foreach ($questions as &$question) {
                if (in_array($question['question_type'], ['dropdown', 'multidropdown', 'checkbox', 'radio'])) {
                    $stmt = $this->pdo->prepare("SELECT option_text FROM question_options WHERE question_id = ?");
                    $stmt->execute([$question['question_id']]);
                    $question['options'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
                } else {
                    $question['options'] = [];
                }
            }

            // Get total count for pagination
            $countQuery = "SELECT COUNT(*) FROM questions $conditions";
            $countStmt = $this->pdo->prepare($countQuery);
            foreach ($params as $key => $val) {
                $countStmt->bindValue($key, $val, PDO::PARAM_STR);
            }
            $countStmt->execute();
            $totalQuestions = $countStmt->fetchColumn();

            return [
                'questions' => $questions,
                'total' => $totalQuestions,
                'perPage' => $perPage,
                'currentPage' => $page,
                'search' => $search
            ];
        } catch (PDOException $e) {
            return ['errors' => ['Failed to fetch questions: ' . $e->getMessage()]];
        }
    }

    public function getQuestion($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM questions WHERE question_id = ?");
            $stmt->execute([$id]);
            $question = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($question && in_array($question['question_type'], ['dropdown', 'multidropdown', 'checkbox', 'radio'])) {
                $stmt = $this->pdo->prepare("SELECT option_text FROM question_options WHERE question_id = ?");
                $stmt->execute([$id]);
                $question['options'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } else {
                $question['options'] = [];
            }
            return $question ?: ['errors' => ['Question not found.']];
        } catch (PDOException $e) {
            return ['errors' => ['Failed to fetch question: ' . $e->getMessage()]];
        }
    }

    public function createQuestion($data) {
        try {
            // Validate question text
            if (empty($data['question_text'])) {
                return ['errors' => ['Question text is required.']];
            }

            // Validate question type
            $validTypes = $this->getQuestionTypes();
            if (isset($validTypes['errors'])) {
                return $validTypes;
            }
            if (empty($data['question_type']) || !in_array($data['question_type'], $validTypes)) {
                return ['errors' => ['Invalid question type.']];
            }

            // Validate options
            if (in_array($data['question_type'], ['dropdown', 'multidropdown', 'checkbox', 'radio']) && empty($data['options'])) {
                return ['errors' => ['At least one option is required for ' . $data['question_type'] . ' questions.']];
            }
            if (in_array($data['question_type'], ['text', 'textarea', 'textbox_list', 'upload_image', 'upload_file']) && !empty($data['options'])) {
                return ['errors' => ['Options are not allowed for ' . $data['question_type'] . ' questions.']];
            }

            $this->pdo->beginTransaction();

            // Insert question
            $stmt = $this->pdo->prepare("INSERT INTO questions (question_text, question_type, is_mandatory, status, created_at, updated_at) VALUES (?, ?, ?, 'active', NOW(), NOW())");
            $stmt->execute([$data['question_text'], $data['question_type'], $data['is_mandatory'] ?? 0]);
            $question_id = $this->pdo->lastInsertId();

            // Insert options for dropdown, multidropdown, checkbox, or radio
            if (in_array($data['question_type'], ['dropdown', 'multidropdown', 'checkbox', 'radio']) && !empty($data['options'])) {
                $stmt = $this->pdo->prepare("INSERT INTO question_options (question_id, option_text) VALUES (?, ?)");
                foreach ($data['options'] as $option) {
                    $stmt->execute([$question_id, $option]);
                }
            }

            $this->pdo->commit();
            return ['success' => 'Question created successfully.'];
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['errors' => ['Database error: ' . $e->getMessage()]];
        }
    }

    public function updateQuestion($id, $data) {
        try {
            // Validate question text
            if (empty($data['question_text'])) {
                return ['errors' => ['Question text is required.']];
            }

            // Validate question type
            $validTypes = $this->getQuestionTypes();
            if (isset($validTypes['errors'])) {
                return $validTypes;
            }
            if (empty($data['question_type']) || !in_array($data['question_type'], $validTypes)) {
                return ['errors' => ['Invalid question type.']];
            }

            // Validate options
            if (in_array($data['question_type'], ['dropdown', 'multidropdown', 'checkbox', 'radio']) && empty($data['options'])) {
                return ['errors' => ['At least one option is required for ' . $data['question_type'] . ' questions.']];
            }
            if (in_array($data['question_type'], ['text', 'textarea', 'textbox_list', 'upload_image', 'upload_file']) && !empty($data['options'])) {
                return ['errors' => ['Options are not allowed for ' . $data['question_type'] . ' questions.']];
            }

            $question = $this->getQuestion($id);
            if (isset($question['errors'])) {
                return $question;
            }
            if ($question['status'] == 'inactive') {
                return ['errors' => ['Cannot update an inactive question.']];
            }

            $this->pdo->beginTransaction();

            // Update question
            $stmt = $this->pdo->prepare("UPDATE questions SET question_text = ?, question_type = ?, is_mandatory = ?, updated_at = NOW() WHERE question_id = ?");
            $stmt->execute([$data['question_text'], $data['question_type'], $data['is_mandatory'] ?? 0, $id]);

            // Delete existing options
            $stmt = $this->pdo->prepare("DELETE FROM question_options WHERE question_id = ?");
            $stmt->execute([$id]);

            // Insert new options
            if (in_array($data['question_type'], ['dropdown', 'multidropdown', 'checkbox', 'radio']) && !empty($data['options'])) {
                $stmt = $this->pdo->prepare("INSERT INTO question_options (question_id, option_text) VALUES (?, ?)");
                foreach ($data['options'] as $option) {
                    $stmt->execute([$id, $option]);
                }
            }

            $this->pdo->commit();
            return ['success' => 'Question updated successfully.'];
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['errors' => ['Database error: ' . $e->getMessage()]];
        }
    }

    public function deleteQuestion($id) {
        try {
            $question = $this->getQuestion($id);
            if (isset($question['errors'])) {
                return $question;
            }
            if ($question['status'] == 'inactive') {
                return ['errors' => ['Cannot delete an inactive question.']];
            }
            $stmt = $this->pdo->prepare("UPDATE questions SET status = 'inactive', updated_at = NOW() WHERE question_id = ?");
            $stmt->execute([$id]);
            if ($stmt->rowCount() > 0) {
                // Delete options
                $stmt = $this->pdo->prepare("DELETE FROM question_options WHERE question_id = ?");
                $stmt->execute([$id]);
                return ['success' => 'Question marked as inactive successfully.'];
            }
            return ['errors' => ['Question not found.']];
        } catch (PDOException $e) {
            return ['errors' => ['Database error: ' . $e->getMessage()]];
        }
    }

    public function activateQuestion($id) {
        try {
            $question = $this->getQuestion($id);
            if (isset($question['errors'])) {
                return $question;
            }
            if ($question['status'] == 'active') {
                return ['errors' => ['Cannot activate an already active question.']];
            }
            $stmt = $this->pdo->prepare("UPDATE questions SET status = 'active', updated_at = NOW() WHERE question_id = ?");
            $stmt->execute([$id]);
            if ($stmt->rowCount() > 0) {
                return ['success' => 'Question activated successfully.'];
            }
            return ['errors' => ['Question not found.']];
        } catch (PDOException $e) {
            return ['errors' => ['Database error: ' . $e->getMessage()]];
        }
    }
}
?>