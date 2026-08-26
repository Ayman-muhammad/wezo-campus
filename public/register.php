<?php
/**
 * WEZO CAMPUS HUB - Registration Page
 * Powered by AYGLOBE INC
 */
require_once __DIR__ . '/../core/bootstrap.php';

use Core\Auth;
use Core\Session;

// Initialize
Auth::init();

// Check if registration is enabled
if (!Core\Config::isFeatureEnabled('registration')) {
    header('Location: /maintenance.php');
    exit;
}

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    header('Location: /');
    exit;
}

// Handle registration form submission
$errors = [];
$success = '';
$formData = [
    'first_name' => '',
    'last_name' => '',
    'username' => '',
    'email' => '',
    'phone' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !Auth::validateCsrfToken($_POST['csrf_token'])) {
        $errors[] = 'Security token invalid. Please try again.';
    } else {
        // Collect form data
        $formData = [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'username' => trim($_POST['username'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'confirm_password' => $_POST['confirm_password'] ?? '',
            'terms' => isset($_POST['terms'])
        ];
        
        // Validate required fields
        $required = ['first_name', 'last_name', 'username', 'email', 'password', 'confirm_password'];
        foreach ($required as $field) {
            if (empty($formData[$field])) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            }
        }
        
        // Validate terms agreement
        if (!$formData['terms']) {
            $errors[] = 'You must agree to the Terms of Service and Privacy Policy.';
        }
        
        // Validate email
        if (!empty($formData['email']) && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        
        // Validate username (alphanumeric, underscores, hyphens)
        if (!empty($formData['username']) && !preg_match('/^[a-zA-Z0-9_-]{3,20}$/', $formData['username'])) {
            $errors[] = 'Username must be 3-20 characters and can only contain letters, numbers, underscores, and hyphens.';
        }
        
        // Validate password strength
        if (!empty($formData['password']) && strlen($formData['password']) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }
        
        // Validate password match
        if (!empty($formData['password']) && !empty($formData['confirm_password']) && 
            $formData['password'] !== $formData['confirm_password']) {
            $errors[] = 'Passwords do not match.';
        }
        
        // If no errors, attempt registration
        if (empty($errors)) {
            $result = Auth::register($formData);
            
            if ($result['success']) {
                Session::flash('success', $result['message']);
                header('Location: /login.php');
                exit;
            } else {
                $errors[] = $result['message'];
            }
        }
    }
}

// Set page title
$pageTitle = "Register - WEZO CAMPUS HUB";

// Include header
include __DIR__ . '/../templates/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-7">
            <!-- Registration Card -->
            <div class="card shadow-lg border-0">
                <div class="card-header bg-success text-white text-center py-4">
                    <h3 class="mb-0">
                        <i class="fas fa-user-plus me-2"></i> Join WEZO CAMPUS HUB
                    </h3>
                    <p class="mb-0 opacity-75">Create your free student account today</p>
                </div>
                
                <div class="card-body p-4">
                    <!-- Display errors -->
                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <h5 class="alert-heading">
                            <i class="fas fa-exclamation-triangle me-2"></i> Please fix the following errors:
                        </h5>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Registration Form -->
                    <form method="POST" action="" id="registerForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">
                        
                        <div class="row">
                            <!-- First Name -->
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">
                                    <i class="fas fa-user me-1"></i> First Name *
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="first_name" 
                                       name="first_name" 
                                       value="<?php echo htmlspecialchars($formData['first_name']); ?>"
                                       required
                                       placeholder="Enter your first name">
                                <div class="invalid-feedback">Please enter your first name.</div>
                            </div>
                            
                            <!-- Last Name -->
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">
                                    <i class="fas fa-user me-1"></i> Last Name *
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="last_name" 
                                       name="last_name" 
                                       value="<?php echo htmlspecialchars($formData['last_name']); ?>"
                                       required
                                       placeholder="Enter your last name">
                                <div class="invalid-feedback">Please enter your last name.</div>
                            </div>
                        </div>
                        
                        <!-- Username -->
                        <div class="mb-3">
                            <label for="username" class="form-label">
                                <i class="fas fa-at me-1"></i> Username *
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">@</span>
                                <input type="text" 
                                       class="form-control" 
                                       id="username" 
                                       name="username" 
                                       value="<?php echo htmlspecialchars($formData['username']); ?>"
                                       required
                                       pattern="[a-zA-Z0-9_-]{3,20}"
                                       placeholder="Choose a username">
                            </div>
                            <div class="form-text">3-20 characters. Letters, numbers, underscores, and hyphens only.</div>
                            <div class="invalid-feedback">Please choose a valid username.</div>
                        </div>
                        
                        <!-- Email -->
                        <div class="mb-3">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope me-1"></i> Email Address *
                            </label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   value="<?php echo htmlspecialchars($formData['email']); ?>"
                                   required
                                   placeholder="Enter your email address">
                            <div class="form-text">Use your student email if available.</div>
                            <div class="invalid-feedback">Please enter a valid email address.</div>
                        </div>
                        
                        <!-- Phone (Optional) -->
                        <div class="mb-3">
                            <label for="phone" class="form-label">
                                <i class="fas fa-phone me-1"></i> Phone Number (Optional)
                            </label>
                            <input type="tel" 
                                   class="form-control" 
                                   id="phone" 
                                   name="phone" 
                                   value="<?php echo htmlspecialchars($formData['phone']); ?>"
                                   placeholder="Enter your phone number">
                            <div class="form-text">Used for important notifications and verification.</div>
                        </div>
                        
                        <div class="row">
                            <!-- Password -->
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock me-1"></i> Password *
                                </label>
                                <div class="input-group">
                                    <input type="password" 
                                           class="form-control" 
                                           id="password" 
                                           name="password" 
                                           required
                                           minlength="8"
                                           placeholder="Create a password">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">Password must be at least 8 characters.</div>
                                <div class="password-strength mt-2">
                                    <div class="progress" style="height: 5px;">
                                        <div class="progress-bar" id="passwordStrength" style="width: 0%"></div>
                                    </div>
                                    <small class="text-muted" id="passwordStrengthText">Password strength</small>
                                </div>
                            </div>
                            
                            <!-- Confirm Password -->
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">
                                    <i class="fas fa-lock me-1"></i> Confirm Password *
                                </label>
                                <div class="input-group">
                                    <input type="password" 
                                           class="form-control" 
                                           id="confirm_password" 
                                           name="confirm_password" 
                                           required
                                           placeholder="Confirm your password">
                                    <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">Passwords do not match.</div>
                                <div id="passwordMatch" class="mt-2 small"></div>
                            </div>
                        </div>
                        
                        <!-- Terms and Conditions -->
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="terms" name="terms" <?php echo $formData['terms'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="terms">
                                    I agree to the 
                                    <a href="terms.php" target="_blank" class="text-decoration-none">Terms of Service</a> and 
                                    <a href="privacy.php" target="_blank" class="text-decoration-none">Privacy Policy</a> *
                                </label>
                                <div class="invalid-feedback">You must agree to the terms.</div>
                            </div>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-user-plus me-2"></i> Create Account
                            </button>
                        </div>
                        
                        <!-- Already have account -->
                        <div class="text-center">
                            <p class="mb-0">
                                Already have an account? 
                                <a href="login.php" class="text-decoration-none">Login here</a>
                            </p>
                        </div>
                    </form>
                </div>
                
                <!-- Footer -->
                <div class="card-footer text-center py-3">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt me-1"></i> Your data is protected and will never be shared with third parties.
                    </small>
                </div>
            </div>
            
            <!-- Benefits Card -->
            <div class="card border-info mt-4">
                <div class="card-header bg-info text-white py-3">
                    <h5 class="mb-0">
                        <i class="fas fa-star me-2"></i> Benefits of Joining
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-book fa-2x text-primary"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6>Access Study Materials</h6>
                                    <p class="small mb-0">Share and download notes, past papers, and resources.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-store fa-2x text-success"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6>Sell & Buy Items</h6>
                                    <p class="small mb-0">Trade textbooks, electronics, and more with fellow students.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-bed fa-2x text-info"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6>Find Accommodation</h6>
                                    <p class="small mb-0">Browse and compare hostels near campus.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-comments fa-2x text-warning"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6>Join Community</h6>
                                    <p class="small mb-0">Connect with students, join discussions, and share experiences.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white text-center">
                    <small class="text-muted">
                        Powered by AYGLOBE INC | Founder: Ayman Muhammad
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle password visibility
function setupPasswordToggle(inputId, buttonId) {
    const button = document.getElementById(buttonId);
    const input = document.getElementById(inputId);
    
    button.addEventListener('click', function() {
        const icon = this.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });
}

// Setup password toggles
setupPasswordToggle('password', 'togglePassword');
setupPasswordToggle('confirm_password', 'toggleConfirmPassword');

// Password strength checker
document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    const strengthBar = document.getElementById('passwordStrength');
    const strengthText = document.getElementById('passwordStrengthText');
    
    let strength = 0;
    let color = 'bg-danger';
    let text = 'Very Weak';
    
    if (password.length >= 8) strength += 25;
    if (/[A-Z]/.test(password)) strength += 25;
    if (/[0-9]/.test(password)) strength += 25;
    if (/[^A-Za-z0-9]/.test(password)) strength += 25;
    
    strengthBar.style.width = strength + '%';
    
    if (strength >= 75) {
        color = 'bg-success';
        text = 'Strong';
    } else if (strength >= 50) {
        color = 'bg-warning';
        text = 'Medium';
    } else if (strength >= 25) {
        color = 'bg-danger';
        text = 'Weak';
    }
    
    strengthBar.className = 'progress-bar ' + color;
    strengthText.textContent = text + ' (' + strength + '%)';
});

// Password match checker
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    const matchDiv = document.getElementById('passwordMatch');
    
    if (confirmPassword === '') {
        matchDiv.innerHTML = '';
        this.classList.remove('is-invalid', 'is-valid');
    } else if (password === confirmPassword) {
        matchDiv.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i> Passwords match</span>';
        this.classList.remove('is-invalid');
        this.classList.add('is-valid');
    } else {
        matchDiv.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle me-1"></i> Passwords do not match</span>';
        this.classList.remove('is-valid');
        this.classList.add('is-invalid');
    }
});

// Form validation
document.getElementById('registerForm').addEventListener('submit', function(e) {
    const requiredFields = ['first_name', 'last_name', 'username', 'email', 'password', 'confirm_password'];
    const terms = document.getElementById('terms');
    let isValid = true;
    
    // Check required fields
    requiredFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    // Check terms
    if (!terms.checked) {
        terms.classList.add('is-invalid');
        isValid = false;
    } else {
        terms.classList.remove('is-invalid');
    }
    
    // Check password match
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    if (password !== confirmPassword) {
        document.getElementById('confirm_password').classList.add('is-invalid');
        isValid = false;
    }
    
    if (!isValid) {
        e.preventDefault();
        alert('Please fill in all required fields correctly.');
    }
});

// Real-time username availability check
document.getElementById('username').addEventListener('blur', function() {
    const username = this.value.trim();
    const feedback = this.nextElementSibling?.nextElementSibling || this.parentElement.nextElementSibling;
    
    if (username.length >= 3 && username.length <= 20) {
        // AJAX check would go here
        // For now, just show checking message
        const originalText = feedback.textContent;
        feedback.textContent = 'Checking availability...';
        feedback.className = 'form-text text-info';
        
        // Simulate API call
        setTimeout(() => {
            // This would be replaced with actual AJAX call
            const isAvailable = Math.random() > 0.3; // Simulated result
            if (isAvailable) {
                feedback.textContent = 'Username is available!';
                feedback.className = 'form-text text-success';
            } else {
                feedback.textContent = 'Username is already taken.';
                feedback.className = 'form-text text-danger';
            }
        }, 500);
    }
});
</script>

<?php
// Include footer
include __DIR__ . '/../templates/footer.php';
?>