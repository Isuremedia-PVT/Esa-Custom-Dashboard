<?php
require_once './config/database.php';

class ShiftController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getAllShifts($page = 1, $perPage = 10, $search = '') {
        try {
            $offset = ($page - 1) * $perPage;
            $searchCondition = '';
            $params = [];
            if (!empty($search)) {
                $searchCondition = " WHERE name LIKE :search OR description LIKE :search";
                $params[':search'] = '%' . $search . '%';
            }

            $query = "SELECT COUNT(*) FROM note_types $searchCondition";
            $stmt = $this->pdo->prepare($query);
            if (!empty($search)) {
                $stmt->bindValue(':search', $params[':search'], PDO::PARAM_STR);
            }
            $stmt->execute();
            $total = $stmt->fetchColumn();

            $query = "SELECT id, name, description, created_at FROM note_types $searchCondition ORDER BY id DESC LIMIT :offset, :perPage";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->bindValue(':perPage', (int)$perPage, PDO::PARAM_INT);
            if (!empty($search)) {
                $stmt->bindValue(':search', $params[':search'], PDO::PARAM_STR);
            }
            $stmt->execute();
            $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'shifts' => $shifts ?: [],
                'total' => $total,
                'currentPage' => $page
            ];
        } catch (PDOException $e) {
            return ['errors' => [$e->getMessage()]];
        }
    }

    public function createShift($data) {
        $errors = [];
        if (empty($data['name'])) {
            $errors[] = "Name is required.";
        }

        if (empty($errors)) {
            try {
                $stmt = $this->pdo->prepare("INSERT INTO note_types (name, description) VALUES (?, ?)");
                $stmt->execute([$data['name'], $data['description'] ?? '']);
                return ['success' => 'Shift created successfully'];
            } catch (PDOException $e) {
                return ['errors' => [$e->getMessage()]];
            }
        }
        return ['errors' => $errors];
    }

    public function updateShift($id, $data) {
        $errors = [];
        if (empty($data['name'])) {
            $errors[] = "Name is required.";
        }

        if (empty($errors)) {
            try {
                $stmt = $this->pdo->prepare("UPDATE note_types SET name = ?, description = ? WHERE id = ?");
                $stmt->execute([$data['name'], $data['description'] ?? '', $id]);
                return ['success' => 'Shift updated successfully'];
            } catch (PDOException $e) {
                return ['errors' => [$e->getMessage()]];
            }
        }
        return ['errors' => $errors];
    }

    public function deleteShift($id) {
        try {
            // Check if any questions are using this note type
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM questions WHERE note_type_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                return ['errors' => ["Cannot delete shift as it is assigned to questions."]];
            }

            $stmt = $this->pdo->prepare("DELETE FROM note_types WHERE id = ?");
            $stmt->execute([$id]);
            return ['success' => 'Shift deleted successfully'];
        } catch (PDOException $e) {
            return ['errors' => [$e->getMessage()]];
        }
    }
}
