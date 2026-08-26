<?php
/**
 * WEZO CAMPUS HUB Session Manager
 * Powered by AYGLOBE INC
 */

namespace Core;

class Session {
    /**
     * Start session if not started
     */
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Set session variable
     */
    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }
    
    /**
     * Get session variable
     */
    public static function get($key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * Check if session variable exists
     */
    public static function has($key) {
        return isset($_SESSION[$key]);
    }
    
    /**
     * Remove session variable
     */
    public static function remove($key) {
        if (self::has($key)) {
            unset($_SESSION[$key]);
        }
    }
    
    /**
     * Set flash message
     */
    public static function flash($key, $value) {
        $_SESSION['flash'][$key] = $value;
    }
    
    /**
     * Get flash message
     */
    public static function getFlash($key) {
        if (isset($_SESSION['flash'][$key])) {
            $message = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $message;
        }
        return null;
    }
    
    /**
     * Check for flash message
     */
    public static function hasFlash($key) {
        return isset($_SESSION['flash'][$key]);
    }
    
    /**
     * Destroy session
     */
    public static function destroy() {
        session_destroy();
        $_SESSION = [];
    }
    
    /**
     * Regenerate session ID
     */
    public static function regenerate() {
        session_regenerate_id(true);
    }
    
    /**
     * Get all session data
     */
    public static function all() {
        return $_SESSION;
    }
}