#!/bin/bash
# Database initialization script for production bus booking system

echo "🚀 Initializing Bus Booking System Database..."

# Wait for MySQL to be ready
echo "⏳ Waiting for MySQL to be ready..."
while ! mysqladmin ping -h mysql -u root -prootpassword --silent; do
    sleep 1
done

echo "✅ MySQL is ready!"

# Create database and run schema
echo "📊 Creating database schema..."
mysql -h mysql -u root -prootpassword < /docker-entrypoint-initdb.d/schema.sql

# Check if schema was created successfully
if [ $? -eq 0 ]; then
    echo "✅ Database schema created successfully!"
    
    # Test the database by checking tables
    echo "🔍 Verifying tables..."
    mysql -h mysql -u root -prootpassword -e "USE bus_booking_system; SHOW TABLES;"
    
    echo "📊 Checking initial data..."
    mysql -h mysql -u root -prootpassword -e "USE bus_booking_system; SELECT * FROM buses;"
    
    echo "🎉 Database initialization completed successfully!"
else
    echo "❌ Database schema creation failed!"
    exit 1
fi