<?php
require_once __DIR__ . '/../../config/config.php';

class Logger {
    private static $logFile;
    private static $errorLogFile;

    public static function init() {
        $logDir = __DIR__ . '/../../logs';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0777, true);
        }

        self::$logFile = $logDir . '/app.log';
        self::$errorLogFile = $logDir . '/error.log';
    }

    public static function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
        
        file_put_contents(self::$logFile, $logMessage, FILE_APPEND);
    }

    public static function error($message, $exception = null) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [ERROR] $message";
        
        if ($exception) {
            $logMessage .= PHP_EOL . "Exception: " . $exception->getMessage();
            $logMessage .= PHP_EOL . "Stack trace: " . $exception->getTraceAsString();
        }
        
        $logMessage .= PHP_EOL;
        
        file_put_contents(self::$errorLogFile, $logMessage, FILE_APPEND);
        
        if (Config::get('APP_DEBUG', false)) {
            error_log($logMessage);
        }
    }

    public static function info($message) {
        self::log($message, 'INFO');
    }

    public static function warning($message) {
        self::log($message, 'WARNING');
    }

    public static function debug($message) {
        if (Config::get('APP_DEBUG', false)) {
            self::log($message, 'DEBUG');
        }
    }
}

// Initialize logger
Logger::init(); 