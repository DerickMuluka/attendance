// Main JavaScript for Attendance System

document.addEventListener('DOMContentLoaded', function() {
    // Initialize the application
    initApp();
    
    // Set up event listeners
    setupEventListeners();
    
    // Check authentication status
    checkAuthStatus();
    
    // Initialize theme
    initTheme();
});

// Initialize the application
function initApp() {
    console.log('Attendance System initialized');
    
    // Set current year in footer
    document.getElementById('currentYear').textContent = new Date().getFullYear();
    
    // Initialize tooltips
    initTooltips();
    
    // Initialize charts if on dashboard
    if (document.querySelector('.dashboard')) {
        initCharts();
    }
}

// Set up event listeners
function setupEventListeners() {
    // Mobile menu toggle
    const menuToggle = document.querySelector('.menu-toggle');
    if (menuToggle) {
        menuToggle.addEventListener('click', toggleMobileMenu);
    }
    
    // Theme toggle
    const themeToggle = document.querySelector('.theme-toggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', toggleTheme);
    }
    
    // Navigation links
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', handleNavigation);
    });
    
    // Form submissions
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', handleFormSubmit);
    });
    
    // Modal handling
    const modalTriggers = document.querySelectorAll('[data-modal]');
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', openModal);
    });
    
    const modalCloses = document.querySelectorAll('.close, .modal');
    modalCloses.forEach(close => {
        close.addEventListener('click', closeModal);
    });
    
    // Prevent modal content from closing when clicking inside
    const modalContents = document.querySelectorAll('.modal-content');
    modalContents.forEach(content => {
        content.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
    
    // Logout button
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', handleLogout);
    }
    
    // Demo video buttons
    const demoButtons = document.querySelectorAll('.demo-video-btn');
    demoButtons.forEach(button => {
        button.addEventListener('click', showDemoVideo);
    });
}

// Check authentication status
function checkAuthStatus() {
    const authPages = ['login.html', 'register.html', 'forgot-password.html'];
    const currentPage = window.location.pathname.split('/').pop();
    
    // Check if user is logged in
    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
    const userData = JSON.parse(localStorage.getItem('userData') || '{}');
    
    if (isLoggedIn && userData) {
        // Update UI for logged in user
        updateUserUI(userData);
        
        // Redirect from auth pages to dashboard if logged in
        if (authPages.includes(currentPage)) {
            window.location.href = 'dashboard.html';
        }
    } else {
        // Redirect to login if trying to access protected pages
        if (!authPages.includes(currentPage) && currentPage !== 'index.html' && currentPage !== '') {
            window.location.href = 'login.html';
        }
    }
}

// Update UI with user data
function updateUserUI(userData) {
    // Update welcome message
    const welcomeElements = document.querySelectorAll('.welcome-user');
    welcomeElements.forEach(element => {
        element.textContent = userData.full_name || userData.username;
    });
    
    // Update profile information
    const profileName = document.getElementById('profileName');
    const profileEmail = document.getElementById('profileEmail');
    const profileUsername = document.getElementById('profileUsername');
    const profileDepartment = document.getElementById('profileDepartment');
    
    if (profileName) profileName.textContent = userData.full_name || 'N/A';
    if (profileEmail) profileEmail.textContent = userData.email || 'N/A';
    if (profileUsername) profileUsername.textContent = userData.username || 'N/A';
    if (profileDepartment) profileDepartment.textContent = userData.department || 'N/A';
}

// Handle navigation
function handleNavigation(e) {
    e.preventDefault();
    
    const target = e.target.closest('.nav-link');
    if (!target) return;
    
    const page = target.getAttribute('href');
    if (page) {
        // Add loading state
        showLoading();
        
        // Navigate to page after a short delay to show loading animation
        setTimeout(() => {
            window.location.href = page;
        }, 500);
    }
}

// Handle form submissions
function handleFormSubmit(e) {
    e.preventDefault();
    
    const form = e.target;
    const formId = form.id;
    const submitBtn = form.querySelector('button[type="submit"]');
    
    // Show loading state
    if (submitBtn) {
        const originalText = submitBtn.textContent;
        submitBtn.innerHTML = '<div class="spinner"></div>';
        submitBtn.disabled = true;
    }
    
    // Simulate API call
    setTimeout(() => {
        if (formId === 'loginForm') {
            handleLogin(form);
        } else if (formId === 'registerForm') {
            handleRegister(form);
        } else if (formId === 'forgotPasswordForm') {
            handleForgotPassword(form);
        } else if (formId === 'profileForm') {
            handleProfileUpdate(form);
        } else if (formId === 'contactForm') {
            handleContactForm(form);
        }
        
        // Reset button state
        if (submitBtn) {
            submitBtn.textContent = submitBtn.getAttribute('data-original-text') || 'Submit';
            submitBtn.disabled = false;
        }
    }, 1500);
}

// Handle login
function handleLogin(form) {
    const formData = new FormData(form);
    const username = formData.get('username');
    const password = formData.get('password');
    
    // Simple validation
    if (!username || !password) {
        showNotification('Please fill in all fields', 'error');
        return;
    }
    
    // Send login request to server
    $.ajax({
        url: 'php/login.php',
        method: 'POST',
        data: {
            username: username,
            password: password
        },
        success: function(response) {
            try {
                const data = JSON.parse(response);
                if (data.success) {
                    // Store user data in localStorage
                    localStorage.setItem('isLoggedIn', 'true');
                    localStorage.setItem('userData', JSON.stringify(data.user));
                    
                    showNotification('Login successful!', 'success');
                    
                    // Redirect to dashboard after a brief delay
                    setTimeout(() => {
                        window.location.href = 'dashboard.html';
                    }, 1000);
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (e) {
                showNotification('Invalid server response', 'error');
            }
        },
        error: function(xhr, status, error) {
            showNotification('An error occurred during login: ' + error, 'error');
        }
    });
}

// Handle registration
function handleRegister(form) {
    const formData = new FormData(form);
    const username = formData.get('username');
    const email = formData.get('email');
    const fullname = formData.get('full_name');
    const password = formData.get('password');
    const confirmPassword = formData.get('confirm_password');
    
    // Simple validation
    if (!username || !email || !fullname || !password || !confirmPassword) {
        showNotification('Please fill in all fields', 'error');
        return;
    }
    
    if (password !== confirmPassword) {
        showNotification('Passwords do not match', 'error');
        return;
    }
    
    if (password.length < 8) {
        showNotification('Password must be at least 8 characters long', 'error');
        return;
    }
    
    // Send registration request to server
    $.ajax({
        url: 'php/register.php',
        method: 'POST',
        data: {
            username: username,
            email: email,
            full_name: fullname,
            password: password
        },
        success: function(response) {
            try {
                const data = JSON.parse(response);
                if (data.success) {
                    showNotification('Registration successful! Please login.', 'success');
                    
                    // Redirect to login page after a brief delay
                    setTimeout(() => {
                        window.location.href = 'login.html';
                    }, 1500);
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (e) {
                showNotification('Invalid server response', 'error');
            }
        },
        error: function(xhr, status, error) {
            showNotification('An error occurred during registration: ' + error, 'error');
        }
    });
}

// Handle forgot password
function handleForgotPassword(form) {
    const formData = new FormData(form);
    const email = formData.get('email');
    
    if (!email) {
        showNotification('Please enter your email address', 'error');
        return;
    }
    
    // Send password reset request
    $.ajax({
        url: 'php/forgot_password.php',
        method: 'POST',
        data: { email: email },
        success: function(response) {
            try {
                const data = JSON.parse(response);
                if (data.success) {
                    showNotification('Password reset instructions sent to your email', 'success');
                    form.reset();
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (e) {
                showNotification('Invalid server response', 'error');
            }
        },
        error: function(xhr, status, error) {
            showNotification('An error occurred: ' + error, 'error');
        }
    });
}

// Handle profile update
function handleProfileUpdate(form) {
    const formData = new FormData(form);
    
    // Simulate profile update
    showNotification('Profile updated successfully', 'success');
}

// Handle contact form
function handleContactForm(form) {
    const formData = new FormData(form);
    
    // Simulate contact form submission
    showNotification('Thank you for your message! We will get back to you soon.', 'success');
    form.reset();
}

// Handle logout
function handleLogout() {
    // Send logout request to server
    $.ajax({
        url: 'php/logout.php',
        method: 'POST',
        success: function() {
            // Clear storage
            localStorage.removeItem('isLoggedIn');
            localStorage.removeItem('userData');
            
            showNotification('Logged out successfully', 'success');
            
            // Redirect to login page
            setTimeout(() => {
                window.location.href = 'login.html';
            }, 1000);
        },
        error: function() {
            // Clear storage even if request fails
            localStorage.removeItem('isLoggedIn');
            localStorage.removeItem('userData');
            window.location.href = 'login.html';
        }
    });
}

// Toggle mobile menu
function toggleMobileMenu() {
    const navLinks = document.querySelector('.nav-links');
    navLinks.classList.toggle('active');
    
    // Update menu icon
    const menuIcon = document.querySelector('.menu-toggle i');
    if (navLinks.classList.contains('active')) {
        menuIcon.className = 'fas fa-times';
    } else {
        menuIcon.className = 'fas fa-bars';
    }
}

// Toggle theme
function toggleTheme() {
    const body = document.body;
    const currentTheme = body.getAttribute('data-theme') || 'light';
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    
    body.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    
    // Update theme toggle icon
    const themeIcon = document.querySelector('.theme-toggle i');
    if (themeIcon) {
        themeIcon.className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
    
    showNotification(`${newTheme === 'dark' ? 'Dark' : 'Light'} mode enabled`, 'success');
}

// Initialize theme
function initTheme() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    const body = document.body;
    
    body.setAttribute('data-theme', savedTheme);
    
    // Set theme toggle icon
    const themeIcon = document.querySelector('.theme-toggle i');
    if (themeIcon) {
        themeIcon.className = savedTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
}

// Show notification
function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => {
        notification.remove();
    });
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    
    // Set icon based on type
    let icon = 'fas fa-info-circle';
    if (type === 'success') icon = 'fas fa-check-circle';
    if (type === 'error') icon = 'fas fa-exclamation-circle';
    if (type === 'warning') icon = 'fas fa-exclamation-triangle';
    
    notification.innerHTML = `
        <i class="${icon}"></i>
        <span>${message}</span>
    `;
    
    // Add to document
    document.body.appendChild(notification);
    
    // Show notification
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    // Hide after delay
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

// Show loading state
function showLoading() {
    const loadingOverlay = document.createElement('div');
    loadingOverlay.className = 'loading-overlay';
    loadingOverlay.innerHTML = `
        <div class="spinner"></div>
        <p>Loading...</p>
    `;
    
    document.body.appendChild(loadingOverlay);
    
    // Remove after navigation
    setTimeout(() => {
        if (loadingOverlay.parentNode) {
            loadingOverlay.remove();
        }
    }, 2000);
}

// Open modal
function openModal(e) {
    e.preventDefault();
    
    const modalId = this.getAttribute('data-modal');
    const modal = document.getElementById(modalId);
    
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
}

// Close modal
function closeModal(e) {
    if (e.target.classList.contains('modal') || e.target.classList.contains('close')) {
        const modal = e.target.closest('.modal');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Show demo video
function showDemoVideo(e) {
    e.preventDefault();
    
    const videoType = this.getAttribute('data-video');
    const modal = document.getElementById('demoModal');
    const modalTitle = document.getElementById('modalTitle');
    
    if (modal && modalTitle) {
        let title = 'Demo Video';
        switch(videoType) {
            case 'checkin':
                title = 'Easy Check-ins Demo';
                break;
            case 'dashboard':
                title = 'Live Dashboard Demo';
                break;
            case 'admin':
                title = 'Admin Features Demo';
                break;
        }
        
        modalTitle.textContent = title;
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
}

// Initialize tooltips
function initTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

// Show tooltip
function showTooltip(e) {
    const tooltipText = this.getAttribute('data-tooltip');
    
    // Create tooltip element
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = tooltipText;
    
    // Position tooltip
    const rect = this.getBoundingClientRect();
    tooltip.style.position = 'fixed';
    tooltip.style.top = `${rect.top - 40}px`;
    tooltip.style.left = `${rect.left + (rect.width / 2)}px`;
    tooltip.style.transform = 'translateX(-50%)';
    
    // Add to document
    document.body.appendChild(tooltip);
    
    // Store reference
    this.tooltip = tooltip;
}

// Hide tooltip
function hideTooltip() {
    if (this.tooltip) {
        this.tooltip.remove();
        this.tooltip = null;
    }
}

// Initialize charts
function initCharts() {
    // This would be replaced with actual chart library integration
    console.log('Charts initialized');
}

// Utility function to format date
function formatDate(date) {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

// Utility function to format time
function formatTime(date) {
    return new Date(date).toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Export functions for use in other modules
window.AttendanceSystem = {
    showNotification,
    formatDate,
    formatTime
};
