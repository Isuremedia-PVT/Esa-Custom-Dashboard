<?php
session_start();
require_once './config/database.php';

// Check if user is logged in and is admin/staff
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] !== 'admin' && $_SESSION['user']['role'] !== 'staff')) {
    die("Unauthorized access.");
}

$user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
$note_type_id = filter_input(INPUT_GET, 'note_type_id', FILTER_VALIDATE_INT);

if (!$user_id) {
    die("Invalid Patient ID");
}

try {
    // Get patient name
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $patient_name = $stmt->fetchColumn() ?: "Patient_$user_id";

    // Build query
    $query = "
        SELECT al.submission_id, nt.name as note_type, q.question_text, al.answer_text, al.status, al.created_at
        FROM answer_logs al
        JOIN questions q ON al.question_id = q.question_id
        LEFT JOIN note_types nt ON al.shift_id = nt.id
        WHERE al.user_id = ?
    ";
    $params = [$user_id];

    if ($note_type_id) {
        $query .= " AND al.shift_id = ?";
        $params[] = $note_type_id;
    }

    $query .= " ORDER BY al.submission_id DESC, al.created_at ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($results)) {
        die("No data found to export.");
    }

    // Set headers for CSV download
    $filename = "Export_{$patient_name}_" . date('Y-m-d_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $output = fopen('php://output', 'w');

    // Add UTF-8 BOM for Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Headers
    fputcsv($output, ['Submission ID', 'Note Type', 'Question', 'Answer', 'Status', 'Date/Time']);

    foreach ($results as $row) {
        fputcsv($output, [
            $row['submission_id'],
            $row['note_type'] ?: 'General',
            $row['question_text'],
            $row['answer_text'],
            ucfirst($row['status']),
            $row['created_at']
        ]);
    }

    fclose($output);
    exit;

} catch (PDOException $e) {
    die("Export failed: " . $e->getMessage());
}
