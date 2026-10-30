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

// 3. Error Reporting
if (!empty($appConfig['app']['debug'])) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}
