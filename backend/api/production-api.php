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
        handleQueryAction($action, $inputJson);
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

function handleQueryAction($action, $inputJson = null) {
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
            $input = $inputJson ?: $_POST;
            echo json_encode(createBooking($input));
            break;
            
        case 'create-reservation':
            $input = $inputJson ?: $_POST;
            echo json_encode(createReservation($input));
            break;
            
        case 'confirm-booking':
            $input = $inputJson ?: $_POST;
            echo json_encode(confirmBooking($input));
            break;
            
        case 'release-reservation':
            $input = $inputJson ?: $_POST;
            echo json_encode(releaseReservation($input));
            break;
            
        case 'cancel-booking':
            $input = $inputJson ?: $_POST;
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
    
    // Get bus details BEFORE acquiring lock to prevent deadlocks
    $buses = getAvailableBuses();
    $selectedBus = null;
    foreach ($buses['data'] as $bus) {
        if ($bus['bus_number'] === $busNumber) {
            $selectedBus = $bus;
            break;
        }
    }
    
    if (!$selectedBus) {
        return ['status' => 'error', 'message' => 'Selected bus not found'];
    }
    
    $busSlot = $selectedBus['slot'] ?? 'unknown';
    
    // ATOMIC OPERATION - File locked during booking process only
    global $dataPath;
    $bookingsFile = $dataPath . '/bookings.json';
    $handle = fopen($bookingsFile, 'c+');
    
    if (!$handle) {
        return ['status' => 'error', 'message' => 'Cannot access booking system'];
    }
    
    // EXCLUSIVE LOCK with timeout - Prevents concurrent access
    $lockAcquired = false;
    $maxAttempts = 30; // 3 seconds max wait
    $attempts = 0;
    
    while (!$lockAcquired && $attempts < $maxAttempts) {
        if (flock($handle, LOCK_EX | LOCK_NB)) {
            $lockAcquired = true;
        } else {
            $attempts++;
            usleep(100000); // 0.1 second
        }
    }
    
    if (!$lockAcquired) {
        fclose($handle);
        return ['status' => 'error', 'message' => 'System busy, please try again in a moment'];
    }
    
    try {
        // Read current bookings inside lock
        $content = stream_get_contents($handle);
        $bookings = $content ? json_decode($content, true) ?: [] : [];
        
        $busSlot = $selectedBus['slot'] ?? 'unknown';
        
        // Check for existing bookings (INSIDE LOCKED SECTION)
        foreach ($bookings as $booking) {
            if ($booking['employee_id'] === $employeeId && 
                $booking['schedule_date'] === $scheduleDate &&
                $booking['status'] === 'active') {
                
                // Get existing booking slot
                foreach ($buses['data'] as $bus) {
                    if ($bus['bus_number'] === $booking['bus_number']) {
                        $existingSlot = $bus['slot'] ?? 'unknown';
                        if ($existingSlot === $busSlot) {
                            $slotName = $busSlot === 'morning' ? 'Morning' : 'Evening';
                            return [
                                'status' => 'error',
                                'message' => "You already have a {$slotName} slot booking for today."
                            ];
                        }
                        break;
                    }
                }
            }
        }
        
        // Check bus capacity
        $busBookingCount = 0;
        foreach ($bookings as $booking) {
            if ($booking['bus_number'] === $busNumber && 
                $booking['schedule_date'] === $scheduleDate && 
                $booking['status'] === 'active') {
                $busBookingCount++;
            }
        }
        
        if ($busBookingCount >= ($selectedBus['capacity'] ?? 45)) {
            return ['status' => 'error', 'message' => 'Bus is fully booked'];
        }
        
        // Create booking (STILL INSIDE LOCKED SECTION)
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
        
        // Write back atomically
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($bookings, JSON_PRETTY_PRINT));
        
        return [
            'status' => 'success',
            'message' => 'Booking created successfully',
            'booking' => $newBooking
        ];
        
    } finally {
        // Always release lock
        flock($handle, LOCK_UN);
        fclose($handle);
    }
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
    
    $handle = fopen($bookingsFile, 'r');
    if (!$handle) {
        return [];
    }
    
    // SHARED LOCK for reading
    if (flock($handle, LOCK_SH)) {
        $content = stream_get_contents($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
        return $content ? json_decode($content, true) ?: [] : [];
    }
    
    fclose($handle);
    return [];
}

function saveBookings($bookings) {
    global $dataPath;
    $bookingsFile = $dataPath . '/bookings.json';
    
    $handle = fopen($bookingsFile, 'c');
    if (!$handle) {
        return false;
    }
    
    // EXCLUSIVE LOCK for writing
    if (flock($handle, LOCK_EX)) {
        ftruncate($handle, 0);
        rewind($handle);
        $result = fwrite($handle, json_encode($bookings, JSON_PRETTY_PRINT));
        flock($handle, LOCK_UN);
        fclose($handle);
        return $result !== false;
    }
    
    fclose($handle);
    return false;
}

// SOFT LOCKING FUNCTIONS
function createReservation($data) {
    $employeeId = $data['employee_id'] ?? '';
    $busNumber = $data['bus_number'] ?? '';
    $scheduleDate = $data['schedule_date'] ?? date('Y-m-d');
    $lockTimeout = 30; // 30 seconds reservation
    
    if (empty($employeeId) || empty($busNumber)) {
        return [
            'status' => 'error',
            'message' => 'Employee ID and Bus Number are required'
        ];
    }
    
    global $dataPath;
    $lockKey = "booking_lock_{$busNumber}_{$scheduleDate}";
    $lockFile = $dataPath . "/{$lockKey}.lock";
    $lockData = [
        'employee_id' => $employeeId,
        'bus_number' => $busNumber,
        'schedule_date' => $scheduleDate,
        'expires_at' => time() + $lockTimeout,
        'created_at' => time()
    ];
    
    // Check if bus exists and get details
    $buses = getAvailableBuses();
    $selectedBus = null;
    foreach ($buses['data'] as $bus) {
        if ($bus['bus_number'] === $busNumber) {
            $selectedBus = $bus;
            break;
        }
    }
    
    if (!$selectedBus) {
        return ['status' => 'error', 'message' => 'Selected bus not found'];
    }
    
    // Try to acquire soft lock
    $handle = fopen($lockFile, 'c+');
    if (!$handle) {
        return ['status' => 'error', 'message' => 'Cannot create reservation'];
    }
    
    if (flock($handle, LOCK_EX)) {
        // Check existing lock
        $content = stream_get_contents($handle);
        if ($content) {
            $existingLock = json_decode($content, true);
            if ($existingLock && $existingLock['expires_at'] > time()) {
                // Lock still valid
                if ($existingLock['employee_id'] !== $employeeId) {
                    flock($handle, LOCK_UN);
                    fclose($handle);
                    $timeLeft = $existingLock['expires_at'] - time();
                    return [
                        'status' => 'error',
                        'message' => "Bus is temporarily reserved by another user. Please try again in {$timeLeft} seconds."
                    ];
                }
            }
        }
        
        // Create/update reservation
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($lockData));
        flock($handle, LOCK_UN);
        fclose($handle);
        
        return [
            'status' => 'success',
            'message' => 'Bus reserved for 30 seconds',
            'reservation_token' => md5($lockKey . $employeeId),
            'expires_at' => $lockData['expires_at'],
            'bus_details' => $selectedBus
        ];
    }
    
    fclose($handle);
    return ['status' => 'error', 'message' => 'Cannot acquire reservation'];
}

function confirmBooking($data) {
    $employeeId = $data['employee_id'] ?? '';
    $busNumber = $data['bus_number'] ?? '';
    $scheduleDate = $data['schedule_date'] ?? date('Y-m-d');
    $reservationToken = $data['reservation_token'] ?? '';
    
    $lockKey = "booking_lock_{$busNumber}_{$scheduleDate}";
    $expectedToken = md5($lockKey . $employeeId);
    
    if ($reservationToken !== $expectedToken) {
        return ['status' => 'error', 'message' => 'Invalid reservation token'];
    }
    
    // Verify reservation is still valid
    global $dataPath;
    $lockFile = $dataPath . "/{$lockKey}.lock";
    if (file_exists($lockFile)) {
        $lockData = json_decode(file_get_contents($lockFile), true);
        if (!$lockData || $lockData['expires_at'] <= time() || $lockData['employee_id'] !== $employeeId) {
            @unlink($lockFile);
            return ['status' => 'error', 'message' => 'Reservation expired or invalid'];
        }
    } else {
        return ['status' => 'error', 'message' => 'Reservation not found'];
    }
    
    // Proceed with actual booking creation
    $result = createBooking([
        'employee_id' => $employeeId,
        'bus_number' => $busNumber,
        'schedule_date' => $scheduleDate
    ]);
    
    // Clean up reservation
    @unlink($lockFile);
    
    return $result;
}

function releaseReservation($data) {
    $employeeId = $data['employee_id'] ?? '';
    $busNumber = $data['bus_number'] ?? '';
    $scheduleDate = $data['schedule_date'] ?? date('Y-m-d');
    
    global $dataPath;
    $lockKey = "booking_lock_{$busNumber}_{$scheduleDate}";
    $lockFile = $dataPath . "/{$lockKey}.lock";
    
    if (file_exists($lockFile)) {
        $lockData = json_decode(file_get_contents($lockFile), true);
        if ($lockData && $lockData['employee_id'] === $employeeId) {
            @unlink($lockFile);
            return ['status' => 'success', 'message' => 'Reservation released'];
        }
    }
    
    return ['status' => 'error', 'message' => 'Reservation not found or not owned by user'];
}
?>