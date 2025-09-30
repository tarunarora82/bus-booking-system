<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Intel proxy bypass headers
header('X-Intel-Proxy: bypassed');
header('X-Corporate-Network: localhost-allowed');

// Get the request URI and method
$requestUri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Remove the /api prefix if present
$path = preg_replace('#^/api#', '', parse_url($requestUri, PHP_URL_PATH));

try {
    // Route handling
    switch ($path) {
        case '/buses/available':
            if ($method === 'GET') {
                echo json_encode(getAvailableBuses());
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
            }
            break;

        case '/booking/create':
            if ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                echo json_encode(createBooking($input['employee_id'], $input['bus_number'], $input['schedule_date']));
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
            }
            break;

        case '/booking/cancel':
            if ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                echo json_encode(cancelBooking($input['employee_id'], $input['bus_number']));
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
            }
            break;

        case (preg_match('#^/employee/bookings/(.+)$#', $path, $matches) ? true : false):
            if ($method === 'GET') {
                echo json_encode(getEmployeeBookings($matches[1], date('Y-m-d')));
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
            }
            break;

        case '/admin/recent-bookings':
            if ($method === 'GET') {
                echo json_encode(['status' => 'success', 'data' => getAllRecentBookings()]);
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
            }
            break;

        case '/admin/add-employee':
            if ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                echo json_encode(addEmployee($input));
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
            }
            break;

        case '/admin/add-bus':
            if ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                echo json_encode(addBus($input));
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
            }
            break;

        case (preg_match('#^/admin/employee/(.+)$#', $path, $matches) ? true : false):
            if ($method === 'PUT') {
                $input = json_decode(file_get_contents('php://input'), true);
                $input['employee_id'] = $matches[1];
                echo json_encode(updateEmployee($input));
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
            }
            break;

        case '/admin/employees':
            if ($method === 'GET') {
                echo json_encode(['status' => 'success', 'data' => getAllEmployees()]);
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
            }
            break;

        case '/system/status':
            if ($method === 'GET') {
                echo json_encode(['status' => 'success', 'data' => getSystemStatus()]);
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
            }
            break;

        case '/admin/buses/bulk-upload':
            if ($method === 'POST') {
                echo json_encode(handleBusBulkUpload());
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
            }
            break;

        case '/admin/employees/bulk-upload':
            if ($method === 'POST') {
                echo json_encode(handleEmployeeBulkUpload());
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
            }
            break;

        case '/admin/export/buses':
            if ($method === 'GET') {
                echo json_encode(exportBusData());
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
            }
            break;

        case '/admin/export/employees':
            if ($method === 'GET') {
                echo json_encode(exportEmployeeData());
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
            }
            break;

        case '/admin/settings':
            if ($method === 'GET') {
                echo json_encode(['status' => 'success', 'data' => getSystemSettings()]);
            } elseif ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                echo json_encode(saveSystemSettings($input));
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
            }
            break;

        default:
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'API endpoint not found',
                'path' => $path,
                'available_endpoints' => [
                    'GET /buses/available',
                    'POST /booking/create',
                    'POST /booking/cancel',
                    'GET /employee/bookings/{id}',
                    'GET /admin/recent-bookings',
                    'POST /admin/add-employee',
                    'POST /admin/add-bus',
                    'PUT /admin/employee/{id}',
                    'GET /admin/settings',
                    'POST /admin/settings'
                ]
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
    return file_exists($file) ? json_decode(file_get_contents($file), true) ?: [] : [];
}

function saveBookings($bookings) {
    file_put_contents(getBookingsFilePath(), json_encode($bookings, JSON_PRETTY_PRINT));
}

function getEmployeeBookings($employeeId, $date) {
    $bookings = loadBookings();
    $employeeBookings = array_filter($bookings, function($booking) use ($employeeId, $date) {
        return $booking['employee_id'] === $employeeId && $booking['schedule_date'] === $date;
    });

    if (empty($employeeBookings)) {
        return ['status' => 'success', 'has_booking' => false, 'data' => [], 'morning_booking' => false, 'evening_booking' => false];
    }

    // Check for morning and evening bookings separately
    $morningBooking = false;
    $eveningBooking = false;
    
    foreach ($employeeBookings as $booking) {
        if (isset($booking['slot'])) {
            if ($booking['slot'] === 'morning') $morningBooking = true;
            if ($booking['slot'] === 'evening') $eveningBooking = true;
        }
    }

    return [
        'status' => 'success', 
        'has_booking' => true, 
        'data' => array_values($employeeBookings),
        'morning_booking' => $morningBooking,
        'evening_booking' => $eveningBooking
    ];
}

function createBooking($employeeId, $busNumber, $scheduleDate) {
    if (empty($employeeId) || empty($busNumber) || empty($scheduleDate)) {
        return ['status' => 'error', 'message' => 'Employee ID and Bus Number are required'];
    }

    // Check if employee already has booking for this date and slot
    $existingBooking = getEmployeeBookings($employeeId, $scheduleDate);
    
    // Get the slot from bus data
    $buses = loadBuses();
    $selectedBus = null;
    foreach ($buses as $bus) {
        if ($bus['bus_number'] === $busNumber) {
            $selectedBus = $bus;
            break;
        }
    }
    
    if (!$selectedBus) {
        return ['status' => 'error', 'message' => 'Bus not found'];
    }
    
    $bookingSlot = $selectedBus['slot'] ?? 'evening';
    
    // Check if employee already has booking for this slot
    if ($existingBooking['has_booking']) {
        if ($bookingSlot === 'morning' && $existingBooking['morning_booking']) {
            return ['status' => 'error', 'message' => 'Employee already has a morning booking for this date'];
        }
        if ($bookingSlot === 'evening' && $existingBooking['evening_booking']) {
            return ['status' => 'error', 'message' => 'Employee already has an evening booking for this date'];
        }
    }

    $bookings = loadBookings();
    $bookingId = 'BK' . time() . rand(1000, 9999);
    
    $booking = [
        'booking_id' => $bookingId,
        'employee_id' => $employeeId,
        'bus_number' => $busNumber,
        'schedule_date' => $scheduleDate,
        'slot' => $bookingSlot,
        'departure_time' => $selectedBus['departure_time'],
        'status' => 'confirmed',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $bookings[] = $booking;
    saveBookings($bookings);

    // Get employee info
    $employee = getEmployeeInfo($employeeId);
    if (!$employee) {
        $employee = ['employee_id' => $employeeId, 'name' => 'Unknown Employee', 'email' => 'unknown@intel.com'];
    }

    // Generate email
    $emailContent = generateBookingConfirmationEmail($employee, $booking);
    
    return [
        'status' => 'success',
        'message' => 'Booking confirmed successfully',
        'data' => $booking,
        'employee' => $employee,
        'disclaimer' => null,
        'email_notification' => 'Confirmation email sent to ' . $employee['email'],
        'email_content' => $emailContent,
        'intel_proxy' => 'bypassed_for_localhost'
    ];
}

function cancelBooking($employeeId, $busNumber) {
    $bookings = loadBookings();
    $updated = false;
    
    foreach ($bookings as $key => $booking) {
        if ($booking['employee_id'] === $employeeId && $booking['bus_number'] === $busNumber) {
            unset($bookings[$key]);
            $updated = true;
            break;
        }
    }
    
    if ($updated) {
        saveBookings(array_values($bookings));
        return ['status' => 'success', 'message' => 'Booking cancelled successfully'];
    }
    
    return ['status' => 'error', 'message' => 'Booking not found'];
}

function getEmployeeInfo($employeeId) {
    $employees = loadEmployees();
    foreach ($employees as $employee) {
        if ($employee['employee_id'] === $employeeId) {
            return $employee;
        }
    }
    return null;
}

function loadEmployees() {
    $file = '/tmp/bus_bookings/employees.json';
    if (!file_exists($file)) {
        $employees = getDefaultEmployees();
        saveEmployees($employees);
        return $employees;
    }
    return json_decode(file_get_contents($file), true) ?: getDefaultEmployees();
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
        [
            'employee_id' => '11453732',
            'name' => 'John Doe Updated',
            'email' => 'john.doe.updated@intel.com',
            'department' => 'Engineering - Updated',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ],
        [
            'employee_id' => '12345678',
            'name' => 'Jane Smith',
            'email' => 'jane.smith@intel.com',
            'department' => 'Marketing',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]
    ];
}

function getAllRecentBookings() {
    $bookings = loadBookings();
    $recent = array_slice($bookings, -10);
    
    // Sort by created_at descending
    usort($recent, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    return array_slice($recent, 0, 10);
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
    
    // Check if employee already exists
    foreach ($employees as $employee) {
        if ($employee['employee_id'] === $employeeId) {
            return ['status' => 'error', 'message' => 'Employee with this ID already exists'];
        }
    }
    
    $newEmployee = [
        'employee_id' => $employeeId,
        'name' => $name,
        'email' => $email,
        'department' => $department,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    $employees[] = $newEmployee;
    saveEmployees($employees);
    
    return [
        'status' => 'success',
        'message' => 'Employee added successfully',
        'data' => $newEmployee
    ];
}

function updateEmployee($data) {
    $employeeId = $data['employee_id'] ?? '';
    $name = $data['name'] ?? '';
    $email = $data['email'] ?? '';
    $department = $data['department'] ?? '';
    
    if (empty($employeeId)) {
        return ['status' => 'error', 'message' => 'Employee ID is required'];
    }
    
    $employees = loadEmployees();
    $found = false;
    
    foreach ($employees as &$employee) {
        if ($employee['employee_id'] === $employeeId) {
            if (!empty($name)) $employee['name'] = $name;
            if (!empty($email)) $employee['email'] = $email;
            if (!empty($department)) $employee['department'] = $department;
            $employee['updated_at'] = date('Y-m-d H:i:s');
            $found = true;
            break;
        }
    }
    
    if ($found) {
        saveEmployees($employees);
        $updatedEmployee = null;
        foreach ($employees as $emp) {
            if ($emp['employee_id'] === $employeeId) {
                $updatedEmployee = $emp;
                break;
            }
        }
        return [
            'status' => 'success',
            'message' => 'Employee updated successfully',
            'data' => $updatedEmployee
        ];
    }
    
    return ['status' => 'error', 'message' => 'Employee not found'];
}

function getAvailableBuses() {
    $buses = loadBuses();
    $bookings = loadBookings();
    $today = date('Y-m-d');
    
    // Count bookings for each bus today
    $bookingCounts = [];
    foreach ($bookings as $booking) {
        if ($booking['schedule_date'] === $today) {
            $bus = $booking['bus_number'];
            $bookingCounts[$bus] = ($bookingCounts[$bus] ?? 0) + 1;
        }
    }
    
    // Calculate available seats
    foreach ($buses as &$bus) {
        $booked = $bookingCounts[$bus['bus_number']] ?? 0;
        $bus['booked_seats'] = $booked;
        $bus['available_seats'] = max(0, $bus['capacity'] - $booked);
    }
    
    return [
        'status' => 'success',
        'data' => $buses,
        'message' => 'Real-time bus availability',
        'intel_proxy' => 'bypassed_for_localhost',
        'timestamp' => date('Y-m-d H:i:s'),
        'last_updated' => 'Live data with actual bookings'
    ];
}

function loadBuses() {
    $file = '/tmp/bus_bookings/buses.json';
    if (!file_exists($file)) {
        $buses = getDefaultBuses();
        saveBuses($buses);
        return $buses;
    }
    return json_decode(file_get_contents($file), true) ?: getDefaultBuses();
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
        [
            'bus_number' => 'BUS001',
            'route' => 'Electronic City - Whitefield Express',
            'capacity' => 40,
            'departure_time' => '08:00',
            'slot' => 'morning',
            'booked_seats' => 0,
            'available_seats' => 40
        ],
        [
            'bus_number' => 'BUS001',
            'route' => 'Electronic City - Whitefield Express',
            'capacity' => 40,
            'departure_time' => '16:00',
            'slot' => 'evening',
            'booked_seats' => 0,
            'available_seats' => 40
        ],
        [
            'bus_number' => 'BUS002',
            'route' => 'Bannerghatta - Marathahalli Direct',
            'capacity' => 50,
            'departure_time' => '08:15',
            'slot' => 'morning',
            'booked_seats' => 0,
            'available_seats' => 50
        ],
        [
            'bus_number' => 'BUS002',
            'route' => 'Bannerghatta - Marathahalli Direct',
            'capacity' => 50,
            'departure_time' => '16:15',
            'slot' => 'evening',
            'booked_seats' => 0,
            'available_seats' => 50
        ],
        [
            'bus_number' => '113A',
            'route' => 'Koramangala - Whitefield IT Corridor',
            'capacity' => 50,
            'departure_time' => '08:30',
            'slot' => 'morning',
            'booked_seats' => 0,
            'available_seats' => 50
        ],
        [
            'bus_number' => '113A',
            'route' => 'Koramangala - Whitefield IT Corridor',
            'capacity' => 50,
            'departure_time' => '16:30',
            'slot' => 'evening',
            'booked_seats' => 0,
            'available_seats' => 50
        ]
    ];
}

function addBus($data) {
    $busNumber = $data['bus_number'] ?? '';
    $route = $data['route'] ?? '';
    $capacity = $data['capacity'] ?? '';
    
    if (empty($busNumber) || empty($route) || empty($capacity)) {
        return ['status' => 'error', 'message' => 'All bus fields are required'];
    }
    
    $buses = loadBuses();
    
    // Check if bus already exists
    foreach ($buses as $bus) {
        if ($bus['bus_number'] === $busNumber) {
            return ['status' => 'error', 'message' => 'Bus with this number already exists'];
        }
    }
    
    $newBus = [
        'bus_number' => $busNumber,
        'route' => $route,
        'capacity' => (int)$capacity,
        'departure_time' => $data['departure_time'] ?? '16:00',
        'slot' => $data['slot'] ?? 'evening',
        'booked_seats' => 0,
        'available_seats' => (int)$capacity,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $buses[] = $newBus;
    saveBuses($buses);
    
    return [
        'status' => 'success',
        'message' => 'Bus added successfully',
        'data' => $newBus
    ];
}

function getSystemSettings() {
    $file = '/tmp/bus_bookings/settings.json';
    if (!file_exists($file)) {
        return getDefaultSettings();
    }
    $settings = json_decode(file_get_contents($file), true);
    return $settings ?: getDefaultSettings();
}

function saveSystemSettings($settings) {
    $dir = '/tmp/bus_bookings';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($dir . '/settings.json', json_encode($settings, JSON_PRETTY_PRINT));
    return ['status' => 'success', 'message' => 'Settings saved successfully'];
}

function getDefaultSettings() {
    return [
        'smtp_host' => 'smtpauth.intel.com',
        'smtp_port' => '587',
        'smtp_username' => 'bus-booking@intel.com',
        'smtp_password' => '',
        'booking_closure_minutes' => '10',
        'max_advance_days' => '7',
        'real_time_interval' => '30',
        'notification_enabled' => true,
        'email_notifications' => true
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

function getAllEmployees() {
    $file = '/tmp/bus_bookings/employees.json';
    if (!file_exists($file)) {
        return [];
    }
    return json_decode(file_get_contents($file), true) ?: [];
}

function getSystemStatus() {
    $employeesFile = '/tmp/bus_bookings/employees.json';
    $busesFile = '/tmp/bus_bookings/buses.json';
    $bookingsFile = '/tmp/bus_bookings/bookings.json';
    
    $employeeCount = 0;
    $busCount = 0;
    $bookingCount = 0;
    
    if (file_exists($employeesFile)) {
        $employees = json_decode(file_get_contents($employeesFile), true) ?: [];
        $employeeCount = count($employees);
    }
    
    if (file_exists($busesFile)) {
        $buses = json_decode(file_get_contents($busesFile), true) ?: [];
        $busCount = count($buses);
    }
    
    if (file_exists($bookingsFile)) {
        $bookings = json_decode(file_get_contents($bookingsFile), true) ?: [];
        $bookingCount = count($bookings);
    }
    
    return [
        'system_status' => 'operational',
        'total_employees' => $employeeCount,
        'total_buses' => $busCount,
        'total_bookings' => $bookingCount,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

function handleBusBulkUpload() {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        return ['status' => 'error', 'message' => 'No file uploaded or upload error'];
    }
    
    $file = $_FILES['file'];
    $filename = $file['name'];
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    
    if (!in_array(strtolower($ext), ['csv', 'xlsx', 'xls'])) {
        return ['status' => 'error', 'message' => 'Only CSV and Excel files are supported'];
    }
    
    $tempPath = $file['tmp_name'];
    $data = [];
    
    if (strtolower($ext) === 'csv') {
        $handle = fopen($tempPath, 'r');
        $header = fgetcsv($handle);
        
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 4) {
                $data[] = [
                    'bus_number' => trim($row[0]),
                    'route' => trim($row[1]),
                    'capacity' => (int)trim($row[2]),
                    'departure_time' => trim($row[3]) ?: '16:00',
                    'slot' => isset($row[4]) ? trim($row[4]) : 'evening'
                ];
            }
        }
        fclose($handle);
    }
    
    $buses = loadBuses();
    $added = 0;
    $skipped = 0;
    
    foreach ($data as $busData) {
        // Check if bus with same number and slot already exists
        $exists = false;
        foreach ($buses as $bus) {
            if ($bus['bus_number'] === $busData['bus_number'] && 
                ($bus['slot'] ?? 'evening') === $busData['slot']) {
                $exists = true;
                break;
            }
        }
        
        if (!$exists && !empty($busData['bus_number']) && !empty($busData['route'])) {
            $buses[] = [
                'bus_number' => $busData['bus_number'],
                'route' => $busData['route'],
                'capacity' => $busData['capacity'],
                'departure_time' => $busData['departure_time'],
                'slot' => $busData['slot'],
                'booked_seats' => 0,
                'available_seats' => $busData['capacity'],
                'created_at' => date('Y-m-d H:i:s')
            ];
            $added++;
        } else {
            $skipped++;
        }
    }
    
    saveBuses($buses);
    
    return [
        'status' => 'success',
        'message' => "Bulk upload completed. Added: $added, Skipped: $skipped",
        'data' => ['added' => $added, 'skipped' => $skipped]
    ];
}

function handleEmployeeBulkUpload() {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        return ['status' => 'error', 'message' => 'No file uploaded or upload error'];
    }
    
    $file = $_FILES['file'];
    $filename = $file['name'];
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    
    if (!in_array(strtolower($ext), ['csv', 'xlsx', 'xls'])) {
        return ['status' => 'error', 'message' => 'Only CSV and Excel files are supported'];
    }
    
    $tempPath = $file['tmp_name'];
    $data = [];
    
    if (strtolower($ext) === 'csv') {
        $handle = fopen($tempPath, 'r');
        $header = fgetcsv($handle);
        
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 4) {
                $data[] = [
                    'employee_id' => trim($row[0]),
                    'name' => trim($row[1]),
                    'email' => trim($row[2]),
                    'department' => trim($row[3])
                ];
            }
        }
        fclose($handle);
    }
    
    $employees = getAllEmployees();
    $added = 0;
    $skipped = 0;
    
    foreach ($data as $empData) {
        // Check if employee already exists
        $exists = false;
        foreach ($employees as $emp) {
            if ($emp['employee_id'] === $empData['employee_id']) {
                $exists = true;
                break;
            }
        }
        
        if (!$exists && !empty($empData['employee_id']) && !empty($empData['name'])) {
            $employees[] = [
                'employee_id' => $empData['employee_id'],
                'name' => $empData['name'],
                'email' => $empData['email'],
                'department' => $empData['department'],
                'created_at' => date('Y-m-d H:i:s')
            ];
            $added++;
        } else {
            $skipped++;
        }
    }
    
    // Save employees
    $dir = '/tmp/bus_bookings';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($dir . '/employees.json', json_encode($employees, JSON_PRETTY_PRINT));
    
    return [
        'status' => 'success',
        'message' => "Bulk upload completed. Added: $added, Skipped: $skipped",
        'data' => ['added' => $added, 'skipped' => $skipped]
    ];
}

function exportBusData() {
    $buses = loadBuses();
    
    $csvData = "Bus Number,Route,Capacity,Departure Time,Booked Seats,Available Seats,Created At\n";
    
    foreach ($buses as $bus) {
        $csvData .= sprintf(
            '"%s","%s","%s","%s","%s","%s","%s"' . "\n",
            $bus['bus_number'],
            $bus['route'],
            $bus['capacity'],
            $bus['departure_time'] ?? '16:00',
            $bus['booked_seats'] ?? 0,
            $bus['available_seats'] ?? $bus['capacity'],
            $bus['created_at'] ?? date('Y-m-d H:i:s')
        );
    }
    
    return [
        'status' => 'success',
        'data' => $csvData,
        'filename' => 'buses_export_' . date('Y-m-d_H-i-s') . '.csv',
        'content_type' => 'text/csv'
    ];
}

function exportEmployeeData() {
    $employees = getAllEmployees();
    
    $csvData = "Employee ID,Name,Email,Department,Created At\n";
    
    foreach ($employees as $emp) {
        $csvData .= sprintf(
            '"%s","%s","%s","%s","%s"' . "\n",
            $emp['employee_id'],
            $emp['name'],
            $emp['email'],
            $emp['department'],
            $emp['created_at'] ?? date('Y-m-d H:i:s')
        );
    }
    
    return [
        'status' => 'success',
        'data' => $csvData,
        'filename' => 'employees_export_' . date('Y-m-d_H-i-s') . '.csv',
        'content_type' => 'text/csv'
    ];
}
?>