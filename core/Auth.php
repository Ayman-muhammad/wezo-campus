<?php
/**
 * WEZO CAMPUS HUB Authentication Class
 * Powered by AYGLOBE INC
 */

namespace Core;

use Exception;

class Auth {
    private static $user = null;
    private static $isLoggedIn = false;
    
    /**
     * Initialize session
     */
    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(Config::SESSION_NAME);
            session_set_cookie_params([
                'lifetime' => Config::SESSION_LIFETIME,
                'path' => '/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
            session_start();
        }
        
        self::checkLogin();
    }
    
    /**
     * Check if user is logged in
     */
    private static function checkLogin() {
        if (isset($_SESSION['user_id'])) {
            try {
                $db = Database::getInstance();
                $user = $db->fetch(
                    "SELECT * FROM users WHERE id = ? AND status = 'active'",
                    [$_SESSION['user_id']]
                );
                
                if ($user) {
                    self::$user = $user;
                    self::$isLoggedIn = true;
                    
                    // Update last login
                    if (!isset($_SESSION['last_activity']) || time() - $_SESSION['last_activity'] > 300) {
                        $db->update('users', 
                            ['last_login' => date('Y-m-d H:i:s')], 
                            'id = ?', 
                            [$user['id']]
                        );
                    }
                    
                    $_SESSION['last_activity'] = time();
                } else {
                    self::logout();
                }
            } catch (Exception $e) {
                // If database error, keep session but don't load user
                error_log("Auth checkLogin error: " . $e->getMessage());
                self::$user = null;
                self::$isLoggedIn = false;
            }
        }
    }
    
    /**
     * Attempt login
     */
    public static function login($email, $password) {
        try {
            $db = Database::getInstance();
            
            $user = $db->fetch(
                "SELECT * FROM users WHERE email = ? AND status = 'active'",
                [$email]
            );
            
            if (!$user) {
                return ['success' => false, 'message' => 'Invalid email or password'];
            }
            
            if (!password_verify($password, $user['password'])) {
                return ['success' => false, 'message' => 'Invalid email or password'];
            }
            
            // If email verification feature is enabled and user is not verified,
            // do NOT block login. Instead set a session flag so the UI can show
            // a reminder or limit features while still allowing login.
            if (Config::isFeatureEnabled('email_verification') && !$user['is_verified']) {
                $_SESSION['needs_verification'] = true;
            }
            
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['login_time'] = time();
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            // Update session user data
            self::$user = $user;
            self::$isLoggedIn = true;
            
            // Log activity
            $db->insert('activity_logs', [
                'user_id' => $user['id'],
                'action' => 'user_login',
                'description' => 'User logged in successfully',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            
            return ['success' => true, 'user' => $user];
            
        } catch (Exception $e) {
            error_log("Auth login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Login failed. Please try again.'];
        }
    }
    
    /**
     * Login with username
     */
    public static function loginWithUsername($username, $password) {
        try {
            $db = Database::getInstance();
            
            $user = $db->fetch(
                "SELECT * FROM users WHERE username = ? AND status = 'active'",
                [$username]
            );
            
            if (!$user) {
                return ['success' => false, 'message' => 'Invalid username or password'];
            }
            
            if (!password_verify($password, $user['password'])) {
                return ['success' => false, 'message' => 'Invalid username or password'];
            }
            
            // If email verification feature is enabled and user is not verified,
            // do NOT block login. Instead set a session flag so the UI can show
            // a reminder or limit features while still allowing login.
            if (Config::isFeatureEnabled('email_verification') && !$user['is_verified']) {
                $_SESSION['needs_verification'] = true;
            }
            
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['login_time'] = time();
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            // Update session user data
            self::$user = $user;
            self::$isLoggedIn = true;
            
            // Log activity
            $db->insert('activity_logs', [
                'user_id' => $user['id'],
                'action' => 'user_login',
                'description' => 'User logged in with username',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            
            return ['success' => true, 'user' => $user];
            
        } catch (Exception $e) {
            error_log("Auth loginWithUsername error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Login failed. Please try again.'];
        }
    }
    
    /**
     * Register new user
     */
    public static function register($data) {
        try {
            $db = Database::getInstance();
            
            // Validate required fields
            $required = ['username', 'email', 'password', 'first_name', 'last_name'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'message' => ucfirst($field) . ' is required'];
                }
            }
            
            // Validate email
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Invalid email address'];
            }
            
            // Check if email exists
            if ($db->exists('users', 'email = ?', [$data['email']])) {
                return ['success' => false, 'message' => 'Email already registered'];
            }
            
            // Check if username exists
            if ($db->exists('users', 'username = ?', [$data['username']])) {
                return ['success' => false, 'message' => 'Username already taken'];
            }
            
            // Validate password strength
            if (strlen($data['password']) < 8) {
                return ['success' => false, 'message' => 'Password must be at least 8 characters'];
            }
            
            // Hash password
            $data['password'] = password_hash($data['password'], Config::PASSWORD_ALGO, [
                'cost' => Config::PASSWORD_COST
            ]);
            
            // Generate verification token
            $data['verification_token'] = bin2hex(random_bytes(32));
            
            $db->beginTransaction();
            
            $userId = $db->insert('users', [
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => $data['password'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? null,
                'verification_token' => $data['verification_token'],
                'role' => 'student',
                'is_verified' => !Config::isFeatureEnabled('email_verification'),
                'status' => 'active',
                'profile_pic' => 'default.png',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Send verification email
            if (Config::isFeatureEnabled('email_verification')) {
                self::sendVerificationEmail($data['email'], $data['verification_token'], $data['first_name']);
            }
            
            // Log activity
            $db->insert('activity_logs', [
                'user_id' => $userId,
                'action' => 'user_registration',
                'description' => 'New user registered: ' . $data['username'],
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            $db->commit();
            
            // Auto-login if no verification required
            if (!Config::isFeatureEnabled('email_verification')) {
                // Get the created user
                $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
                
                // Set session data
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['login_time'] = time();
                $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                
                self::$user = $user;
                self::$isLoggedIn = true;
            }
            
            return [
                'success' => true, 
                'message' => Config::isFeatureEnabled('email_verification') 
                    ? 'Registration successful! Please check your email to verify your account.' 
                    : 'Registration successful! You are now logged in.',
                'user_id' => $userId,
                'auto_login' => !Config::isFeatureEnabled('email_verification')
            ];
            
        } catch (Exception $e) {
            if (isset($db) && method_exists($db, 'rollback')) {
                $db->rollback();
            }
            error_log("Auth register error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed. Please try again.'];
        }
    }
    
    /**
     * Verify email
     */
    public static function verifyEmail($token) {
        try {
            $db = Database::getInstance();
            
            $user = $db->fetch(
                "SELECT * FROM users WHERE verification_token = ? AND is_verified = 0",
                [$token]
            );
            
            if (!$user) {
                return ['success' => false, 'message' => 'Invalid or expired verification token'];
            }
            
            $db->update('users', 
                [
                    'is_verified' => 1,
                    'verification_token' => null
                ], 
                'id = ?', 
                [$user['id']]
            );
            
            // Log activity
            $db->insert('activity_logs', [
                'user_id' => $user['id'],
                'action' => 'email_verified',
                'description' => 'User verified email address',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            // Send welcome email
            self::sendWelcomeEmail($user['email'], $user['first_name']);
            
            return ['success' => true, 'message' => 'Email verified successfully! You can now login.'];
            
        } catch (Exception $e) {
            error_log("Auth verifyEmail error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Verification failed. Please try again.'];
        }
    }
    
    /**
     * Logout user
     */
    public static function logout() {
        try {
            if (self::isLoggedIn()) {
                // Log activity
                $db = Database::getInstance();
                $db->insert('activity_logs', [
                    'user_id' => self::$user['id'],
                    'action' => 'user_logout',
                    'description' => 'User logged out',
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }
        } catch (Exception $e) {
            error_log("Auth logout error: " . $e->getMessage());
        }
        
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        
        self::$user = null;
        self::$isLoggedIn = false;
    }
    
    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        return self::$isLoggedIn;
    }
    
    /**
     * Get current user
     */
    public static function user() {
        return self::$user;
    }
    
    /**
     * Get user ID
     */
    public static function id() {
        return self::$user ? self::$user['id'] : null;
    }
    
    /**
     * Get user by ID
     */
    public static function getUser($id) {
        try {
            $db = Database::getInstance();
            return $db->fetch("SELECT * FROM users WHERE id = ?", [$id]);
        } catch (Exception $e) {
            error_log("Auth getUser error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get user by email
     */
    public static function getUserByEmail($email) {
        try {
            $db = Database::getInstance();
            return $db->fetch("SELECT * FROM users WHERE email = ?", [$email]);
        } catch (Exception $e) {
            error_log("Auth getUserByEmail error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get user by username
     */
    public static function getUserByUsername($username) {
        try {
            $db = Database::getInstance();
            return $db->fetch("SELECT * FROM users WHERE username = ?", [$username]);
        } catch (Exception $e) {
            error_log("Auth getUserByUsername error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check if user has role
     */
    public static function hasRole($role) {
        return self::isLoggedIn() && self::$user['role'] === $role;
    }
    
    /**
     * Check if user is admin
     */
    public static function isAdmin() {
        return self::hasRole('admin');
    }
    
    /**
     * Check if user is moderator
     */
    public static function isModerator() {
        return self::hasRole('moderator');
    }
    
    /**
     * Check if user is verified student
     */
    public static function isVerifiedStudent() {
        return self::hasRole('verified_student');
    }
    
    /**
     * Check if user is regular student
     */
    public static function isStudent() {
        return self::hasRole('student');
    }
    
    /**
     * Check if user is any type of student
     */
    public static function isAnyStudent() {
        if (!self::isLoggedIn()) return false;
        
        $studentRoles = ['student', 'verified_student'];
        return in_array(self::$user['role'], $studentRoles);
    }
    
    /**
     * Check if user is verified (email verified)
     */
    public static function isVerified() {
        return self::isLoggedIn() && self::$user['is_verified'] == 1;
    }
    
    /**
     * Update user profile
     */
    public static function updateProfile($data) {
        if (!self::isLoggedIn()) {
            return ['success' => false, 'message' => 'Not logged in'];
        }
        
        try {
            $db = Database::getInstance();
            
            $updateData = [];
            $allowedFields = ['first_name', 'last_name', 'phone', 'bio', 'profile_pic'];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = $data[$field];
                }
            }
            
            if (empty($updateData)) {
                return ['success' => false, 'message' => 'No data to update'];
            }
            
            $db->update('users', $updateData, 'id = ?', [self::$user['id']]);
            
            // Update session user data
            self::$user = array_merge(self::$user, $updateData);
            $_SESSION['user_role'] = self::$user['role'];
            
            // Log activity
            $db->insert('activity_logs', [
                'user_id' => self::$user['id'],
                'action' => 'profile_update',
                'description' => 'User updated profile information',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            return ['success' => true, 'message' => 'Profile updated successfully'];
            
        } catch (Exception $e) {
            error_log("Auth updateProfile error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Update failed. Please try again.'];
        }
    }
    
    /**
     * Change password
     */
    public static function changePassword($currentPassword, $newPassword) {
        if (!self::isLoggedIn()) {
            return ['success' => false, 'message' => 'Not logged in'];
        }
        
        if (!password_verify($currentPassword, self::$user['password'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }
        
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'message' => 'New password must be at least 8 characters'];
        }
        
        try {
            $hashedPassword = password_hash($newPassword, Config::PASSWORD_ALGO, [
                'cost' => Config::PASSWORD_COST
            ]);
            
            $db = Database::getInstance();
            
            $db->update('users', 
                ['password' => $hashedPassword], 
                'id = ?', 
                [self::$user['id']]
            );
            
            // Update session user password
            self::$user['password'] = $hashedPassword;
            
            // Log activity
            $db->insert('activity_logs', [
                'user_id' => self::$user['id'],
                'action' => 'password_change',
                'description' => 'User changed password',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            return ['success' => true, 'message' => 'Password changed successfully'];
            
        } catch (Exception $e) {
            error_log("Auth changePassword error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to change password'];
        }
    }
    
    /**
     * Send verification email
     */
    private static function sendVerificationEmail($email, $token, $name = '') {
        try {
            // Check if Email class exists
            if (class_exists('Core\Email')) {
                // Use the Email class
                return \Core\Email::sendVerification($email, $token, $name);
            } else {
                // Fallback to simple email
                return self::sendSimpleVerificationEmail($email, $token, $name);
            }
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            
            // In development mode, log the email instead
            if (Config::APP_ENV === 'development') {
                error_log("Would send verification email to: $email, token: $token");
                return true; // Pretend it was sent
            }
            
            return false;
        }
    }
    
    /**
     * Simple verification email fallback
     */
    private static function sendSimpleVerificationEmail($email, $token, $name = '') {
        $subject = 'Verify Your WEZO CAMPUS HUB Account';
        $verificationUrl = Config::APP_URL . '/verify.php?token=' . $token;
        
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #1A56DB; color: white; padding: 20px; text-align: center; }
                .content { padding: 30px; background: #f9f9f9; }
                .button { display: inline-block; padding: 12px 24px; background: #10B981; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>WEZO CAMPUS HUB</h1>
                    <p>Powered by AYGLOBE INC</p>
                </div>
                <div class='content'>
                    <h2>Welcome to WEZO CAMPUS HUB!</h2>
                    <p>Hello " . htmlspecialchars($name) . ",</p>
                    <p>Thank you for registering. To complete your registration and start using all features, please verify your email address by clicking the button below:</p>
                    
                    <div style='text-align: center;'>
                        <a href='{$verificationUrl}' class='button'>Verify Email Address</a>
                    </div>
                    
                    <p>Or copy and paste this link in your browser:</p>
                    <p style='background: #eee; padding: 10px; border-radius: 3px; word-break: break-all;'>{$verificationUrl}</p>
                    
                    <p>This link will expire in 24 hours. If you didn't create an account, you can safely ignore this email.</p>
                    
                    <p>Best regards,<br>The WEZO CAMPUS HUB Team</p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " WEZO CAMPUS HUB. Powered by AYGLOBE INC.</p>
                    <p>Founder: Ayman Muhammad</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: WEZO CAMPUS HUB <ayglobe@gmail.com>\r\n";
        $headers .= "Reply-To: " . Config::SUPPORT_EMAIL . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        // In development, just log it
        if (Config::APP_ENV === 'development') {
            error_log("DEV MODE: Would send email to $email\nSubject: $subject\nURL: $verificationUrl");
            
            // Save to file for preview
            $previewDir = __DIR__ . '/../email_previews/';
            if (!is_dir($previewDir)) {
                mkdir($previewDir, 0755, true);
            }
            $previewFile = $previewDir . 'verification_' . date('Y-m-d_H-i-s') . '.html';
            file_put_contents($previewFile, $message);
            error_log("Email preview saved to: $previewFile");
            
            return true;
        }
        
        return mail($email, $subject, $message, $headers);
    }
    
    /**
     * Send welcome email
     */
    private static function sendWelcomeEmail($email, $name = '') {
        try {
            // Check if Email class exists
            if (class_exists('Core\Email')) {
                return \Core\Email::sendWelcome($email, $name);
            }
            return true;
        } catch (Exception $e) {
            error_log("Welcome email error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate CSRF token
     */
    public static function csrfToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateCsrfToken($token) {
        if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
            return false;
        }
        return true;
    }
    
    /**
     * Generate and store password reset token
     */
    public static function generatePasswordResetToken($email) {
        try {
            $db = Database::getInstance();
            
            $user = $db->fetch("SELECT id FROM users WHERE email = ? AND status = 'active'", [$email]);
            
            if (!$user) {
                return ['success' => false, 'message' => 'No account found with this email'];
            }
            
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
            
            $db->update('users', [
                'reset_token' => $token,
                'reset_expires' => $expires
            ], 'id = ?', [$user['id']]);
            
            // Send reset email
            self::sendPasswordResetEmail($email, $token);
            
            return ['success' => true, 'message' => 'Password reset instructions sent to your email'];
            
        } catch (Exception $e) {
            error_log("Auth generatePasswordResetToken error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to generate reset token'];
        }
    }
    
    /**
     * Send password reset email
     */
    private static function sendPasswordResetEmail($email, $token) {
        try {
            if (class_exists('Core\Email')) {
                return \Core\Email::sendPasswordReset($email, $token);
            }
            return true;
        } catch (Exception $e) {
            error_log("Password reset email error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Reset password with token
     */
    public static function resetPassword($token, $newPassword) {
        try {
            $db = Database::getInstance();
            
            $user = $db->fetch(
                "SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()",
                [$token]
            );
            
            if (!$user) {
                return ['success' => false, 'message' => 'Invalid or expired reset token'];
            }
            
            if (strlen($newPassword) < 8) {
                return ['success' => false, 'message' => 'Password must be at least 8 characters'];
            }
            
            $hashedPassword = password_hash($newPassword, Config::PASSWORD_ALGO, [
                'cost' => Config::PASSWORD_COST
            ]);
            
            $db->update('users', [
                'password' => $hashedPassword,
                'reset_token' => null,
                'reset_expires' => null
            ], 'id = ?', [$user['id']]);
            
            return ['success' => true, 'message' => 'Password reset successfully'];
            
        } catch (Exception $e) {
            error_log("Auth resetPassword error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to reset password'];
        }
    }
    
    /**
     * Require login middleware
     */
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            $redirect = isset($_SERVER['REQUEST_URI']) ? urlencode($_SERVER['REQUEST_URI']) : '';
            header('Location: /login.php' . ($redirect ? '?redirect=' . $redirect : ''));
            exit;
        }
    }
    
    /**
     * Require admin middleware
     */
    public static function requireAdmin() {
        self::requireLogin();
        
        if (!self::isAdmin()) {
            header('HTTP/1.0 403 Forbidden');
            echo '<h1>403 Forbidden</h1><p>You do not have permission to access this page.</p>';
            exit;
        }
    }
    
    /**
     * Require moderator middleware
     */
    public static function requireModerator() {
        self::requireLogin();
        
        if (!self::isModerator() && !self::isAdmin()) {
            header('HTTP/1.0 403 Forbidden');
            echo '<h1>403 Forbidden</h1><p>You do not have permission to access this page.</p>';
            exit;
        }
    }
    
    /**
     * Require verified student
     */
    public static function requireVerified() {
        self::requireLogin();
        
        if (!self::isVerified() && !self::isAdmin() && !self::isModerator()) {
            header('Location: /verify.php');
            exit;
        }
    }
    
    /**
     * Check if user can access resource
     */
    public static function canAccess($resource, $action = 'view') {
        if (!self::isLoggedIn()) {
            return false;
        }
        
        $userRole = self::$user['role'];
        
        // Admin can do anything
        if ($userRole === 'admin') {
            return true;
        }
        
        // Moderator can manage content
        if ($userRole === 'moderator') {
            return in_array($resource, ['notes', 'marketplace', 'hostels', 'resources', 'users']);
        }
        
        // Students have limited access
        if (in_array($userRole, ['student', 'verified_student'])) {
            switch ($resource) {
                case 'notes':
                    return in_array($action, ['view', 'create', 'edit_own', 'delete_own']);
                case 'marketplace':
                    return in_array($action, ['view', 'create', 'edit_own', 'delete_own']);
                case 'hostels':
                    return $action === 'view';
                case 'profile':
                    return in_array($action, ['view', 'edit']);
                default:
                    return false;
            }
        }
        
        return false;
    }
    
    /**
     * Get user role name
     */
    public static function getRoleName($role = null) {
        $role = $role ?? (self::$user ? self::$user['role'] : null);
        
        $roleNames = [
            'admin' => 'Administrator',
            'moderator' => 'Moderator',
            'verified_student' => 'Verified Student',
            'student' => 'Student'
        ];
        
        return $roleNames[$role] ?? 'Unknown Role';
    }
}
