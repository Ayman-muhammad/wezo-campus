<?php
/**
 * WEZO CAMPUS HUB - Bootstrap File
 * Loads all essential classes in correct order
 * Powered by AYGLOBE INC
 */

// Define base paths
define('BASE_PATH', dirname(__DIR__));
define('CORE_PATH', __DIR__);

// Load Config first (it has no dependencies)
require_once CORE_PATH . '/Config.php';

// Load Database class
if (file_exists(CORE_PATH . '/Database.php')) {
    require_once CORE_PATH . '/Database.php';
} else {
    die('<h2>Database Class Missing</h2><p>Please check your installation.</p>');
}

// Load Session class if exists
if (file_exists(CORE_PATH . '/Session.php')) {
    require_once CORE_PATH . '/Session.php';
}

// Load Auth class
if (file_exists(CORE_PATH . '/Auth.php')) {
    require_once CORE_PATH . '/Auth.php';
} else {
    die('<h2>Auth Class Missing</h2><p>Please check your installation.</p>');
}