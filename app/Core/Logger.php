<?php
namespace App\Core;

/**
 * System and Security Audit Logger
 * Records immutable events in the database and structured files.
 * Strictly redacts passwords, tokens, and secret parameters.
 */
class Logger
{
    private static ?string $correlationId = null;

    private const REDACTED_KEYS = [
        'password',
        'password_hash',
        'temp_password',
        'password_confirmation',
        'current_password',
        'new_password',
        'confirm_password',
        'csrf_token',
        'token',
        'cookie',
        'session_id',
        'candidate_id',
        'candidate_choice',
        'choice'
    ];

    public static function getCorrelationId(): string
    {
        if (self::$correlationId === null) {
            self::$correlationId = $_SERVER['HTTP_X_CORRELATION_ID'] 
                ?? $_SERVER['HTTP_X_REQUEST_ID'] 
                ?? ('req_' . bin2hex(random_bytes(8)));
        }
        return self::$correlationId;
    }

    public static function audit(
        string $action,
        string $entityType,
        ?string $entityId = null,
        array $details = [],
        ?int $userId = null
    ): void {
        try {
            $db = Database::getInstance();

            if ($userId === null) {
                $userId = Auth::id();
            }

            $ip = self::getClientIp();
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI/Unknown', 0, 255);
            $correlationId = self::getCorrelationId();
            $cleanDetails = self::redactSensitiveData($details);
            $detailsJson = !empty($cleanDetails) ? json_encode($cleanDetails, JSON_UNESCAPED_SLASHES) : null;

            $db->execute(
                "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, user_agent, correlation_id, details_json) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$userId, $action, $entityType, $entityId, $ip, $userAgent, $correlationId, $detailsJson],
                'isssssss'
            );
        } catch (\Throwable $e) {
            self::fileLog('ERROR', 'Failed to write audit log to DB: ' . $e->getMessage());
        }

        self::fileLog('AUDIT', sprintf(
            '[%s] req=%s user_id=%s entity=%s:%s ip=%s details=%s',
            $action,
            self::getCorrelationId(),
            $userId ?? 'anon',
            $entityType,
            $entityId ?? 'none',
            self::getClientIp(),
            json_encode(self::redactSensitiveData($details))
        ));
    }

    public static function error(string $message, array $context = [], ?string $errorCategory = 'GENERAL_ERROR'): void
    {
        $details = !empty($context) ? ' ' . json_encode(self::redactSensitiveData($context), JSON_UNESCAPED_SLASHES) : '';
        self::fileLog('ERROR', $message . $details);
        self::structured('ERROR', $message, $context, $errorCategory);
    }

    public static function warning(string $message, array $context = []): void
    {
        $details = !empty($context) ? ' ' . json_encode(self::redactSensitiveData($context), JSON_UNESCAPED_SLASHES) : '';
        self::fileLog('WARNING', $message . $details);
        self::structured('WARNING', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        $details = !empty($context) ? ' ' . json_encode(self::redactSensitiveData($context), JSON_UNESCAPED_SLASHES) : '';
        self::fileLog('INFO', $message . $details);
        self::structured('INFO', $message, $context);
    }

    public static function structured(
        string $level,
        string $message,
        array $context = [],
        ?string $errorCategory = null,
        ?float $executionTimeMs = null
    ): void {
        $logDir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . '/verityvote-json-' . date('Y-m-d') . '.log';

        $entry = [
            'timestamp' => date('c'),
            'level' => strtoupper($level),
            'correlation_id' => self::getCorrelationId(),
            'route' => $_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_NAME'] ?? 'CLI'),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'client_ip' => self::getClientIp(),
            'message' => $message,
            'context' => self::redactSensitiveData($context)
        ];

        if ($errorCategory !== null) {
            $entry['error_category'] = $errorCategory;
        }

        if ($executionTimeMs !== null) {
            $entry['execution_time_ms'] = round($executionTimeMs, 2);
        }

        $jsonLine = json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n";
        @file_put_contents($logFile, $jsonLine, FILE_APPEND | LOCK_EX);
    }

    public static function fileLog(string $level, string $message): void
    {
        $logDir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . '/verityvote-' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $entry = sprintf("[%s] [%s] %s\n", $timestamp, strtoupper($level), $message);
        @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    public static function redactSensitiveData(array $data): array
    {
        $clean = [];
        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);
            if (in_array($lowerKey, self::REDACTED_KEYS, true)) {
                $clean[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $clean[$key] = self::redactSensitiveData($value);
            } else {
                $clean[$key] = $value;
            }
        }
        return $clean;
    }

    public static function getClientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';
    }
}
