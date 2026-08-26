<?php
/**
 * WEZO CAMPUS HUB Configuration Class
 * Powered by AYGLOBE INC
 * Founder: Ayman Muhammad
 */

namespace Core;

class Config {
    // Database Configuration - UPDATED FOR XAMPP
    const DB_HOST = 'localhost';
    const DB_NAME = 'wezo_campus_hub';
    const DB_USER = 'root';                   // XAMPP default user
    const DB_PASS = '';                       // XAMPP default password (empty)
    const DB_CHARSET = 'utf8mb4';
    const DB_PORT = 3306;                     // Added port
    
    // Application Configuration
    const APP_NAME = 'WEZO CAMPUS HUB';
    const APP_VERSION = '1.0.0';
    const APP_ENV = 'development'; // development, staging, production
    const APP_URL = 'http://localhost/wezo-campus'; // Updated for localhost
    const APP_TIMEZONE = 'Africa/Nairobi';

    // Email Configuration
    const EMAIL_FROM_NAME = 'WEZO CAMPUS HUB';
    const EMAIL_FROM_ADDRESS = 'ayglobe@gmail.com';
    const SUPPORT_EMAIL = 'ayman11muhammad@gmail.com';
    
    // Company Information
    const COMPANY_NAME = 'AYGLOBE INC';
    const FOUNDER_NAME = 'Ayman Muhammad';
    const COMPANY_EMAIL = 'ayglobe@gmail.com';
    
    // Security Configuration
    const SESSION_NAME = 'WCH_SESSION';
    const SESSION_LIFETIME = 86400; // 24 hours
    const CSRF_TOKEN_NAME = 'wch_csrf_token';
    const PASSWORD_ALGO = PASSWORD_BCRYPT;
    const PASSWORD_COST = 12;
    
    // File Upload Configuration
    const MAX_UPLOAD_SIZE = 52428800; // 50MB
    const ALLOWED_IMAGE_TYPES = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    const ALLOWED_DOC_TYPES = ['pdf', 'doc', 'docx', 'txt', 'ppt', 'pptx'];
    const UPLOAD_DIR = __DIR__ . '/../public/assets/uploads/';
    
    // Pagination
    const ITEMS_PER_PAGE = 12;
    const NOTES_PER_PAGE = 10;
    const MARKETPLACE_PER_PAGE = 15;
    
    // Cache Configuration
    const CACHE_ENABLED = true;
    const CACHE_DIR = __DIR__ . '/../cache/';
    const CACHE_LIFETIME = 3600; // 1 hour
    
    // SMTP Email Configuration (for production)
    const SMTP_HOST = 'smtp.gmail.com';
    const SMTP_PORT = 587;
    const SMTP_USER = '';
    const SMTP_PASS = '';
    const SMTP_SECURE = 'tls';
    
    // API Keys (for future integrations)
    const GOOGLE_MAPS_API = '';
    const OPENAI_API_KEY = '';
    
    // Feature Flags
    const REGISTRATION_ENABLED = true;
    const EMAIL_VERIFICATION_REQUIRED = true;
    const MAINTENANCE_MODE = false;
    
    // Theme Configuration
    const PRIMARY_COLOR = '#1A56DB';
    const SECONDARY_COLOR = '#10B981';
    const ACCENT_COLOR = '#F59E0B';
    
    /**
     * Get database configuration
     */
    public static function getDatabaseConfig() {
        return [
            'host' => self::DB_HOST,
            'database' => self::DB_NAME,
            'username' => self::DB_USER,
            'password' => self::DB_PASS,
            'charset' => self::DB_CHARSET,
            'port' => self::DB_PORT  // Added port
        ];
    }
    
    /**
     * Get SMTP configuration
     */
    public static function getSmtpConfig() {
        return [
            'host' => self::SMTP_HOST,
            'port' => self::SMTP_PORT,
            'username' => self::SMTP_USER,
            'password' => self::SMTP_PASS,
            'secure' => self::SMTP_SECURE
        ];
    }
    
    /**
     * Get allowed file types
     */
    public static function getAllowedFileTypes($type = 'all') {
        $types = [
            'image' => self::ALLOWED_IMAGE_TYPES,
            'document' => self::ALLOWED_DOC_TYPES,
            'all' => array_merge(self::ALLOWED_IMAGE_TYPES, self::ALLOWED_DOC_TYPES)
        ];
        
        return $types[$type] ?? $types['all'];
    }
    
    /**
     * Get upload directory
     */
    public static function getUploadDir($subdir = '') {
        $dir = self::UPLOAD_DIR . $subdir;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }
    
    /**
     * Get cache directory
     */
    public static function getCacheDir($subdir = '') {
        $dir = self::CACHE_DIR . $subdir;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }
    
    /**
     * Check if feature is enabled
     */
    public static function isFeatureEnabled($feature) {
        switch ($feature) {
            case 'registration':
                return self::REGISTRATION_ENABLED;
            case 'email_verification':
                return self::EMAIL_VERIFICATION_REQUIRED;
            case 'maintenance':
                return self::MAINTENANCE_MODE;
            default:
                return false;
        }
    }
    
    /**
     * Get company information
     */
    public static function getCompanyInfo() {
        return [
            'name' => self::COMPANY_NAME,
            'founder' => self::FOUNDER_NAME,
            'email' => self::COMPANY_EMAIL,
            'support_email' => self::SUPPORT_EMAIL
        ];
    }
    
    /**
     * Get environment-specific configuration
     */
    public static function getEnvironmentConfig() {
        return [
            'development' => [
                'debug' => true,
                'error_reporting' => E_ALL,
                'display_errors' => true,
                'log_errors' => true,
                'cache' => false,
                'send_emails' => false, // Don't send real emails in development
                'log_emails' => true    // Log emails to file instead
            ],
            'staging' => [
                'debug' => true,
                'error_reporting' => E_ALL,
                'display_errors' => false,
                'log_errors' => true,
                'cache' => true,
                'send_emails' => true   // Send real emails in staging
            ],
            'production' => [
                'debug' => false,
                'error_reporting' => E_ALL & ~E_DEPRECATED & ~E_STRICT,
                'display_errors' => false,
                'log_errors' => true,
                'cache' => true,
                'send_emails' => true   // Send real emails in production
            ]
        ];
    }
    
    /**
     * Get current environment settings
     */
    public static function getCurrentEnvSettings() {
        $env = self::APP_ENV;
        $envConfigs = self::getEnvironmentConfig();
        return $envConfigs[$env] ?? $envConfigs['development'];
    }
    
    /**
     * Get email configuration
     */
    public static function getEmailConfig() {
        return [
            'from_name' => self::EMAIL_FROM_NAME,
            'from_address' => self::EMAIL_FROM_ADDRESS,
            'reply_to' => self::SUPPORT_EMAIL,
            'support_email' => self::SUPPORT_EMAIL
        ];
    }
    
    /**
     * Check if debug mode is enabled
     */
    public static function isDebugEnabled() {
        $settings = self::getCurrentEnvSettings();
        return $settings['debug'] ?? false;
    }
    
    /**
     * Check if email sending is enabled
     */
    public static function isEmailSendingEnabled() {
        $settings = self::getCurrentEnvSettings();
        return $settings['send_emails'] ?? false;
    }
    
    /**
     * Check if email logging is enabled
     */
    public static function isEmailLoggingEnabled() {
        $settings = self::getCurrentEnvSettings();
        return $settings['log_emails'] ?? true;
    }
    
    /**
     * Get application settings
     */
    public static function getAppSettings() {
        return [
            'name' => self::APP_NAME,
            'version' => self::APP_VERSION,
            'env' => self::APP_ENV,
            'url' => self::APP_URL,
            'timezone' => self::APP_TIMEZONE
        ];
    }
    
    /**
     * Get theme colors
     */
    public static function getThemeColors() {
        return [
            'primary' => self::PRIMARY_COLOR,
            'secondary' => self::SECONDARY_COLOR,
            'accent' => self::ACCENT_COLOR
        ];
    }
    
    /**
     * Initialize environment settings
     */
    public static function init() {
        // Set timezone
        date_default_timezone_set(self::APP_TIMEZONE);
        
        // Set error reporting based on environment
        $settings = self::getCurrentEnvSettings();
        
        if (isset($settings['error_reporting'])) {
            error_reporting($settings['error_reporting']);
        }
        
        if (isset($settings['display_errors'])) {
            ini_set('display_errors', $settings['display_errors'] ? '1' : '0');
        }
        
        if (isset($settings['log_errors'])) {
            ini_set('log_errors', $settings['log_errors'] ? '1' : '0');
        }
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_name(self::SESSION_NAME);
            session_set_cookie_params([
                'lifetime' => self::SESSION_LIFETIME,
                'path' => '/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
            session_start();
        }
    }
}