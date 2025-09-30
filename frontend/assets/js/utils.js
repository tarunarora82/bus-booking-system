/**
 * Utility Functions
 */
const Utils = {
    /**
     * Validate worker ID format
     * @param {string} workerId 
     * @returns {boolean}
     */
    isValidWorkerId(workerId) {
        if (!workerId || typeof workerId !== 'string') {
            return false;
        }
        
        const regex = /^[0-9]{7,10}$/;
        return regex.test(workerId.trim());
    },

    /**
     * Format date to YYYY-MM-DD
     * @param {Date} date 
     * @returns {string}
     */
    formatDate(date) {
        if (!(date instanceof Date)) {
            date = new Date(date);
        }
        
        return date.toISOString().split('T')[0];
    },

    /**
     * Format time to HH:MM
     * @param {string} time 
     * @returns {string}
     */
    formatTime(time) {
        if (!time) return '';
        
        const [hours, minutes] = time.split(':');
        return `${hours.padStart(2, '0')}:${minutes.padStart(2, '0')}`;
    },

    /**
     * Get today's date in YYYY-MM-DD format
     * @returns {string}
     */
    getTodayDate() {
        return this.formatDate(new Date());
    },

    /**
     * Get tomorrow's date in YYYY-MM-DD format
     * @returns {string}
     */
    getTomorrowDate() {
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        return this.formatDate(tomorrow);
    },

    /**
     * Check if date is today
     * @param {string} date 
     * @returns {boolean}
     */
    isToday(date) {
        return date === this.getTodayDate();
    },

    /**
     * Get time until booking cutoff
     * @param {string} departureTime 
     * @param {string} date 
     * @returns {number} Minutes until cutoff
     */
    getTimeUntilCutoff(departureTime, date) {
        const now = new Date();
        const departureDateTime = new Date(`${date} ${departureTime}`);
        const cutoffDateTime = new Date(departureDateTime.getTime() - (APP_CONFIG.BOOKING_CUTOFF_MINUTES * 60000));
        
        return Math.max(0, Math.floor((cutoffDateTime.getTime() - now.getTime()) / 60000));
    },

    /**
     * Check if booking is still allowed
     * @param {string} departureTime 
     * @param {string} date 
     * @returns {boolean}
     */
    canBookSlot(departureTime, date) {
        return this.getTimeUntilCutoff(departureTime, date) > 0;
    },

    /**
     * Debounce function calls
     * @param {Function} func 
     * @param {number} wait 
     * @returns {Function}
     */
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    /**
     * Throttle function calls
     * @param {Function} func 
     * @param {number} limit 
     * @returns {Function}
     */
    throttle(func, limit) {
        let inThrottle;
        return function executedFunction(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },

    /**
     * Deep clone an object
     * @param {Object} obj 
     * @returns {Object}
     */
    deepClone(obj) {
        if (obj === null || typeof obj !== 'object') {
            return obj;
        }
        
        if (obj instanceof Date) {
            return new Date(obj.getTime());
        }
        
        if (obj instanceof Array) {
            return obj.map(item => this.deepClone(item));
        }
        
        const cloned = {};
        for (let key in obj) {
            if (obj.hasOwnProperty(key)) {
                cloned[key] = this.deepClone(obj[key]);
            }
        }
        
        return cloned;
    },

    /**
     * Generate unique ID
     * @returns {string}
     */
    generateId() {
        return Date.now().toString(36) + Math.random().toString(36).substr(2);
    },

    /**
     * Get element by ID with error handling
     * @param {string} id 
     * @returns {HTMLElement|null}
     */
    getElementById(id) {
        const element = document.getElementById(id);
        if (!element) {
            console.warn(`Element with ID '${id}' not found`);
        }
        return element;
    },

    /**
     * Create DOM element with attributes
     * @param {string} tag 
     * @param {Object} attributes 
     * @param {string} textContent 
     * @returns {HTMLElement}
     */
    createElement(tag, attributes = {}, textContent = '') {
        const element = document.createElement(tag);
        
        Object.keys(attributes).forEach(key => {
            if (key === 'className') {
                element.className = attributes[key];
            } else if (key === 'innerHTML') {
                element.innerHTML = attributes[key];
            } else {
                element.setAttribute(key, attributes[key]);
            }
        });
        
        if (textContent) {
            element.textContent = textContent;
        }
        
        return element;
    },

    /**
     * Add event listener with cleanup
     * @param {HTMLElement} element 
     * @param {string} event 
     * @param {Function} handler 
     * @returns {Function} Cleanup function
     */
    addEventListener(element, event, handler) {
        element.addEventListener(event, handler);
        return () => element.removeEventListener(event, handler);
    },

    /**
     * Local storage helpers
     */
    storage: {
        set(key, value) {
            try {
                localStorage.setItem(key, JSON.stringify(value));
                return true;
            } catch (error) {
                console.error('Error saving to localStorage:', error);
                return false;
            }
        },

        get(key, defaultValue = null) {
            try {
                const item = localStorage.getItem(key);
                return item ? JSON.parse(item) : defaultValue;
            } catch (error) {
                console.error('Error reading from localStorage:', error);
                return defaultValue;
            }
        },

        remove(key) {
            try {
                localStorage.removeItem(key);
                return true;
            } catch (error) {
                console.error('Error removing from localStorage:', error);
                return false;
            }
        },

        clear() {
            try {
                localStorage.clear();
                return true;
            } catch (error) {
                console.error('Error clearing localStorage:', error);
                return false;
            }
        }
    },

    /**
     * URL helpers
     */
    url: {
        getParams() {
            const params = new URLSearchParams(window.location.search);
            const result = {};
            for (let [key, value] of params) {
                result[key] = value;
            }
            return result;
        },

        setParam(key, value) {
            const url = new URL(window.location);
            url.searchParams.set(key, value);
            window.history.replaceState({}, '', url);
        },

        removeParam(key) {
            const url = new URL(window.location);
            url.searchParams.delete(key);
            window.history.replaceState({}, '', url);
        }
    },

    /**
     * Device detection
     */
    device: {
        isMobile() {
            return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        },

        isTablet() {
            return /iPad|Android(?!.*Mobile)/i.test(navigator.userAgent);
        },

        isDesktop() {
            return !this.isMobile() && !this.isTablet();
        },

        supportsPWA() {
            return 'serviceWorker' in navigator && 'manifesto' in window;
        },

        supportsNotifications() {
            return 'Notification' in window;
        },

        isOnline() {
            return navigator.onLine;
        }
    },

    /**
     * Network helpers
     */
    network: {
        async checkConnectivity() {
            try {
                const response = await fetch('/api/health', {
                    method: 'GET',
                    cache: 'no-cache'
                });
                return response.ok;
            } catch (error) {
                return false;
            }
        },

        getConnectionType() {
            if ('connection' in navigator) {
                return navigator.connection.effectiveType || 'unknown';
            }
            return 'unknown';
        }
    },

    /**
     * Error handling
     */
    error: {
        log(error, context = '') {
            const errorData = {
                message: error.message || error,
                stack: error.stack,
                context,
                timestamp: new Date().toISOString(),
                userAgent: navigator.userAgent,
                url: window.location.href
            };
            
            console.error('Application Error:', errorData);
            
            // In production, you might want to send this to an error tracking service
            if (!APP_CONFIG.DEBUG) {
                // Example: Send to error tracking service
                // this.sendToErrorService(errorData);
            }
        },

        handle(error, showToUser = true) {
            this.log(error);
            
            if (showToUser && window.Toast) {
                const message = this.getUserFriendlyMessage(error);
                Toast.show(message, APP_CONFIG.TOAST_TYPES.ERROR);
            }
        },

        getUserFriendlyMessage(error) {
            if (error.code) {
                switch (error.code) {
                    case APP_CONFIG.ERROR_CODES.NETWORK_ERROR:
                        return 'Network connection error. Please check your internet connection.';
                    case APP_CONFIG.ERROR_CODES.AUTHENTICATION_ERROR:
                        return 'Authentication failed. Please try logging in again.';
                    case APP_CONFIG.ERROR_CODES.VALIDATION_ERROR:
                        return 'Invalid input. Please check your information and try again.';
                    case APP_CONFIG.ERROR_CODES.BOOKING_ERROR:
                        return 'Booking operation failed. Please try again.';
                    default:
                        return 'An unexpected error occurred. Please try again.';
                }
            }
            
            return error.message || 'An unexpected error occurred. Please try again.';
        }
    },

    /**
     * Animation helpers
     */
    animation: {
        fadeIn(element, duration = 300) {
            element.style.opacity = '0';
            element.style.display = 'block';
            
            let start = null;
            const animate = (timestamp) => {
                if (!start) start = timestamp;
                const progress = Math.min((timestamp - start) / duration, 1);
                element.style.opacity = progress;
                
                if (progress < 1) {
                    requestAnimationFrame(animate);
                }
            };
            
            requestAnimationFrame(animate);
        },

        fadeOut(element, duration = 300) {
            let start = null;
            const animate = (timestamp) => {
                if (!start) start = timestamp;
                const progress = Math.min((timestamp - start) / duration, 1);
                element.style.opacity = 1 - progress;
                
                if (progress < 1) {
                    requestAnimationFrame(animate);
                } else {
                    element.style.display = 'none';
                }
            };
            
            requestAnimationFrame(animate);
        },

        slideDown(element, duration = 300) {
            element.style.height = '0px';
            element.style.overflow = 'hidden';
            element.style.display = 'block';
            
            const targetHeight = element.scrollHeight;
            let start = null;
            
            const animate = (timestamp) => {
                if (!start) start = timestamp;
                const progress = Math.min((timestamp - start) / duration, 1);
                element.style.height = (targetHeight * progress) + 'px';
                
                if (progress < 1) {
                    requestAnimationFrame(animate);
                } else {
                    element.style.height = 'auto';
                    element.style.overflow = 'visible';
                }
            };
            
            requestAnimationFrame(animate);
        }
    }
};

// Export for modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = Utils;
}