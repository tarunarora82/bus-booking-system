<?php
/**
 * Schedule Controller
 * Handles schedule-related API endpoints
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';

class ScheduleController {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get available schedules for a date
     * GET /api/schedules?date=2024-01-01
     */
    public function getSchedules() {
        try {
            $date = $_GET['date'] ?? date('Y-m-d');
            
            if (!$this->isValidDate($date)) {
                Response::error('Invalid date format. Use YYYY-MM-DD', 400);
                return;
            }
            
            // Get schedules with bus information
            $query = "
                SELECT 
                    s.id,
                    s.schedule_type,
                    s.departure_time,
                    s.arrival_time,
                    s.is_active,
                    b.id as bus_id,
                    b.bus_number,
                    b.capacity,
                    b.route,
                    b.is_active as bus_active
                FROM schedules s
                JOIN buses b ON s.bus_id = b.id
                WHERE s.is_active = 1 
                AND b.is_active = 1
                AND s.schedule_type IN ('morning', 'evening')
                ORDER BY s.schedule_type, s.departure_time
            ";
            
            $schedules = $this->db->fetchAll($query);
            
            // Add availability information for each schedule
            foreach ($schedules as &$schedule) {
                $availability = $this->getScheduleAvailability($schedule['id'], $date);
                $schedule['availability'] = $availability;
            }
            
            Response::success([
                'schedules' => $schedules,
                'date' => $date,
                'total_count' => count($schedules)
            ], 'Schedules retrieved successfully');
            
        } catch (Exception $e) {
            error_log("Get schedules error: " . $e->getMessage());
            Response::error('Failed to retrieve schedules: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get availability for a specific schedule
     * GET /api/schedules/{id}/availability?date=2024-01-01
     */
    public function getAvailability() {
        try {
            // Extract schedule ID from URL
            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $pathParts = explode('/', trim($path, '/'));
            $scheduleId = null;
            
            foreach ($pathParts as $i => $part) {
                if ($part === 'schedules' && isset($pathParts[$i + 1])) {
                    $scheduleId = $pathParts[$i + 1];
                    break;
                }
            }
            
            if (!$scheduleId || !is_numeric($scheduleId)) {
                Response::error('Invalid schedule ID', 400);
                return;
            }
            
            $date = $_GET['date'] ?? date('Y-m-d');
            
            if (!$this->isValidDate($date)) {
                Response::error('Invalid date format. Use YYYY-MM-DD', 400);
                return;
            }
            
            $availability = $this->getScheduleAvailability($scheduleId, $date);
            
            Response::success($availability, 'Schedule availability retrieved successfully');
            
        } catch (Exception $e) {
            error_log("Get schedule availability error: " . $e->getMessage());
            Response::error('Failed to retrieve schedule availability: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get schedule details by ID
     * GET /api/schedules/{id}
     */
    public function getScheduleById() {
        try {
            // Extract schedule ID from URL
            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $pathParts = explode('/', trim($path, '/'));
            $scheduleId = null;
            
            foreach ($pathParts as $i => $part) {
                if ($part === 'schedules' && isset($pathParts[$i + 1])) {
                    $scheduleId = $pathParts[$i + 1];
                    break;
                }
            }
            
            if (!$scheduleId || !is_numeric($scheduleId)) {
                Response::error('Invalid schedule ID', 400);
                return;
            }
            
            $query = "
                SELECT 
                    s.id,
                    s.schedule_type,
                    s.departure_time,
                    s.arrival_time,
                    s.is_active,
                    s.created_at,
                    s.updated_at,
                    b.id as bus_id,
                    b.bus_number,
                    b.capacity,
                    b.route,
                    b.is_active as bus_active
                FROM schedules s
                JOIN buses b ON s.bus_id = b.id
                WHERE s.id = :schedule_id
            ";
            
            $schedule = $this->db->fetch($query, ['schedule_id' => $scheduleId]);
            
            if (!$schedule) {
                Response::error('Schedule not found', 404);
                return;
            }
            
            Response::success($schedule, 'Schedule retrieved successfully');
            
        } catch (Exception $e) {
            error_log("Get schedule by ID error: " . $e->getMessage());
            Response::error('Failed to retrieve schedule: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get schedule availability data
     */
    private function getScheduleAvailability($scheduleId, $date) {
        try {
            // Get bus capacity
            $capacityQuery = "
                SELECT b.capacity 
                FROM schedules s 
                JOIN buses b ON s.bus_id = b.id 
                WHERE s.id = :schedule_id
            ";
            $capacityResult = $this->db->fetch($capacityQuery, ['schedule_id' => $scheduleId]);
            $capacity = $capacityResult['capacity'] ?? 50;
            
            // Get booking counts
            $bookingQuery = "
                SELECT 
                    COUNT(*) as booked_count,
                    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_count,
                    SUM(CASE WHEN status = 'waitlisted' THEN 1 ELSE 0 END) as waitlist_count
                FROM bookings 
                WHERE schedule_id = :schedule_id 
                AND booking_date = :date 
                AND status IN ('confirmed', 'waitlisted')
            ";
            
            $bookingStats = $this->db->fetch($bookingQuery, [
                'schedule_id' => $scheduleId,
                'date' => $date
            ]);
            
            $confirmedCount = (int)($bookingStats['confirmed_count'] ?? 0);
            $waitlistCount = (int)($bookingStats['waitlist_count'] ?? 0);
            $totalBooked = (int)($bookingStats['booked_count'] ?? 0);
            
            $availableCount = max(0, $capacity - $confirmedCount);
            $canBook = $confirmedCount < $capacity || $waitlistCount < 20; // Max 20 waitlist
            
            return [
                'schedule_id' => (int)$scheduleId,
                'date' => $date,
                'capacity' => (int)$capacity,
                'booked_count' => $totalBooked,
                'confirmed_count' => $confirmedCount,
                'waitlist_count' => $waitlistCount,
                'available_count' => $availableCount,
                'can_book' => $canBook,
                'is_full' => $confirmedCount >= $capacity,
                'waitlist_available' => $waitlistCount < 20
            ];
            
        } catch (Exception $e) {
            error_log("Get schedule availability error: " . $e->getMessage());
            
            // Return safe defaults on error
            return [
                'schedule_id' => (int)$scheduleId,
                'date' => $date,
                'capacity' => 50,
                'booked_count' => 0,
                'confirmed_count' => 0,
                'waitlist_count' => 0,
                'available_count' => 50,
                'can_book' => true,
                'is_full' => false,
                'waitlist_available' => true
            ];
        }
    }
    
    /**
     * Get all buses (admin function)
     * GET /api/schedules/buses
     */
    public function getBuses() {
        try {
            $query = "
                SELECT 
                    id,
                    bus_number,
                    capacity,
                    route,
                    is_active,
                    created_at,
                    updated_at
                FROM buses 
                ORDER BY bus_number
            ";
            
            $buses = $this->db->fetchAll($query);
            
            Response::success([
                'buses' => $buses,
                'total_count' => count($buses)
            ], 'Buses retrieved successfully');
            
        } catch (Exception $e) {
            error_log("Get buses error: " . $e->getMessage());
            Response::error('Failed to retrieve buses: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get schedule statistics for a date range
     * GET /api/schedules/stats?start_date=2024-01-01&end_date=2024-01-31
     */
    public function getStatistics() {
        try {
            $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $endDate = $_GET['end_date'] ?? date('Y-m-d');
            
            if (!$this->isValidDate($startDate) || !$this->isValidDate($endDate)) {
                Response::error('Invalid date format. Use YYYY-MM-DD', 400);
                return;
            }
            
            if (strtotime($startDate) > strtotime($endDate)) {
                Response::error('start_date must be before end_date', 400);
                return;
            }
            
            // Get booking statistics
            $statsQuery = "
                SELECT 
                    s.schedule_type,
                    COUNT(b.id) as total_bookings,
                    SUM(CASE WHEN b.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
                    SUM(CASE WHEN b.status = 'waitlisted' THEN 1 ELSE 0 END) as waitlisted_bookings,
                    SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
                    AVG(bus.capacity) as avg_capacity
                FROM schedules s
                LEFT JOIN bookings b ON s.id = b.schedule_id 
                    AND b.booking_date BETWEEN :start_date AND :end_date
                JOIN buses bus ON s.bus_id = bus.id
                WHERE s.is_active = 1
                GROUP BY s.schedule_type
                ORDER BY s.schedule_type
            ";
            
            $stats = $this->db->fetchAll($statsQuery, [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            
            // Get daily booking trends
            $trendQuery = "
                SELECT 
                    booking_date,
                    COUNT(*) as total_bookings,
                    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings
                FROM bookings 
                WHERE booking_date BETWEEN :start_date AND :end_date
                GROUP BY booking_date
                ORDER BY booking_date
            ";
            
            $trends = $this->db->fetchAll($trendQuery, [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            
            Response::success([
                'statistics' => $stats,
                'trends' => $trends,
                'date_range' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]
            ], 'Schedule statistics retrieved successfully');
            
        } catch (Exception $e) {
            error_log("Get schedule statistics error: " . $e->getMessage());
            Response::error('Failed to retrieve schedule statistics: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Validate date format
     */
    private function isValidDate($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}

// Route handling based on URL path
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));
$controller = new ScheduleController();

// Determine which method to call based on the URL structure
if (in_array('availability', $pathParts)) {
    $controller->getAvailability();
} elseif (in_array('buses', $pathParts)) {
    $controller->getBuses();
} elseif (in_array('stats', $pathParts)) {
    $controller->getStatistics();
} elseif (count($pathParts) >= 2 && is_numeric($pathParts[1])) {
    $controller->getScheduleById();
} else {
    $controller->getSchedules();
}