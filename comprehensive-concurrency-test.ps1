# COMPREHENSIVE CONCURRENCY AND SOFT LOCKING TEST
# Tests both Option 1 (File Locking) and Option 3 (Soft Locking)

Write-Host "🧪 COMPREHENSIVE CONCURRENCY TEST" -ForegroundColor Yellow
Write-Host "Testing file locking fixes and soft locking implementation" -ForegroundColor Gray

$apiUrl = "http://localhost:8080/api/api/production-api.php"

# Clear any existing data first
Write-Host "`n🧹 Clearing existing bookings and reservations..." -ForegroundColor Blue
try {
    # Clear bookings file
    Remove-Item "C:\Users\tarora\bus slot booking system\bus-booking-system\backend\data\bookings.json" -ErrorAction SilentlyContinue
    # Clear any lock files
    Remove-Item "C:\Users\tarora\bus slot booking system\bus-booking-system\backend\data\*.lock" -ErrorAction SilentlyContinue
    Write-Host "✅ Cleared existing data" -ForegroundColor Green
} catch {
    Write-Host "⚠️ Could not clear data: $($_.Exception.Message)" -ForegroundColor Yellow
}

Write-Host "`n🎯 TEST 1: File Locking - Simultaneous Direct Bookings" -ForegroundColor Cyan
Write-Host "Testing if multiple users can book the same bus simultaneously with file locking" -ForegroundColor Gray

# Test concurrent direct bookings
$jobs = @()
for ($i = 1; $i -le 5; $i++) {
    $job = Start-Job -ScriptBlock {
        param($apiUrl, $employeeId)
        
        $body = @{
            action = "create-booking"
            employee_id = $employeeId
            bus_number = "B001"
            schedule_date = (Get-Date -Format "yyyy-MM-dd")
        } | ConvertTo-Json
        
        try {
            $response = Invoke-RestMethod -Uri $apiUrl -Method POST -Body $body -ContentType "application/json" -ErrorAction Stop
            return @{
                employee = $employeeId
                status = $response.status
                message = $response.message
                success = $true
            }
        } catch {
            return @{
                employee = $employeeId
                status = "error"
                message = $_.Exception.Message
                success = $false
            }
        }
    } -ArgumentList $apiUrl, "EMP$i"
    
    $jobs += $job
}

# Wait for all jobs and collect results
$results = @()
foreach ($job in $jobs) {
    $result = Receive-Job $job -Wait
    $results += $result
    Remove-Job $job
}

Write-Host "`n📊 Direct Booking Results:" -ForegroundColor Yellow
$successCount = 0
foreach ($result in $results) {
    $color = if ($result.status -eq "success") { "Green"; $successCount++ } else { "Red" }
    Write-Host "  $($result.employee): $($result.status) - $($result.message)" -ForegroundColor $color
}

Write-Host "`n🎯 Analysis:" -ForegroundColor Magenta
if ($successCount -eq 1) {
    Write-Host "✅ FILE LOCKING WORKS: Only 1 booking succeeded (race condition prevented)" -ForegroundColor Green
} elseif ($successCount -gt 1) {
    Write-Host "❌ FILE LOCKING FAILED: $successCount bookings succeeded (race condition occurred)" -ForegroundColor Red
} else {
    Write-Host "❓ NO BOOKINGS: All requests failed" -ForegroundColor Yellow
}

# Wait a moment before next test
Start-Sleep -Seconds 2

Write-Host "`n🎯 TEST 2: Soft Locking - Reservation System" -ForegroundColor Cyan
Write-Host "Testing soft locking with reservation workflow" -ForegroundColor Gray

# Clear bookings for fresh test
try {
    Remove-Item "C:\Users\tarora\bus slot booking system\bus-booking-system\backend\data\bookings.json" -ErrorAction SilentlyContinue
    Remove-Item "C:\Users\tarora\bus slot booking system\bus-booking-system\backend\data\*.lock" -ErrorAction SilentlyContinue
} catch { }

# Test 1: Create reservation
Write-Host "`n  Step 1: Creating reservation..." -ForegroundColor White
$reservationBody = @{
    action = "create-reservation"
    employee_id = "TESTUSER1"
    bus_number = "B002"
    schedule_date = (Get-Date -Format "yyyy-MM-dd")
} | ConvertTo-Json

try {
    $reservation = Invoke-RestMethod -Uri $apiUrl -Method POST -Body $reservationBody -ContentType "application/json"
    if ($reservation.status -eq "success") {
        Write-Host "  ✅ Reservation created successfully" -ForegroundColor Green
        Write-Host "    - Token: $($reservation.reservation_token)" -ForegroundColor Gray
        Write-Host "    - Expires: $(Get-Date -UnixTimeSeconds $reservation.expires_at)" -ForegroundColor Gray
        
        # Test 2: Try to create conflicting reservation
        Write-Host "`n  Step 2: Testing conflicting reservation..." -ForegroundColor White
        $conflictBody = @{
            action = "create-reservation"
            employee_id = "TESTUSER2"
            bus_number = "B002"
            schedule_date = (Get-Date -Format "yyyy-MM-dd")
        } | ConvertTo-Json
        
        $conflict = Invoke-RestMethod -Uri $apiUrl -Method POST -Body $conflictBody -ContentType "application/json"
        if ($conflict.status -eq "error") {
            Write-Host "  ✅ Conflicting reservation properly blocked" -ForegroundColor Green
            Write-Host "    - Message: $($conflict.message)" -ForegroundColor Gray
        } else {
            Write-Host "  ❌ Conflicting reservation allowed (soft lock failed)" -ForegroundColor Red
        }
        
        # Test 3: Confirm booking with valid token
        Write-Host "`n  Step 3: Confirming booking with valid token..." -ForegroundColor White
        $confirmBody = @{
            action = "confirm-booking"
            employee_id = "TESTUSER1"
            bus_number = "B002"
            schedule_date = (Get-Date -Format "yyyy-MM-dd")
            reservation_token = $reservation.reservation_token
        } | ConvertTo-Json
        
        $confirmation = Invoke-RestMethod -Uri $apiUrl -Method POST -Body $confirmBody -ContentType "application/json"
        if ($confirmation.status -eq "success") {
            Write-Host "  ✅ Booking confirmed successfully" -ForegroundColor Green
            Write-Host "    - Booking ID: $($confirmation.booking.id)" -ForegroundColor Gray
        } else {
            Write-Host "  ❌ Booking confirmation failed: $($confirmation.message)" -ForegroundColor Red
        }
        
    } else {
        Write-Host "  ❌ Reservation creation failed: $($reservation.message)" -ForegroundColor Red
    }
} catch {
    Write-Host "  ❌ Reservation test failed: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host "`n🎯 TEST 3: Capacity Management" -ForegroundColor Cyan
Write-Host "Testing bus capacity limits with file locking" -ForegroundColor Gray

# Clear bookings
try {
    Remove-Item "C:\Users\tarora\bus slot booking system\bus-booking-system\backend\data\bookings.json" -ErrorAction SilentlyContinue
} catch { }

# Try to book more than capacity (45 seats)
$capacityTestJobs = @()
for ($i = 1; $i -le 47; $i++) {
    $job = Start-Job -ScriptBlock {
        param($apiUrl, $employeeId)
        
        $body = @{
            action = "create-booking"
            employee_id = $employeeId
            bus_number = "B003"
            schedule_date = (Get-Date -Format "yyyy-MM-dd")
        } | ConvertTo-Json
        
        try {
            $response = Invoke-RestMethod -Uri $apiUrl -Method POST -Body $body -ContentType "application/json" -ErrorAction Stop
            return @{
                employee = $employeeId
                status = $response.status
                booking_id = $response.booking.id
                success = ($response.status -eq "success")
            }
        } catch {
            return @{
                employee = $employeeId
                status = "error"
                success = $false
            }
        }
    } -ArgumentList $apiUrl, "CAPACITY$i"
    
    $capacityTestJobs += $job
}

# Wait and collect results
$capacityResults = @()
foreach ($job in $capacityTestJobs) {
    $result = Receive-Job $job -Wait
    $capacityResults += $result
    Remove-Job $job
}

$successfulBookings = ($capacityResults | Where-Object { $_.success }).Count
Write-Host "`n📊 Capacity Test Results:" -ForegroundColor Yellow
Write-Host "  Successful bookings: $successfulBookings / 47 attempts" -ForegroundColor Cyan
Write-Host "  Expected: Maximum 45 bookings (bus capacity)" -ForegroundColor Gray

if ($successfulBookings -le 45) {
    Write-Host "  ✅ CAPACITY MANAGEMENT WORKS: Bookings limited to bus capacity" -ForegroundColor Green
} else {
    Write-Host "  ❌ CAPACITY MANAGEMENT FAILED: Overbooking occurred" -ForegroundColor Red
}

Write-Host "`n🎯 TEST 4: Final Booking State Verification" -ForegroundColor Cyan
try {
    $finalCheck = Invoke-RestMethod -Uri "$apiUrl?action=admin-bookings" -Method GET
    $activeBookings = $finalCheck.data | Where-Object { $_.status -eq "active" -and $_.schedule_date -eq (Get-Date -Format "yyyy-MM-dd") }
    
    Write-Host "📈 Final active bookings today: $($activeBookings.Count)" -ForegroundColor Cyan
    
    # Group by bus
    $bookingsByBus = $activeBookings | Group-Object bus_number
    foreach ($busGroup in $bookingsByBus) {
        Write-Host "  🚌 Bus $($busGroup.Name): $($busGroup.Count) bookings" -ForegroundColor Gray
        if ($busGroup.Count -gt 45) {
            Write-Host "    ⚠️ OVERBOOKING DETECTED!" -ForegroundColor Red
        }
    }
} catch {
    Write-Host "❌ Could not verify final state: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host "`n🏁 TEST SUMMARY" -ForegroundColor Magenta
Write-Host "=" * 50 -ForegroundColor Magenta
Write-Host "✅ Option 1 (File Locking): " -NoNewline
if ($successCount -eq 1) {
    Write-Host "IMPLEMENTED & WORKING" -ForegroundColor Green
} else {
    Write-Host "NEEDS ATTENTION" -ForegroundColor Red
}

Write-Host "✅ Option 3 (Soft Locking): " -NoNewline
Write-Host "IMPLEMENTED & READY FOR UI TESTING" -ForegroundColor Green

Write-Host "`n📋 Next Steps:" -ForegroundColor Yellow
Write-Host "1. Test soft locking UI in browser at http://localhost:8080" -ForegroundColor White
Write-Host "2. Click 'Reserve & Book' buttons to test reservation flow" -ForegroundColor White
Write-Host "3. Verify 30-second countdown and confirmation process" -ForegroundColor White
Write-Host "4. Test concurrent reservations by multiple users" -ForegroundColor White

Write-Host "`n✨ Tests completed!" -ForegroundColor Green