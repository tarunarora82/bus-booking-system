<?php
/**
 * Booking Controller
 * Handles all booking-related API endpoints
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../services/BookingService.php';
require_once __DIR__ . '/../services/EmailService.php';

class BookingController {
    private $bookingService;
    private $emailService;
    
    public function __construct() {
        $this->bookingService = new BookingService();
        $this->emailService = new EmailService();
    }
    
    /**
     * Create a new booking
     * POST /api/bookings
     */
    public function create() {
        try {
            $data = $this->getRequestData();
            
            // Validate required fields
            $required = ['worker_id', 'schedule_id', 'booking_date'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    Response::error("Field '$field' is required", 400);
                    return;
                }
            }
            
            // Validate worker ID format
            if (!$this->isValidWorkerId($data['worker_id'])) {
                Response::error('Invalid worker ID format', 400);
                return;
            }
            
            // Validate date format
            if (!$this->isValidDate($data['booking_date'])) {
                Response::error('Invalid date format. Use YYYY-MM-DD', 400);
                return;
            }
            
            // Check if booking date is not in the past
            if (strtotime($data['booking_date']) < strtotime('today')) {
                Response::error('Cannot book for past dates', 400);
                return;
            }
            
            // Create booking
            $result = $this->bookingService->createBooking(
                $data['worker_id'],
                $data['schedule_id'],
                $data['booking_date']
            );
            
            // Send confirmation email if booking confirmed
            if ($result['status'] === 'confirmed') {
                $this->sendBookingConfirmation($data['worker_id'], $result);
            } elseif ($result['status'] === 'waitlisted') {
                $this->sendWaitlistNotification($data['worker_id'], $result);
            }
            
            Response::success($result, 'Booking request processed successfully');
            
        } catch (Exception $e) {
            error_log("Booking creation error: " . $e->getMessage());
            Response::error('Failed to create booking: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Cancel a booking
     * DELETE /api/bookings
     */
    public function cancel() {
        try {
            $data = $this->getRequestData();
            
            // Validate required fields
            $required = ['worker_id', 'schedule_id', 'booking_date'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    Response::error("Field '$field' is required", 400);
                    return;
                }
            }
            
            // Cancel booking
            $result = $this->bookingService->cancelBooking(
                $data['worker_id'],
                $data['schedule_id'],
                $data['booking_date']
            );
            
            // Send cancellation confirmation
            $this->sendCancellationConfirmation($data['worker_id'], $result);
            
            Response::success($result, 'Booking cancelled successfully');
            
        } catch (Exception $e) {
            error_log("Booking cancellation error: " . $e->getMessage());
            Response::error('Failed to cancel booking: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get booking status for a worker
     * GET /api/booking-status?worker_id=123&schedule_id=1&date=2024-01-01
     */
    public function getStatus() {
        try {
            $workerId = $_GET['worker_id'] ?? '';
            $scheduleId = $_GET['schedule_id'] ?? '';
            $date = $_GET['date'] ?? '';
            
            if (empty($workerId) || empty($scheduleId) || empty($date)) {
                Response::error('worker_id, schedule_id, and date parameters are required', 400);
                return;
            }
            
            if (!$this->isValidWorkerId($workerId)) {
                Response::error('Invalid worker ID format', 400);
                return;
            }
            
            if (!$this->isValidDate($date)) {
                Response::error('Invalid date format. Use YYYY-MM-DD', 400);
                return;
            }
            
            $status = $this->bookingService->getBookingStatus($workerId, $scheduleId, $date);
            
            Response::success($status, 'Booking status retrieved successfully');
            
        } catch (Exception $e) {
            error_log("Booking status error: " . $e->getMessage());
            Response::error('Failed to get booking status: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get booking history for a worker
     * GET /api/bookings/history?worker_id=123&limit=10&offset=0
     */
    public function getHistory() {
        try {
            $workerId = $_GET['worker_id'] ?? '';
            $limit = min((int)($_GET['limit'] ?? 10), 50); // Max 50 records
            $offset = max((int)($_GET['offset'] ?? 0), 0);
            
            if (empty($workerId)) {
                Response::error('worker_id parameter is required', 400);
                return;
            }
            
            if (!$this->isValidWorkerId($workerId)) {
                Response::error('Invalid worker ID format', 400);
                return;
            }
            
            $history = $this->bookingService->getBookingHistory($workerId, $limit, $offset);
            
            Response::success($history, 'Booking history retrieved successfully');
            
        } catch (Exception $e) {
            error_log("Booking history error: " . $e->getMessage());
            Response::error('Failed to get booking history: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get all bookings for a specific date (admin only)
     * GET /api/bookings?date=2024-01-01&schedule_id=1
     */
    public function getBookings() {
        try {
            // This would require admin authentication in a real app
            $date = $_GET['date'] ?? '';
            $scheduleId = $_GET['schedule_id'] ?? '';
            
            if (empty($date)) {
                Response::error('date parameter is required', 400);
                return;
            }
            
            if (!$this->isValidDate($date)) {
                Response::error('Invalid date format. Use YYYY-MM-DD', 400);
                return;
            }
            
            $bookings = $this->bookingService->getBookingsByDate($date, $scheduleId);
            
            Response::success($bookings, 'Bookings retrieved successfully');
            
        } catch (Exception $e) {
            error_log("Get bookings error: " . $e->getMessage());
            Response::error('Failed to get bookings: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get waitlist for a specific schedule and date
     * GET /api/bookings/waitlist?schedule_id=1&date=2024-01-01
     */
    public function getWaitlist() {
        try {
            $scheduleId = $_GET['schedule_id'] ?? '';
            $date = $_GET['date'] ?? '';
            
            if (empty($scheduleId) || empty($date)) {
                Response::error('schedule_id and date parameters are required', 400);
                return;
            }
            
            if (!$this->isValidDate($date)) {
                Response::error('Invalid date format. Use YYYY-MM-DD', 400);
                return;
            }
            
            $waitlist = $this->bookingService->getWaitlist($scheduleId, $date);
            
            Response::success($waitlist, 'Waitlist retrieved successfully');
            
        } catch (Exception $e) {
            error_log("Get waitlist error: " . $e->getMessage());
            Response::error('Failed to get waitlist: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Send booking confirmation email
     */
    private function sendBookingConfirmation($workerId, $bookingData) {
        try {
            $this->emailService->sendBookingConfirmation($workerId, $bookingData);
        } catch (Exception $e) {
            error_log("Failed to send booking confirmation email: " . $e->getMessage());
        }
    }
    
    /**
     * Send waitlist notification email
     */
    private function sendWaitlistNotification($workerId, $bookingData) {
        try {
            $this->emailService->sendWaitlistNotification($workerId, $bookingData);
        } catch (Exception $e) {
            error_log("Failed to send waitlist notification email: " . $e->getMessage());
        }
    }
    
    /**
     * Send cancellation confirmation email
     */
    private function sendCancellationConfirmation($workerId, $cancellationData) {
        try {
            $this->emailService->sendCancellationConfirmation($workerId, $cancellationData);
        } catch (Exception $e) {
            error_log("Failed to send cancellation confirmation email: " . $e->getMessage());
        }
    }
    
    /**
     * Get request data from JSON body
     */
    private function getRequestData() {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON data');
        }
        
        return $data ?: [];
    }
    
    /**
     * Validate worker ID format
     */
    private function isValidWorkerId($workerId) {
        return preg_match('/^\d{7,10}$/', $workerId);
    }
    
    /**
     * Validate date format
     */
    private function isValidDate($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}

// Route handling based on HTTP method
$method = $_SERVER['REQUEST_METHOD'];
$controller = new BookingController();

// Parse the URL path
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

switch ($method) {
    case 'POST':
        $controller->create();
        break;
        
    case 'DELETE':
        $controller->cancel();
        break;
        
    case 'GET':
        // Check for specific endpoints
        if (end($pathParts) === 'history') {
            $controller->getHistory();
        } elseif (end($pathParts) === 'waitlist') {
            $controller->getWaitlist();
        } elseif (isset($_GET['worker_id']) && isset($_GET['schedule_id'])) {
            $controller->getStatus();
        } else {
            $controller->getBookings();
        }
        break;
        
    default:
        Response::error('Method not allowed', 405);
}