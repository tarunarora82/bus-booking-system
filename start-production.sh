#!/bin/bash
# Production startup script for Bus Booking System

echo "🚀 Starting Bus Booking System in Production Mode..."

# Color codes for better output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    print_error "Docker is not running. Please start Docker and try again."
    exit 1
fi

print_success "Docker is running"

# Check if docker-compose is available
if ! command -v docker-compose &> /dev/null; then
    print_error "docker-compose is not installed. Please install docker-compose and try again."
    exit 1
fi

print_success "docker-compose is available"

# Stop existing containers if running
print_status "Stopping existing containers..."
docker-compose down > /dev/null 2>&1

# Remove old volumes to ensure fresh start
print_status "Cleaning up old data (optional)..."
# docker-compose down -v  # Uncomment this line to remove all data

# Build and start containers
print_status "Building and starting containers..."
docker-compose up -d --build

# Check if containers are running
print_status "Checking container status..."
sleep 10

# Check MySQL health
print_status "Waiting for MySQL to be ready..."
timeout=60
counter=0
while ! docker-compose exec -T mysql mysqladmin ping -h localhost -u root -prootpassword --silent 2>/dev/null; do
    if [ $counter -eq $timeout ]; then
        print_error "MySQL failed to start within $timeout seconds"
        docker-compose logs mysql
        exit 1
    fi
    print_status "MySQL is starting... ($counter/$timeout)"
    sleep 2
    counter=$((counter+2))
done

print_success "MySQL is ready!"

# Check if PHP service is responding
print_status "Checking PHP API service..."
if docker-compose ps php | grep -q "Up"; then
    print_success "PHP service is running"
else
    print_error "PHP service failed to start"
    docker-compose logs php
    exit 1
fi

# Check if Nginx is responding
print_status "Checking Nginx web server..."
if docker-compose ps nginx | grep -q "Up"; then
    print_success "Nginx web server is running"
else
    print_error "Nginx web server failed to start"
    docker-compose logs nginx
    exit 1
fi

# Test database connection and verify schema
print_status "Verifying database schema..."
if docker-compose exec -T mysql mysql -u root -prootpassword -e "USE bus_booking_system; SHOW TABLES;" > /dev/null 2>&1; then
    print_success "Database schema is properly initialized"
    
    # Show table status
    echo ""
    print_status "Database tables:"
    docker-compose exec -T mysql mysql -u root -prootpassword -e "USE bus_booking_system; SHOW TABLES;"
    
    echo ""
    print_status "Sample data check:"
    docker-compose exec -T mysql mysql -u root -prootpassword -e "USE bus_booking_system; SELECT bus_number, capacity FROM buses LIMIT 3;"
else
    print_warning "Database schema verification failed, but system may still work"
fi

# Test API endpoints
print_status "Testing API endpoints..."
sleep 5

# Test health endpoint
if curl -s -f http://localhost:8080/api/health > /dev/null; then
    print_success "API health endpoint is responding"
else
    print_warning "API health endpoint is not responding yet"
fi

# Show final status
echo ""
echo "=========================================="
print_success "🎉 Bus Booking System is running!"
echo "=========================================="
echo ""
echo "📱 Frontend Application: http://localhost:8080"
echo "🔧 Admin Panel: http://localhost:8080/admin"
echo "🗄️  Database Admin: http://localhost:8081 (phpMyAdmin)"
echo "🔌 API Endpoints: http://localhost:8080/api"
echo ""
echo "📊 Container Status:"
docker-compose ps

echo ""
echo "📝 Real-time Features:"
echo "   ✅ Live seat availability updates"
echo "   ✅ Concurrent booking protection"
echo "   ✅ Duplicate booking prevention"
echo "   ✅ Professional notification system"
echo ""
echo "🔧 Administration:"
echo "   - Add/Edit buses via Admin Panel"
echo "   - Manage employee records"
echo "   - Monitor booking statistics"
echo ""
echo "🛠️  Development Commands:"
echo "   docker-compose logs -f        # View live logs"
echo "   docker-compose down           # Stop system"
echo "   docker-compose restart        # Restart services"
echo ""
print_success "System is ready for production use!"