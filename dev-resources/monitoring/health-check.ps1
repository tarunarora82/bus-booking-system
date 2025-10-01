# Bus Booking System - Health Monitoring Script (PowerShell)
# This script checks all system components and services

Write-Host "🚌 Bus Booking System - Health Check Monitor" -ForegroundColor Cyan
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host ""

# Function to test URL
function Test-Url {
    param(
        [string]$Url,
        [string]$Name,
        [int]$ExpectedStatus = 200
    )
    
    Write-Host "Testing $Name... " -NoNewline
    
    try {
        $response = Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 10 -ErrorAction Stop -Proxy $null
        if ($response.StatusCode -eq $ExpectedStatus) {
            Write-Host "✅ PASS" -ForegroundColor Green -NoNewline
            Write-Host " (HTTP $($response.StatusCode))"
            return $true
        } else {
            Write-Host "❌ FAIL" -ForegroundColor Red -NoNewline
            Write-Host " (HTTP $($response.StatusCode))"
            return $false
        }
    } catch {
        Write-Host "❌ FAIL" -ForegroundColor Red -NoNewline
        Write-Host " (Error: $($_.Exception.Message))"
        return $false
    }
}

# Function to test Docker container
function Test-Container {
    param(
        [string]$ContainerName,
        [string]$ServiceName
    )
    
    Write-Host "Testing $ServiceName container... " -NoNewline
    
    try {
        $containers = docker ps --format "table {{.Names}}" 2>$null
        if ($containers -and $containers -match $ContainerName) {
            Write-Host "✅ RUNNING" -ForegroundColor Green
            return $true
        } else {
            Write-Host "❌ NOT RUNNING" -ForegroundColor Red
            return $false
        }
    } catch {
        Write-Host "⚠️ SKIP" -ForegroundColor Yellow -NoNewline
        Write-Host " (Docker not available)"
        return $false
    }
}

# Test Docker Containers
Write-Host "🐳 Docker Containers:" -ForegroundColor Yellow
Test-Container "bus_booking_nginx" "Nginx"
Test-Container "bus_booking_php" "PHP-FPM"
Test-Container "bus_booking_mysql" "MySQL"
Test-Container "bus_booking_redis" "Redis"
Write-Host ""

# Test API Endpoints
Write-Host "🌐 API Endpoints:" -ForegroundColor Yellow
Test-Url "http://localhost:8080/api/health" "API Health Check"
Test-Url "http://localhost:8080/api/buses/available" "Bus Availability"
Test-Url "http://localhost:8080/api/admin/settings" "Database Connectivity"
Test-Url "http://localhost:8080/api/bookings" "Bookings API"
Test-Url "http://localhost:8080/api/admin/recent-bookings" "Admin API"
Write-Host ""

# Test CORS with OPTIONS
Write-Host "🔒 CORS Configuration:" -ForegroundColor Yellow
Write-Host "Testing CORS preflight... " -NoNewline
try {
    $headers = @{
        'Access-Control-Request-Method' = 'GET'
        'Access-Control-Request-Headers' = 'Content-Type'
    }
    $response = Invoke-WebRequest -Uri "http://localhost:8080/api/buses/available" -Method Options -Headers $headers -UseBasicParsing -TimeoutSec 10 -ErrorAction Stop -Proxy $null
    
    if ($response.StatusCode -eq 204) {
        Write-Host "✅ PASS" -ForegroundColor Green -NoNewline
        Write-Host " (HTTP $($response.StatusCode))"
    } else {
        Write-Host "❌ FAIL" -ForegroundColor Red -NoNewline
        Write-Host " (HTTP $($response.StatusCode))"
    }
} catch {
    Write-Host "❌ FAIL" -ForegroundColor Red -NoNewline
    Write-Host " (Error: $($_.Exception.Message))"
}
Write-Host ""

# Test Frontend
Write-Host "🖥️ Frontend:" -ForegroundColor Yellow
Test-Url "http://localhost:8080/" "Main Frontend"
Test-Url "http://localhost:8080/api-health-check.html" "Health Check Page"
Write-Host ""

# Performance Test
Write-Host "⚡ Performance:" -ForegroundColor Yellow
Write-Host "Testing API response time... " -NoNewline
try {
    $startTime = Get-Date
    $response = Invoke-WebRequest -Uri "http://localhost:8080/api/health" -UseBasicParsing -TimeoutSec 10 -ErrorAction Stop -Proxy $null
    $endTime = Get-Date
    $duration = ($endTime - $startTime).TotalMilliseconds
    
    if ($duration -lt 1000) {
        Write-Host "✅ FAST" -ForegroundColor Green -NoNewline
        Write-Host " ($([math]::Round($duration))ms)"
    } elseif ($duration -lt 2000) {
        Write-Host "⚠️ SLOW" -ForegroundColor Yellow -NoNewline
        Write-Host " ($([math]::Round($duration))ms)"
    } else {
        Write-Host "❌ VERY SLOW" -ForegroundColor Red -NoNewline
        Write-Host " ($([math]::Round($duration))ms)"
    }
} catch {
    Write-Host "❌ FAIL" -ForegroundColor Red -NoNewline
    Write-Host " (Error: $($_.Exception.Message))"
}
Write-Host ""

Write-Host "Health check completed! 🏁" -ForegroundColor Green
Write-Host ""
Write-Host "💡 Tips:" -ForegroundColor Cyan
Write-Host "- If any container is not running, use: docker-compose up -d"
Write-Host "- If API endpoints fail, check: docker logs bus_booking_nginx"
Write-Host "- For database issues, check: docker logs bus_booking_mysql"
Write-Host "- View live health status at: http://localhost:8080/api-health-check.html"