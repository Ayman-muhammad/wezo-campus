<?php
/**
 * WEZO CAMPUS HUB - About Us Page
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
    // Fallback if database not available
    $user = null;
    $isLoggedIn = false;
}

$pageTitle = "About Us - WEZO CAMPUS HUB";

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
                <h1 class="display-4 fw-bold text-white mb-3">About WEZO CAMPUS HUB</h1>
                <p class="lead text-white mb-0">The Ultimate Student Ecosystem - Empowering Students for Success</p>
            </div>
        </div>
    </div>
</section>

<!-- Main Content -->
<div class="container py-5">
    <!-- Our Story -->
    <div class="row mb-5">
        <div class="col-lg-8 mx-auto">
            <div class="text-center mb-5">
                <h2 class="fw-bold mb-3">Our Story</h2>
                <div class="divider mx-auto" style="width: 80px; height: 4px; background: #10B981;"></div>
            </div>
            
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <p class="lead text-center">
                        Founded in 2024, WEZO CAMPUS HUB was born from a simple observation: students face numerous challenges 
                        that existing platforms don't adequately address. We saw the need for a comprehensive ecosystem 
                        that connects all aspects of student life.
                    </p>
                </div>
            </div>
            
            <p class="mb-4">
                What started as a project to help fellow students share study materials has evolved into a full-fledged 
                student ecosystem. Today, WEZO CAMPUS HUB serves thousands of students across multiple institutions, 
                providing tools, resources, and opportunities for academic and personal growth.
            </p>
            
            <p class="mb-4">
                Our platform combines marketplace functionality, study resources, accommodation services, and community 
                features into one seamless experience. We believe that by connecting students with the right resources 
                and opportunities, we can help them achieve their full potential.
            </p>
        </div>
    </div>

    <!-- Mission & Vision -->
    <div class="row mb-5">
        <div class="col-md-6 mb-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <div class="icon-box mb-3" style="width: 80px; height: 80px; background: rgba(26, 86, 219, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                            <i class="fas fa-bullseye fa-2x text-primary"></i>
                        </div>
                        <h3 class="fw-bold mb-3">Our Mission</h3>
                    </div>
                    <p class="text-center">
                        To create a comprehensive digital ecosystem that empowers students by providing easy access to 
                        academic resources, marketplace opportunities, accommodation solutions, and a supportive community 
                        that fosters growth and success.
                    </p>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 mb-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <div class="icon-box mb-3" style="width: 80px; height: 80px; background: rgba(16, 185, 129, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                            <i class="fas fa-eye fa-2x text-success"></i>
                        </div>
                        <h3 class="fw-bold mb-3">Our Vision</h3>
                    </div>
                    <p class="text-center">
                        To become the leading student platform in Africa, transforming how students learn, connect, 
                        and succeed by leveraging technology to address their unique needs and challenges.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Values -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="text-center mb-5">
                <h2 class="fw-bold mb-3">Our Core Values</h2>
                <div class="divider mx-auto" style="width: 80px; height: 4px; background: #1A56DB;"></div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4 text-center">
                            <div class="mb-3">
                                <i class="fas fa-users fa-3x text-primary"></i>
                            </div>
                            <h4 class="fw-bold mb-3">Community First</h4>
                            <p>We prioritize building strong, supportive communities where students can help each other succeed.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4 text-center">
                            <div class="mb-3">
                                <i class="fas fa-lightbulb fa-3x text-warning"></i>
                            </div>
                            <h4 class="fw-bold mb-3">Innovation</h4>
                            <p>We continuously innovate to provide cutting-edge solutions for student challenges.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4 text-center">
                            <div class="mb-3">
                                <i class="fas fa-shield-alt fa-3x text-info"></i>
                            </div>
                            <h4 class="fw-bold mb-3">Trust & Safety</h4>
                            <p>We maintain the highest standards of security and trust in all our platform interactions.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Founder Section -->
    <div class="row mb-5">
        <div class="col-lg-8 mx-auto">
            <div class="card border-primary">
                <div class="card-body p-5">
                    <div class="row align-items-center">
                        <div class="col-md-4 text-center mb-4 mb-md-0">
                            <div class="founder-image mb-3" style="width: 150px; height: 150px; border-radius: 50%; background: linear-gradient(135deg, #1A56DB, #10B981); display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                <i class="fas fa-user-tie fa-4x text-white"></i>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <h3 class="fw-bold mb-3">From the Founder</h3>
                            <blockquote class="blockquote mb-4">
                                <p class="mb-0">
                                    "As a student myself, I experienced firsthand the challenges of finding quality study materials, 
                                    affordable accommodation, and legitimate ways to earn extra income. WEZO CAMPUS HUB was created 
                                    to solve these problems and empower students to focus on what matters most - their education and growth."
                                </p>
                            </blockquote>
                            <div class="d-flex align-items-center">
                                <div>
                                    <h5 class="fw-bold mb-0">Ayman Muhammad</h5>
                                    <p class="text-muted mb-0">Founder & CEO, AYGLOBE INC</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="text-center mb-5">
                <h2 class="fw-bold mb-3">Our Impact</h2>
                <div class="divider mx-auto" style="width: 80px; height: 4px; background: #10B981;"></div>
            </div>
            
            <div class="row text-center">
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-box p-4">
                        <h2 class="display-4 fw-bold text-primary mb-2">2,500+</h2>
                        <p class="mb-0">Active Students</p>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-box p-4">
                        <h2 class="display-4 fw-bold text-success mb-2">5,000+</h2>
                        <p class="mb-0">Study Resources</p>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-box p-4">
                        <h2 class="display-4 fw-bold text-warning mb-2">1,200+</h2>
                        <p class="mb-0">Marketplace Items</p>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-box p-4">
                        <h2 class="display-4 fw-bold text-info mb-2">150+</h2>
                        <p class="mb-0">Partner Hostels</p>
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