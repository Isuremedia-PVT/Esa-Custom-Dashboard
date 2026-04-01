<?php
require_once './config/database.php';

class DashboardController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getPatients($filters = []) {
        try {
            $query = "SELECT id, username, email, phone, profile_url, status 
                      FROM users 
                      WHERE role = :role";
            $stmt = $this->pdo->prepare($query);
            $role = $filters['role'] ?? 'patient'; // Assign to variable
            $stmt->bindParam(':role', $role, PDO::PARAM_STR);
            $stmt->execute();
            $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $patients ?: [];
        } catch (PDOException $e) {
            error_log("getPatients failed: " . $e->getMessage());
            return false;
        }
    }

    public function getPatientsPaginated($filters = [], $offset, $perPage) {
        try {
            $query = "SELECT id, username, email, phone, profile_url, status 
                      FROM users 
                      WHERE role = :role";
            $role = $filters['role'] ?? 'patient'; // Assign to variable
            $query .= " LIMIT :offset, :perPage";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':role', $role, PDO::PARAM_STR);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->bindValue(':perPage', (int)$perPage, PDO::PARAM_INT);
            $stmt->execute();
            $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $patients ?: [];
        } catch (PDOException $e) {
            error_log("getPatientsPaginated failed: " . $e->getMessage());
            return false;
        }
    }

    public function getStaff($filters = []) {
        try {
            $query = "SELECT id, username, email, phone, profile_url, status 
                      FROM users 
                      WHERE role = :role";
            $stmt = $this->pdo->prepare($query);
            $role = $filters['role'] ?? 'staff'; // Assign to variable
            $stmt->bindParam(':role', $role, PDO::PARAM_STR);
            $stmt->execute();
            $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $staff ?: [];
        } catch (PDOException $e) {
            error_log("getStaff failed: " . $e->getMessage());
            return false;
        }
    }

    public function getStaffPaginated($filters = [], $offset, $perPage) {
        try {
            $query = "SELECT id, username, email, phone, profile_url, status 
                      FROM users 
                      WHERE role = :role";
            $role = $filters['role'] ?? 'staff'; // Assign to variable
            $query .= " LIMIT :offset, :perPage";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':role', $role, PDO::PARAM_STR);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->bindValue(':perPage', (int)$perPage, PDO::PARAM_INT);
            $stmt->execute();
            $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $staff ?: [];
        } catch (PDOException $e) {
            error_log("getStaffPaginated failed: " . $e->getMessage());
            return false;
        }
    }

    public function getAllQuestions() {
        try {
            $stmt = $this->pdo->prepare("SELECT question_id, question_text, status, created_at, updated_at 
                                         FROM questions 
                                         WHERE status = 'active'");
            $stmt->execute();
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $questions ?: [];
        } catch (PDOException $e) {
            error_log("getAllQuestions failed: " . $e->getMessage());
            return false;
        }
    }

    public function getAllQuestionsPaginated($offset, $perPage) {
        try {
            $stmt = $this->pdo->prepare("SELECT question_id, question_text, status, created_at, updated_at 
                                         FROM questions 
                                         WHERE status = 'active' 
                                         LIMIT :offset, :perPage");
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->bindValue(':perPage', (int)$perPage, PDO::PARAM_INT);
            $stmt->execute();
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $questions ?: [];
        } catch (PDOException $e) {
            error_log("getAllQuestionsPaginated failed: " . $e->getMessage());
            return false;
        }
    }

    public function getPatientDetails($user_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT id, username, email, phone, profile_url, status 
                                         FROM users 
                                         WHERE id = ? AND status = 'active'");
            $stmt->execute([(int)$user_id]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            return $patient ?: [];
        } catch (PDOException $e) {
            error_log("getPatientDetails failed: " . $e->getMessage());
            return false;
        }
    }

    public function getUserDiseases($user_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT name, date_diagnosed 
                                         FROM patient_diseases 
                                         WHERE user_id = ? AND status = 'active'");
            $stmt->execute([(int)$user_id]);
            $diseases = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $diseases ?: [];
        } catch (PDOException $e) {
            error_log("getUserDiseases failed: " . $e->getMessage());
            return false;
        }
    }

    public function getAnswersByUser($user_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT question_id, answer_text 
                                         FROM answers 
                                         WHERE user_id = ? AND status = 'active'");
            $stmt->execute([(int)$user_id]);
            $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $answers ?: [];
        } catch (PDOException $e) {
            error_log("getAnswersByUser failed: " . $e->getMessage());
            return false;
        }
    }

    public function getQuestionById($question_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT question_id, question_text, status, created_at, updated_at 
                                         FROM questions 
                                         WHERE question_id = ? AND status = 'active'");
            $stmt->execute([(int)$question_id]);
            $question = $stmt->fetch(PDO::FETCH_ASSOC);
            return $question ?: [];
        } catch (PDOException $e) {
            error_log("getQuestionById failed: " . $e->getMessage());
            return false;
        }
    }
}
?>