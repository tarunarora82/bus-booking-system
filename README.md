# Bus Slot Booking System

A complete, production-ready Progressive Web Application (PWA) for managing bus slot bookings for employees. This system provides a simplified, capacity-based booking approach without individual seat assignments.

## 🚀 Features

### Core Functionality
- **Worker-based Booking**: Employees can book bus slots using their Worker ID
- **Capacity Management**: Simple capacity-based system (no individual seat tracking)
- **Real-time Updates**: Live availability updates with Redis caching  
- **Waitlist Support**: Automatic waitlist management when buses are full
- **Email Notifications**: Automated confirmation and status emails
- **Offline Support**: PWA with offline capabilities and background sync

### Technical Features
- **Progressive Web App**: Installable, responsive, offline-capable
- **Docker Containerized**: Complete Docker setup for easy deployment
- **Security First**: CORS, rate limiting, input validation, JWT authentication
- **Database Optimization**: Indexed queries, views, triggers for performance
- **Modern Architecture**: PHP 8.2+, MySQL 8.0+, Redis, modern JavaScript

## 📋 System Requirements

### For Development
- Docker Desktop 20.10+
- Docker Compose 2.0+
- Web browser with PWA support
- 4GB RAM minimum, 8GB recommended

### For Production
- Linux server (Ubuntu 20.04+ or CentOS 8+)
- Docker Engine 20.10+
- Docker Compose 2.0+
- 8GB RAM minimum, 16GB recommended
- SSL certificate for HTTPS
- SMTP server for email notifications

## 🛠 Quick Start

### 1. Clone and Setup
```bash
# Clone the repository
cd "bus slot booking system"
cd bus-booking-system

# Copy environment configuration
cp .env.example .env

# Edit environment variables
nano .env  # Or your preferred editor
```

### 2. Configure Environment
Update `.env` file with your settings:
```env
# Database Configuration
DB_HOST=mysql
DB_PORT=3306
DB_NAME=bus_booking
DB_USER=booking_user
DB_PASS=secure_password_123

# Redis Configuration
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=redis_password_123

# Email Configuration (for notifications)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your-email@gmail.com
SMTP_PASS=your-app-password
SMTP_FROM=your-email@gmail.com
SMTP_FROM_NAME=Bus Booking System

# Application Configuration
APP_ENV=production
APP_DEBUG=false
JWT_SECRET=your-super-secure-jwt-secret-key-here

# Security Configuration
CORS_ORIGINS=https://yourdomain.com
RATE_LIMIT_REQUESTS=100
RATE_LIMIT_WINDOW=3600
```

### 3. Deploy with Docker
```bash
# Build and start all services
docker-compose up -d

# Check service status
docker-compose ps

# View logs
docker-compose logs -f
```

### 4. Access the Application
- **Main Application**: http://localhost:8080
- **Database Admin**: http://localhost:8081 (phpMyAdmin)
- **API Endpoints**: http://localhost:8080/api/

### 5. Default Login (phpMyAdmin)
- **Server**: mysql
- **Username**: booking_user
- **Password**: secure_password_123
- **Database**: bus_booking

## 🏗 System Architecture

### Backend Architecture
```
backend/
├── controllers/          # API endpoint controllers
│   ├── BookingController.php
│   └── ScheduleController.php
├── core/                # Core system classes
│   ├── Database.php     # Database connection & queries
│   ├── Request.php      # HTTP request handling
│   ├── Response.php     # API response formatting
│   └── Router.php       # URL routing
├── middleware/          # HTTP middleware
│   ├── CorsMiddleware.php
│   ├── AuthMiddleware.php
│   └── RateLimitMiddleware.php
├── services/           # Business logic services
│   ├── AuthService.php
│   ├── BookingService.php
│   └── EmailService.php
└── api/               # API entry points
    ├── bookings.php
    └── schedules.php
```

### Frontend Architecture
```
frontend/
├── assets/
│   ├── css/
│   │   └── styles.css      # Complete responsive styling
│   ├── js/
│   │   ├── config.js       # App configuration
│   │   ├── utils.js        # Utility functions
│   │   ├── api.js          # HTTP client with caching
│   │   ├── components.js   # UI components
│   │   └── app.js          # Main application logic
│   └── images/             # PWA icons and assets
├── index.html              # Main application page
├── manifest.json           # PWA manifest
├── sw.js                   # Service worker
└── offline.html            # Offline fallback page
```

### Database Schema
```sql
-- Core tables
users              # Employee/worker information
buses              # Bus fleet management
schedules          # Bus schedules (morning/evening)
bookings           # Booking transactions
waitlist           # Waitlist management
admin_users        # Admin authentication

-- Views
booking_summary    # Booking statistics view
schedule_availability  # Real-time availability

-- Triggers
booking_waitlist_trigger  # Auto-waitlist management
```

## 📱 PWA Features

### Installation
Users can install the app on their devices:
- **Desktop**: Install button in browser address bar
- **Mobile**: "Add to Home Screen" option
- **Automatic**: Install prompt after multiple visits

### Offline Capabilities
- **Static Content**: App shell cached for offline use
- **Dynamic Content**: API responses cached with TTL
- **Background Sync**: Offline actions sync when online
- **Offline Page**: Custom offline experience

### Performance
- **Service Worker**: Intelligent caching strategies
- **Compression**: Gzip compression for all assets
- **Lazy Loading**: Components loaded on demand
- **Redis Caching**: API response caching

## 🔒 Security Features

### Authentication & Authorization
- **Worker ID Validation**: Format validation (7-10 digits)
- **Session Management**: Secure session handling
- **Rate Limiting**: API endpoint protection
- **CORS Configuration**: Cross-origin request protection

### Data Protection
- **Input Validation**: Server-side validation for all inputs
- **SQL Injection Prevention**: Prepared statements only
- **XSS Protection**: Output escaping and CSP headers
- **HTTPS Enforcement**: SSL/TLS in production

### Security Headers
```
Content-Security-Policy: default-src 'self'
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
```

## 🚌 Booking System Logic

### Simplified Booking Flow
1. **Worker Identification**: Employee enters Worker ID
2. **Schedule Selection**: Choose morning or evening bus
3. **Availability Check**: Real-time capacity verification
4. **Booking Confirmation**: Immediate confirmation or waitlist
5. **Email Notification**: Automated confirmation email

### Capacity Management
- **No Seat Numbers**: Simplified capacity-based system
- **Real-time Updates**: Live availability via Redis
- **Waitlist System**: Automatic queuing when full
- **Cancellation Flow**: Easy booking cancellation

### Business Rules
- **Booking Window**: 24 hours advance booking
- **Cancellation Policy**: Cancel up to 2 hours before departure
- **Waitlist Limit**: Maximum 20 people per schedule
- **One Booking Rule**: One booking per worker per day

## 📊 Monitoring & Analytics

### Application Metrics
- **Booking Statistics**: Daily, weekly, monthly reports
- **Capacity Utilization**: Bus occupancy rates
- **Waitlist Analytics**: Waitlist conversion rates
- **User Behavior**: Booking patterns and trends

### System Monitoring
- **Error Logging**: Comprehensive error tracking
- **Performance Monitoring**: Response time tracking
- **Database Monitoring**: Query performance analysis
- **Cache Hit Rates**: Redis performance metrics

### Health Checks
```bash
# Check application health
curl http://localhost:8080/api/health

# Check database connectivity
docker-compose exec mysql mysqladmin ping

# Check Redis connectivity
docker-compose exec redis redis-cli ping
```
echo "APP_DEBUG=true" >> .env
docker-compose restart php-fpm

# View application logs
docker-compose logs -f php-fpm
tail -f backend/logs/app.log
```

## 📚 API Documentation

### Booking Endpoints
```
POST   /api/bookings              # Create booking
DELETE /api/bookings              # Cancel booking
GET    /api/bookings/history      # Get booking history
GET    /api/booking-status        # Get booking status
```

### Schedule Endpoints
```
GET    /api/schedules             # Get available schedules
GET    /api/schedules/{id}        # Get schedule details
GET    /api/schedules/{id}/availability  # Get schedule availability
```

### Example API Calls
```bash
# Create booking
curl -X POST http://localhost:8080/api/bookings \
  -H "Content-Type: application/json" \
  -d '{"worker_id":"1234567","schedule_id":1,"booking_date":"2024-01-15"}'

# Get schedules
curl "http://localhost:8080/api/schedules?date=2024-01-15"

# Check booking status
curl "http://localhost:8080/api/booking-status?worker_id=1234567&schedule_id=1&date=2024-01-15"
```

## 🤝 Contributing

### Development Setup
```bash
# Setup development environment
cp .env.example .env.dev
docker-compose -f docker-compose.dev.yml up -d

# Install development dependencies
docker-compose exec php-fpm composer install
```

### Code Standards
- **PHP**: PSR-12 coding standard
- **JavaScript**: ES6+ with proper documentation
- **Database**: Use prepared statements, proper indexing
- **Security**: Validate all inputs, escape all outputs

## 📞 Support

### Documentation
- **System Architecture**: `/docs/architecture.md`
- **API Reference**: `/docs/api.md` 
- **Deployment Guide**: `/docs/deployment.md`
- **Troubleshooting**: `/docs/troubleshooting.md`

### Getting Help
- **Issues**: Create detailed issue reports
- **Feature Requests**: Submit enhancement requests
- **Security Issues**: Report privately to maintainers

---

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- **Open Source Libraries**: Built with community-driven technologies
- **Docker Community**: For containerization best practices
- **PWA Community**: For progressive web app standards
- **Security Community**: For security best practices

---

**Bus Slot Booking System** - A complete, production-ready solution for employee transportation management.
>>>>>>> 477a2fd (Initial commit: import project)
