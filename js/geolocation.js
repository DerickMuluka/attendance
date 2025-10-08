// Enhanced geolocation functionality
class GeolocationService {
    constructor() {
        this.currentLocation = null;
        this.watchId = null;
    }
    
    async getCurrentPosition(options = {}) {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                reject(new Error('Geolocation is not supported by this browser'));
                return;
            }
            
            const defaultOptions = {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            };
            
            const finalOptions = { ...defaultOptions, ...options };
            
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.currentLocation = {
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        accuracy: position.coords.accuracy,
                        altitude: position.coords.altitude,
                        altitudeAccuracy: position.coords.altitudeAccuracy,
                        heading: position.coords.heading,
                        speed: position.coords.speed,
                        timestamp: position.timestamp
                    };
                    resolve(this.currentLocation);
                },
                (error) => {
                    reject(this.getGeolocationError(error));
                },
                finalOptions
            );
        });
    }
    
    watchPosition(callback, options = {}) {
        if (!navigator.geolocation) {
            throw new Error('Geolocation is not supported by this browser');
        }
        
        const defaultOptions = {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        };
        
        const finalOptions = { ...defaultOptions, ...options };
        
        this.watchId = navigator.geolocation.watchPosition(
            (position) => {
                this.currentLocation = {
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    accuracy: position.coords.accuracy,
                    altitude: position.coords.altitude,
                    altitudeAccuracy: position.coords.altitudeAccuracy,
                    heading: position.coords.heading,
                    speed: position.coords.speed,
                    timestamp: position.timestamp
                };
                callback(this.currentLocation);
            },
            (error) => {
                callback(null, this.getGeolocationError(error));
            },
            finalOptions
        );
        
        return this.watchId;
    }
    
    stopWatching() {
        if (this.watchId !== null) {
            navigator.geolocation.clearWatch(this.watchId);
            this.watchId = null;
        }
    }
    
    getGeolocationError(error) {
        switch(error.code) {
            case error.PERMISSION_DENIED:
                return new Error('Location access denied. Please enable location permissions in your browser settings.');
            case error.POSITION_UNAVAILABLE:
                return new Error('Location information is unavailable. Please check your connection and try again.');
            case error.TIMEOUT:
                return new Error('Location request timed out. Please try again.');
            case error.UNKNOWN_ERROR:
                return new Error('An unknown error occurred while getting your location.');
            default:
                return new Error('An error occurred while getting your location.');
        }
    }
    
    calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371000; // Earth's radius in meters
        const dLat = this.deg2rad(lat2 - lat1);
        const dLon = this.deg2rad(lon2 - lon1);
        
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                 Math.cos(this.deg2rad(lat1)) * Math.cos(this.deg2rad(lat2)) *
                 Math.sin(dLon/2) * Math.sin(dLon/2);
        
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        const distance = R * c; // Distance in meters
        
        return distance;
    }
    
    deg2rad(deg) {
        return deg * (Math.PI/180);
    }
    
    isWithinRadius(targetLat, targetLon, radius) {
        if (!this.currentLocation) {
            return false;
        }
        
        const distance = this.calculateDistance(
            this.currentLocation.latitude,
            this.currentLocation.longitude,
            targetLat,
            targetLon
        );
        
        return distance <= radius;
    }
}

// Initialize geolocation service
window.geolocationService = new GeolocationService();