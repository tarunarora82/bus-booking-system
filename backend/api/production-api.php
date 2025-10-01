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
// Read raw request body once (php://input can be read only once reliably)
$rawInput = file_get_contents('php://input');
$inputJson = json_decode($rawInput, true);

$action = $_GET['action'] ?? $_POST['action'] ?? ($inputJson['action'] ?? '');

// Log incoming request for debugging intermittent routing issues
$logEntry = [
    'timestamp' => date('c'),
    'method' => $method,
    'request_uri' => $requestUri,
    'path' => $path,
    'headers' => (function_exists('getallheaders') ? getallheaders() : []),
    'body_raw' => $rawInput,
];
@file_put_contents($dataPath . '/requests.log', json_encode($logEntry) . PHP_EOL, FILE_APPEND);

// Also write a copy into repository-local backend/data for developer convenience
$localLogDir = __DIR__ . '/../data';
if (!is_dir($localLogDir)) {
    @mkdir($localLogDir, 0755, true);
}
$localLogPath = $localLogDir . '/requests.log';
@file_put_contents($localLogPath, json_encode($logEntry) . PHP_EOL, FILE_APPEND);

// Helper to log responses with route info for easier debugging
function logResponseForDebug($path, $note, $responseArray, $status = 200) {
    global $dataPath;
    $entry = [
        'timestamp' => date('c'),
        'path' => $path,
        'note' => $note,
        'status_code' => $status,
        'response' => $responseArray
    ];
    @file_put_contents($dataPath . '/responses.log', json_encode($entry) . PHP_EOL, FILE_APPEND);
    // Also write to repo-local
    $localLogDir = __DIR__ . '/../data';
    $localPath = $localLogDir . '/responses.log';
    @file_put_contents($localPath, json_encode($entry) . PHP_EOL, FILE_APPEND);
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
    } elseif ($method === 'GET' && $path === '/schedules/available') {
        echo json_encode(getAvailableSchedules());
    } elseif ($method === 'GET' && preg_match('#^/employee/bookings/(.+)$#', $path, $matches)) {
        $employeeId = $matches[1];
        $date = $_GET['date'] ?? date('Y-m-d');
        echo json_encode(getEmployeeBookings($employeeId, $date));
    } elseif ($method === 'POST' && $path === '/booking/create') {
        $input = $inputJson;
        $resp = createBooking($input);
        logResponseForDebug($path, 'booking_create', $resp, 200);
        echo json_encode($resp);
    } elseif ($method === 'POST' && $path === '/booking/cancel') {
        $input = $inputJson;
        $resp = cancelBooking($input);
        logResponseForDebug($path, 'booking_cancel', $resp, 200);
        echo json_encode($resp);
    // Backwards-compatible plural endpoints used by some frontend builds (e.g. working.html)
    } elseif ($method === 'POST' && $path === '/bookings/create') {
        $input = $inputJson;
        $resp = createBooking($input);
        logResponseForDebug($path, 'bookings_create_plural', $resp, 200);
        echo json_encode($resp);
    } elseif ($method === 'POST' && ($path === '/bookings/cancel' || $path === '/bookings/close')) {
        // Accept both /bookings/cancel and legacy /bookings/close if present
        $input = $inputJson;
        $resp = cancelBooking($input);
        logResponseForDebug($path, 'bookings_cancel_plural', $resp, 200);
        echo json_encode($resp);
    } elseif ($method === 'POST' && $path === '/bookings') {
        // Generic POST to /bookings - treat as create for compatibility with some clients
        $input = $inputJson;
        $resp = createBooking($input);
        logResponseForDebug($path, 'bookings_create_generic', $resp, 200);
        echo json_encode($resp);
    } elseif (!empty($action)) {
        // Handle query-style requests for backward compatibility
        handleQueryAction($action);
    } else {
        http_response_code(404);
        $resp = [
            'status' => 'error',
            'message' => 'Endpoint not found',
            'path' => $path,
            'method' => $method
        ];
        logResponseForDebug($path, 'endpoint_not_found', $resp, 404);
        echo json_encode($resp);
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
            
        case 'available-schedules':
            echo json_encode(getAvailableSchedules());
            break;
            
        case 'employee-bookings':
            $employeeId = $_GET['employee_id'] ?? '';
            $date = $_GET['date'] ?? date('Y-m-d');
            echo json_encode(getEmployeeBookings($employeeId, $date));
            break;
            
        case 'create-booking':
            $input = $inputJson;
            echo json_encode(createBooking($input));
            break;
            
        case 'cancel-booking':
            $input = $inputJson;
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
                $input = $inputJson;
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
            ],
            [
                'id' => 4,
                'bus_number' => 'BUS004',
                'route' => 'HSR Layout',
                'capacity' => 35,
                'departure_time' => '17:30',
                'slot' => 'evening',
                'booked_seats' => 0,
                'available_seats' => 35
            ],
            [
                'id' => 5,
                'bus_number' => 'BUS005',
                'route' => 'Marathahalli',
                'capacity' => 45,
                'departure_time' => '08:00',
                'slot' => 'morning',
                'booked_seats' => 0,
                'available_seats' => 45
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

function getAvailableSchedules() {
    // Return sample schedules (kept simple and independent of DB for compatibility)
    $schedules = [
        [
            'id' => 1,
            'name' => 'Morning Shift - Bus A',
            'departure_time' => '08:00',
            'arrival_time' => '18:00',
            'capacity' => 45,
            'available_seats' => 32,
            'route' => 'City Center to Industrial Park',
            'type' => 'morning',
            'schedule_type' => 'morning'
        ],
        [
            'id' => 2,
            'name' => 'Evening Shift - Bus B',
            'departure_time' => '18:30',
            'arrival_time' => '04:30',
            'capacity' => 45,
            'available_seats' => 28,
            'route' => 'Industrial Park to City Center',
            'type' => 'evening',
            'schedule_type' => 'evening'
        ],
        [
            'id' => 3,
            'name' => 'Night Shift - Bus C',
            'departure_time' => '22:00',
            'arrival_time' => '08:00',
            'capacity' => 40,
            'available_seats' => 15,
            'route' => 'City Center to Industrial Park',
            'type' => 'night',
            'schedule_type' => 'night'
        ]
    ];

    return [
        'status' => 'success',
        'message' => 'Schedules retrieved successfully',
        'data' => [
            'schedules' => $schedules
        ]
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
    
    // Get bus details to determine slot
    $buses = getAvailableBuses();
    $selectedBus = null;
    foreach ($buses['data'] as $bus) {
        if ($bus['bus_number'] === $busNumber) {
            $selectedBus = $bus;
            break;
        }
    }
    
    if (!$selectedBus) {
        return [
            'status' => 'error',
            'message' => 'Selected bus not found'
        ];
    }
    
    $busSlot = $selectedBus['slot'] ?? 'unknown';
    
    $bookings = loadBookings();
    
    // Check for existing booking in the same slot (morning/evening)
    foreach ($bookings as $booking) {
        if ($booking['employee_id'] === $employeeId && 
            $booking['schedule_date'] === $scheduleDate &&
            $booking['status'] === 'active') {
            
            // Get the slot of the existing booking
            $existingBuses = getAvailableBuses();
            $existingSlot = 'unknown';
            foreach ($existingBuses['data'] as $bus) {
                if ($bus['bus_number'] === $booking['bus_number']) {
                    $existingSlot = $bus['slot'] ?? 'unknown';
                    break;
                }
            }
            
            if ($existingSlot === $busSlot) {
                $slotName = $busSlot === 'morning' ? 'Morning' : ($busSlot === 'evening' ? 'Evening' : $busSlot);
                return [
                    'status' => 'error',
                    'message' => "You already have a {$slotName} slot booking for today. Please cancel your existing {$slotName} booking first."
                ];
            }
        }
    }
    
    // Create new booking with slot information
    $bookingId = 'BK' . time() . rand(100, 999);
    $newBooking = [
        'id' => $bookingId,
        'employee_id' => $employeeId,
        'bus_number' => $busNumber,
        'schedule_date' => $scheduleDate,
        'slot' => $busSlot,
        'route' => $selectedBus['route'] ?? 'Route TBD',
        'departure_time' => $selectedBus['departure_time'] ?? '00:00',
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