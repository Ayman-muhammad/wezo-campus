<?php
/**
 * WEZO CAMPUS HUB - Email Verification
 * Powered by AYGLOBE INC
 */

session_start();
require_once __DIR__ . '/../core/bootstrap.php';

use Core\Auth;
use Core\Database;

$pageTitle = "Email Verification - WEZO CAMPUS HUB";

// Get token from URL
$token = $_GET['token'] ?? '';

// Initialize
try {
    Auth::init();
    $db = Database::getInstance();
    $user = Auth::user();
    $isLoggedIn = Auth::isLoggedIn();
} catch (Exception $e) {
    $user = null;
    $isLoggedIn = false;
}

$message = '';
$success = false;

// Process verification
if (!empty($token)) {
    $result = Auth::verifyEmail($token);
    
    if ($result['success']) {
        $success = true;
        $message = $result['message'];
    } else {
        $message = $result['message'];
    }
}

// Include header
$headerPath = __DIR__ . '/../templates/header.php';
if (file_exists($headerPath)) {
    include $headerPath;
} else {
    include __DIR__ . '/../templates/mini-header.php';
}
?>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-6 mx-auto">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="mb-4">
                            <?php if ($success): ?>
                            <div class="icon-success mb-3" style="width: 80px; height: 80px; background: rgba(16, 185, 129, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                <i class="fas fa-check-circle fa-3x text-success"></i>
                            </div>
                            <?php else: ?>
                            <div class="icon-error mb-3" style="width: 80px; height: 80px; background: rgba(220, 53, 69, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                <i class="fas fa-exclamation-circle fa-3x text-danger"></i>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <h1 class="h3 fw-bold mb-3">
                            <?php echo $success ? 'Email Verified!' : 'Verification Failed'; ?>
                        </h1>
                        
                        <?php if (!empty($token)): ?>
                        <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?>">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-<?php echo $success ? 'check' : 'times'; ?>-circle me-2"></i>
                                </div>
                                <div>
                                    <?php echo htmlspecialchars($message); ?>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No verification token provided. Please check your email for the verification link.
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="text-center mt-4">
                        <?php if ($success): ?>
                        <div class="d-grid gap-3">
                            <a href="/login.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i> Login to Your Account
                            </a>
                            <a href="/" class="btn btn-outline-primary">
                                <i class="fas fa-home me-2"></i> Go to Homepage
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="d-grid gap-3">
                            <a href="/register.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-user-plus me-2"></i> Register Again
                            </a>
                            <a href="/contact.php" class="btn btn-outline-primary">
                                <i class="fas fa-envelope me-2"></i> Contact Support
                            </a>
                            <a href="/" class="btn btn-outline-secondary">
                                <i class="fas fa-home me-2"></i> Go to Homepage
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!$success && !empty($token)): ?>
                    <div class="mt-4 pt-4 border-top">
                        <h5 class="fw-bold mb-3">Troubleshooting:</h5>
                        <ul class="mb-0">
                            <li>Make sure you're using the exact link from your email</li>
                            <li>Verification links expire after 24 hours</li>
                            <li>Try registering again if link has expired</li>
                            <li>Check your spam folder for the verification email</li>
                            <li>Contact support if problems persist</li>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Development Info -->
            <?php if (Config::APP_ENV === 'development'): ?>
            <div class="card border-info mt-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3 text-info">
                        <i class="fas fa-code me-2"></i> Development Mode Info
                    </h5>
                    <p class="mb-2">In development mode, emails are saved as HTML files instead of being sent.</p>
                    <p class="mb-0">Check the <code>email_previews/</code> folder to see verification emails.</p>
                    
                    <?php
                    $previewDir = __DIR__ . '/../email_previews/';
                    if (is_dir($previewDir)) {
                        $files = scandir($previewDir);
                        $emailFiles = array_filter($files, function($file) {
                            return pathinfo($file, PATHINFO_EXTENSION) === 'html';
                        });
                        
                        if (!empty($emailFiles)): ?>
                        <div class="mt-3">
                            <a href="/email_preview.php" class="btn btn-sm btn-outline-info">
                                <i class="fas fa-envelope-open-text me-2"></i> View Email Previews
                            </a>
                        </div>
                        <?php endif;
                    }
                    ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
$footerPath = __DIR__ . '/../templates/footer.php';
if (file_exists($footerPath)) {
    include $footerPath;
} else {
    include __DIR__ . '/../templates/mini-footer.php';
}
?>