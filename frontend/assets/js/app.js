/**
 * Main Application Logic
 */
class BusBookingApp {
    constructor() {
        this.currentWorkerId = null;
        this.schedules = [];
        this.pollingInterval = null;
        this.offlineMode = false;
        
        this.init();
    }

    /**
     * Initialize the application
     */
    async init() {
        try {
            // Show loading screen
            this.showLoadingScreen();
            
            // Initialize components
            this.initializeComponents();
            
            // Bind events
            this.bindEvents();
            
            // Check for saved worker ID
            this.loadSavedWorkerId();
            
            // Check connectivity
            await this.checkConnectivity();
            
            // Handle URL parameters
            this.handleUrlParameters();
            
            // Hide loading screen and show app
            this.hideLoadingScreen();
            
            console.log('Bus Booking App initialized successfully');
        } catch (error) {
            Utils.error.handle(error);
            this.hideLoadingScreen();
        }
    }

    /**
     * Initialize components
     */
    initializeComponents() {
        // Initialize install prompt
        new InstallPrompt();
        
        // Initialize offline indicator
        this.initializeOfflineIndicator();
        
        // Initialize notification support
        this.initializeNotifications();
    }

    /**
     * Bind event listeners
     */
    bindEvents() {
        // Worker ID form
        const workerIdInput = Utils.getElementById('worker-id');
        const checkStatusBtn = Utils.getElementById('check-status-btn');
        
        if (workerIdInput) {
            workerIdInput.addEventListener('input', this.onWorkerIdInput.bind(this));
            workerIdInput.addEventListener('keypress', this.onWorkerIdKeypress.bind(this));
        }
        
        if (checkStatusBtn) {
            checkStatusBtn.addEventListener('click', this.onCheckStatus.bind(this));
        }

        // Header buttons
        const refreshBtn = Utils.getElementById('refresh-btn');
        const settingsBtn = Utils.getElementById('settings-btn');
        
        if (refreshBtn) {
            refreshBtn.addEventListener('click', this.onRefresh.bind(this));
        }
        
        if (settingsBtn) {
            settingsBtn.addEventListener('click', this.onSettings.bind(this));
        }

        // Online/offline events
        window.addEventListener('online', this.onOnline.bind(this));
        window.addEventListener('offline', this.onOffline.bind(this));

        // Visibility change for polling
        document.addEventListener('visibilitychange', this.onVisibilityChange.bind(this));

        // Beforeunload for cleanup
        window.addEventListener('beforeunload', this.onBeforeUnload.bind(this));
    }

    /**
     * Handle worker ID input
     */
    onWorkerIdInput(event) {
        const workerId = event.target.value.trim();
        const checkStatusBtn = Utils.getElementById('check-status-btn');
        
        if (checkStatusBtn) {
            checkStatusBtn.disabled = !Utils.isValidWorkerId(workerId);
        }
        
        // Save worker ID for next time
        if (workerId.length >= APP_CONFIG.WORKER_ID_MIN_LENGTH) {
            Utils.storage.set(APP_CONFIG.STORAGE_KEYS.WORKER_ID, workerId);
        }
    }

    /**
     * Handle Enter key in worker ID input
     */
    onWorkerIdKeypress(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            this.onCheckStatus();
        }
    }

    /**
     * Handle check status button click
     */
    async onCheckStatus() {
        const workerIdInput = Utils.getElementById('worker-id');
        if (!workerIdInput) return;

        const workerId = workerIdInput.value.trim();
        
        if (!Utils.isValidWorkerId(workerId)) {
            Toast.error('Please enter a valid Worker ID (7-10 digits, numbers only)');
            workerIdInput.focus();
            return;
        }

        try {
            Loading.show('Checking booking status...');
            
            this.currentWorkerId = workerId;
            
            // Load schedules and booking status
            await Promise.all([
                this.loadSchedules(),
                this.loadBookingHistory()
            ]);
            
            // Show booking interface
            this.showBookingInterface();
            
            // Start real-time updates
            this.startPolling();
            
            Toast.success('Booking status loaded successfully');
            
        } catch (error) {
            Utils.error.handle(error);
            this.currentWorkerId = null;
        } finally {
            Loading.hide();
        }
    }

    /**
     * Handle refresh button click
     */
    async onRefresh() {
        if (!this.currentWorkerId) {
            Toast.info('Please enter your Worker ID first');
            return;
        }

        try {
            const refreshBtn = Utils.getElementById('refresh-btn');
            if (refreshBtn) {
                refreshBtn.disabled = true;
                refreshBtn.innerHTML = '<i class="icon-refresh spinning"></i>';
            }

            await this.loadSchedules();
            Toast.success('Data refreshed successfully');

        } catch (error) {
            Utils.error.handle(error);
        } finally {
            const refreshBtn = Utils.getElementById('refresh-btn');
            if (refreshBtn) {
                refreshBtn.disabled = false;
                refreshBtn.innerHTML = '<i class="icon-refresh"></i>';
            }
        }
    }

    /**
     * Handle settings button click
     */
    onSettings() {
        // Show settings modal or navigate to settings page
        Toast.info('Settings feature coming soon!');
    }

    /**
     * Load available schedules
     */
    async loadSchedules() {
        try {
            const date = Utils.getTodayDate();
            const response = await API.getAvailableSchedules(date);
            
            if (!response.success) {
                throw new Error(response.message || 'Failed to load schedules');
            }

            this.schedules = response.data.schedules || [];
            
            // Load booking status for each schedule
            await this.loadBookingStatuses();
            
            // Render schedule cards
            this.renderScheduleCards();
            
        } catch (error) {
            throw new Error(`Failed to load schedules: ${error.message}`);
        }
    }

    /**
     * Load booking statuses for all schedules
     */
    async loadBookingStatuses() {
        const date = Utils.getTodayDate();
        const statusPromises = this.schedules.map(async (schedule) => {
            try {
                const [availabilityResponse, statusResponse] = await Promise.all([
                    API.getScheduleAvailability(schedule.id, date),
                    API.getBookingStatus(this.currentWorkerId, schedule.id, date)
                ]);

                schedule.availability = availabilityResponse.data;
                schedule.bookingStatus = statusResponse.data;
                
            } catch (error) {
                console.warn(`Failed to load status for schedule ${schedule.id}:`, error);
                schedule.availability = {
                    capacity: schedule.capacity || 50,
                    booked_count: 0,
                    available_count: schedule.capacity || 50,
                    waitlist_count: 0,
                    can_book: true
                };
                schedule.bookingStatus = {
                    status: APP_CONFIG.BOOKING_STATUS.NO_BOOKING,
                    can_book: true
                };
            }
        });

        await Promise.all(statusPromises);
    }

    /**
     * Render schedule cards
     */
    renderScheduleCards() {
        const container = Utils.getElementById('schedules-container');
        if (!container) return;

        container.innerHTML = '';
        
        if (this.schedules.length === 0) {
            const emptyState = Utils.createElement('div', {
                className: 'empty-state'
            });
            emptyState.innerHTML = `
                <h3>No schedules available</h3>
                <p>There are no bus schedules available for today.</p>
            `;
            container.appendChild(emptyState);
            return;
        }

        // Sort schedules by type (morning first)
        const sortedSchedules = this.schedules.sort((a, b) => {
            const order = { morning: 1, evening: 2 };
            return order[a.schedule_type] - order[b.schedule_type];
        });

        sortedSchedules.forEach(schedule => {
            const card = new ScheduleCard(schedule, schedule.availability, schedule.bookingStatus);
            container.appendChild(card.element);
            
            // Bind booking actions
            this.bindScheduleCardEvents(card);
        });

        container.style.display = 'grid';
    }

    /**
     * Bind events for schedule card
     */
    bindScheduleCardEvents(card) {
        const bookBtn = card.element.querySelector('[data-action="book"]');
        const cancelBtn = card.element.querySelector('[data-action="cancel"]');

        if (bookBtn) {
            bookBtn.addEventListener('click', () => {
                this.handleBooking(card.schedule.id, card.schedule.schedule_type);
            });
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => {
                this.handleCancellation(card.schedule.id, card.schedule.schedule_type);
            });
        }
    }

    /**
     * Handle booking action
     */
    async handleBooking(scheduleId, scheduleType) {
        if (!this.currentWorkerId) {
            Toast.error('Worker ID is required');
            return;
        }

        const confirmed = await Modal.confirm(
            `Are you sure you want to book the ${scheduleType} bus slot?`,
            'Confirm Booking'
        );

        if (!confirmed) return;

        try {
            Loading.show('Processing booking...');
            
            const date = Utils.getTodayDate();
            const response = await API.createBooking(this.currentWorkerId, scheduleId, date);
            
            if (!response.success) {
                throw new Error(response.message || 'Booking failed');
            }

            const result = response.data;
            
            if (result.status === 'confirmed') {
                Toast.success('Booking confirmed successfully!');
            } else if (result.status === 'waitlisted') {
                Toast.warning(`Bus is full. You are #${result.position} on the waitlist.`);
            }
            
            // Refresh schedules
            await this.loadSchedules();
            
        } catch (error) {
            Utils.error.handle(error);
        } finally {
            Loading.hide();
        }
    }

    /**
     * Handle cancellation action
     */
    async handleCancellation(scheduleId, scheduleType) {
        if (!this.currentWorkerId) {
            Toast.error('Worker ID is required');
            return;
        }

        const confirmed = await Modal.confirm(
            `Are you sure you want to cancel your ${scheduleType} booking?`,
            'Confirm Cancellation'
        );

        if (!confirmed) return;

        try {
            Loading.show('Processing cancellation...');
            
            const date = Utils.getTodayDate();
            const response = await API.cancelBooking(this.currentWorkerId, scheduleId, date);
            
            if (!response.success) {
                throw new Error(response.message || 'Cancellation failed');
            }

            Toast.success('Booking cancelled successfully!');
            
            // Refresh schedules
            await this.loadSchedules();
            
        } catch (error) {
            Utils.error.handle(error);
        } finally {
            Loading.hide();
        }
    }

    /**
     * Load booking history
     */
    async loadBookingHistory() {
        try {
            // For now, we'll skip authentication and just show the interface
            // In a real implementation, this would require authentication
            const historyContainer = Utils.getElementById('booking-history');
            if (historyContainer) {
                historyContainer.style.display = 'none'; // Hide for now
            }
        } catch (error) {
            console.warn('Failed to load booking history:', error);
        }
    }

    /**
     * Show booking interface
     */
    showBookingInterface() {
        const bookingStatus = Utils.getElementById('booking-status');
        const schedulesContainer = Utils.getElementById('schedules-container');
        
        if (bookingStatus) {
            bookingStatus.style.display = 'block';
        }
        
        if (schedulesContainer) {
            schedulesContainer.style.display = 'grid';
        }
    }

    /**
     * Start real-time polling
     */
    startPolling() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
        }

        this.pollingInterval = setInterval(async () => {
            if (this.currentWorkerId && !document.hidden && navigator.onLine) {
                try {
                    await this.loadSchedules();
                } catch (error) {
                    console.warn('Polling update failed:', error);
                }
            }
        }, APP_CONFIG.POLLING_INTERVAL);
    }

    /**
     * Stop polling
     */
    stopPolling() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
        }
    }

    /**
     * Load saved worker ID
     */
    loadSavedWorkerId() {
        const savedWorkerId = Utils.storage.get(APP_CONFIG.STORAGE_KEYS.WORKER_ID);
        if (savedWorkerId) {
            const workerIdInput = Utils.getElementById('worker-id');
            if (workerIdInput) {
                workerIdInput.value = savedWorkerId;
                
                // Enable check status button if valid
                const checkStatusBtn = Utils.getElementById('check-status-btn');
                if (checkStatusBtn && Utils.isValidWorkerId(savedWorkerId)) {
                    checkStatusBtn.disabled = false;
                }
            }
        }
    }

    /**
     * Handle URL parameters
     */
    handleUrlParameters() {
        const params = Utils.url.getParams();
        
        if (params.worker_id && Utils.isValidWorkerId(params.worker_id)) {
            const workerIdInput = Utils.getElementById('worker-id');
            if (workerIdInput) {
                workerIdInput.value = params.worker_id;
                // Auto-check status
                setTimeout(() => this.onCheckStatus(), 100);
            }
        }
    }

    /**
     * Check connectivity
     */
    async checkConnectivity() {
        try {
            const isOnline = await Utils.network.checkConnectivity();
            this.updateConnectivityStatus(isOnline);
        } catch (error) {
            this.updateConnectivityStatus(false);
        }
    }

    /**
     * Update connectivity status
     */
    updateConnectivityStatus(isOnline) {
        this.offlineMode = !isOnline;
        const offlineIndicator = Utils.getElementById('offline-indicator');
        
        if (offlineIndicator) {
            if (isOnline) {
                offlineIndicator.classList.remove('show');
            } else {
                offlineIndicator.classList.add('show');
            }
        }
    }

    /**
     * Initialize offline indicator
     */
    initializeOfflineIndicator() {
        this.updateConnectivityStatus(navigator.onLine);
    }

    /**
     * Initialize notifications
     */
    async initializeNotifications() {
        if (Utils.device.supportsNotifications()) {
            const permission = await Notification.requestPermission();
            Utils.storage.set(APP_CONFIG.STORAGE_KEYS.NOTIFICATIONS, permission === 'granted');
        }
    }

    /**
     * Show loading screen
     */
    showLoadingScreen() {
        const loadingScreen = Utils.getElementById('loading-screen');
        const app = Utils.getElementById('app');
        
        if (loadingScreen) loadingScreen.style.display = 'flex';
        if (app) app.style.display = 'none';
    }

    /**
     * Hide loading screen
     */
    hideLoadingScreen() {
        const loadingScreen = Utils.getElementById('loading-screen');
        const app = Utils.getElementById('app');
        
        if (loadingScreen) {
            Utils.animation.fadeOut(loadingScreen, 500);
        }
        
        if (app) {
            app.style.display = 'block';
            Utils.animation.fadeIn(app, 500);
        }
    }

    /**
     * Handle online event
     */
    onOnline() {
        this.updateConnectivityStatus(true);
        Toast.success('Connection restored');
        
        // Refresh data if we have a worker ID
        if (this.currentWorkerId) {
            this.loadSchedules();
        }
    }

    /**
     * Handle offline event
     */
    onOffline() {
        this.updateConnectivityStatus(false);
        Toast.warning('You are now offline. Some features may not be available.');
    }

    /**
     * Handle visibility change
     */
    onVisibilityChange() {
        if (document.hidden) {
            this.stopPolling();
        } else if (this.currentWorkerId) {
            this.startPolling();
        }
    }

    /**
     * Handle before unload
     */
    onBeforeUnload() {
        this.stopPolling();
    }
}

/**
 * Initialize app when DOM is ready
 */
document.addEventListener('DOMContentLoaded', () => {
    window.busBookingApp = new BusBookingApp();
});

// Export for modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = BusBookingApp;
}