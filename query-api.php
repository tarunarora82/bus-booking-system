<?php
/**
 * Simple API Router for Query Parameter Based Requests
 * Complete standalone implementation with all functions
 * Intel Corporate Proxy Compatible
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

// Database connection configuration
$config = [
    'host' => 'mysql',
    'username' => 'bususer', 
    'password' => 'buspass123',
    'database' => 'bus_booking'
];

function connectToDatabase($config) {
    try {
        $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['username'], $config['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Database connection failed: " . $e->getMessage());
    }
}

// File-based storage functions
function getBookingsFilePath() {
    return '/var/www/html/data/bookings.json';
}

function loadBookings() {
    $filePath = getBookingsFilePath();
    if (!file_exists($filePath)) {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($filePath, json_encode([]));
        return [];
    }
    $content = file_get_contents($filePath);
    return json_decode($content, true) ?: [];
}

function saveBookings($bookings) {
    $filePath = getBookingsFilePath();
    file_put_contents($filePath, json_encode($bookings, JSON_PRETTY_PRINT));
}

function loadBuses() {
    $filePath = '/var/www/html/data/buses.json';
    if (!file_exists($filePath)) {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $defaultBuses = getDefaultBuses();
        file_put_contents($filePath, json_encode($defaultBuses, JSON_PRETTY_PRINT));
        return $defaultBuses;
    }
    $content = file_get_contents($filePath);
    return json_decode($content, true) ?: getDefaultBuses();
}

function saveBuses($buses) {
    $filePath = '/var/www/html/data/buses.json';
    file_put_contents($filePath, json_encode($buses, JSON_PRETTY_PRINT));
}

function getDefaultBuses() {
    return [
        ['bus_number' => 'BUS001', 'route' => 'Office to Metro Station', 'capacity' => 40, 'departure_time' => '09:00', 'arrival_time' => '09:30'],
        ['bus_number' => 'BUS002', 'route' => 'Office to Downtown', 'capacity' => 35, 'departure_time' => '09:15', 'arrival_time' => '09:45'],
        ['bus_number' => 'BUS003', 'route' => 'Office to Airport', 'capacity' => 50, 'departure_time' => '17:30', 'arrival_time' => '18:30'],
        ['bus_number' => 'BUS004', 'route' => 'Metro to Office', 'capacity' => 40, 'departure_time' => '08:00', 'arrival_time' => '08:30'],
        ['bus_number' => 'BUS005', 'route' => 'Downtown to Office', 'capacity' => 35, 'departure_time' => '08:15', 'arrival_time' => '08:45']
    ];
}

function loadEmployees() {
    $filePath = '/var/www/html/data/employees.json';
    if (!file_exists($filePath)) {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $defaultEmployees = getDefaultEmployees();
        file_put_contents($filePath, json_encode($defaultEmployees, JSON_PRETTY_PRINT));
        return $defaultEmployees;
    }
    $content = file_get_contents($filePath);
    return json_decode($content, true) ?: getDefaultEmployees();
}

function saveEmployees($employees) {
    $filePath = '/var/www/html/data/employees.json';
    file_put_contents($filePath, json_encode($employees, JSON_PRETTY_PRINT));
}

function getDefaultEmployees() {
    return [
        ['id' => '11453732', 'name' => 'John Doe', 'department' => 'Engineering', 'email' => 'john.doe@intel.com'],
        ['id' => '11453733', 'name' => 'Jane Smith', 'department' => 'Marketing', 'email' => 'jane.smith@intel.com'],
        ['id' => '11453734', 'name' => 'Bob Johnson', 'department' => 'HR', 'email' => 'bob.johnson@intel.com'],
        ['id' => '11453735', 'name' => 'Alice Brown', 'department' => 'Finance', 'email' => 'alice.brown@intel.com'],
        ['id' => '11453736', 'name' => 'Charlie Wilson', 'department' => 'Operations', 'email' => 'charlie.wilson@intel.com']
    ];
}

function addBus($data) {
    $buses = loadBuses();
    
    // Check if bus number already exists
    foreach ($buses as $bus) {
        if ($bus['bus_number'] === $data['bus_number']) {
            return ['status' => 'error', 'message' => 'Bus number already exists'];
        }
    }
    
    $newBus = [
        'bus_number' => $data['bus_number'],
        'route' => $data['route'],
        'capacity' => (int)$data['capacity'],
        'departure_time' => $data['departure_time'],
        'arrival_time' => $data['arrival_time']
    ];
    
    $buses[] = $newBus;
    saveBuses($buses);
    
    return ['status' => 'success', 'message' => 'Bus added successfully', 'bus' => $newBus];
}

function updateBus($data) {
    $buses = loadBuses();
    
    for ($i = 0; $i < count($buses); $i++) {
        if ($buses[$i]['bus_number'] === $data['bus_number']) {
            $buses[$i]['route'] = $data['route'];
            $buses[$i]['capacity'] = (int)$data['capacity'];
            $buses[$i]['departure_time'] = $data['departure_time'];
            $buses[$i]['arrival_time'] = $data['arrival_time'];
            
            saveBuses($buses);
            return ['status' => 'success', 'message' => 'Bus updated successfully', 'bus' => $buses[$i]];
        }
    }
    
    return ['status' => 'error', 'message' => 'Bus not found'];
}

function deleteBus($busNumber) {
    $buses = loadBuses();
    
    for ($i = 0; $i < count($buses); $i++) {
        if ($buses[$i]['bus_number'] === $busNumber) {
            array_splice($buses, $i, 1);
            saveBuses($buses);
            return ['status' => 'success', 'message' => 'Bus deleted successfully'];
        }
    }
    
    return ['status' => 'error', 'message' => 'Bus not found'];
}

function createBooking($employeeId, $busNumber, $scheduleDate) {
    $bookings = loadBookings();
    $buses = loadBuses();
    
    // Find the bus and get its slot
    $bus = null;
    foreach ($buses as $b) {
        if ($b['bus_number'] === $busNumber) {
            $bus = $b;
            break;
        }
    }
    
    if (!$bus) {
        return ['status' => 'error', 'message' => 'Bus not found'];
    }
    
    $requestedSlot = $bus['slot'] ?? 'morning'; // Default to morning if no slot specified
    
    // Check if employee already has a booking for this slot on this date
    $existingBookings = [];
    foreach ($bookings as $booking) {
        if ($booking['employee_id'] === $employeeId && $booking['schedule_date'] === $scheduleDate && $booking['status'] === 'active') {
            // Find the bus for this booking to get its slot
            foreach ($buses as $busInfo) {
                if ($busInfo['bus_number'] === $booking['bus_number']) {
                    $booking['slot'] = $busInfo['slot'] ?? 'morning';
                    break;
                }
            }
            $existingBookings[] = $booking;
        }
    }
    
    // Check if employee already has a booking for this specific slot
    foreach ($existingBookings as $existing) {
        if ($existing['slot'] === $requestedSlot) {
            return ['status' => 'error', 'message' => "Employee already has a booking for this date in the $requestedSlot slot. Please cancel the existing booking first."];
        }
    }
    
    // Allow if employee has 0 bookings or 1 booking in different slot (max 2 total: 1 morning + 1 evening)
    if (count($existingBookings) >= 2) {
        return ['status' => 'error', 'message' => 'Employee already has maximum bookings for this date (one morning + one evening)'];
    }
    
    // Count existing bookings for this bus and date
    $bookedSeats = 0;
    foreach ($bookings as $booking) {
        if ($booking['bus_number'] === $busNumber && $booking['schedule_date'] === $scheduleDate) {
            $bookedSeats++;
        }
    }
    
    if ($bookedSeats >= $bus['capacity']) {
        return ['status' => 'error', 'message' => 'Bus is full'];
    }
    
    $newBooking = [
        'id' => uniqid(),
        'employee_id' => $employeeId,
        'bus_number' => $busNumber,
        'schedule_date' => $scheduleDate,
        'booking_time' => date('Y-m-d H:i:s'),
        'status' => 'active',
        'slot' => $requestedSlot,
        'route' => $bus['route'],
        'departure_time' => $bus['departure_time']
    ];
    
    $bookings[] = $newBooking;
    saveBookings($bookings);
    
    return ['status' => 'success', 'message' => 'Booking created successfully', 'booking' => $newBooking];
}

function getEmployeeBookings($employeeId, $date) {
    $bookings = loadBookings();
    $result = [];
    
    foreach ($bookings as $booking) {
        if ($booking['employee_id'] === $employeeId && 
            ($date === null || $booking['schedule_date'] === $date)) {
            $result[] = $booking;
        }
    }
    
    return $result;
}

function cancelBooking($employeeId, $busNumber) {
    $bookings = loadBookings();
    
    for ($i = 0; $i < count($bookings); $i++) {
        if ($bookings[$i]['employee_id'] === $employeeId && 
            $bookings[$i]['bus_number'] === $busNumber && 
            $bookings[$i]['status'] === 'active') {
            
            // Remove the booking
            array_splice($bookings, $i, 1);
            saveBookings($bookings);
            
            return ['status' => 'success', 'message' => 'Booking cancelled successfully'];
        }
    }
    
    return ['status' => 'error', 'message' => 'No active booking found for this employee and bus'];
}

// Get action from POST JSON body, POST parameters, or GET parameters
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// For POST requests with JSON body, check the JSON data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($action)) {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input && isset($input['action'])) {
        $action = $input['action'];
    }
}

// Log the request for debugging
error_log("Query API Request: action=$action, method=" . $_SERVER['REQUEST_METHOD']);

try {
    switch ($action) {
        case 'health-check':
            echo json_encode([
                'status' => 'success',
                'message' => 'Intel Bus Booking API is healthy',
                'timestamp' => date('Y-m-d H:i:s'),
                'server' => 'Intel Corporate Network Compatible'
            ]);
            break;

        case 'get-employees':
            $employees = loadEmployees();
            echo json_encode(['status' => 'success', 'data' => $employees]);
            break;

        case 'add-employee':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $employees = loadEmployees();
                
                // Check if employee ID already exists
                foreach ($employees as $emp) {
                    if ($emp['id'] === $input['id']) {
                        echo json_encode(['status' => 'error', 'message' => 'Employee ID already exists']);
                        exit;
                    }
                }
                
                $newEmployee = [
                    'id' => $input['id'],
                    'name' => $input['name'],
                    'department' => $input['department'],
                    'email' => $input['email']
                ];
                
                $employees[] = $newEmployee;
                saveEmployees($employees);
                
                echo json_encode(['status' => 'success', 'message' => 'Employee added successfully', 'employee' => $newEmployee]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'POST method required']);
            }
            break;

        case 'get-buses':
            $buses = loadBuses();
            echo json_encode(['status' => 'success', 'data' => $buses]);
            break;

        case 'add-bus':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $result = addBus($input);
                echo json_encode($result);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'POST method required']);
            }
            break;

        case 'update-bus':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $result = updateBus($input);
                echo json_encode($result);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'POST method required']);
            }
            break;

        case 'delete-bus':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $result = deleteBus($input['bus_number']);
                echo json_encode($result);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'POST method required']);
            }
            break;

        case 'available-buses':
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
                        $booking['status'] === 'active') {
                        $bookedSeats++;
                    }
                }
                
                $availableSeats = $bus['capacity'] - $bookedSeats;
                
                $busData[] = [
                    'bus_number' => $bus['bus_number'],
                    'route' => $bus['route'],
                    'capacity' => $bus['capacity'],
                    'available_seats' => max(0, $availableSeats),
                    'booked_seats' => $bookedSeats,
                    'departure_time' => $bus['departure_time'],
                    'arrival_time' => $bus['arrival_time']
                ];
            }
            
            echo json_encode(['status' => 'success', 'data' => $busData]);
            break;

            

        case 'create-booking':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $scheduleDate = $input['schedule_date'] ?? date('Y-m-d');
                $result = createBooking($input['employee_id'], $input['bus_number'], $scheduleDate);
                echo json_encode($result);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'POST method required']);
            }
            break;

        case 'employee-bookings':
            $employeeId = $_GET['employee_id'] ?? null;
            $date = $_GET['date'] ?? date('Y-m-d');
            
            if (!$employeeId) {
                echo json_encode(['status' => 'error', 'message' => 'Employee ID required']);
                break;
            }
            
            $bookings = getEmployeeBookings($employeeId, $date);
            echo json_encode(['status' => 'success', 'data' => $bookings]);
            break;

        case 'get-bookings':
            $bookings = loadBookings();
            echo json_encode(['status' => 'success', 'data' => $bookings]);
            break;

        case 'cancel-booking':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $result = cancelBooking($input['employee_id'], $input['bus_number']);
                echo json_encode($result);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'POST method required']);
            }
            break;

        case 'delete-all-bookings':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Clear all bookings for testing
                saveBookings([]);
                echo json_encode(['status' => 'success', 'message' => 'All bookings cleared']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'POST method required']);
            }
            break;
        
        case 'debug-cancel':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $bookings = loadBookings();
                $matches = [];
                foreach ($bookings as $booking) {
                    if ($booking['employee_id'] === $input['employee_id'] && 
                        $booking['bus_number'] === $input['bus_number']) {
                        $matches[] = $booking;
                    }
                }
                echo json_encode([
                    'status' => 'debug', 
                    'input' => $input,
                    'total_bookings' => count($bookings),
                    'matches' => $matches
                ]);
            }
            break;        default:
            echo json_encode([
                'status' => 'error',
                'message' => 'Unknown action: ' . $action,
                'available_actions' => [
                    'health-check', 'get-employees', 'add-employee', 'get-buses', 
                    'add-bus', 'update-bus', 'delete-bus', 'available-buses', 
                    'create-booking', 'cancel-booking', 'employee-bookings', 'get-bookings', 'delete-all-bookings', 'debug-cancel'
                ]
            ]);
            break;
    }

} catch (Exception $e) {
    error_log("Query API Error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error occurred',
        'debug' => $e->getMessage()
    ]);
}
?>