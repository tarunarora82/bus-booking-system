&lt;?php
require_once '../config/database.php';

/**
 * Real-time Bus Booking API
 * Production-ready with proper concurrency control and corporate proxy support
 */

// Corporate proxy and CORS handling
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');
header('Content-Type: application/json; charset=UTF-8');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// Get real client IP behind corporate proxy
function getRealClientIP() {
    $headers = [
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP', 
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

// Log proxy information for debugging
error_log("API Request: " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI'] . " from IP: " . getRealClientIP());

class BusBookingService {
    private $db;
    
    public function __construct() {
        $this-&gt;db = DatabaseConfig::getInstance()-&gt;getConnection();
    }
    
    /**
     * Get all available buses with real-time seat availability
     */
    public function getAvailableBuses($date = null) {
        try {
            if ($date === null) {
                $date = date('Y-m-d');
            }
            
            // Validate date
            $dateError = Validator::validateDate($date);
            if ($dateError) {
                throw new Exception($dateError);
            }
            
            $sql = "
                SELECT 
                    b.id as bus_id,
                    b.bus_number,
                    b.bus_name,
                    b.departure_time,
                    b.arrival_time,
                    b.total_capacity,
                    b.route,
                    b.bus_type,
                    b.driver_name,
                    b.driver_contact,
                    COALESCE(booked.booked_seats, 0) as booked_seats,
                    (b.total_capacity - COALESCE(booked.booked_seats, 0)) as available_seats
                FROM buses b
                LEFT JOIN (
                    SELECT 
                        bus_id,
                        COUNT(*) as booked_seats
                    FROM bookings 
                    WHERE booking_date = ? 
                    AND status = 'confirmed'
                    GROUP BY bus_id
                ) booked ON b.id = booked.bus_id
                WHERE b.is_active = TRUE
                ORDER BY b.departure_time
            ";
            
            $stmt = $this-&gt;db-&gt;prepare($sql);
            $stmt-&gt;execute([$date]);
            $buses = $stmt-&gt;fetchAll();
            
            // Format time fields for display
            foreach ($buses as &amp;$bus) {
                $bus['departure_time'] = date('H:i', strtotime($bus['departure_time']));
                $bus['arrival_time'] = date('H:i', strtotime($bus['arrival_time']));
                $bus['booked_seats'] = (int)$bus['booked_seats'];
                $bus['available_seats'] = (int)$bus['available_seats'];
                $bus['total_capacity'] = (int)$bus['total_capacity'];
            }
            
            Logger::info("Retrieved buses for date: $date", ['count' =&gt; count($buses)]);
            
            return [
                'schedules' =&gt; $buses,
                'date' =&gt; $date,
                'total_buses' =&gt; count($buses)
            ];
            
        } catch (Exception $e) {
            Logger::error("Error getting available buses", ['error' =&gt; $e-&gt;getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Create a new booking with real-time concurrency control
     */
    public function createBooking($employeeId, $busId, $date) {
        try {
            // Validate inputs
            $employeeError = Validator::validateEmployeeId($employeeId);
            if ($employeeError) throw new Exception($employeeError);
            
            $busError = Validator::validateBusId($busId);
            if ($busError) throw new Exception($busError);
            
            $dateError = Validator::validateDate($date);
            if ($dateError) throw new Exception($dateError);
            
            // Use stored procedure for atomic booking
            $sql = "CALL CreateBooking(?, ?, ?, @booking_id, @result_code, @message)";
            $stmt = $this-&gt;db-&gt;prepare($sql);
            $stmt-&gt;execute([$employeeId, $busId, $date]);
            
            // Get the results
            $result = $this-&gt;db-&gt;query("SELECT @booking_id as booking_id, @result_code as result_code, @message as message")-&gt;fetch();
            
            if ($result['result_code'] != 0) {
                throw new Exception($result['message']);
            }
            
            // Get booking details
            $bookingDetails = $this-&gt;getBookingDetails($result['booking_id']);
            
            Logger::info("Booking created successfully", [
                'employee_id' =&gt; $employeeId,
                'booking_id' =&gt; $result['booking_id'],
                'bus_id' =&gt; $busId,
                'date' =&gt; $date
            ]);
            
            return [
                'booking_id' =&gt; $result['booking_id'],
                'status' =&gt; 'confirmed',
                'booking_details' =&gt; $bookingDetails
            ];
            
        } catch (Exception $e) {
            Logger::error("Error creating booking", [
                'employee_id' =&gt; $employeeId,
                'bus_id' =&gt; $busId,
                'date' =&gt; $date,
                'error' =&gt; $e-&gt;getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Cancel an existing booking
     */
    public function cancelBooking($employeeId, $busId, $date) {
        try {
            // Validate inputs
            $employeeError = Validator::validateEmployeeId($employeeId);
            if ($employeeError) throw new Exception($employeeError);
            
            // Use stored procedure for atomic cancellation
            $sql = "CALL CancelBooking(?, ?, ?, @result_code, @message)";
            $stmt = $this-&gt;db-&gt;prepare($sql);
            $stmt-&gt;execute([$employeeId, $busId, $date]);
            
            // Get the results
            $result = $this-&gt;db-&gt;query("SELECT @result_code as result_code, @message as message")-&gt;fetch();
            
            if ($result['result_code'] != 0) {
                throw new Exception($result['message']);
            }
            
            Logger::info("Booking cancelled successfully", [
                'employee_id' =&gt; $employeeId,
                'bus_id' =&gt; $busId,
                'date' =&gt; $date
            ]);
            
            return ['status' =&gt; 'cancelled'];
            
        } catch (Exception $e) {
            Logger::error("Error cancelling booking", [
                'employee_id' =&gt; $employeeId,
                'bus_id' =&gt; $busId,
                'date' =&gt; $date,
                'error' =&gt; $e-&gt;getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get employee's booking status for a specific date
     */
    public function getEmployeeBookingStatus($employeeId, $date) {
        try {
            $employeeError = Validator::validateEmployeeId($employeeId);
            if ($employeeError) throw new Exception($employeeError);
            
            $sql = "
                SELECT 
                    bk.booking_id,
                    bk.bus_id,
                    b.bus_number,
                    b.bus_name,
                    b.departure_time,
                    b.arrival_time,
                    b.route,
                    bk.booking_date,
                    bk.status,
                    bk.seat_number,
                    bk.created_at
                FROM bookings bk
                JOIN buses b ON bk.bus_id = b.id
                WHERE bk.employee_id = ? 
                AND bk.booking_date = ?
                AND bk.status = 'confirmed'
                LIMIT 1
            ";
            
            $stmt = $this-&gt;db-&gt;prepare($sql);
            $stmt-&gt;execute([$employeeId, $date]);
            $booking = $stmt-&gt;fetch();
            
            if ($booking) {
                $booking['departure_time'] = date('H:i', strtotime($booking['departure_time']));
                $booking['arrival_time'] = date('H:i', strtotime($booking['arrival_time']));
                $booking['created_at'] = date('Y-m-d H:i:s', strtotime($booking['created_at']));
            }
            
            return [
                'has_booking' =&gt; $booking !== false,
                'booking' =&gt; $booking
            ];
            
        } catch (Exception $e) {
            Logger::error("Error getting booking status", [
                'employee_id' =&gt; $employeeId,
                'date' =&gt; $date,
                'error' =&gt; $e-&gt;getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get real-time seat availability for a specific bus
     */
    public function getBusAvailability($busId, $date) {
        try {
            $sql = "
                SELECT 
                    b.id as bus_id,
                    b.bus_number,
                    b.bus_name,
                    b.total_capacity,
                    COALESCE(booked.booked_seats, 0) as booked_seats,
                    (b.total_capacity - COALESCE(booked.booked_seats, 0)) as available_seats,
                    ? as check_date
                FROM buses b
                LEFT JOIN (
                    SELECT 
                        bus_id,
                        COUNT(*) as booked_seats
                    FROM bookings 
                    WHERE booking_date = ? 
                    AND status = 'confirmed'
                    GROUP BY bus_id
                ) booked ON b.id = booked.bus_id
                WHERE b.id = ? AND b.is_active = TRUE
            ";
            
            $stmt = $this-&gt;db-&gt;prepare($sql);
            $stmt-&gt;execute([$date, $date, $busId]);
            $result = $stmt-&gt;fetch();
            
            if (!$result) {
                throw new Exception('Bus not found or inactive');
            }
            
            return [
                'bus_id' =&gt; (int)$result['bus_id'],
                'bus_number' =&gt; $result['bus_number'],
                'bus_name' =&gt; $result['bus_name'],
                'total_capacity' =&gt; (int)$result['total_capacity'],
                'booked_seats' =&gt; (int)$result['booked_seats'],
                'available_seats' =&gt; (int)$result['available_seats'],
                'date' =&gt; $result['check_date']
            ];
            
        } catch (Exception $e) {
            Logger::error("Error getting bus availability", [
                'bus_id' =&gt; $busId,
                'date' =&gt; $date,
                'error' =&gt; $e-&gt;getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get booking details by booking ID
     */
    private function getBookingDetails($bookingId) {
        $sql = "
            SELECT 
                bk.booking_id,
                bk.employee_id,
                bk.bus_id,
                b.bus_number,
                b.bus_name,
                b.departure_time,
                b.arrival_time,
                b.route,
                bk.booking_date,
                bk.status,
                bk.seat_number,
                bk.created_at
            FROM bookings bk
            JOIN buses b ON bk.bus_id = b.id
            WHERE bk.booking_id = ?
        ";
        
        $stmt = $this-&gt;db-&gt;prepare($sql);
        $stmt-&gt;execute([$bookingId]);
        $booking = $stmt-&gt;fetch();
        
        if ($booking) {
            $booking['departure_time'] = date('H:i', strtotime($booking['departure_time']));
            $booking['arrival_time'] = date('H:i', strtotime($booking['arrival_time']));
        }
        
        return $booking;
    }
    
    /**
     * Register employee email for notifications
     */
    public function registerEmployee($employeeId, $email, $fullName = '', $department = '') {
        try {
            $employeeError = Validator::validateEmployeeId($employeeId);
            if ($employeeError) throw new Exception($employeeError);
            
            $emailError = Validator::validateEmail($email);
            if ($emailError) throw new Exception($emailError);
            
            $sql = "
                INSERT INTO employees (employee_id, email, full_name, department) 
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                email = VALUES(email),
                full_name = VALUES(full_name),
                department = VALUES(department),
                updated_at = CURRENT_TIMESTAMP
            ";
            
            $stmt = $this-&gt;db-&gt;prepare($sql);
            $stmt-&gt;execute([$employeeId, $email, $fullName, $department]);
            
            Logger::info("Employee registered/updated", [
                'employee_id' =&gt; $employeeId,
                'email' =&gt; $email
            ]);
            
            return [
                'employee_id' =&gt; $employeeId,
                'email' =&gt; $email,
                'status' =&gt; 'registered'
            ];
            
        } catch (Exception $e) {
            Logger::error("Error registering employee", [
                'employee_id' =&gt; $employeeId,
                'error' =&gt; $e-&gt;getMessage()
            ]);
            throw $e;
        }
    }
}

// Handle API requests
try {
    $service = new BusBookingService();
    $method = $_SERVER['REQUEST_METHOD'];
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $pathParts = explode('/', trim($path, '/'));
    
    // Get JSON input for POST requests
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE &amp;&amp; !empty(file_get_contents('php://input'))) {
        throw new Exception('Invalid JSON input');
    }
    
    switch ($method) {
        case 'GET':
            if (end($pathParts) === 'available') {
                // GET /api/schedules/available?date=2024-01-15
                $date = $_GET['date'] ?? null;
                $result = $service-&gt;getAvailableBuses($date);
                echo ApiResponse::success($result, 'Available buses retrieved successfully');
                
            } elseif ($pathParts[count($pathParts)-2] === 'availability') {
                // GET /api/bus/{busId}/availability?date=2024-01-15
                $busId = $pathParts[count($pathParts)-3];
                $date = $_GET['date'] ?? date('Y-m-d');
                $result = $service-&gt;getBusAvailability($busId, $date);
                echo ApiResponse::success($result, 'Bus availability retrieved successfully');
                
            } elseif (end($pathParts) === 'status') {
                // GET /api/booking/status?employee_id=1234567&amp;date=2024-01-15
                $employeeId = $_GET['employee_id'] ?? '';
                $date = $_GET['date'] ?? date('Y-m-d');
                $result = $service-&gt;getEmployeeBookingStatus($employeeId, $date);
                echo ApiResponse::success($result, 'Booking status retrieved successfully');
                
            } else {
                throw new Exception('Endpoint not found', 404);
            }
            break;
            
        case 'POST':
            if (end($pathParts) === 'book') {
                // POST /api/booking/book
                $employeeId = $input['employee_id'] ?? '';
                $busId = $input['bus_id'] ?? '';
                $date = $input['date'] ?? date('Y-m-d');
                
                $result = $service-&gt;createBooking($employeeId, $busId, $date);
                echo ApiResponse::success($result, 'Booking created successfully', 201);
                
            } elseif (end($pathParts) === 'cancel') {
                // POST /api/booking/cancel
                $employeeId = $input['employee_id'] ?? '';
                $busId = $input['bus_id'] ?? '';
                $date = $input['date'] ?? date('Y-m-d');
                
                $result = $service-&gt;cancelBooking($employeeId, $busId, $date);
                echo ApiResponse::success($result, 'Booking cancelled successfully');
                
            } elseif (end($pathParts) === 'register') {
                // POST /api/employee/register
                $employeeId = $input['employee_id'] ?? '';
                $email = $input['email'] ?? '';
                $fullName = $input['full_name'] ?? '';
                $department = $input['department'] ?? '';
                
                $result = $service-&gt;registerEmployee($employeeId, $email, $fullName, $department);
                echo ApiResponse::success($result, 'Employee registered successfully', 201);
                
            } else {
                throw new Exception('Endpoint not found', 404);
            }
            break;
            
        default:
            throw new Exception('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    $code = $e-&gt;getCode() ?: 400;
    if ($code &lt; 100 || $code &gt;= 600) $code = 400;
    
    echo ApiResponse::error($e-&gt;getMessage(), $code);
}
?&gt;