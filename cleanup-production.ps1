# Production Workspace Cleanup Script
# Date: October 1, 2025
# Purpose: Remove duplicate API files and organize production structure

Write-Host "🧹 Cleaning up production workspace..." -ForegroundColor Yellow

# Create archive directory for old APIs
$archiveDir = "dev-resources\archived-apis"
if (!(Test-Path $archiveDir)) {
    New-Item -ItemType Directory -Path $archiveDir -Force
}

Write-Host "📦 Archiving old API implementations..." -ForegroundColor Blue

# Archive duplicate API files from backend root
if (Test-Path "backend\query-api.php") {
    Move-Item "backend\query-api.php" "$archiveDir\query-api-backend-root.php" -Force
    Write-Host "  ✅ Archived backend\query-api.php"
}

if (Test-Path "backend\simple-api.php") {
    Move-Item "backend\simple-api.php" "$archiveDir\simple-api-backend-root.php" -Force
    Write-Host "  ✅ Archived backend\simple-api.php"
}

if (Test-Path "backend\simple-api-clean.php") {
    Move-Item "backend\simple-api-clean.php" "$archiveDir\simple-api-clean-backend.php" -Force
    Write-Host "  ✅ Archived backend\simple-api-clean.php"
}

# Archive duplicate API files from backend/api (keep only production-api.php)
if (Test-Path "backend\api\query-api.php") {
    Move-Item "backend\api\query-api.php" "$archiveDir\query-api-backend-api.php" -Force
    Write-Host "  ✅ Archived backend\api\query-api.php"
}

if (Test-Path "backend\api\query-api-fixed.php") {
    Move-Item "backend\api\query-api-fixed.php" "$archiveDir\query-api-fixed.php" -Force
    Write-Host "  ✅ Archived backend\api\query-api-fixed.php"
}

if (Test-Path "backend\api\simple-api.php") {
    Move-Item "backend\api\simple-api.php" "$archiveDir\simple-api-backend-api.php" -Force
    Write-Host "  ✅ Archived backend\api\simple-api.php"
}

# Archive old index.php from backend/api if it exists
if (Test-Path "backend\api\index.php") {
    Move-Item "backend\api\index.php" "$archiveDir\index-backend-api.php" -Force
    Write-Host "  ✅ Archived backend\api\index.php"
}

Write-Host "🚀 Production cleanup complete!" -ForegroundColor Green
Write-Host "📁 Active API: backend\api\production-api.php" -ForegroundColor Cyan
Write-Host "📦 Archived APIs moved to: $archiveDir" -ForegroundColor Cyan

# List current production structure
Write-Host "`n📋 Current Production API Structure:" -ForegroundColor Yellow
Get-ChildItem "backend\api\" | Format-Table Name, Length, LastWriteTime -AutoSize

Write-Host "`n📦 Archived Files:" -ForegroundColor Yellow
Get-ChildItem $archiveDir | Format-Table Name, Length, LastWriteTime -AutoSize