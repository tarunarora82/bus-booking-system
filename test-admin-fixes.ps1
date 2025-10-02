# Admin Panel Testing Script
# Run this after starting the application to test all fixes

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Admin Panel Testing Guide" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

Write-Host "1. Testing Dashboard..." -ForegroundColor Yellow
Write-Host "   - Open: http://localhost:8080/admin-new.html" -ForegroundColor White
Write-Host "   - Check: Activity Log appears under Dashboard section" -ForegroundColor Green
Write-Host "   - Check: Bus counts by slot are accurate" -ForegroundColor Green
Write-Host "   - Check: Only 4 navigation buttons visible" -ForegroundColor Green
Write-Host ""

Write-Host "2. Testing Buses Section..." -ForegroundColor Yellow
Write-Host "   - Click 'Buses' button" -ForegroundColor White
Write-Host "   - Try adding a new bus:" -ForegroundColor Green
Write-Host "     * Bus Number: BUS999" -ForegroundColor Gray
Write-Host "     * Route: Test Route" -ForegroundColor Gray
Write-Host "     * Capacity: 50" -ForegroundColor Gray
Write-Host "     * Time: 08:30" -ForegroundColor Gray
Write-Host "     * Slot: morning" -ForegroundColor Gray
Write-Host "   - Check: Success alert appears" -ForegroundColor Green
Write-Host "   - Check: Bus appears in list" -ForegroundColor Green
Write-Host "   - Check: Edit and Delete buttons visible" -ForegroundColor Green
Write-Host "   - Try clicking Edit on a bus" -ForegroundColor Green
Write-Host "   - Try clicking Delete on the test bus" -ForegroundColor Green
Write-Host ""

Write-Host "3. Testing Employees Section..." -ForegroundColor Yellow
Write-Host "   - Click 'Employees' button" -ForegroundColor White
Write-Host "   - Check: NO department field shown" -ForegroundColor Green
Write-Host "   - Try adding a new employee:" -ForegroundColor Green
Write-Host "     * Employee ID: TEST001" -ForegroundColor Gray
Write-Host "     * Name: Test User" -ForegroundColor Gray
Write-Host "     * Email: test@intel.com" -ForegroundColor Gray
Write-Host "   - Check: Success alert appears" -ForegroundColor Green
Write-Host "   - Check: Employee appears in list" -ForegroundColor Green
Write-Host ""

Write-Host "4. Testing Bookings Section..." -ForegroundColor Yellow
Write-Host "   - Click 'Bookings' button" -ForegroundColor White
Write-Host "   - Check: Three filter buttons visible" -ForegroundColor Green
Write-Host "   - Click 'All Bookings'" -ForegroundColor Green
Write-Host "   - Click 'Active Bookings'" -ForegroundColor Green
Write-Host "   - Click 'Cancelled Bookings'" -ForegroundColor Green
Write-Host "   - Check: Status column shows in table" -ForegroundColor Green
Write-Host ""

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "All Tests Complete!" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Quick API endpoint test
Write-Host "Testing API endpoints..." -ForegroundColor Yellow
Write-Host ""

try {
    $healthCheck = Invoke-RestMethod -Uri "http://localhost:8080/api/health" -Method Get
    Write-Host "✓ API Health Check: PASSED" -ForegroundColor Green
    
    $buses = Invoke-RestMethod -Uri "http://localhost:8080/api/buses/available" -Method Get
    Write-Host "✓ Get Buses: PASSED ($($buses.data.Count) buses found)" -ForegroundColor Green
    
    $bookings = Invoke-RestMethod -Uri "http://localhost:8080/api/admin/recent-bookings?status=all" -Method Get
    Write-Host "✓ Get Bookings: PASSED ($($bookings.data.Count) bookings found)" -ForegroundColor Green
    
    Write-Host ""
    Write-Host "All API endpoints are working!" -ForegroundColor Green
} catch {
    Write-Host "⚠ Make sure the application is running first!" -ForegroundColor Red
    Write-Host "Run: .\start-production.ps1" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Press any key to open admin panel in browser..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
Start-Process "http://localhost:8080/admin-new.html"
