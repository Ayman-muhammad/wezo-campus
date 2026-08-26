<?php
/**
 * WEZO CAMPUS HUB - Delete Email Previews API
 * For development mode only
 */

require_once __DIR__ . '/../core/bootstrap.php';

// Only allow in development mode
if (Config::APP_ENV !== 'development') {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

// Get parameters
$filename = $_GET['file'] ?? '';
$deleteAll = isset($_GET['all']) && $_GET['all'] === 'true';

$previewDir = __DIR__ . '/../email_previews/';

if ($deleteAll) {
    // Delete all files in preview directory
    $files = glob($previewDir . '*.html');
    $deletedCount = 0;
    
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
            $deletedCount++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Deleted {$deletedCount} email previews"
    ]);
    
} elseif (!empty($filename)) {
    // Delete specific file
    $filepath = $previewDir . basename($filename);
    
    // Only allow deletion of HTML files in the preview directory
    if (strpos(realpath($filepath), realpath($previewDir)) === 0 && 
        pathinfo($filepath, PATHINFO_EXTENSION) === 'html' &&
        file_exists($filepath)) {
        
        if (unlink($filepath)) {
            echo json_encode([
                'success' => true,
                'message' => 'Email preview deleted'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to delete file'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid file or access denied'
        ]);
    }
    
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No file specified'
    ]);
}d