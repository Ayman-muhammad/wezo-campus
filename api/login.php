<?php
/**
 * WEZO CAMPUS HUB - Login API
 * Powered by AYGLOBE INC
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../../core/Config.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Session.php';
require_once __DIR__ . '/../../core/Validation.php';

use Core\Auth;
use Core\Database;
use Core\Session;
use Core\Validation;

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Get request data
$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

// Validate required fields
$validator = new Validation($data);
$validator->rules([
    'email' => ['required', 'email'],
    'password' => ['required', 'min:6']
]);

if (!$validator->validate()) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Validation failed',
        'errors' => $validator->errors()
    ]);
    exit;
}

$db = Database::getInstance();

try {
    // Check login attempts
    $ip = $_SERVER['REMOTE_ADDR'];
    $attempts = $db->fetchColumn("
        SELECT COUNT(*) 
        FROM login_attempts 
        WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ", [$ip]);
    
    if ($attempts >= 5) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Too many login attempts. Please try again in 15 minutes.'
        ]);
        exit;
    }
    
    // Find user
    $user = $db->fetch("
        SELECT u.*, 
               (SELECT COUNT(*) FROM login_attempts WHERE user_id = u.id AND success = 0 AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)) as failed_attempts
        FROM users u
        WHERE u.email = ? AND u.status = 'active'
    ", [$data['email']]);
    
    if (!$user) {
        // Record failed attempt
        $db->insert('login_attempts', [
            'ip_address' => $ip,
            'email' => $data['email'],
            'success' => 0,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'attempt_time' => date('Y-m-d H:i:s')
        ]);
        
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email or password'
        ]);
        exit;
    }
    
    // Check if account is locked
    if ($user['failed_attempts'] >= 5) {
        http_response_code(423);
        echo json_encode([
            'success' => false,
            'message' => 'Account temporarily locked due to too many failed attempts. Please try again in 15 minutes.'
        ]);
        exit;
    }
    
    // Verify password
    if (!password_verify($data['password'], $user['password'])) {
        // Record failed attempt
        $db->insert('login_attempts', [
            'ip_address' => $ip,
            'user_id' => $user['id'],
            'email' => $data['email'],
            'success' => 0,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'attempt_time' => date('Y-m-d H:i:s')
        ]);
        
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email or password'
        ]);
        exit;
    }
    
    // Check if email needs verification
    if ($user['email_verified'] == 0) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Please verify your email address before logging in.',
            'requires_verification' => true,
            'user_id' => $user['id']
        ]);
        exit;
    }
    
    // Update last login
    $db->query("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
    
    // Record successful attempt
    $db->insert('login_attempts', [
        'ip_address' => $ip,
        'user_id' => $user['id'],
        'email' => $data['email'],
        'success' => 1,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'attempt_time' => date('Y-m-d H:i:s')
    ]);
    
    // Clear failed attempts
    $db->query("DELETE FROM login_attempts WHERE user_id = ? AND success = 0", [$user['id']]);
    
    // Log activity
    $db->insert('activity_logs', [
        'user_id' => $user['id'],
        'action' => 'login',
        'description' => 'User logged in successfully',
        'ip_address' => $ip,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    // Create session
    Session::start();
    $_SESSION['user'] = [
        'id' => $user['id'],
        'email' => $user['email'],
        'username' => $user['username'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'role' => $user['role'],
        'avatar' => $user['avatar']
    ];
    
    // Generate JWT token for API access
    $token = Auth::generateToken($user);
    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'data' => [
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'username' => $user['username'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'role' => $user['role'],
                'avatar' => $user['avatar']
            ],
            'token' => $token,
            'redirect' => $data['redirect'] ?? '/'
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}