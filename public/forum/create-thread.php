<?php
/**
 * WEZO CAMPUS HUB - Create Forum Thread
 * Powered by AYGLOBE INC
 */
require_once __DIR__ . '/../../core/Config.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Session.php';
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Validation.php';

use Core\Auth;
use Core\Database;
use Core\Session;
use Core\Helpers;
use Core\Validation;

Auth::init();
Auth::requireLogin();

$db = Database::getInstance();
$user = Auth::user();

// Get categories
$categories = $db->fetchAll("
    SELECT * FROM forum_categories 
    WHERE is_private = FALSE OR ? = 'admin'
    ORDER BY sort_order, name
", [$user['role']]);

$errors = [];
$formData = [
    'title' => '',
    'category_id' => '',
    'content' => '',
    'is_pinned' => 0,
    'is_featured' => 0
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = array_merge($formData, $_POST);
    
    // Validate
    if (empty(trim($formData['title']))) {
        $errors[] = 'Title is required';
    } elseif (strlen(trim($formData['title'])) < 5) {
        $errors[] = 'Title must be at least 5 characters';
    }
    
    if (empty($formData['category_id'])) {
        $errors[] = 'Category is required';
    }
    
    $contentErrors = Validation::validateForumContent($formData['content']);
    if (!empty($contentErrors)) {
        $errors = array_merge($errors, $contentErrors);
    }
    
    // Check if user can pin/feature (admin only)
    if ($user['role'] !== 'admin') {
        $formData['is_pinned'] = 0;
        $formData['is_featured'] = 0;
    }
    
    if (empty($errors)) {
        // Create thread
        $threadData = [
            'category_id' => intval($formData['category_id']),
            'user_id' => $user['id'],
            'title' => Validation::sanitize($formData['title']),
            'content' => Helpers::sanitizeForumContent($formData['content']),
            'is_pinned' => $formData['is_pinned'] ? 1 : 0,
            'is_featured' => $formData['is_featured'] ? 1 : 0,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $threadId = $db->insert('forum_threads', $threadData);
        
        if ($threadId) {
            // Update category counts
            $db->query("
                UPDATE forum_categories 
                SET thread_count = thread_count + 1,
                    post_count = post_count + 1,
                    last_post_id = ?
                WHERE id = ?
            ", [$threadId, $formData['category_id']]);
            
            Session::setFlash('success', 'Thread created successfully!');
            header('Location: thread.php?id=' . $threadId);
            exit;
        } else {
            $errors[] = 'Failed to create thread. Please try again.';
        }
    }
}

$pageTitle = "Create New Thread";
include __DIR__ . '/../../templates/header.php';
include __DIR__ . '/../../templates/navbar.php';
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <?php include __DIR__ . '/../../templates/sidebar.php'; ?>
        </div>
        <div class="col-md-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Create New Thread</h1>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Forum
                </a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="POST" id="threadForm">
                        <div class="mb-3">
                            <label class="form-label">Title *</label>
                            <input type="text" name="title" class="form-control" 
                                   value="<?= htmlspecialchars($formData['title']) ?>" 
                                   required minlength="5" maxlength="200">
                            <small class="text-muted">Make it clear and descriptive</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Category *</label>
                            <select name="category_id" class="form-select" required>
                                <option value="">Select a category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" 
                                        <?= $formData['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                        <?php if ($cat['description']): ?>
                                            - <?= htmlspecialchars($cat['description']) ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Content *</label>
                            <textarea name="content" id="content" class="form-control" 
                                      rows="10" required><?= htmlspecialchars($formData['content']) ?></textarea>
                            <div class="mt-2">
                                <small class="text-muted">
                                    <span id="charCount">0</span> characters • Minimum 10 characters
                                </small>
                                <div class="btn-group btn-group-sm mt-1">
                                    <button type="button" class="btn btn-outline-secondary" onclick="formatText('bold')">
                                        <i class="fas fa-bold"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="formatText('italic')">
                                        <i class="fas fa-italic"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="formatText('code')">
                                        <i class="fas fa-code"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="formatText('link')">
                                        <i class="fas fa-link"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="formatText('list')">
                                        <i class="fas fa-list"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="formatText('quote')">
                                        <i class="fas fa-quote-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <?php if ($user['role'] === 'admin'): ?>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input type="checkbox" name="is_pinned" class="form-check-input" 
                                               id="is_pinned" <?= $formData['is_pinned'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="is_pinned">
                                            Pin this thread (appears at top)
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input type="checkbox" name="is_featured" class="form-check-input" 
                                               id="is_featured" <?= $formData['is_featured'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="is_featured">
                                            Feature this thread (highlighted)
                                        </label>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-paper-plane"></i> Create Thread
                            </button>
                            <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Formatting Help -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0">Formatting Help</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>**bold**</strong></td>
                                    <td>→</td>
                                    <td><strong>bold</strong></td>
                                </tr>
                                <tr>
                                    <td><em>*italic*</em></td>
                                    <td>→</td>
                                    <td><em>italic</em></td>
                                </tr>
                                <tr>
                                    <td>`code`</td>
                                    <td>→</td>
                                    <td><code>code</code></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <td>[link](url)</td>
                                    <td>→</td>
                                    <td><a href="#">link</a></td>
                                </tr>
                                <tr>
                                    <td>> quote</td>
                                    <td>→</td>
                                    <td><blockquote class="blockquote">quote</blockquote></td>
                                </tr>
                                <tr>
                                    <td>- list item</td>
                                    <td>→</td>
                                    <td>• list item</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Character counter
document.getElementById('content').addEventListener('input', function() {
    document.getElementById('charCount').textContent = this.value.length;
});

// Text formatting
function formatText(type) {
    const textarea = document.getElementById('content');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = textarea.value.substring(start, end);
    
    let formattedText = '';
    let cursorPos = start;
    
    switch(type) {
        case 'bold':
            formattedText = '**' + selectedText + '**';
            cursorPos = start + 2;
            break;
        case 'italic':
            formattedText = '*' + selectedText + '*';
            cursorPos = start + 1;
            break;
        case 'code':
            formattedText = '`' + selectedText + '`';
            cursorPos = start + 1;
            break;
        case 'link':
            formattedText = '[' + (selectedText || 'link text') + '](url)';
            cursorPos = start + 1;
            break;
        case 'list':
            formattedText = '- ' + selectedText;
            cursorPos = start + 2;
            break;
        case 'quote':
            formattedText = '> ' + selectedText;
            cursorPos = start + 2;
            break;
    }
    
    textarea.value = textarea.value.substring(0, start) + formattedText + textarea.value.substring(end);
    textarea.focus();
    textarea.setSelectionRange(cursorPos, cursorPos + (selectedText.length || 0));
}

// Form validation
document.getElementById('threadForm').addEventListener('submit', function(e) {
    const title = this.elements['title'].value.trim();
    const content = this.elements['content'].value.trim();
    
    if (title.length < 5) {
        e.preventDefault();
        alert('Title must be at least 5 characters');
        return false;
    }
    
    if (content.length < 10) {
        e.preventDefault();
        alert('Content must be at least 10 characters');
        return false;
    }
    
    return true;
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>