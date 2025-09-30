&lt;?php
/**
 * Database Configuration and Connection Manager
 * Production-ready with connection pooling and error handling
 */

class DatabaseConfig {
    private static $instance = null;
    private $connection;
    
    // Database configuration
    private $host = 'mysql';  // Docker service name
    private $dbname = 'bus_booking_system';
    private $username = 'root';
    private $password = 'rootpassword';
    private $charset = 'utf8mb4';
    
    private function __construct() {
        $this-&gt;connect();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function connect() {
        try {
            $dsn = "mysql:host={$this-&gt;host};dbname={$this-&gt;dbname};charset={$this-&gt;charset}";
            
            $options = [
                PDO::ATTR_ERRMODE =&gt; PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE =&gt; PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES =&gt; false,
                PDO::MYSQL_ATTR_INIT_COMMAND =&gt; "SET sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'"
            ];
            
            $this-&gt;connection = new PDO($dsn, $this-&gt;username, $this-&gt;password, $options);
            
            // Set timezone
            $this-&gt;connection-&gt;exec("SET time_zone = '+00:00'");
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e-&gt;getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    public function getConnection() {
        // Check if connection is still alive
        try {
            $this-&gt;connection-&gt;query('SELECT 1');
        } catch (PDOException $e) {
            // Reconnect if connection is lost
            $this-&gt;connect();
        }
        
        return $this-&gt;connection;
    }
    
    public function beginTransaction() {
        return $this-&gt;connection-&gt;beginTransaction();
    }
    
    public function commit() {
        return $this-&gt;connection-&gt;commit();
    }
    
    public function rollback() {
        return $this-&gt;connection-&gt;rollback();
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserializing
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Base API Response Handler
 */
class ApiResponse {
    public static function success($data = null, $message = '', $code = 200) {
        http_response_code($code);
        return json_encode([
            'success' =&gt; true,
            'data' =&gt; $data,
            'message' =&gt; $message,
            'timestamp' =&gt; date('c')
        ], JSON_UNESCAPED_UNICODE);
    }
    
    public static function error($message, $code = 400, $details = null) {
        http_response_code($code);
        return json_encode([
            'success' =&gt; false,
            'error' =&gt; $message,
            'details' =&gt; $details,
            'timestamp' =&gt; date('c')
        ], JSON_UNESCAPED_UNICODE);
    }
    
    public static function validationError($errors, $message = 'Validation failed') {
        return self::error($message, 422, $errors);
    }
}

/**
 * Input Validation Helper
 */
class Validator {
    public static function validateEmployeeId($employeeId) {
        if (empty($employeeId)) {
            return 'Employee ID is required';
        }
        
        if (!preg_match('/^\d{7,10}$/', $employeeId)) {
            return 'Employee ID must be 7-10 digits';
        }
        
        return null;
    }
    
    public static function validateDate($date) {
        if (empty($date)) {
            return 'Date is required';
        }
        
        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj || $dateObj-&gt;format('Y-m-d') !== $date) {
            return 'Invalid date format. Use YYYY-MM-DD';
        }
        
        // Check if date is not in the past
        $today = new DateTime();
        if ($dateObj &lt; $today-&gt;setTime(0, 0, 0)) {
            return 'Cannot book for past dates';
        }
        
        // Check if date is not too far in the future (e.g., 30 days)
        $maxDate = new DateTime();
        $maxDate-&gt;add(new DateInterval('P30D'));
        if ($dateObj &gt; $maxDate) {
            return 'Cannot book more than 30 days in advance';
        }
        
        return null;
    }
    
    public static function validateBusId($busId) {
        if (empty($busId)) {
            return 'Bus ID is required';
        }
        
        if (!is_numeric($busId) || $busId &lt;= 0) {
            return 'Invalid bus ID';
        }
        
        return null;
    }
    
    public static function validateEmail($email) {
        if (empty($email)) {
            return 'Email is required';
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Invalid email format';
        }
        
        // Check for valid company domains
        $validDomains = ['@company.com', '@subsidiary.com', '@contractor.company.com'];
        $isValidDomain = false;
        
        foreach ($validDomains as $domain) {
            if (str_ends_with(strtolower($email), strtolower($domain))) {
                $isValidDomain = true;
                break;
            }
        }
        
        if (!$isValidDomain) {
            return 'Email must be from a valid company domain';
        }
        
        return null;
    }
}

/**
 * Logger for production debugging
 */
class Logger {
    public static function info($message, $context = []) {
        error_log("INFO: $message " . json_encode($context));
    }
    
    public static function error($message, $context = []) {
        error_log("ERROR: $message " . json_encode($context));
    }
    
    public static function debug($message, $context = []) {
        if (defined('DEBUG') && DEBUG) {
            error_log("DEBUG: $message " . json_encode($context));
        }
    }
}

// Set error reporting for production
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set JSON header
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

?&gt;