#!/bin/bash

# Bus Booking System - Health Monitoring Script
# This script checks all system components and services

echo "🚌 Bus Booking System - Health Check Monitor"
echo "============================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to test URL
test_url() {
    local url=$1
    local name=$2
    local expected_status=${3:-200}
    
    echo -n "Testing $name... "
    
    if command -v curl >/dev/null 2>&1; then
        response=$(curl -s -o /dev/null -w "%{http_code}" "$url" 2>/dev/null)
        if [ "$response" = "$expected_status" ]; then
            echo -e "${GREEN}✅ PASS${NC} (HTTP $response)"
            return 0
        else
            echo -e "${RED}❌ FAIL${NC} (HTTP $response)"
            return 1
        fi
    else
        echo -e "${YELLOW}⚠️ SKIP${NC} (curl not available)"
        return 0
    fi
}

# Function to test Docker container
test_container() {
    local container_name=$1
    local service_name=$2
    
    echo -n "Testing $service_name container... "
    
    if command -v docker >/dev/null 2>&1; then
        if docker ps --format "table {{.Names}}" | grep -q "$container_name"; then
            echo -e "${GREEN}✅ RUNNING${NC}"
            return 0
        else
            echo -e "${RED}❌ NOT RUNNING${NC}"
            return 1
        fi
    else
        echo -e "${YELLOW}⚠️ SKIP${NC} (Docker not available)"
        return 0
    fi
}

# Test Docker Containers
echo "🐳 Docker Containers:"
test_container "bus_booking_nginx" "Nginx"
test_container "bus_booking_php" "PHP-FPM"
test_container "bus_booking_mysql" "MySQL"
test_container "bus_booking_redis" "Redis"
echo ""

# Test API Endpoints
echo "🌐 API Endpoints:"
test_url "http://localhost:8080/api/health" "API Health Check"
test_url "http://localhost:8080/api/buses/available" "Bus Availability"
test_url "http://localhost:8080/api/admin/settings" "Database Connectivity"
test_url "http://localhost:8080/api/bookings" "Bookings API"
test_url "http://localhost:8080/api/admin/recent-bookings" "Admin API"
echo ""

# Test CORS with OPTIONS
echo "🔒 CORS Configuration:"
if command -v curl >/dev/null 2>&1; then
    echo -n "Testing CORS preflight... "
    cors_response=$(curl -s -o /dev/null -w "%{http_code}" -X OPTIONS \
        -H "Access-Control-Request-Method: GET" \
        -H "Access-Control-Request-Headers: Content-Type" \
        "http://localhost:8080/api/buses/available" 2>/dev/null)
    
    if [ "$cors_response" = "204" ]; then
        echo -e "${GREEN}✅ PASS${NC} (HTTP $cors_response)"
    else
        echo -e "${RED}❌ FAIL${NC} (HTTP $cors_response)"
    fi
else
    echo -e "${YELLOW}⚠️ SKIP${NC} (curl not available)"
fi
echo ""

# Test Frontend
echo "🖥️ Frontend:"
test_url "http://localhost:8080/" "Main Frontend" 200
test_url "http://localhost:8080/api-health-check.html" "Health Check Page" 200
echo ""

# Performance Test
echo "⚡ Performance:"
if command -v curl >/dev/null 2>&1; then
    echo -n "Testing API response time... "
    start_time=$(date +%s%3N)
    curl -s -o /dev/null "http://localhost:8080/api/health" 2>/dev/null
    end_time=$(date +%s%3N)
    duration=$((end_time - start_time))
    
    if [ $duration -lt 1000 ]; then
        echo -e "${GREEN}✅ FAST${NC} (${duration}ms)"
    elif [ $duration -lt 2000 ]; then
        echo -e "${YELLOW}⚠️ SLOW${NC} (${duration}ms)"
    else
        echo -e "${RED}❌ VERY SLOW${NC} (${duration}ms)"
    fi
else
    echo -e "${YELLOW}⚠️ SKIP${NC} (curl not available)"
fi
echo ""

echo "Health check completed! 🏁"
echo ""
echo "💡 Tips:"
echo "- If any container is not running, use: docker-compose up -d"
echo "- If API endpoints fail, check: docker logs bus_booking_nginx"
echo "- For database issues, check: docker logs bus_booking_mysql"
echo "- View live health status at: http://localhost:8080/api-health-check.html"