<?php
namespace App\Core;

use App\Helpers\Sanitizer;
use App\Helpers\TimeHelper;

/**
 * Lightweight Template View Engine
 */
class View
{
    private static ?string $baseUrl = null;

    public static function getBaseUrl(): string
    {
        if (self::$baseUrl === null) {
            $configFile = dirname(__DIR__, 2) . '/config/config.php';
            if (!file_exists($configFile)) {
                $configFile = dirname(__DIR__, 2) . '/config/config.example.php';
            }
            $config = require $configFile;
            $configuredBase = $config['app']['base_url'] ?? '';

            if (!empty($configuredBase)) {
                self::$baseUrl = rtrim($configuredBase, '/');
            } else {
                // Auto-detect based on script name and host
                $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
                $dir = str_replace('\\', '/', dirname($scriptName));
                self::$baseUrl = ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
            }
        }
        return self::$baseUrl;
    }

    public static function url(string $path = ''): string
    {
        $base = self::getBaseUrl();
        $path = '/' . ltrim($path, '/');
        return $base . $path;
    }

    public static function asset(string $path): string
    {
        return self::url('/assets/' . ltrim($path, '/'));
    }

    public static function render(string $template, array $data = [], ?string $layout = 'layouts/main'): void
    {
        $viewsDir = dirname(__DIR__) . '/Views';
        $templateFile = $viewsDir . '/' . ltrim($template, '/') . '.php';

        if (!file_exists($templateFile)) {
            throw new \RuntimeException("View template not found: {$templateFile}");
        }

        // Shared view variables
        $data['currentUser'] = Auth::user();
        $data['csrfField'] = CSRF::field();
        $data['csrfToken'] = CSRF::getToken();
        $data['flashes'] = Session::getFlashes();
        $data['baseUrl'] = self::getBaseUrl();

        // Extract variables to local scope
        extract($data, EXTR_SKIP);

        // Buffer the template content
        ob_start();
        require $templateFile;
        $content = ob_get_clean();

        if ($layout !== null) {
            $layoutFile = $viewsDir . '/' . ltrim($layout, '/') . '.php';
            if (file_exists($layoutFile)) {
                require $layoutFile;
                return;
            }
        }

        echo $content;
    }

    public static function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
