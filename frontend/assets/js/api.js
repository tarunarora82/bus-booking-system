/**
 * API Service - OFFLINE VERSION (No HTTP requests)
 * All data is embedded locally to work with corporate proxies
 */
class APIService {
    constructor() {
        this.baseURL = APP_CONFIG.API_BASE_URL;
        this.timeout = APP_CONFIG.API_TIMEOUT;
        this.token = Utils.storage.get(APP_CONFIG.TOKEN_KEY);
        
        // Embedded local data - no network calls needed
        this.localData = {
            schedules: [
                {
                    id: 1,
                    name: 'Morning Shift - Bus A',
                    departure_time: '08:00',
                    arrival_time: '18:00',
                    capacity: 45,
                    available_seats: 32,
                    route: 'City Center to Industrial Park',
                    type: 'morning',
                    schedule_type: 'morning'
                },
                {
                    id: 2,
                    name: 'Evening Shift - Bus B',
                    departure_time: '18:30',
                    arrival_time: '04:30',
                    capacity: 45,
                    available_seats: 28,
                    route: 'Industrial Park to City Center',
                    type: 'evening',
                    schedule_type: 'evening'
                },
                {
                    id: 3,
                    name: 'Night Shift - Bus C',
                    departure_time: '22:00',
                    arrival_time: '08:00',
                    capacity: 40,
                    available_seats: 15,
                    route: 'City Center to Industrial Park',
                    type: 'night',
                    schedule_type: 'night'
                }
            ],
            bookings: {}
        };
    }

    /**
     * Set authentication token
     * @param {string} token 
     */
    setToken(token) {
        this.token = token;
        if (token) {
            Utils.storage.set(APP_CONFIG.TOKEN_KEY, token);
        } else {
            Utils.storage.remove(APP_CONFIG.TOKEN_KEY);
        }
    }

    /**
     * Get authentication token
     * @returns {string|null}
     */
    getToken() {
        return this.token || Utils.storage.get(APP_CONFIG.TOKEN_KEY);
    }

    /**
     * Create request headers
     * @param {Object} additionalHeaders 
     * @returns {Object}
     */
    createHeaders(additionalHeaders = {}) {
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...additionalHeaders
        };

        const token = this.getToken();
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        return headers;
    }

    /**
     * Handle API response
     * @param {Response} response 
     * @returns {Promise<Object>}
     */
    async handleResponse(response) {
        let data;
        
        try {
            data = await response.json();
        } catch (error) {
            data = { message: 'Invalid response format' };
        }

        if (!response.ok) {
            const error = new Error(data.message || `HTTP ${response.status}`);
            error.status = response.status;
            error.code = data.error_code || APP_CONFIG.ERROR_CODES.SERVER_ERROR;
            error.errors = data.errors || {};
            throw error;
        }

        return data;
    }

    /**
     * Make HTTP request - OFFLINE VERSION (No actual HTTP calls)
     * @param {string} method 
     * @param {string} endpoint 
     * @param {Object} data 
     * @param {Object} options 
     * @returns {Promise<Object>}
     */
    async request(method, endpoint, data = null, options = {}) {
        // NO ACTUAL HTTP REQUESTS - All data is local
        console.log(`OFFLINE MODE: Simulating ${method} request to ${endpoint}`);
        
        return new Promise((resolve, reject) => {
            setTimeout(() => {
                // Simulate successful response for any endpoint
                resolve({
                    success: true,
                    data: {},
                    message: 'Request completed (offline mode)'
                });
            }, 100);
        });
    }
            
            throw error;
        }
    }

    /**
     * GET request
     * @param {string} endpoint 
     * @param {Object} options 
     * @returns {Promise<Object>}
     */
    async get(endpoint, options = {}) {
        return this.request('GET', endpoint, null, options);
    }

    /**
     * POST request
     * @param {string} endpoint 
     * @param {Object} data 
     * @param {Object} options 
     * @returns {Promise<Object>}
     */
    async post(endpoint, data, options = {}) {
        return this.request('POST', endpoint, data, options);
    }

    /**
     * PUT request
     * @param {string} endpoint 
     * @param {Object} data 
     * @param {Object} options 
     * @returns {Promise<Object>}
     */
    async put(endpoint, data, options = {}) {
        return this.request('PUT', endpoint, data, options);
    }

    /**
     * DELETE request
     * @param {string} endpoint 
     * @param {Object} data 
     * @param {Object} options 
     * @returns {Promise<Object>}
     */
    async delete(endpoint, data = null, options = {}) {
        return this.request('DELETE', endpoint, data, options);
    }

    // Authentication API methods
    async login(workerId, type = 'user', password = null) {
        const payload = { worker_id: workerId, type };
        if (password && type === 'admin') {
            payload.password = password;
        }
        
        const response = await this.post('/auth/login', payload);
        
        if (response.success && response.data.token) {
            this.setToken(response.data.token);
            Utils.storage.set(APP_CONFIG.USER_DATA_KEY, response.data.user || response.data.admin);
        }
        
        return response;
    }

    async logout() {
        try {
            await this.post('/auth/logout');
        } catch (error) {
            // Ignore logout errors
        } finally {
            this.setToken(null);
            Utils.storage.remove(APP_CONFIG.USER_DATA_KEY);
        }
    }

    async verifyToken(token = null) {
        const tokenToVerify = token || this.getToken();
        if (!tokenToVerify) {
            throw new Error('No token to verify');
        }
        
        return this.post('/auth/verify', { token: tokenToVerify });
    }

    // Schedule API methods - OFFLINE VERSION
    async getAvailableSchedules(date = null) {
        // Return local data instead of making HTTP requests
        return new Promise((resolve) => {
            setTimeout(() => {
                resolve({
                    success: true,
                    data: {
                        schedules: this.localData.schedules
                    },
                    message: 'Schedules retrieved successfully (offline mode)'
                });
            }, 200); // Small delay to simulate API call
        });
    }

    async getScheduleAvailability(scheduleId, date) {
        // Return local availability data
        return new Promise((resolve) => {
            setTimeout(() => {
                const schedule = this.localData.schedules.find(s => s.id === parseInt(scheduleId));
                resolve({
                    success: true,
                    data: {
                        schedule_id: scheduleId,
                        date: date,
                        available_seats: schedule ? schedule.available_seats : 0,
                        capacity: schedule ? schedule.capacity : 0
                    },
                    message: 'Availability retrieved successfully (offline mode)'
                });
            }, 100);
        });
    }

    async getRealTimeAvailability(scheduleId, date) {
        // Same as getScheduleAvailability for offline mode
        return this.getScheduleAvailability(scheduleId, date);
    }

    // Booking API methods - OFFLINE VERSION
    async createBooking(workerId, scheduleId, date) {
        return new Promise((resolve) => {
            setTimeout(() => {
                const bookingId = 'BK' + Date.now();
                const key = `${workerId}_${scheduleId}_${date}`;
                this.localData.bookings[key] = {
                    booking_id: bookingId,
                    worker_id: workerId,
                    schedule_id: scheduleId,
                    date: date,
                    status: 'confirmed',
                    created_at: new Date().toISOString()
                };
                
                // Reduce available seats
                const schedule = this.localData.schedules.find(s => s.id === parseInt(scheduleId));
                if (schedule && schedule.available_seats > 0) {
                    schedule.available_seats--;
                }
                
                resolve({
                    success: true,
                    data: {
                        booking_id: bookingId,
                        status: 'confirmed'
                    },
                    message: 'Booking created successfully (offline mode)'
                });
            }, 300);
        });
    }

    async cancelBooking(workerId, scheduleId, date) {
        return new Promise((resolve) => {
            setTimeout(() => {
                const key = `${workerId}_${scheduleId}_${date}`;
                if (this.localData.bookings[key]) {
                    delete this.localData.bookings[key];
                    
                    // Increase available seats
                    const schedule = this.localData.schedules.find(s => s.id === parseInt(scheduleId));
                    if (schedule) {
                        schedule.available_seats++;
                    }
                }
                
                resolve({
                    success: true,
                    data: {
                        status: 'cancelled'
                    },
                    message: 'Booking cancelled successfully (offline mode)'
                });
            }, 200);
        });
    }

    async getBookingStatus(workerId, scheduleId, date) {
        return new Promise((resolve) => {
            setTimeout(() => {
                const key = `${workerId}_${scheduleId}_${date}`;
                const booking = this.localData.bookings[key];
                
                resolve({
                    success: true,
                    data: {
                        booking: booking || null,
                        has_booking: !!booking
                    },
                    message: 'Booking status retrieved successfully (offline mode)'
                });
            }, 100);
        });
    }

    async getMyBookings(startDate = null, endDate = null) {
        let endpoint = '/bookings/my-bookings';
        const params = new URLSearchParams();
        
        if (startDate) params.append('start_date', startDate);
        if (endDate) params.append('end_date', endDate);
        
        if (params.toString()) {
            endpoint += `?${params.toString()}`;
        }
        
        return this.get(endpoint);
    }

    async searchBookings(workerId, date = null) {
        let endpoint = `/bookings/search/${workerId}`;
        if (date) {
            endpoint += `?date=${date}`;
        }
        return this.get(endpoint);
    }

    // User API methods
    async getUserProfile() {
        return this.get('/user/profile');
    }

    async updateUserProfile(data) {
        return this.put('/user/profile', data);
    }

    // Health check
    async healthCheck() {
        try {
            const response = await fetch('/api/health', {
                method: 'GET',
                cache: 'no-cache'
            });
            return response.ok;
        } catch (error) {
            return false;
        }
    }
}

/**
 * Cached API Service with offline support
 */
class CachedAPIService extends APIService {
    constructor() {
        super();
        this.cache = new Map();
        this.cacheDuration = 5 * 60 * 1000; // 5 minutes
    }

    /**
     * Generate cache key
     * @param {string} method 
     * @param {string} endpoint 
     * @param {Object} data 
     * @returns {string}
     */
    getCacheKey(method, endpoint, data = null) {
        const key = `${method}:${endpoint}`;
        if (data) {
            return `${key}:${JSON.stringify(data)}`;
        }
        return key;
    }

    /**
     * Check if cache entry is valid
     * @param {Object} entry 
     * @returns {boolean}
     */
    isCacheValid(entry) {
        return entry && (Date.now() - entry.timestamp) < this.cacheDuration;
    }

    /**
     * Get from cache or make request
     * @param {string} method 
     * @param {string} endpoint 
     * @param {Object} data 
     * @param {Object} options 
     * @returns {Promise<Object>}
     */
    async cachedRequest(method, endpoint, data = null, options = {}) {
        const cacheKey = this.getCacheKey(method, endpoint, data);
        const cached = this.cache.get(cacheKey);

        // Return cached data if valid and we're offline or cache is still fresh
        if (this.isCacheValid(cached) && (!navigator.onLine || options.preferCache)) {
            return cached.data;
        }

        try {
            const response = await this.request(method, endpoint, data, options);
            
            // Cache successful GET requests
            if (method === 'GET' && response.success) {
                this.cache.set(cacheKey, {
                    data: response,
                    timestamp: Date.now()
                });
            }
            
            return response;
        } catch (error) {
            // Return cached data if available and we're offline
            if (!navigator.onLine && cached) {
                console.warn('Using cached data due to offline status');
                return cached.data;
            }
            throw error;
        }
    }

    /**
     * Override GET method to use caching
     */
    async get(endpoint, options = {}) {
        return this.cachedRequest('GET', endpoint, null, options);
    }

    /**
     * Clear cache
     */
    clearCache() {
        this.cache.clear();
    }

    /**
     * Remove expired cache entries
     */
    cleanupCache() {
        for (let [key, entry] of this.cache.entries()) {
            if (!this.isCacheValid(entry)) {
                this.cache.delete(key);
            }
        }
    }
}

// Create global API instance
const API = new CachedAPIService();

// Cleanup cache periodically
setInterval(() => {
    API.cleanupCache();
}, 5 * 60 * 1000); // Every 5 minutes

// Export for modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { APIService, CachedAPIService, API };
}