// Dashboard specific functionality
class Dashboard {
    constructor() {
        this.init();
    }
    
    init() {
        this.loadDashboardData();
        this.setupQuickActions();
    }
    
    async loadDashboardData() {
        try {
            // Load user data
            const userResponse = await fetch('php/get_user_data.php');
            const userResult = await userResponse.json();
            
            if (userResult.success) {
                this.updateWelcomeMessage(userResult.data.user);
            }
            
            // Load attendance data
            const attendanceResponse = await fetch('php/get_attendance.php?limit=5');
            const attendanceResult = await attendanceResponse.json();
            
            if (attendanceResult.success) {
                this.updateRecentActivity(attendanceResult.data.attendance);
                this.updateStats(attendanceResult.data);
            }
            
        } catch (error) {
            console.error('Error loading dashboard data:', error);
        }
    }
    
    updateWelcomeMessage(user) {
        const welcomeElement = document.getElementById('welcomeMessage');
        if (welcomeElement) {
            const now = new Date();
            const hour = now.getHours();
            let greeting = 'Good morning';
            
            if (hour >= 12 && hour < 18) {
                greeting = 'Good afternoon';
            } else if (hour >= 18) {
                greeting = 'Good evening';
            }
            
            welcomeElement.innerHTML = `
                <h1>${greeting}, ${user.full_name}!</h1>
                <p>Welcome back to your attendance dashboard</p>
            `;
        }
    }
    
    updateRecentActivity(attendance) {
        const activityContainer = document.getElementById('recentActivity');
        if (!activityContainer) return;
        
        if (attendance.length === 0) {
            activityContainer.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <p>No attendance records yet</p>
                    <a href="attendance.html" class="btn btn-primary">Mark Attendance</a>
                </div>
            `;
            return;
        }
        
        activityContainer.innerHTML = attendance.map(record => `
            <div class="activity-item">
                <div class="activity-icon">
                    <i class="fas fa-${record.status === 'Present' ? 'check' : 'clock'}"></i>
                </div>
                <div class="activity-content">
                    <h4>${record.location_name}</h4>
                    <p>${new Date(record.check_in).toLocaleDateString()} at ${new Date(record.check_in).toLocaleTimeString()}</p>
                    <span class="status-badge ${record.status.toLowerCase()}">${record.status}</span>
                </div>
            </div>
        `).join('');
    }
    
    updateStats(data) {
        // Update various stats on the dashboard
        const totalElement = document.getElementById('totalAttendance');
        const presentElement = document.getElementById('presentCount');
        const lateElement = document.getElementById('lateCount');
        
        if (totalElement) totalElement.textContent = data.pagination.total;
        
        // These would require additional database queries for accurate counts
        // For now, we'll use placeholder values
        if (presentElement) presentElement.textContent = Math.floor(data.pagination.total * 0.8);
        if (lateElement) lateElement.textContent = Math.floor(data.pagination.total * 0.2);
    }
    
    setupQuickActions() {
        const quickMarkBtn = document.getElementById('quickMarkAttendance');
        if (quickMarkBtn) {
            quickMarkBtn.addEventListener('click', () => {
                window.location.href = 'attendance.html';
            });
        }
        
        const viewHistoryBtn = document.getElementById('viewHistory');
        if (viewHistoryBtn) {
            viewHistoryBtn.addEventListener('click', () => {
                window.location.href = 'history.html';
            });
        }
    }
}

// Initialize dashboard
document.addEventListener('DOMContentLoaded', () => {
    if (window.location.pathname.includes('dashboard.html')) {
        window.dashboard = new Dashboard();
    }
});