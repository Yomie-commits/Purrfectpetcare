<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Database connection
require_once 'db_connect.php';

// Check if database is connected
if (!isDatabaseConnected()) {
    echo json_encode([
        'success' => false,
        'error' => getDatabaseError(),
        'message' => 'Database connection failed. Please check your configuration.'
    ]);
    exit;
}

// --- ROLE-BASED ACCESS CONTROL FUNCTIONS ---
function checkUserRole($requiredRole, $userId) {
    global $pdo;
    
    if (empty($userId)) {
        throw new Exception('User ID is required for role verification');
    }
    
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? AND status = 'Active'");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('User not found or inactive');
    }
    
    if ($user['role'] !== $requiredRole) {
        throw new Exception('Unauthorized access: Insufficient permissions. Required role: ' . $requiredRole . ', User role: ' . $user['role']);
    }
    
    return true;
}

function checkUserPermission($userId, $allowedRoles = ['admin']) {
    global $pdo;
    
    if (empty($userId)) {
        throw new Exception('User ID is required for permission verification');
    }
    
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? AND status = 'Active'");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('User not found or inactive');
    }
    
    if (!in_array($user['role'], $allowedRoles)) {
        throw new Exception('Unauthorized access: Insufficient permissions. Allowed roles: ' . implode(', ', $allowedRoles) . ', User role: ' . $user['role']);
    }
    
    return true;
}

// --- ERROR HANDLING & SECURITY IMPROVEMENTS ---
// Ensure errors are not displayed to users in production
if (getenv('APP_ENV') !== 'development') {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL);
}

// Utility: Log errors to a file
function logError($message) {
    error_log($message, 3, __DIR__ . '/error.log');
}

// Utility: Validate input (basic example)
function validateInput($data, $fields) {
    foreach ($fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            throw new Exception("Missing or empty required field: $field");
        }
    }
}

// Get the action from POST data
$action = $_POST['action'] ?? '';

// Initialize response array
$response = ['success' => false, 'message' => '', 'data' => null];

session_start();

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Allow frontend to fetch CSRF token
if ($action === 'get_csrf_token') {
    echo json_encode(['csrf_token' => $_SESSION['csrf_token']]);
    exit;
}

// Actions that do NOT require CSRF token
$noCsrfActions = ['login', 'register', 'forgot_password', 'reset_password', 'get_csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array($action, $noCsrfActions)) {
    $csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$csrf || $csrf !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid or missing CSRF token.']);
        exit;
    }
}

try {
    switch ($action) {
        // --- USER MANAGEMENT ---
        case 'login':
            $response = login($pdo, $_POST);
            break;
        case 'register':
            $response = register($pdo, $_POST);
            break;
        case 'get_user':
            $response = get_user($pdo, $_POST);
            break;
        case 'update_user':
            $response = update_user($pdo, $_POST);
            break;
        case 'toggle_user_status':
            $response = toggle_user_status($pdo, $_POST);
            break;
        case 'delete_user':
            $response = delete_user($pdo, $_POST);
            break;
        case 'add_user':
            $response = add_user($pdo, $_POST);
            break;
        case 'get_users':
            $response = get_users($pdo, $_POST);
            break;
        case 'delete_account':
            $response = delete_account($pdo, $_POST);
            break;
        case 'forgot_password':
            $response = forgot_password($pdo, $_POST);
            break;
        case 'reset_password':
            $response = reset_password($pdo, $_POST);
            break;
        // --- PET MANAGEMENT ---
        case 'get_pet':
            $response = get_pet($pdo, $_POST);
            break;
        case 'get_pet_details':
            $response = get_pet_details($pdo, $_POST);
            break;
        case 'update_pet':
            $response = update_pet($pdo, $_POST);
            break;
        case 'delete_pet':
            $response = delete_pet($pdo, $_POST);
            break;
        case 'get_pets':
            $response = get_pets($pdo, $_POST);
            break;
        case 'add_pet':
            $response = add_pet($pdo, $_POST);
            break;
        case 'get_enhanced_pet_details':
            $response = get_enhanced_pet_details($pdo, $_POST);
            break;
        case 'upload_pet_photo':
            $response = upload_pet_photo($pdo, array_merge($_POST, $_FILES));
            break;
        case 'delete_pet_photo':
            $response = delete_pet_photo($pdo, $_POST);
            break;
        case 'set_primary_photo':
            $response = set_primary_photo($pdo, $_POST);
            break;
        case 'add_medical_record':
            $response = add_medical_record($pdo, $_POST);
            break;
        case 'add_vaccination':
            $response = add_vaccination($pdo, $_POST);
            break;
        case 'add_health_record':
            $response = add_health_record($pdo, $_POST);
            break;
        case 'generate_pet_health_report':
            $response = generate_pet_health_report($pdo, $_POST);
            break;
        // --- APPOINTMENT MANAGEMENT ---
        case 'get_appointment':
            $response = get_appointment($pdo, $_POST);
            break;
        case 'update_appointment':
            $response = update_appointment($pdo, $_POST);
            break;
        case 'cancel_appointment':
            $response = cancel_appointment($pdo, $_POST);
            break;
        case 'start_appointment':
            $response = start_appointment($pdo, $_POST);
            break;
        case 'complete_appointment':
            $response = complete_appointment($pdo, $_POST);
            break;
        case 'get_appointments':
            $response = get_appointments($pdo, $_POST);
            break;
        case 'add_appointment':
            $response = add_appointment($pdo, $_POST);
            break;
        case 'get_appointments_analytics':
            $response = get_appointments_analytics($pdo, $_POST);
            break;
        // --- PAYMENT MANAGEMENT ---
        case 'get_payment':
            $response = get_payment($pdo, $_POST);
            break;
        case 'add_payment':
            $response = add_payment($pdo, $_POST);
            break;
        case 'update_payment':
            $response = update_payment($pdo, $_POST);
            break;
        case 'delete_payment':
            $response = delete_payment($pdo, $_POST);
            break;
        case 'get_payments':
            $response = get_payments($pdo, $_POST);
            break;
        case 'get_revenue_analytics':
            $response = get_revenue_analytics($pdo, $_POST);
            break;
        // --- FORUM MANAGEMENT ---
        case 'get_post':
            $response = get_post($pdo, $_POST);
            break;
        case 'approve_post':
            $response = approve_post($pdo, $_POST);
            break;
        case 'reject_post':
            $response = reject_post($pdo, $_POST);
            break;
        case 'add_forum_post':
            $response = add_forum_post($pdo, $_POST);
            break;
        case 'update_forum_post':
            $response = update_forum_post($pdo, $_POST);
            break;
        case 'delete_forum_post':
            $response = delete_forum_post($pdo, $_POST);
            break;
        case 'get_forum_posts':
            $response = get_forum_posts($pdo, $_POST);
            break;
        // --- ANALYTICS & ADMIN ---
        case 'get_admin_dashboard_stats':
            $response = get_admin_dashboard_stats($pdo, $_POST);
            break;
        case 'get_pet_types_analytics':
            $response = get_pet_types_analytics($pdo, $_POST);
            break;
        case 'get_system_performance':
            $response = get_system_performance($pdo, $_POST);
            break;
        case 'get_system_alerts':
            $response = get_system_alerts($pdo, $_POST);
            break;
        case 'get_recent_activity':
            $response = get_recent_activity($pdo, $_POST);
            break;
        case 'generate_admin_report':
            $response = generate_admin_report($pdo, $_POST);
            break;
        case 'get_user_distribution':
            $response = get_user_distribution($pdo, $_POST);
            break;
        // --- DEFAULT CASE ---
        default:
            throw new Exception('Invalid action specified');
    }

} catch (Exception $e) {
    logError($e->getMessage() . "\n" . $e->getTraceAsString());
    $response = [
        'success' => false,
        'message' => 'An internal error occurred. Please try again later.'
    ];
    echo json_encode($response);
    exit;
}

// Return JSON response
echo json_encode($response);
?> 