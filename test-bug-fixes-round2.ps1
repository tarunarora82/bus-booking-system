# Bug Fixes Round 2 - Testing Script
# Test all the new bug fixes and features

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Bug Fixes Round 2 - Testing" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

Write-Host "🐛 BUGS FIXED:" -ForegroundColor Green
Write-Host ""
Write-Host "1. ✅ Time Display Bug (AM AM / PM AM)" -ForegroundColor Yellow
Write-Host "   - No more double AM/PM suffixes" -ForegroundColor White
Write-Host "   - Handles already-formatted times" -ForegroundColor Gray
Write-Host ""

Write-Host "2. ✅ Null/Undefined Employee Deletion" -ForegroundColor Yellow
Write-Host "   - Validates employee ID before deletion" -ForegroundColor White
Write-Host "   - Shows clear error for invalid IDs" -ForegroundColor Gray
Write-Host ""

Write-Host "3. ✅ CSV Upload - Immediate Feedback" -ForegroundColor Yellow
Write-Host "   - Modal appears after file selection" -ForegroundColor White
Write-Host "   - Upload Now or Upload Later options" -ForegroundColor Gray
Write-Host ""

Write-Host "4. ✅ Multi-Select Bus Deletion" -ForegroundColor Yellow
Write-Host "   - Checkbox for each bus" -ForegroundColor White
Write-Host "   - Select All checkbox in header" -ForegroundColor White
Write-Host "   - Delete Selected button" -ForegroundColor White
Write-Host "   - Batch processing with summary" -ForegroundColor Gray
Write-Host ""

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "TESTING INSTRUCTIONS" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

Write-Host "TEST 1: Time Display Fix" -ForegroundColor Yellow
Write-Host "───────────────────────────────────────" -ForegroundColor Gray
Write-Host "1. Go to Buses → Bus List" -ForegroundColor White
Write-Host "2. Check departure times for all buses" -ForegroundColor White
Write-Host "3. ✓ Verify NO double AM/PM (e.g. NO '8:30 AM AM')" -ForegroundColor Green
Write-Host "4. ✓ All times show single AM or PM" -ForegroundColor Green
Write-Host "5. Refresh the page" -ForegroundColor White
Write-Host "6. ✓ Times still display correctly" -ForegroundColor Green
Write-Host ""

Write-Host "TEST 2: Employee Deletion Validation" -ForegroundColor Yellow
Write-Host "───────────────────────────────────────" -ForegroundColor Gray
Write-Host "1. Go to Employees → Employee List" -ForegroundColor White
Write-Host "2. Look for any employee with 'null' or 'undefined' ID" -ForegroundColor White
Write-Host "3. Click Delete button for that employee" -ForegroundColor White
Write-Host "4. ✓ Modal appears: 'Cannot delete employee with invalid ID'" -ForegroundColor Green
Write-Host "5. ✓ No crash or error" -ForegroundColor Green
Write-Host "6. Find valid employee and delete successfully" -ForegroundColor White
Write-Host ""

Write-Host "TEST 3: CSV Upload - Immediate Modal" -ForegroundColor Yellow
Write-Host "───────────────────────────────────────" -ForegroundColor Gray
Write-Host "1. Go to Buses → Bulk Upload tab" -ForegroundColor White
Write-Host "2. ✓ Notice hint: '💡 Upload will start immediately...'" -ForegroundColor Green
Write-Host "3. Click 'Select CSV File'" -ForegroundColor White
Write-Host "4. Choose any CSV file" -ForegroundColor White
Write-Host "5. ✓ Modal appears IMMEDIATELY" -ForegroundColor Green
Write-Host "6. ✓ Modal shows: 'File Selected' with filename" -ForegroundColor Green
Write-Host "7. ✓ Two buttons visible: 'Upload Later' and 'Upload Now'" -ForegroundColor Green
Write-Host "8. Click 'Upload Later' → Modal closes" -ForegroundColor White
Write-Host "9. Repeat and click 'Upload Now' → Upload starts" -ForegroundColor White
Write-Host ""

Write-Host "TEST 4: Multi-Select Bus Deletion" -ForegroundColor Yellow
Write-Host "───────────────────────────────────────" -ForegroundColor Gray
Write-Host "Step 1: Visual Check" -ForegroundColor Cyan
Write-Host "  1. Go to Buses → Bus List" -ForegroundColor White
Write-Host "  2. ✓ See checkbox column (first column)" -ForegroundColor Green
Write-Host "  3. ✓ Header has checkbox" -ForegroundColor Green
Write-Host "  4. ✓ Each row has checkbox" -ForegroundColor Green
Write-Host "  5. ✓ 'Delete Selected' button visible" -ForegroundColor Green
Write-Host ""

Write-Host "Step 2: Select Individual Buses" -ForegroundColor Cyan
Write-Host "  1. Click checkbox for BUS001" -ForegroundColor White
Write-Host "  2. Click checkbox for BUS002" -ForegroundColor White
Write-Host "  3. ✓ Checkboxes are checked (blue)" -ForegroundColor Green
Write-Host "  4. Click 'Delete Selected' button" -ForegroundColor White
Write-Host "  5. ✓ Confirmation modal appears" -ForegroundColor Green
Write-Host "  6. ✓ Shows count: 'delete 2 selected buses?'" -ForegroundColor Green
Write-Host "  7. Click Cancel → Nothing happens" -ForegroundColor White
Write-Host ""

Write-Host "Step 3: Select All Buses" -ForegroundColor Cyan
Write-Host "  1. Click header checkbox (☑)" -ForegroundColor White
Write-Host "  2. ✓ All bus checkboxes get selected" -ForegroundColor Green
Write-Host "  3. Click header checkbox again" -ForegroundColor White
Write-Host "  4. ✓ All bus checkboxes get deselected" -ForegroundColor Green
Write-Host ""

Write-Host "Step 4: Delete Multiple Buses" -ForegroundColor Cyan
Write-Host "  1. Select 2-3 buses" -ForegroundColor White
Write-Host "  2. Click 'Delete Selected'" -ForegroundColor White
Write-Host "  3. Click 'Confirm' in modal" -ForegroundColor White
Write-Host "  4. ✓ Activity log shows progress:" -ForegroundColor Green
Write-Host "     '🔄 Deleting X selected buses...'" -ForegroundColor Gray
Write-Host "     '✅ Bus deleted: BUS001'" -ForegroundColor Gray
Write-Host "     '✅ Bus deleted: BUS002'" -ForegroundColor Gray
Write-Host "  5. ✓ Summary modal appears" -ForegroundColor Green
Write-Host "  6. ✓ Shows: 'Successfully deleted X buses'" -ForegroundColor Green
Write-Host "  7. Click OK" -ForegroundColor White
Write-Host "  8. ✓ Bus list refreshes without deleted buses" -ForegroundColor Green
Write-Host ""

Write-Host "Step 5: Test with Active Bookings" -ForegroundColor Cyan
Write-Host "  1. Create a booking for a bus (use user view)" -ForegroundColor White
Write-Host "  2. Return to admin panel" -ForegroundColor White
Write-Host "  3. Select that bus + another bus without bookings" -ForegroundColor White
Write-Host "  4. Click 'Delete Selected'" -ForegroundColor White
Write-Host "  5. ✓ Activity log shows mixed results:" -ForegroundColor Green
Write-Host "     '✅ Bus deleted: BUS002'" -ForegroundColor Gray
Write-Host "     '❌ Failed: BUS001 (has active bookings)'" -ForegroundColor Gray
Write-Host "  6. ✓ Summary modal shows:" -ForegroundColor Green
Write-Host "     'Successfully deleted: 1'" -ForegroundColor Gray
Write-Host "     'Failed: 1'" -ForegroundColor Gray
Write-Host "     'Errors: BUS001: Cannot delete...'" -ForegroundColor Gray
Write-Host ""

Write-Host "Step 6: Test No Selection" -ForegroundColor Cyan
Write-Host "  1. Ensure no buses are selected" -ForegroundColor White
Write-Host "  2. Click 'Delete Selected'" -ForegroundColor White
Write-Host "  3. ✓ Error modal appears:" -ForegroundColor Green
Write-Host "     'Please select at least one bus to delete'" -ForegroundColor Gray
Write-Host ""

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "EMPLOYEE CSV UPLOAD TEST" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "1. Go to Employees → Bulk Upload" -ForegroundColor White
Write-Host "2. Click 'Select CSV File'" -ForegroundColor White
Write-Host "3. Choose employee CSV file" -ForegroundColor White
Write-Host "4. ✓ Modal appears immediately" -ForegroundColor Green
Write-Host "5. Click 'Upload Now'" -ForegroundColor White
Write-Host "6. ✓ See progress in activity log" -ForegroundColor Green
Write-Host "7. ✓ Summary modal with results" -ForegroundColor Green
Write-Host ""

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "EDGE CASE TESTS" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

Write-Host "Test A: Empty CSV File" -ForegroundColor Yellow
Write-Host "  1. Create empty CSV file" -ForegroundColor White
Write-Host "  2. Try to upload" -ForegroundColor White
Write-Host "  3. ✓ Error: 'CSV file is empty or invalid'" -ForegroundColor Green
Write-Host ""

Write-Host "Test B: CSV with Invalid Data" -ForegroundColor Yellow
Write-Host "  1. Create CSV with missing fields" -ForegroundColor White
Write-Host "  2. Upload file" -ForegroundColor White
Write-Host "  3. ✓ Summary shows failed count" -ForegroundColor Green
Write-Host ""

Write-Host "Test C: Delete All Buses" -ForegroundColor Yellow
Write-Host "  1. Select all buses using header checkbox" -ForegroundColor White
Write-Host "  2. Delete all" -ForegroundColor White
Write-Host "  3. ✓ Processes all deletions" -ForegroundColor Green
Write-Host "  4. ✓ Some may fail due to bookings" -ForegroundColor Green
Write-Host ""

Write-Host "========================================" -ForegroundColor Green
Write-Host "All Tests Ready!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""

# Check if application is running
try {
    $response = Invoke-WebRequest -Uri "http://localhost:8080/api/health" -Method Get -TimeoutSec 2 -ErrorAction Stop
    Write-Host "✅ Application is running!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Opening admin panel..." -ForegroundColor Cyan
    Start-Sleep -Seconds 1
    Start-Process "http://localhost:8080/admin-new.html"
} catch {
    Write-Host "⚠ Application not running!" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Please start the application first:" -ForegroundColor Yellow
    Write-Host ".\start-production.ps1" -ForegroundColor White
    Write-Host ""
}

Write-Host ""
Write-Host "📋 Follow the test instructions above" -ForegroundColor Cyan
Write-Host "📝 Report any issues found" -ForegroundColor Cyan
Write-Host ""
