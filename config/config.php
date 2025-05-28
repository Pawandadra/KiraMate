<?php

class Config {
    private static $config = [];

    public static function load() {
        $envFile = __DIR__ . '/../.env';
        if (!file_exists($envFile)) {
            error_log("Environment file not found: $envFile");
            return;
        }
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if (strpos($value, '"') === 0 || strpos($value, "'") === 0) {
                    $value = substr($value, 1, -1);
                }
                
                self::$config[$key] = $value;
            }
        }
    }

    public static function get($key, $default = null) {
        return self::$config[$key] ?? $default;
    }
}

// Load configuration
Config::load();

// Check required environment variables
$required_vars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
foreach ($required_vars as $var) {
    if (!Config::get($var)) {
        die("Required environment variable not set: $var");
    }
}

// Database configuration
define('DB_HOST', Config::get('DB_HOST'));
define('DB_NAME', Config::get('DB_NAME'));
define('DB_USER', Config::get('DB_USER'));
define('DB_PASS', Config::get('DB_PASS'));

// Application configuration
define('APP_NAME', Config::get('APP_NAME', 'KiraMate'));
define('APP_URL', Config::get('APP_URL', 'http://localhost/kiramate'));
define('BASE_PATH', '/kiramate');
define('UPLOAD_DIR', __DIR__ . '/../public/uploads');

// Create upload directories if they don't exist
$directories = [
    UPLOAD_DIR,
    UPLOAD_DIR . '/tenant_documents',
    UPLOAD_DIR . '/shop_documents'
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        if (!mkdir($dir, 0755, true)) {
            error_log("Failed to create directory: $dir");
        }
    }
    if (!is_writable($dir)) {
        error_log("Directory not writable: $dir");
    }
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Create logs directory if it doesn't exist
if (!file_exists(__DIR__ . '/../logs')) {
    if (!mkdir(__DIR__ . '/../logs', 0755, true)) {
        error_log("Failed to create logs directory");
    }
}

// Set timezone
date_default_timezone_set(Config::get('APP_TIMEZONE', 'UTC')); 