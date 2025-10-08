$(document).ready(function() {
    // Toggle between sign in and sign up forms
    const signUpButton = document.getElementById('signUp');
    const signInButton = document.getElementById('signIn');
    const container = document.getElementById('container');

    signUpButton.addEventListener('click', () => {
        container.classList.add('right-panel-active');
    });

    signInButton.addEventListener('click', () => {
        container.classList.remove('right-panel-active');
    });

    // Handle login form submission
    $('#loginForm').on('submit', function(e) {
        e.preventDefault();
        const username = $('#loginUsername').val();
        const password = $('#loginPassword').val();
        
        // Simple validation
        if (!username || !password) {
            alert('Please fill in all fields');
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
                const data = JSON.parse(response);
                if (data.success) {
                    // Store user data in localStorage
                    localStorage.setItem('user', JSON.stringify(data.user));
                    // Show dashboard
                    $('#container').addClass('hidden');
                    $('#dashboard').removeClass('hidden');
                    // Load user data
                    loadUserData();
                    loadAttendanceStats();
                    loadRecentActivity();
                } else {
                    alert(data.message);
                }
            },
            error: function() {
                alert('An error occurred during login');
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
        
        // Simple validation
        if (!username || !email || !fullname || !password || !confirmPassword) {
            alert('Please fill in all fields');
            return;
        }
        
        if (password !== confirmPassword) {
            alert('Passwords do not match');
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
                const data = JSON.parse(response);
                if (data.success) {
                    alert('Registration successful! Please login.');
                    container.classList.remove('right-panel-active');
                    // Clear form
                    $('#registerForm')[0].reset();
                } else {
                    alert(data.message);
                }
            },
            error: function() {
                alert('An error occurred during registration');
            }
        });
    });

    // Navigation
    $('.nav-links a').on('click', function(e) {
        e.preventDefault();
        const section = $(this).data('section');
        
        // Update active link
        $('.nav-links a').removeClass('active');
        $(this).addClass('active');
        
        // Show corresponding section
        $('.section').removeClass('active');
        $(`#${section}-section`).addClass('active');
        
        // Load section-specific data
        if (section === 'history') {
            loadAttendanceHistory();
        }
    });

    // Logout
    $('#logoutBtn').on('click', function() {
        $.ajax({
            url: 'php/logout.php',
            method: 'POST',
            success: function() {
                localStorage.removeItem('user');
                $('#dashboard').addClass('hidden');
                $('#container').removeClass('hidden');
                // Clear forms
                $('#loginForm')[0].reset();
            }
        });
    });

    // Profile form submission
    $('#profileForm').on('submit', function(e) {
        e.preventDefault();
        // Implementation for profile update would go here
        alert('Profile update functionality would be implemented here');
    });

    // Load user data
    function loadUserData() {
        const user = JSON.parse(localStorage.getItem('user'));
        if (user) {
            $('#userFullname').text(user.full_name);
            $('#profileFullname').text(user.full_name);
            $('#profileUsername').text('@' + user.username);
            $('#profileEmail').text(user.email);
            $('#profileFullName').val(user.full_name);
            $('#profileEmailInput').val(user.email);
        }
    }

    // Load attendance statistics
    function loadAttendanceStats() {
        const user = JSON.parse(localStorage.getItem('user'));
        if (user) {
            $.ajax({
                url: 'php/get_attendance.php',
                method: 'POST',
                data: {
                    action: 'stats',
                    user_id: user.id
                },
                success: function(response) {
                    const data = JSON.parse(response);
                    if (data.success) {
                        $('#totalPresent').text(data.stats.present);
                        $('#totalLate').text(data.stats.late);
                        $('#totalAbsent').text(data.stats.absent);
                    }
                }
            });
        }
    }

    // Load recent activity
    function loadRecentActivity() {
        const user = JSON.parse(localStorage.getItem('user'));
        if (user) {
            $.ajax({
                url: 'php/get_attendance.php',
                method: 'POST',
                data: {
                    action: 'recent',
                    user_id: user.id,
                    limit: 5
                },
                success: function(response) {
                    const data = JSON.parse(response);
                    if (data.success) {
                        let html = '';
                        data.attendance.forEach(item => {
                            const date = new Date(item.timestamp);
                            html += `
                                <div class="activity-item">
                                    <div class="activity-info">
                                        <h4>Attendance Marked</h4>
                                        <p>Status: ${item.status}</p>
                                    </div>
                                    <div class="activity-time">
                                        ${date.toLocaleDateString()} ${date.toLocaleTimeString()}
                                    </div>
                                </div>
                            `;
                        });
                        $('#recentActivity').html(html);
                    }
                }
            });
        }
    }

    // Load attendance history
    function loadAttendanceHistory() {
        const user = JSON.parse(localStorage.getItem('user'));
        const filter = $('#historyFilter').val();
        
        if (user) {
            $.ajax({
                url: 'php/get_attendance.php',
                method: 'POST',
                data: {
                    action: 'history',
                    user_id: user.id,
                    filter: filter
                },
                success: function(response) {
                    const data = JSON.parse(response);
                    if (data.success) {
                        let html = '';
                        data.attendance.forEach(item => {
                            const date = new Date(item.timestamp);
                            html += `
                                <tr>
                                    <td>${date.toLocaleDateString()}</td>
                                    <td>${date.toLocaleTimeString()}</td>
                                    <td>${item.status}</td>
                                    <td>${item.latitude}, ${item.longitude}</td>
                                </tr>
                            `;
                        });
                        $('#attendanceHistory').html(html);
                    }
                }
            });
        }
    }

    // Filter change
    $('#historyFilter').on('change', function() {
        loadAttendanceHistory();
    });
});
