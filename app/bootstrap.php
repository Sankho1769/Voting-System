<?php
/**
 * VerityVote - Application Bootstrap and Class Autoloader
 */

declare(strict_types=1);

// 1. Standard PSR-4 Autoloader for App namespace
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/';
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// 2. Load Configuration and Set Standardized Timezone
$configFile = dirname(__DIR__) . '/config/config.php';
if (!file_exists($configFile)) {
    $configFile = dirname(__DIR__) . '/config/config.example.php';
}
$appConfig = require $configFile;

date_default_timezone_set($appConfig['app']['timezone'] ?? 'Asia/Kolkata');

// 3. Error Reporting and Private Logging
$logDir = $appConfig['storage']['logs_path'] ?? (dirname(__DIR__) . '/storage/logs');
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

if (!empty($appConfig['app']['debug'])) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    ini_set('log_errors', '1');
    ini_set('error_log', $logDir . '/php_errors.log');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', $logDir . '/php_errors.log');
    error_reporting(E_ALL);

    // Global Uncaught Exception Handler for production safety
    set_exception_handler(function (\Throwable $e): void {
        \App\Core\Logger::error("Uncaught exception: " . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'code' => $e->getCode()
        ]);

        if (PHP_SAPI !== 'cli') {
            if (!headers_sent()) {
                http_response_code(500);
            }
            try {
                \App\Core\View::render('errors/500');
            } catch (\Throwable $renderError) {
                echo "<!DOCTYPE html><html><head><title>500 Error</title></head><body><h1>500 Internal Server Error</h1><p>An unexpected internal error occurred. Please contact the administrator.</p></body></html>";
            }
        } else {
            fwrite(STDERR, "[FATAL ERROR] " . $e->getMessage() . "\n");
        }
        exit(1);
    });
}
