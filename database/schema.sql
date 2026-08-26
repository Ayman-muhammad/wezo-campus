-- ============================================
-- WEZO CAMPUS HUB Database Schema
-- Phases 1, 2 & 3 - Complete Version
-- Powered by AYGLOBE INC
-- ============================================

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS wezo_campus_hub 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE wezo_campus_hub;

-- ============================================
-- CORE TABLES (Phase 1)
-- ============================================

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    phone VARCHAR(20),
    profile_pic VARCHAR(255) DEFAULT 'default.png',
    bio TEXT,
    role ENUM('student', 'verified_student', 'moderator', 'admin') DEFAULT 'student',
    is_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(100),
    status ENUM('active', 'suspended', 'banned') DEFAULT 'active',
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sessions table
CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    payload TEXT,
    last_activity INT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Activity logs
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- System settings
CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    category VARCHAR(50),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dashboard statistics table
CREATE TABLE IF NOT EXISTS dashboard_stats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    total_users INT DEFAULT 0,
    total_notes INT DEFAULT 0,
    total_marketplace_items INT DEFAULT 0,
    total_hostels INT DEFAULT 0,
    total_resources INT DEFAULT 0,
    active_listings INT DEFAULT 0,
    total_downloads INT DEFAULT 0,
    total_revenue DECIMAL(10,2) DEFAULT 0.00,
    stats_date DATE UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_stats_date (stats_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- PHASE 2 TABLES (Marketplace & Study Tools)
-- ============================================

-- Notes categories
CREATE TABLE IF NOT EXISTS note_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    parent_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES note_categories(id) ON DELETE SET NULL,
    INDEX idx_slug (slug),
    INDEX idx_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Study notes
CREATE TABLE IF NOT EXISTS notes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    category_id INT,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(200) UNIQUE NOT NULL,
    description TEXT,
    content LONGTEXT,
    file_path VARCHAR(255),
    file_size INT,
    file_type VARCHAR(50),
    download_count INT DEFAULT 0,
    view_count INT DEFAULT 0,
    like_count INT DEFAULT 0,
    is_approved BOOLEAN DEFAULT TRUE,
    is_featured BOOLEAN DEFAULT FALSE,
    tags TEXT,
    price DECIMAL(10,2) DEFAULT 0.00,
    is_free BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES note_categories(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_category (category_id),
    INDEX idx_slug (slug),
    INDEX idx_approved (is_approved),
    FULLTEXT idx_search (title, description, tags)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Note reviews/ratings
CREATE TABLE IF NOT EXISTS note_reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    note_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    review TEXT,
    is_verified_purchase BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_note_user (note_id, user_id),
    INDEX idx_note_id (note_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Marketplace categories
CREATE TABLE IF NOT EXISTS marketplace_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    icon VARCHAR(50),
    description TEXT,
    parent_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES marketplace_categories(id) ON DELETE SET NULL,
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Marketplace items
CREATE TABLE IF NOT EXISTS marketplace_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    category_id INT,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(200) UNIQUE NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    negotiable BOOLEAN DEFAULT FALSE,
    `condition` ENUM('new', 'like_new', 'good', 'fair', 'poor') DEFAULT 'good',
    images TEXT, -- JSON array of image paths
    location VARCHAR(200),
    contact_phone VARCHAR(20),
    contact_email VARCHAR(100),
    status ENUM('active', 'sold', 'reserved', 'expired', 'removed') DEFAULT 'active',
    is_approved BOOLEAN DEFAULT FALSE,
    view_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES marketplace_categories(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_category (category_id),
    INDEX idx_status (status),
    INDEX idx_approved (is_approved),
    FULLTEXT idx_search (title, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Campuses table (for Phase 3)
CREATE TABLE IF NOT EXISTS campuses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    location VARCHAR(200),
    address TEXT,
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Hostels
CREATE TABLE IF NOT EXISTS hostels (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) UNIQUE NOT NULL,
    owner_id INT,
    campus_id INT,
    description TEXT,
    address TEXT,
    location VARCHAR(200),
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    price_per_month DECIMAL(10,2),
    deposit_amount DECIMAL(10,2) DEFAULT 0.00,
    available_rooms INT DEFAULT 0,
    total_rooms INT DEFAULT 0,
    amenities TEXT, -- JSON array
    rules TEXT,
    contact_phone VARCHAR(20),
    contact_email VARCHAR(100),
    images TEXT, -- JSON array
    is_approved BOOLEAN DEFAULT FALSE,
    is_featured BOOLEAN DEFAULT FALSE,
    rating DECIMAL(3,2) DEFAULT 0.00,
    review_count INT DEFAULT 0,
    view_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE SET NULL,
    INDEX idx_approved (is_approved),
    INDEX idx_featured (is_featured),
    INDEX idx_location (location),
    INDEX idx_campus (campus_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Hostel reviews
CREATE TABLE IF NOT EXISTS hostel_reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hostel_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    title VARCHAR(200),
    review TEXT,
    cleanliness INT CHECK (cleanliness >= 1 AND cleanliness <= 5),
    safety INT CHECK (safety >= 1 AND safety <= 5),
    management INT CHECK (management >= 1 AND management <= 5),
    amenities INT CHECK (amenities >= 1 AND amenities <= 5),
    is_verified_stay BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hostel_id) REFERENCES hostels(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_hostel_user (hostel_id, user_id),
    INDEX idx_hostel_id (hostel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Resources (Past papers, etc.)
CREATE TABLE IF NOT EXISTS resources (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(200) UNIQUE NOT NULL,
    description TEXT,
    file_path VARCHAR(255),
    file_size INT,
    file_type VARCHAR(50),
    resource_type ENUM('past_paper', 'textbook', 'article', 'video', 'other') DEFAULT 'past_paper',
    subject VARCHAR(100),
    year INT,
    semester VARCHAR(50),
    institution VARCHAR(100),
    campus_id INT,
    download_count INT DEFAULT 0,
    view_count INT DEFAULT 0,
    is_approved BOOLEAN DEFAULT FALSE,
    tags TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_resource_type (resource_type),
    INDEX idx_subject (subject),
    FULLTEXT idx_search (title, description, tags)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Favorites/Bookmarks
CREATE TABLE IF NOT EXISTS favorites (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    item_type ENUM('note', 'marketplace', 'hostel', 'resource', 'event', 'forum_thread') NOT NULL,
    item_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_item (user_id, item_type, item_id),
    INDEX idx_user_id (user_id),
    INDEX idx_item (item_type, item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- PHASE 3 TABLES (Community & Communication)
-- ============================================

-- LOST & FOUND TABLES
CREATE TABLE IF NOT EXISTS lostfound_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(50),
    description TEXT,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (name),
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lostfound (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type ENUM('lost', 'found') NOT NULL,
    category_id INT,
    campus_id INT,
    item_name VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(200),
    incident_date DATE,
    image VARCHAR(500),
    contact_preference ENUM('phone', 'email', 'both') DEFAULT 'both',
    status ENUM('active', 'resolved', 'closed') DEFAULT 'active',
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES lostfound_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE SET NULL,
    INDEX idx_type_status (type, status),
    INDEX idx_campus_type (campus_id, type),
    INDEX idx_created_at (created_at),
    FULLTEXT idx_search (item_name, description, location)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lostfound_matches (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lostfound_id INT NOT NULL,
    matched_item_id INT NOT NULL,
    confidence_score INT DEFAULT 0,
    status ENUM('pending', 'accepted', 'rejected', 'resolved') DEFAULT 'pending',
    created_by INT NOT NULL,
    verified_by INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (lostfound_id) REFERENCES lostfound(id) ON DELETE CASCADE,
    FOREIGN KEY (matched_item_id) REFERENCES lostfound(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_match (lostfound_id, matched_item_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lostfound_match_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    match_id INT NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (match_id) REFERENCES lostfound_matches(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_match_id (match_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lostfound_verification_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    match_id INT NOT NULL,
    requested_by INT NOT NULL,
    request_type ENUM('identity', 'ownership', 'meeting', 'general') NOT NULL,
    notes TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    handled_by INT,
    resolution TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    handled_at TIMESTAMP NULL,
    FOREIGN KEY (match_id) REFERENCES lostfound_matches(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- EVENTS TABLES
CREATE TABLE IF NOT EXISTS event_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(50),
    color VARCHAR(20),
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    category_id INT,
    campus_id INT,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    event_type ENUM('academic', 'social', 'club', 'workshop', 'sports', 'career', 'cultural') DEFAULT 'social',
    start_date DATE NOT NULL,
    end_date DATE,
    start_time TIME,
    end_time TIME,
    location VARCHAR(200),
    venue VARCHAR(200),
    cover_image VARCHAR(500),
    max_attendees INT,
    is_free BOOLEAN DEFAULT TRUE,
    fee DECIMAL(10,2) DEFAULT 0,
    contact_email VARCHAR(100),
    contact_phone VARCHAR(20),
    website_url VARCHAR(500),
    status ENUM('draft', 'pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES event_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE SET NULL,
    INDEX idx_date_status (start_date, status),
    INDEX idx_campus (campus_id),
    FULLTEXT idx_search (title, description, location, venue)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS event_attendees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('registered', 'attended', 'cancelled') DEFAULT 'registered',
    check_in_time TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance (event_id, user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS event_comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    parent_id INT NULL,
    comment TEXT NOT NULL,
    likes INT DEFAULT 0,
    status ENUM('active', 'hidden') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES event_comments(id) ON DELETE CASCADE,
    INDEX idx_event (event_id, created_at),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- FORUM TABLES
CREATE TABLE IF NOT EXISTS forum_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    parent_id INT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    color VARCHAR(20),
    sort_order INT DEFAULT 0,
    is_private BOOLEAN DEFAULT FALSE,
    post_count INT DEFAULT 0,
    thread_count INT DEFAULT 0,
    last_post_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES forum_categories(id) ON DELETE CASCADE,
    INDEX idx_sort (sort_order),
    INDEX idx_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS forum_threads (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT NOT NULL,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    content LONGTEXT NOT NULL,
    views INT DEFAULT 0,
    reply_count INT DEFAULT 0,
    last_reply_at TIMESTAMP NULL,
    is_pinned BOOLEAN DEFAULT FALSE,
    is_locked BOOLEAN DEFAULT FALSE,
    is_featured BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'hidden', 'pending') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES forum_categories(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_category (category_id),
    INDEX idx_user (user_id),
    INDEX idx_pinned (is_pinned),
    INDEX idx_status (status),
    FULLTEXT idx_search (title, content)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS forum_replies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    thread_id INT NOT NULL,
    user_id INT NOT NULL,
    content LONGTEXT NOT NULL,
    parent_id INT NULL,
    likes INT DEFAULT 0,
    is_solution BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'hidden') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (thread_id) REFERENCES forum_threads(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES forum_replies(id) ON DELETE CASCADE,
    INDEX idx_thread (thread_id, created_at),
    INDEX idx_user (user_id),
    INDEX idx_solution (is_solution)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS forum_reactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reply_id INT NOT NULL,
    user_id INT NOT NULL,
    reaction_type ENUM('like', 'helpful', 'insightful') DEFAULT 'like',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reply_id) REFERENCES forum_replies(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_reaction (reply_id, user_id),
    INDEX idx_reply (reply_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS forum_subscriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    thread_id INT NULL,
    category_id INT NULL,
    notification_preference ENUM('instant', 'daily', 'weekly', 'none') DEFAULT 'instant',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (thread_id) REFERENCES forum_threads(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES forum_categories(id) ON DELETE CASCADE,
    UNIQUE KEY unique_subscription (user_id, thread_id, category_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- CHAT TABLES
CREATE TABLE IF NOT EXISTS chat_conversations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    conversation_uid VARCHAR(50) NOT NULL,
    type ENUM('private', 'group') DEFAULT 'private',
    title VARCHAR(200),
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_message_at TIMESTAMP NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY (conversation_uid),
    INDEX idx_last_message (last_message_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS chat_participants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    conversation_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('admin', 'member') DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_read_at TIMESTAMP NULL,
    is_muted BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_participant (conversation_id, user_id),
    INDEX idx_conversation (conversation_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS chat_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    message_type ENUM('text', 'image', 'file', 'system') DEFAULT 'text',
    message TEXT,
    attachment_url VARCHAR(500),
    attachment_name VARCHAR(200),
    attachment_size INT,
    is_read BOOLEAN DEFAULT FALSE,
    read_by TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_conversation (conversation_id, created_at),
    INDEX idx_sender (sender_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS chat_message_status (
    id INT PRIMARY KEY AUTO_INCREMENT,
    message_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('delivered', 'read') DEFAULT 'delivered',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_status (message_id, user_id),
    INDEX idx_message (message_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- NOTIFICATIONS SYSTEM (Updated)
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    data JSON,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_unread (user_id, is_read, created_at),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- REPORTS SYSTEM (Updated)
CREATE TABLE IF NOT EXISTS content_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reporter_id INT NOT NULL,
    content_type ENUM('lostfound', 'event', 'forum_thread', 'forum_reply', 'marketplace', 'note', 'user', 'hostel', 'resource') NOT NULL,
    content_id INT NOT NULL,
    reason ENUM('spam', 'inappropriate', 'harassment', 'fake', 'fraud', 'duplicate', 'other') NOT NULL,
    details TEXT,
    status ENUM('pending', 'investigating', 'resolved', 'dismissed') DEFAULT 'pending',
    handled_by INT,
    resolution TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    handled_at TIMESTAMP NULL,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status_type (status, content_type),
    INDEX idx_content (content_type, content_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- INITIAL DATA
-- ============================================

-- Insert default admin user (password: admin123)
INSERT IGNORE INTO users (username, email, password, first_name, last_name, role, is_verified) 
VALUES 
('admin', 'admin@wezocampus.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System', 'Administrator', 'admin', TRUE),
('ayman', 'ayman@ayglobe.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ayman', 'Muhammad', 'admin', TRUE);

-- Insert system settings
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, category, description) VALUES
('site_name', 'WEZO CAMPUS HUB', 'string', 'general', 'Website name'),
('site_tagline', 'The Ultimate Student Ecosystem', 'string', 'general', 'Website tagline'),
('company_name', 'AYGLOBE INC', 'string', 'general', 'Parent company name'),
('founder_name', 'Ayman Muhammad', 'string', 'general', 'Founder/CEO name'),
('site_email', 'contact@wezocampushub.com', 'string', 'general', 'Default email address'),
('items_per_page', '12', 'integer', 'general', 'Items per page in listings'),
('registration_enabled', 'true', 'boolean', 'general', 'Allow new user registration'),
('maintenance_mode', 'false', 'boolean', 'general', 'Maintenance mode status'),
('currency', 'KES', 'string', 'general', 'Default currency'),
('currency_symbol', 'KSh', 'string', 'general', 'Currency symbol');

-- Insert initial stats
INSERT IGNORE INTO dashboard_stats (
    total_users, 
    total_notes, 
    total_marketplace_items, 
    total_hostels, 
    total_resources,
    active_listings,
    stats_date
) VALUES (
    2, -- admin + ayman
    3, -- sample notes
    3, -- sample marketplace items
    3, -- sample hostels
    0, -- no resources yet
    9, -- active listings
    CURDATE()
);

-- Insert note categories
INSERT IGNORE INTO note_categories (name, slug, description, icon) VALUES
('Computer Science', 'computer-science', 'Computer Science notes and resources', 'laptop'),
('Engineering', 'engineering', 'Engineering subjects notes', 'gear'),
('Business', 'business', 'Business and management notes', 'briefcase'),
('Medicine', 'medicine', 'Medical and health sciences', 'heart-pulse'),
('Law', 'law', 'Legal studies and law notes', 'scale'),
('Arts & Humanities', 'arts-humanities', 'Arts and humanities subjects', 'palette'),
('Sciences', 'sciences', 'Physical and biological sciences', 'flask'),
('Mathematics', 'mathematics', 'Mathematics and statistics', 'calculator');

-- Insert marketplace categories
INSERT IGNORE INTO marketplace_categories (name, slug, description, icon) VALUES
('Electronics', 'electronics', 'Laptops, phones, gadgets', 'tv'),
('Books & Notes', 'books-notes', 'Textbooks and study materials', 'book'),
('Furniture', 'furniture', 'Room furniture and accessories', 'couch'),
('Clothing', 'clothing', 'Clothes and fashion items', 'tshirt'),
('Sports', 'sports', 'Sports equipment and gear', 'dumbbell'),
('Services', 'services', 'Student services', 'wrench'),
('Other', 'other', 'Other items', 'box');

-- Insert campuses
INSERT IGNORE INTO campuses (name, code, location, description) VALUES
('Main Campus', 'MAIN', 'Nairobi', 'Main university campus'),
('City Campus', 'CITY', 'Nairobi CBD', 'City campus for business studies'),
('Westlands Campus', 'WEST', 'Westlands, Nairobi', 'Engineering and technology campus'),
('Karen Campus', 'KAREN', 'Karen, Nairobi', 'Arts and design campus');

-- Insert sample hostels
INSERT IGNORE INTO hostels (name, slug, campus_id, description, address, location, price_per_month, available_rooms, amenities, contact_phone, is_approved, is_featured) VALUES
('Campus View Hostels', 'campus-view-hostels', 1, 'Modern hostel with campus view', '123 Campus Road', 'Near Main Gate', 12000.00, 5, '["WiFi", "24/7 Security", "Laundry", "Study Room", "Hot Water"]', '+254712345678', TRUE, TRUE),
('Green Valley Hostels', 'green-valley-hostels', 2, 'Eco-friendly student accommodation', '456 Valley Road', '5km from Campus', 8500.00, 8, '["WiFi", "Security", "Garden", "Parking"]', '+254723456789', TRUE, FALSE),
('University Suites', 'university-suites', 1, 'Premium student accommodation', '789 University Avenue', 'Campus Adjacent', 15000.00, 3, '["WiFi", "Gym", "Swimming Pool", "Cafeteria", "Study Rooms"]', '+254734567890', TRUE, TRUE);

-- Create sample notes
INSERT IGNORE INTO notes (user_id, category_id, title, slug, description, content, download_count, is_approved, is_featured, tags) VALUES
(1, 1, 'Introduction to Programming', 'introduction-to-programming', 'Basic programming concepts for beginners', 'This note covers basic programming concepts...', 45, TRUE, TRUE, 'programming, beginners, computer-science'),
(1, 8, 'Calculus 101 Notes', 'calculus-101-notes', 'Complete calculus notes for first year', 'Calculus fundamentals and examples...', 78, TRUE, FALSE, 'mathematics, calculus, first-year'),
(2, 3, 'Business Management Principles', 'business-management-principles', 'Core principles of business management', 'Introduction to business management...', 32, TRUE, TRUE, 'business, management, commerce');

-- Create sample marketplace items
INSERT IGNORE INTO marketplace_items (user_id, category_id, title, slug, description, price, `condition`, images, location, status, is_approved) VALUES
(2, 1, 'MacBook Pro 2020', 'macbook-pro-2020', 'Excellent condition MacBook Pro', 85000.00, 'like_new', '["laptop1.jpg", "laptop2.jpg"]', 'Campus Hostels', 'active', TRUE),
(1, 2, 'Calculus Textbook 3rd Edition', 'calculus-textbook-3rd-edition', 'Good condition calculus book', 1500.00, 'good', '["book1.jpg"]', 'Library Area', 'active', TRUE),
(2, 4, 'Winter Jacket Size M', 'winter-jacket-size-m', 'Warm winter jacket barely used', 2500.00, 'like_new', '["jacket1.jpg", "jacket2.jpg"]', 'Student Center', 'active', TRUE);

-- ============================================
-- PHASE 3 INITIAL DATA
-- ============================================

-- Insert Lost & Found categories
INSERT IGNORE INTO lostfound_categories (name, icon, description, sort_order) VALUES
('Electronics', 'fa-laptop', 'Phones, laptops, tablets, chargers', 1),
('Documents', 'fa-file-text', 'ID cards, books, notes, certificates', 2),
('Clothing', 'fa-tshirt', 'Jackets, hats, shoes, accessories', 3),
('Accessories', 'fa-key', 'Keys, bags, wallets, jewelry', 4),
('Academic', 'fa-graduation-cap', 'Calculators, textbooks, lab equipment', 5),
('Sports', 'fa-futbol', 'Sports gear, equipment, uniforms', 6),
('Other', 'fa-question-circle', 'Other miscellaneous items', 7);

-- Insert Event categories
INSERT IGNORE INTO event_categories (name, icon, color, sort_order) VALUES
('Academic', 'fa-graduation-cap', '#4CAF50', 1),
('Social', 'fa-users', '#2196F3', 2),
('Sports', 'fa-futbol', '#FF5722', 3),
('Workshop', 'fa-tools', '#9C27B0', 4),
('Career', 'fa-briefcase', '#FF9800', 5),
('Cultural', 'fa-music', '#E91E63', 6),
('Club Meeting', 'fa-handshake', '#795548', 7);

-- Insert Forum categories
INSERT IGNORE INTO forum_categories (name, description, icon, color, sort_order) VALUES
('General Discussion', 'Chat about anything campus-related', 'fa-comments', '#667eea', 1),
('Academic Help', 'Get help with courses and assignments', 'fa-graduation-cap', '#4CAF50', 2),
('Marketplace', 'Buy, sell, and trade items', 'fa-shopping-cart', '#FF9800', 3),
('Housing & Hostels', 'Find roommates and accommodation', 'fa-home', '#795548', 4),
('Events & Activities', 'Campus events and social activities', 'fa-calendar-alt', '#E91E63', 5),
('Lost & Found', 'Report lost or found items', 'fa-search', '#2196F3', 6),
('Career Advice', 'Internships and career guidance', 'fa-briefcase', '#9C27B0', 7),
('Clubs & Societies', 'Student organizations and clubs', 'fa-users', '#FF5722', 8);

-- ============================================
-- VIEWS FOR REPORTING
-- ============================================

-- User activity summary view
CREATE OR REPLACE VIEW user_activity_summary AS
SELECT 
    u.id,
    u.username,
    u.email,
    u.role,
    u.created_at,
    (SELECT COUNT(*) FROM notes WHERE user_id = u.id) as note_count,
    (SELECT COUNT(*) FROM marketplace_items WHERE user_id = u.id) as marketplace_count,
    (SELECT COUNT(*) FROM lostfound WHERE user_id = u.id) as lostfound_count,
    (SELECT COUNT(*) FROM events WHERE user_id = u.id) as event_count,
    (SELECT COUNT(*) FROM forum_threads WHERE user_id = u.id) as thread_count,
    (SELECT COUNT(*) FROM forum_replies WHERE user_id = u.id) as reply_count
FROM users u;

-- Dashboard overview view
CREATE OR REPLACE VIEW dashboard_overview AS
SELECT 
    (SELECT COUNT(*) FROM users) as total_users,
    (SELECT COUNT(*) FROM notes WHERE is_approved = TRUE) as total_notes,
    (SELECT COUNT(*) FROM marketplace_items WHERE status = 'active' AND is_approved = TRUE) as active_marketplace,
    (SELECT COUNT(*) FROM hostels WHERE is_approved = TRUE) as total_hostels,
    (SELECT COUNT(*) FROM resources WHERE is_approved = TRUE) as total_resources,
    (SELECT COUNT(*) FROM lostfound WHERE status = 'active') as active_lostfound,
    (SELECT COUNT(*) FROM events WHERE status = 'approved') as upcoming_events,
    (SELECT COUNT(*) FROM forum_threads WHERE status = 'active') as forum_threads,
    CURDATE() as report_date;

-- ============================================
-- TRIGGERS FOR DATA INTEGRITY
-- ============================================

-- Update thread count when new reply is added
DELIMITER //
CREATE TRIGGER update_thread_reply_count 
AFTER INSERT ON forum_replies
FOR EACH ROW
BEGIN
    UPDATE forum_threads 
    SET reply_count = reply_count + 1, 
        last_reply_at = NOW() 
    WHERE id = NEW.thread_id;
END//
DELIMITER ;

-- Update forum category stats
DELIMITER //
CREATE TRIGGER update_forum_category_stats 
AFTER INSERT ON forum_threads
FOR EACH ROW
BEGIN
    UPDATE forum_categories 
    SET thread_count = thread_count + 1,
        post_count = post_count + 1,
        last_post_id = NEW.id
    WHERE id = NEW.category_id;
END//
DELIMITER ;

-- Update lost found match status when resolved
DELIMITER //
CREATE TRIGGER update_lostfound_on_match_resolved 
AFTER UPDATE ON lostfound_matches
FOR EACH ROW
BEGIN
    IF NEW.status = 'resolved' AND OLD.status != 'resolved' THEN
        UPDATE lostfound 
        SET status = 'resolved' 
        WHERE id IN (NEW.lostfound_id, NEW.matched_item_id);
    END IF;
END//
DELIMITER ;

-- ============================================
-- STORED PROCEDURES FOR COMMON OPERATIONS
-- ============================================

-- Get user notifications
DELIMITER //
CREATE PROCEDURE GetUserNotifications(IN user_id INT)
BEGIN
    SELECT * FROM notifications 
    WHERE user_id = user_id 
    ORDER BY created_at DESC 
    LIMIT 50;
END//
DELIMITER ;

-- Search across multiple tables
DELIMITER //
CREATE PROCEDURE GlobalSearch(IN search_term VARCHAR(255))
BEGIN
    -- Search notes
    SELECT 'note' as type, id, title, description, created_at 
    FROM notes 
    WHERE MATCH(title, description, tags) AGAINST (search_term IN NATURAL LANGUAGE MODE)
    AND is_approved = TRUE
    UNION ALL
    -- Search marketplace
    SELECT 'marketplace' as type, id, title, description, created_at 
    FROM marketplace_items 
    WHERE MATCH(title, description) AGAINST (search_term IN NATURAL LANGUAGE MODE)
    AND status = 'active' AND is_approved = TRUE
    UNION ALL
    -- Search lost & found
    SELECT 'lostfound' as type, id, item_name as title, description, created_at 
    FROM lostfound 
    WHERE MATCH(item_name, description, location) AGAINST (search_term IN NATURAL LANGUAGE MODE)
    AND status = 'active'
    UNION ALL
    -- Search events
    SELECT 'event' as type, id, title, description, created_at 
    FROM events 
    WHERE MATCH(title, description, location, venue) AGAINST (search_term IN NATURAL LANGUAGE MODE)
    AND status = 'approved'
    UNION ALL
    -- Search forum threads
    SELECT 'forum' as type, id, title, content as description, created_at 
    FROM forum_threads 
    WHERE MATCH(title, content) AGAINST (search_term IN NATURAL LANGUAGE MODE)
    AND status = 'active'
    ORDER BY created_at DESC 
    LIMIT 100;
END//
DELIMITER ;

-- Update dashboard stats
DELIMITER //
CREATE PROCEDURE UpdateDashboardStats()
BEGIN
    INSERT INTO dashboard_stats (
        total_users,
        total_notes,
        total_marketplace_items,
        total_hostels,
        total_resources,
        active_listings,
        stats_date
    )
    VALUES (
        (SELECT COUNT(*) FROM users),
        (SELECT COUNT(*) FROM notes WHERE is_approved = TRUE),
        (SELECT COUNT(*) FROM marketplace_items WHERE is_approved = TRUE),
        (SELECT COUNT(*) FROM hostels WHERE is_approved = TRUE),
        (SELECT COUNT(*) FROM resources WHERE is_approved = TRUE),
        (
            (SELECT COUNT(*) FROM marketplace_items WHERE status = 'active' AND is_approved = TRUE) +
            (SELECT COUNT(*) FROM lostfound WHERE status = 'active') +
            (SELECT COUNT(*) FROM events WHERE status = 'approved')
        ),
        CURDATE()
    )
    ON DUPLICATE KEY UPDATE
        total_users = VALUES(total_users),
        total_notes = VALUES(total_notes),
        total_marketplace_items = VALUES(total_marketplace_items),
        total_hostels = VALUES(total_hostels),
        total_resources = VALUES(total_resources),
        active_listings = VALUES(active_listings),
        updated_at = NOW();
END//
DELIMITER ;