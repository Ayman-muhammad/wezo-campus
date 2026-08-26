<?php
/**
 * WEZO CAMPUS HUB - Terms of Service
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

$pageTitle = "Terms of Service - WEZO CAMPUS HUB";
$effectiveDate = "December 1, 2024";

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
                <h1 class="display-4 fw-bold text-white mb-3">Terms of Service</h1>
                <p class="lead text-white mb-0">Rules and guidelines for using WEZO CAMPUS HUB</p>
                <p class="text-white-50 mb-0">Effective Date: <?php echo $effectiveDate; ?></p>
            </div>
        </div>
    </div>
</section>

<!-- Quick Navigation -->
<div class="container py-3">
    <div class="row">
        <div class="col-12">
            <nav class="nav nav-pills justify-content-center">
                <a class="nav-link" href="#acceptance">Acceptance</a>
                <a class="nav-link" href="#accounts">Accounts</a>
                <a class="nav-link" href="#content">Content Rules</a>
                <a class="nav-link" href="#marketplace">Marketplace</a>
                <a class="nav-link" href="#prohibited">Prohibited</a>
                <a class="nav-link" href="#termination">Termination</a>
                <a class="nav-link" href="#disclaimer">Disclaimer</a>
            </nav>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="container py-5">
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <!-- Acceptance of Terms -->
            <div class="card border-0 shadow-sm mb-4" id="acceptance">
                <div class="card-body p-4 p-md-5">
                    <h2 class="fw-bold mb-4">1. Acceptance of Terms</h2>
                    <p>
                        By accessing and using WEZO CAMPUS HUB ("the Platform"), you agree to be bound by these Terms of Service 
                        and all applicable laws and regulations. If you do not agree with any of these terms, you are prohibited 
                        from using or accessing this site.
                    </p>
                    <p>
                        The materials contained in this Platform are protected by applicable copyright and trademark law. 
                        These Terms apply to all visitors, users, and others who access or use the Service.
                    </p>
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle me-2"></i>
                        By creating an account or using our services, you acknowledge that you have read, understood, 
                        and agree to be bound by these Terms.
                    </div>
                </div>
            </div>

            <!-- User Accounts -->
            <div class="card border-0 shadow-sm mb-4" id="accounts">
                <div class="card-body p-4 p-md-5">
                    <h2 class="fw-bold mb-4">2. User Accounts</h2>
                    
                    <h5 class="fw-bold mb-3">2.1 Account Creation</h5>
                    <p>To access certain features, you must register for an account. When you create an account, you agree to:</p>
                    <ul>
                        <li>Provide accurate, current, and complete information</li>
                        <li>Maintain and promptly update your account information</li>
                        <li>Maintain the security of your password and accept all risks of unauthorized access</li>
                        <li>Notify us immediately of any unauthorized use of your account</li>
                        <li>Be responsible for all activities that occur under your account</li>
                    </ul>
                    
                    <h5 class="fw-bold mb-3 mt-4">2.2 Student Verification</h5>
                    <p>To access student-specific features, you may need to verify your student status by:</p>
                    <ul>
                        <li>Providing a valid student ID</li>
                        <li>Using an institutional email address</li>
                        <li>Other verification methods we may introduce</li>
                    </ul>
                    <p>False representation of student status may result in account termination.</p>
                    
                    <h5 class="fw-bold mb-3 mt-4">2.3 Account Restrictions</h5>
                    <p>You may not:</p>
                    <ul>
                        <li>Create multiple accounts for fraudulent purposes</li>
                        <li>Share your account credentials with others</li>
                        <li>Use another user's account without permission</li>
                        <li>Create an account if you are under 16 years old</li>
                    </ul>
                </div>
            </div>

            <!-- Content Guidelines -->
            <div class="card border-0 shadow-sm mb-4" id="content">
                <div class="card-body p-4 p-md-5">
                    <h2 class="fw-bold mb-4">3. Content Guidelines</h2>
                    
                    <h5 class="fw-bold mb-3">3.1 User Content</h5>
                    <p>You retain ownership of any intellectual property rights that you hold in content you submit to the Platform. By submitting content, you grant us a worldwide, non-exclusive, royalty-free license to use, reproduce, modify, and display such content in connection with the Service.</p>
                    
                    <h5 class="fw-bold mb-3 mt-4">3.2 Study Materials</h5>
                    <p>When uploading study materials, you agree that:</p>
                    <ul>
                        <li>You own the rights to the materials or have permission to share them</li>
                        <li>Materials do not violate copyright or intellectual property rights</li>
                        <li>Materials are accurate and helpful for educational purposes</li>
                        <li>You clearly indicate if materials are free or paid</li>
                        <li>Paid materials are reasonably priced and fairly represent their value</li>
                    </ul>
                    
                    <h5 class="fw-bold mb-3 mt-4">3.3 Content Moderation</h5>
                    <p>We reserve the right to:</p>
                    <ul>
                        <li>Review, moderate, and remove any content</li>
                        <li>Disable access to content that violates these Terms</li>
                        <li>Cooperate with law enforcement authorities</li>
                        <li>Take appropriate legal action against violators</li>
                    </ul>
                </div>
            </div>

            <!-- Marketplace Rules -->
            <div class="card border-0 shadow-sm mb-4" id="marketplace">
                <div class="card-body p-4 p-md-5">
                    <h2 class="fw-bold mb-4">4. Marketplace Rules</h2>
                    
                    <h5 class="fw-bold mb-3">4.1 Listing Items</h5>
                    <p>When listing items for sale, you agree to:</p>
                    <ul>
                        <li>Provide accurate descriptions and clear photos</li>
                        <li>Set fair and reasonable prices</li>
                        <li>Honor the listed price and availability</li>
                        <li>Clearly state the condition of items</li>
                        <li>Respond to inquiries in a timely manner</li>
                    </ul>
                    
                    <h5 class="fw-bold mb-3 mt-4">4.2 Transactions</h5>
                    <p>All marketplace transactions are between buyers and sellers. WEZO CAMPUS HUB:</p>
                    <ul>
                        <li>Is not a party to transactions</li>
                        <li>Does not handle payments (unless specified)</li>
                        <li>Does not guarantee item quality or seller reliability</li>
                        <li>Is not responsible for transaction disputes</li>
                    </ul>
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        We strongly recommend meeting in safe, public places for transactions and using secure payment methods.
                    </div>
                    
                    <h5 class="fw-bold mb-3 mt-4">4.3 Fees</h5>
                    <p>For paid study materials and certain premium features, we charge a service fee:</p>
                    <ul>
                        <li>Sellers receive 80% of the sale price</li>
                        <li>20% goes to platform maintenance and development</li>
                        <li>Fees are clearly displayed before purchase</li>
                        <li>All prices are in Kenyan Shillings (KES)</li>
                    </ul>
                </div>
            </div>

            <!-- Prohibited Activities -->
            <div class="card border-0 shadow-sm mb-4" id="prohibited">
                <div class="card-body p-4 p-md-5">
                    <h2 class="fw-bold mb-4">5. Prohibited Activities</h2>
                    <p>You agree not to engage in any of the following prohibited activities:</p>
                    
                    <div class="row mt-3">
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-danger">
                                <div class="card-body">
                                    <h5 class="fw-bold text-danger">Illegal Activities</h5>
                                    <ul class="mb-0">
                                        <li>Fraud or deception</li>
                                        <li>Selling illegal items</li>
                                        <li>Copyright infringement</li>
                                        <li>Harassment or threats</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-warning">
                                <div class="card-body">
                                    <h5 class="fw-bold text-warning">Platform Abuse</h5>
                                    <ul class="mb-0">
                                        <li>Spamming or phishing</li>
                                        <li>Creating fake accounts</li>
                                        <li>Automated data collection</li>
                                        <li>Overloading systems</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-info">
                                <div class="card-body">
                                    <h5 class="fw-bold text-info">Content Violations</h5>
                                    <ul class="mb-0">
                                        <li>Adult or explicit content</li>
                                        <li>False information</li>
                                        <li>Hate speech</li>
                                        <li>Malicious software</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-success">
                                <div class="card-body">
                                    <h5 class="fw-bold text-success">Commercial Restrictions</h5>
                                    <ul class="mb-0">
                                        <li>Unauthorized advertising</li>
                                        <li>Commercial solicitation</li>
                                        <li>Affiliate marketing</li>
                                        <li>Reselling services</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Termination -->
            <div class="card border-0 shadow-sm mb-4" id="termination">
                <div class="card-body p-4 p-md-5">
                    <h2 class="fw-bold mb-4">6. Termination</h2>
                    
                    <h5 class="fw-bold mb-3">6.1 By User</h5>
                    <p>You may terminate your account at any time by:</p>
                    <ul>
                        <li>Going to Account Settings</li>
                        <li>Selecting "Delete Account"</li>
                        <li>Following the confirmation steps</li>
                    </ul>
                    <p>Account deletion may take up to 30 days to complete, and some data may be retained for legal purposes.</p>
                    
                    <h5 class="fw-bold mb-3 mt-4">6.2 By WEZO CAMPUS HUB</h5>
                    <p>We may terminate or suspend your account immediately, without prior notice or liability, for any reason, including:</p>
                    <ul>
                        <li>Breach of these Terms</li>
                        <li>Fraudulent activity</li>
                        <li>Legal requirements</li>
                        <li>Platform security concerns</li>
                    </ul>
                    
                    <h5 class="fw-bold mb-3 mt-4">6.3 Effect of Termination</h5>
                    <p>Upon termination:</p>
                    <ul>
                        <li>Your right to use the Service will immediately cease</li>
                        <li>All content may be deleted (backups retained for 30 days)</li>
                        <li>Outstanding payments will be processed according to our policies</li>
                        <li>You remain liable for any obligations incurred before termination</li>
                    </ul>
                </div>
            </div>

            <!-- Disclaimer & Limitation -->
            <div class="card border-0 shadow-sm mb-4" id="disclaimer">
                <div class="card-body p-4 p-md-5">
                    <h2 class="fw-bold mb-4">7. Disclaimer & Limitation of Liability</h2>
                    
                    <h5 class="fw-bold mb-3">7.1 Service "As Is"</h5>
                    <p>The Service is provided on an "AS IS" and "AS AVAILABLE" basis. WEZO CAMPUS HUB makes no warranties, expressed or implied, and hereby disclaims all other warranties including, without limitation, implied warranties or conditions of merchantability, fitness for a particular purpose, or non-infringement of intellectual property.</p>
                    
                    <h5 class="fw-bold mb-3 mt-4">7.2 Accuracy of Materials</h5>
                    <p>The materials appearing on the Platform could include technical, typographical, or photographic errors. We do not warrant that any of the materials are accurate, complete, or current. We may make changes to the materials at any time without notice.</p>
                    
                    <h5 class="fw-bold mb-3 mt-4">7.3 Limitation of Liability</h5>
                    <p>In no event shall WEZO CAMPUS HUB or its suppliers be liable for any damages (including, without limitation, damages for loss of data or profit, or due to business interruption) arising out of the use or inability to use the materials, even if we have been notified orally or in writing of the possibility of such damage.</p>
                    
                    <div class="alert alert-danger mt-3">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Some jurisdictions do not allow limitations on implied warranties or limitations of liability for incidental or consequential damages, so these limitations may not apply to you.
                    </div>
                </div>
            </div>

            <!-- Governing Law -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-md-5">
                    <h2 class="fw-bold mb-4">8. Governing Law</h2>
                    <p>These Terms shall be governed and construed in accordance with the laws of Kenya, without regard to its conflict of law provisions.</p>
                    <p>Any disputes arising from these Terms or your use of the Service shall be subject to the exclusive jurisdiction of the courts located in Nairobi, Kenya.</p>
                </div>
            </div>

            <!-- Changes to Terms -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-md-5">
                    <h2 class="fw-bold mb-4">9. Changes to Terms</h2>
                    <p>We reserve the right, at our sole discretion, to modify or replace these Terms at any time. If a revision is material, we will provide at least 30 days' notice prior to any new terms taking effect.</p>
                    <p>By continuing to access or use our Service after those revisions become effective, you agree to be bound by the revised terms. If you do not agree to the new terms, please stop using the Service.</p>
                </div>
            </div>

            <!-- Contact & Acceptance -->
            <div class="card border-primary">
                <div class="card-body p-4 p-md-5 text-center">
                    <h2 class="fw-bold mb-4">10. Contact & Acceptance</h2>
                    
                    <div class="mb-4">
                        <p>For questions about these Terms, please contact us:</p>
                        <p>
                            <strong>Legal Department</strong><br>
                            WEZO CAMPUS HUB<br>
                            <a href="mailto:ayglobe@gmail.com">click here</a>
                        </p>
                    </div>
                    
                    <div class="alert alert-success">
                        <h5 class="alert-heading">
                            <i class="fas fa-check-circle me-2"></i>
                            By using WEZO CAMPUS HUB, you acknowledge that you have read, understood, and agree to these Terms of Service.
                        </h5>
                        <p class="mb-0">These Terms constitute the entire agreement between you and WEZO CAMPUS HUB regarding our Service.</p>
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
?>

<style>
.nav-pills .nav-link {
    color: #666;
    margin: 0 5px;
    border-radius: 20px;
}

.nav-pills .nav-link:hover {
    background-color: rgba(26, 86, 219, 0.1);
    color: #1A56DB;
}

.card {
    scroll-margin-top: 100px;
}
</style>

<script>
// Smooth scrolling for navigation
document.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        const targetId = this.getAttribute('href');
        const targetElement = document.querySelector(targetId);
        
        if (targetElement) {
            window.scrollTo({
                top: targetElement.offsetTop - 100,
                behavior: 'smooth'
            });
        }
    });
});

// Highlight active section
window.addEventListener('scroll', function() {
    const sections = document.querySelectorAll('.card[id]');
    const navLinks = document.querySelectorAll('.nav-link');
    
    let currentSection = '';
    
    sections.forEach(section => {
        const sectionTop = section.offsetTop - 150;
        const sectionHeight = section.clientHeight;
        
        if (window.scrollY >= sectionTop && window.scrollY < sectionTop + sectionHeight) {
            currentSection = section.getAttribute('id');
        }
    });
    
    navLinks.forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href') === `#${currentSection}`) {
            link.classList.add('active');
            link.style.backgroundColor = '#1A56DB';
            link.style.color = 'white';
        } else {
            link.style.backgroundColor = '';
            link.style.color = '#666';
        }
    });
});
</script>