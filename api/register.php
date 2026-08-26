<?php
/**
 * WEZO CAMPUS HUB - Registration API
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

// Validate input
$validator = new Validation($data);
$validator->rules([
    'first_name' => ['required', 'min:2', 'max:50'],
    'last_name' => ['required', 'min:2', 'max:50'],
    'email' => ['required', 'email', 'max:100'],
    'username' => ['required', 'min:3', 'max:30', 'alpha_dash'],
    'password' => ['required', 'min:8', 'confirmed'],
    'phone' => ['max:20'],
    'campus_id' => ['numeric'],
    'agree_terms' => ['required', 'accepted']
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
    $db->beginTransaction();
    
    // Check if email already exists
    $emailExists = $db->fetchColumn("SELECT COUNT(*) FROM users WHERE email = ?", [$data['email']]);
    if ($emailExists > 0) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Email already registered'
        ]);
        exit;
    }
    
    // Check if username already exists
    $usernameExists = $db->fetchColumn("SELECT COUNT(*) FROM users WHERE username = ?", [$data['username']]);
    if ($usernameExists > 0) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Username already taken'
        ]);
        exit;
    }
    
    // Hash password
    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
    
    // Generate verification token
    $verificationToken = bin2hex(random_bytes(32));
    
    // Create user
    $userId = $db->insert('users', [
        'first_name' => trim($data['first_name']),
        'last_name' => trim($data['last_name']),
        'email' => strtolower(trim($data['email'])),
        'username' => strtolower(trim($data['username'])),
        'password' => $hashedPassword,
        'phone' => $data['phone'] ?? null,
        'campus_id' => $data['campus_id'] ?? null,
        'role' => 'student',
        'status' => 'active',
        'email_verified' => 0,
        'verification_token' => $verificationToken,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    
    // Send verification email
    $verificationLink = APP_URL . "/verify-email?token=" . $verificationToken;
    
    // In production, you would send an actual email
    // For now, we'll just log it
    $db->insert('email_queue', [
        'to_email' => $data['email'],
        'subject' => 'Verify Your WEZO CAMPUS HUB Account',
        'body' => "Hello " . $data['first_name'] . ",\n\nPlease click the link below to verify your email address:\n\n" . $verificationLink . "\n\nThis link will expire in 24 hours.\n\nBest regards,\nWEZO CAMPUS HUB Team",
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    // Log activity
    $db->insert('activity_logs', [
        'user_id' => $userId,
        'action' => 'registration',
        'description' => 'New user registration: ' . $data['email'],
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    $db->commit();
    
    // Auto-login if email verification is not required
    $settings = $db->fetch("SELECT * FROM settings WHERE id = 1");
    
    if ($settings['require_email_verification'] == 0) {
        // Update email as verified
        $db->query("UPDATE users SET email_verified = 1 WHERE id = ?", [$userId]);
        
        // Create session
        Session::start();
        $_SESSION['user'] = [
            'id' => $userId,
            'email' => $data['email'],
            'username' => $data['username'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'role' => 'student',
            'avatar' => 'default.jpg'
        ];
        
        $token = Auth::generateToken(['id' => $userId, 'email' => $data['email']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful! Welcome to WEZO CAMPUS HUB.',
            'data' => [
                'user' => [
                    'id' => $userId,
                    'email' => $data['email'],
                    'username' => $data['username'],
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'role' => 'student',
                    'avatar' => 'default.jpg'
                ],
                'token' => $token,
                'redirect' => '/'
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful! Please check your email to verify your account.',
            'data' => [
                'requires_verification' => true,
                'user_id' => $userId
            ]
        ]);
    }
    
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Registration failed: ' . $e->getMessage()
    ]);
}