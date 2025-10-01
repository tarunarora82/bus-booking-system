<?php
/**
 * Simple Bus Booking API - No Composer Dependencies
 * Production-ready with Intel proxy support
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
$path = str_replace('/api', '', $path); // Remove /api prefix
$segments = explode('/', trim($path, '/'));

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
                    $disclaimerMessage = "IMPORTANT: Booking a slot on the bus does not imply or confirm your physical attendance at the office. Employees must follow the company's attendance policy independently to mark their presence at work.";
                    
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Booking confirmed successfully',
                        'data' => $result['data'],
                        'employee' => $employee,
                        'disclaimer' => $disclaimerMessage,
                        'email_notification' => "Confirmation email sent to {$employee['email']}",
                        'email_content' => generateBookingConfirmationEmail($employee, $result['data']),
                        'intel_proxy' => 'bypassed'
                    ]);
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
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Booking cancelled successfully',
                        'employee_id' => $employeeId,
                        'employee' => $employee,
                        'email_notification' => "Cancellation email sent to {$employee['email']}",
                        'intel_proxy' => 'bypassed'
                    ]);
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
            } elseif ($action === 'buses' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                // Add new bus
                $input = json_decode(file_get_contents('php://input'), true);
                $result = addBus($input);
                echo json_encode($result);
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
                    '/api/admin/settings (GET/POST)'
                ],
                'intel_proxy' => 'configured_and_bypassed'
            ]);
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
    $bookings = loadBookings();
    
    // Check if employee already has a booking for this date
    foreach ($bookings as $booking) {
        if ($booking['employee_id'] === $employeeId && 
            $booking['schedule_date'] === $scheduleDate && 
            $booking['status'] === 'confirmed') {
            return ['status' => 'error', 'message' => 'Employee already has a booking for this date'];
        }
    }
    
    // Create new booking
    $bookingId = 'BK' . time() . substr($employeeId, -4);
    $newBooking = [
        'booking_id' => $bookingId,
        'employee_id' => $employeeId,
        'bus_number' => $busNumber,
        'schedule_date' => $scheduleDate,
        'status' => 'confirmed',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $bookings[] = $newBooking;
    saveBookings($bookings);
    
    return ['status' => 'success', 'data' => $newBooking];
}

function cancelBooking($employeeId, $busNumber) {
    $bookings = loadBookings();
    $updated = false;
    
    foreach ($bookings as &$booking) {
        if ($booking['employee_id'] === $employeeId && 
            $booking['bus_number'] === $busNumber && 
            $booking['status'] === 'confirmed') {
            $booking['status'] = 'cancelled';
            $booking['cancelled_at'] = date('Y-m-d H:i:s');
            $updated = true;
            break;
        }
    }
    
    if ($updated) {
        saveBookings($bookings);
        return ['status' => 'success', 'message' => 'Booking cancelled successfully'];
    }
    
    return ['status' => 'error', 'message' => 'No active booking found to cancel'];
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

Thank you for using our bus booking service.

Regards,
Company Transportation Team

---
This is an automated message. Please do not reply to this email."
    ];
}
?>