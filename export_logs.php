<?php
session_start();
require_once './config/database.php';
require_once './controllers/PatientDiseaseController.php';

// Check if user is logged in and authorized
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] !== 'admin' && $_SESSION['user']['role'] !== 'staff')) {
    die("Unauthorized access.");
}

$user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
$submission_id = filter_input(INPUT_GET, 'submission_id', FILTER_VALIDATE_INT);

if (!$user_id) {
    die("Invalid Patient ID.");
}

$controller = new PatientDiseaseController($pdo);
$logs = $controller->getPatientSubmissionLogs($user_id, $submission_id, 1, 10000); // Get all logs

if (!$logs['success'] || empty($logs['data'])) {
    die("No data to export.");
}

// Fetch user details for the filename
$stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$patientName = $user ? str_replace(' ', '_', $user['username']) : 'Patient';

$filename = $patientName . "_Logs_" . date('Y-m-d') . ".csv";

// Set headers for download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Output the column headings
fputcsv($output, ['Log ID', 'Admin/Staff', 'Question', 'Answer', 'Type', 'Status', 'Date']);

// Loop over the data and output it
foreach ($logs['data'] as $log) {
    fputcsv($output, [
        $log['log_id'],
        $log['admin_name'],
        $log['question_text'],
        $log['answer_text'],
        $log['note_type_name'] ?? 'N/A',
        $log['status'],
        $log['created_at']
    ]);
}

fclose($output);
exit;
?>
