// Profile page functionality
class ProfileSystem {
    constructor() {
        this.init();
    }
    
    init() {
        this.setupProfileForm();
        this.loadProfileData();
    }
    
    setupProfileForm() {
        const profileForm = document.getElementById('profileForm');
        if (profileForm) {
            profileForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.updateProfile();
            });
        }
    }
    
    async loadProfileData() {
        try {
            const response = await fetch('php/get_user_data.php');
            const result = await response.json();
            
            if (result.success) {
                this.populateProfileForm(result.data.user);
            }
        } catch (error) {
            this.showNotification('Error loading profile data', 'error');
        }
    }
    
    populateProfileForm(userData) {
        const form = document.getElementById('profileForm');
        if (!form) return;
        
        Object.keys(userData).forEach(key => {
            const input = form.querySelector(`[name="${key}"]`);
            if (input && userData[key] !== null) {
                input.value = userData[key];
            }
        });
    }
    
    async updateProfile() {
        const formData = new FormData(document.getElementById('profileForm'));
        const data = Object.fromEntries(formData);
        
        try {
            const response = await fetch('php/update_profile.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showNotification('Profile updated successfully!', 'success');
                
                // Update user data in localStorage
                const user = JSON.parse(localStorage.getItem('user'));
                if (user) {
                    user.full_name = data.full_name;
                    localStorage.setItem('user', JSON.stringify(user));
                    
                    // Update UI
                    const userNameElements = document.querySelectorAll('.user-info.user-name');
                    userNameElements.forEach(element => {
                        element.textContent = data.full_name;
                    });
                }
            } else {
                this.showNotification(result.message, 'error');
            }
        } catch (error) {
            this.showNotification('An error occurred. Please try again.', 'error');
        }
    }
    
    showNotification(message, type) {
        // Remove existing notifications
        const existingNotifications = document.querySelectorAll('.notification');
        existingNotifications.forEach(notification => notification.remove());
        
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <span>${message}</span>
            <button onclick="this.parentElement.remove()">&times;</button>
        `;
        
        document.body.appendChild(notification);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }
}

// Initialize profile system
document.addEventListener('DOMContentLoaded', () => {
    if (window.location.pathname.includes('profile.html')) {
        window.profileSystem = new ProfileSystem();
    }
});