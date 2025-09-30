<?php
/**
 * Simple API for Bus Booking System
 * Handles basic API requests without external dependencies
 */

// Set JSON header
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Simple routing
$requestUri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Remove query string from URI
$path = parse_url($requestUri, PHP_URL_PATH);

// Check for JSONP callback parameter
$callback = isset($_GET['callback']) ? $_GET['callback'] : null;

// Basic routing - match the exact path the frontend is calling
if ($method === 'GET' && ($path === '/api/schedules/available' || strpos($path, '/api/schedules') !== false)) {
    handleSchedules($callback);
} else {
    http_response_code(404);
    $response = json_encode(['error' => 'Endpoint not found', 'path' => $path, 'method' => $method]);
    if ($callback) {
        echo $callback . '(' . $response . ');';
    } else {
        echo $response;
    }
}

function handleSchedules($callback = null) {
    // Return data in the format the frontend expects
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
    
    http_response_code(200);
    
    $response = json_encode([
        'success' => true,
        'data' => [
            'schedules' => $schedules
        ],
        'message' => 'Schedules retrieved successfully'
    ]);
    
    // Support JSONP for cross-origin requests
    if ($callback) {
        header('Content-Type: application/javascript');
        echo $callback . '(' . $response . ');';
    } else {
        echo $response;
    }
}
?>