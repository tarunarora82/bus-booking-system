/**
 * Enhanced Bus Booking Application
 * Features:
 * - One bus selection only
 * - One booking per day per employee
 * - Book/Cancel functionality
 * - Professional modals
 * - Employee terminology
 */
class EnhancedBusBookingApp {
    constructor() {
        this.currentEmployeeId = null;
        this.buses = [];
        this.currentBooking = null;
        this.selectedBusId = null;
        
        this.init();
    }

    /**
     * Initialize the application
     */
    async init() {
        try {
            this.showLoadingScreen();
            this.bindEvents();
            this.hideLoadingScreen();
            
            console.log('Enhanced Bus Booking App initialized successfully');
        } catch (error) {
            console.error('App initialization failed:', error);
            this.hideLoadingScreen();
        }
    }

    /**
     * Bind event listeners
     */
    bindEvents() {
        // Employee ID form
        const employeeIdInput = document.getElementById('employee-id');
        const checkStatusBtn = document.getElementById('check-status-btn');
        
        if (employeeIdInput) {
            employeeIdInput.addEventListener('input', this.onEmployeeIdInput.bind(this));
            employeeIdInput.addEventListener('keypress', this.onEmployeeIdKeypress.bind(this));
        }
        
        if (checkStatusBtn) {
            checkStatusBtn.addEventListener('click', this.onCheckStatus.bind(this));
        }
    }

    /**
     * Handle employee ID input
     */
    onEmployeeIdInput(event) {
        const employeeId = event.target.value.trim();
        const checkStatusBtn = document.getElementById('check-status-btn');
        
        if (checkStatusBtn) {
            checkStatusBtn.disabled = !this.isValidEmployeeId(employeeId);
        }
    }

    /**
     * Handle Enter key in employee ID input
     */
    onEmployeeIdKeypress(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            this.onCheckStatus();
        }
    }

    /**
     * Validate employee ID
     */
    isValidEmployeeId(employeeId) {
        return employeeId && employeeId.length >= 7 && /^\d+$/.test(employeeId);
    }

    /**
     * Handle check status button click
     */
    async onCheckStatus() {
        const employeeIdInput = document.getElementById('employee-id');
        if (!employeeIdInput) return;

        const employeeId = employeeIdInput.value.trim();
        
        if (!this.isValidEmployeeId(employeeId)) {
            ProfessionalModal.error(
                'Please enter a valid Employee ID (7+ digits, numbers only)',
                'Invalid Employee ID'
            );
            employeeIdInput.focus();
            return;
        }

        try {
            this.showLoadingScreen();
            
            this.currentEmployeeId = employeeId;
            
            // Load buses and check current booking
            await Promise.all([
                this.loadBuses(),
                this.checkCurrentBooking()
            ]);
            
            // Show bus selection interface
            this.showBusSelection();
            
        } catch (error) {
            console.error('Error checking status:', error);
            ProfessionalModal.error(
                'Failed to load bus information. Please try again.',
                'System Error'
            );
            this.currentEmployeeId = null;
        } finally {
            this.hideLoadingScreen();
        }
    }

    /**
     * Load available buses
     */
    async loadBuses() {
        try {
            const response = await API.getAvailableSchedules();
            if (response.success) {
                this.buses = response.data.schedules || [];
            } else {
                throw new Error(response.message || 'Failed to load buses');
            }
        } catch (error) {
            throw new Error(`Failed to load buses: ${error.message}`);
        }
    }

    /**
     * Check if employee has a current booking for today
     */
    async checkCurrentBooking() {
        const today = new Date().toISOString().split('T')[0];
        
        try {
            const response = await API.getEmployeeDailyBooking(this.currentEmployeeId, today);
            if (response.success && response.data.booking) {
                this.currentBooking = response.data.booking;
            } else {
                this.currentBooking = null;
            }
        } catch (error) {
            console.error('Error checking current booking:', error);
            this.currentBooking = null;
        }
    }

    /**
     * Show bus selection interface
     */
    showBusSelection() {
        // Hide employee ID section
        const employeeIdSection = document.querySelector('.worker-id-section');
        if (employeeIdSection) {
            employeeIdSection.style.display = 'none';
        }

        // Show buses container
        const busesContainer = document.getElementById('schedules-container');
        if (!busesContainer) return;

        busesContainer.style.display = 'block';
        busesContainer.innerHTML = this.generateBusSelectionHTML();

        // Bind bus selection events
        this.bindBusSelectionEvents();

        // Show current booking status if exists
        if (this.currentBooking) {
            this.showCurrentBookingStatus();
        }
    }

    /**
     * Generate HTML for bus selection
     */
    generateBusSelectionHTML() {
        let html = `
            <div class=\"bus-selection-header\">
                <h2>🚌 Select Your Bus</h2>
                <p class=\"employee-welcome\">Welcome, Employee ID: <strong>${this.currentEmployeeId}</strong></p>
                ${this.currentBooking ? 
                    `<div class=\"current-booking-alert\">
                        <i class=\"icon-info\">ℹ</i>
                        You have an existing booking for <strong>${this.currentBooking.bus_number}</strong> today.
                        <br>Cancel it to book a different bus.
                    </div>` : 
                    '<p class=\"selection-instruction\">Please select one bus for today. Only one booking is allowed per day.</p>'
                }
            </div>
            <div class=\"buses-grid\">
        `;

        this.buses.forEach(bus => {
            const isCurrentBooking = this.currentBooking && this.currentBooking.schedule_id === bus.id;
            const isDisabled = this.currentBooking && !isCurrentBooking;
            const hasSeats = bus.available_seats > 0;
            
            html += `
                <div class=\"bus-card ${isCurrentBooking ? 'current-booking' : ''} ${isDisabled ? 'disabled' : ''} ${!hasSeats && !isCurrentBooking ? 'no-seats' : ''}\" 
                     data-bus-id=\"${bus.id}\" 
                     ${!isDisabled && hasSeats ? 'data-selectable=\"true\"' : ''}>
                    
                    <div class=\"bus-header\">
                        <div class=\"bus-number\">${bus.bus_number}</div>
                        ${isCurrentBooking ? '<div class=\"booking-badge\">Your Booking</div>' : ''}
                        ${!hasSeats && !isCurrentBooking ? '<div class=\"full-badge\">Full</div>' : ''}
                    </div>
                    
                    <div class=\"bus-info\">
                        <h3 class=\"bus-name\">${bus.name}</h3>
                        <div class=\"bus-details\">
                            <div class=\"detail-item\">
                                <i class=\"icon-time\">🕐</i>
                                <span><strong>Time:</strong> ${bus.departure_time} - ${bus.arrival_time}</span>
                            </div>
                            <div class=\"detail-item\">
                                <i class=\"icon-route\">📍</i>
                                <span><strong>Route:</strong> ${bus.route}</span>
                            </div>
                            <div class=\"detail-item capacity-info\">
                                <i class=\"icon-seat\">💺</i>
                                <span><strong>Seats:</strong> 
                                    <span class=\"available-seats\">${bus.available_seats}</span> / 
                                    <span class=\"total-seats\">${bus.capacity}</span> available
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class=\"bus-actions\">
                        ${this.generateBusActionButton(bus, isCurrentBooking, isDisabled, hasSeats)}
                    </div>
                    
                    ${isCurrentBooking ? `
                        <div class=\"booking-details\">
                            <small>Booking ID: ${this.currentBooking.booking_id}</small>
                            <small>Booked: ${new Date(this.currentBooking.created_at).toLocaleString()}</small>
                        </div>
                    ` : ''}
                </div>
            `;
        });

        html += `
            </div>
            <div class=\"selection-footer\">
                <button id=\"back-to-employee-id\" class=\"btn btn-secondary\">
                    <i class=\"icon-back\">←</i> Back to Employee ID
                </button>
                <button id=\"refresh-buses\" class=\"btn btn-outline\">
                    <i class=\"icon-refresh\">🔄</i> Refresh
                </button>
            </div>
        `;

        return html;
    }

    /**
     * Generate action button for each bus
     */
    generateBusActionButton(bus, isCurrentBooking, isDisabled, hasSeats) {
        if (isCurrentBooking) {
            return `
                <button class=\"btn btn-danger cancel-booking-btn\" data-bus-id=\"${bus.id}\">
                    <i class=\"icon-cancel\">✗</i> Cancel Booking
                </button>
            `;
        }
        
        if (isDisabled) {
            return `
                <button class=\"btn btn-disabled\" disabled>
                    <i class=\"icon-lock\">🔒</i> Cancel existing booking first
                </button>
            `;
        }
        
        if (!hasSeats) {
            return `
                <button class=\"btn btn-disabled\" disabled>
                    <i class=\"icon-full\">🚫</i> Bus Full
                </button>
            `;
        }
        
        return `
            <button class=\"btn btn-primary book-bus-btn\" data-bus-id=\"${bus.id}\">
                <i class=\"icon-book\">✓</i> Book This Bus
            </button>
        `;
    }

    /**
     * Bind events for bus selection
     */
    bindBusSelectionEvents() {
        // Book bus buttons
        document.querySelectorAll('.book-bus-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const busId = parseInt(e.target.closest('.book-bus-btn').dataset.busId);
                this.confirmBooking(busId);
            });
        });

        // Cancel booking buttons
        document.querySelectorAll('.cancel-booking-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const busId = parseInt(e.target.closest('.cancel-booking-btn').dataset.busId);
                this.confirmCancellation(busId);
            });
        });

        // Back button
        const backBtn = document.getElementById('back-to-employee-id');
        if (backBtn) {
            backBtn.addEventListener('click', () => {
                this.showEmployeeIdSection();
            });
        }

        // Refresh button
        const refreshBtn = document.getElementById('refresh-buses');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                this.refreshBusData();
            });
        }
    }

    /**
     * Confirm bus booking
     */
    confirmBooking(busId) {
        const bus = this.buses.find(b => b.id === busId);
        if (!bus) return;

        ProfessionalModal.confirm(
            `Do you want to book ${bus.bus_number} (${bus.name}) for today?\\n\\nTime: ${bus.departure_time} - ${bus.arrival_time}\\nRoute: ${bus.route}\\nAvailable Seats: ${bus.available_seats}/${bus.capacity}`,
            'Confirm Bus Booking',
            {
                confirmText: 'Book Now',
                cancelText: 'Cancel',
                onConfirm: () => this.bookBus(busId),
                onCancel: () => console.log('Booking cancelled by user')
            }
        );
    }

    /**
     * Confirm booking cancellation
     */
    confirmCancellation(busId) {
        const bus = this.buses.find(b => b.id === busId);
        if (!bus || !this.currentBooking) return;

        ProfessionalModal.confirm(
            `Are you sure you want to cancel your booking for ${bus.bus_number}?\\n\\nThis will free up your seat and allow you to book a different bus.`,
            'Cancel Booking',
            {
                confirmText: 'Yes, Cancel',
                cancelText: 'Keep Booking',
                onConfirm: () => this.cancelBooking(busId),
                onCancel: () => console.log('Cancellation cancelled by user')
            }
        );
    }

    /**
     * Book a bus
     */
    async bookBus(busId) {
        const today = new Date().toISOString().split('T')[0];
        
        try {
            this.showLoadingScreen();
            
            const response = await API.createBooking(this.currentEmployeeId, busId, today);
            
            if (response.success) {
                const bus = this.buses.find(b => b.id === busId);
                
                ProfessionalModal.success(
                    `Your booking has been confirmed!\\n\\nBooking ID: ${response.data.booking_id}\\nBus: ${bus.bus_number} (${bus.name})\\nDate: ${today}`,
                    'Booking Confirmed',
                    {
                        onConfirm: () => this.refreshAfterBooking()
                    }
                );
                
            } else if (response.error === 'DUPLICATE_BOOKING') {
                ProfessionalModal.warning(
                    response.message,
                    'Booking Already Exists',
                    {
                        details: response.data.existing_bus ? 
                            `Existing booking: ${response.data.existing_bus.bus_number}` : 
                            'You have an existing booking for today'
                    }
                );
            } else {
                throw new Error(response.message || 'Booking failed');
            }
            
        } catch (error) {
            console.error('Booking error:', error);
            ProfessionalModal.error(
                `Failed to book the bus: ${error.message}`,
                'Booking Failed'
            );
        } finally {
            this.hideLoadingScreen();
        }
    }

    /**
     * Cancel a booking
     */
    async cancelBooking(busId) {
        const today = new Date().toISOString().split('T')[0];
        
        try {
            this.showLoadingScreen();
            
            const response = await API.cancelBooking(this.currentEmployeeId, busId, today);
            
            if (response.success) {
                const bus = this.buses.find(b => b.id === busId);
                
                ProfessionalModal.success(
                    `Your booking for ${bus.bus_number} has been cancelled successfully.\\n\\nYou can now book a different bus.`,
                    'Booking Cancelled',
                    {
                        onConfirm: () => this.refreshAfterCancellation()
                    }
                );
                
            } else {
                throw new Error(response.message || 'Cancellation failed');
            }
            
        } catch (error) {
            console.error('Cancellation error:', error);
            ProfessionalModal.error(
                `Failed to cancel booking: ${error.message}`,
                'Cancellation Failed'
            );
        } finally {
            this.hideLoadingScreen();
        }
    }

    /**
     * Refresh data after booking
     */
    async refreshAfterBooking() {
        try {
            await Promise.all([
                this.loadBuses(),
                this.checkCurrentBooking()
            ]);
            this.showBusSelection();
        } catch (error) {
            console.error('Refresh error:', error);
        }
    }

    /**
     * Refresh data after cancellation
     */
    async refreshAfterCancellation() {
        try {
            this.currentBooking = null;
            await this.loadBuses();
            this.showBusSelection();
        } catch (error) {
            console.error('Refresh error:', error);
        }
    }

    /**
     * Refresh bus data manually
     */
    async refreshBusData() {
        try {
            this.showLoadingScreen();
            await this.loadBuses();
            await this.checkCurrentBooking();
            this.showBusSelection();
            
            ProfessionalModal.success(
                'Bus information has been refreshed successfully.',
                'Data Refreshed'
            );
        } catch (error) {
            console.error('Refresh error:', error);
            ProfessionalModal.error(
                'Failed to refresh bus data. Please try again.',
                'Refresh Failed'
            );
        } finally {
            this.hideLoadingScreen();
        }
    }

    /**
     * Show employee ID section
     */
    showEmployeeIdSection() {
        const employeeIdSection = document.querySelector('.worker-id-section');
        const busesContainer = document.getElementById('schedules-container');
        
        if (employeeIdSection) {
            employeeIdSection.style.display = 'block';
        }
        
        if (busesContainer) {
            busesContainer.style.display = 'none';
        }
        
        // Clear current data
        this.currentEmployeeId = null;
        this.currentBooking = null;
        this.buses = [];
        
        // Focus employee ID input
        const employeeIdInput = document.getElementById('employee-id');
        if (employeeIdInput) {
            employeeIdInput.value = '';
            employeeIdInput.focus();
        }
    }

    /**
     * Show loading screen
     */
    showLoadingScreen() {
        const loadingScreen = document.getElementById('loading-screen');
        if (loadingScreen) {
            loadingScreen.style.display = 'flex';
        }
    }

    /**
     * Hide loading screen
     */
    hideLoadingScreen() {
        const loadingScreen = document.getElementById('loading-screen');
        if (loadingScreen) {
            loadingScreen.style.display = 'none';
        }
    }

    /**
     * Show current booking status (additional info display)
     */
    showCurrentBookingStatus() {
        // This could be used to show additional status information
        // For now, it's integrated into the bus cards
    }
}

// Initialize the enhanced app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Check if API is available
    if (typeof API === 'undefined') {
        console.error('API service not available');
        return;
    }
    
    // Initialize the enhanced app
    window.busApp = new EnhancedBusBookingApp();
});