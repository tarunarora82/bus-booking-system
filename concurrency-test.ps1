# Concurrency Test for Bus Booking System
# Tests if two users can book the same slot simultaneously

Write-Host "🧪 CONCURRENCY TEST: Simultaneous Booking Attempts" -ForegroundColor Yellow
Write-Host "Testing if two users can book the same bus/slot at the same time" -ForegroundColor Gray

$apiUrl = "http://localhost:8080/api/api/production-api.php"

# Test data - Two different employees trying to book the same bus (B001 - morning slot)
$employee1 = @{
    employee_id = "EMP001"
    bus_number = "B001"
    schedule_date = (Get-Date -Format "yyyy-MM-dd")
}

$employee2 = @{
    employee_id = "EMP002"  
    bus_number = "B001"
    schedule_date = (Get-Date -Format "yyyy-MM-dd")
}

# Clear any existing bookings first
Write-Host "`n🧹 Clearing existing bookings..." -ForegroundColor Blue
$clearParams = @{
    Uri = "$apiUrl?action=clear-bookings"
    Method = "GET"
    ContentType = "application/json"
    ErrorAction = "SilentlyContinue"
}
try { Invoke-RestMethod @clearParams } catch { }

# Function to make booking request
function Make-BookingRequest {
    param($employeeData, $employeeName)
    
    $body = @{
        action = "create-booking"
        employee_id = $employeeData.employee_id
        bus_number = $employeeData.bus_number
        schedule_date = $employeeData.schedule_date
    } | ConvertTo-Json
    
    $params = @{
        Uri = $apiUrl
        Method = "POST"
        Body = $body
        ContentType = "application/json"
        ErrorAction = "SilentlyContinue"
    }
    
    try {
        $response = Invoke-RestMethod @params
        Write-Host "✅ $employeeName Response: $($response.status) - $($response.message)" -ForegroundColor Green
        return $response
    } catch {
        Write-Host "❌ $employeeName Error: $($_.Exception.Message)" -ForegroundColor Red
        return $null
    }
}

Write-Host "`n⚡ Launching simultaneous booking requests..." -ForegroundColor Cyan

# Launch both requests simultaneously using background jobs
$job1 = Start-Job -ScriptBlock {
    param($apiUrl, $employeeData)
    
    $body = @{
        action = "create-booking"
        employee_id = $employeeData.employee_id
        bus_number = $employeeData.bus_number  
        schedule_date = $employeeData.schedule_date
    } | ConvertTo-Json
    
    $params = @{
        Uri = $apiUrl
        Method = "POST"
        Body = $body
        ContentType = "application/json"
        ErrorAction = "SilentlyContinue"
    }
    
    try {
        Invoke-RestMethod @params
    } catch {
        @{ status = "error"; message = $_.Exception.Message }
    }
} -ArgumentList $apiUrl, $employee1

$job2 = Start-Job -ScriptBlock {
    param($apiUrl, $employeeData)
    
    $body = @{
        action = "create-booking"
        employee_id = $employeeData.employee_id
        bus_number = $employeeData.bus_number
        schedule_date = $employeeData.schedule_date  
    } | ConvertTo-Json
    
    $params = @{
        Uri = $apiUrl
        Method = "POST"
        Body = $body
        ContentType = "application/json"
        ErrorAction = "SilentlyContinue"
    }
    
    try {
        Invoke-RestMethod @params
    } catch {
        @{ status = "error"; message = $_.Exception.Message }
    }
} -ArgumentList $apiUrl, $employee2

# Wait for both jobs to complete
$result1 = Receive-Job $job1 -Wait
$result2 = Receive-Job $job2 -Wait

Remove-Job $job1, $job2

Write-Host "`n📊 RESULTS:" -ForegroundColor Yellow
Write-Host "Employee 1 (EMP001): $($result1.status) - $($result1.message)" -ForegroundColor $(if($result1.status -eq "success") { "Green" } else { "Red" })
Write-Host "Employee 2 (EMP002): $($result2.status) - $($result2.message)" -ForegroundColor $(if($result2.status -eq "success") { "Green" } else { "Red" })

# Check final booking state
Write-Host "`n🔍 Checking final booking state..." -ForegroundColor Blue
$checkParams = @{
    Uri = "$apiUrl?action=admin-bookings"
    Method = "GET"
    ContentType = "application/json"
    ErrorAction = "SilentlyContinue"
}

try {
    $bookings = Invoke-RestMethod @checkParams
    $todayBookings = $bookings.data | Where-Object { $_.schedule_date -eq (Get-Date -Format "yyyy-MM-dd") -and $_.status -eq "active" }
    $b001Bookings = $todayBookings | Where-Object { $_.bus_number -eq "B001" }
    
    Write-Host "📈 Active bookings for B001 today: $($b001Bookings.Count)" -ForegroundColor Cyan
    if ($b001Bookings.Count -gt 0) {
        $b001Bookings | ForEach-Object {
            Write-Host "  - Employee: $($_.employee_id), Booking ID: $($_.id), Time: $($_.created_at)" -ForegroundColor Gray
        }
    }
    
    # Analysis
    Write-Host "`n🎯 CONCURRENCY ANALYSIS:" -ForegroundColor Magenta
    if ($b001Bookings.Count -gt 1) {
        Write-Host "⚠️  RACE CONDITION DETECTED: Multiple bookings allowed for same bus!" -ForegroundColor Red
        Write-Host "❌ System lacks proper concurrency control" -ForegroundColor Red
    } elseif ($b001Bookings.Count -eq 1) {
        Write-Host "✅ Only one booking succeeded - system handled concurrency correctly" -ForegroundColor Green
    } else {
        Write-Host "❓ No bookings found - both requests may have failed" -ForegroundColor Yellow
    }
    
} catch {
    Write-Host "❌ Failed to check booking state: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host "`n✨ Test completed!" -ForegroundColor Green