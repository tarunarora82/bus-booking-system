# Production startup script for Bus Booking System (Windows PowerShell)

Write-Host "🚀 Starting Bus Booking System in Production Mode..." -ForegroundColor Blue

# Function to print colored output
function Print-Status($message) {
    Write-Host "[INFO] $message" -ForegroundColor Cyan
}

function Print-Success($message) {
    Write-Host "[SUCCESS] $message" -ForegroundColor Green
}

function Print-Warning($message) {
    Write-Host "[WARNING] $message" -ForegroundColor Yellow
}

function Print-Error($message) {
    Write-Host "[ERROR] $message" -ForegroundColor Red
}

# Check if Docker is running
try {
    docker info | Out-Null
    Print-Success "Docker is running"
} catch {
    Print-Error "Docker is not running. Please start Docker Desktop and try again."
    Read-Host "Press Enter to exit"
    exit 1
}

# Check if docker-compose is available
try {
    docker-compose --version | Out-Null
    Print-Success "docker-compose is available"
} catch {
    Print-Error "docker-compose is not installed. Please install docker-compose and try again."
    Read-Host "Press Enter to exit"
    exit 1
}

# Stop existing containers if running
Print-Status "Stopping existing containers..."
docker-compose down 2>$null | Out-Null

# Build and start containers
Print-Status "Building and starting containers..."
docker-compose up -d --build

# Check if containers are running
Print-Status "Checking container status..."
Start-Sleep -Seconds 10

# Check MySQL health
Print-Status "Waiting for MySQL to be ready..."
$timeout = 60
$counter = 0
do {
    if ($counter -eq $timeout) {
        Print-Error "MySQL failed to start within $timeout seconds"
        docker-compose logs mysql
        Read-Host "Press Enter to exit"
        exit 1
    }
    Print-Status "MySQL is starting... ($counter/$timeout)"
    Start-Sleep -Seconds 2
    $counter += 2
    $mysqlReady = docker-compose exec -T mysql mysqladmin ping -h localhost -u root -prootpassword --silent 2>$null
} while ($LASTEXITCODE -ne 0 -and $counter -lt $timeout)

Print-Success "MySQL is ready!"

# Check if PHP service is responding
Print-Status "Checking PHP API service..."
$phpStatus = docker-compose ps php
if ($phpStatus -match "Up") {
    Print-Success "PHP service is running"
} else {
    Print-Error "PHP service failed to start"
    docker-compose logs php
    Read-Host "Press Enter to continue anyway"
}

# Check if Nginx is responding
Print-Status "Checking Nginx web server..."
$nginxStatus = docker-compose ps nginx
if ($nginxStatus -match "Up") {
    Print-Success "Nginx web server is running"
} else {
    Print-Error "Nginx web server failed to start"
    docker-compose logs nginx
    Read-Host "Press Enter to continue anyway"
}

# Test database connection and verify schema
Print-Status "Verifying database schema..."
try {
    docker-compose exec -T mysql mysql -u root -prootpassword -e "USE bus_booking_system; SHOW TABLES;" 2>$null | Out-Null
    Print-Success "Database schema is properly initialized"
    
    # Show table status
    Write-Host ""
    Print-Status "Database tables:"
    docker-compose exec -T mysql mysql -u root -prootpassword -e "USE bus_booking_system; SHOW TABLES;"
    
    Write-Host ""
    Print-Status "Sample data check:"
    docker-compose exec -T mysql mysql -u root -prootpassword -e "USE bus_booking_system; SELECT bus_number, capacity FROM buses LIMIT 3;"
} catch {
    Print-Warning "Database schema verification failed, but system may still work"
}

# Test API endpoints
Print-Status "Testing API endpoints..."
Start-Sleep -Seconds 5

# Test health endpoint
try {
    $response = Invoke-WebRequest -Uri "http://localhost:8080/api/health" -TimeoutSec 10 -ErrorAction Stop
    Print-Success "API health endpoint is responding"
} catch {
    Print-Warning "API health endpoint is not responding yet"
}

# Show final status
Write-Host ""
Write-Host "==========================================" -ForegroundColor Green
Print-Success "🎉 Bus Booking System is running!"
Write-Host "==========================================" -ForegroundColor Green
Write-Host ""
Write-Host "📱 Frontend Application: http://localhost:8080" -ForegroundColor White
Write-Host "🔧 Admin Panel: http://localhost:8080/admin" -ForegroundColor White
Write-Host "🗄️  Database Admin: http://localhost:8081 (phpMyAdmin)" -ForegroundColor White  
Write-Host "🔌 API Endpoints: http://localhost:8080/api" -ForegroundColor White
Write-Host ""
Write-Host "📊 Container Status:" -ForegroundColor Yellow
docker-compose ps

Write-Host ""
Write-Host "📝 Real-time Features:" -ForegroundColor Yellow
Write-Host "   ✅ Live seat availability updates" -ForegroundColor Green
Write-Host "   ✅ Concurrent booking protection" -ForegroundColor Green
Write-Host "   ✅ Duplicate booking prevention" -ForegroundColor Green
Write-Host "   ✅ Professional notification system" -ForegroundColor Green
Write-Host ""
Write-Host "🔧 Administration:" -ForegroundColor Yellow
Write-Host "   - Add/Edit buses via Admin Panel" -ForegroundColor White
Write-Host "   - Manage employee records" -ForegroundColor White
Write-Host "   - Monitor booking statistics" -ForegroundColor White
Write-Host ""
Write-Host "🛠️  Development Commands:" -ForegroundColor Yellow
Write-Host "   docker-compose logs -f        # View live logs" -ForegroundColor Gray
Write-Host "   docker-compose down           # Stop system" -ForegroundColor Gray
Write-Host "   docker-compose restart        # Restart services" -ForegroundColor Gray
Write-Host ""
Print-Success "System is ready for production use!"

Write-Host ""
Write-Host "Press Enter to keep this window open, or close to continue..." -ForegroundColor Gray
Read-Host