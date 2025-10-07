# Test Email Notification System
# PowerShell script to test the email functionality

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "📧 EMAIL NOTIFICATION SYSTEM TEST" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check if PHP is available
$phpPath = Get-Command php -ErrorAction SilentlyContinue

if (-not $phpPath) {
    Write-Host "❌ PHP not found in PATH" -ForegroundColor Red
    Write-Host "Please ensure PHP is installed and added to PATH" -ForegroundColor Yellow
    exit 1
}

Write-Host "✓ PHP found: $($phpPath.Path)" -ForegroundColor Green
Write-Host ""

# Run the standalone test script
Write-Host "Running standalone email test..." -ForegroundColor Yellow
Write-Host ""

php test-email-system.php

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "📋 INTERACTIVE TESTS AVAILABLE" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "For interactive testing, open:" -ForegroundColor Yellow
Write-Host "  test-email-notifications.html" -ForegroundColor Cyan
Write-Host ""
Write-Host "Make sure the backend is running:" -ForegroundColor Yellow
Write-Host "  cd backend" -ForegroundColor Cyan
Write-Host "  php -S localhost:3000" -ForegroundColor Cyan
Write-Host ""

# Offer to open the test HTML file
$response = Read-Host "Do you want to open the interactive test page? (Y/N)"
if ($response -eq "Y" -or $response -eq "y") {
    Start-Process "test-email-notifications.html"
}

Write-Host ""
Write-Host "✅ Test script completed!" -ForegroundColor Green
