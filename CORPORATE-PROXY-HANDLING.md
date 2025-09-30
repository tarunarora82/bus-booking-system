# 🌐 Corporate Proxy Handling - Bus Booking System

## Overview
This document explains how the Bus Booking System handles corporate proxy environments and ensures reliable API access without any offline dependencies.

## ✅ Corporate Proxy Solutions Implemented

### 1. **nginx Proxy Configuration**
```nginx
# Enhanced API proxy handling
location /api/ {
    # Remove /api prefix and proxy to PHP backend
    rewrite ^/api/(.*)$ /$1 break;
    
    # Proxy to PHP-FPM container
    fastcgi_pass php:9000;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME /var/www/html/api/index.php;
    
    # Corporate proxy compatibility headers
    fastcgi_param HTTP_X_FORWARDED_FOR $proxy_add_x_forwarded_for;
    fastcgi_param HTTP_X_FORWARDED_PROTO $scheme;
    fastcgi_param HTTP_X_REAL_IP $remote_addr;
    fastcgi_param HTTP_HOST $host;
    
    # Extended timeouts for slow corporate networks
    fastcgi_read_timeout 300;
    fastcgi_connect_timeout 300;
    fastcgi_send_timeout 300;
    
    # Buffer settings for large responses
    fastcgi_buffer_size 16k;
    fastcgi_buffers 4 16k;
    fastcgi_busy_buffers_size 32k;
}
```

### 2. **CORS Headers for Corporate Firewalls**
```nginx
# Corporate proxy-friendly CORS headers
add_header Access-Control-Allow-Origin *;
add_header Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS";
add_header Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With, X-CSRF-Token";
add_header Access-Control-Max-Age 86400;

# Handle preflight OPTIONS requests
if ($request_method = 'OPTIONS') {
    return 204;
}
```

### 3. **PHP API Corporate Proxy Support**
```php
// Corporate proxy and CORS handling
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');
header('Content-Type: application/json; charset=UTF-8');

// Get real client IP behind corporate proxy
function getRealClientIP() {
    $headers = [
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP', 
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}
```

### 4. **JavaScript Client Corporate Proxy Compatibility**
```javascript
// Corporate proxy-friendly request headers
const config = {
    method: 'GET',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Cache-Control': 'no-cache',
        'Pragma': 'no-cache',
        // Corporate proxy compatibility headers
        'X-Requested-With': 'XMLHttpRequest'
    },
    timeout: 30000, // Extended timeout for corporate proxies
    credentials: 'same-origin', // Important for corporate proxies
    ...options
};
```

## 🚫 **Offline Dependencies Removed**

### 1. **Service Worker Disabled**
- ❌ No service worker registration
- ❌ No offline caching
- ❌ No background sync
- ✅ Pure online system only

### 2. **PWA Features Disabled**
- ❌ No app manifest caching
- ❌ No offline pages
- ❌ No background sync
- ✅ Standard web application only

### 3. **API Configuration**
- ❌ No offline API fallbacks
- ❌ No local storage caching
- ❌ No offline booking queue
- ✅ Real-time API calls only

## 🔧 **Network Connectivity Testing**

### Access Network Test Page
Navigate to: `http://localhost:8080/network-test.html`

This page tests:
- ✅ API Health Check
- ✅ Bus Availability API
- ✅ CORS Headers
- ✅ Response Time
- ✅ Network Information
- ✅ Corporate Proxy Detection

## 📊 **Proxy-Friendly Features**

### 1. **Extended Timeouts**
```javascript
// API requests with extended timeouts
API_TIMEOUT: 30000 // 30 seconds for corporate environments
POLLING_INTERVAL: 5000 // 5 seconds for real-time updates
```

### 2. **Retry Logic**
```javascript
// Enhanced retry for corporate proxy issues
retryAttempts: 5 // More retries for proxy issues
retryDelay: 2000 // Longer delay between retries
```

### 3. **Network Status Monitoring**
```javascript
// Monitor network status for corporate environments
window.addEventListener('online', () => {
    this.isOnline = true;
    this.startRealTimeUpdates();
});

window.addEventListener('offline', () => {
    this.isOnline = false;
    this.stopRealTimeUpdates();
});
```

## 🏢 **Corporate Environment Configuration**

### 1. **Proxy Server Settings**
If your corporate proxy requires authentication:
```bash
# Docker environment variables
HTTP_PROXY=http://proxy.company.com:8080
HTTPS_PROXY=http://proxy.company.com:8080
NO_PROXY=localhost,127.0.0.1,mysql,php,redis
```

### 2. **Firewall Configuration**
Ensure these ports are open:
- **8080** - Main application
- **3307** - Database access (admin only)
- **8081** - phpMyAdmin (admin only)

### 3. **DNS Resolution**
Ensure localhost resolution works:
```bash
# Add to hosts file if needed
127.0.0.1 localhost
127.0.0.1 bus-booking.local
```

## 🔍 **Troubleshooting Corporate Proxy Issues**

### Common Issues & Solutions

#### 1. **API Calls Blocked**
**Symptoms:** 
- Network test shows API health failures
- CORS errors in browser console
- Timeout errors

**Solutions:**
```bash
# Check proxy settings
echo $HTTP_PROXY
echo $HTTPS_PROXY

# Test direct connectivity
curl -v http://localhost:8080/api/health

# Check corporate firewall logs
```

#### 2. **Long Response Times**
**Symptoms:**
- Slow page loading
- Timeout errors
- Intermittent failures

**Solutions:**
- Timeouts already increased to 30 seconds
- Retry logic handles temporary failures
- Extended buffer sizes for large responses

#### 3. **CORS Errors**
**Symptoms:**
- "Access-Control-Allow-Origin" errors
- Preflight OPTIONS failures

**Solutions:**
- All CORS headers properly configured
- OPTIONS requests handled at nginx level
- Wildcard origin allowed for development

## ✅ **Verification Checklist**

### API Connectivity
- [ ] http://localhost:8080/api/health returns 200 OK
- [ ] http://localhost:8080/api/buses/available returns bus data
- [ ] Network test page shows all green checkmarks
- [ ] Browser console shows no CORS errors

### Real-time Features
- [ ] Bus availability updates every 5 seconds
- [ ] Multiple browser tabs show synchronized data
- [ ] Booking/cancellation reflects immediately across all users
- [ ] No offline-related errors in console

### Corporate Compliance
- [ ] No service worker registration attempts
- [ ] No localStorage caching of sensitive data
- [ ] All API calls use relative URLs
- [ ] Extended timeouts handle slow corporate networks

## 🚀 **Production Deployment for Corporate Environment**

### 1. **Docker Configuration**
```yaml
# docker-compose.yml adjustments for corporate proxy
environment:
  - HTTP_PROXY=${HTTP_PROXY:-}
  - HTTPS_PROXY=${HTTPS_PROXY:-}
  - NO_PROXY=localhost,127.0.0.1,mysql,php,redis
```

### 2. **nginx SSL Configuration**
```nginx
# Add SSL for corporate security requirements
server {
    listen 443 ssl http2;
    ssl_certificate /etc/ssl/certs/your-cert.crt;
    ssl_certificate_key /etc/ssl/private/your-key.key;
    
    # Corporate-friendly SSL settings
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers on;
}
```

### 3. **Security Headers**
```nginx
# Additional security headers for corporate compliance
add_header X-Frame-Options SAMEORIGIN;
add_header X-Content-Type-Options nosniff;
add_header X-XSS-Protection "1; mode=block";
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains";
```

## 📞 **Support for Corporate IT Teams**

### System Architecture
- **Frontend**: Pure web application (no PWA/offline features)
- **Backend**: PHP 8.2 with MySQL 8.0
- **Proxy**: nginx with corporate-friendly configuration
- **Ports**: 8080 (HTTP), 443 (HTTPS), 3307 (DB admin)

### Security Features
- Input validation and sanitization
- SQL injection protection
- CORS properly configured
- No sensitive data in client storage
- Corporate proxy IP detection and logging

### Monitoring
- All API requests logged with real client IP
- Network connectivity test page for diagnostics
- Health check endpoints for monitoring
- Error logging for troubleshooting

---

## 🎯 **Summary**

The Bus Booking System is now configured as a **pure online system** with comprehensive corporate proxy support:

✅ **No Offline Dependencies** - Service workers and PWA features completely disabled  
✅ **Corporate Proxy Compatible** - Extended timeouts, proper headers, retry logic  
✅ **Real-time API Only** - All data comes from live database through PHP API  
✅ **Network Testing** - Dedicated page to verify corporate network compatibility  
✅ **Security Compliant** - Proper CORS, security headers, IP detection  

The system eliminates all offline functionality as requested and ensures reliable operation through corporate proxy environments.