<?php
// Function to load .env variables
if (!function_exists('loadEnv')) {
    function loadEnv($path) {
        if (!file_exists($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            
            // Handle lines without '=' gracefully
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                    putenv(sprintf('%s=%s', $name, $value));
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
    }
}

loadEnv(__DIR__ . '/../.env');

$db_hostname = getenv('DB_HOST') ?: "localhost";
$db_user     = getenv('DB_USER') ?: "root";
$db_pass     = getenv('DB_PASS') ?: "";
$db_database = getenv('DB_NAME') ?: "patient_record_management";

$dsn = "mysql:host=$db_hostname;dbname=$db_database;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}
