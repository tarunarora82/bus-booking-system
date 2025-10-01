# Bus Booking System - Complete Architecture Documentation

## System Overview
The Intel Bus Booking System is a containerized web application built with:
- **Frontend**: HTML/CSS/JavaScript (working.html, admin-new.html)
- **Backend**: PHP 8.2 with simple routing (simple-api.php, query-api.php)
- **Database**: MySQL 8.0 with file-based fallback
- **Infrastructure**: Docker Compose with nginx, PHP-FPM
- **Proxy Support**: Intel corporate proxy compatible

## Architecture Components

### 1. Frontend Architecture
```
frontend/
├── working.html          # Main user interface (employee booking)
├── admin-new.html        # Admin interface (management dashboard)
├── api-router.js         # API routing abstraction layer
└── query-api.php         # Query parameter based API endpoint
```

**Frontend Data Flow:**
1. User Interface → JavaScript API calls → nginx
2. nginx routes `/api/*` → PHP-FPM → simple-api.php
3. PHP processes request → Database/Files → JSON response
4. Frontend receives JSON → Updates UI

### 2. Backend Architecture
```
backend/
├── simple-api.php        # RESTful API with path-based routing
├── query-api.php         # Query parameter API (GET-based)
└── api/
    └── Various PHP files  # Additional API endpoints
```

**Backend Request Flow:**
- **Path-based**: `/api/buses/available` → simple-api.php
- **Query-based**: `/query-api.php?action=get_buses` → query-api.php

### 3. Database Architecture
- **Primary**: MySQL container (bus_booking_mysql)
- **Fallback**: JSON files in `frontend/data/`
- **Connection**: PDO with error handling

### 4. Infrastructure Architecture
```
Docker Services:
├── nginx (port 8080)     # Web server & reverse proxy
├── php (port 9000)       # PHP-FPM application server  
├── mysql (port 3307)     # Database server
└── redis (port 6379)     # Session/cache store
```

## Current Issues & Root Causes

### Issue 1: API Routing Mismatch
**Problem**: Frontend calls `/api/buses/available` but nginx routes incorrectly
**Root Cause**: nginx configuration routes ALL `/api/*` to same PHP file
**Impact**: "File not found" errors in browser tests

### Issue 2: Endpoint Inconsistency  
**Problem**: Some endpoints use different patterns
**Frontend calls**: `buses/available`, `booking/create`
**Backend expects**: Proper path parsing in simple-api.php

### Issue 3: Authentication Handling
**Admin endpoints**: Require authorization headers
**Current state**: No token validation implemented

## Production System Data Flow

### Main Interface Flow (working.html)
```
User Input (Employee ID) 
    ↓
apiCall('employee/bookings/{id}') 
    ↓
nginx /api/ → simple-api.php 
    ↓
PHP: employee/bookings/{id} endpoint
    ↓
Database/JSON file lookup
    ↓
JSON response: existing bookings
    ↓
Frontend: Display available buses + existing bookings
    ↓
User selects bus → Book/Cancel actions
```

### Admin Interface Flow (admin-new.html)
```
Dashboard Load
    ↓
Parallel API calls:
- apiCall('buses/available')
- apiCall('admin/recent-bookings') 
    ↓
nginx /api/ → simple-api.php
    ↓
PHP: admin/* endpoints (need auth)
    ↓
Database aggregation
    ↓
JSON response: dashboard data
    ↓
Frontend: Render admin dashboard
```

## API Endpoints Mapping

### Main Interface Endpoints
| Frontend Call | Backend Route | Description |
|---------------|---------------|-------------|
| `buses/available` | `/api/buses/available` | Get available buses with real-time capacity |
| `employee/bookings/{id}` | `/api/employee/bookings/{id}` | Get employee's current bookings |
| `booking/create` | `/api/booking/create` | Create new booking |
| `booking/cancel` | `/api/booking/cancel` | Cancel existing booking |

### Admin Interface Endpoints  
| Frontend Call | Backend Route | Description |
|---------------|---------------|-------------|
| `admin/recent-bookings` | `/api/admin/recent-bookings` | Get recent booking list (requires auth) |
| `admin/add-bus` | `/api/admin/add-bus` | Add new bus (requires auth) |
| `admin/add-employee` | `/api/admin/add-employee` | Add new employee (requires auth) |
| `admin/employees` | `/api/admin/employees` | Get employee list (requires auth) |

## Infrastructure Configuration

### nginx Configuration
- **Document Root**: `/var/www/html` (frontend files)
- **PHP-FPM**: Routes `/api/*` to `backend/simple-api.php`  
- **Static Files**: Direct serving of HTML/CSS/JS
- **CORS**: Enabled for development

### PHP-FPM Configuration
- **Container**: `bus_booking_php`
- **Volume Mapping**: 
  - `./backend` → `/var/www/html/api`
  - `./frontend` → `/var/www/html`

## Data Storage Architecture

### MySQL Database Schema
```sql
-- Bus information
buses: bus_number, route, capacity, schedule_time

-- Employee information  
employees: employee_id, name, email, department, site

-- Booking records
bookings: booking_id, employee_id, bus_number, schedule_date, status, created_at
```

### File-based Fallback
```
frontend/data/
├── buses.json       # Bus definitions
├── employees.json   # Employee directory
├── bookings.json    # Booking records
└── notifications.json # System notifications
```

## Authentication & Authorization

### Admin Endpoints Security
- **Method**: Bearer token in Authorization header
- **Implementation**: Currently stub (needs JWT or session)
- **Fallback**: No auth for development/testing

### Employee Endpoints Security
- **Method**: Employee ID validation
- **Implementation**: Basic existence check
- **Rate Limiting**: Not implemented (should add)

## Error Handling Strategy

### Frontend Error Handling
```javascript
try {
    const response = await apiCall(endpoint);
    if (response.status === 'success') {
        // Handle success
    } else {
        showResult(response.message, 'error');
    }
} catch (error) {
    showResult(`❌ Error: ${error.message}`, 'error');
}
```

### Backend Error Handling
```php
try {
    // Process request
    echo json_encode(['status' => 'success', 'data' => $result]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
```

## Performance & Scalability

### Current Limitations
1. **File-based storage**: Not suitable for high concurrency
2. **No caching**: Database queries on every request
3. **No load balancing**: Single nginx/PHP container

### Recommended Improvements
1. **Database**: Move fully to MySQL with connection pooling
2. **Caching**: Implement Redis for frequently accessed data
3. **API Gateway**: Add proper rate limiting and authentication
4. **Monitoring**: Add logging and metrics collection

## Testing Strategy

### Test Coverage Areas
1. **API Endpoints**: All CRUD operations
2. **Authentication**: Admin/employee access control  
3. **Data Validation**: Input sanitization and validation
4. **Error Scenarios**: Database failures, invalid inputs
5. **Integration**: Frontend-backend communication

### Test Implementation
- **Main Interface**: 5 core functionality tests
- **Admin Interface**: 5 management operation tests  
- **System Integration**: 4 infrastructure tests
- **Total Coverage**: 14 comprehensive test scenarios

## Deployment Configuration

### Docker Compose Services
```yaml
nginx: 
  - Ports: 8080:80, 443:443
  - Volumes: Frontend files, nginx config
  
php:
  - Volumes: Backend files, PHP config
  - Environment: Database credentials
  
mysql:
  - Port: 3307:3306  
  - Volumes: Persistent data, init scripts
  
redis:
  - Port: 6379:6379
  - Volume: Persistent cache data
```

### Environment Variables
```
DB_HOST=mysql
DB_NAME=bus_booking_system
DB_USER=root
DB_PASS=rootpassword
JWT_SECRET=(to be implemented)
SMTP_*=(email configuration)
```

## Maintenance & Operations

### Log Files
- **nginx**: `/var/log/nginx/access.log`, `/var/log/nginx/error.log`
- **PHP**: Container logs via `docker logs bus_booking_php`
- **MySQL**: Container logs via `docker logs bus_booking_mysql`

### Backup Strategy
- **Database**: Daily MySQL dumps
- **Files**: Version control for code, file backup for data
- **Configuration**: Docker compose and environment files

### Monitoring Points
- **API Response Times**: Track endpoint performance
- **Error Rates**: Monitor failed requests
- **Resource Usage**: CPU, memory, disk usage
- **Database Performance**: Query times, connection pool

This comprehensive documentation provides the foundation for understanding the complete system architecture and implementing proper test coverage that mirrors production workflows.