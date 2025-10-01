#!/bin/bash
# Production API Test Runner - Comprehensive Test Suite
# Date: October 1, 2025
# Purpose: Test all API endpoints and functionality

echo "🚀 Starting Comprehensive API Test Suite"
echo "=================================================="

# Test counters
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# Function to run a test
run_test() {
    local test_name="$1"
    local test_command="$2"
    local expected_status="$3"
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    echo ""
    echo "🔍 Test $TOTAL_TESTS: $test_name"
    echo "--------------------"
    
    # Run the test inside the PHP container to avoid proxy issues
    result=$(docker exec bus_booking_php curl -s -w "HTTPSTATUS:%{http_code}" "$test_command")
    
    # Extract body and status code
    body=$(echo "$result" | sed -E 's/HTTPSTATUS\:[0-9]{3}$//')
    status_code=$(echo "$result" | grep -o "HTTPSTATUS:[0-9]*" | cut -d: -f2)
    
    echo "Status Code: $status_code"
    echo "Response: $body"
    
    # Check if test passed
    if [[ "$status_code" == "$expected_status" ]]; then
        echo "✅ PASSED"
        PASSED_TESTS=$((PASSED_TESTS + 1))
    else
        echo "❌ FAILED (Expected: $expected_status, Got: $status_code)"
        FAILED_TESTS=$((FAILED_TESTS + 1))
    fi
}

# Function to run a POST test
run_post_test() {
    local test_name="$1"
    local test_url="$2"
    local test_data="$3"
    local expected_status="$4"
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    echo ""
    echo "🔍 Test $TOTAL_TESTS: $test_name"
    echo "--------------------"
    
    # Run the POST test inside the PHP container
    result=$(docker exec bus_booking_php curl -s -w "HTTPSTATUS:%{http_code}" -X POST -H "Content-Type: application/json" -d "$test_data" "$test_url")
    
    # Extract body and status code
    body=$(echo "$result" | sed -E 's/HTTPSTATUS\:[0-9]{3}$//')
    status_code=$(echo "$result" | grep -o "HTTPSTATUS:[0-9]*" | cut -d: -f2)
    
    echo "Status Code: $status_code"
    echo "Response: $body"
    
    # Check if test passed
    if [[ "$status_code" == "$expected_status" ]]; then
        echo "✅ PASSED"
        PASSED_TESTS=$((PASSED_TESTS + 1))
    else
        echo "❌ FAILED (Expected: $expected_status, Got: $status_code)"
        FAILED_TESTS=$((FAILED_TESTS + 1))
    fi
}

echo "📋 Starting API Endpoint Tests..."

# Test 1: Health Check (REST style)
run_test "Health Check (REST)" "http://localhost/api/health" "200"

# Test 2: Available Buses (REST style)
run_test "Available Buses (REST)" "http://localhost/api/buses/available" "200"

# Test 3: Employee Bookings (REST style)
run_test "Employee Bookings (REST)" "http://localhost/api/employee/bookings/11453732?date=2025-10-01" "200"

# Test 4: Health Check (Legacy query style)
run_test "Health Check (Legacy)" "http://localhost/api/production-api.php?action=health-check" "200"

# Test 5: Available Buses (Legacy query style)
run_test "Available Buses (Legacy)" "http://localhost/api/production-api.php?action=available-buses" "200"

# Test 6: Employee Bookings (Legacy query style)
run_test "Employee Bookings (Legacy)" "http://localhost/api/production-api.php?action=employee-bookings&employee_id=11453732&date=2025-10-01" "200"

# Test 7: Admin Settings (Legacy query style)
run_test "Admin Settings" "http://localhost/api/production-api.php?action=admin-settings" "200"

# Test 8: Admin Bookings (Legacy query style)
run_test "Admin Bookings" "http://localhost/api/production-api.php?action=admin-bookings" "200"

# Test 9: Create Booking (REST style)
run_post_test "Create Booking (REST)" "http://localhost/api/booking/create" '{"employee_id":"TEST123","bus_number":"BUS001","schedule_date":"2025-10-01"}' "200"

# Test 10: Cancel Booking (REST style)
run_post_test "Cancel Booking (REST)" "http://localhost/api/booking/cancel" '{"employee_id":"TEST123","bus_number":"BUS001","schedule_date":"2025-10-01"}' "200"

# Test 11: Invalid Endpoint (Error handling)
run_test "Invalid Endpoint" "http://localhost/api/invalid/endpoint" "404"

# Test 12: Invalid Action (Error handling)
run_test "Invalid Action" "http://localhost/api/production-api.php?action=invalid-action" "404"

# Test 13: Create Booking (Legacy query style)
run_post_test "Create Booking (Legacy)" "http://localhost/api/production-api.php?action=create-booking" '{"employee_id":"LEGACY_TEST","bus_number":"BUS002","schedule_date":"2025-10-01"}' "200"

# Test 14: Cancel Booking (Legacy query style)
run_post_test "Cancel Booking (Legacy)" "http://localhost/api/production-api.php?action=cancel-booking" '{"employee_id":"LEGACY_TEST","bus_number":"BUS002","schedule_date":"2025-10-01"}' "200"

echo ""
echo "=================================================="
echo "🎯 COMPREHENSIVE TEST RESULTS"
echo "=================================================="
echo "Total Tests: $TOTAL_TESTS"
echo "Passed: $PASSED_TESTS"
echo "Failed: $FAILED_TESTS"

# Calculate success rate
if [ $TOTAL_TESTS -gt 0 ]; then
    SUCCESS_RATE=$(( (PASSED_TESTS * 100) / TOTAL_TESTS ))
    echo "Success Rate: $SUCCESS_RATE%"
    
    if [ $FAILED_TESTS -eq 0 ]; then
        echo ""
        echo "🎉 ALL TESTS PASSED! Production system is ready!"
        echo "✅ Unified API architecture working perfectly"
        echo "✅ Both REST and legacy endpoints functional"
        echo "✅ Error handling working correctly"
        echo "✅ All CRUD operations operational"
        exit 0
    elif [ $SUCCESS_RATE -ge 80 ]; then
        echo ""
        echo "⚠️ Most tests passed - system mostly functional"
        echo "Some issues need attention before full production deployment"
        exit 1
    else
        echo ""
        echo "❌ Multiple test failures - system needs significant fixes"
        echo "Please review failed tests and fix issues before deployment"
        exit 2
    fi
else
    echo "❌ No tests were executed"
    exit 3
fi