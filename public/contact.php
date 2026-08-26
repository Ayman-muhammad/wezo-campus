<?php
/**
 * WEZO CAMPUS HUB - Contact Us Page
 * Powered by AYGLOBE INC
 */

session_start();
require_once __DIR__ . '/../core/bootstrap.php';

use Core\Auth;
use Core\Database;

try {
    Auth::init();
    $db = Database::getInstance();
    $user = Auth::user();
    $isLoggedIn = Auth::isLoggedIn();
} catch (Exception $e) {
    $user = null;
    $isLoggedIn = false;
}

$pageTitle = "Contact Us - WEZO CAMPUS HUB";

// Handle form submission
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Validation
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($message) < 10) {
        $error = 'Message must be at least 10 characters long.';
    } else {
        // Process the message (in a real app, you'd save to database and send email)
        $success = 'Thank you for your message! We\'ll get back to you within 24-48 hours.';
        
        // For now, just log it
        error_log("Contact Form Submission: $name <$email> - $subject: $message");
        
        // Clear form
        $_POST = [];
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

<!-- Hero Section -->
<section class="hero-section py-5" style="background: linear-gradient(135deg, #1A56DB, #10B981);">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8 mx-auto text-center">
                <h1 class="display-4 fw-bold text-white mb-3">Contact Us</h1>
                <p class="lead text-white mb-0">We're here to help. Get in touch with our team.</p>
            </div>
        </div>
    </div>
</section>

<!-- Main Content -->
<div class="container py-5">
    <div class="row">
        <!-- Contact Form -->
        <div class="col-lg-8 mb-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <h2 class="fw-bold mb-4">Send us a Message</h2>
                    
                    <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                                       required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                       required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject *</label>
                            <input type="text" class="form-control" id="subject" name="subject"
                                   value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>"
                                   required>
                        </div>
                        
                        <div class="mb-4">
                            <label for="message" class="form-label">Your Message *</label>
                            <textarea class="form-control" id="message" name="message" rows="5" 
                                      required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-paper-plane me-2"></i> Send Message
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Contact Info & FAQ -->
        <div class="col-lg-4">
            <!-- Contact Information -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h4 class="fw-bold mb-4">
                        <i class="fas fa-info-circle text-primary me-2"></i> Contact Information
                    </h4>
                    
                    <div class="contact-item mb-3">
                        <div class="d-flex align-items-start">
                            <div class="flex-shrink-0">
                                <i class="fas fa-envelope text-primary me-3"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1">Email</h6>
                                <p class="mb-0">support@wezocampushub.com</p>
                                <p class="mb-0">contact@ayglobe.com</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="contact-item mb-3">
                        <div class="d-flex align-items-start">
                            <div class="flex-shrink-0">
                                <i class="fas fa-phone text-success me-3"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1">Phone</h6>
                                <p class="mb-0">+254 700 000 000</p>
                                <p class="mb-0">Mon-Fri, 9AM-6PM EAT</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <div class="d-flex align-items-start">
                            <div class="flex-shrink-0">
                                <i class="fas fa-map-marker-alt text-warning me-3"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1">Office Location</h6>
                                <p class="mb-0">Nairobi, Kenya</p>
                                <p class="mb-0">AYGLOBE INC Headquarters</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick FAQ -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h4 class="fw-bold mb-4">
                        <i class="fas fa-question-circle text-info me-2"></i> Quick Help
                    </h4>
                    
                    <div class="accordion" id="contactAccordion">
                        <div class="accordion-item border-0">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    How long for response?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#contactAccordion">
                                <div class="accordion-body">
                                    We typically respond within 24-48 hours during business days.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item border-0">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    Technical support?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#contactAccordion">
                                <div class="accordion-body">
                                    For technical issues, email support@wezocampushub.com with screenshots.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item border-0">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    Partnership inquiries?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#contactAccordion">
                                <div class="accordion-body">
                                    Contact partnerships@ayglobe.com for business and collaboration opportunities.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Map Section (Optional) -->
    <div class="row mt-5">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h4 class="fw-bold mb-4 text-center">
                        <i class="fas fa-map-marked-alt text-primary me-2"></i> Our Location
                    </h4>
                    <div class="map-placeholder text-center p-5" style="background: #f8f9fa; border-radius: 10px;">
                        <i class="fas fa-map fa-3x text-muted mb-3"></i>
                        <p class="mb-0">Nairobi, Kenya</p>
                        <p class="text-muted">AYGLOBE INC Headquarters</p>
                        <a href="https://maps.google.com/?q=Nairobi+Kenya" target="_blank" class="btn btn-outline-primary">
                            <i class="fas fa-external-link-alt me-2"></i> View on Google Maps
                        </a>
                    </div>
                </div>
            </div>
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
?>c