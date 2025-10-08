// Authentication functionality
$(document).ready(function() {
    // Check if user is already logged in
    checkAuthStatus();
    
    // Password strength indicator
    $('#regPassword').on('input', function() {
        checkPasswordStrength($(this).val());
    });
    
    // Handle login form submission
    $('#loginForm').on('submit', function(e) {
        e.preventDefault();
        const username = $('#loginUsername').val();
        const password = $('#loginPassword').val();
        const rememberMe = $('#rememberMe').is(':checked');
        
        // Simple validation
        if (!username || !password) {
            showNotification('Please fill in all fields', 'error');
            return;
        }
        
        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Signing in...');
        
        // Send login request to server
        $.ajax({
            url: 'php/login.php',
            method: 'POST',
            data: {
                username: username,
                password: password,
                remember_me: rememberMe
            },
            success: function(response) {
                try {
                    const data = JSON.parse(response);
                    if (data.success) {
                        // Store user data in localStorage
                        localStorage.setItem('user', JSON.stringify(data.user));
                        if (rememberMe) {
                            localStorage.setItem('rememberMe', 'true');
                        }
                        
                        showNotification('Login successful!', 'success');
                        
                        // Redirect to dashboard after a brief delay
                        setTimeout(() => {
                            window.location.href = 'dashboard.html';
                        }, 1000);
                    } else {
                        showNotification(data.message, 'error');
                        submitBtn.prop('disabled', false).html(originalText);
                    }
                } catch (e) {
                    showNotification('Invalid server response', 'error');
                    submitBtn.prop('disabled', false).html(originalText);
                }
            },
            error: function(xhr, status, error) {
                showNotification('An error occurred during login: ' + error, 'error');
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Handle registration form submission
    $('#registerForm').on('submit', function(e) {
        e.preventDefault();
        const username = $('#regUsername').val();
        const email = $('#regEmail').val();
        const fullname = $('#regFullname').val();
        const password = $('#regPassword').val();
        const confirmPassword = $('#regConfirmPassword').val();
        const termsAgreed = $('#termsAgree').is(':checked');
        
        // Simple validation
        if (!username || !email || !fullname || !password || !confirmPassword) {
            showNotification('Please fill in all fields', 'error');
            return;
        }
        
        if (!termsAgreed) {
            showNotification('Please agree to the terms and conditions', 'error');
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
        
        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Creating account...');
        
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
                        submitBtn.prop('disabled', false).html(originalText);
                    }
                } catch (e) {
                    showNotification('Invalid server response', 'error');
                    submitBtn.prop('disabled', false).html(originalText);
                }
            },
            error: function(xhr, status, error) {
                showNotification('An error occurred during registration: ' + error, 'error');
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Forgot password functionality
    $('#forgotPasswordLink').on('click', function(e) {
        e.preventDefault();
        $('#forgotPasswordModal').css('display', 'flex');
    });
    
    $('.close').on('click', function() {
        $('#forgotPasswordModal').css('display', 'none');
    });
    
    $(window).on('click', function(e) {
        if ($(e.target).is('#forgotPasswordModal')) {
            $('#forgotPasswordModal').css('display', 'none');
        }
    });
    
    $('#forgotPasswordForm').on('submit', function(e) {
        e.preventDefault();
        const email = $('#resetEmail').val();
        
        if (!email) {
            showNotification('Please enter your email', 'error');
            return;
        }
        
        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sending...');
        
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
                        $('#forgotPasswordModal').css('display', 'none');
                        $('#forgotPasswordForm')[0].reset();
                    } else {
                        showNotification(data.message, 'error');
                    }
                } catch (e) {
                    showNotification('Invalid server response', 'error');
                }
                submitBtn.prop('disabled', false).html(originalText);
            },
            error: function(xhr, status, error) {
                showNotification('An error occurred: ' + error, 'error');
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Social login buttons
    $('.btn-social').on('click', function() {
        const provider = $(this).hasClass('google') ? 'google' : 'microsoft';
        showNotification(`${provider.charAt(0).toUpperCase() + provider.slice(1)} login is not implemented yet`, 'warning');
    });
});

function checkAuthStatus() {
    const user = localStorage.getItem('user');
    if (user && window.location.pathname.includes('login.html')) {
        window.location.href = 'dashboard.html';
    }
}

function checkPasswordStrength(password) {
    let strength = 0;
    const strengthBar = $('.strength-bar');
    const strengthText = $('.strength-text');
    
    if (password.length > 7) strength++;
    if (password.match(/[a-z]+/)) strength++;
    if (password.match(/[A-Z]+/)) strength++;
    if (password.match(/[0-9]+/)) strength++;
    if (password.match(/[!@#$%^&*(),.?":{}|<>]+/)) strength++;
    
    switch(strength) {
        case 0:
        case 1:
            strengthBar.css('width', '20%').css('background', 'var(--error)');
            strengthText.text('Weak').css('color', 'var(--error)');
            break;
        case 2:
            strengthBar.css('width', '40%').css('background', 'var(--warning)');
            strengthText.text('Fair').css('color', 'var(--warning)');
            break;
        case 3:
            strengthBar.css('width', '60%').css('background', 'var(--warning)');
            strengthText.text('Good').css('color', 'var(--warning)');
            break;
        case 4:
            strengthBar.css('width', '80%').css('background', 'var(--success)');
            strengthText.text('Strong').css('color', 'var(--success)');
            break;
        case 5:
            strengthBar.css('width', '100%').css('background', 'var(--success)');
            strengthText.text('Very Strong').css('color', 'var(--success)');
            break;
    }
}

function showNotification(message, type) {
    // Remove any existing notifications
    $('.notification').remove();
    
    const icon = type === 'success' ? 'fa-check-circle' :
                 type === 'error' ? 'fa-exclamation-circle' :
                 'fa-info-circle';
    
    const notification = $(`
        <div class="notification ${type}">
            <i class="fas ${icon}"></i>
            ${message}
        </div>
    `);
    
    $('body').append(notification);
    
    // Show notification
    setTimeout(() => {
        notification.addClass('show');
    }, 10);
    
    // Hide after 5 seconds
    setTimeout(() => {
        notification.removeClass('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 5000);
}
