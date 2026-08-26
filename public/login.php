<?php
/**
 * WEZO CAMPUS HUB - Login Page
 * Powered by AYGLOBE INC
 */
require_once __DIR__ . '/../core/Config.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Session.php';
// At the top of your PHP files:
require_once __DIR__ . '/../core/autoload.php';
use Core\Database;

use Core\Auth;
use Core\Session;

// Initialize
Auth::init();

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    header('Location: /');
    exit;
}

// Handle login form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !Auth::validateCsrfToken($_POST['csrf_token'])) {
        $error = 'Security token invalid. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        
        if (empty($email) || empty($password)) {
            $error = 'Please fill in all required fields.';
        } else {
            $result = Auth::login($email, $password);
            
            if ($result['success']) {
                // Set remember me cookie if requested
                if ($remember) {
                    setcookie('remember_user', $result['user']['id'], time() + (30 * 24 * 60 * 60), '/');
                }
                
                // Redirect to intended page or home
                $redirect = $_GET['redirect'] ?? '/wezo-campus/public/index.php';
                header('Location: ' . $redirect);
                exit;
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Set page title
$pageTitle = "Login - WEZO CAMPUS HUB";

// Include header
include __DIR__ . '/../templates/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <!-- Login Card -->
            <div class="card shadow-lg border-0">
                <div class="card-header bg-primary text-white text-center py-4">
                    <h3 class="mb-0">
                        <i class="fas fa-sign-in-alt me-2"></i> Login
                    </h3>
                    <p class="mb-0 opacity-75">Access your WEZO CAMPUS HUB account</p>
                </div>
                
                <div class="card-body p-4">
                    <!-- Display errors -->
                    <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Login Form -->
                    <form method="POST" action="" id="loginForm">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">
                        
                        <!-- Email -->
                        <div class="mb-3">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope me-1"></i> Email Address
                            </label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                   required
                                   placeholder="Enter your email">
                            <div class="invalid-feedback">Please enter a valid email address.</div>
                        </div>
                        
                        <!-- Password -->
                        <div class="mb-3">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock me-1"></i> Password
                            </label>
                            <div class="input-group">
                                <input type="password" 
                                       class="form-control" 
                                       id="password" 
                                       name="password" 
                                       required
                                       placeholder="Enter your password">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">Please enter your password.</div>
                        </div>
                        
                        <!-- Remember Me & Forgot Password -->
                        <div class="mb-3 d-flex justify-content-between align-items-center">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remember" name="remember">
                                <label class="form-check-label" for="remember">
                                    Remember me
                                </label>
                            </div>
                            <a href="/forgot-password.php" class="text-decoration-none small">
                                Forgot password?
                            </a>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i> Login
                            </button>
                        </div>
                        
                        <!-- Demo Accounts (for development) -->
                        <?php if (Core\Config::APP_ENV === 'development'): ?>
                        <div class="card border-info mb-3">
                            <div class="card-header bg-info text-white py-2 small">
                                <i class="fas fa-vial me-1"></i> Demo Accounts
                            </div>
                            <div class="card-body p-3 small">
                                <p class="mb-1"><strong>Admin:</strong> admin@wezocampus.local / admin123</p>
                                <p class="mb-0"><strong>Student:</strong> john.doe@student.wezo.edu / password</p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </form>
                    
                    <!-- Divider -->
                    <div class="position-relative my-4">
                        <hr>
                        <div class="position-absolute top-50 start-50 translate-middle bg-white px-3">
                            <span class="text-muted">Or continue with</span>
                        </div>
                    </div>
                    
                    <!-- Social Login (Placeholder) -->
                    <div class="row g-2 mb-4">
                        <div class="col">
                            <button type="button" class="btn btn-outline-primary w-100" disabled>
                                <i class="fab fa-google me-2"></i> Google
                            </button>
                        </div>
                        <div class="col">
                            <button type="button" class="btn btn-outline-dark w-100" disabled>
                                <i class="fab fa-github me-2"></i> GitHub
                            </button>
                        </div>
                    </div>
                    
                    <!-- Registration Link -->
                    <div class="text-center">
                        <p class="mb-2">Don't have an account?</p>
                        <a href="register.php" class="btn btn-outline-success">
                            <i class="fas fa-user-plus me-1"></i> Create Account
                        </a>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="card-footer text-center py-3">
                    <small class="text-muted">
                        By logging in, you agree to our 
                        <a href="terms.php" class="text-decoration-none">Terms of Service</a> and 
                        <a href="privacy.php" class="text-decoration-none">Privacy Policy</a>.
                    </small>
                </div>
            </div>
            
            <!-- Safety Tips -->
            <div class="card border-warning mt-4">
                <div class="card-header bg-warning text-dark py-2">
                    <i class="fas fa-shield-alt me-1"></i> Safety Tips
                </div>
                <div class="card-body p-3 small">
                    <ul class="mb-0">
                        <li>Never share your password with anyone</li>
                        <li>Use a strong, unique password</li>
                        <li>Log out from public computers</li>
                        <li>Enable two-factor authentication if available</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle password visibility
document.getElementById('togglePassword').addEventListener('click', function() {
    const passwordInput = document.getElementById('password');
    const icon = this.querySelector('i');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
});

// Form validation
document.getElementById('loginForm').addEventListener('submit', function(e) {
    const email = document.getElementById('email');
    const password = document.getElementById('password');
    let isValid = true;
    
    // Email validation
    if (!email.value || !email.value.includes('@')) {
        email.classList.add('is-invalid');
        isValid = false;
    } else {
        email.classList.remove('is-invalid');
    }
    
    // Password validation
    if (!password.value) {
        password.classList.add('is-invalid');
        isValid = false;
    } else {
        password.classList.remove('is-invalid');
    }
    
    if (!isValid) {
        e.preventDefault();
    }
});
</script>

<?php
// Include footer
include __DIR__ . '/../templates/footer.php';
?>