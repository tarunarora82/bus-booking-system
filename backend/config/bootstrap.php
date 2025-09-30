<?php
/**
 * Bootstrap file - Initializes the application
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Load environment variables
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Load .env file from root directory
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// Database configuration
define('DB_HOST', $_ENV['DB_HOST'] ?? 'mysql');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'bus_booking');
define('DB_USER', $_ENV['DB_USER'] ?? 'bus_user');
define('DB_PASS', $_ENV['DB_PASSWORD'] ?? 'secure_password');
define('DB_CHARSET', 'utf8mb4');

// JWT configuration
define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? 'your_jwt_secret_key_here');
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRY', 3600); // 1 hour

// Redis configuration
define('REDIS_HOST', $_ENV['REDIS_HOST'] ?? 'redis');
define('REDIS_PORT', $_ENV['REDIS_PORT'] ?? 6379);

// Email configuration
define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com');
define('SMTP_PORT', $_ENV['SMTP_PORT'] ?? 587);
define('SMTP_USERNAME', $_ENV['SMTP_USERNAME'] ?? '');
define('SMTP_PASSWORD', $_ENV['SMTP_PASSWORD'] ?? '');
define('SMTP_ENCRYPTION', 'tls');

// Application configuration
define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost');
define('APP_ENV', $_ENV['APP_ENV'] ?? 'development');
define('APP_DEBUG', filter_var($_ENV['APP_DEBUG'] ?? true, FILTER_VALIDATE_BOOLEAN));

// Security configuration
define('CORS_ALLOWED_ORIGINS', explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? 'http://localhost,http://127.0.0.1'));
define('RATE_LIMIT_REQUESTS', (int)($_ENV['RATE_LIMIT_REQUESTS'] ?? 100));
define('RATE_LIMIT_WINDOW', (int)($_ENV['RATE_LIMIT_WINDOW'] ?? 60));

// Business logic configuration
define('BOOKING_CUTOFF_MINUTES', 15);
define('MAX_ADVANCE_BOOKING_DAYS', 1);
define('WAITLIST_EXPIRY_HOURS', 2);

// Logging configuration
define('LOG_LEVEL', 'INFO');
define('LOG_FILE', __DIR__ . '/../logs/app.log');

// Create logs directory if it doesn't exist
$logDir = dirname(LOG_FILE);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Set up error and exception handlers
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    
    $errorMessage = "PHP Error: $message in $file on line $line";
    error_log($errorMessage);
    
    if (APP_DEBUG) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
    
    return true;
});

set_exception_handler(function($exception) {
    $errorMessage = "Uncaught Exception: " . $exception->getMessage() . 
                   " in " . $exception->getFile() . 
                   " on line " . $exception->getLine();
    error_log($errorMessage);
    
    // Don't expose internal errors in production
    if (!APP_DEBUG) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Internal server error'
        ]);
    } else {
        throw $exception;
    }
});

// Initialize autoloader
spl_autoload_register(function ($class) {
    $prefix = 'BusBooking\\';
    $base_dir = __DIR__ . '/../src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Initialize core services
try {
    // Test database connection
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    if (APP_DEBUG) {
        throw $e;
    }
}