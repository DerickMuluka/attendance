// Enhanced Attendance functionality
class AttendanceSystem {
    constructor() {
        this.userLocation = null;
        this.currentStep = 1;
        this.qrData = null;
        this.qrScanner = null;
        this.init();
    }
    
    init() {
        this.initStepper();
        this.setupEventListeners();
        this.checkGeolocationSupport();
        this.initQRScanner();
    }
    
    initStepper() {
        // Show first step, hide others
        this.showStep(1);
        this.updateStepper();
    }
    
    setupEventListeners() {
        // Get location button
        $('#getLocationBtn').on('click', () => this.getLocation());
        
        // Manual QR entry
        $('#manualEntryBtn').on('click', () => this.manualQRInput());
        
        // Navigation buttons
        $('#nextStepBtn').on('click', () => this.nextStep());
        $('#prevStepBtn').on('click', () => this.prevStep());
        
        // Confirm attendance
        $('#confirmAttendanceBtn').on('click', () => this.markAttendance());
        
        // Edit attendance details
        $('#editAttendanceBtn').on('click', () => this.showStep(1));
    }
    
    checkGeolocationSupport() {
        if (!navigator.geolocation) {
            $('#locationStatus').html('<i class="fas fa-exclamation-triangle"></i> Geolocation is not supported by this browser.').addClass('error');
            $('#getLocationBtn').prop('disabled', true);
        }
    }
    
    async getLocation() {
        try {
            $('#locationStatus').html('<i class="fas fa-sync fa-spin"></i> Getting location...').removeClass('error success').addClass('warning');
            $('#getLocationBtn').prop('disabled', true).html('<i class="fas fa-sync fa-spin"></i> Getting Location...');
            
            const location = await geolocationService.getCurrentPosition();
            this.userLocation = location;
            
            $('#locationStatus').html('<i class="fas fa-check-circle"></i> Location retrieved successfully').removeClass('warning error').addClass('success');
            $('#getLocationBtn').prop('disabled', false).html('<i class="fas fa-location-arrow"></i> Get My Location');
            
            // Show coordinates
            $('#latitudeValue').text(location.latitude.toFixed(6));
            $('#longitudeValue').text(location.longitude.toFixed(6));
            $('#accuracyValue').text(`${location.accuracy.toFixed(2)} meters`);
            $('#locationCoordinates').show();
            
            // Enable next step if QR is also scanned
            if (this.qrData) {
                $('#nextStepBtn').prop('disabled', false);
            }
            
        } catch (error) {
            $('#locationStatus').html(`<i class="fas fa-exclamation-circle"></i> ${error.message}`).removeClass('warning success').addClass('error');
            $('#getLocationBtn').prop('disabled', false).html('<i class="fas fa-location-arrow"></i> Get My Location');
            this.showNotification(error.message, 'error');
        }
    }
    
    initQRScanner() {
        try {
            this.qrScanner = new QRCodeScanner('qr-reader', {
                onResult: (qrData) => this.processQRCode(qrData),
                onError: (error) => {
                    $('#qr-result').html(`
                        <div class="qr-error">
                            <i class="fas fa-times-circle"></i>
                            <p>${error}</p>
                            <button id="manualEntryBtn" class="btn btn-primary mt-2">
                                <i class="fas fa-keyboard"></i> Enter Code Manually
                            </button>
                        </div>
                    `);
                }
            });
            
            this.qrScanner.start();
            
        } catch (error) {
            $('#qr-reader').html(`
                <div class="qr-error">
                    <i class="fas fa-times-circle"></i>
                    <p>QR Scanner initialization failed: ${error.message}</p>
                    <button id="manualEntryBtn" class="btn btn-primary">
                        <i class="fas fa-keyboard"></i> Enter Code Manually
                    </button>
                </div>
            `);
        }
    }
    
    processQRCode(qrData) {
        try {
            // Validate QR data format
            const qrInfo = JSON.parse(qrData);
            
            if (!qrInfo.type || qrInfo.type !== 'attendance') {
                throw new Error('Invalid QR code format');
            }
            
            $('#qr-result').html(`
                <div class="qr-success">
                    <i class="fas fa-check-circle"></i>
                    <p>QR Code successfully scanned!</p>
                    <small>Location: ${qrInfo.locationName || 'Unknown Location'}</small>
                </div>
            `);
            
            this.qrData = qrData;
            
            // Enable next step if location is also available
            if (this.userLocation) {
                $('#nextStepBtn').prop('disabled', false);
            }
            
        } catch (error) {
            $('#qr-result').html(`
                <div class="qr-error">
                    <i class="fas fa-times-circle"></i>
                    <p>Invalid QR code: ${error.message}</p>
                </div>
            `);
        }
    }
    
    manualQRInput() {
        const qrData = prompt('Please enter the QR code data manually:');
        if (qrData) {
            this.processQRCode(qrData);
        }
    }
    
    showStep(step) {
        // Hide all steps
        $('.attendance-step').hide();
        
        // Show current step
        $(`#step${step}`).show();
        
        // Update current step
        this.currentStep = step;
        
        // Update navigation
        this.updateStepper();
        
        // Prepare step-specific content
        if (step === 3) {
            this.prepareConfirmation();
        }
    }
    
    nextStep() {
        if (this.validateStep(this.currentStep)) {
            this.showStep(this.currentStep + 1);
        }
    }
    
    prevStep() {
        this.showStep(this.currentStep - 1);
    }
    
    validateStep(step) {
        switch(step) {
            case 1:
                if (!this.userLocation) {
                    this.showNotification('Please get your location first', 'error');
                    return false;
                }
                return true;
            case 2:
                if (!this.qrData) {
                    this.showNotification('Please scan a valid QR code', 'error');
                    return false;
                }
                return true;
            default:
                return true;
        }
    }
    
    updateStepper() {
        // Update navigation buttons
        $('#prevStepBtn').prop('disabled', this.currentStep === 1);
        
        if (this.currentStep === 3) {
            $('#nextStepBtn').hide();
        } else {
            $('#nextStepBtn').show().text(this.currentStep === 2 ? 'Confirm' : 'Next');
        }
    }
    
    prepareConfirmation() {
        if (!this.userLocation || !this.qrData) return;
        
        const now = new Date();
        const qrInfo = JSON.parse(this.qrData);
        
        // Determine status based on current time
        const currentTime = now.toTimeString().split(' ')[0];
        const status = currentTime > '09:15:00' ? 'Late' : 'Present';
        
        $('#confirmDateTime').text(now.toLocaleString());
        $('#confirmLocation').text(`${this.userLocation.latitude.toFixed(6)}, ${this.userLocation.longitude.toFixed(6)}`);
        $('#confirmQR').text(qrInfo.locationName || 'Unknown Location');
        $('#confirmStatus').text(status).removeClass('present late').addClass(status.toLowerCase());
    }
    
    async markAttendance() {
        if (!this.userLocation || !this.qrData) {
            this.showNotification('Please complete all steps first', 'error');
            return;
        }
        
        const user = JSON.parse(localStorage.getItem('user'));
        if (!user) {
            this.showNotification('Please login first', 'error');
            window.location.href = 'login.html';
            return;
        }
        
        try {
            $('#confirmAttendanceBtn').prop('disabled', true).html('<i class="fas fa-sync fa-spin"></i> Marking Attendance...');
            
            const response = await fetch('php/mark_attendance.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: user.id,
                    qr_data: this.qrData,
                    latitude: this.userLocation.latitude,
                    longitude: this.userLocation.longitude,
                    accuracy: this.userLocation.accuracy
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showNotification('Attendance marked successfully!', 'success');
                
                // Reset form
                this.resetAttendanceForm();
                
                // Redirect to dashboard after a brief delay
                setTimeout(() => {
                    window.location.href = 'dashboard.html';
                }, 2000);
            } else {
                this.showNotification(result.message, 'error');
                $('#confirmAttendanceBtn').prop('disabled', false).html('<i class="fas fa-check"></i> Confirm Attendance');
            }
        } catch (error) {
            this.showNotification('An error occurred: ' + error.message, 'error');
            $('#confirmAttendanceBtn').prop('disabled', false).html('<i class="fas fa-check"></i> Confirm Attendance');
        }
    }
    
    resetAttendanceForm() {
        this.userLocation = null;
        this.qrData = null;
        this.currentStep = 1;
        
        $('#locationStatus').html('<i class="fas fa-info-circle"></i> Waiting for location access...').removeClass('success error warning');
        $('#locationCoordinates').hide();
        $('#qr-result').empty();
        
        if (this.qrScanner) {
            this.qrScanner.stop();
        }
        
        this.initStepper();
    }
    
    showNotification(message, type) {
        // Remove existing notifications
        $('.notification').remove();
        
        const icon = type === 'success' ? 'fa-check-circle' : 
                     type === 'error' ? 'fa-exclamation-circle' : 
                     type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
        
        const notification = $(`
            <div class="notification ${type}">
                <i class="fas ${icon}"></i>
                ${message}
            </div>
        `);
        
        $('body').append(notification);
        
        // Show notification with animation
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
}

// Initialize attendance system
document.addEventListener('DOMContentLoaded', () => {
    window.attendanceSystem = new AttendanceSystem();
});