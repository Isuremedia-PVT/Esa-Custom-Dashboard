<?php
session_start();
require_once './config/database.php';
require_once './controllers/PatientController.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die("Unauthorized access.");
}

$controller = new PatientController($pdo);
$data = $controller->getPatients(1, 10000); // Get all patients (large limit)
$patients = $data['patients'];

if (empty($patients)) {
    die("No data to export.");
}

$filename = "All_Patients_" . date('Y-m-d') . ".csv";

// Set headers for download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Output the column headings
fputcsv($output, ['ID', 'Username', 'Email', 'Phone', 'Status']);

// Loop over the data and output it
foreach ($patients as $patient) {
    fputcsv($output, [
        $patient['id'],
        $patient['username'],
        $patient['email'],
        $patient['phone'],
        $patient['status']
    ]);
}

fclose($output);
exit;
?>
