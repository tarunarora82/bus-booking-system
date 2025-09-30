# Manual Start Guide

## Docker Compose Issue Fix

If you're getting "docker-compose not recognized", try these solutions:

### Option 1: Use Docker Compose V2 syntax
```powershell
docker compose up -d
```

### Option 2: Install Docker Compose standalone
```powershell
# Download Docker Compose
Invoke-WebRequest "https://github.com/docker/compose/releases/download/v2.21.0/docker-compose-windows-x86_64.exe" -UseBasicParsing -OutFile docker-compose.exe

# Move to a directory in your PATH
Move-Item docker-compose.exe $env:ProgramFiles\Docker\Docker\resources\bin\
```

### Option 3: Use Docker Desktop
Make sure Docker Desktop is running and try:
```powershell
# Check if Docker Desktop is running
docker version

# Use the integrated compose
docker compose up -d
```

## Manual Steps to Start the System

1. **Make sure Docker Desktop is running**
   - Open Docker Desktop application
   - Wait for it to start completely

2. **Navigate to project directory**
   ```powershell
   cd "c:\Users\tarora\bus slot booking system\bus-booking-system"
   ```

3. **Start the system** (try these in order):
   ```powershell
   # Try option 1
   docker compose up -d
   
   # If that doesn't work, try option 2
   docker-compose up -d
   
   # If still doesn't work, try with full path
   & "C:\Program Files\Docker\Docker\resources\bin\docker-compose.exe" up -d
   ```

4. **Wait for services to start** (30-60 seconds)

5. **Check if running**:
   ```powershell
   docker compose ps
   # or
   docker-compose ps
   ```

6. **Open the application**:
   - Main App: http://localhost:8080
   - Database Admin: http://localhost:8081

## Alternative: Use Docker Desktop GUI

1. Open Docker Desktop
2. Go to "Compose" tab
3. Click "Create" and select your `docker-compose.yml` file
4. Click "Run"

## Stop the System

```powershell
docker compose down
# or
docker-compose down
```

## Troubleshooting

### If ports are busy:
```powershell
# Check what's using port 8080
netstat -ano | findstr :8080

# Kill the process (replace PID with actual process ID)
taskkill /PID <PID> /F
```

### If Docker images fail to download:
```powershell
# Pull images manually
docker pull nginx:alpine
docker pull mysql:8.0
docker pull redis:alpine
docker pull phpmyadmin/phpmyadmin
```

### Check Docker Desktop status:
```powershell
docker version
docker info
```