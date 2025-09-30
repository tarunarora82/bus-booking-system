# Bus Slot Booking System - Quick Start Script
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Bus Slot Booking System - Quick Start" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check Docker installation
Write-Host "Checking Docker installation..." -ForegroundColor Yellow

# Check Docker
$dockerInstalled = $false
try {
    $dockerVersion = & docker --version 2>$null
    if ($dockerVersion) {
        Write-Host "✓ Docker is installed: $dockerVersion" -ForegroundColor Green
        $dockerInstalled = $true
    }
} catch {
    Write-Host "✗ ERROR: Docker is not installed or not in PATH" -ForegroundColor Red
}

if (-not $dockerInstalled) {
    Write-Host "Please install Docker Desktop from https://www.docker.com/products/docker-desktop" -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit 1
}

# Check Docker Compose (try both docker-compose and docker compose)
$composeInstalled = $false
try {
    $composeVersion = & docker-compose --version 2>$null
    if ($composeVersion) {
        Write-Host "✓ Docker Compose is installed: $composeVersion" -ForegroundColor Green
        $composeInstalled = $true
        $composeCommand = "docker-compose"
    }
} catch {
    # Try docker compose (newer syntax)
    try {
        $composeVersion = & docker compose version 2>$null
        if ($composeVersion) {
            Write-Host "✓ Docker Compose is installed: $composeVersion" -ForegroundColor Green
            $composeInstalled = $true
            $composeCommand = "docker compose"
        }
    } catch {
        Write-Host "✗ ERROR: Docker Compose is not available" -ForegroundColor Red
    }
}

if (-not $composeInstalled) {
    Write-Host "Please install Docker Compose or update Docker Desktop" -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit 1
}

Write-Host ""

# Check .env file
Write-Host "Checking configuration..." -ForegroundColor Yellow
if (Test-Path ".env") {
    Write-Host "✓ .env file found!" -ForegroundColor Green
} else {
    Write-Host "✗ ERROR: .env file not found" -ForegroundColor Red
    Write-Host "The .env file should have been created. Please check if it exists." -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit 1
}

Write-Host ""

# Start the system
Write-Host "Starting Bus Booking System..." -ForegroundColor Yellow
Write-Host "This may take a few minutes on first run (downloading Docker images)..." -ForegroundColor Yellow
Write-Host ""

try {
    if ($composeCommand -eq "docker-compose") {
        & docker-compose up -d
    } else {
        & docker compose up -d
    }
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host ""
        Write-Host "========================================" -ForegroundColor Green
        Write-Host "SUCCESS! Bus Booking System is running" -ForegroundColor Green
        Write-Host "========================================" -ForegroundColor Green
        Write-Host ""
        Write-Host "Application URLs:" -ForegroundColor Cyan
        Write-Host "• Main Application: http://localhost:8080" -ForegroundColor White
        Write-Host "• Database Admin:   http://localhost:8081" -ForegroundColor White
        Write-Host "• API Endpoints:    http://localhost:8080/api/" -ForegroundColor White
        Write-Host ""
        Write-Host "Default phpMyAdmin Login:" -ForegroundColor Cyan
        Write-Host "• Server: mysql" -ForegroundColor White
        Write-Host "• Username: booking_user" -ForegroundColor White
        Write-Host "• Password: secure_password_123" -ForegroundColor White
        Write-Host ""
        Write-Host "Useful Commands:" -ForegroundColor Cyan
        Write-Host "• Stop system:  $composeCommand down" -ForegroundColor White
        Write-Host "• View logs:    $composeCommand logs -f" -ForegroundColor White
        Write-Host "• Check status: $composeCommand ps" -ForegroundColor White
        Write-Host ""
        
        # Wait a moment for services to fully start
        Write-Host "Waiting for services to start..." -ForegroundColor Yellow
        Start-Sleep -Seconds 10
        
        # Check if services are running
        Write-Host "Checking service status..." -ForegroundColor Yellow
        if ($composeCommand -eq "docker-compose") {
            & docker-compose ps
        } else {
            & docker compose ps
        }
        
        Write-Host ""
        $openBrowser = Read-Host "Would you like to open the application in your browser? (Y/n)"
        if ($openBrowser -ne "n" -and $openBrowser -ne "N") {
            Start-Process "http://localhost:8080"
        }
    } else {
        throw "Docker compose command failed with exit code $LASTEXITCODE"
    }
    
} catch {
    Write-Host ""
    Write-Host "✗ ERROR: Failed to start the system" -ForegroundColor Red
    Write-Host "Error details: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host ""
    Write-Host "Troubleshooting tips:" -ForegroundColor Yellow
    Write-Host "1. Make sure Docker Desktop is running" -ForegroundColor White
    Write-Host "2. Check if ports 8080 and 8081 are available" -ForegroundColor White
    Write-Host "3. Run '$composeCommand logs' to see detailed error messages" -ForegroundColor White
    Write-Host ""
    Read-Host "Press Enter to exit"
}