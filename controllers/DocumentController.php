<?php
require_once __DIR__ . '/../config/database.php';

class DocumentController {
    protected $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getDocuments($patient_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM patient_documents WHERE patient_id = ? ORDER BY created_at DESC");
            $stmt->execute([(int)$patient_id]);
            return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
        } catch (PDOException $e) {
            error_log("getDocuments error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function uploadDocument($patient_id, $file, $document_name, $category = 'Other', $description = '') {
        try {
            error_log("Starting uploadDocument for patient_id: $patient_id, file: " . $file['name']);
            if ($file['error'] !== UPLOAD_ERR_OK) {
                error_log("Upload error code: " . $file['error']);
                return ['success' => false, 'message' => 'Upload error: ' . $file['error']];
            }

            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $fileName = uniqid('doc_', true) . '.' . $extension;
            $uploadDir = __DIR__ . '/../public/uploads/documents/';
            
            if (!is_dir($uploadDir)) {
                error_log("Creating upload directory: $uploadDir");
                if (!mkdir($uploadDir, 0777, true)) {
                    error_log("Failed to create upload directory");
                    return ['success' => false, 'message' => 'Failed to create upload directory.'];
                }
            }

            $uploadPath = $uploadDir . $fileName;
            $relativeUrl = 'public/uploads/documents/' . $fileName;
            $uploaded_by = $_SESSION['user']['id'] ?? 0;

            error_log("Moving uploaded file to: $uploadPath");
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                error_log("File moved successfully. Inserting into DB.");
                $stmt = $this->pdo->prepare("INSERT INTO patient_documents (patient_id, uploaded_by, file_name, file_path, file_type, category, description, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([
                    (int)$patient_id,
                    (int)$uploaded_by,
                    $document_name ?: $file['name'],
                    $relativeUrl,
                    $file['type'],
                    $category,
                    $description
                ]);

                error_log("Document upload complete.");
                return ['success' => true, 'message' => 'Document uploaded successfully.'];
            } else {
                error_log("Failed to move uploaded file from " . $file['tmp_name'] . " to " . $uploadPath);
                return ['success' => false, 'message' => 'Failed to move uploaded file.'];
            }
        } catch (PDOException $e) {
            error_log("uploadDocument error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function deleteDocument($document_id) {
        try {
            // First get the file URL to delete the physical file
            $stmt = $this->pdo->prepare("SELECT file_path FROM patient_documents WHERE id = ?");
            $stmt->execute([(int)$document_id]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($doc) {
                $filePath = __DIR__ . '/../' . $doc['file_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }

                $stmt = $this->pdo->prepare("DELETE FROM patient_documents WHERE id = ?");
                $stmt->execute([(int)$document_id]);

                return ['success' => true, 'message' => 'Document deleted successfully.'];
            }
            return ['success' => false, 'message' => 'Document not found.'];
        } catch (PDOException $e) {
            error_log("deleteDocument error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}
