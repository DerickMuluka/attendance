// Theme management functionality

document.addEventListener('DOMContentLoaded', function() {
    initTheme();
});

function initTheme() {
    const themeToggle = document.getElementById('themeToggle');
    const savedTheme = localStorage.getItem('theme') || 'auto';
    
    // Apply saved theme or system preference
    applyTheme(savedTheme);
    
    if (themeToggle) {
        // Set initial icon based on current theme
        updateThemeIcon(getEffectiveTheme());
        
        themeToggle.addEventListener('click', function() {
            toggleTheme();
        });
    }
}

function toggleTheme() {
    const currentTheme = localStorage.getItem('theme') || 'auto';
    let newTheme;
    
    // Cycle through themes: auto -> light -> dark -> auto
    if (currentTheme === 'auto') {
        newTheme = 'light';
    } else if (currentTheme === 'light') {
        newTheme = 'dark';
    } else {
        newTheme = 'auto';
    }
    
    applyTheme(newTheme);
    localStorage.setItem('theme', newTheme);
    
    // Save preference to server if user is logged in
    if (window.AttendanceSystem?.isLoggedIn()) {
        saveThemePreference(newTheme);
    }
}

function applyTheme(theme) {
    const html = document.documentElement;
    const effectiveTheme = theme === 'auto' ? getSystemTheme() : theme;
    
    html.setAttribute('data-theme', effectiveTheme);
    updateThemeIcon(effectiveTheme);
}

function getSystemTheme() {
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function getEffectiveTheme() {
    const savedTheme = localStorage.getItem('theme') || 'auto';
    return savedTheme === 'auto' ? getSystemTheme() : savedTheme;
}

function updateThemeIcon(theme) {
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        themeToggle.innerHTML = theme === 'dark' ? 
            '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
    }
}

function saveThemePreference(theme) {
    const userData = JSON.parse(localStorage.getItem('userData') || '{}');
    
    fetch('php/save_preferences.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + localStorage.getItem('authToken')
        },
        body: JSON.stringify({
            theme: theme
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update local storage with new preferences
            const preferences = JSON.parse(localStorage.getItem('userPreferences') || '{}');
            preferences.theme = theme;
            localStorage.setItem('userPreferences', JSON.stringify(preferences));
        }
    })
    .catch(error => {
        console.error('Error saving theme preference:', error);
    });
}

// Listen for system theme changes
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
    const currentTheme = localStorage.getItem('theme') || 'auto';
    if (currentTheme === 'auto') {
        applyTheme('auto');
    }
});

// Export theme functions
window.ThemeManager = {
    toggleTheme,
    getEffectiveTheme,
    applyTheme
};