<?php
/**
 * Application Configuration
 * Contains global constants and settings
 */

// Application Information
define('APP_NAME', 'Employee Attendance & Salary System');
define('APP_VERSION', '1.0.0');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'emp');
define('DB_USER', 'root');         // Update with your MySQL username
define('DB_PASS', 'root');             // Update with your MySQL password
define('DB_CHARSET', 'utf8mb4');

// Redis Cache Configuration
define('REDIS_HOST', 'localhost');
define('REDIS_PORT', 6379);
define('REDIS_PASSWORD', null);    // Set to null if no password
define('CACHE_ENABLED', true);     // Set to false to disable caching
define('CACHE_TTL', 3600);         // Time to live for cache items in seconds (1 hour)

// Session Configuration
define('SESSION_LIFETIME', 7200);  // Session lifetime in seconds (2 hours)
define('SESSION_NAME', 'ATTENDANCESESSID');
define('SESSION_SECURE', false);   // Set to true in production with HTTPS
define('SESSION_USE_STRICT_MODE', true);
define('SESSION_USE_COOKIES', true);
define('SESSION_USE_ONLY_COOKIES', true);
define('SESSION_HTTPONLY', true);
define('SESSION_PATH', '/');
define('SESSION_DOMAIN', '');      // Leave empty for current domain
define('SESSION_SAVE_HANDLER', 'files'); // Options: files, database, redis

// Date and Time Settings
define('TIMEZONE', 'Asia/Kolkata'); // Set your timezone
define('DATE_FORMAT', 'Y-m-d');
define('TIME_FORMAT', 'H:i');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');

// Employee Settings
define('DEFAULT_WORKING_HOURS', 8);
define('OVERTIME_RATE_MULTIPLIER', 1.5); // Overtime pay is 1.5x normal rate

// Currency Settings
define('CURRENCY_SYMBOL', '₹');
define('CURRENCY_CODE', 'INR');

// Pagination Settings
define('ITEMS_PER_PAGE', 10);

// File Upload Settings
define('UPLOAD_DIR', 'uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5 MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx']);

// Error Reporting
define('DISPLAY_ERRORS', true);  // Set to false in production
define('LOG_ERRORS', true);
define('ERROR_LOG_FILE', 'logs/error.log');

// Set timezone
date_default_timezone_set(TIMEZONE);

// Error handling settings
if (DISPLAY_ERRORS) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
}

if (LOG_ERRORS) {
    ini_set('log_errors', 1);
    ini_set('error_log', ERROR_LOG_FILE);
}

// Create necessary directories if they don't exist
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

if (!is_dir(dirname(ERROR_LOG_FILE))) {
    mkdir(dirname(ERROR_LOG_FILE), 0755, true);
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Configure session
    session_name(SESSION_NAME);
    
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => SESSION_PATH,
        'domain' => SESSION_DOMAIN,
        'secure' => SESSION_SECURE,
        'httponly' => SESSION_HTTPONLY,
        'samesite' => 'Lax'
    ]);
    
    // Use database or Redis for session storage if configured
    if (SESSION_SAVE_HANDLER === 'database') {
        ini_set('session.save_handler', 'user');
        // Session handler will be initialized in db_functions.php
    } elseif (SESSION_SAVE_HANDLER === 'redis' && extension_loaded('redis')) {
        ini_set('session.save_handler', 'redis');
        $redis_session_url = 'tcp://' . REDIS_HOST . ':' . REDIS_PORT;
        if (REDIS_PASSWORD) {
            $redis_session_url = "tcp://" . REDIS_HOST . ":" . REDIS_PORT . "?auth=" . REDIS_PASSWORD;
        }
        ini_set('session.save_path', $redis_session_url);
    }
    
    session_start();
}

// Include core files
require_once 'includes/db_functions.php';
require_once 'includes/auth_functions.php';
require_once 'includes/functions.php';