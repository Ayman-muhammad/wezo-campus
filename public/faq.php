<?php
/**
 * WEZO CAMPUS HUB - Frequently Asked Questions
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

$pageTitle = "FAQ - WEZO CAMPUS HUB";

// FAQ Data
$faqCategories = [
    'general' => [
        'title' => 'General Questions',
        'icon' => 'fas fa-question-circle',
        'color' => 'primary'
    ],
    'account' => [
        'title' => 'Account & Registration',
        'icon' => 'fas fa-user-circle',
        'color' => 'success'
    ],
    'marketplace' => [
        'title' => 'Marketplace & Selling',
        'icon' => 'fas fa-store',
        'color' => 'warning'
    ],
    'study' => [
        'title' => 'Study Resources & Notes',
        'icon' => 'fas fa-book',
        'color' => 'info'
    ],
    'hostels' => [
        'title' => 'Hostels & Accommodation',
        'icon' => 'fas fa-bed',
        'color' => 'danger'
    ],
    'safety' => [
        'title' => 'Safety & Security',
        'icon' => 'fas fa-shield-alt',
        'color' => 'dark'
    ]
];

$faqItems = [
    'general' => [
        [
            'question' => 'What is WEZO CAMPUS HUB?',
            'answer' => 'WEZO CAMPUS HUB is a comprehensive student ecosystem platform that combines marketplace functionality, study resources, accommodation services, and community features. It\'s designed to help students succeed academically while providing opportunities for entrepreneurship and community building.'
        ],
        [
            'question' => 'Is WEZO CAMPUS HUB free to use?',
            'answer' => 'Yes! Basic features including browsing, creating an account, and accessing most study resources are completely free. Some premium features or certain paid study materials may have fees, but these are clearly marked.'
        ],
        [
            'question' => 'Which institutions are supported?',
            'answer' => 'WEZO CAMPUS HUB is open to students from all higher education institutions. We currently have active users from universities, colleges, and technical training institutes across multiple countries.'
        ]
    ],
    'account' => [
        [
            'question' => 'How do I create an account?',
            'answer' => 'Click the "Register" button in the top navigation or go to the registration page. You\'ll need to provide your email address, create a username, and set a password. Student verification may be required for certain features.'
        ],
        [
            'question' => 'How do I verify my student status?',
            'answer' => 'After registration, you can verify your student status by uploading a valid student ID or providing your institutional email address. This gives you access to verified student features.'
        ],
        [
            'question' => 'I forgot my password. What should I do?',
            'answer' => 'Click "Forgot Password" on the login page. Enter your registered email address, and we\'ll send you instructions to reset your password.'
        ]
    ],
    'marketplace' => [
        [
            'question' => 'How do I sell items on the marketplace?',
            'answer' => 'Click "Sell Item" from your dashboard or the marketplace section. You\'ll need to provide item details, upload photos, set a price, and choose a category. All items are reviewed before being listed.'
        ],
        [
            'question' => 'What items are not allowed?',
            'answer' => 'Prohibited items include: illegal substances, weapons, counterfeit goods, stolen items, explicit content, and any items that violate our community guidelines or local laws.'
        ],
        [
            'question' => 'How do I ensure safe transactions?',
            'answer' => 'Always meet in safe, public places during daylight hours. Use our messaging system to communicate. Never share personal financial information. Report any suspicious activity immediately.'
        ]
    ],
    'study' => [
        [
            'question' => 'How do I upload study notes?',
            'answer' => 'Go to "Upload Notes" from your dashboard. Select the category, add a title and description, upload your file, and set a price (if selling). Free notes are encouraged to help fellow students!'
        ],
        [
            'question' => 'Can I earn money from my notes?',
            'answer' => 'Yes! You can set a price for your study notes. When other students purchase your notes, you earn 80% of the sale price (20% goes to platform maintenance).'
        ],
        [
            'question' => 'Are the study materials verified?',
            'answer' => 'All study materials are reviewed by our moderation team for quality and appropriateness. Verified tutors and top-performing students can earn "Verified Contributor" badges.'
        ]
    ],
    'hostels' => [
        [
            'question' => 'How do I find hostels near my campus?',
            'answer' => 'Use the hostel search feature to filter by location, price range, amenities, and distance from campus. You can also view ratings and reviews from other students.'
        ],
        [
            'question' => 'Can I list my hostel on the platform?',
            'answer' => 'Yes! Hostel owners can register as partners and list their properties. You\'ll need to provide photos, amenities, pricing, and contact information.'
        ],
        [
            'question' => 'How are hostel ratings calculated?',
            'answer' => 'Ratings are based on student reviews in categories: cleanliness, safety, management, amenities, and overall experience. Only verified residents can leave reviews.'
        ]
    ],
    'safety' => [
        [
            'question' => 'How do you protect my personal information?',
            'answer' => 'We use industry-standard encryption, secure servers, and regular security audits. We never share your personal information with third parties without your consent.'
        ],
        [
            'question' => 'What should I do if I encounter a scam?',
            'answer' => 'Immediately report the user/item through our reporting system. Provide as much detail as possible. Our moderation team will investigate and take appropriate action.'
        ],
        [
            'question' => 'How can I verify a seller\'s credibility?',
            'answer' => 'Check their profile for verification badges, review history, and response rate. Verified students and users with positive reviews are generally more trustworthy.'
        ]
    ]
];

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
                <h1 class="display-4 fw-bold text-white mb-3">Frequently Asked Questions</h1>
                <p class="lead text-white mb-0">Find answers to common questions about WEZO CAMPUS HUB</p>
            </div>
        </div>
    </div>
</section>

<!-- Main Content -->
<div class="container py-5">
    <!-- Search FAQ -->
    <div class="row mb-5">
        <div class="col-lg-8 mx-auto">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-light border-0">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" class="form-control border-0" id="faqSearch" placeholder="Search for questions or keywords...">
                        <button class="btn btn-primary" type="button">Search</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FAQ Categories Navigation -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="text-center mb-4">
                <h2 class="fw-bold mb-3">Browse by Category</h2>
                <div class="divider mx-auto" style="width: 80px; height: 4px; background: #1A56DB;"></div>
            </div>
            
            <div class="row g-3">
                <?php foreach ($faqCategories as $key => $category): ?>
                <div class="col-md-4 col-6">
                    <a href="#category-<?php echo $key; ?>" class="text-decoration-none">
                        <div class="card border-0 shadow-sm h-100 category-card" data-category="<?php echo $key; ?>">
                            <div class="card-body text-center p-4">
                                <div class="mb-3">
                                    <div class="icon-box mx-auto" style="width: 70px; height: 70px; background: rgba(var(--bs-<?php echo $category['color']; ?>-rgb), 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                        <i class="<?php echo $category['icon']; ?> fa-2x text-<?php echo $category['color']; ?>"></i>
                                    </div>
                                </div>
                                <h5 class="fw-bold mb-0"><?php echo $category['title']; ?></h5>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- FAQ Content -->
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <?php foreach ($faqCategories as $catKey => $category): ?>
            <div class="faq-category mb-5" id="category-<?php echo $catKey; ?>">
                <div class="d-flex align-items-center mb-4">
                    <div class="icon-box me-3" style="width: 50px; height: 50px; background: rgba(var(--bs-<?php echo $category['color']; ?>-rgb), 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="<?php echo $category['icon']; ?> fa-lg text-<?php echo $category['color']; ?>"></i>
                    </div>
                    <h3 class="fw-bold mb-0"><?php echo $category['title']; ?></h3>
                </div>
                
                <div class="accordion" id="accordion-<?php echo $catKey; ?>">
                    <?php foreach ($faqItems[$catKey] as $index => $faq): ?>
                    <div class="accordion-item border-0 mb-3 shadow-sm">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                    data-bs-target="#faq-<?php echo $catKey . '-' . $index; ?>" 
                                    aria-expanded="false">
                                <?php echo $faq['question']; ?>
                            </button>
                        </h2>
                        <div id="faq-<?php echo $catKey . '-' . $index; ?>" class="accordion-collapse collapse" 
                             data-bs-parent="#accordion-<?php echo $catKey; ?>">
                            <div class="accordion-body">
                                <?php echo $faq['answer']; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Still Have Questions -->
    <div class="row mt-5">
        <div class="col-lg-8 mx-auto">
            <div class="card border-primary">
                <div class="card-body p-5 text-center">
                    <div class="mb-4">
                        <i class="fas fa-headset fa-4x text-primary mb-3"></i>
                        <h3 class="fw-bold mb-3">Still Have Questions?</h3>
                        <p class="mb-4">Can't find the answer you're looking for? Our support team is here to help.</p>
                    </div>
                    <div class="d-flex flex-column flex-md-row justify-content-center gap-3">
                        <a href="contact.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-envelope me-2"></i> Contact Support
                        </a>
                        <a href="mailto:ayglobe@gmail.com" class="btn btn-outline-primary btn-lg">
                            <i class="fas fa-paper-plane me-2"></i> Email Us
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
?>

<style>
.category-card {
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.category-card:hover {
    transform: translateY(-5px);
    border-color: var(--bs-primary);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
}

.accordion-button {
    font-weight: 600;
    background-color: white;
}

.accordion-button:not(.collapsed) {
    background-color: rgba(26, 86, 219, 0.05);
    color: #1A56DB;
}

.accordion-body {
    background-color: #f8f9fa;
}
</style>

<script>
// FAQ Search functionality
document.getElementById('faqSearch').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const faqItems = document.querySelectorAll('.accordion-item');
    
    faqItems.forEach(item => {
        const question = item.querySelector('.accordion-button').textContent.toLowerCase();
        const answer = item.querySelector('.accordion-body').textContent.toLowerCase();
        
        if (question.includes(searchTerm) || answer.includes(searchTerm)) {
            item.style.display = 'block';
            
            // Show parent category
            const category = item.closest('.faq-category');
            if (category) {
                category.style.display = 'block';
            }
        } else {
            item.style.display = 'none';
        }
    });
    
    // Hide empty categories
    document.querySelectorAll('.faq-category').forEach(category => {
        const visibleItems = category.querySelectorAll('.accordion-item[style="display: block"]');
        if (visibleItems.length === 0) {
            category.style.display = 'none';
        }
    });
});

// Category card highlighting
document.querySelectorAll('.category-card').forEach(card => {
    card.addEventListener('click', function(e) {
        e.preventDefault();
        const category = this.dataset.category;
        const targetElement = document.getElementById(`category-${category}`);
        
        if (targetElement) {
            window.scrollTo({
                top: targetElement.offsetTop - 100,
                behavior: 'smooth'
            });
        }
    });
});
</script>