# 🚌 Employee Bus Booking System - Production Ready

A real-time, production-ready bus slot booking system designed for enterprise employee transportation management with concurrent booking protection and live seat availability updates.

## 🎯 Key Features

### Real-time Capabilities
- ✅ **Live Seat Availability**: Real-time updates every 5 seconds
- ✅ **Concurrent Booking Protection**: Database-level atomic transactions
- ✅ **Duplicate Prevention**: One booking per employee per day
- ✅ **Professional UI**: Modal dialogs and responsive design

### Business Rules Compliance
- 🚌 **Single Bus Selection**: Only one bus can be selected at a time
- 👤 **Employee-based**: Proper employee terminology throughout
- 📅 **Daily Limit**: One booking allowed per day per employee
- 🔄 **Book/Cancel Options**: Easy booking management
- 📧 **Email Integration**: Configurable SMTP for notifications

### Production Architecture
- 🐳 **Docker Containerized**: Full container orchestration
- 🗄️ **MySQL Database**: Proper relational database with stored procedures
- 🔌 **REST API**: PHP-based backend with comprehensive endpoints
- 🔄 **Real-time Updates**: JavaScript polling with retry logic
- 🔐 **Security**: Input validation, SQL injection protection

## 🚀 Quick Start

### Prerequisites
- Docker Desktop installed and running
- 8GB+ RAM recommended
- Ports 8080, 3307, 8081, 6379 available

### 1. Start Production System

**For Windows (PowerShell):**
```powershell
.\start-production.ps1
```

**For Linux/Mac:**
```bash
chmod +x start-production.sh
./start-production.sh
```

**Manual Docker Start:**
```bash
docker-compose up -d --build
```

### 2. Access the System
- 📱 **Main Application**: http://localhost:8080
- 🔧 **Admin Panel**: http://localhost:8080/admin
- 🗄️ **Database Admin**: http://localhost:8081 (phpMyAdmin)
- 🔌 **API Health**: http://localhost:8080/api/health

## 📊 System Architecture

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Frontend      │    │   PHP API       │    │   MySQL DB      │
│   (JavaScript)  │◄──►│   (REST)        │◄──►│   (Stored Proc) │
│   Real-time UI  │    │   Atomic Ops    │    │   Atomic Trans  │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         │              ┌─────────────────┐              │
         │              │   nginx         │              │
         └─────────────►│   Load Balancer │◄─────────────┘
                        └─────────────────┘
```

## 🗄️ Database Schema

### Core Tables
- **`buses`**: Bus information (number, route, capacity, timing)
- **`employees`**: Employee records (ID, name, email, department)
- **`bookings`**: Booking records with constraints preventing duplicates

### Stored Procedures
- **`CreateBooking`**: Atomic booking creation with row locking
- **`CancelBooking`**: Safe booking cancellation with validation
- **`GetAvailableSeats`**: Real-time seat availability calculation

### Key Features
- Unique booking per employee per day per schedule
- Foreign key relationships ensuring data integrity
- Indexed columns for optimal query performance

## 🔌 API Endpoints

### Real-time Booking Operations
```
GET    /api/buses/available      # Live bus availability
POST   /api/bookings/create      # Create booking (atomic)
DELETE /api/bookings/cancel      # Cancel booking
GET    /api/bookings/employee/{id} # Employee bookings
GET    /api/health              # System health check
```

### Administration
```
GET    /api/buses               # List all buses
POST   /api/buses               # Add new bus
PUT    /api/buses/{id}          # Update bus details
GET    /api/employees           # List employees
POST   /api/employees           # Register employee
```

## ⚙️ Configuration

### Environment Variables (.env)
```bash
# Database Configuration
DB_HOST=mysql
DB_NAME=bus_booking_system
DB_USER=root
DB_PASS=rootpassword
DB_PORT=3306

# API Settings
API_BASE_URL=http://localhost:8080/api
POLLING_INTERVAL=5000
MAX_RETRIES=3

# Email Notifications (configure for production)
SMTP_HOST=your-smtp-server.com
SMTP_PORT=587
SMTP_USER=your-email@company.com
SMTP_PASSWORD=your-password
SMTP_FROM_EMAIL=noreply@company.com
```

## 🔄 Real-time Features

### Live Updates System
The system automatically polls for updates every 5 seconds:
- ✅ Seat availability changes
- ✅ New bookings by other employees
- ✅ Booking cancellations
- ✅ Bus capacity updates

### Concurrent Protection
Database-level protection ensures:
- ✅ No double-booking of seats
- ✅ Atomic transaction processing
- ✅ Row-level locking during booking
- ✅ Consistent data state across users

### Retry Logic
Automatic retry for failed operations:
- ✅ Network connectivity issues
- ✅ Temporary database locks
- ✅ Server response delays
- ✅ Connection timeouts

## 🛠️ Administration

### Admin Panel Features
Access at http://localhost:8080/admin
- 📊 **Dashboard**: Real-time booking statistics
- 🚌 **Bus Management**: Add, edit, remove buses
- 👥 **Employee Management**: Register and manage employees
- 📈 **Reports**: Booking trends and utilization
- ⚙️ **System Settings**: Configure schedules and routes

### Database Management
Access phpMyAdmin at http://localhost:8081
- Username: `root`  
- Password: `rootpassword`
- Database: `bus_booking_system`

## 🔧 Development Commands

```bash
# View live logs from all services
docker-compose logs -f

# View logs from specific service
docker-compose logs -f php
docker-compose logs -f mysql
docker-compose logs -f nginx

# Restart specific service
docker-compose restart php

# Stop entire system
docker-compose down

# Stop and remove all data (fresh start)
docker-compose down -v

# Update and restart
docker-compose pull && docker-compose up -d

# Access container shell
docker-compose exec php bash
docker-compose exec mysql mysql -u root -p
```

## 🚨 Troubleshooting

### Database Issues
**Connection Failed:**
```bash
# Check MySQL container status
docker-compose logs mysql

# Test database connection
docker-compose exec php php -r "
try {
    \$pdo = new PDO('mysql:host=mysql;dbname=bus_booking_system', 'root', 'rootpassword');
    echo 'Database connection: SUCCESS\n';
} catch(PDOException \$e) {
    echo 'Database connection: FAILED - ' . \$e->getMessage() . '\n';
}
"
```

**Schema Not Initialized:**
```bash
# Check if tables exist
docker-compose exec mysql mysql -u root -prootpassword -e "USE bus_booking_system; SHOW TABLES;"

# Manually run schema
docker-compose exec mysql mysql -u root -prootpassword bus_booking_system < backend/database/schema.sql
```

### API Issues
**Not Responding:**
```bash
# Check PHP service
docker-compose logs php

# Test API health endpoint
curl -v http://localhost:8080/api/health

# Check PHP configuration
docker-compose exec php php -m
```

### Frontend Issues
**Not Loading:**
```bash
# Check nginx status
docker-compose logs nginx

# Verify file permissions
docker-compose exec nginx ls -la /var/www/html

# Test direct file access
curl -v http://localhost:8080/index.html
```

### Real-time Updates Not Working
**Polling Issues:**
```bash
# Check browser console for JavaScript errors
# Verify API endpoints are responding:
curl http://localhost:8080/api/buses/available

# Check network connectivity from frontend
docker-compose exec nginx ping php
```

## 📈 Production Deployment

### Prerequisites for Production
- **Server**: Ubuntu 20.04+ or CentOS 8+ 
- **RAM**: 8GB minimum, 16GB recommended
- **Storage**: 50GB+ for logs and database
- **Network**: Static IP, domain name, SSL certificate

### Security Hardening
```bash
# Change default passwords in .env
DB_PASS=your-secure-database-password
DB_ROOT_PASS=your-secure-root-password

# Configure firewall
ufw allow 80
ufw allow 443
ufw allow 22
ufw enable

# Set up SSL certificate
# Update nginx configuration with SSL
```

### Performance Optimization
```bash
# Database tuning
# Edit my.cnf for production MySQL settings

# PHP tuning  
# Configure OPcache in docker/php/php.ini

# Enable Redis caching
# Uncomment Redis sections in docker-compose.yml
```

## ✅ Production Checklist

**System Setup:**
- [x] Docker containers configured and running
- [x] Database schema initialized with stored procedures
- [x] Real-time API service implemented with polling
- [x] Professional UI with modal dialogs
- [x] Concurrent booking protection (atomic transactions)
- [x] Duplicate booking prevention (unique constraints)

**Configuration Required:**
- [ ] Email SMTP settings configured in .env
- [ ] Bus routes and schedules added via admin panel
- [ ] Employee database populated
- [ ] SSL certificate configured for HTTPS
- [ ] Production domain configured
- [ ] Firewall and security settings applied

**Testing & Monitoring:**
- [ ] Load testing with multiple concurrent users
- [ ] Backup procedures tested and verified
- [ ] Monitoring and alerts configured
- [ ] User acceptance testing completed
- [ ] Security audit completed

## 📊 Key Business Rules Implemented

✅ **Single Bus Selection**: Only one bus can be selected at a time  
✅ **Employee-based System**: Uses "Employee" terminology throughout  
✅ **One Booking Per Day**: Database constraint prevents multiple bookings  
✅ **Book/Cancel Options**: Easy toggle between booking and cancellation  
✅ **Real-time Seat Availability**: Live updates prevent overbooking  
✅ **Professional UI**: Modal dialogs instead of basic browser alerts  
✅ **Duplicate Prevention**: Comprehensive validation and constraints  

## 🎯 Production Ready Features

✅ **Database Atomicity**: All booking operations use stored procedures with row locking  
✅ **Real-time Updates**: JavaScript polling every 5 seconds with retry logic  
✅ **Concurrent User Support**: Multiple employees can book simultaneously safely  
✅ **Production Architecture**: Docker containers with proper service separation  
✅ **Error Handling**: Comprehensive error handling and user feedback  
✅ **Security**: Input validation, SQL injection protection, CORS configuration  

## 📞 Support

### Getting Help
1. **Check Logs**: `docker-compose logs -f`
2. **Test API**: `curl http://localhost:8080/api/health`
3. **Database**: Access phpMyAdmin at http://localhost:8081
4. **Container Status**: `docker-compose ps`

### System Information
- **Technology Stack**: PHP 8.2, MySQL 8.0, nginx, Docker
- **Real-time Method**: JavaScript polling with exponential backoff
- **Database**: Stored procedures for atomic operations
- **Frontend**: Vanilla JavaScript with modern API patterns

---

## 🎉 Success!

Your **production-ready, real-time bus booking system** is now configured with:

✅ **Real-time seat availability** that updates every 5 seconds  
✅ **Atomic booking operations** preventing double-bookings  
✅ **Professional UI** with modal dialogs and smooth UX  
✅ **Concurrent user support** for multiple employees  
✅ **Complete admin system** for managing buses and employees  

**Access your system at:** http://localhost:8080

The system addresses all original requirements with a proper database-driven, real-time architecture suitable for production deployment.