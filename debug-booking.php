<?php
$_SERVER["REQUEST_METHOD"] = "POST";
$_GET["action"] = "create-booking";
echo "Testing JSON input handling...\n";

// Simulate JSON input
$testJson = json_encode(["employee_id" => "EMP001", "bus_number" => "BUS001", "schedule_date" => "2025-10-01"]);
file_put_contents("/tmp/test_input", $testJson);

// Mock php://input
$rawInput = $testJson;
$inputJson = json_decode($rawInput, true);

echo "Raw input: " . $rawInput . "\n";
echo "Parsed JSON: " . print_r($inputJson, true) . "\n";

// Test the create booking with this data
$dataPath = "/var/www/html/data";
function createBooking($data) {
    global $dataPath;
    echo "createBooking called with: " . print_r($data, true) . "\n";
    
    $employeeId = $data["employee_id"] ?? "";
    $busNumber = $data["bus_number"] ?? "";
    $scheduleDate = $data["schedule_date"] ?? date("Y-m-d");
    
    echo "Employee ID: $employeeId\n";
    echo "Bus Number: $busNumber\n";
    echo "Schedule Date: $scheduleDate\n";
    
    if (empty($employeeId) || empty($busNumber)) {
        return [
            "status" => "error",
            "message" => "Employee ID and Bus Number are required"
        ];
    }
    
    return [
        "status" => "success", 
        "message" => "Booking would be created",
        "employee_id" => $employeeId,
        "bus_number" => $busNumber
    ];
}

$result = createBooking($inputJson);
echo "Result: " . json_encode($result) . "\n";
?>
