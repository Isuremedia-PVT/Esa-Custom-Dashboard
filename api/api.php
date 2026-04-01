<?php
require_once '../middleware/auth_check.php';
header('Content-Type: application/json');
require_once '../controllers/PatientDiseaseController.php';

// Helper function for permission checks
function checkUserPermission($controller, $request_user_id, $session_user_id, $role) {
    if ($role === 'patient' && $request_user_id !== $session_user_id && !$controller->isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit;
    }
}

// Validate session data
if (!isset($_SESSION['user']['id']) || !isset($_SESSION['user']['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}
$session_user_id = $_SESSION['user']['id'];

// Initialize database connection
try {
    $controller = new PatientDiseaseController($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Handle CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// API Endpoints
$request_method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'get_user_details':
        if ($request_method !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }
        $request_user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
        if ($request_user_id === false || $request_user_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            exit;
        }
        checkUserPermission($controller, $request_user_id, $session_user_id, $_SESSION['user']['role']);

        // Block access to inactive patient profiles (except for admins)
        if (!$controller->isUserActive($request_user_id) && !$controller->isAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied. This patient is inactive.']);
            exit;
        }
        error_log("Fetching details for user_id: $request_user_id");
        $details = $controller->getUserDetails($request_user_id);
        if ($details) {
            echo json_encode(['success' => true, 'data' => $details]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
        break;

    case 'get_questions':
        if ($request_method !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }
        error_log("Fetching questions");
        $note_type_id = filter_input(INPUT_GET, 'note_type_id', FILTER_VALIDATE_INT);
        $questions = $controller->getAllQuestions($note_type_id);
        echo json_encode($questions);
        break;

    case 'get_patient_questions':
        if ($request_method !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }
        $request_user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
        if ($request_user_id === false || $request_user_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            exit;
        }
        checkUserPermission($controller, $request_user_id, $session_user_id, $_SESSION['user']['role']);
        error_log("Fetching questions for user_id: $request_user_id");
        // Accept both shift_id (new) and note_type_id (legacy) param names
        $note_type_id = filter_input(INPUT_GET, 'shift_id', FILTER_VALIDATE_INT);
        if (!$note_type_id) {
            $note_type_id = filter_input(INPUT_GET, 'note_type_id', FILTER_VALIDATE_INT);
        }
        $questions = $controller->getPatientQuestions($request_user_id, $note_type_id);
        if (isset($questions['success']) && $questions['success'] === false) {
            http_response_code($questions['code']);
            echo json_encode($questions);
            exit;
        }
        $answers = $controller->getPatientAnswers($request_user_id);
        if (isset($answers['success']) && $answers['success'] === false) {
            http_response_code($answers['code']);
            echo json_encode($answers);
            exit;
        }
        $result = array_map(function($question) use ($answers) {
            $question['answer_text'] = isset($answers[$question['question_id']]) ? $answers[$question['question_id']] : '';
            return $question;
        }, $questions);
        http_response_code(200);
        echo json_encode($result);
        break;

    case 'add_question':
        if ($request_method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }
        if (!$controller->isAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
        // Validate CSRF token
        if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        error_log("Adding question: " . print_r($data, true));
        $result = $controller->addQuestion($data);
        if (isset($result['success']) && $result['success']) {
            http_response_code($result['code'] ?? 200);
            echo json_encode($result);
        } else {
            http_response_code(isset($result['code']) ? $result['code'] : 400);
            echo json_encode([
                'success' => false,
                'message' => isset($result['errors']) ? implode(', ', $result['errors']) : ($result['message'] ?? 'Failed to add question')
            ]);
        }
        break;

    case 'assign_questions':
        if ($request_method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }
        if (!$controller->isAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
        // Validate CSRF token
        if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        error_log("Assigning questions: " . print_r($data, true));
        if (!is_array($data) || !isset($data['user_id']) || !isset($data['question_ids'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing user_id or question_ids']);
            exit;
        }
        $request_user_id = filter_var($data['user_id'], FILTER_VALIDATE_INT);
        $question_ids = array_filter(array_map('intval', (array)$data['question_ids']));
        if ($request_user_id === false || $request_user_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            exit;
        }
        $shift_id = (!empty($data['shift_id'])) ? filter_var($data['shift_id'], FILTER_VALIDATE_INT) : null;
        if ($shift_id === false) $shift_id = null;
        $result = $controller->assignQuestionsToPatient($request_user_id, $question_ids, $shift_id);
        http_response_code($result['code']);
        echo json_encode($result);
        break;

    case 'unassign_questions':
        if ($request_method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }
        if (!$controller->isAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
        // Validate CSRF token
        if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        error_log("Unassigning questions: " . print_r($data, true));
        if (!is_array($data) || !isset($data['user_id']) || !isset($data['question_ids'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing user_id or question_ids']);
            exit;
        }
        $request_user_id = filter_var($data['user_id'], FILTER_VALIDATE_INT);
        $question_ids = array_filter(array_map('intval', (array)$data['question_ids']));
        if ($request_user_id === false || $request_user_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            exit;
        }
        if (empty($question_ids)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No valid question IDs provided']);
            exit;
        }
        $result = $controller->unassignQuestionsFromPatient($request_user_id, $question_ids);
        http_response_code($result['code']);
        echo json_encode($result);
        break;

    case 'save_answers':
        if ($request_method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }
        // Validate CSRF token
        if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }
        $request_user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        if ($request_user_id === false || $request_user_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            exit;
        }
        checkUserPermission($controller, $request_user_id, $session_user_id, $_SESSION['user']['role']);
        
        // Block actions for inactive patients
        if (!$controller->isUserActive($request_user_id)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Cannot perform actions on an inactive patient.']);
            exit;
        }
        $answers = json_decode($_POST['answers'] ?? '{}', true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid JSON input for answers', 'error' => json_last_error_msg()]);
            exit;
        }
        if (!is_array($answers)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid answers format']);
            exit;
        }
        $files = [];
        if (isset($_FILES['file_answers']) && is_array($_FILES['file_answers']['name'])) {
            foreach ($_FILES['file_answers']['name'] as $question_id => $name) {
                if ($_FILES['file_answers']['error'][$question_id] === UPLOAD_ERR_OK) {
                    $files[$question_id] = [
                        'name' => $name,
                        'type' => $_FILES['file_answers']['type'][$question_id],
                        'tmp_name' => $_FILES['file_answers']['tmp_name'][$question_id],
                        'error' => $_FILES['file_answers']['error'][$question_id],
                        'size' => $_FILES['file_answers']['size'][$question_id]
                    ];
                }
            }
        }
        $status = $_POST['status'] ?? 'final';
        // Accept both note_type_id and shift_id (new name) from POST
        $note_type_id = !empty($_POST['shift_id']) ? (int)$_POST['shift_id'] : (!empty($_POST['note_type_id']) ? (int)$_POST['note_type_id'] : 1);
        if ($note_type_id <= 0) $note_type_id = 1;
        error_log("Saving answers for user_id: $request_user_id, status: $status, note_type_id: $note_type_id, files: " . print_r($files, true));
        $result = $controller->saveAnswers($request_user_id, $answers, $files, $status, $note_type_id);
        http_response_code($result['code']);
        echo json_encode($result);
        break;

    case 'get_answer_logs':
        if ($request_method !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }
        $request_user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
        $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 10;
        $shift_id = isset($_GET['shift_id']) && $_GET['shift_id'] !== '' ? (int)$_GET['shift_id'] : null;
        $start_date = $_GET['start_date'] ?? null;
        $end_date = $_GET['end_date'] ?? null;
        
        if ($request_user_id === false || $request_user_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            exit;
        }
        checkUserPermission($controller, $request_user_id, $session_user_id, $_SESSION['user']['role']);

        // Block access to inactive patient profiles (except for administrators)
        if (!$controller->isUserActive($request_user_id) && !$controller->isAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied. This patient is inactive.']);
            exit;
        }
        
        // Validate CSRF token
        if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }
        
        $logs = $controller->getAnswerLogs($request_user_id, $page, $limit, $shift_id, $start_date, $end_date);
        http_response_code($logs['success'] ? 200 : $logs['code']);
        echo json_encode($logs);
        break;

    case 'get_question_types':
        if ($request_method !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }
        if (!$controller->isAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
        error_log("Fetching question types");
        $questionTypes = $controller->getQuestionTypes();
        if (isset($questionTypes['success']) && !$questionTypes['success']) {
            http_response_code($questionTypes['code']);
            echo json_encode($questionTypes);
        } else {
            echo json_encode(['success' => true, 'data' => $questionTypes]);
        }
        break;



    case 'view_logs':
        if ($request_method !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }
        $request_user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
        $submission_id = filter_input(INPUT_GET, 'submission_id', FILTER_VALIDATE_INT);
        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
        $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT, ['options' => ['default' => 10, 'min_range' => 1, 'max_range' => 100]]);
        if ($request_user_id === false || $request_user_id === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid user_id']);
            exit;
        }
        if ($submission_id !== null && ($submission_id === false || $submission_id <= 0)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid submission_id']);
            exit;
        }
        checkUserPermission($controller, $request_user_id, $session_user_id, $_SESSION['user']['role']);

        // Block access to inactive patient profiles (except for administrators)
        if (!$controller->isUserActive($request_user_id) && !$controller->isAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied. This patient is inactive.']);
            exit;
        }
        error_log("Viewing logs for user_id: $request_user_id, submission_id: $submission_id, page: $page, limit: $limit");
        $result = $controller->getPatientSubmissionLogs($request_user_id, $submission_id, $page, $limit);
        if (!$result['success']) {
            http_response_code($result['code'] ?? 400);
            echo json_encode(['success' => false, 'message' => $result['message']]);
        } else {
            echo json_encode([
                'success' => true,
                'data' => $result['data'],
                'totalLogs' => $result['totalLogs'],
                'totalPages' => $result['totalPages']
            ]);
        }
        break;

    case 'get_note_types':
        $result = $controller->getNoteTypes();
        echo json_encode($result);
        break;

    case 'get_documents':
        require_once '../controllers/DocumentController.php';
        $docController = new DocumentController($pdo);
        $patient_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
        $result = $docController->getDocuments($patient_id);
        echo json_encode($result);
        break;

    case 'update_profile':
        if ($request_method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }
        require_once '../controllers/PatientController.php';
        $patientController = new PatientController($pdo);
        $patient_id = $_POST['user_id'] ?? null;
        
        // Ensure user is updating their own profile or is admin
        if (((int)$patient_id !== (int)$session_user_id) && $_SESSION['user']['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
            exit;
        }

        $result = $patientController->updatePatient($patient_id, $_POST, $_FILES);
        if (isset($result['success'])) {
            echo json_encode(['success' => true, 'message' => $result['success']]);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => implode(', ', $result['errors'] ?? ['Unknown error'])]);
        }
        break;

    case 'upload_document':
        if ($request_method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }
        require_once '../controllers/DocumentController.php';
        $docController = new DocumentController($pdo);
        $patient_id = $_POST['user_id'] ?? null;
        $doc_name = $_POST['document_name'] ?? '';
        $category = $_POST['category'] ?? 'Other';
        $description = $_POST['description'] ?? '';
        if (isset($_FILES['document'])) {
            $result = $docController->uploadDocument($patient_id, $_FILES['document'], $doc_name, $category, $description);
            echo json_encode($result);
        } else {
            echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        }
        break;

    case 'delete_document':
        if ($request_method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }
        require_once '../controllers/DocumentController.php';
        $docController = new DocumentController($pdo);
        $data = json_decode(file_get_contents('php://input'), true);
        $doc_id = $data['document_id'] ?? null;
        $result = $docController->deleteDocument($doc_id);
        echo json_encode($result);
        break;

    case 'get_notifications':
        require_once '../controllers/NotificationController.php';
        $notifCtrl = new NotificationController($pdo);
        $unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
        $notifications = $notifCtrl->getNotifications($session_user_id, 10, $unread_only);
        echo json_encode(['success' => true, 'data' => $notifications]);
        break;

    case 'get_unread_count':
        require_once '../controllers/NotificationController.php';
        $notifCtrl = new NotificationController($pdo);
        $count = $notifCtrl->getUnreadCount($session_user_id);
        echo json_encode(['success' => true, 'count' => $count]);
        break;

    case 'mark_notifications_read':
        require_once '../controllers/NotificationController.php';
        $notifCtrl = new NotificationController($pdo);
        $notification_id = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT);
        $result = $notifCtrl->markAsRead($session_user_id, $notification_id);
        echo json_encode(['success' => $result]);
        break;
    
    case 'delete_notification':
        if ($request_method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }
        require_once '../controllers/NotificationController.php';
        $notifCtrl = new NotificationController($pdo);
        
        // Get notification_id from POST or JSON input
        $data = json_decode(file_get_contents('php://input'), true);
        $notification_id = $data['notification_id'] ?? filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT);
        
        if (!$notification_id) {
            echo json_encode(['success' => false, 'message' => 'Missing notification ID']);
            exit;
        }
        
        $result = $notifCtrl->deleteNotification($session_user_id, $notification_id);
        echo json_encode(['success' => $result]);
        break;

    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Invalid endpoint']);
        break;
}
?>