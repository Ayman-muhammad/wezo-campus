<?php
/**
 * WEZO CAMPUS HUB - Help & Support Center
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

$pageTitle = "Help & Support - WEZO CAMPUS HUB";

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
                <h1 class="display-4 fw-bold text-white mb-3">Help & Support Center</h1>
                <p class="lead text-white mb-0">Find answers, get help, and learn how to use WEZO CAMPUS HUB</p>
            </div>
        </div>
    </div>
</section>

<!-- Main Content -->
<div class="container py-5">
    <!-- Quick Help Cards -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="text-center mb-5">
                <h2 class="fw-bold mb-3">How Can We Help You?</h2>
                <div class="divider mx-auto" style="width: 80px; height: 4px; background: #1A56DB;"></div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm text-center help-card">
                        <div class="card-body p-4">
                            <div class="icon-box mb-4" style="width: 80px; height: 80px; background: rgba(26, 86, 219, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                <i class="fas fa-question-circle fa-3x text-primary"></i>
                            </div>
                            <h4 class="fw-bold mb-3">FAQs</h4>
                            <p class="mb-0">Browse frequently asked questions and quick answers</p>
                            <div class="mt-4">
                                <a href="faq.php" class="btn btn-outline-primary">View FAQs</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm text-center help-card">
                        <div class="card-body p-4">
                            <div class="icon-box mb-4" style="width: 80px; height: 80px; background: rgba(16, 185, 129, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                <i class="fas fa-book fa-3x text-success"></i>
                            </div>
                            <h4 class="fw-bold mb-3">Guides & Tutorials</h4>
                            <p class="mb-0">Step-by-step guides for using platform features</p>
                            <div class="mt-4">
                                <a href="#guides" class="btn btn-outline-success">View Guides</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm text-center help-card">
                        <div class="card-body p-4">
                            <div class="icon-box mb-4" style="width: 80px; height: 80px; background: rgba(245, 158, 11, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                <i class="fas fa-headset fa-3x text-warning"></i>
                            </div>
                            <h4 class="fw-bold mb-3">Contact Support</h4>
                            <p class="mb-0">Get personalized help from our support team</p>
                            <div class="mt-4">
                                <a href="contact.php" class="btn btn-outline-warning">Contact Us</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Getting Started -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <h3 class="fw-bold mb-4">
                        <i class="fas fa-rocket text-primary me-2"></i> Getting Started
                    </h3>
                    
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <span class="step-number">1</span>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h5 class="fw-bold mb-2">Create Your Account</h5>
                                    <p class="text-muted mb-0">Sign up with your student email and verify your account to access all features.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <span class="step-number">2</span>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h5 class="fw-bold mb-2">Complete Your Profile</h5>
                                    <p class="text-muted mb-0">Add your profile picture, bio, and academic information to build trust.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <span class="step-number">3</span>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h5 class="fw-bold mb-2">Explore Features</h5>
                                    <p class="text-muted mb-0">Browse study notes, marketplace items, hostels, and connect with students.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <span class="step-number">4</span>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h5 class="fw-bold mb-2">Start Contributing</h5>
                                    <p class="text-muted mb-0">Upload notes, sell items, or help fellow students in the community.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Guides Section -->
    <div class="row mb-5" id="guides">
        <div class="col-12">
            <div class="text-center mb-5">
                <h2 class="fw-bold mb-3">Guides & Tutorials</h2>
                <div class="divider mx-auto" style="width: 80px; height: 4px; background: #10B981;"></div>
            </div>
            
            <div class="accordion" id="guidesAccordion">
                <!-- Study Notes Guide -->
                <div class="accordion-item border-0 shadow-sm mb-3">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#guide1">
                            <i class="fas fa-book text-primary me-3"></i>
                            <span class="fw-bold">How to Upload Study Notes</span>
                        </button>
                    </h2>
                    <div id="guide1" class="accordion-collapse collapse" data-bs-parent="#guidesAccordion">
                        <div class="accordion-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5 class="fw-bold mb-3">Step-by-Step Guide:</h5>
                                    <ol class="mb-4">
                                        <li>Go to your Dashboard and click "Upload Notes"</li>
                                        <li>Select the appropriate category for your notes</li>
                                        <li>Fill in the title and description</li>
                                        <li>Upload your file (PDF, DOC, PPT supported)</li>
                                        <li>Set a price (or choose free)</li>
                                        <li>Add relevant tags for better searchability</li>
                                        <li>Click "Publish" to submit for review</li>
                                    </ol>
                                    
                                    <div class="alert alert-info">
                                        <i class="fas fa-lightbulb me-2"></i>
                                        <strong>Tip:</strong> High-quality, well-organized notes get more downloads and better reviews.
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h5 class="fw-bold mb-3">Best Practices:</h5>
                                    <ul>
                                        <li>Use clear, descriptive titles</li>
                                        <li>Include course code and instructor name</li>
                                        <li>Format your notes properly</li>
                                        <li>Add summaries and key points</li>
                                        <li>Use images and diagrams when helpful</li>
                                        <li>Proofread before uploading</li>
                                        <li>Price competitively</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Marketplace Guide -->
                <div class="accordion-item border-0 shadow-sm mb-3">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#guide2">
                            <i class="fas fa-store text-success me-3"></i>
                            <span class="fw-bold">How to Sell Items Safely</span>
                        </button>
                    </h2>
                    <div id="guide2" class="accordion-collapse collapse" data-bs-parent="#guidesAccordion">
                        <div class="accordion-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5 class="fw-bold mb-3">Creating a Listing:</h5>
                                    <ol class="mb-4">
                                        <li>Click "Sell Item" from your dashboard</li>
                                        <li>Select the appropriate category</li>
                                        <li>Take clear, well-lit photos</li>
                                        <li>Write an honest, detailed description</li>
                                        <li>Set a fair price</li>
                                        <li>Choose "Negotiable" if open to offers</li>
                                        <li>Set your location for meetup</li>
                                        <li>Submit for approval</li>
                                    </ol>
                                </div>
                                <div class="col-md-6">
                                    <h5 class="fw-bold mb-3">Safety Guidelines:</h5>
                                    <div class="alert alert-warning">
                                        <h6 class="alert-heading"><i class="fas fa-shield-alt me-2"></i> Safety First!</h6>
                                        <ul class="mb-0">
                                            <li>Always meet in public, well-lit areas</li>
                                            <li>Bring a friend if possible</li>
                                            <li>Meet during daylight hours</li>
                                            <li>Use secure payment methods</li>
                                            <li>Trust your instincts</li>
                                            <li>Report suspicious behavior</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Hostel Guide -->
                <div class="accordion-item border-0 shadow-sm mb-3">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#guide3">
                            <i class="fas fa-bed text-info me-3"></i>
                            <span class="fw-bold">Finding Accommodation</span>
                        </button>
                    </h2>
                    <div id="guide3" class="accordion-collapse collapse" data-bs-parent="#guidesAccordion">
                        <div class="accordion-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5 class="fw-bold mb-3">Searching for Hostels:</h5>
                                    <ul class="mb-4">
                                        <li>Use filters to narrow down options</li>
                                        <li>Check distance from campus</li>
                                        <li>Read reviews from other students</li>
                                        <li>Compare prices and amenities</li>
                                        <li>Contact hostel management</li>
                                        <li>Schedule a viewing appointment</li>
                                        <li>Ask about security measures</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h5 class="fw-bold mb-3">What to Look For:</h5>
                                    <ul>
                                        <li>24/7 security presence</li>
                                        <li>Reliable water and electricity</li>
                                        <li>Internet connectivity</li>
                                        <li>Study areas</li>
                                        <li>Laundry facilities</li>
                                        <li>Cleanliness standards</li>
                                        <li>House rules and policies</li>
                                        <li>Deposit requirements</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Common Issues -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <h3 class="fw-bold mb-4">
                        <i class="fas fa-tools text-warning me-2"></i> Troubleshooting Common Issues
                    </h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="issue-card p-4 border rounded">
                                <h5 class="fw-bold mb-3">
                                    <i class="fas fa-sign-in-alt text-danger me-2"></i> Login Problems
                                </h5>
                                <p class="mb-0">If you can't login:</p>
                                <ul class="mb-3">
                                    <li>Check your email/username spelling</li>
                                    <li>Use "Forgot Password" if needed</li>
                                    <li>Clear browser cache and cookies</li>
                                    <li>Try a different browser</li>
                                    <li>Ensure email is verified</li>
                                </ul>
                                <a href="login.php" class="btn btn-sm btn-outline-danger">Go to Login</a>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <div class="issue-card p-4 border rounded">
                                <h5 class="fw-bold mb-3">
                                    <i class="fas fa-upload text-primary me-2"></i> Upload Issues
                                </h5>
                                <p class="mb-0">If uploads fail:</p>
                                <ul class="mb-3">
                                    <li>Check file size (max 50MB)</li>
                                    <li>Use supported file formats</li>
                                    <li>Check internet connection</li>
                                    <li>Try smaller files first</li>
                                    <li>Contact support if persistent</li>
                                </ul>
                                <a href="contact.php" class="btn btn-sm btn-outline-primary">Contact Support</a>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <div class="issue-card p-4 border rounded">
                                <h5 class="fw-bold mb-3">
                                    <i class="fas fa-credit-card text-success me-2"></i> Payment Issues
                                </h5>
                                <p class="mb-0">If payments fail:</p>
                                <ul class="mb-3">
                                    <li>Check payment method details</li>
                                    <li>Ensure sufficient funds</li>
                                    <li>Try a different payment method</li>
                                    <li>Check with your bank</li>
                                    <li>Contact our support team</li>
                                </ul>
                                <a href="contact.php" class="btn btn-sm btn-outline-success">Get Help</a>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <div class="issue-card p-4 border rounded">
                                <h5 class="fw-bold mb-3">
                                    <i class="fas fa-user-shield text-info me-2"></i> Account Security
                                </h5>
                                <p class="mb-0">Security concerns:</p>
                                <ul class="mb-3">
                                    <li>Use strong, unique passwords</li>
                                    <li>Enable 2FA if available</li>
                                    <li>Log out from shared devices</li>
                                    <li>Report suspicious activity</li>
                                    <li>Update contact information</li>
                                </ul>
                                <a href="profile.php" class="btn btn-sm btn-outline-info">Security Settings</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contact Options -->
    <div class="row">
        <div class="col-12">
            <div class="card border-primary">
                <div class="card-body p-5 text-center">
                    <div class="mb-4">
                        <i class="fas fa-headset fa-4x text-primary mb-3"></i>
                        <h3 class="fw-bold mb-3">Still Need Help?</h3>
                        <p class="lead mb-4">Our support team is ready to assist you with any issues or questions.</p>
                    </div>
                    
                    <div class="row g-4">
                        <div class="col-md-4">
                            <div class="p-4">
                                <div class="mb-3">
                                    <i class="fas fa-envelope fa-2x text-primary"></i>
                                </div>
                                <h5 class="fw-bold mb-2">Email Support</h5>
                                <p class="text-muted mb-3">Get help via email within 24 hours</p>
                                <a href="mailto:ayglobe@gmail.com" class="btn btn-outline-primary">
                                    click me, i'll help
                                </a>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="p-4">
                                <div class="mb-3">
                                    <i class="fas fa-comments fa-2x text-success"></i>
                                </div>
                                <h5 class="fw-bold mb-2">Live Chat</h5>
                                <p class="text-muted mb-3">Chat with our support team in real-time</p>
                                <button class="btn btn-outline-success" id="liveChatBtn">
                                    <i class="fas fa-comment-dots me-2"></i> Start Chat
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="p-4">
                                <div class="mb-3">
                                    <i class="fas fa-phone fa-2x text-warning"></i>
                                </div>
                                <h5 class="fw-bold mb-2">Phone Support</h5>
                                <p class="text-muted mb-3">Call us during business hours</p>
                                <a href="tel:+254722251333" class="btn btn-outline-warning">
                                    call here
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-5">
                        <p class="text-muted mb-2">
                            <i class="fas fa-clock me-2"></i>
                            Support Hours: Monday - Friday, 9:00 AM - 6:00 PM EAT
                        </p>
                        <p class="text-muted mb-0">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            For urgent issues outside business hours, please use email support.
                        </p>
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
.step-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #1A56DB, #10B981);
    color: white;
    border-radius: 50%;
    font-weight: bold;
    font-size: 18px;
}

.help-card {
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.help-card:hover {
    transform: translateY(-5px);
    border-color: var(--bs-primary);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
}

.issue-card {
    transition: all 0.3s ease;
    height: 100%;
}

.issue-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.accordion-button {
    font-weight: 600;
    background-color: white;
    padding: 20px;
}

.accordion-button:not(.collapsed) {
    background-color: rgba(26, 86, 219, 0.05);
    color: #1A56DB;
    box-shadow: none;
}

.accordion-body {
    background-color: #f8f9fa;
}

.divider {
    background: linear-gradient(90deg, #1A56DB, #10B981);
}
</style>

<script>
// Live Chat Simulation
document.getElementById('liveChatBtn').addEventListener('click', function() {
    if (!<?php echo $isLoggedIn ? 'true' : 'false'; ?>) {
        alert('Please login to use live chat support.');
        window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.pathname);
        return;
    }
    
    // Simulate live chat
    const chatWindow = window.open('', 'livechat', 'width=400,height=600');
    chatWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Live Chat Support - WEZO CAMPUS HUB</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
                .chat-container { max-width: 400px; margin: 0 auto; }
                .chat-header { background: linear-gradient(135deg, #1A56DB, #10B981); color: white; padding: 15px; border-radius: 10px 10px 0 0; text-align: center; }
                .chat-messages { background: white; padding: 20px; height: 400px; overflow-y: auto; border-left: 1px solid #ddd; border-right: 1px solid #ddd; }
                .message { margin-bottom: 15px; padding: 10px; border-radius: 10px; max-width: 80%; }
                .support { background: #e3f2fd; align-self: flex-start; }
                .user { background: #e8f5e9; align-self: flex-end; margin-left: auto; }
                .chat-input { padding: 15px; background: white; border-top: 1px solid #ddd; }
                .input-group { display: flex; gap: 10px; }
                input { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
                button { padding: 10px 20px; background: #1A56DB; color: white; border: none; border-radius: 5px; cursor: pointer; }
            </style>
        </head>
        <body>
            <div class="chat-container">
                <div class="chat-header">
                    <h3 style="margin: 0;">WEZO CAMPUS HUB Support</h3>
                    <small>Live Chat</small>
                </div>
                <div class="chat-messages" id="chatMessages">
                    <div class="message support">
                        <strong>Support Agent:</strong><br>
                        Hello! Welcome to WEZO CAMPUS HUB support. How can I help you today?
                    </div>
                </div>
                <div class="chat-input">
                    <div class="input-group">
                        <input type="text" id="messageInput" placeholder="Type your message...">
                        <button onclick="sendMessage()">Send</button>
                    </div>
                    <small class="text-muted" style="display: block; margin-top: 10px;">
                        This is a simulation. In production, this would connect to real support agents.
                    </small>
                </div>
            </div>
            <script>
                function sendMessage() {
                    const input = document.getElementById('messageInput');
                    const message = input.value.trim();
                    if (message) {
                        const messagesDiv = document.getElementById('chatMessages');
                        
                        // User message
                        const userMsg = document.createElement('div');
                        userMsg.className = 'message user';
                        userMsg.innerHTML = '<strong>You:</strong><br>' + message;
                        messagesDiv.appendChild(userMsg);
                        
                        // Clear input
                        input.value = '';
                        
                        // Auto-reply after delay
                        setTimeout(() => {
                            const replies = [
                                "Thank you for your message. Our support team will respond shortly.",
                                "I understand your concern. Let me check that for you.",
                                "Could you provide more details about this issue?",
                                "I'll forward this to our technical team for further assistance.",
                                "Is there anything else I can help you with today?"
                            ];
                            const reply = replies[Math.floor(Math.random() * replies.length)];
                            
                            const supportMsg = document.createElement('div');
                            supportMsg.className = 'message support';
                            supportMsg.innerHTML = '<strong>Support Agent:</strong><br>' + reply;
                            messagesDiv.appendChild(supportMsg);
                            
                            // Scroll to bottom
                            messagesDiv.scrollTop = messagesDiv.scrollHeight;
                        }, 1000);
                        
                        // Scroll to bottom
                        messagesDiv.scrollTop = messagesDiv.scrollHeight;
                    }
                }
                
                // Enter key support
                document.getElementById('messageInput').addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        sendMessage();
                    }
                });
            <\/script>
        </body>
        </html>
    `);
});
</script>