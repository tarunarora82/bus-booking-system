# Production API Test Runner - Comprehensive Test Suite (PowerShell)
# Date: October 1, 2025
# Purpose: Test all API endpoints and functionality

Write-Host "🚀 Starting Comprehensive API Test Suite" -ForegroundColor Yellow
Write-Host "==================================================" -ForegroundColor Yellow

# Test counters
$TotalTests = 0
$PassedTests = 0
$FailedTests = 0

# Function to run a GET test
function Run-Test {
    param(
        [string]$TestName,
        [string]$TestUrl,
        [string]$ExpectedStatus
    )
    
    $script:TotalTests++
    Write-Host ""
    Write-Host "🔍 Test $($script:TotalTests): $TestName" -ForegroundColor Cyan
    Write-Host "--------------------" -ForegroundColor Gray
    
    try {
        # Run the test inside the PHP container to avoid proxy issues
        $result = docker exec bus_booking_php curl -s -w "HTTPSTATUS:%{http_code}" $TestUrl
        
        # Parse result (simple approach for PowerShell)
        if ($result -match "HTTPSTATUS:(\d+)$") {
            $StatusCode = $Matches[1]
            $Body = $result -replace "HTTPSTATUS:\d+$", ""
        } else {
            $StatusCode = "0"
            $Body = $result
        }
        
        Write-Host "Status Code: $StatusCode" -ForegroundColor White
        Write-Host "Response: $Body" -ForegroundColor Gray
        
        # Check if test passed
        if ($StatusCode -eq $ExpectedStatus) {
            Write-Host "✅ PASSED" -ForegroundColor Green
            $script:PassedTests++
        } else {
            Write-Host "❌ FAILED (Expected: $ExpectedStatus, Got: $StatusCode)" -ForegroundColor Red
            $script:FailedTests++
        }
    } catch {
        Write-Host "❌ FAILED (Exception: $($_.Exception.Message))" -ForegroundColor Red
        $script:FailedTests++
    }
}

# Function to run a POST test
function Run-PostTest {
    param(
        [string]$TestName,
        [string]$TestUrl,
        [string]$TestData,
        [string]$ExpectedStatus
    )
    
    $script:TotalTests++
    Write-Host ""
    Write-Host "🔍 Test $($script:TotalTests): $TestName" -ForegroundColor Cyan
    Write-Host "--------------------" -ForegroundColor Gray
    
    try {
        # Run the POST test inside the PHP container
        $result = docker exec bus_booking_php curl -s -w "HTTPSTATUS:%{http_code}" -X POST -H "Content-Type: application/json" -d $TestData $TestUrl
        
        # Parse result
        if ($result -match "HTTPSTATUS:(\d+)$") {
            $StatusCode = $Matches[1]
            $Body = $result -replace "HTTPSTATUS:\d+$", ""
        } else {
            $StatusCode = "0"
            $Body = $result
        }
        
        Write-Host "Status Code: $StatusCode" -ForegroundColor White
        Write-Host "Response: $Body" -ForegroundColor Gray
        
        # Check if test passed
        if ($StatusCode -eq $ExpectedStatus) {
            Write-Host "✅ PASSED" -ForegroundColor Green
            $script:PassedTests++
        } else {
            Write-Host "❌ FAILED (Expected: $ExpectedStatus, Got: $StatusCode)" -ForegroundColor Red
            $script:FailedTests++
        }
    } catch {
        Write-Host "❌ FAILED (Exception: $($_.Exception.Message))" -ForegroundColor Red
        $script:FailedTests++
    }
}

Write-Host "📋 Starting API Endpoint Tests..." -ForegroundColor Blue

# Test 1: Health Check (REST style)
Run-Test "Health Check (REST)" "http://localhost/api/health" "200"

# Test 2: Available Buses (REST style)
Run-Test "Available Buses (REST)" "http://localhost/api/buses/available" "200"

# Test 3: Employee Bookings (REST style)
Run-Test "Employee Bookings (REST)" "http://localhost/api/employee/bookings/11453732?date=2025-10-01" "200"

# Test 4: Health Check (Legacy query style)
Run-Test "Health Check (Legacy)" "http://localhost/api/api/production-api.php?action=health-check" "200"

# Test 5: Available Buses (Legacy query style)
Run-Test "Available Buses (Legacy)" "http://localhost/api/api/production-api.php?action=available-buses" "200"

# Test 6: Employee Bookings (Legacy query style)
Run-Test "Employee Bookings (Legacy)" "http://localhost/api/api/production-api.php?action=employee-bookings&employee_id=11453732&date=2025-10-01" "200"

# Test 7: Admin Settings (Legacy query style)
Run-Test "Admin Settings" "http://localhost/api/api/production-api.php?action=admin-settings" "200"

# Test 8: Admin Bookings (Legacy query style)
Run-Test "Admin Bookings" "http://localhost/api/api/production-api.php?action=admin-bookings" "200"

# Test 9: Create Booking (REST style)
Run-PostTest "Create Booking (REST)" "http://localhost/api/booking/create" '{"employee_id":"TEST123","bus_number":"BUS001","schedule_date":"2025-10-01"}' "200"

# Test 10: Cancel Booking (REST style)
Run-PostTest "Cancel Booking (REST)" "http://localhost/api/booking/cancel" '{"employee_id":"TEST123","bus_number":"BUS001","schedule_date":"2025-10-01"}' "200"

# Test 11: Invalid Endpoint (Error handling)
Run-Test "Invalid Endpoint" "http://localhost/api/invalid/endpoint" "404"

# Test 12: Invalid Action (Error handling)
Run-Test "Invalid Action" "http://localhost/api/api/production-api.php?action=invalid-action" "404"

# Test 13: Create Booking (Legacy query style)
Run-PostTest "Create Booking (Legacy)" "http://localhost/api/api/production-api.php?action=create-booking" '{"employee_id":"LEGACY_TEST","bus_number":"BUS002","schedule_date":"2025-10-01"}' "200"

# Test 14: Cancel Booking (Legacy query style)
Run-PostTest "Cancel Booking (Legacy)" "http://localhost/api/api/production-api.php?action=cancel-booking" '{"employee_id":"LEGACY_TEST","bus_number":"BUS002","schedule_date":"2025-10-01"}' "200"

Write-Host ""
Write-Host "==================================================" -ForegroundColor Yellow
Write-Host "🎯 COMPREHENSIVE TEST RESULTS" -ForegroundColor Yellow
Write-Host "==================================================" -ForegroundColor Yellow
Write-Host "Total Tests: $TotalTests" -ForegroundColor White
Write-Host "Passed: $PassedTests" -ForegroundColor Green
Write-Host "Failed: $FailedTests" -ForegroundColor Red

# Calculate success rate
if ($TotalTests -gt 0) {
    $SuccessRate = [math]::Round(($PassedTests * 100) / $TotalTests)
    Write-Host "Success Rate: $SuccessRate%" -ForegroundColor White
    
    if ($FailedTests -eq 0) {
        Write-Host ""
        Write-Host "🎉 ALL TESTS PASSED! Production system is ready!" -ForegroundColor Green
        Write-Host "✅ Unified API architecture working perfectly" -ForegroundColor Green
        Write-Host "✅ Both REST and legacy endpoints functional" -ForegroundColor Green
        Write-Host "✅ Error handling working correctly" -ForegroundColor Green
        Write-Host "✅ All CRUD operations operational" -ForegroundColor Green
    } elseif ($SuccessRate -ge 80) {
        Write-Host ""
        Write-Host "⚠️ Most tests passed - system mostly functional" -ForegroundColor Yellow
        Write-Host "Some issues need attention before full production deployment" -ForegroundColor Yellow
    } else {
        Write-Host ""
        Write-Host "❌ Multiple test failures - system needs significant fixes" -ForegroundColor Red
        Write-Host "Please review failed tests and fix issues before deployment" -ForegroundColor Red
    }
} else {
    Write-Host "❌ No tests were executed" -ForegroundColor Red
}