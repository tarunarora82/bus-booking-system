# Deployment Guide - Bus Slot Booking System

This guide provides detailed instructions for deploying the Bus Slot Booking System in various environments.

## 🚀 Production Deployment

### Prerequisites

#### Server Requirements
- **Operating System**: Ubuntu 20.04+ or CentOS 8+
- **Memory**: 8GB RAM minimum, 16GB recommended
- **Storage**: 50GB SSD minimum, 100GB+ recommended
- **CPU**: 2 cores minimum, 4+ cores recommended
- **Network**: Public IP address and domain name

#### Software Requirements
- Docker Engine 20.10+
- Docker Compose 2.0+
- SSL Certificate (Let's Encrypt recommended)
- SMTP server access for email notifications

### Step 1: Server Setup

#### Update System
```bash
# Ubuntu/Debian
sudo apt update && sudo apt upgrade -y

# CentOS/RHEL
sudo yum update -y
```

#### Install Docker
```bash
# Add Docker's official GPG key
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg

# Add Docker repository
echo "deb [arch=amd64 signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

# Install Docker
sudo apt update
sudo apt install docker-ce docker-ce-cli containerd.io -y

# Start and enable Docker
sudo systemctl start docker
sudo systemctl enable docker

# Add user to docker group
sudo usermod -aG docker $USER
```

#### Install Docker Compose
```bash
# Download Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/download/v2.20.0/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose

# Make executable
sudo chmod +x /usr/local/bin/docker-compose

# Verify installation
docker-compose --version
```

### Step 2: SSL Certificate Setup

#### Using Let's Encrypt (Recommended)
```bash
# Install Certbot
sudo apt install certbot -y

# Stop any web server
sudo systemctl stop apache2 nginx 2>/dev/null || true

# Generate certificate
sudo certbot certonly --standalone -d yourdomain.com -d www.yourdomain.com

# Setup auto-renewal
echo "0 12 * * * /usr/bin/certbot renew --quiet" | sudo crontab -
```

#### Manual Certificate Installation
```bash
# Create SSL directory
sudo mkdir -p /etc/ssl/certs/bus-booking

# Copy your certificate files
sudo cp your-certificate.crt /etc/ssl/certs/bus-booking/
sudo cp your-private-key.key /etc/ssl/certs/bus-booking/
sudo cp ca-bundle.crt /etc/ssl/certs/bus-booking/

# Set proper permissions
sudo chmod 600 /etc/ssl/certs/bus-booking/*
```

### Step 3: Application Deployment

#### Clone Repository
```bash
# Create application directory
sudo mkdir -p /opt/bus-booking
sudo chown $USER:$USER /opt/bus-booking
cd /opt/bus-booking

# Clone repository (replace with your repository URL)
git clone https://github.com/your-org/bus-booking-system.git .
```

#### Configure Environment
```bash
# Copy production environment template
cp .env.example .env.production

# Edit production configuration
nano .env.production
```

#### Production Environment Configuration
```env
# Application Configuration
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Database Configuration
DB_HOST=mysql
DB_PORT=3306
DB_NAME=bus_booking_prod
DB_USER=booking_user_prod
DB_PASS=super_secure_production_password_here

# Redis Configuration
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=secure_redis_password_here

# Email Configuration
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your-production-email@yourdomain.com
SMTP_PASS=your-secure-app-password
SMTP_FROM=noreply@yourdomain.com
SMTP_FROM_NAME=Bus Booking System

# Security Configuration
JWT_SECRET=your-super-secure-64-character-jwt-secret-key-for-production
CORS_ORIGINS=https://yourdomain.com,https://www.yourdomain.com
RATE_LIMIT_REQUESTS=100
RATE_LIMIT_WINDOW=3600

# SSL Configuration
SSL_CERT_PATH=/etc/letsencrypt/live/yourdomain.com/fullchain.pem
SSL_KEY_PATH=/etc/letsencrypt/live/yourdomain.com/privkey.pem
```

#### Create Production Docker Compose
```bash
# Create production compose file
cat > docker-compose.prod.yml << 'EOF'
version: '3.8'

services:
  nginx:
    image: nginx:1.24-alpine
    container_name: bus-booking-nginx
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx/nginx.prod.conf:/etc/nginx/nginx.conf:ro
      - ./nginx/ssl.conf:/etc/nginx/ssl.conf:ro
      - ./frontend:/var/www/html:ro
      - /etc/letsencrypt:/etc/letsencrypt:ro
    depends_on:
      - php-fpm
    networks:
      - bus-booking-network

  php-fpm:
    build:
      context: .
      dockerfile: Dockerfile.prod
    container_name: bus-booking-php
    restart: unless-stopped
    volumes:
      - ./backend:/var/www/html/backend
      - ./php/php.prod.ini:/usr/local/etc/php/php.ini:ro
    env_file:
      - .env.production
    depends_on:
      - mysql
      - redis
    networks:
      - bus-booking-network

  mysql:
    image: mysql:8.0
    container_name: bus-booking-mysql
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASS}
      MYSQL_DATABASE: ${DB_NAME}
      MYSQL_USER: ${DB_USER}
      MYSQL_PASSWORD: ${DB_PASS}
    volumes:
      - mysql_data:/var/lib/mysql
      - ./database/init.sql:/docker-entrypoint-initdb.d/01-init.sql:ro
      - ./database/sample-data.sql:/docker-entrypoint-initdb.d/02-sample-data.sql:ro
      - ./mysql/my.prod.cnf:/etc/mysql/conf.d/custom.cnf:ro
    networks:
      - bus-booking-network

  redis:
    image: redis:7.2-alpine
    container_name: bus-booking-redis
    restart: unless-stopped
    command: redis-server --requirepass ${REDIS_PASSWORD}
    volumes:
      - redis_data:/data
      - ./redis/redis.prod.conf:/usr/local/etc/redis/redis.conf:ro
    networks:
      - bus-booking-network

volumes:
  mysql_data:
    driver: local
  redis_data:
    driver: local

networks:
  bus-booking-network:
    driver: bridge
EOF
```

#### Create Production Nginx Configuration
```bash
# Create nginx directory
mkdir -p nginx

# Create production nginx config
cat > nginx/nginx.prod.conf << 'EOF'
user nginx;
worker_processes auto;
error_log /var/log/nginx/error.log warn;
pid /var/run/nginx.pid;

events {
    worker_connections 1024;
    use epoll;
    multi_accept on;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    # Logging
    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" "$http_x_forwarded_for"';
    access_log /var/log/nginx/access.log main;

    # Performance
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 2048;
    client_max_body_size 10m;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' cdnjs.cloudflare.com fonts.googleapis.com; font-src 'self' cdnjs.cloudflare.com fonts.gstatic.com; img-src 'self' data:; connect-src 'self';" always;

    # Gzip Compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types
        text/plain
        text/css
        text/xml
        text/javascript
        application/javascript
        application/json
        application/xml+rss
        application/atom+xml
        image/svg+xml;

    # Rate Limiting
    limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;
    limit_req_zone $binary_remote_addr zone=general:10m rate=30r/s;

    # Redirect HTTP to HTTPS
    server {
        listen 80;
        server_name yourdomain.com www.yourdomain.com;
        return 301 https://$server_name$request_uri;
    }

    # HTTPS Server
    server {
        listen 443 ssl http2;
        server_name yourdomain.com www.yourdomain.com;
        
        root /var/www/html;
        index index.html index.php;

        # SSL Configuration
        include /etc/nginx/ssl.conf;

        # Security
        limit_req zone=general burst=20 nodelay;

        # Static files
        location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf)$ {
            expires 1y;
            add_header Cache-Control "public, immutable";
            add_header X-Content-Type-Options "nosniff";
        }

        # PWA Files
        location ~* \.(json|webmanifest)$ {
            expires 1d;
            add_header Cache-Control "public";
        }

        # API Endpoints
        location /api/ {
            limit_req zone=api burst=5 nodelay;
            try_files $uri $uri/ @php;
        }

        # PHP-FPM
        location @php {
            fastcgi_pass php-fpm:9000;
            fastcgi_index index.php;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME /var/www/html/backend$fastcgi_script_name;
            fastcgi_param DOCUMENT_ROOT /var/www/html/backend;
            fastcgi_read_timeout 300;
        }

        # Deny access to sensitive files
        location ~ /\. {
            deny all;
        }

        location ~ \.(env|log|sql)$ {
            deny all;
        }

        # Handle SPA routing
        location / {
            try_files $uri $uri/ /index.html;
        }
    }
}
EOF
```

#### Create SSL Configuration
```bash
cat > nginx/ssl.conf << 'EOF'
# SSL Configuration
ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;

# SSL Settings
ssl_protocols TLSv1.2 TLSv1.3;
ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-SHA384;
ssl_prefer_server_ciphers off;
ssl_session_cache shared:SSL:10m;
ssl_session_timeout 10m;

# HSTS
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

# OSCP Stapling
ssl_stapling on;
ssl_stapling_verify on;
ssl_trusted_certificate /etc/letsencrypt/live/yourdomain.com/chain.pem;
resolver 8.8.8.8 8.8.4.4 valid=300s;
resolver_timeout 5s;
EOF
```

#### Create Production Dockerfile
```bash
cat > Dockerfile.prod << 'EOF'
FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    curl \
    libzip-dev \
    zip \
    unzip \
    git \
    mysql-client \
    redis

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mysqli \
    zip

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s \
    CMD curl -f http://localhost:9000 || exit 1

EXPOSE 9000
CMD ["php-fpm"]
EOF
```

### Step 4: Deploy Application

#### Build and Start Services
```bash
# Build images
docker-compose -f docker-compose.prod.yml build

# Start services
docker-compose -f docker-compose.prod.yml up -d

# Check service status
docker-compose -f docker-compose.prod.yml ps
```

#### Verify Deployment
```bash
# Check application health
curl -k https://yourdomain.com/api/health

# Check logs
docker-compose -f docker-compose.prod.yml logs -f

# Check database connectivity
docker-compose -f docker-compose.prod.yml exec mysql mysqladmin ping

# Check Redis connectivity
docker-compose -f docker-compose.prod.yml exec redis redis-cli ping
```

## 🔄 Backup Strategy

### Database Backup Script
```bash
#!/bin/bash
# backup.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/opt/backups/bus-booking"
RETENTION_DAYS=30

# Create backup directory
mkdir -p $BACKUP_DIR

# Database backup
docker-compose -f docker-compose.prod.yml exec -T mysql mysqldump \
    --single-transaction --routines --triggers \
    -u ${DB_USER} -p${DB_PASS} ${DB_NAME} > $BACKUP_DIR/db_backup_$DATE.sql

# Redis backup
docker-compose -f docker-compose.prod.yml exec redis redis-cli BGSAVE
docker cp $(docker-compose -f docker-compose.prod.yml ps -q redis):/data/dump.rdb $BACKUP_DIR/redis_backup_$DATE.rdb

# Compress backups
gzip $BACKUP_DIR/db_backup_$DATE.sql
gzip $BACKUP_DIR/redis_backup_$DATE.rdb

# Remove old backups
find $BACKUP_DIR -name "*.gz" -mtime +$RETENTION_DAYS -delete

echo "Backup completed: $DATE"
```

#### Setup Automated Backups
```bash
# Make script executable
chmod +x backup.sh

# Add to crontab (daily at 2 AM)
crontab -e
# Add: 0 2 * * * /opt/bus-booking/backup.sh
```

## 📊 Monitoring Setup

### Health Check Script
```bash
#!/bin/bash
# health-check.sh

SERVICES=("nginx" "php-fpm" "mysql" "redis")
FAILED_SERVICES=()

for service in "${SERVICES[@]}"; do
    if ! docker-compose -f docker-compose.prod.yml ps $service | grep -q "Up"; then
        FAILED_SERVICES+=($service)
    fi
done

if [ ${#FAILED_SERVICES[@]} -gt 0 ]; then
    echo "CRITICAL: Services down: ${FAILED_SERVICES[*]}"
    # Send alert email/notification here
    exit 1
else
    echo "OK: All services running"
    exit 0
fi
```

### Log Rotation
```bash
# Create logrotate configuration
sudo tee /etc/logrotate.d/bus-booking << 'EOF'
/var/log/nginx/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 0644 nginx nginx
    postrotate
        docker-compose -f /opt/bus-booking/docker-compose.prod.yml exec nginx nginx -s reload
    endscript
}
EOF
```

## 🚀 Zero-Downtime Deployment

### Blue-Green Deployment Script
```bash
#!/bin/bash
# deploy.sh

set -e

COMPOSE_FILE="docker-compose.prod.yml"
BACKUP_COMPOSE_FILE="docker-compose.backup.yml"

echo "Starting deployment..."

# Create backup of current running services
cp $COMPOSE_FILE $BACKUP_COMPOSE_FILE

# Pull latest images
docker-compose -f $COMPOSE_FILE pull

# Start new services with different names
docker-compose -f $COMPOSE_FILE -p bus-booking-new up -d

# Wait for health check
sleep 30

# Health check
if curl -f -k https://yourdomain.com/api/health; then
    echo "Health check passed"
    
    # Stop old services
    docker-compose -f $BACKUP_COMPOSE_FILE -p bus-booking stop
    
    # Remove old containers
    docker-compose -f $BACKUP_COMPOSE_FILE -p bus-booking rm -f
    
    # Rename new services
    docker-compose -f $COMPOSE_FILE -p bus-booking-new stop
    docker-compose -f $COMPOSE_FILE -p bus-booking up -d
    docker-compose -f $COMPOSE_FILE -p bus-booking-new rm -f
    
    echo "Deployment successful"
else
    echo "Health check failed, rolling back..."
    docker-compose -f $COMPOSE_FILE -p bus-booking-new stop
    docker-compose -f $COMPOSE_FILE -p bus-booking-new rm -f
    exit 1
fi
```

## 🔧 Performance Tuning

### MySQL Optimization
```bash
# Create optimized MySQL configuration
cat > mysql/my.prod.cnf << 'EOF'
[mysqld]
# Memory Settings
innodb_buffer_pool_size = 2G
innodb_log_file_size = 256M
innodb_log_buffer_size = 16M
innodb_flush_log_at_trx_commit = 1

# Connection Settings
max_connections = 200
max_connect_errors = 10000

# Query Cache
query_cache_type = 1
query_cache_size = 128M

# Performance Schema
performance_schema = ON

# Logging
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2
EOF
```

### Redis Optimization
```bash
# Create Redis configuration
cat > redis/redis.prod.conf << 'EOF'
# Memory Management
maxmemory 1gb
maxmemory-policy allkeys-lru

# Persistence
save 900 1
save 300 10
save 60 10000

# Security
requirepass your_secure_redis_password

# Performance
tcp-keepalive 300
timeout 0
tcp-backlog 511
EOF
```

### PHP-FPM Optimization
```bash
# Create PHP production configuration
cat > php/php.prod.ini << 'EOF'
[PHP]
; Performance
opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 0
opcache.revalidate_freq = 0

; Memory
memory_limit = 512M
post_max_size = 10M
upload_max_filesize = 10M

; Security
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php/error.log

; Session
session.cookie_secure = 1
session.cookie_httponly = 1
session.use_strict_mode = 1
EOF
```

## 🛡️ Security Hardening

### Firewall Configuration
```bash
# Configure UFW firewall
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow ssh
sudo ufw allow 80
sudo ufw allow 443
sudo ufw enable

# Fail2ban for additional protection
sudo apt install fail2ban
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

### SSL Security Test
```bash
# Test SSL configuration
curl -I -k https://yourdomain.com
openssl s_client -connect yourdomain.com:443 -servername yourdomain.com
```

## 📱 Mobile App Deployment (Optional)

### Apache Cordova Build
```bash
# Install Cordova
npm install -g cordova

# Create mobile app
cordova create BusBookingApp com.company.busbooking "Bus Booking"
cd BusBookingApp

# Add platforms
cordova platform add android
cordova platform add ios

# Copy web assets
cp -r ../frontend/* www/

# Build
cordova build android --release
```

This completes the comprehensive deployment guide for the Bus Slot Booking System. The system is now ready for production use with proper security, monitoring, and backup strategies in place.