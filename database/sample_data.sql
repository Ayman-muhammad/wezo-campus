-- ============================================
-- WEZO CAMPUS HUB Sample Data
-- For testing and demonstration
-- ============================================

USE wezo_campus_hub;

-- Insert sample users (students)
INSERT INTO users (username, email, password, first_name, last_name, role, is_verified, bio) VALUES
('john_doe', 'john.doe@student.wezo.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John', 'Doe', 'student', TRUE, 'Computer Science major, interested in AI and web development.'),
('jane_smith', 'jane.smith@student.wezo.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane', 'Smith', 'verified_student', TRUE, 'Engineering student and campus ambassador.'),
('mike_wilson', 'mike.wilson@student.wezo.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mike', 'Wilson', 'moderator', TRUE, 'Campus moderator and senior student.'),
('sarah_jones', 'sarah.jones@student.wezo.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sarah', 'Jones', 'student', TRUE, 'Business administration major.');

-- Insert sample notes
INSERT INTO notes (user_id, category_id, title, slug, description, content, download_count, view_count, like_count, is_approved, tags) VALUES
(3, 1, 'Data Structures and Algorithms', 'data-structures-algorithms', 'Complete DSA notes with examples', '## Data Structures and Algorithms\n\n### Arrays\n\nArrays are...', 120, 450, 56, TRUE, 'data-structures, algorithms, programming'),
(4, 3, 'Marketing Fundamentals', 'marketing-fundamentals', 'Introduction to marketing principles', '## Marketing Fundamentals\n\n### 4 Ps of Marketing...', 85, 320, 42, TRUE, 'marketing, business, commerce'),
(5, 2, 'Thermodynamics Notes', 'thermodynamics-notes', 'Engineering thermodynamics complete notes', '## Thermodynamics\n\n### First Law...', 65, 280, 38, TRUE, 'engineering, thermodynamics, physics'),
(3, 8, 'Linear Algebra Complete Guide', 'linear-algebra-complete-guide', 'From basics to advanced linear algebra', '## Linear Algebra\n\n### Vectors...', 95, 380, 51, TRUE, 'mathematics, linear-algebra'),
(4, 1, 'Web Development Basics', 'web-development-basics', 'HTML, CSS, and JavaScript fundamentals', '## Web Development\n\n### HTML Basics...', 110, 420, 67, TRUE, 'web-development, programming, html-css');

-- Insert more marketplace items
INSERT INTO marketplace_items (user_id, category_id, title, slug, description, price, condition, images, location, status, is_approved, view_count, created_at) VALUES
(3, 1, 'Dell XPS 13 Laptop', 'dell-xps-13-laptop', 'Lightly used Dell XPS 13, 8GB RAM, 256GB SSD', 65000.00, 'like_new', '["laptop3.jpg", "laptop4.jpg"]', 'Block A Hostels', 'active', TRUE, 45, '2024-01-15 10:30:00'),
(4, 2, 'Organic Chemistry Textbook', 'organic-chemistry-textbook', '2nd edition, good condition with highlights', 2000.00, 'good', '["chem_book.jpg"]', 'Science Block', 'active', TRUE, 28, '2024-01-18 14:20:00'),
(5, 3, 'Study Desk and Chair', 'study-desk-chair', 'Wooden study desk with comfortable chair', 4500.00, 'good', '["desk1.jpg", "desk2.jpg"]', 'Hostel Zone B', 'reserved', TRUE, 62, '2024-01-10 09:15:00'),
(3, 1, 'iPhone 12 Pro', 'iphone-12-pro', '256GB, space gray, excellent condition', 75000.00, 'like_new', '["iphone1.jpg", "iphone2.jpg"]', 'Campus Mall', 'active', TRUE, 89, '2024-01-20 16:45:00'),
(4, 4, 'Formal Suit Set', 'formal-suit-set', 'Black suit for presentations and interviews', 3500.00, 'good', '["suit1.jpg", "suit2.jpg"]', 'Commerce Building', 'active', TRUE, 34, '2024-01-22 11:30:00');

-- Insert hostel reviews
INSERT INTO hostel_reviews (hostel_id, user_id, rating, title, review, cleanliness, safety, management, amenities, is_verified_stay) VALUES
(1, 3, 5, 'Excellent Stay!', 'Great hostel with amazing facilities. WiFi is fast and security is top-notch.', 5, 5, 4, 5, TRUE),
(1, 4, 4, 'Good value for money', 'Clean rooms and friendly staff. Location is convenient for classes.', 4, 4, 4, 3, TRUE),
(2, 5, 3, 'Average experience', 'Basic facilities but gets the job done. Could use better maintenance.', 3, 4, 2, 3, TRUE),
(3, 3, 5, 'Luxury student living', 'Worth every penny! Gym and pool access make it perfect.', 5, 5, 5, 5, TRUE);

-- Insert note reviews
INSERT INTO note_reviews (note_id, user_id, rating, review, is_verified_purchase) VALUES
(1, 4, 5, 'Excellent notes! Helped me ace my DSA exam.', TRUE),
(1, 5, 4, 'Very comprehensive, but could use more examples.', TRUE),
(2, 3, 5, 'Perfect for marketing beginners. Clear and concise.', TRUE),
(3, 4, 4, 'Good thermodynamics coverage, but missing some diagrams.', TRUE);

-- Insert resources
INSERT INTO resources (user_id, title, slug, description, file_path, resource_type, subject, year, semester, institution, download_count, is_approved, tags) VALUES
(1, '2023 Computer Science Final Papers', '2023-cs-final-papers', 'Collection of all CS final papers from 2023', 'cs_papers_2023.zip', 'past_paper', 'Computer Science', 2023, 'Semester 2', 'Wezo University', 156, TRUE, 'past-papers, computer-science, finals'),
(2, 'Business Statistics Textbook', 'business-statistics-textbook', 'Digital textbook for business statistics', 'business_stats.pdf', 'textbook', 'Statistics', 2023, 'Semester 1', 'Wezo University', 89, TRUE, 'textbook, statistics, business'),
(3, 'Engineering Drawing Guide', 'engineering-drawing-guide', 'Step-by-step engineering drawing tutorial', 'engineering_drawing.pdf', 'article', 'Engineering Drawing', 2023, 'Semester 1', 'Wezo University', 67, TRUE, 'engineering, drawing, guide');

-- Insert favorites
INSERT INTO favorites (user_id, item_type, item_id) VALUES
(3, 'note', 1),
(3, 'marketplace', 1),
(4, 'hostel', 1),
(5, 'resource', 1),
(3, 'note', 2),
(4, 'marketplace', 2);

-- Insert notifications
INSERT INTO notifications (user_id, type, title, message, data, is_read) VALUES
(3, 'note_approved', 'Note Approved', 'Your note "Data Structures and Algorithms" has been approved and published.', '{"note_id": 1, "note_title": "Data Structures and Algorithms"}', TRUE),
(4, 'marketplace_sold', 'Item Reserved', 'Your item "Organic Chemistry Textbook" has been reserved by a buyer.', '{"item_id": 2, "item_title": "Organic Chemistry Textbook"}', FALSE),
(5, 'new_message', 'New Message', 'You have received a new message about your hostel review.', '{"sender_id": 3, "message_preview": "Thanks for your review..."}', FALSE),
(3, 'system_update', 'System Update', 'WEZO CAMPUS HUB has been updated with new features.', '{"version": "1.2.0", "features": ["Improved search", "Better UI"]}', TRUE);

-- Update hostel ratings based on reviews
UPDATE hostels h
SET rating = (
    SELECT AVG(rating) 
    FROM hostel_reviews hr 
    WHERE hr.hostel_id = h.id
),
review_count = (
    SELECT COUNT(*) 
    FROM hostel_reviews hr 
    WHERE hr.hostel_id = h.id
)
WHERE id IN (1, 2, 3);

-- Update note ratings
UPDATE notes n
SET like_count = (
    SELECT COUNT(*) 
    FROM note_reviews nr 
    WHERE nr.note_id = n.id AND nr.rating >= 4
)
WHERE id IN (1, 2, 3, 4, 5);

-- Insert activity logs
INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES
(1, 'user_login', 'Admin user logged in', '192.168.1.100'),
(2, 'user_registration', 'New user registered: john_doe', '192.168.1.101'),
(3, 'note_upload', 'Uploaded new note: Data Structures and Algorithms', '192.168.1.102'),
(4, 'marketplace_post', 'Posted new item: Organic Chemistry Textbook', '192.168.1.103'),
(5, 'hostel_review', 'Posted review for Campus View Hostels', '192.168.1.104');

-- Create views for reporting
CREATE OR REPLACE VIEW dashboard_stats AS
SELECT 
    (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students,
    (SELECT COUNT(*) FROM notes WHERE is_approved = TRUE) as total_notes,
    (SELECT COUNT(*) FROM marketplace_items WHERE status = 'active' AND is_approved = TRUE) as active_items,
    (SELECT COUNT(*) FROM hostels WHERE is_approved = TRUE) as total_hostels,
    (SELECT COUNT(*) FROM resources WHERE is_approved = TRUE) as total_resources,
    (SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()) as new_users_today,
    (SELECT SUM(download_count) FROM notes) as total_note_downloads,
    (SELECT SUM(view_count) FROM marketplace_items) as total_marketplace_views;

-- Create view for user activity
CREATE OR REPLACE VIEW user_activity_summary AS
SELECT 
    u.id,
    u.username,
    u.email,
    u.role,
    u.created_at,
    (SELECT COUNT(*) FROM notes WHERE user_id = u.id) as notes_count,
    (SELECT COUNT(*) FROM marketplace_items WHERE user_id = u.id) as items_count,
    (SELECT COUNT(*) FROM hostel_reviews WHERE user_id = u.id) as reviews_count,
    (SELECT MAX(created_at) FROM activity_logs WHERE user_id = u.id) as last_activity
FROM users u
ORDER BY u.created_at DESC;