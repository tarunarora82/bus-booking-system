<?php
/**
 * Bus Booking System - Main Entry Point
 * Handles all API requests and routes them to appropriate controllers
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/bootstrap.php';

use BusBooking\Core\Router;
use BusBooking\Core\Request;
use BusBooking\Core\Response;
use BusBooking\Middleware\CorsMiddleware;
use BusBooking\Middleware\AuthMiddleware;
use BusBooking\Middleware\RateLimitMiddleware;
use BusBooking\Controllers\AuthController;
use BusBooking\Controllers\BookingController;
use BusBooking\Controllers\AdminController;
use BusBooking\Controllers\ScheduleController;
use BusBooking\Controllers\UserController;

// Initialize request and response objects
$request = new Request();
$response = new Response();

// Initialize router
$router = new Router();

// Apply global middleware
$router->addMiddleware(new CorsMiddleware());
$router->addMiddleware(new RateLimitMiddleware());

try {
    // Authentication routes (no auth required)
    $router->post('/auth/login', [AuthController::class, 'login']);
    $router->post('/auth/verify', [AuthController::class, 'verify']);
    $router->post('/auth/logout', [AuthController::class, 'logout']);

    // Public routes (no auth required)
    $router->get('/schedules/available', [ScheduleController::class, 'getAvailableSchedules']);
    $router->get('/schedules/{schedule_id}/availability/{date}', [ScheduleController::class, 'getAvailability']);

    // Protected user routes (require authentication)
    $router->group('/api', [AuthMiddleware::class], function($router) {
        // User profile
        $router->get('/user/profile', [UserController::class, 'getProfile']);
        $router->put('/user/profile', [UserController::class, 'updateProfile']);
        
        // Booking operations
        $router->post('/bookings', [BookingController::class, 'createBooking']);
        $router->get('/bookings/my-bookings', [BookingController::class, 'getMyBookings']);
        $router->delete('/bookings', [BookingController::class, 'cancelBooking']);
        $router->get('/bookings/status/{worker_id}/{schedule_id}/{date}', [BookingController::class, 'getBookingStatus']);
        $router->get('/bookings/search/{worker_id}', [BookingController::class, 'searchBookings']);
        
        // Real-time availability
        $router->get('/realtime/availability/{schedule_id}/{date}', [ScheduleController::class, 'getRealTimeAvailability']);
    });

    // Admin routes (require admin authentication)
    $router->group('/admin', [AuthMiddleware::class, 'admin'], function($router) {
        // Dashboard
        $router->get('/dashboard', [AdminController::class, 'getDashboard']);
        
        // Bus management
        $router->get('/buses', [AdminController::class, 'getBuses']);
        $router->post('/buses', [AdminController::class, 'createBus']);
        $router->put('/buses/{id}', [AdminController::class, 'updateBus']);
        $router->delete('/buses/{id}', [AdminController::class, 'deleteBus']);
        
        // Schedule management
        $router->get('/schedules', [AdminController::class, 'getSchedules']);
        $router->post('/schedules', [AdminController::class, 'createSchedule']);
        $router->put('/schedules/{id}', [AdminController::class, 'updateSchedule']);
        $router->delete('/schedules/{id}', [AdminController::class, 'deleteSchedule']);
        
        // Booking management
        $router->get('/bookings/reports', [AdminController::class, 'getBookingReports']);
        $router->put('/bookings/{id}/mark-attendance', [AdminController::class, 'markAttendance']);
        $router->get('/bookings/export/{format}', [AdminController::class, 'exportBookings']);
        
        // Holiday management
        $router->get('/holidays', [AdminController::class, 'getHolidays']);
        $router->post('/holidays', [AdminController::class, 'createHoliday']);
        $router->delete('/holidays/{id}', [AdminController::class, 'deleteHoliday']);
        
        // System settings
        $router->get('/settings', [AdminController::class, 'getSettings']);
        $router->put('/settings', [AdminController::class, 'updateSettings']);
        
        // Audit logs
        $router->get('/audit-logs', [AdminController::class, 'getAuditLogs']);
        
        // Walk-in passengers
        $router->post('/walk-in', [AdminController::class, 'addWalkInPassenger']);
        $router->get('/walk-in/{date}', [AdminController::class, 'getWalkInPassengers']);
    });

    // WebSocket endpoint for real-time updates
    $router->get('/ws/booking-updates', [BookingController::class, 'handleWebSocket']);

    // Health check endpoint
    $router->get('/health', function() {
        return [
            'status' => 'healthy',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0.0'
        ];
    });

    // Process the request
    $result = $router->handle($request);
    
    // Send response
    $response->json($result);

} catch (Exception $e) {
    // Log error
    error_log("API Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    
    // Send error response
    $response->setStatusCode(500);
    $response->json([
        'success' => false,
        'message' => 'Internal server error',
        'error_code' => 'INTERNAL_ERROR'
    ]);
}