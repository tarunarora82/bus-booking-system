/**
 * UI Components and Reusable Functions
 */

/**
 * Toast Notification System
 */
class Toast {
    static container = null;

    static init() {
        if (!this.container) {
            this.container = Utils.getElementById('toast-container');
            if (!this.container) {
                this.container = Utils.createElement('div', {
                    id: 'toast-container',
                    className: 'toast-container'
                });
                document.body.appendChild(this.container);
            }
        }
    }

    static show(message, type = APP_CONFIG.TOAST_TYPES.INFO, duration = APP_CONFIG.TOAST_DURATION) {
        this.init();

        const toast = Utils.createElement('div', {
            className: `toast ${type}`
        });

        const content = Utils.createElement('div', {
            className: 'toast-content'
        }, message);

        const closeBtn = Utils.createElement('button', {
            className: 'toast-close',
            innerHTML: '&times;'
        });

        toast.appendChild(content);
        toast.appendChild(closeBtn);
        this.container.appendChild(toast);

        // Auto remove
        const timeoutId = setTimeout(() => {
            this.remove(toast);
        }, duration);

        // Manual close
        closeBtn.addEventListener('click', () => {
            clearTimeout(timeoutId);
            this.remove(toast);
        });

        // Animate in
        requestAnimationFrame(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateX(0)';
        });

        return toast;
    }

    static remove(toast) {
        if (toast && toast.parentNode) {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }
    }

    static success(message, duration) {
        return this.show(message, APP_CONFIG.TOAST_TYPES.SUCCESS, duration);
    }

    static error(message, duration) {
        return this.show(message, APP_CONFIG.TOAST_TYPES.ERROR, duration);
    }

    static warning(message, duration) {
        return this.show(message, APP_CONFIG.TOAST_TYPES.WARNING, duration);
    }

    static info(message, duration) {
        return this.show(message, APP_CONFIG.TOAST_TYPES.INFO, duration);
    }
}

/**
 * Modal Dialog System
 */
class Modal {
    constructor(options = {}) {
        this.options = {
            title: 'Confirm',
            message: 'Are you sure?',
            confirmText: 'Confirm',
            cancelText: 'Cancel',
            type: 'confirm',
            ...options
        };

        this.element = this.create();
        this.isOpen = false;
    }

    create() {
        const modal = Utils.createElement('div', {
            className: 'modal',
            id: `modal-${Utils.generateId()}`
        });

        const content = Utils.createElement('div', { className: 'modal-content' });
        
        const header = Utils.createElement('div', { className: 'modal-header' });
        const title = Utils.createElement('h3', { id: 'modal-title' }, this.options.title);
        const closeBtn = Utils.createElement('button', {
            className: 'btn btn-icon',
            innerHTML: '<i class="icon-close"></i>'
        });

        header.appendChild(title);
        header.appendChild(closeBtn);

        const body = Utils.createElement('div', { className: 'modal-body' });
        const message = Utils.createElement('p', { id: 'modal-message' }, this.options.message);
        body.appendChild(message);

        const footer = Utils.createElement('div', { className: 'modal-footer' });
        
        if (this.options.type === 'confirm') {
            const cancelBtn = Utils.createElement('button', {
                className: 'btn btn-secondary',
                id: 'modal-cancel'
            }, this.options.cancelText);

            const confirmBtn = Utils.createElement('button', {
                className: 'btn btn-primary',
                id: 'modal-confirm'
            }, this.options.confirmText);

            footer.appendChild(cancelBtn);
            footer.appendChild(confirmBtn);
        } else if (this.options.type === 'alert') {
            const okBtn = Utils.createElement('button', {
                className: 'btn btn-primary',
                id: 'modal-ok'
            }, 'OK');

            footer.appendChild(okBtn);
        }

        content.appendChild(header);
        content.appendChild(body);
        content.appendChild(footer);
        modal.appendChild(content);

        document.body.appendChild(modal);

        this.bindEvents();
        return modal;
    }

    bindEvents() {
        const closeBtn = this.element.querySelector('.btn-icon');
        const cancelBtn = this.element.querySelector('#modal-cancel');
        const confirmBtn = this.element.querySelector('#modal-confirm');
        const okBtn = this.element.querySelector('#modal-ok');

        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.close(false));
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => this.close(false));
        }

        if (confirmBtn) {
            confirmBtn.addEventListener('click', () => this.close(true));
        }

        if (okBtn) {
            okBtn.addEventListener('click', () => this.close(true));
        }

        // Close on backdrop click
        this.element.addEventListener('click', (e) => {
            if (e.target === this.element) {
                this.close(false);
            }
        });

        // Close on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen) {
                this.close(false);
            }
        });
    }

    open() {
        return new Promise((resolve) => {
            this.resolve = resolve;
            this.element.style.display = 'flex';
            this.isOpen = true;
            
            requestAnimationFrame(() => {
                this.element.classList.add('show');
            });
        });
    }

    close(confirmed = false) {
        this.element.classList.remove('show');
        this.isOpen = false;
        
        setTimeout(() => {
            this.element.style.display = 'none';
            if (this.resolve) {
                this.resolve(confirmed);
                this.resolve = null;
            }
        }, 300);
    }

    destroy() {
        if (this.element && this.element.parentNode) {
            this.element.parentNode.removeChild(this.element);
        }
    }

    static confirm(message, title = 'Confirm Action') {
        const modal = new Modal({
            title,
            message,
            type: 'confirm'
        });

        return modal.open().finally(() => {
            modal.destroy();
        });
    }

    static alert(message, title = 'Information') {
        const modal = new Modal({
            title,
            message,
            type: 'alert'
        });

        return modal.open().finally(() => {
            modal.destroy();
        });
    }
}

/**
 * Loading Indicator
 */
class Loading {
    static show(message = 'Loading...') {
        const existing = Utils.getElementById('loading-overlay');
        if (existing) {
            return;
        }

        const overlay = Utils.createElement('div', {
            id: 'loading-overlay',
            className: 'loading-screen'
        });

        const spinner = Utils.createElement('div', {
            className: 'loading-spinner'
        });

        const text = Utils.createElement('p', {}, message);

        overlay.appendChild(spinner);
        overlay.appendChild(text);
        document.body.appendChild(overlay);

        Utils.animation.fadeIn(overlay);
    }

    static hide() {
        const overlay = Utils.getElementById('loading-overlay');
        if (overlay) {
            Utils.animation.fadeOut(overlay, 300);
            setTimeout(() => {
                if (overlay.parentNode) {
                    overlay.parentNode.removeChild(overlay);
                }
            }, 300);
        }
    }
}

/**
 * Schedule Card Component
 */
class ScheduleCard {
    constructor(schedule, availability, bookingStatus) {
        this.schedule = schedule;
        this.availability = availability;
        this.bookingStatus = bookingStatus;
        this.element = this.create();
    }

    create() {
        const card = Utils.createElement('div', {
            className: `schedule-card ${this.schedule.schedule_type}`,
            'data-schedule-id': this.schedule.id
        });

        // Header
        const header = Utils.createElement('div', { className: 'schedule-header' });
        const title = Utils.createElement('h3', {}, `${this.schedule.schedule_type.charAt(0).toUpperCase() + this.schedule.schedule_type.slice(1)} Schedule`);
        
        const badge = Utils.createElement('span', {
            className: `badge ${this.schedule.is_mandatory ? 'mandatory' : 'optional'}`
        }, this.schedule.is_mandatory ? 'Mandatory' : 'Optional');

        header.appendChild(title);
        header.appendChild(badge);

        // Details
        const details = Utils.createElement('div', { className: 'schedule-details' });
        
        const busInfo = Utils.createElement('p', {}, `Bus: `);
        const busSpan = Utils.createElement('span', { id: `${this.schedule.schedule_type}-bus` }, this.schedule.bus_number);
        busInfo.appendChild(busSpan);

        const departureInfo = Utils.createElement('p', {}, `Departure: `);
        const departureSpan = Utils.createElement('span', { id: `${this.schedule.schedule_type}-time` }, Utils.formatTime(this.schedule.departure_time));
        departureInfo.appendChild(departureSpan);

        const availabilityInfo = Utils.createElement('p', {}, `Availability: `);
        const availabilitySpan = Utils.createElement('span', {
            id: `${this.schedule.schedule_type}-availability`,
            className: this.getAvailabilityClass()
        }, this.getAvailabilityText());
        availabilityInfo.appendChild(availabilitySpan);

        details.appendChild(busInfo);
        details.appendChild(departureInfo);
        details.appendChild(availabilityInfo);

        // Actions
        const actions = Utils.createElement('div', { className: 'booking-actions' });
        const buttons = this.createActionButtons();
        buttons.forEach(button => actions.appendChild(button));

        card.appendChild(header);
        card.appendChild(details);
        card.appendChild(actions);

        return card;
    }

    getAvailabilityClass() {
        if (this.availability.available_count === 0) {
            return 'availability full';
        } else if (this.availability.available_count <= 5) {
            return 'availability low';
        } else {
            return 'availability good';
        }
    }

    getAvailabilityText() {
        let text = `${this.availability.available_count} of ${this.availability.capacity} seats available`;
        if (this.availability.waitlist_count > 0) {
            text += ` (${this.availability.waitlist_count} on waitlist)`;
        }
        return text;
    }

    createActionButtons() {
        const buttons = [];
        const canBook = Utils.canBookSlot(this.schedule.departure_time, Utils.getTodayDate());

        if (this.bookingStatus.status === APP_CONFIG.BOOKING_STATUS.CONFIRMED) {
            const cancelBtn = Utils.createElement('button', {
                className: 'btn btn-danger',
                id: `${this.schedule.schedule_type}-cancel-btn`,
                'data-action': 'cancel'
            });
            cancelBtn.innerHTML = '<i class="icon-close"></i> Cancel Booking';
            
            if (!canBook) {
                cancelBtn.disabled = true;
                cancelBtn.title = 'Booking cutoff time has passed';
            }
            
            buttons.push(cancelBtn);
            
        } else if (this.bookingStatus.status === APP_CONFIG.BOOKING_STATUS.WAITLISTED) {
            const cancelBtn = Utils.createElement('button', {
                className: 'btn btn-warning',
                id: `${this.schedule.schedule_type}-cancel-btn`,
                'data-action': 'cancel'
            });
            cancelBtn.innerHTML = '<i class="icon-close"></i> Cancel Waitlist';
            buttons.push(cancelBtn);
            
        } else {
            const bookBtn = Utils.createElement('button', {
                className: 'btn btn-primary',
                id: `${this.schedule.schedule_type}-book-btn`,
                'data-action': 'book'
            });

            if (this.availability.can_book && canBook) {
                bookBtn.innerHTML = '<i class="icon-check"></i> Book Seat';
            } else if (!canBook) {
                bookBtn.innerHTML = '<i class="icon-clock"></i> Booking Closed';
                bookBtn.disabled = true;
                bookBtn.title = 'Booking cutoff time has passed';
            } else {
                bookBtn.innerHTML = '<i class="icon-user"></i> Join Waitlist';
            }
            
            buttons.push(bookBtn);
        }

        return buttons;
    }

    updateAvailability(newAvailability) {
        this.availability = newAvailability;
        const availabilitySpan = this.element.querySelector(`#${this.schedule.schedule_type}-availability`);
        if (availabilitySpan) {
            availabilitySpan.textContent = this.getAvailabilityText();
            availabilitySpan.className = this.getAvailabilityClass();
        }
    }

    updateBookingStatus(newStatus) {
        this.bookingStatus = newStatus;
        const actions = this.element.querySelector('.booking-actions');
        if (actions) {
            actions.innerHTML = '';
            const buttons = this.createActionButtons();
            buttons.forEach(button => actions.appendChild(button));
        }
    }
}

/**
 * Booking History Component
 */
class BookingHistory {
    constructor(bookings) {
        this.bookings = bookings;
        this.element = this.create();
    }

    create() {
        const container = Utils.createElement('div', {
            className: 'booking-history'
        });

        const title = Utils.createElement('h3', {}, 'Your Recent Bookings');
        container.appendChild(title);

        const list = Utils.createElement('div', {
            className: 'history-list',
            id: 'history-list'
        });

        if (this.bookings.length === 0) {
            const emptyState = Utils.createElement('p', {
                className: 'empty-state'
            }, 'No bookings found.');
            list.appendChild(emptyState);
        } else {
            this.bookings.forEach(booking => {
                const item = this.createHistoryItem(booking);
                list.appendChild(item);
            });
        }

        container.appendChild(list);
        return container;
    }

    createHistoryItem(booking) {
        const item = Utils.createElement('div', {
            className: 'history-item'
        });

        const info = Utils.createElement('div', {
            className: 'history-item-info'
        });

        const dateText = Utils.createElement('strong', {}, Utils.formatDate(booking.booking_date));
        const scheduleText = Utils.createElement('span', {}, ` - ${booking.schedule_type.charAt(0).toUpperCase() + booking.schedule_type.slice(1)} (${booking.bus_number})`);
        const timeText = Utils.createElement('small', {}, ` at ${Utils.formatTime(booking.departure_time)}`);

        info.appendChild(dateText);
        info.appendChild(scheduleText);
        info.appendChild(Utils.createElement('br'));
        info.appendChild(timeText);

        const status = Utils.createElement('span', {
            className: `history-item-status ${booking.status}`
        }, booking.status.charAt(0).toUpperCase() + booking.status.slice(1));

        item.appendChild(info);
        item.appendChild(status);

        return item;
    }
}

/**
 * Install Prompt Component
 */
class InstallPrompt {
    constructor() {
        this.deferredPrompt = null;
        this.element = null;
        this.init();
    }

    init() {
        // Listen for the beforeinstallprompt event
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            this.deferredPrompt = e;
            this.show();
        });

        // Hide prompt if already installed
        window.addEventListener('appinstalled', () => {
            this.hide();
            Toast.success('App installed successfully!');
        });
    }

    show() {
        if (Utils.storage.get(APP_CONFIG.STORAGE_KEYS.INSTALL_DISMISSED)) {
            return;
        }

        this.element = Utils.getElementById('install-prompt');
        if (!this.element) {
            this.createElement();
        }

        this.element.style.display = 'block';
        requestAnimationFrame(() => {
            this.element.classList.add('show');
        });

        this.bindEvents();
    }

    createElement() {
        this.element = Utils.createElement('div', {
            id: 'install-prompt',
            className: 'install-prompt'
        });

        const content = Utils.createElement('div', {
            className: 'install-prompt-content'
        });

        const icon = Utils.createElement('i', {
            className: 'icon-download'
        });

        const textDiv = Utils.createElement('div', {
            className: 'install-prompt-text'
        });

        const title = Utils.createElement('h4', {}, 'Install Bus Booking App');
        const description = Utils.createElement('p', {}, 'Get quick access to bus bookings right from your home screen');

        textDiv.appendChild(title);
        textDiv.appendChild(description);

        const actions = Utils.createElement('div', {
            className: 'install-prompt-actions'
        });

        const dismissBtn = Utils.createElement('button', {
            id: 'install-dismiss',
            className: 'btn btn-secondary'
        }, 'Not now');

        const installBtn = Utils.createElement('button', {
            id: 'install-accept',
            className: 'btn btn-primary'
        }, 'Install');

        actions.appendChild(dismissBtn);
        actions.appendChild(installBtn);

        content.appendChild(icon);
        content.appendChild(textDiv);
        content.appendChild(actions);

        this.element.appendChild(content);
        document.body.appendChild(this.element);
    }

    bindEvents() {
        const dismissBtn = this.element.querySelector('#install-dismiss');
        const installBtn = this.element.querySelector('#install-accept');

        if (dismissBtn) {
            dismissBtn.addEventListener('click', () => {
                this.dismiss();
            });
        }

        if (installBtn) {
            installBtn.addEventListener('click', () => {
                this.install();
            });
        }
    }

    async install() {
        if (!this.deferredPrompt) {
            return;
        }

        this.deferredPrompt.prompt();
        const { outcome } = await this.deferredPrompt.userChoice;
        
        if (outcome === 'accepted') {
            Toast.success('App installation started');
        } else {
            Toast.info('App installation cancelled');
        }

        this.deferredPrompt = null;
        this.hide();
    }

    dismiss() {
        Utils.storage.set(APP_CONFIG.STORAGE_KEYS.INSTALL_DISMISSED, true);
        this.hide();
    }

    hide() {
        if (this.element) {
            this.element.classList.remove('show');
            setTimeout(() => {
                this.element.style.display = 'none';
            }, 300);
        }
    }
}

// Initialize global components
window.Toast = Toast;
window.Modal = Modal;
window.Loading = Loading;
window.InstallPrompt = InstallPrompt;

// Export for modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        Toast,
        Modal,
        Loading,
        ScheduleCard,
        BookingHistory,
        InstallPrompt
    };
}