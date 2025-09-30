@echo off
echo ========================================
echo Bus Slot Booking System - Quick Start
echo ========================================
echo.

echo Checking Docker installation...
docker --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: Docker is not installed or not in PATH
    echo Please install Docker Desktop from https://www.docker.com/products/docker-desktop
    pause
    exit /b 1
)

docker-compose --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: Docker Compose is not installed or not in PATH
    echo Please install Docker Compose
    pause
    exit /b 1
)

echo Docker is installed successfully!
echo.

echo Checking if .env file exists...
if not exist ".env" (
    echo ERROR: .env file not found
    echo Please create the .env file with your configuration
    pause
    exit /b 1
)

echo .env file found!
echo.

echo Starting Bus Booking System...
echo This may take a few minutes on first run...
echo.

docker-compose up -d

if %errorlevel% equ 0 (
    echo.
    echo ========================================
    echo SUCCESS! Bus Booking System is running
    echo ========================================
    echo.
    echo Application URLs:
    echo - Main Application: http://localhost:8080
    echo - Database Admin:   http://localhost:8081
    echo - API Endpoints:    http://localhost:8080/api/
    echo.
    echo Default phpMyAdmin Login:
    echo - Server: mysql
    echo - Username: booking_user
    echo - Password: secure_password_123
    echo.
    echo To stop the system: docker-compose down
    echo To view logs: docker-compose logs -f
    echo.
    echo Press any key to open the application in your browser...
    pause >nul
    start http://localhost:8080
) else (
    echo.
    echo ERROR: Failed to start the system
    echo Please check the error messages above
    echo.
    pause
)