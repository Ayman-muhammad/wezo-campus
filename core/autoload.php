<?php
/**
 * WEZO CAMPUS HUB - Autoloader
 * Powered by AYGLOBE INC
 */

spl_autoload_register(function ($className) {
    // Convert namespace to file path
    $className = ltrim($className, '\\');
    $fileName = '';
    
    if ($lastNsPos = strrpos($className, '\\')) {
        $namespace = substr($className, 0, $lastNsPos);
        $className = substr($className, $lastNsPos + 1);
        $fileName = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
    }
    
    $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';
    
    // Look in core directory first
    $filePath = __DIR__ . DIRECTORY_SEPARATOR . $fileName;
    
    if (file_exists($filePath)) {
        require_once $filePath;
        return true;
    }
    
    return false;
});

// Always load these essential classes
require_once __DIR__ . '/Config.php';

// Try to load Database and other classes, but don't fail if they don't exist yet
$essentialClasses = ['Database', 'Session', 'Auth'];
foreach ($essentialClasses as $class) {
    $filePath = __DIR__ . '/' . $class . '.php';
    if (file_exists($filePath)) {
        require_once $filePath;
    }
}