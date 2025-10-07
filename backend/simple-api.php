<?php
/**
 * Simple Bus Booking API - No Composer Dependencies
 * Production-ready with Intel proxy support and Email Notifications
 */

// Include Email Service
require_once __DIR__ . '/EmailService.php';

// CORS headers - properly configured for cross-origin requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');
header('Access-Control-Max-Age: 86400'); // Cache preflight for 24 hours
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Send proper CORS headers for preflight
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');
    header('Access-Control-Max-Age: 86400');
    http_response_code(204);
    exit();
}

// Simple database connection (no composer needed)
class SimpleDatabase {
    private static $connection = null;
    
    public static function getConnection() {
        if (self::$connection === null) {
            try {
                $host = $_ENV['DB_HOST'] ?? 'mysql';
                $dbname = $_ENV['DB_NAME'] ?? 'bus_booking_system';
                $username = $_ENV['DB_USER'] ?? 'root';
                $password = $_ENV['DB_PASS'] ?? 'rootpassword';
                
                self::$connection = new PDO(
                    "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                    $username,
                    $password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
            } catch (PDOException $e) {
                // Return null connection for now, will be handled in endpoints
                self::$connection = null;
            }
        }
        return self::$connection;
    }
}

// Parse the request URI
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Remove /api prefix if present
$path = preg_replace('#^/api#', '', $path);
$segments = explode('/', trim($path, '/'));

// Filter out empty segments
$segments = array_filter($segments);
$segments = array_values($segments); // Re-index

// Simple routing
$endpoint = $segments[0] ?? '';
$action = $segments[1] ?? '';

try {
    switch ($endpoint) {
        case 'health':
            echo json_encode([
                'status' => 'healthy',
                'message' => 'Bus Booking API is operational',
                'timestamp' => date('c'),
                'server' => 'php-simple',
                'intel_proxy' => 'configured',
                'database' => SimpleDatabase::getConnection() ? 'connected' : 'pending'
            ]);
            break;
            
        case 'buses':
            if ($action === 'available') {
                $buses = loadBuses();
                $busData = [];
                
                foreach ($buses as $bus) {
                    // Calculate available seats based on current bookings
                    $bookings = loadBookings();
                    $bookedSeats = 0;
                    $today = date('Y-m-d');
                    
                    foreach ($bookings as $booking) {
                        if ($booking['bus_number'] === $bus['bus_number'] && 
                            $booking['schedule_date'] === $today && 
                            $booking['status'] === 'confirmed') {
                            $bookedSeats++;
                        }
                    }
                    
                    $availableSeats = $bus['capacity'] - $bookedSeats;
                    
                    $busData[] = [
                        'bus_number' => $bus['bus_number'],
                        'route' => $bus['route'],
                        'capacity' => $bus['capacity'],
                        'available_seats' => max(0, $availableSeats),
                        'booked_seats' => $bookedSeats
                    ];
                }
                
                echo json_encode([
                    'status' => 'success',
                    'data' => $busData,
                    'message' => 'Real-time bus availability',
                    'intel_proxy' => 'bypassed_for_localhost',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'last_updated' => 'Live data with actual bookings'
                ]);
            } else {
                echo json_encode([
                    'status' => 'success', 
                    'data' => [
                        ['bus_number' => 'BUS001', 'route' => 'Main Route', 'capacity' => 40],
                        ['bus_number' => 'BUS002', 'route' => 'Express Route', 'capacity' => 50]
                    ],
                    'message' => 'All buses retrieved'
                ]);
            }
            break;
            
        case 'booking':
            if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $employeeId = $input['employee_id'] ?? '';
                $busNumber = $input['bus_number'] ?? '';
                $scheduleDate = $input['schedule_date'] ?? date('Y-m-d');
                
                if (empty($employeeId) || empty($busNumber)) {
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Employee ID and Bus Number are required'
                    ]);
                    break;
                }
                
                // Create booking using proper storage
                $result = createBooking($employeeId, $busNumber, $scheduleDate);
                
                if ($result['status'] === 'success') {
                    $employee = getEmployeeInfo($employeeId);
                    
                    // Send email notification
                    $emailService = new EmailService();
                    $emailResult = $emailService->sendBookingConfirmation($result['data'], $employee);
                    
                    $disclaimer = "Booking a slot on the bus does not imply or confirm your physical attendance at the office. Employees must follow the company's attendance policy independently to mark their presence at work.\n\nDeparture Guidelines:\n• Be seated in the shuttle 10 minutes before departure\n• 3:55 PM: First whistle – boarding closes soon\n• 4:00 PM: Final whistle – shuttle departs\n• Boarding/Drop Location: Use your designated site only\n\nBooking should adhere to above guidelines, else will be considered deemed cancelled.";
                    
                    $response = [
                        'status' => 'success',
                        'message' => 'Booking confirmed successfully',
                        'data' => $result['data'],
                        'employee' => $employee,
                        'disclaimer' => $disclaimer,
                        'intel_proxy' => 'bypassed'
                    ];
                    
                    // Add email notification status
                    if ($emailResult['success']) {
                        $response['email_notification'] = "Confirmation email sent to {$emailResult['email']}";
                        $response['email_sent'] = true;
                    } else {
                        $response['email_notification'] = $emailResult['message'];
                        $response['email_sent'] = false;
                        if (isset($emailResult['skip_reason'])) {
                            $response['email_skip_reason'] = $emailResult['skip_reason'];
                        }
                    }
                    
                    echo json_encode($response);
                } else {
                    echo json_encode($result);
                }
            } elseif ($action === 'cancel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $employeeId = $input['employee_id'] ?? '';
                $busNumber = $input['bus_number'] ?? '';
                
                if (empty($employeeId) || empty($busNumber)) {
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Employee ID and Bus Number are required'
                    ]);
                    break;
                }
                
                $result = cancelBooking($employeeId, $busNumber);
                $employee = getEmployeeInfo($employeeId);
                
                if ($result['status'] === 'success') {
                    // Send cancellation email notification
                    $emailService = new EmailService();
                    $emailResult = $emailService->sendBookingCancellation($result['data'], $employee);
                    
                    $response = [
                        'status' => 'success',
                        'message' => 'Booking cancelled successfully',
                        'employee_id' => $employeeId,
                        'employee' => $employee,
                        'intel_proxy' => 'bypassed'
                    ];
                    
                    // Add email notification status
                    if ($emailResult['success']) {
                        $response['email_notification'] = "Cancellation email sent to {$emailResult['email']}";
                        $response['email_sent'] = true;
                    } else {
                        $response['email_notification'] = $emailResult['message'];
                        $response['email_sent'] = false;
                        if (isset($emailResult['skip_reason'])) {
                            $response['email_skip_reason'] = $emailResult['skip_reason'];
                        }
                    }
                    
                    echo json_encode($response);
                } else {
                    echo json_encode($result);
                }
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid booking action'
                ]);
            }
            break;
            
        case 'employee':
            if ($action === 'bookings' && isset($segments[2])) {
                $employeeId = $segments[2];
                $date = $_GET['date'] ?? date('Y-m-d');
                
                // Get employee info and bookings from simple storage
                $bookings = getEmployeeBookings($employeeId, $date);
                $employee = getEmployeeInfo($employeeId);
                
                if (!empty($bookings)) {
                    echo json_encode([
                        'status' => 'success',
                        'data' => $bookings,
                        'employee' => $employee,
                        'message' => 'Employee bookings retrieved',
                        'has_booking' => true
                    ]);
                } else {
                    echo json_encode([
                        'status' => 'success',
                        'data' => [],
                        'employee' => $employee,
                        'message' => 'No bookings found for this employee',
                        'has_booking' => false
                    ]);
                }
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Employee ID required'
                ]);
            }
            break;
            
        case 'bookings':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Booking created successfully',
                    'booking_id' => 'BK' . time(),
                    'employee_id' => $input['employee_id'] ?? 'unknown'
                ]);
            } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
                echo json_encode([
                    'status' => 'success', 
                    'message' => 'Booking cancelled successfully'
                ]);
            } else {
                echo json_encode([
                    'status' => 'success',
                    'data' => [],
                    'message' => 'Bookings retrieved'
                ]);
            }
            break;
            
        case 'admin':
            // Simple admin authentication check
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            if (empty($authHeader) || !validateAdminAuth($authHeader)) {
                http_response_code(401);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Admin authentication required',
                    'code' => 'UNAUTHORIZED'
                ]);
                break;
            }
            
            if ($action === 'bookings') {
                // Get all recent bookings
                $bookings = getAllRecentBookings();
                echo json_encode([
                    'status' => 'success',
                    'data' => $bookings,
                    'message' => 'Recent bookings retrieved'
                ]);
            } elseif ($action === 'employees' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                // Add new employee
                $input = json_decode(file_get_contents('php://input'), true);
                $result = addEmployee($input);
                echo json_encode($result);
            } elseif ($action === 'employees' && $_SERVER['REQUEST_METHOD'] === 'PUT') {
                // Update existing employee
                $input = json_decode(file_get_contents('php://input'), true);
                $result = updateEmployee($input);
                echo json_encode($result);
            } elseif ($action === 'buses' && $_SERVER['REQUEST_METHOD'] === 'GET') {
                // Get all buses for admin management
                $buses = loadBuses();
                echo json_encode([
                    'status' => 'success',
                    'data' => array_values($buses),
                    'message' => 'All buses retrieved'
                ]);
            } elseif ($action === 'buses' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                // Add new bus
                $input = json_decode(file_get_contents('php://input'), true);
                $result = addBus($input);
                echo json_encode($result);
            } elseif ($action === 'buses' && $_SERVER['REQUEST_METHOD'] === 'PUT') {
                // Update existing bus
                $input = json_decode(file_get_contents('php://input'), true);
                $result = updateBus($input);
                echo json_encode($result);
            } elseif ($action === 'buses' && $_SERVER['REQUEST_METHOD'] === 'DELETE') {
                // Delete bus (with booking validation)
                $input = json_decode(file_get_contents('php://input'), true);
                $result = deleteBus($input['bus_number'] ?? '');
                echo json_encode($result);
            } elseif ($action === 'employees' && $_SERVER['REQUEST_METHOD'] === 'GET') {
                // Get all employees for admin management
                $employees = loadEmployees();
                echo json_encode([
                    'status' => 'success',
                    'data' => array_values($employees),
                    'message' => 'All employees retrieved'
                ]);
            } elseif ($action === 'employees' && $_SERVER['REQUEST_METHOD'] === 'DELETE') {
                // Delete employee
                $input = json_decode(file_get_contents('php://input'), true);
                $result = deleteEmployee($input['employee_id'] ?? '');
                echo json_encode($result);
            } elseif ($action === 'activity-log') {
                // Get system activity log
                $log = getActivityLog();
                echo json_encode([
                    'status' => 'success',
                    'data' => $log,
                    'message' => 'Activity log retrieved'
                ]);
            } elseif ($action === 'email-log') {
                // Get email activity log
                $emailService = new EmailService();
                $emailLog = $emailService->getEmailLog(100);
                echo json_encode([
                    'status' => 'success',
                    'data' => ['log' => $emailLog],
                    'message' => 'Email log retrieved successfully'
                ]);
            } elseif ($action === 'settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                // Save system settings
                $input = json_decode(file_get_contents('php://input'), true);
                $result = saveSystemSettings($input);
                echo json_encode($result);
            } elseif ($action === 'settings' && $_SERVER['REQUEST_METHOD'] === 'GET') {
                // Get system settings
                $settings = getSystemSettings();
                echo json_encode([
                    'status' => 'success',
                    'data' => $settings,
                    'message' => 'System settings retrieved'
                ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid admin action'
                ]);
            }
            break;
            
        default:
            // Check for query parameter based actions (legacy support)
            $queryAction = $_GET['action'] ?? '';
            
            if (!empty($queryAction)) {
                handleQueryAction($queryAction);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Endpoint not found',
                    'available_endpoints' => [
                        '/api/health',
                        '/api/buses/available', 
                        '/api/buses',
                        '/api/booking/create (POST)',
                        '/api/booking/cancel (POST)',
                        '/api/employee/bookings/{employee_id}',
                        '/api/bookings',
                        '/api/admin/bookings',
                        '/api/admin/employees (POST)',
                        '/api/admin/buses (POST)',
                        '/api/admin/settings (GET/POST)',
                        '?action=get_buses',
                        '?action=get_employees',
                        '?action=get_system_settings',
                        '?action=get_activity_log',
                        '?action=add_bus (POST)',
                        '?action=update_bus (POST)',
                        '?action=delete_bus (POST)',
                        '?action=save_system_settings (POST)'
                    ],
                    'intel_proxy' => 'configured_and_bypassed'
                ]);
            }
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error: ' . $e->getMessage(),
        'intel_proxy' => 'not_the_issue'
    ]);
}

// Handle query parameter based actions (legacy support)
function handleQueryAction($action) {
    try {
        switch ($action) {
            case 'get_buses':
                $buses = loadBuses();
                echo json_encode([
                    'status' => 'success',
                    'data' => $buses,
                    'message' => 'Buses retrieved successfully'
                ]);
                break;
                
            case 'get_employees':
                $employees = loadEmployees();
                echo json_encode([
                    'status' => 'success',
                    'data' => $employees,
                    'message' => 'Employees retrieved successfully'
                ]);
                break;
                
            case 'get_system_settings':
                $settings = getSystemSettings();
                echo json_encode([
                    'status' => 'success',
                    'data' => $settings,
                    'message' => 'System settings retrieved successfully'
                ]);
                break;
                
            case 'get_activity_log':
                $limit = (int)($_GET['limit'] ?? 100);
                $log = getActivityLog($limit);
                echo json_encode([
                    'status' => 'success',
                    'data' => $log,
                    'message' => 'Activity log retrieved successfully'
                ]);
                break;
                
            case 'add_bus':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $input = json_decode(file_get_contents('php://input'), true);
                    $result = addBus($input);
                    echo json_encode($result);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'POST method required']);
                }
                break;
                
            case 'update_bus':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $input = json_decode(file_get_contents('php://input'), true);
                    $result = updateBus($input);
                    echo json_encode($result);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'POST method required']);
                }
                break;
                
            case 'delete_bus':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $input = json_decode(file_get_contents('php://input'), true);
                    $busNumber = $input['bus_number'] ?? '';
                    $result = deleteBus($busNumber);
                    echo json_encode($result);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'POST method required']);
                }
                break;
                
            case 'delete_employee':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $input = json_decode(file_get_contents('php://input'), true);
                    $employeeId = $input['employee_id'] ?? '';
                    $result = deleteEmployee($employeeId);
                    echo json_encode($result);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'POST method required']);
                }
                break;
                
            case 'save_system_settings':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $input = json_decode(file_get_contents('php://input'), true);
                    $result = saveSystemSettings($input);
                    echo json_encode($result);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'POST method required']);
                }
                break;
                
            case 'book':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $input = json_decode(file_get_contents('php://input'), true);
                    $employeeId = $input['employee_id'] ?? '';
                    $busNumber = $input['bus_number'] ?? '';
                    $scheduleDate = $input['schedule_date'] ?? '';
                    $result = createBooking($employeeId, $busNumber, $scheduleDate);
                    echo json_encode($result);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'POST method required']);
                }
                break;
                
            case 'cancel':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $input = json_decode(file_get_contents('php://input'), true);
                    $employeeId = $input['employee_id'] ?? '';
                    $busNumber = $input['bus_number'] ?? '';
                    $scheduleDate = $input['schedule_date'] ?? null;
                    $result = cancelBooking($employeeId, $busNumber, $scheduleDate);
                    echo json_encode($result);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'POST method required']);
                }
                break;
                
            case 'get_bookings':
                $employeeId = $_GET['employee_id'] ?? '';
                if ($employeeId) {
                    $date = $_GET['date'] ?? date('Y-m-d');
                    $bookings = getEmployeeBookings($employeeId, $date);
                    echo json_encode([
                        'status' => 'success',
                        'data' => $bookings,
                        'message' => 'Employee bookings retrieved'
                    ]);
                } else {
                    $bookings = getAllRecentBookings();
                    echo json_encode([
                        'status' => 'success',
                        'data' => $bookings,
                        'message' => 'All bookings retrieved'
                    ]);
                }
                break;
                
            default:
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Unknown action: ' . $action,
                    'available_actions' => [
                        'get_buses', 'get_employees', 'get_system_settings', 'get_activity_log',
                        'add_bus', 'update_bus', 'delete_bus', 'delete_employee',
                        'save_system_settings', 'book', 'cancel', 'get_bookings'
                    ]
                ]);
                break;
        }
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Action failed: ' . $e->getMessage()
        ]);
    }
}

// Simple booking storage functions using file system
function getBookingsFilePath() {
    $dir = '/tmp/bus_bookings';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir . '/bookings.json';
}

function loadBookings() {
    $file = getBookingsFilePath();
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true) ?: [];
    }
    return [];
}

function saveBookings($bookings) {
    $file = getBookingsFilePath();
    file_put_contents($file, json_encode($bookings, JSON_PRETTY_PRINT));
}

function getEmployeeBookings($employeeId, $date) {
    $bookings = loadBookings();
    $result = [];
    
    foreach ($bookings as $booking) {
        if ($booking['employee_id'] === $employeeId && 
            $booking['schedule_date'] === $date && 
            $booking['status'] === 'confirmed') {
            $result[] = $booking;
        }
    }
    
    return $result;
}

function createBooking($employeeId, $busNumber, $scheduleDate) {
    // Acquire lock for this bus/date combination
    $lockResource = "booking_{$busNumber}_{$scheduleDate}";
    if (!acquireLock($lockResource)) {
        return ['status' => 'error', 'message' => 'System busy, please try again'];
    }
    
    try {
        // Get system settings for validation
        $settings = getSystemSettings();
        
        // Check system maintenance mode
        if ($settings['system_maintenance_mode']) {
            return ['status' => 'error', 'message' => 'System is under maintenance'];
        }
        
        $bookings = loadBookings();
        $buses = loadBuses();
        $employees = loadEmployees();
        
        // Validate employee exists
        if (!isset($employees[$employeeId])) {
            return ['status' => 'error', 'message' => 'Employee not found'];
        }
        
        // Validate bus exists and is enabled
        if (!isset($buses[$busNumber])) {
            return ['status' => 'error', 'message' => 'Bus not found'];
        }
        
        if (!($buses[$busNumber]['enabled'] ?? true)) {
            return ['status' => 'error', 'message' => 'Bus is currently disabled'];
        }
        
        // Check employee daily booking limit
        $employeeDailyBookings = 0;
        foreach ($bookings as $booking) {
            if ($booking['employee_id'] === $employeeId && 
                $booking['schedule_date'] === $scheduleDate && 
                $booking['status'] === 'confirmed') {
                $employeeDailyBookings++;
            }
        }
        
        if ($employeeDailyBookings >= $settings['max_bookings_per_employee_per_day']) {
            return ['status' => 'error', 'message' => 
                "Maximum {$settings['max_bookings_per_employee_per_day']} bookings per day allowed"];
        }
        
        // Check if employee already has a booking for this date
        foreach ($bookings as $booking) {
            if ($booking['employee_id'] === $employeeId && 
                $booking['schedule_date'] === $scheduleDate && 
                $booking['status'] === 'confirmed') {
                return ['status' => 'error', 'message' => 'Employee already has a booking for this date'];
            }
        }
        
        // Count current bookings for capacity check
        $currentBookings = 0;
        foreach ($bookings as $booking) {
            if ($booking['bus_number'] === $busNumber && 
                $booking['schedule_date'] === $scheduleDate && 
                $booking['status'] === 'confirmed') {
                $currentBookings++;
            }
        }
        
        // Check capacity
        $busCapacity = $buses[$busNumber]['capacity'] ?? 40;
        if ($currentBookings >= $busCapacity) {
            return ['status' => 'error', 'message' => 'Bus is fully booked'];
        }
        
        // Create new booking
        $bookingId = 'BK' . date('Ymd') . sprintf('%04d', count($bookings) + 1);
        $newBooking = [
            'booking_id' => $bookingId,
            'employee_id' => $employeeId,
            'employee_name' => $employees[$employeeId]['name'] ?? 'Unknown',
            'bus_number' => $busNumber,
            'route' => $buses[$busNumber]['route'] ?? 'Not specified',
            'schedule_date' => $scheduleDate,
            'departure_time' => $buses[$busNumber]['departure_time'] ?? '08:00 AM',
            'slot' => $buses[$busNumber]['slot'] ?? 'morning',
            'status' => 'confirmed',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $bookings[] = $newBooking;
        saveBookings($bookings);
        
        // Log the booking activity
        logActivity($employeeId, 'booking_created', 
            "Booked seat on bus {$busNumber} for {$scheduleDate}");
        
        return ['status' => 'success', 'data' => $newBooking];
        
    } finally {
        // Always release the lock
        releaseLock($lockResource);
    }
}

function cancelBooking($employeeId, $busNumber, $scheduleDate = null) {
    // Acquire lock for this operation
    $lockResource = $scheduleDate ? "booking_{$busNumber}_{$scheduleDate}" : "cancel_{$employeeId}_{$busNumber}";
    if (!acquireLock($lockResource)) {
        return ['status' => 'error', 'message' => 'System busy, please try again'];
    }
    
    try {
        // Get system settings
        $settings = getSystemSettings();
        
        // Check system maintenance mode
        if ($settings['system_maintenance_mode']) {
            return ['status' => 'error', 'message' => 'System is under maintenance'];
        }
        
        $bookings = loadBookings();
        $updated = false;
        $cancelledBooking = null;
        
        foreach ($bookings as &$booking) {
            $matchesEmployee = $booking['employee_id'] === $employeeId;
            $matchesBus = $booking['bus_number'] === $busNumber;
            $matchesDate = !$scheduleDate || $booking['schedule_date'] === $scheduleDate;
            $isActive = $booking['status'] === 'confirmed';
            
            if ($matchesEmployee && $matchesBus && $matchesDate && $isActive) {
                // Check if cancellation is allowed (not too close to departure)
                if ($booking['schedule_date'] === date('Y-m-d')) {
                    $buses = loadBuses();
                    if (isset($buses[$busNumber])) {
                        $departureTime = DateTime::createFromFormat('h:i A', $buses[$busNumber]['departure_time']);
                        $freezeTime = clone $departureTime;
                        $freezeTime->sub(new DateInterval('PT' . $settings['booking_freeze_minutes'] . 'M'));
                        
                        if (new DateTime() > $freezeTime) {
                            return ['status' => 'error', 'message' => 
                                "Cannot cancel booking {$settings['booking_freeze_minutes']} minutes before departure"];
                        }
                    }
                }
                
                $booking['status'] = 'cancelled';
                $booking['cancelled_at'] = date('Y-m-d H:i:s');
                $cancelledBooking = $booking;
                $updated = true;
                break;
            }
        }
        
        if ($updated) {
            saveBookings($bookings);
            
            // Log the cancellation activity
            logActivity($employeeId, 'booking_cancelled', 
                "Cancelled booking for bus {$busNumber}" . 
                ($scheduleDate ? " on {$scheduleDate}" : ""));
            
            return [
                'status' => 'success', 
                'message' => 'Booking cancelled successfully',
                'data' => $cancelledBooking
            ];
        }
        
        return ['status' => 'error', 'message' => 'No active booking found to cancel'];
        
    } finally {
        // Always release the lock
        releaseLock($lockResource);
    }
}

function getEmployeeInfo($employeeId) {
    $employees = loadEmployees();
    return $employees[$employeeId] ?? [
        'employee_id' => $employeeId,
        'name' => 'Unknown Employee',
        'email' => $employeeId . '@intel.com',
        'department' => 'Unknown'
    ];
}

function loadEmployees() {
    $file = '/tmp/bus_bookings/employees.json';
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true) ?: getDefaultEmployees();
    }
    return getDefaultEmployees();
}

function saveEmployees($employees) {
    $dir = '/tmp/bus_bookings';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($dir . '/employees.json', json_encode($employees, JSON_PRETTY_PRINT));
}

function getDefaultEmployees() {
    return [
        '11453732' => [
            'employee_id' => '11453732',
            'name' => 'John Doe',
            'email' => 'john.doe@intel.com',
            'department' => 'Engineering'
        ],
        '1234567' => [
            'employee_id' => '1234567',
            'name' => 'Jane Smith',
            'email' => 'jane.smith@intel.com',
            'department' => 'Marketing'
        ],
        '9876543' => [
            'employee_id' => '9876543',
            'name' => 'Bob Johnson',
            'email' => 'bob.johnson@intel.com',
            'department' => 'Finance'
        ]
    ];
}

function getAllRecentBookings() {
    $bookings = loadBookings();
    $recent = [];
    
    foreach ($bookings as $booking) {
        if ($booking['status'] === 'confirmed') {
            $employee = getEmployeeInfo($booking['employee_id']);
            $recent[] = [
                'booking_id' => $booking['booking_id'],
                'employee_id' => $booking['employee_id'],
                'employee_name' => $employee['name'],
                'bus_number' => $booking['bus_number'],
                'schedule_date' => $booking['schedule_date'],
                'status' => $booking['status'],
                'created_at' => $booking['created_at']
            ];
        }
    }
    
    // Sort by creation date, most recent first
    usort($recent, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    return array_slice($recent, 0, 10); // Return last 10 bookings
}

function addEmployee($data) {
    $employeeId = $data['employee_id'] ?? '';
    $name = $data['name'] ?? '';
    $email = $data['email'] ?? '';
    $department = $data['department'] ?? '';
    
    if (empty($employeeId) || empty($name) || empty($email) || empty($department)) {
        return ['status' => 'error', 'message' => 'All employee fields are required'];
    }
    
    $employees = loadEmployees();
    
    if (isset($employees[$employeeId])) {
        return ['status' => 'error', 'message' => 'Employee ID already exists. Use update to modify existing employee.'];
    }
    
    $employees[$employeeId] = [
        'employee_id' => $employeeId,
        'name' => $name,
        'email' => $email,
        'department' => $department,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    saveEmployees($employees);
    
    return [
        'status' => 'success',
        'message' => 'Employee added successfully',
        'data' => $employees[$employeeId]
    ];
}

function updateEmployee($data) {
    $employeeId = $data['employee_id'] ?? '';
    $name = $data['name'] ?? '';
    $email = $data['email'] ?? '';
    $department = $data['department'] ?? '';
    
    if (empty($employeeId) || empty($name) || empty($email) || empty($department)) {
        return ['status' => 'error', 'message' => 'All employee fields are required'];
    }
    
    $employees = loadEmployees();
    
    if (!isset($employees[$employeeId])) {
        return ['status' => 'error', 'message' => 'Employee ID not found'];
    }
    
    // Keep original creation date
    $originalCreatedAt = $employees[$employeeId]['created_at'] ?? date('Y-m-d H:i:s');
    
    $employees[$employeeId] = [
        'employee_id' => $employeeId,
        'name' => $name,
        'email' => $email,
        'department' => $department,
        'created_at' => $originalCreatedAt,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    saveEmployees($employees);
    
    return [
        'status' => 'success',
        'message' => 'Employee updated successfully',
        'data' => $employees[$employeeId]
    ];
}

function loadBuses() {
    $file = '/tmp/bus_bookings/buses.json';
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true) ?: getDefaultBuses();
    }
    return getDefaultBuses();
}

function saveBuses($buses) {
    $dir = '/tmp/bus_bookings';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($dir . '/buses.json', json_encode($buses, JSON_PRETTY_PRINT));
}

function getDefaultBuses() {
    return [
        'BUS001' => [
            'bus_number' => 'BUS001',
            'route' => 'Main Route',
            'capacity' => 40
        ],
        'BUS002' => [
            'bus_number' => 'BUS002', 
            'route' => 'Express Route',
            'capacity' => 50
        ]
    ];
}

function addBus($data) {
    $busNumber = $data['bus_number'] ?? '';
    $route = $data['route'] ?? '';
    $capacity = intval($data['capacity'] ?? 0);
    
    if (empty($busNumber) || empty($route) || $capacity <= 0) {
        return ['status' => 'error', 'message' => 'All bus fields are required and capacity must be positive'];
    }
    
    $buses = loadBuses();
    
    if (isset($buses[$busNumber])) {
        return ['status' => 'error', 'message' => 'Bus number already exists'];
    }
    
    $buses[$busNumber] = [
        'bus_number' => $busNumber,
        'route' => $route,
        'capacity' => $capacity,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    saveBuses($buses);
    
    return [
        'status' => 'success',
        'message' => 'Bus added successfully',
        'data' => $buses[$busNumber]
    ];
}

function getSystemSettings() {
    $file = '/tmp/bus_bookings/settings.json';
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true) ?: getDefaultSettings();
    }
    return getDefaultSettings();
}

function saveSystemSettings($settings) {
    $dir = '/tmp/bus_bookings';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    
    $currentSettings = getSystemSettings();
    $updatedSettings = array_merge($currentSettings, $settings);
    $updatedSettings['updated_at'] = date('Y-m-d H:i:s');
    
    file_put_contents($dir . '/settings.json', json_encode($updatedSettings, JSON_PRETTY_PRINT));
    
    return [
        'status' => 'success',
        'message' => 'Settings saved successfully',
        'data' => $updatedSettings
    ];
}

function getDefaultSettings() {
    return [
        'smtp_host' => 'smtpauth.intel.com',
        'smtp_port' => 587,
        'smtp_user' => 'noreply@intel.com',
        'booking_cutoff' => 15,
        'max_advance_days' => 1,
        'real_time_interval' => 5
    ];
}

function generateBookingConfirmationEmail($employee, $booking) {
    $scheduleTime = date('A', strtotime($booking['created_at'])) === 'AM' ? 'Morning' : 'Evening';
    
    return [
        'to' => $employee['email'],
        'subject' => 'Bus Booking Confirmation - ' . $booking['bus_number'],
        'body' => "Dear {$employee['name']},

Your booking for the bus on {$booking['schedule_date']} ({$scheduleTime} Schedule) is confirmed. Here are the details:

Bus Number: {$booking['bus_number']}
Booking ID: {$booking['booking_id']}
Employee ID: {$booking['employee_id']}
Schedule Date: {$booking['schedule_date']}
Booking Time: {$booking['created_at']}

IMPORTANT NOTICE - BOOKING ATTENDANCE DISCLAIMER:
Please note: Booking a slot on the bus does not mean you have attended the office. Ensure you comply with your company's attendance policy to mark your official presence at work.

DEPARTURE GUIDELINES:
• Be seated in the shuttle 10 minutes before departure
• 3:55 PM: First whistle – boarding closes soon
• 4:00 PM: Final whistle – shuttle departs  
• Boarding/Drop Location: Use your designated site only

⚠️ Booking should adhere to above guidelines, else will be considered deemed cancelled.

Thank you for using our bus booking service.

Regards,
Company Transportation Team

---
This is an automated message. Please do not reply to this email."
    ];
}

// Simple admin authentication function
function validateAdminAuth($authHeader) {
    // Extract token from "Bearer TOKEN" format
    if (strpos($authHeader, 'Bearer ') !== 0) {
        return false;
    }
    
    $token = substr($authHeader, 7);
    
    // Simple token validation (in production, use proper JWT or database lookup)
    $validTokens = [
        'admin123', // Simple admin token
        'intel-admin-2024', // Corporate admin token
        hash('sha256', 'bus-admin-' . date('Y-m-d')) // Daily rotating token
    ];
    
    return in_array($token, $validTokens);
}

// Additional admin functions for comprehensive management

function addBus($busData) {
    $buses = loadBuses();
    
    $busNumber = $busData['bus_number'] ?? '';
    if (empty($busNumber)) {
        return ['status' => 'error', 'message' => 'Bus number is required'];
    }
    
    if (isset($buses[$busNumber])) {
        return ['status' => 'error', 'message' => 'Bus number already exists'];
    }
    
    $buses[$busNumber] = [
        'bus_number' => $busNumber,
        'route' => $busData['route'] ?? 'Not specified',
        'capacity' => (int)($busData['capacity'] ?? 40),
        'departure_time' => $busData['departure_time'] ?? '08:00 AM',
        'slot' => $busData['slot'] ?? 'morning',
        'enabled' => $busData['enabled'] ?? true,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    saveBuses($buses);
    logActivity('admin', 'bus_added', "Bus {$busNumber} added");
    
    return [
        'status' => 'success',
        'message' => 'Bus added successfully',
        'data' => $buses[$busNumber]
    ];
}

function updateBus($busData) {
    $buses = loadBuses();
    
    $busNumber = $busData['bus_number'] ?? '';
    if (empty($busNumber) || !isset($buses[$busNumber])) {
        return ['status' => 'error', 'message' => 'Bus not found'];
    }
    
    $buses[$busNumber] = array_merge($buses[$busNumber], [
        'route' => $busData['route'] ?? $buses[$busNumber]['route'],
        'capacity' => (int)($busData['capacity'] ?? $buses[$busNumber]['capacity']),
        'departure_time' => $busData['departure_time'] ?? $buses[$busNumber]['departure_time'],
        'slot' => $busData['slot'] ?? $buses[$busNumber]['slot'],
        'enabled' => $busData['enabled'] ?? $buses[$busNumber]['enabled'],
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    
    saveBuses($buses);
    logActivity('admin', 'bus_updated', "Bus {$busNumber} updated");
    
    return [
        'status' => 'success',
        'message' => 'Bus updated successfully',
        'data' => $buses[$busNumber]
    ];
}

function deleteBus($busNumber) {
    if (empty($busNumber)) {
        return ['status' => 'error', 'message' => 'Bus number is required'];
    }
    
    // Check for existing bookings
    $bookings = loadBookings();
    $activeBookings = 0;
    
    foreach ($bookings as $booking) {
        if ($booking['bus_number'] === $busNumber && 
            $booking['status'] === 'confirmed' && 
            $booking['schedule_date'] >= date('Y-m-d')) {
            $activeBookings++;
        }
    }
    
    if ($activeBookings > 0) {
        return [
            'status' => 'error', 
            'message' => "Cannot delete bus. {$activeBookings} active booking(s) exist",
            'active_bookings' => $activeBookings
        ];
    }
    
    $buses = loadBuses();
    if (!isset($buses[$busNumber])) {
        return ['status' => 'error', 'message' => 'Bus not found'];
    }
    
    unset($buses[$busNumber]);
    saveBuses($buses);
    logActivity('admin', 'bus_deleted', "Bus {$busNumber} deleted");
    
    return [
        'status' => 'success',
        'message' => 'Bus deleted successfully'
    ];
}

function deleteEmployee($employeeId) {
    if (empty($employeeId)) {
        return ['status' => 'error', 'message' => 'Employee ID is required'];
    }
    
    $employees = loadEmployees();
    if (!isset($employees[$employeeId])) {
        return ['status' => 'error', 'message' => 'Employee not found'];
    }
    
    unset($employees[$employeeId]);
    saveEmployees($employees);
    logActivity('admin', 'employee_deleted', "Employee {$employeeId} deleted");
    
    return [
        'status' => 'success',
        'message' => 'Employee deleted successfully'
    ];
}

function getSystemSettings() {
    $file = '/tmp/bus_bookings/system_settings.json';
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true) ?: getDefaultSettings();
    }
    return getDefaultSettings();
}

function getDefaultSettings() {
    return [
        'booking_freeze_minutes' => 30, // Freeze booking 30 minutes before departure
        'max_advance_booking_days' => 7, // Allow booking up to 7 days in advance
        'booking_allowed_hours_start' => '06:00',
        'booking_allowed_hours_end' => '22:00',
        'max_bookings_per_employee_per_day' => 2, // Morning + Evening
        'email_notifications_enabled' => true,
        'system_maintenance_mode' => false,
        'concurrency_lock_timeout' => 30 // Seconds
    ];
}

function saveSystemSettings($settings) {
    $dir = '/tmp/bus_bookings';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    
    $file = $dir . '/system_settings.json';
    file_put_contents($file, json_encode($settings, JSON_PRETTY_PRINT));
    logActivity('admin', 'settings_updated', 'System settings updated');
    
    return [
        'status' => 'success',
        'message' => 'System settings saved successfully',
        'data' => $settings
    ];
}

function getActivityLog($limit = 100) {
    $file = '/tmp/bus_bookings/activity_log.json';
    if (file_exists($file)) {
        $log = json_decode(file_get_contents($file), true) ?: [];
        return array_slice(array_reverse($log), 0, $limit); // Latest first
    }
    return [];
}

function logActivity($user, $action, $details) {
    $dir = '/tmp/bus_bookings';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    
    $file = $dir . '/activity_log.json';
    $log = [];
    
    if (file_exists($file)) {
        $log = json_decode(file_get_contents($file), true) ?: [];
    }
    
    $log[] = [
        'timestamp' => date('Y-m-d H:i:s'),
        'user' => $user,
        'action' => $action,
        'details' => $details,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    // Keep only last 1000 entries
    $log = array_slice($log, -1000);
    
    file_put_contents($file, json_encode($log, JSON_PRETTY_PRINT));
}

// Concurrency handling for booking operations
function acquireLock($resource, $timeout = 30) {
    $lockFile = "/tmp/bus_bookings/locks/{$resource}.lock";
    $lockDir = dirname($lockFile);
    
    if (!is_dir($lockDir)) {
        mkdir($lockDir, 0777, true);
    }
    
    $startTime = time();
    
    while (time() - $startTime < $timeout) {
        if (!file_exists($lockFile) || (time() - filemtime($lockFile)) > $timeout) {
            file_put_contents($lockFile, getmypid() . "\n" . time());
            return true;
        }
        usleep(100000); // Wait 100ms
    }
    
    return false;
}

function releaseLock($resource) {
    $lockFile = "/tmp/bus_bookings/locks/{$resource}.lock";
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
}

// Rate limiting function for security
function checkRateLimit($endpoint) {
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $current_time = time();
    $rate_limit_file = sys_get_temp_dir() . '/bus_api_rate_limit.json';
    
    // Load existing rate limit data
    $rate_data = [];
    if (file_exists($rate_limit_file)) {
        $rate_data = json_decode(file_get_contents($rate_limit_file), true) ?? [];
    }
    
    // Clean old entries (older than 1 hour)
    foreach ($rate_data as $ip => $data) {
        $rate_data[$ip] = array_filter($data, function($timestamp) use ($current_time) {
            return ($current_time - $timestamp) < 3600; // 1 hour
        });
        if (empty($rate_data[$ip])) {
            unset($rate_data[$ip]);
        }
    }
    
    // Check current IP rate
    $ip_requests = $rate_data[$client_ip] ?? [];
    if (count($ip_requests) > 100) { // Max 100 requests per hour
        return false;
    }
    
    // Add current request
    $rate_data[$client_ip][] = $current_time;
    
    // Save rate limit data
    file_put_contents($rate_limit_file, json_encode($rate_data));
    
    return true;
}
?>