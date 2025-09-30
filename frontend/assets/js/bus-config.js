/**
 * Bus Configuration Management
 * This file contains all bus and employee configuration
 */

// Bus Configuration
const BUS_CONFIG = {
    // Bus fleet information
    buses: [
        {
            id: 1,
            bus_number: 'BUS-001',
            name: 'Morning Express',
            departure_time: '08:00',
            arrival_time: '18:00',
            capacity: 45,
            route: 'City Center ↔ Industrial Park',
            type: 'morning',
            driver_name: 'John Doe',
            driver_contact: '+1-234-567-8901',
            active: true
        },
        {
            id: 2,
            bus_number: 'BUS-002',
            name: 'Evening Shuttle',
            departure_time: '18:30',
            arrival_time: '04:30',
            capacity: 45,
            route: 'Industrial Park ↔ City Center',
            type: 'evening',
            driver_name: 'Jane Smith',
            driver_contact: '+1-234-567-8902',
            active: true
        },
        {
            id: 3,
            bus_number: 'BUS-003',
            name: 'Night Service',
            departure_time: '22:00',
            arrival_time: '08:00',
            capacity: 40,
            route: 'City Center ↔ Industrial Park',
            type: 'night',
            driver_name: 'Mike Johnson',
            driver_contact: '+1-234-567-8903',
            active: true
        }
    ],

    // Email configuration for notifications
    email: {
        smtp_server: 'smtp.company.com',
        smtp_port: 587,
        from_email: 'bus-booking@company.com',
        from_name: 'Bus Booking System',
        admin_email: 'transport-admin@company.com'
    },

    // Employee email domains (for validation)
    valid_email_domains: [
        '@company.com',
        '@subsidiary.com',
        '@contractor.company.com'
    ],

    // System settings
    settings: {
        booking_window_hours: 48, // How many hours in advance can book
        cancellation_window_hours: 2, // How many hours before departure can cancel
        max_bookings_per_day: 1, // Maximum bookings per employee per day
        notification_enabled: true,
        sms_enabled: false
    }
};

// Employee Management Functions
const EMPLOYEE_MANAGER = {
    /**
     * Register employee email for notifications
     */
    registerEmployeeEmail: (employeeId, email, name = '', department = '') => {
        // Validate email domain
        const isValidDomain = BUS_CONFIG.valid_email_domains.some(domain => 
            email.toLowerCase().endsWith(domain.toLowerCase())
        );
        
        if (!isValidDomain) {
            throw new Error(`Email must be from a valid company domain: ${BUS_CONFIG.valid_email_domains.join(', ')}`);
        }

        // Store in localStorage for demo purposes
        // In production, this would be stored in a database
        const employees = JSON.parse(localStorage.getItem('employees') || '{}');
        employees[employeeId] = {
            email: email.toLowerCase(),
            name: name,
            department: department,
            registered_at: new Date().toISOString(),
            notification_preferences: {
                booking_confirmation: true,
                booking_reminder: true,
                booking_cancellation: true,
                schedule_changes: true
            }
        };
        
        localStorage.setItem('employees', JSON.stringify(employees));
        
        return {
            success: true,
            message: 'Employee email registered successfully',
            employee: employees[employeeId]
        };
    },

    /**
     * Get employee information
     */
    getEmployeeInfo: (employeeId) => {
        const employees = JSON.parse(localStorage.getItem('employees') || '{}');
        return employees[employeeId] || null;
    },

    /**
     * Update employee email
     */
    updateEmployeeEmail: (employeeId, newEmail) => {
        const employees = JSON.parse(localStorage.getItem('employees') || '{}');
        if (!employees[employeeId]) {
            throw new Error('Employee not found');
        }

        // Validate email domain
        const isValidDomain = BUS_CONFIG.valid_email_domains.some(domain => 
            newEmail.toLowerCase().endsWith(domain.toLowerCase())
        );
        
        if (!isValidDomain) {
            throw new Error(`Email must be from a valid company domain: ${BUS_CONFIG.valid_email_domains.join(', ')}`);
        }

        employees[employeeId].email = newEmail.toLowerCase();
        employees[employeeId].updated_at = new Date().toISOString();
        
        localStorage.setItem('employees', JSON.stringify(employees));
        
        return {
            success: true,
            message: 'Employee email updated successfully',
            employee: employees[employeeId]
        };
    },

    /**
     * Get all registered employees
     */
    getAllEmployees: () => {
        return JSON.parse(localStorage.getItem('employees') || '{}');
    }
};

// Bus Management Functions
const BUS_MANAGER = {
    /**
     * Add a new bus to the fleet
     */
    addBus: (busData) => {
        const buses = JSON.parse(localStorage.getItem('bus_fleet') || JSON.stringify(BUS_CONFIG.buses));
        
        const newBus = {
            id: Math.max(...buses.map(b => b.id)) + 1,
            bus_number: busData.bus_number,
            name: busData.name,
            departure_time: busData.departure_time,
            arrival_time: busData.arrival_time,
            capacity: parseInt(busData.capacity),
            route: busData.route,
            type: busData.type,
            driver_name: busData.driver_name || '',
            driver_contact: busData.driver_contact || '',
            active: true,
            created_at: new Date().toISOString()
        };
        
        buses.push(newBus);
        localStorage.setItem('bus_fleet', JSON.stringify(buses));
        
        return {
            success: true,
            message: 'Bus added successfully',
            bus: newBus
        };
    },

    /**
     * Update bus information
     */
    updateBus: (busId, updateData) => {
        const buses = JSON.parse(localStorage.getItem('bus_fleet') || JSON.stringify(BUS_CONFIG.buses));
        const busIndex = buses.findIndex(b => b.id === parseInt(busId));
        
        if (busIndex === -1) {
            throw new Error('Bus not found');
        }
        
        buses[busIndex] = {
            ...buses[busIndex],
            ...updateData,
            updated_at: new Date().toISOString()
        };
        
        localStorage.setItem('bus_fleet', JSON.stringify(buses));
        
        return {
            success: true,
            message: 'Bus updated successfully',
            bus: buses[busIndex]
        };
    },

    /**
     * Get all buses
     */
    getAllBuses: () => {
        return JSON.parse(localStorage.getItem('bus_fleet') || JSON.stringify(BUS_CONFIG.buses));
    },

    /**
     * Deactivate a bus
     */
    deactivateBus: (busId) => {
        return BUS_MANAGER.updateBus(busId, { active: false });
    },

    /**
     * Activate a bus
     */
    activateBus: (busId) => {
        return BUS_MANAGER.updateBus(busId, { active: true });
    }
};

// Email Notification Functions (Mock implementation)
const EMAIL_SERVICE = {
    /**
     * Send booking confirmation email
     */
    sendBookingConfirmation: async (employeeId, bookingData) => {
        const employee = EMPLOYEE_MANAGER.getEmployeeInfo(employeeId);
        if (!employee || !employee.email) {
            console.warn(`No email found for employee ${employeeId}`);
            return { success: false, message: 'Employee email not found' };
        }

        // Mock email sending
        console.log(`📧 Sending booking confirmation to ${employee.email}:`, {
            to: employee.email,
            subject: `Bus Booking Confirmation - ${bookingData.bus_number}`,
            body: `
                Dear ${employee.name || 'Employee'},
                
                Your bus booking has been confirmed!
                
                Booking Details:
                - Booking ID: ${bookingData.booking_id}
                - Bus: ${bookingData.bus_number} (${bookingData.bus_name})
                - Date: ${bookingData.date}
                - Departure: ${bookingData.departure_time}
                - Route: ${bookingData.route}
                
                Please be at the pickup point 10 minutes before departure.
                
                Best regards,
                Bus Booking System
            `
        });

        return { success: true, message: 'Confirmation email sent' };
    },

    /**
     * Send booking cancellation email
     */
    sendBookingCancellation: async (employeeId, bookingData) => {
        const employee = EMPLOYEE_MANAGER.getEmployeeInfo(employeeId);
        if (!employee || !employee.email) {
            console.warn(`No email found for employee ${employeeId}`);
            return { success: false, message: 'Employee email not found' };
        }

        // Mock email sending
        console.log(`📧 Sending cancellation notice to ${employee.email}:`, {
            to: employee.email,
            subject: `Bus Booking Cancelled - ${bookingData.bus_number}`,
            body: `
                Dear ${employee.name || 'Employee'},
                
                Your bus booking has been cancelled.
                
                Cancelled Booking:
                - Booking ID: ${bookingData.booking_id}
                - Bus: ${bookingData.bus_number}
                - Date: ${bookingData.date}
                
                You can make a new booking anytime.
                
                Best regards,
                Bus Booking System
            `
        });

        return { success: true, message: 'Cancellation email sent' };
    }
};

// Make available globally
window.BUS_CONFIG = BUS_CONFIG;
window.EMPLOYEE_MANAGER = EMPLOYEE_MANAGER;
window.BUS_MANAGER = BUS_MANAGER;
window.EMAIL_SERVICE = EMAIL_SERVICE;

// Initialize default data if not exists
document.addEventListener('DOMContentLoaded', () => {
    // Initialize bus fleet if not exists
    if (!localStorage.getItem('bus_fleet')) {
        localStorage.setItem('bus_fleet', JSON.stringify(BUS_CONFIG.buses));
    }
    
    // Initialize employees if not exists
    if (!localStorage.getItem('employees')) {
        localStorage.setItem('employees', JSON.stringify({}));
    }
    
    console.log('Bus configuration initialized');
});