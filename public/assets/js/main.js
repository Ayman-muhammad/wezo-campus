/**
 * WEZO CAMPUS HUB - Main JavaScript
 * Powered by AYGLOBE INC
 */

// DOM Ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initComponents();
    setupEventListeners();
    loadUserPreferences();
});

/**
 * Initialize all UI components
 */
function initComponents() {
    // Initialize tooltips
    initTooltips();
    
    // Initialize dropdowns
    initDropdowns();
    
    // Initialize modals
    initModals();
    
    // Initialize tabs
    initTabs();
    
    // Initialize forms
    initForms();
    
    // Initialize notifications
    initNotifications();
}

/**
 * Initialize Bootstrap tooltips
 */
function initTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Initialize dropdowns
 */
function initDropdowns() {
    const dropdowns = document.querySelectorAll('.dropdown');
    dropdowns.forEach(dropdown => {
        dropdown.addEventListener('show.bs.dropdown', function() {
            this.classList.add('show');
        });
        
        dropdown.addEventListener('hide.bs.dropdown', function() {
            this.classList.remove('show');
        });
    });
}

/**
 * Initialize modals
 */
function initModals() {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.addEventListener('show.bs.modal', function() {
            document.body.classList.add('modal-open');
        });
        
        modal.addEventListener('hide.bs.modal', function() {
            document.body.classList.remove('modal-open');
        });
    });
}

/**
 * Initialize tabs
 */
function initTabs() {
    const tabTriggers = document.querySelectorAll('[data-bs-toggle="tab"]');
    tabTriggers.forEach(trigger => {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            
            const target = document.querySelector(this.getAttribute('data-bs-target'));
            if (target) {
                // Hide all tab panes
                document.querySelectorAll('.tab-pane').forEach(pane => {
                    pane.classList.remove('show', 'active');
                });
                
                // Deactivate all tab triggers
                document.querySelectorAll('.nav-link').forEach(link => {
                    link.classList.remove('active');
                });
                
                // Activate current tab
                this.classList.add('active');
                target.classList.add('show', 'active');
            }
        });
    });
}

/**
 * Initialize form validation and enhancements
 */
function initForms() {
    // Auto-dismiss alerts
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.classList.add('fade');
            setTimeout(() => alert.remove(), 150);
        }, 5000);
    });
    
    // Form validation
    const forms = document.querySelectorAll('form[novalidate]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!this.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            this.classList.add('was-validated');
        });
    });
    
    // Password strength meter
    const passwordInputs = document.querySelectorAll('input[type="password"]');
    passwordInputs.forEach(input => {
        input.addEventListener('input', function() {
            checkPasswordStrength(this.value);
        });
    });
    
    // Character counters
    const textareas = document.querySelectorAll('textarea[maxlength]');
    textareas.forEach(textarea => {
        const maxLength = parseInt(textarea.getAttribute('maxlength'));
        const counter = document.createElement('small');
        counter.className = 'text-muted text-end d-block mt-1';
        counter.textContent = `0/${maxLength}`;
        textarea.parentNode.appendChild(counter);
        
        textarea.addEventListener('input', function() {
            const length = this.value.length;
            counter.textContent = `${length}/${maxLength}`;
            
            if (length > maxLength * 0.9) {
                counter.classList.add('text-warning');
            } else {
                counter.classList.remove('text-warning');
            }
        });
    });
}

/**
 * Initialize notification system
 */
function initNotifications() {
    // Check for new notifications
    checkNotifications();
    
    // Setup notification bell
    const bell = document.getElementById('notificationBell');
    if (bell) {
        bell.addEventListener('click', function() {
            markNotificationsAsRead();
        });
    }
}

/**
 * Setup global event listeners
 */
function setupEventListeners() {
    // Theme toggle
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', toggleTheme);
    }
    
    // Search functionality
    const searchInput = document.getElementById('globalSearch');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(handleSearch, 300));
    }
    
    // Mobile menu toggle
    const menuToggle = document.getElementById('mobileMenuToggle');
    if (menuToggle) {
        menuToggle.addEventListener('click', toggleMobileMenu);
    }
    
    // Back to top button
    const backToTop = document.getElementById('backToTop');
    if (backToTop) {
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                backToTop.classList.add('show');
            } else {
                backToTop.classList.remove('show');
            }
        });
        
        backToTop.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
    
    // Image lazy loading
    setupLazyLoading();
    
    // Offline detection
    window.addEventListener('online', handleOnlineStatus);
    window.addEventListener('offline', handleOfflineStatus);
}

/**
 * Load user preferences
 */
function loadUserPreferences() {
    // Load theme
    const theme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', theme);
    
    // Load sidebar state
    const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (sidebarCollapsed) {
        document.body.classList.add('sidebar-collapsed');
    }
    
    // Load other preferences
    const preferences = JSON.parse(localStorage.getItem('preferences') || '{}');
    applyPreferences(preferences);
}

/**
 * Check password strength
 */
function checkPasswordStrength(password) {
    if (!password) return;
    
    let strength = 0;
    const meter = document.getElementById('passwordStrength');
    const feedback = document.getElementById('passwordFeedback');
    
    if (!meter || !feedback) return;
    
    // Check length
    if (password.length >= 8) strength++;
    
    // Check for lowercase
    if (/[a-z]/.test(password)) strength++;
    
    // Check for uppercase
    if (/[A-Z]/.test(password)) strength++;
    
    // Check for numbers
    if (/\d/.test(password)) strength++;
    
    // Check for special characters
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    
    // Update meter
    meter.value = strength;
    
    // Update feedback
    const messages = [
        'Very weak',
        'Weak',
        'Fair',
        'Good',
        'Strong'
    ];
    
    const colors = [
        'danger',
        'warning',
        'info',
        'success',
        'success'
    ];
    
    feedback.textContent = messages[strength];
    feedback.className = `text-${colors[strength]}`;
}

/**
 * Toggle dark/light theme
 */
function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    
    // Update button icon
    const icon = document.querySelector('#themeToggle i');
    if (icon) {
        icon.className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
}

/**
 * Toggle mobile menu
 */
function toggleMobileMenu() {
    const menu = document.getElementById('mobileMenu');
    if (menu) {
        menu.classList.toggle('show');
    }
}

/**
 * Handle global search
 */
function handleSearch(query) {
    if (query.length < 2) return;
    
    // Show loading state
    const resultsContainer = document.getElementById('searchResults');
    if (resultsContainer) {
        resultsContainer.innerHTML = '<div class="text-center p-3"><div class="spinner-border spinner-border-sm"></div></div>';
        resultsContainer.classList.add('show');
    }
    
    // Perform search
    fetch(`/api/search?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            displaySearchResults(data);
        })
        .catch(error => {
            console.error('Search error:', error);
            if (resultsContainer) {
                resultsContainer.innerHTML = '<div class="text-center p-3 text-danger">Search failed</div>';
            }
        });
}

/**
 * Display search results
 */
function displaySearchResults(results) {
    const container = document.getElementById('searchResults');
    if (!container) return;
    
    if (!results || results.length === 0) {
        container.innerHTML = '<div class="text-center p-3 text-muted">No results found</div>';
        return;
    }
    
    let html = '<div class="list-group list-group-flush">';
    
    results.forEach(result => {
        html += `
            <a href="${result.url}" class="list-group-item list-group-item-action">
                <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1">${escapeHtml(result.title)}</h6>
                    <small class="text-muted">${result.type}</small>
                </div>
                <p class="mb-1 small text-muted">${escapeHtml(result.description)}</p>
            </a>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

/**
 * Check for notifications
 */
function checkNotifications() {
    // Only check if user is logged in
    if (!window.userId) return;
    
    fetch('/api/notifications/unread')
        .then(response => response.json())
        .then(data => {
            updateNotificationBadge(data.count);
        })
        .catch(console.error);
}

/**
 * Update notification badge
 */
function updateNotificationBadge(count) {
    const badge = document.getElementById('notificationBadge');
    if (badge) {
        if (count > 0) {
            badge.textContent = count;
            badge.classList.remove('d-none');
        } else {
            badge.classList.add('d-none');
        }
    }
}

/**
 * Mark notifications as read
 */
function markNotificationsAsRead() {
    fetch('/api/notifications/mark-read', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': getCsrfToken()
        }
    })
    .then(() => {
        updateNotificationBadge(0);
    })
    .catch(console.error);
}

/**
 * Setup lazy loading for images
 */
function setupLazyLoading() {
    const images = document.querySelectorAll('img[data-src]');
    
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                    observer.unobserve(img);
                }
            });
        });
        
        images.forEach(img => observer.observe(img));
    } else {
        // Fallback for older browsers
        images.forEach(img => {
            img.src = img.dataset.src;
        });
    }
}

/**
 * Handle online status
 */
function handleOnlineStatus() {
    showToast('You are back online', 'success');
    
    // Sync any pending data
    if (window.syncQueue && window.syncQueue.length > 0) {
        syncPendingData();
    }
}

/**
 * Handle offline status
 */
function handleOfflineStatus() {
    showToast('You are offline. Some features may be limited.', 'warning');
}

/**
 * Sync pending data when back online
 */
function syncPendingData() {
    const queue = window.syncQueue || [];
    
    queue.forEach(item => {
        fetch(item.url, {
            method: item.method,
            headers: item.headers,
            body: item.body
        })
        .then(response => {
            if (response.ok) {
                // Remove from queue
                window.syncQueue = window.syncQueue.filter(q => q !== item);
            }
        })
        .catch(console.error);
    });
}

/**
 * Show toast notification
 */
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                ${escapeHtml(message)}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    const container = document.getElementById('toastContainer');
    if (!container) {
        const newContainer = document.createElement('div');
        newContainer.id = 'toastContainer';
        newContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(newContainer);
        container = newContainer;
    }
    
    container.appendChild(toast);
    
    const bsToast = new bootstrap.Toast(toast, { delay: 5000 });
    bsToast.show();
    
    toast.addEventListener('hidden.bs.toast', function() {
        this.remove();
    });
}

/**
 * Copy text to clipboard
 */
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('Copied to clipboard', 'success');
    }).catch(err => {
        console.error('Failed to copy:', err);
        showToast('Failed to copy', 'danger');
    });
}

/**
 * Format file size
 */
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

/**
 * Format date relative to now
 */
function timeAgo(date) {
    const seconds = Math.floor((new Date() - new Date(date)) / 1000);
    
    let interval = Math.floor(seconds / 31536000);
    if (interval >= 1) return interval + ' year' + (interval > 1 ? 's' : '') + ' ago';
    
    interval = Math.floor(seconds / 2592000);
    if (interval >= 1) return interval + ' month' + (interval > 1 ? 's' : '') + ' ago';
    
    interval = Math.floor(seconds / 86400);
    if (interval >= 1) return interval + ' day' + (interval > 1 ? 's' : '') + ' ago';
    
    interval = Math.floor(seconds / 3600);
    if (interval >= 1) return interval + ' hour' + (interval > 1 ? 's' : '') + ' ago';
    
    interval = Math.floor(seconds / 60);
    if (interval >= 1) return interval + ' minute' + (interval > 1 ? 's' : '') + ' ago';
    
    return 'just now';
}

/**
 * Get CSRF token
 */
function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Debounce function
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Throttle function
 */
function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

/**
 * API wrapper for consistent error handling
 */
const api = {
    get: async (url) => {
        try {
            const response = await fetch(url, {
                headers: {
                    'X-CSRF-Token': getCsrfToken()
                }
            });
            return await handleResponse(response);
        } catch (error) {
            handleApiError(error);
            throw error;
        }
    },
    
    post: async (url, data) => {
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCsrfToken()
                },
                body: JSON.stringify(data)
            });
            return await handleResponse(response);
        } catch (error) {
            handleApiError(error);
            throw error;
        }
    },
    
    put: async (url, data) => {
        try {
            const response = await fetch(url, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCsrfToken()
                },
                body: JSON.stringify(data)
            });
            return await handleResponse(response);
        } catch (error) {
            handleApiError(error);
            throw error;
        }
    },
    
    delete: async (url) => {
        try {
            const response = await fetch(url, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-Token': getCsrfToken()
                }
            });
            return await handleResponse(response);
        } catch (error) {
            handleApiError(error);
            throw error;
        }
    }
};

/**
 * Handle API response
 */
async function handleResponse(response) {
    if (!response.ok) {
        const error = await response.text();
        throw new Error(error);
    }
    
    const contentType = response.headers.get('content-type');
    if (contentType && contentType.includes('application/json')) {
        return await response.json();
    }
    
    return await response.text();
}

/**
 * Handle API errors
 */
function handleApiError(error) {
    console.error('API Error:', error);
    
    let message = 'An error occurred';
    if (error.message) {
        try {
            const errorData = JSON.parse(error.message);
            message = errorData.message || error.message;
        } catch {
            message = error.message;
        }
    }
    
    showToast(message, 'danger');
    
    // Handle specific error codes
    if (error.status === 401) {
        // Unauthorized - redirect to login
        setTimeout(() => {
            window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname);
        }, 2000);
    } else if (error.status === 403) {
        // Forbidden
        showToast('You do not have permission to perform this action', 'warning');
    } else if (error.status === 429) {
        // Too many requests
        showToast('Too many requests. Please try again later.', 'warning');
    }
}

// Export for use in other modules
window.WEZO = {
    api,
    utils: {
        formatFileSize,
        timeAgo,
        copyToClipboard,
        escapeHtml,
        debounce,
        throttle
    },
    ui: {
        showToast,
        initComponents
    }
};

// Global error handler
window.addEventListener('error', function(event) {
    console.error('Global error:', event.error);
    
    // Don't show error toast for network errors
    if (event.error && event.error.name !== 'TypeError') {
        showToast('An unexpected error occurred', 'danger');
    }
});

// Unhandled promise rejection handler
window.addEventListener('unhandledrejection', function(event) {
    console.error('Unhandled promise rejection:', event.reason);
    showToast('An unexpected error occurred', 'danger');
});