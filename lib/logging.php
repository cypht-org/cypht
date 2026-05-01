<?php

/**
 * Logging bridge for Monolog integration
 * @package framework
 * @subpackage logging
 */

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Formatter\LineFormatter;

/**
 * Logging bridge class that integrates Monolog with Cypht's debug system
 */
class Hm_Logger {

    /* Monolog logger instance */
    private static $logger = null;

    /* Singleton instance */
    private static $instance = null;

    /**
     * Get singleton instance
     * @return Hm_Logger
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the logger
     */
    private function __construct() {
        if (self::$logger === null) {
            self::$logger = new Logger('cypht');
            $this->configureHandlers();
        }
    }

    /**
     * Configure Monolog handlers based on environment variables
     * @return void
     */
    private function configureHandlers() {
        $log_level = $this->getLogLevel();
        $log_file = env('LOG_FILE', '');
        $enable_debug = filter_var(env('ENABLE_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);

        // Custom formatter that includes context
        $formatter = new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context%\n",
            "Y-m-d H:i:s",
            true,
            true
        );

        // Resolve relative paths against APP_PATH (or the app root derived from this file's location)
        if (!empty($log_file) && !str_starts_with($log_file, '/')) {
            $app_path = (defined('APP_PATH') && APP_PATH !== '') ? APP_PATH : dirname(__DIR__) . '/';
            $log_file = rtrim($app_path, '/') . '/' . $log_file;
        }

        if (!empty($log_file)) {
            // File handler — file must be accessible under open_basedir
            try {
                $fileHandler = new StreamHandler($log_file, $log_level);
                $fileHandler->setFormatter($formatter);
                self::$logger->pushHandler($fileHandler);
            } catch (\Exception $e) {
                error_log("Cypht: failed to open log file '{$log_file}': " . $e->getMessage());
            }
        }

        // Fall back to error_log (Apache or nginx error.log) when ENABLE_DEBUG=true, no handler set,
        // and running under a web SAPI (never in CLI — PHPUnit captures error_log output as exceptions)
        if ($enable_debug && count(self::$logger->getHandlers()) === 0 && php_sapi_name() !== 'cli') {
            $errorLogHandler = new ErrorLogHandler(
                ErrorLogHandler::OPERATING_SYSTEM,
                $log_level
            );
            $errorLogHandler->setFormatter($formatter);
            self::$logger->pushHandler($errorLogHandler);
        }

        // Last resort: NullHandler to prevent Monolog from throwing
        if (count(self::$logger->getHandlers()) === 0) {
            self::$logger->pushHandler(new \Monolog\Handler\NullHandler());
        }
    }

    /**
     * Get log level from environment
     * @return int Monolog log level constant
     */
    private function getLogLevel() {
        $level_name = strtoupper(env('LOG_LEVEL', 'WARNING'));
        
        $levels = [
            'DEBUG' => Logger::DEBUG,
            'INFO' => Logger::INFO,
            'NOTICE' => Logger::NOTICE,
            'WARNING' => Logger::WARNING,
            'ERROR' => Logger::ERROR,
            'CRITICAL' => Logger::CRITICAL,
            'ALERT' => Logger::ALERT,
            'EMERGENCY' => Logger::EMERGENCY,
        ];

        return $levels[$level_name] ?? Logger::WARNING;
    }

    /**
     * Map Cypht message type to Monolog log level
     * @param string $type Cypht message type (danger, warning, info, success)
     * @return int Monolog log level
     */
    private function mapTypeToLevel($type) {
        $mapping = [
            'danger' => Logger::ERROR,
            'error' => Logger::ERROR,
            'warning' => Logger::WARNING,
            'info' => Logger::INFO,
            'success' => Logger::INFO,
            'debug' => Logger::DEBUG,
        ];

        return $mapping[$type] ?? Logger::ERROR;
    }

    /**
     * Log a message
     * @param string $message Log message
     * @param string $type Cypht message type
     * @param array $context Additional context data
     * @return void
     */
    public function log($message, $type = 'danger', $context = []) {
        if (self::$logger === null) {
            return;
        }

        $level = $this->mapTypeToLevel($type);

        if (!isset($context['type'])) {
            $context['type'] = $type;
        }

        try {
            self::$logger->log($level, (string) $message, $context);
        } catch (\Exception $e) {
            // StreamHandler can throw on first write (e.g. open_basedir, permissions).
            // Remove all handlers, fall back to error_log and avoid crashing the app.
            foreach (self::$logger->getHandlers() as $handler) {
                self::$logger->popHandler();
            }
            self::$logger->pushHandler(new \Monolog\Handler\NullHandler());
            error_log("Cypht: log handler failed (falling back to NullHandler): " . $e->getMessage());
        }
    }

    /**
     * Log an error message
     * @param string $message
     * @param array $context
     * @return void
     */
    public static function error($message, $context = []) {
        self::getInstance()->log($message, 'danger', $context);
    }

    /**
     * Log a warning message
     * @param string $message
     * @param array $context
     * @return void
     */
    public static function warning($message, $context = []) {
        self::getInstance()->log($message, 'warning', $context);
    }

    /**
     * Log an info message
     * @param string $message
     * @param array $context
     * @return void
     */
    public static function info($message, $context = []) {
        self::getInstance()->log($message, 'info', $context);
    }

    /**
     * Log a debug message
     * @param string $message
     * @param array $context
     * @return void
     */
    public static function debug($message, $context = []) {
        self::getInstance()->log($message, 'debug', $context);
    }

    /**
     * Get the underlying Monolog logger instance
     * @return Logger
     */
    public static function getLogger() {
        self::getInstance();
        return self::$logger;
    }
}
