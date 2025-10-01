<?php
/**
 * UNIFIED PRODUCTION API - Single Source of Truth
 * Consolidates all API functionality into one endpoint
 * Date: October 1, 2025
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Configuration - Use Docker environment
$dataPath = '/var/www/html/data';
if (!is_dir($dataPath)) {
    mkdir($dataPath, 0755, true);
}

// Get request path and method
$requestUri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($requestUri, PHP_URL_PATH);
$path = preg_replace('#^/api#', '', $path);

// Get action from multiple sources
$action = $_GET['action'] ?? $_POST['action'] ?? '';
if (empty($action) && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
}

try {
    // Route handling - REST style first, then query style
    if ($method === 'GET' && $path === '/health') {
        echo json_encode([
            'status' => 'success',
            'message' => 'Production Bus Booking API is healthy',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '2.0-production',
            'data_path' => $dataPath
        ]);
    } elseif ($method === 'GET' && $path === '/buses/available') {
        echo json_encode(getAvailableBuses());
    } elseif ($method === 'GET' && preg_match('#^/employee/bookings/(.+)$#', $path, $matches)) {
        $employeeId = $matches[1];
        $date = $_GET['date'] ?? date('Y-m-d');
        echo json_encode(getEmployeeBookings($employeeId, $date));
    } elseif ($method === 'POST' && $path === '/booking/create') {
        $input = json_decode(file_get_contents('php://input'), true);
        echo json_encode(createBooking($input));
    } elseif ($method === 'POST' && $path === '/booking/cancel') {
        $input = json_decode(file_get_contents('php://input'), true);
        echo json_encode(cancelBooking($input));
    } elseif (!empty($action)) {
        // Handle query-style requests for backward compatibility
        handleQueryAction($action);
    } else {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Endpoint not found',
            'path' => $path,
            'method' => $method
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

function handleQueryAction($action) {
    switch ($action) {
        case 'health-check':
            echo json_encode([
                'status' => 'success',
                'message' => 'Production API healthy',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'available-buses':
            echo json_encode(getAvailableBuses());
            break;
            
        case 'employee-bookings':
            $employeeId = $_GET['employee_id'] ?? '';
            $date = $_GET['date'] ?? date('Y-m-d');
            echo json_encode(getEmployeeBookings($employeeId, $date));
            break;
            
        case 'create-booking':
            $input = json_decode(file_get_contents('php://input'), true);
            echo json_encode(createBooking($input));
            break;
            
        case 'cancel-booking':
            $input = json_decode(file_get_contents('php://input'), true);
            echo json_encode(cancelBooking($input));
            break;
            
        case 'admin-recent-bookings':
        case 'admin-bookings':
            echo json_encode(getAdminBookings());
            break;
            
        case 'admin-settings':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                echo json_encode(getAdminSettings());
            } else {
                $input = json_decode(file_get_contents('php://input'), true);
                echo json_encode(updateAdminSettings($input));
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Action not found: ' . $action
            ]);
    }
}

function getAvailableBuses() {
    global $dataPath;
    $busesFile = $dataPath . '/buses.json';
    
    if (!file_exists($busesFile)) {
        $defaultBuses = [
            [
                'id' => 1,
                'bus_number' => 'BUS001',
                'route' => 'Whitefield',
                'capacity' => 50,
                'departure_time' => '08:30',
                'slot' => 'morning',
                'booked_seats' => 0,
                'available_seats' => 50
            ],
            [
                'id' => 2,
                'bus_number' => 'BUS002',
                'route' => 'Electronic City',
                'capacity' => 45,
                'departure_time' => '09:00',
                'slot' => 'morning',
                'booked_seats' => 0,
                'available_seats' => 45
            ],
            [
                'id' => 3,
                'bus_number' => 'BUS003',
                'route' => 'Koramangala',
                'capacity' => 40,
                'departure_time' => '18:30',
                'slot' => 'evening',
                'booked_seats' => 0,
                'available_seats' => 40
            ]
        ];
        file_put_contents($busesFile, json_encode($defaultBuses, JSON_PRETTY_PRINT));
        $buses = $defaultBuses;
    } else {
        $buses = json_decode(file_get_contents($busesFile), true) ?: [];
    }
    
    // Update available seats based on current bookings
    $bookings = loadBookings();
    $today = date('Y-m-d');
    
    foreach ($buses as &$bus) {
        $bookedCount = 0;
        foreach ($bookings as $booking) {
            if ($booking['bus_number'] === $bus['bus_number'] && 
                $booking['schedule_date'] === $today && 
                $booking['status'] === 'active') {
                $bookedCount++;
            }
        }
        $bus['booked_seats'] = $bookedCount;
        $bus['available_seats'] = $bus['capacity'] - $bookedCount;
    }
    
    return [
        'status' => 'success',
        'message' => 'Available buses retrieved',
        'data' => $buses
    ];
}

function getEmployeeBookings($employeeId, $date) {
    if (empty($employeeId)) {
        return [
            'status' => 'error',
            'message' => 'Employee ID is required'
        ];
    }
    
    $bookings = loadBookings();
    $employeeBookings = [];
    
    foreach ($bookings as $booking) {
        if ($booking['employee_id'] === $employeeId && 
            $booking['schedule_date'] === $date &&
            $booking['status'] === 'active') {
            $employeeBookings[] = $booking;
        }
    }
    
    return [
        'status' => 'success',
        'message' => count($employeeBookings) > 0 ? 'Bookings found' : 'No bookings found',
        'data' => $employeeBookings,
        'has_booking' => count($employeeBookings) > 0,
        'employee_id' => $employeeId
    ];
}

function createBooking($data) {
    $employeeId = $data['employee_id'] ?? '';
    $busNumber = $data['bus_number'] ?? '';
    $scheduleDate = $data['schedule_date'] ?? date('Y-m-d');
    
    if (empty($employeeId) || empty($busNumber)) {
        return [
            'status' => 'error',
            'message' => 'Employee ID and Bus Number are required'
        ];
    }
    
    $bookings = loadBookings();
    
    // Check for existing booking
    foreach ($bookings as $booking) {
        if ($booking['employee_id'] === $employeeId && 
            $booking['schedule_date'] === $scheduleDate &&
            $booking['status'] === 'active') {
            return [
                'status' => 'error',
                'message' => 'You already have a booking for today'
            ];
        }
    }
    
    // Create new booking
    $bookingId = 'BK' . time() . rand(100, 999);
    $newBooking = [
        'id' => $bookingId,
        'employee_id' => $employeeId,
        'bus_number' => $busNumber,
        'schedule_date' => $scheduleDate,
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $bookings[] = $newBooking;
    saveBookings($bookings);
    
    return [
        'status' => 'success',
        'message' => 'Booking created successfully',
        'booking' => $newBooking
    ];
}

function cancelBooking($data) {
    $employeeId = $data['employee_id'] ?? '';
    $busNumber = $data['bus_number'] ?? '';
    $scheduleDate = $data['schedule_date'] ?? date('Y-m-d');
    
    $bookings = loadBookings();
    $found = false;
    
    for ($i = 0; $i < count($bookings); $i++) {
        if ($bookings[$i]['employee_id'] === $employeeId && 
            $bookings[$i]['bus_number'] === $busNumber &&
            $bookings[$i]['schedule_date'] === $scheduleDate &&
            $bookings[$i]['status'] === 'active') {
            
            $bookings[$i]['status'] = 'cancelled';
            $bookings[$i]['cancelled_at'] = date('Y-m-d H:i:s');
            $found = true;
            break;
        }
    }
    
    if ($found) {
        saveBookings($bookings);
        return [
            'status' => 'success',
            'message' => 'Booking cancelled successfully'
        ];
    } else {
        return [
            'status' => 'error',
            'message' => 'No active booking found'
        ];
    }
}

function getAdminBookings() {
    $bookings = loadBookings();
    return [
        'status' => 'success',
        'message' => 'Admin bookings retrieved',
        'data' => array_slice($bookings, -20) // Last 20 bookings
    ];
}

function getAdminSettings() {
    global $dataPath;
    $settingsFile = $dataPath . '/settings.json';
    
    if (!file_exists($settingsFile)) {
        $defaultSettings = [
            'smtp_host' => 'smtpauth.intel.com',
            'smtp_port' => '587',
            'smtp_user' => 'noreply@intel.com',
            'booking_cutoff' => '15',
            'max_advance_days' => '1',
            'real_time_interval' => '5'
        ];
        file_put_contents($settingsFile, json_encode($defaultSettings, JSON_PRETTY_PRINT));
        return [
            'status' => 'success',
            'data' => $defaultSettings
        ];
    }
    
    $settings = json_decode(file_get_contents($settingsFile), true);
    return [
        'status' => 'success',
        'data' => $settings
    ];
}

function updateAdminSettings($data) {
    global $dataPath;
    $settingsFile = $dataPath . '/settings.json';
    file_put_contents($settingsFile, json_encode($data, JSON_PRETTY_PRINT));
    
    return [
        'status' => 'success',
        'message' => 'Settings updated successfully'
    ];
}

function loadBookings() {
    global $dataPath;
    $bookingsFile = $dataPath . '/bookings.json';
    
    if (!file_exists($bookingsFile)) {
        return [];
    }
    
    return json_decode(file_get_contents($bookingsFile), true) ?: [];
}

function saveBookings($bookings) {
    global $dataPath;
    $bookingsFile = $dataPath . '/bookings.json';
    file_put_contents($bookingsFile, json_encode($bookings, JSON_PRETTY_PRINT));
}
?>