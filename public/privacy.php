<?php
/**
 * WEZO CAMPUS HUB - Privacy Policy
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

$pageTitle = "Privacy Policy - WEZO CAMPUS HUB";
$lastUpdated = "December 1, 2024";

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
                <h1 class="display-4 fw-bold text-white mb-3">Privacy Policy</h1>
                <p class="lead text-white mb-0">How we protect and use your information</p>
                <p class="text-white-50 mb-0">Last Updated: <?php echo $lastUpdated; ?></p>
            </div>
        </div>
    </div>
</section>

<!-- Main Content -->
<div class="container py-5">
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <!-- Introduction -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-md-5">
                    <h2 class="fw-bold mb-4">1. Introduction</h2>
                    <p>
                        Welcome to WEZO CAMPUS HUB ("we," "our," or "us"). We are committed to protecting your personal 
                        information and your right to privacy. This Privacy Policy explains how we collect, use, disclose, 
                        and safeguard your information when you visit our website and use our services.
                    </p>
                    <p>
                        By using WEZO CAMPUS HUB, you consent to the data practices described in this policy. If you do 
                        not agree with the terms of this privacy policy, please do not access the site.
                    </p>
                </div>
            </div>

            <!-- Information We Collect -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-md-5">
                    <h2 class="fw-bold mb-4">2. Information We Collect</h2>
                    
                    <h5 class="fw-bold mb-3">2.1 Personal Information</h5>
                    <p>We may collect personal information that you voluntarily provide to us when you:</p>
                    <ul>
                        <li>Register for an account</li>
                        <li>Update your profile information</li>
                        <li>List items for sale</li>
                        <li>Upload study materials</li>
                        <li>Contact our support team</li>
                        <li>Participate in community discussions</li>
                    </ul>
                    <p>This information may include:</p>
                    <ul>
                        <li>Name, email address, and phone number</li>
                        <li>Student identification information</li>
                        <li>Profile picture and biography</li>
                        <li>Educational institution and course details</li>
                        <li>Payment information (processed securely by third-party providers)</li>
                    </ul>
                    
                    <h5 class="fw-bold mb-3 mt-4">2.2 Automatically Collected Information</h5>
                    <p>When you use our services, we automatically collect:</p>
                    <ul>
                        <li>Device information (IP address, browser type, operating system)</li>
                        <li>Usage data (pages visited, time spent, features used)</li>
                        <li>Location data (general location based on IP address)</li>
                        <li>Cookies and similar tracking technologies</li>
                    </ul>
                </div>
            </div>

            <!-- How We Use Information -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-md-5">
                    <h2 class="fw-bold mb-4">3. How We Use Your Information</h2>
                    <p>We use the information we collect for various purposes, including:</p>
                    <ul>
                        <li>To create and manage your account</li>
                        <li>To provide and maintain our services</li>
                        <li>To process transactions and send related information</li>
                        <li>To send you technical notices, updates, and support messages</li>
                        <li>To respond to your comments and questions</li>
                        <li>To monitor and analyze usage patterns and trends</li>
                        <li>To prevent fraudulent activities and ensure security</li>
                        <li>To personalize your experience and provide relevant content</li>
                        <li>To send promotional communications (with your consent)</li>
                        <li>To comply with legal obligations</li>
                    </ul>
                </div>
            </div>

            <!-- Information Sharing -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-md-5">
                    <h2 class="fw-bold mb-4">4. Information Sharing and Disclosure</h2>
                    <p>We do not sell, trade, or rent your personal information to third parties. We may share information in the following circumstances:</p>
                    
                    <h5 class="fw-bold mb-3">4.1 With Your Consent</h5>
                    <p>We may disclose your information when you give us explicit consent to do so.</p>
                    
                    <h5 class="fw-bold mb-3">4.2 Service Providers</h5>
                    <p>We may share information with third-party vendors, consultants, and other service providers who need access to such information to carry out work on our behalf, including:</p>
                    <ul>
                        <li>Payment processing</li>
                        <li>Data analysis</li>
                        <li>Email delivery</li>
                        <li>Hosting services</li>
                        <li>Customer service</li>
                    </ul>
                    
                    <h5 class="fw-bold mb-3">4.3 Legal Requirements</h5>
                    <p>We may disclose your information if required to do so by law or in response to valid requests by public authorities.</p>
                    
                    <h5 class="fw-bold mb-3">4.4 Business Transfers</h5>
                    <p>In connection with any merger, sale of company assets, financing, or acquisition of all or a portion of our business to another company.</p>
                </div>
            </div>

            <!-- Data Security -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-md-5">
                    <h2 class="fw-bold mb-4">5. Data Security</h2>
                    <p>We implement appropriate technical and organizational security measures designed to protect the security of any personal information we process. However, please also remember that we cannot guarantee that the internet itself is 100% secure.</p>
                    <p>Our security measures include:</p>
                    <ul>
                        <li>SSL/TLS encryption for data transmission</li>
                        <li>Secure servers with regular security updates</li>
                        <li>Access controls and authentication procedures</li>
                        <li>Regular security audits and vulnerability assessments</li>
                        <li>Data backup and disaster recovery procedures</li>
                    </ul>
                </div>
            </div>

            <!-- Your Rights -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-md-5">
                    <h2 class="fw-bold mb-4">6. Your Privacy Rights</h2>
                    <p>Depending on your location, you may have the following rights regarding your personal information:</p>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border">
                                <div class="card-body">
                                    <h5 class="fw-bold">Access & Portability</h5>
                                    <p class="mb-0">Request access to and receive a copy of your personal data.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border">
                                <div class="card-body">
                                    <h5 class="fw-bold">Correction</h5>
                                    <p class="mb-0">Request correction of inaccurate or incomplete personal data.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border">
                                <div class="card-body">
                                    <h5 class="fw-bold">Deletion</h5>
                                    <p class="mb-0">Request deletion of your personal data under certain circumstances.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border">
                                <div class="card-body">
                                    <h5 class="fw-bold">Objection</h5>
                                    <p class="mb-0">Object to processing of your personal data for specific purposes.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <p class="mt-3">
                        To exercise these rights, please contact us at 
                        <a href="mailto:ayglobe@gmail.com">click here</a>.
                    </p>
                </div>
            </div>

            <!-- Cookies -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-md-5">
                    <h2 class="fw-bold mb-4">7. Cookies and Tracking Technologies</h2>
                    <p>We use cookies and similar tracking technologies to track activity on our service and hold certain information. Cookies are files with small amount of data which may include an anonymous unique identifier.</p>
                    
                    <p>Types of cookies we use:</p>
                    <ul>
                        <li><strong>Essential Cookies:</strong> Necessary for the website to function properly</li>
                        <li><strong>Performance Cookies:</strong> Help us understand how visitors interact with our website</li>
                        <li><strong>Functionality Cookies:</strong> Allow the website to remember choices you make</li>
                        <li><strong>Targeting Cookies:</strong> Used to deliver relevant advertisements</li>
                    </ul>
                    
                    <p>You can instruct your browser to refuse all cookies or to indicate when a cookie is being sent. However, if you do not accept cookies, you may not be able to use some portions of our service.</p>
                </div>
            </div>

            <!-- Children's Privacy -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-md-5">
                    <h2 class="fw-bold mb-4">8. Children's Privacy</h2>
                    <p>Our services are not directed to individuals under 16. We do not knowingly collect personal information from children under 16. If you become aware that a child has provided us with personal information, please contact us. If we become aware that we have collected personal information from a child under 16, we will take steps to delete such information.</p>
                </div>
            </div>

            <!-- Changes to Policy -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-md-5">
                    <h2 class="fw-bold mb-4">9. Changes to This Privacy Policy</h2>
                    <p>We may update our Privacy Policy from time to time. We will notify you of any changes by posting the new Privacy Policy on this page and updating the "Last Updated" date.</p>
                    <p>You are advised to review this Privacy Policy periodically for any changes. Changes to this Privacy Policy are effective when they are posted on this page.</p>
                </div>
            </div>

            <!-- Contact -->
            <div class="card border-primary">
                <div class="card-body p-4 p-md-5 text-center">
                    <h2 class="fw-bold mb-4">10. Contact Us</h2>
                    <p>If you have any questions or concerns about this Privacy Policy, please contact us:</p>
                    
                    <div class="row mt-4">
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border">
                                <div class="card-body">
                                    <h5 class="fw-bold">Email</h5>
                                    <p class="mb-0">
                                        <a href="mailto:ayglobe@gmail.com">click here</a>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border">
                                <div class="card-body">
                                    <h5 class="fw-bold">Postal Address</h5>
                                    <p class="mb-0">
                                        Data Protection Officer<br>
                                        WEZO CAMPUS HUB<br>
                                        AYGLOBE INC Headquarters<br>
                                        Nairobi, Kenya
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <p class="mt-4">
                        For general inquiries, please visit our 
                        <a href="contact.php">Contact Page</a>.
                    </p>
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