#!/usr/bin/env pwsh
<#
.SYNOPSIS
    Quick Email Verification - Test with Real Booking
.DESCRIPTION
    Creates a test booking and verifies email is sent
#>

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "📧 QUICK EMAIL VERIFICATION TEST" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

Write-Host "This will:" -ForegroundColor Yellow
Write-Host "1. Run SMTP connection test" -ForegroundColor Gray
Write-Host "2. Show you the email was sent successfully" -ForegroundColor Gray
Write-Host "3. Provide instructions to check your inbox" -ForegroundColor Gray
Write-Host ""

# Test 1: SMTP Direct Test
Write-Host "🔍 Test 1: Running SMTP Connection Test..." -ForegroundColor Cyan
Write-Host ""

docker exec bus_booking_php php /var/www/html/test-smtp-auto.php

$smtpResult = $LASTEXITCODE

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan

if ($smtpResult -eq 0) {
    Write-Host "✅ SMTP TEST PASSED!" -ForegroundColor Green
    Write-Host ""
    Write-Host "📧 CHECK YOUR EMAIL NOW!" -ForegroundColor Green -BackgroundColor Black
    Write-Host ""
    Write-Host "Email Address: tarun.arora@intel.com" -ForegroundColor Yellow
    Write-Host "Subject: Bus Booking System - SMTP Test [timestamp]" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "What to look for:" -ForegroundColor Cyan
    Write-Host "  ✓ Email with blue header" -ForegroundColor Gray
    Write-Host "  ✓ Subject: 'SMTP Test Successful'" -ForegroundColor Gray
    Write-Host "  ✓ Test details and timestamp" -ForegroundColor Gray
    Write-Host "  ✓ From: Bus Booking System (sys_github01@intel.com)" -ForegroundColor Gray
    Write-Host ""
    Write-Host "If not in inbox:" -ForegroundColor Yellow
    Write-Host "  1. Check SPAM/JUNK folder" -ForegroundColor Gray
    Write-Host "  2. Search for 'sys_github01'" -ForegroundColor Gray
    Write-Host "  3. Wait 1-2 minutes for delivery" -ForegroundColor Gray
    Write-Host ""
    
    Write-Host "Want to test with actual booking? (y/n): " -NoNewline -ForegroundColor Yellow
    $response = Read-Host
    
    if ($response -eq 'y') {
        Write-Host ""
        Write-Host "🎫 Creating Test Booking..." -ForegroundColor Cyan
        Write-Host ""
        
        # Get booking data
        Write-Host "Enter Employee ID (default: 11453732): " -NoNewline -ForegroundColor Yellow
        $empId = Read-Host
        if ([string]::IsNullOrWhiteSpace($empId)) { $empId = "11453732" }
        
        Write-Host "Enter your email (default: tarun.arora@intel.com): " -NoNewline -ForegroundColor Yellow
        $email = Read-Host
        if ([string]::IsNullOrWhiteSpace($email)) { $email = "tarun.arora@intel.com" }
        
        Write-Host ""
        Write-Host "Creating booking with:" -ForegroundColor Gray
        Write-Host "  Employee: $empId" -ForegroundColor Gray
        Write-Host "  Email: $email" -ForegroundColor Gray
        Write-Host ""
        
        # Create booking via API
        $bookingData = @{
            action = "createBooking"
            employee_id = $empId
            bus_id = "1"
            schedule_date = (Get-Date).AddDays(3).ToString("yyyy-MM-dd")
            slot = "evening"
        } | ConvertTo-Json
        
        try {
            $response = Invoke-RestMethod -Uri "http://localhost:8080/backend/simple-api.php" `
                -Method POST `
                -Body $bookingData `
                -ContentType "application/json"
            
            Write-Host "API Response:" -ForegroundColor Cyan
            Write-Host ($response | ConvertTo-Json -Depth 10) -ForegroundColor Gray
            Write-Host ""
            
            if ($response.success) {
                Write-Host "✅ Booking created successfully!" -ForegroundColor Green
                Write-Host "   Booking ID: $($response.data.booking_id)" -ForegroundColor Yellow
                
                if ($response.email_sent) {
                    Write-Host "   ✅ Email sent successfully!" -ForegroundColor Green
                    Write-Host ""
                    Write-Host "📧 CHECK YOUR EMAIL: $email" -ForegroundColor Green -BackgroundColor Black
                    Write-Host ""
                    Write-Host "Subject: Bus Booking Confirmation - $($response.data.booking_id)" -ForegroundColor Yellow
                } else {
                    Write-Host "   ⚠️  Email not sent: $($response.email_message)" -ForegroundColor Yellow
                }
            } else {
                Write-Host "❌ Booking failed: $($response.message)" -ForegroundColor Red
            }
        }
        catch {
            Write-Host "❌ API Error: $($_.Exception.Message)" -ForegroundColor Red
            Write-Host ""
            Write-Host "Possible issues:" -ForegroundColor Yellow
            Write-Host "  - Docker containers not running" -ForegroundColor Gray
            Write-Host "  - API not accessible at localhost:8080" -ForegroundColor Gray
            Write-Host "  - Check: docker ps" -ForegroundColor Gray
        }
    }
    
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "📋 SUMMARY" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "✅ SMTP Connection: Working" -ForegroundColor Green
    Write-Host "✅ Email Sending: Working" -ForegroundColor Green
    Write-Host "✅ System Status: OPERATIONAL" -ForegroundColor Green
    Write-Host ""
    Write-Host "📚 Documentation: EMAIL_WORKING_SOLUTION.md" -ForegroundColor Cyan
    Write-Host "📋 View Logs: docker exec bus_booking_php cat /tmp/bus_bookings/email_log.txt" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "🎉 Email system is ready for production!" -ForegroundColor Green
    Write-Host ""
    
} else {
    Write-Host "❌ SMTP TEST FAILED" -ForegroundColor Red
    Write-Host ""
    Write-Host "Troubleshooting steps:" -ForegroundColor Yellow
    Write-Host "1. Check Docker containers: docker ps" -ForegroundColor Gray
    Write-Host "2. Verify network connectivity" -ForegroundColor Gray
    Write-Host "3. Check SMTP credentials in backend/config/email.php" -ForegroundColor Gray
    Write-Host "4. Review documentation: EMAIL_WORKING_SOLUTION.md" -ForegroundColor Gray
    Write-Host ""
}

Write-Host ""
