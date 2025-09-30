/**
 * Real-time API Service for Production Bus Booking System
 * Connects to PHP backend with MySQL database
 */
class RealTimeAPIService {
    constructor() {
        this.baseURL = '/api';
        this.timeout = 30000; // Increased for corporate proxy environments
        this.retryAttempts = 5; // More retries for proxy issues
        this.retryDelay = 2000; // Longer delay between retries
        
        // Real-time update polling
        this.pollingInterval = null;
        this.pollingDelay = 5000; // 5 seconds
        this.listeners = new Map();
        
        // Corporate proxy compatibility
        this.isOnline = navigator.onLine;
        this.proxyCompatible = true;
        
        // Monitor network status
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.startRealTimeUpdates();
        });
        
        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.stopRealTimeUpdates();
        });
        
        console.log('🌐 Real-time API Service initialized for corporate environment');
    }

    /**
     * Make HTTP request with retry logic
     */
    async request(url, options = {}) {
        const config = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache',
                // Corporate proxy compatibility headers
                'X-Requested-With': 'XMLHttpRequest'
            },
            timeout: this.timeout,
            credentials: 'same-origin', // Important for corporate proxies
            ...options
        };

        let lastError;
        
        for (let attempt = 1; attempt <= this.retryAttempts; attempt++) {
            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), this.timeout);
                
                const response = await fetch(url, {
                    ...config,
                    signal: controller.signal
                });
                
                clearTimeout(timeoutId);
                
                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.error || `HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.error || 'API request failed');
                }
                
                return data;
                
            } catch (error) {
                lastError = error;
                
                if (attempt < this.retryAttempts && !error.name === 'AbortError') {
                    console.warn(`API request attempt ${attempt} failed, retrying...`, error.message);
                    await this.delay(this.retryDelay * attempt);
                    continue;
                }
                
                break;
            }
        }
        
        console.error('API request failed after all retries:', lastError);
        throw lastError;
    }

    /**
     * Delay helper for retry logic
     */
    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Get available buses with real-time seat availability
     */
    async getAvailableSchedules(date = null) {
        try {
            const url = date ? 
                `${this.baseURL}/schedules/available?date=${date}` : 
                `${this.baseURL}/schedules/available`;
                
            const response = await this.request(url);
            
            return {
                success: true,
                data: {
                    schedules: response.data.schedules || []
                },
                message: response.message
            };
            
        } catch (error) {
            return {
                success: false,
                error: error.message,
                data: { schedules: [] }
            };
        }
    }

    /**
     * Get real-time availability for a specific bus
     */
    async getScheduleAvailability(scheduleId, date) {
        try {
            const url = `${this.baseURL}/bus/${scheduleId}/availability?date=${date}`;
            const response = await this.request(url);
            
            return {
                success: true,
                data: response.data,
                message: response.message
            };
            
        } catch (error) {
            return {
                success: false,
                error: error.message,
                data: null
            };
        }
    }

    /**
     * Create a booking with real-time concurrency control
     */
    async createBooking(employeeId, scheduleId, date) {
        try {
            const response = await this.request(`${this.baseURL}/booking/book`, {
                method: 'POST',
                body: JSON.stringify({
                    employee_id: employeeId,
                    bus_id: parseInt(scheduleId),
                    date: date
                })
            });
            
            // Notify listeners about the booking change
            this.notifyListeners('booking-created', {
                employeeId,
                scheduleId,
                date,
                bookingData: response.data
            });
            
            return {
                success: true,
                data: response.data,
                message: response.message
            };
            
        } catch (error) {
            return {
                success: false,
                error: error.message,
                message: error.message
            };
        }
    }

    /**
     * Cancel a booking
     */
    async cancelBooking(employeeId, scheduleId, date) {
        try {
            const response = await this.request(`${this.baseURL}/booking/cancel`, {
                method: 'POST',
                body: JSON.stringify({
                    employee_id: employeeId,
                    bus_id: parseInt(scheduleId),
                    date: date
                })
            });
            
            // Notify listeners about the booking change
            this.notifyListeners('booking-cancelled', {
                employeeId,
                scheduleId,
                date
            });
            
            return {
                success: true,
                data: response.data,
                message: response.message
            };
            
        } catch (error) {
            return {
                success: false,
                error: error.message,
                message: error.message
            };
        }
    }

    /**
     * Get employee's current booking status
     */
    async getBookingStatus(employeeId, scheduleId, date) {
        try {
            const url = `${this.baseURL}/booking/status?employee_id=${employeeId}&date=${date}`;
            const response = await this.request(url);
            
            return {
                success: true,
                data: response.data,
                message: response.message
            };
            
        } catch (error) {
            return {
                success: false,
                error: error.message,
                data: { has_booking: false, booking: null }
            };
        }
    }

    /**
     * Get employee's daily booking (for duplicate prevention)
     */
    async getEmployeeDailyBooking(employeeId, date) {
        return this.getBookingStatus(employeeId, null, date);
    }

    /**
     * Register employee for email notifications
     */
    async registerEmployee(employeeId, email, fullName = '', department = '') {
        try {
            const response = await this.request(`${this.baseURL}/employee/register`, {
                method: 'POST',
                body: JSON.stringify({
                    employee_id: employeeId,
                    email: email,
                    full_name: fullName,
                    department: department
                })
            });
            
            return {
                success: true,
                data: response.data,
                message: response.message
            };
            
        } catch (error) {
            return {
                success: false,
                error: error.message,
                message: error.message
            };
        }
    }

    /**
     * Start real-time polling for updates
     */
    startRealTimeUpdates(callback, interval = null) {
        if (this.pollingInterval) {
            this.stopRealTimeUpdates();
        }
        
        const pollInterval = interval || this.pollingDelay;
        
        this.pollingInterval = setInterval(async () => {
            try {
                // Poll for updated bus availability
                const busesResponse = await this.getAvailableSchedules();
                if (busesResponse.success) {
                    callback('buses-updated', busesResponse.data.schedules);
                }
            } catch (error) {
                console.warn('Real-time polling error:', error.message);
            }
        }, pollInterval);
        
        console.log(`Real-time updates started (polling every ${pollInterval}ms)`);
    }

    /**
     * Stop real-time polling
     */
    stopRealTimeUpdates() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
            console.log('Real-time updates stopped');
        }
    }

    /**
     * Add event listener for real-time updates
     */
    addEventListener(event, callback) {
        if (!this.listeners.has(event)) {
            this.listeners.set(event, new Set());
        }
        this.listeners.get(event).add(callback);
    }

    /**
     * Remove event listener
     */
    removeEventListener(event, callback) {
        if (this.listeners.has(event)) {
            this.listeners.get(event).delete(callback);
        }
    }

    /**
     * Notify all listeners of an event
     */
    notifyListeners(event, data) {
        if (this.listeners.has(event)) {
            this.listeners.get(event).forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    console.error('Error in event listener:', error);
                }
            });
        }
    }

    /**
     * Health check to verify API connectivity
     */
    async checkHealth() {
        try {
            // Try to get available buses as a health check
            const response = await this.getAvailableSchedules();
            
            return {
                success: true,
                data: {
                    status: 'healthy',
                    mode: 'real-time',
                    timestamp: new Date().toISOString()
                },
                message: 'API is healthy and connected to database'
            };
            
        } catch (error) {
            return {
                success: false,
                data: {
                    status: 'unhealthy',
                    mode: 'real-time',
                    error: error.message,
                    timestamp: new Date().toISOString()
                },
                message: 'API health check failed'
            };
        }
    }

    /**
     * Get system statistics (for admin panel)
     */
    async getSystemStats() {
        try {
            // This would be implemented as a separate endpoint
            const response = await this.request(`${this.baseURL}/admin/stats`);
            
            return {
                success: true,
                data: response.data,
                message: response.message
            };
            
        } catch (error) {
            return {
                success: false,
                error: error.message,
                data: null
            };
        }
    }

    /**
     * Clean up resources
     */
    destroy() {
        this.stopRealTimeUpdates();
        this.listeners.clear();
        console.log('Real-time API Service destroyed');
    }
}

// Initialize the real-time API service
const API = new RealTimeAPIService();

// Make it globally available
if (typeof window !== 'undefined') {
    window.API = API;
    
    // Clean up on page unload
    window.addEventListener('beforeunload', () => {
        API.destroy();
    });
}

// For debugging in development
if (typeof console !== 'undefined') {
    console.log('Real-time Bus Booking API Service loaded');
    
    // Test connectivity on load
    API.checkHealth().then(result => {
        if (result.success) {
            console.log('✅ API connectivity verified');
        } else {
            console.warn('⚠️ API connectivity issue:', result.message);
        }
    });
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = RealTimeAPIService;
}