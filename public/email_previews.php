<?php
/**
 * WEZO CAMPUS HUB - Email Previews
 * View saved email previews in development mode
 * Powered by AYGLOBE INC
 */

session_start();
require_once __DIR__ . '/../core/bootstrap.php';

use Core\Auth;

// Only allow in development mode
if (Config::APP_ENV !== 'development') {
    header('Location: /');
    exit;
}

$pageTitle = "Email Previews - WEZO CAMPUS HUB";

$previewDir = __DIR__ . '/../email_previews/';
$previews = [];

if (is_dir($previewDir)) {
    $files = scandir($previewDir);
    $files = array_diff($files, ['.', '..']);
    
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'html') {
            $filepath = $previewDir . $file;
            $previews[] = [
                'filename' => $file,
                'path' => '../email_previews/' . $file,
                'size' => filesize($filepath),
                'modified' => date('Y-m-d H:i:s', filemtime($filepath))
            ];
        }
    }
    
    // Sort by modification time (newest first)
    usort($previews, function($a, $b) {
        return strtotime($b['modified']) - strtotime($a['modified']);
    });
}

// Include header
include __DIR__ . '/../templates/mini-header.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h1 class="h3 fw-bold mb-3">
                        <i class="fas fa-envelope-open-text text-primary me-2"></i>
                        Email Previews (Development Mode)
                    </h1>
                    <p class="text-muted">
                        In development mode, emails are saved as HTML files instead of being sent.
                        This prevents spamming during testing.
                    </p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> When deployed to production, emails will be sent normally.
                    </div>
                </div>
            </div>
            
            <?php if (empty($previews)): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="fas fa-inbox fa-4x text-muted mb-4"></i>
                    <h3 class="h5 fw-bold mb-3">No Email Previews Yet</h3>
                    <p class="text-muted mb-4">Trigger an email (like user registration) to see previews here.</p>
                    <a href="/register.php" class="btn btn-primary">
                        <i class="fas fa-user-plus me-2"></i> Test Registration
                    </a>
                </div>
            </div>
            <?php else: ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Preview</th>
                                    <th>Filename</th>
                                    <th>Size</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($previews as $preview): ?>
                                <tr>
                                    <td>
                                        <i class="fas fa-envelope text-primary me-2"></i>
                                        Email Preview
                                    </td>
                                    <td>
                                        <code><?php echo htmlspecialchars($preview['filename']); ?></code>
                                    </td>
                                    <td><?php echo round($preview['size'] / 1024, 2); ?> KB</td>
                                    <td><?php echo $preview['modified']; ?></td>
                                    <td>
                                        <a href="<?php echo $preview['path']; ?>" target="_blank" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye me-1"></i> View
                                        </a>
                                        <a href="<?php echo $preview['path']; ?>" download class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-download me-1"></i> Download
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">
                            Showing <?php echo count($previews); ?> email previews
                        </span>
                        <a href="javascript:location.reload()" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-sync-alt me-1"></i> Refresh
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/mini-footer.php'; ?>