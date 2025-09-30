# Quick Start Guide

## 🚀 Get Started in 3 Steps

### Step 1: Prerequisites
Make sure you have Docker installed:
- **Windows/Mac**: [Docker Desktop](https://www.docker.com/products/docker-desktop)
- **Linux**: Docker Engine + Docker Compose

### Step 2: Configure Email (Optional)
Edit the `.env` file and update the email settings if you want email notifications:
```env
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your-email@gmail.com
SMTP_PASS=your-app-password
SMTP_FROM=your-email@gmail.com
```

### Step 3: Start the System

#### Option A: Use the Start Script (Recommended)
```powershell
# Windows PowerShell
.\start.ps1

# Windows Command Prompt
start.bat
```

#### Option B: Manual Start
```bash
docker-compose up -d
```

## 🌐 Access the Application

Once started, open your browser and go to:
- **Main Application**: http://localhost:8080
- **Database Admin**: http://localhost:8081

## 🧪 Test the System

1. Open http://localhost:8080
2. Enter a test Worker ID: `1234567`
3. Click "Check Status" to see available buses
4. Try booking a morning or evening slot

## 📊 Sample Data

The system comes with sample data:
- **Morning Bus**: Departure 8:00 AM, Route: Office to Metro Station
- **Evening Bus**: Departure 6:00 PM, Route: Metro Station to Office
- **Test Worker IDs**: 1234567, 2345678, 3456789

## 🛑 Stop the System

```bash
docker-compose down
```

## 🔧 Troubleshooting

### Common Issues:

**Port already in use:**
```bash
# Check what's using the port
netstat -ano | findstr :8080
# Kill the process or change ports in docker-compose.yml
```

**Docker not running:**
- Make sure Docker Desktop is started
- Check Docker status: `docker version`

**Services not starting:**
```bash
# Check logs
docker-compose logs

# Check individual service
docker-compose logs nginx
docker-compose logs mysql
```

## 📱 Using the Application

### For Employees:
1. Enter your 7-10 digit Worker ID
2. View available bus schedules
3. Book or cancel your slot
4. Receive email confirmations (if configured)

### For Admins:
- Access phpMyAdmin at http://localhost:8081
- Default login: `booking_user` / `secure_password_123`
- View bookings, manage schedules, check statistics

## 🎯 What's Next?

- **Customize**: Update bus schedules, capacity, and routes in the database
- **Email Setup**: Configure SMTP for email notifications
- **Security**: Change default passwords in `.env` file
- **Production**: See `DEPLOYMENT.md` for production deployment guide

---

**Need Help?** Check the full `README.md` for detailed documentation.