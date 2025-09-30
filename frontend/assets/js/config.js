/**
 * Application Configuration
 */
const APP_CONFIG = {
    // API Configuration - Always use relative URL for proxy compatibility
    API_BASE_URL: '/api',
    API_TIMEOUT: 30000, // Increased for corporate proxy environments
    
    // Authentication
    TOKEN_KEY: 'bus_booking_token',
    USER_DATA_KEY: 'bus_booking_user',
    
    // Booking settings
    WORKER_ID_MIN_LENGTH: 7,
    WORKER_ID_MAX_LENGTH: 10,
    BOOKING_CUTOFF_MINUTES: 15,
    MAX_ADVANCE_BOOKING_DAYS: 1,
    
    // UI Settings
    TOAST_DURATION: 5000,
    POLLING_INTERVAL: 5000, // 5 seconds for real-time updates (corporate proxy friendly)
    
    // Pure Online System - No PWA/Offline features
    NETWORK_TIMEOUT: 30000, // Extended timeout for corporate proxies
    CACHE_URLS: [
        '/',
        '/index.html',
        '/manifest.json',
        '/assets/css/styles.css',
        '/assets/js/config.js',
        '/assets/js/utils.js',
        '/assets/js/api.js',
        '/assets/js/components.js',
        '/assets/js/app.js'
    ],
    
    // Schedule types
    SCHEDULE_TYPES: {
        MORNING: 'morning',
        EVENING: 'evening'
    },
    
    // Booking statuses
    BOOKING_STATUS: {
        CONFIRMED: 'confirmed',
        WAITLISTED: 'waitlisted',
        CANCELLED: 'cancelled',
        NO_BOOKING: 'no_booking'
    },
    
    // Toast types
    TOAST_TYPES: {
        SUCCESS: 'success',
        ERROR: 'error',
        WARNING: 'warning',
        INFO: 'info'
    },
    
    // Error codes
    ERROR_CODES: {
        NETWORK_ERROR: 'NETWORK_ERROR',
        VALIDATION_ERROR: 'VALIDATION_ERROR',
        AUTHENTICATION_ERROR: 'AUTHENTICATION_ERROR',
        BOOKING_ERROR: 'BOOKING_ERROR',
        SERVER_ERROR: 'SERVER_ERROR'
    },
    
    // Local storage keys
    STORAGE_KEYS: {
        WORKER_ID: 'last_worker_id',
        THEME: 'preferred_theme',
        NOTIFICATIONS: 'notifications_enabled',
        INSTALL_DISMISSED: 'install_prompt_dismissed'
    },
    
    // Default values
    DEFAULTS: {
        THEME: 'light',
        LANGUAGE: 'en',
        NOTIFICATIONS_ENABLED: true
    }
};

// Environment-specific overrides - Always use relative paths for Docker
APP_CONFIG.DEBUG = true; // Enable debugging for troubleshooting
// API_BASE_URL remains '/api' for relative paths within Docker network

// Freeze configuration to prevent accidental modifications
Object.freeze(APP_CONFIG);

// Export for modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = APP_CONFIG;
}