/**
 * API Service - COMPLETELY OFFLINE VERSION
 * No HTTP requests - all data is embedded locally
 */
class APIService {
    constructor() {
        this.baseURL = APP_CONFIG.API_BASE_URL;
        this.timeout = APP_CONFIG.API_TIMEOUT;
        this.token = Utils.storage.get(APP_CONFIG.TOKEN_KEY);
        
        // Embedded local data - no network calls needed
        this.localData = {
            buses: [
                {
                    id: 1,
                    bus_number: 'BUS-001',
                    name: 'Morning Express',
                    departure_time: '08:00',
                    arrival_time: '18:00',
                    capacity: 45,
                    available_seats: 32,
                    route: 'City Center ↔ Industrial Park',
                    type: 'morning',
                    schedule_type: 'morning',
                    driver_name: 'John Doe',
                    contact_number: '+1-234-567-8901'
                },
                {
                    id: 2,
                    bus_number: 'BUS-002',
                    name: 'Evening Shuttle',
                    departure_time: '18:30',
                    arrival_time: '04:30',
                    capacity: 45,
                    available_seats: 28,
                    route: 'Industrial Park ↔ City Center',
                    type: 'evening',
                    schedule_type: 'evening',
                    driver_name: 'Jane Smith',
                    contact_number: '+1-234-567-8902'
                },
                {
                    id: 3,
                    bus_number: 'BUS-003',
                    name: 'Night Service',
                    departure_time: '22:00',
                    arrival_time: '08:00',
                    capacity: 40,
                    available_seats: 15,
                    route: 'City Center ↔ Industrial Park',
                    type: 'night',
                    schedule_type: 'night',
                    driver_name: 'Mike Johnson',
                    contact_number: '+1-234-567-8903'
                }
            ],
            employees: {
                // Employee data structure: employeeId: { email, name, department }
                // This will be populated when employees are registered
            },
            bookings: {},
            // Daily booking tracking per employee
            dailyBookings: {}
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

    // Schedule API methods - OFFLINE VERSION
    async getAvailableSchedules(date = null) {
        return new Promise((resolve) => {
            setTimeout(() => {
                resolve({
                    success: true,
                    data: {
                        schedules: this.localData.buses // Return buses as schedules for compatibility
                    },
                    message: 'Bus schedules retrieved successfully (offline mode)'
                });
            }, 200); // Small delay to simulate API call
        });
    }

    async getScheduleAvailability(scheduleId, date) {
        return new Promise((resolve) => {
            setTimeout(() => {
                const bus = this.localData.buses.find(b => b.id === parseInt(scheduleId));
                resolve({
                    success: true,
                    data: {
                        schedule_id: scheduleId,
                        date: date,
                        available_seats: bus ? bus.available_seats : 0,
                        capacity: bus ? bus.capacity : 0,
                        bus_number: bus ? bus.bus_number : 'N/A'
                    },
                    message: 'Bus availability retrieved successfully (offline mode)'
                });
            }, 100);
        });
    }

    async getRealTimeAvailability(scheduleId, date) {
        return this.getScheduleAvailability(scheduleId, date);
    }

    // Booking API methods - OFFLINE VERSION
    async createBooking(employeeId, scheduleId, date) {
        return new Promise((resolve) => {
            setTimeout(() => {
                // Check if employee already has a booking for this date
                const dailyKey = `${employeeId}_${date}`;
                if (this.localData.dailyBookings[dailyKey]) {
                    const existingBooking = this.localData.dailyBookings[dailyKey];
                    const existingBus = this.localData.buses.find(b => b.id === existingBooking.schedule_id);
                    resolve({
                        success: false,
                        error: 'DUPLICATE_BOOKING',
                        message: `You already have a booking for ${existingBus ? existingBus.bus_number : 'another bus'} on ${date}. Please cancel the existing booking to make a new one.`,
                        data: {
                            existing_booking: existingBooking,
                            existing_bus: existingBus
                        }
                    });
                    return;
                }

                // Check if bus has available seats
                const bus = this.localData.buses.find(b => b.id === parseInt(scheduleId));
                if (!bus || bus.available_seats <= 0) {
                    resolve({
                        success: false,
                        error: 'NO_SEATS_AVAILABLE',
                        message: 'No seats available on this bus. Please select another bus.',
                        data: null
                    });
                    return;
                }

                const bookingId = 'BK' + Date.now();
                const bookingKey = `${employeeId}_${scheduleId}_${date}`;
                const bookingData = {
                    booking_id: bookingId,
                    employee_id: employeeId,
                    schedule_id: parseInt(scheduleId),
                    date: date,
                    status: 'confirmed',
                    created_at: new Date().toISOString(),
                    bus_number: bus.bus_number,
                    bus_name: bus.name
                };
                
                // Store the booking
                this.localData.bookings[bookingKey] = bookingData;
                
                // Track daily booking to prevent duplicates
                this.localData.dailyBookings[dailyKey] = bookingData;
                
                // Reduce available seats
                bus.available_seats--;
                
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

    async cancelBooking(employeeId, scheduleId, date) {
        return new Promise((resolve) => {
            setTimeout(() => {
                const bookingKey = `${employeeId}_${scheduleId}_${date}`;
                const dailyKey = `${employeeId}_${date}`;
                
                if (this.localData.bookings[bookingKey]) {
                    delete this.localData.bookings[bookingKey];
                    
                    // Remove from daily booking tracker
                    if (this.localData.dailyBookings[dailyKey]) {
                        delete this.localData.dailyBookings[dailyKey];
                    }
                    
                    // Increase available seats
                    const bus = this.localData.buses.find(b => b.id === parseInt(scheduleId));
                    if (bus) {
                        bus.available_seats++;
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

    async getBookingStatus(employeeId, scheduleId, date) {
        return new Promise((resolve) => {
            setTimeout(() => {
                const key = `${employeeId}_${scheduleId}_${date}`;
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

    async getMyBookings(employeeId) {
        return new Promise((resolve) => {
            setTimeout(() => {
                const userBookings = Object.values(this.localData.bookings)
                    .filter(booking => booking.employee_id === employeeId);
                
                resolve({
                    success: true,
                    data: {
                        bookings: userBookings
                    },
                    message: 'Bookings retrieved successfully (offline mode)'
                });
            }, 150);
        });
    }

    // Get employee's current booking for a specific date
    async getEmployeeDailyBooking(employeeId, date) {
        return new Promise((resolve) => {
            setTimeout(() => {
                const dailyKey = `${employeeId}_${date}`;
                const booking = this.localData.dailyBookings[dailyKey];
                
                resolve({
                    success: true,
                    data: {
                        booking: booking || null,
                        has_booking: !!booking
                    },
                    message: 'Daily booking status retrieved successfully (offline mode)'
                });
            }, 100);
        });
    }

    // Employee management methods
    async registerEmployee(employeeId, email, name = '', department = '') {
        return new Promise((resolve) => {
            setTimeout(() => {
                this.localData.employees[employeeId] = {
                    email: email,
                    name: name,
                    department: department,
                    registered_at: new Date().toISOString()
                };
                
                resolve({
                    success: true,
                    data: {
                        employee_id: employeeId,
                        email: email
                    },
                    message: 'Employee registered successfully (offline mode)'
                });
            }, 100);
        });
    }

    async getEmployeeInfo(employeeId) {
        return new Promise((resolve) => {
            setTimeout(() => {
                const employee = this.localData.employees[employeeId];
                
                resolve({
                    success: true,
                    data: {
                        employee: employee || null,
                        is_registered: !!employee
                    },
                    message: 'Employee info retrieved successfully (offline mode)'
                });
            }, 100);
        });
    }

    async searchBookings(workerId) {
        return this.getMyBookings(workerId);
    }

    // Authentication methods - OFFLINE VERSION
    async login(workerId, type = 'user', password = null) {
        return new Promise((resolve) => {
            setTimeout(() => {
                if (workerId && workerId.length >= 7) {
                    const token = 'offline_token_' + Date.now();
                    this.setToken(token);
                    
                    resolve({
                        success: true,
                        data: {
                            token: token,
                            user: {
                                worker_id: workerId,
                                type: type,
                                name: `Worker ${workerId}`
                            }
                        },
                        message: 'Login successful (offline mode)'
                    });
                } else {
                    resolve({
                        success: false,
                        message: 'Invalid Worker ID'
                    });
                }
            }, 200);
        });
    }

    async logout() {
        return new Promise((resolve) => {
            setTimeout(() => {
                this.setToken(null);
                resolve({
                    success: true,
                    message: 'Logout successful (offline mode)'
                });
            }, 100);
        });
    }

    async verifyToken(token = null) {
        return new Promise((resolve) => {
            setTimeout(() => {
                const tokenToVerify = token || this.getToken();
                if (tokenToVerify && tokenToVerify.startsWith('offline_token_')) {
                    resolve({
                        success: true,
                        data: {
                            valid: true
                        },
                        message: 'Token verified (offline mode)'
                    });
                } else {
                    resolve({
                        success: false,
                        message: 'Invalid token'
                    });
                }
            }, 100);
        });
    }

    // Health check - OFFLINE VERSION
    async checkHealth() {
        return new Promise((resolve) => {
            setTimeout(() => {
                resolve({
                    success: true,
                    data: {
                        status: 'healthy',
                        mode: 'offline'
                    },
                    message: 'System is healthy (offline mode)'
                });
            }, 50);
        });
    }
}

// Initialize API service globally
const API = new APIService();

// Make it available globally for debugging
if (typeof window !== 'undefined') {
    window.API = API;
}