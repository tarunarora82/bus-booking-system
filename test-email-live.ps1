#!/usr/bin/env pwsh
<#
.SYNOPSIS
    Live Email System Test Runner
.DESCRIPTION
    Runs comprehensive email system test with detailed diagnostics
#>

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "📧 EMAIL SYSTEM LIVE TEST RUNNER" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Find PHP executable
Write-Host "🔍 Locating PHP..." -ForegroundColor Yellow
$phpPaths = @(
    "php",
    "C:\php\php.exe",
    "C:\xampp\php\php.exe",
    "C:\wamp64\bin\php\php7.4.9\php.exe"
)

$phpExe = $null
foreach ($path in $phpPaths) {
    try {
        $version = & $path --version 2>$null
        if ($LASTEXITCODE -eq 0) {
            $phpExe = $path
            Write-Host "  ✓ Found PHP: $path" -ForegroundColor Green
            Write-Host "  Version: $($version.Split("`n")[0])" -ForegroundColor Gray
            break
        }
    }
    catch {
        continue
    }
}

if (-not $phpExe) {
    Write-Host "  ❌ PHP not found!" -ForegroundColor Red
    Write-Host ""
    Write-Host "Please install PHP or update the path in this script" -ForegroundColor Yellow
    exit 1
}
Write-Host ""

# Check if Docker is running (for production environment)
Write-Host "🔍 Checking Docker environment..." -ForegroundColor Yellow
try {
    $dockerPS = docker ps 2>&1
    if ($LASTEXITCODE -eq 0) {
        Write-Host "  ✓ Docker is running" -ForegroundColor Green
        
        # Check if our containers are running
        $containers = docker ps --format "{{.Names}}" 2>$null
        if ($containers -match "bus-booking") {
            Write-Host "  ✓ Bus booking containers are running" -ForegroundColor Green
            Write-Host ""
            Write-Host "💡 TIP: This test can also be run inside Docker container:" -ForegroundColor Cyan
            Write-Host "   docker exec -it bus-booking-php php /var/www/html/test-email-live.php" -ForegroundColor Gray
        }
    }
}
catch {
    Write-Host "  ⚠️  Docker not running (testing locally)" -ForegroundColor Yellow
}
Write-Host ""

# Run the test
Write-Host "🚀 Running email system test..." -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

$testFile = Join-Path $PSScriptRoot "test-email-live.php"

if (-not (Test-Path $testFile)) {
    Write-Host "❌ Test file not found: $testFile" -ForegroundColor Red
    exit 1
}

# Run the PHP test script
& $phpExe $testFile

$exitCode = $LASTEXITCODE

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan

if ($exitCode -eq 0) {
    Write-Host "✅ Test completed successfully" -ForegroundColor Green
} else {
    Write-Host "❌ Test completed with errors" -ForegroundColor Red
}

Write-Host ""
Write-Host "📝 Additional Resources:" -ForegroundColor Cyan
Write-Host "  - Documentation: EMAIL_SYSTEM_DOCUMENTATION.md" -ForegroundColor Gray
Write-Host "  - Quick Start: EMAIL_QUICKSTART.md" -ForegroundColor Gray
Write-Host "  - Check logs: /tmp/bus_bookings/email_log.txt" -ForegroundColor Gray
Write-Host ""

# Offer to view log
Write-Host "Would you like to view the email log? (y/n): " -NoNewline -ForegroundColor Yellow
$response = Read-Host

if ($response -eq 'y') {
    $logPath = "/tmp/bus_bookings/email_log.txt"
    if (Test-Path $logPath) {
        Write-Host ""
        Write-Host "📋 Email Log:" -ForegroundColor Cyan
        Write-Host "========================================" -ForegroundColor Cyan
        Get-Content $logPath -Tail 20
        Write-Host "========================================" -ForegroundColor Cyan
    } else {
        Write-Host "⚠️  Log file not found at: $logPath" -ForegroundColor Yellow
    }
}

Write-Host ""
exit $exitCode
