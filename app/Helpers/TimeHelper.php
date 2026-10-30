<?php
namespace App\Helpers;

use DateTime;
use DateTimeZone;

/**
 * Timezone and Date Utility Helper
 */
class TimeHelper
{
    private static ?string $timezone = null;

    public static function getTimezone(): string
    {
        if (self::$timezone === null) {
            $configFile = dirname(__DIR__, 2) . '/config/config.php';
            if (!file_exists($configFile)) {
                $configFile = dirname(__DIR__, 2) . '/config/config.example.php';
            }
            $config = require $configFile;
            self::$timezone = $config['app']['timezone'] ?? 'Asia/Kolkata';
        }
        return self::$timezone;
    }

    public static function format(?string $datetime, string $format = 'd M Y, h:i A'): string
    {
        if (empty($datetime)) {
            return '—';
        }

        try {
            $dt = new DateTime($datetime, new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone(self::getTimezone()));
            return $dt->format($format);
        } catch (\Exception $e) {
            return $datetime;
        }
    }

    public static function toIso8601(?string $datetime): string
    {
        if (empty($datetime)) {
            return '';
        }

        try {
            $dt = new DateTime($datetime, new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone(self::getTimezone()));
            return $dt->format(DateTime::ATOM);
        } catch (\Exception $e) {
            return '';
        }
    }

    public static function now(): string
    {
        $dt = new DateTime('now', new DateTimeZone('UTC'));
        return $dt->format('Y-m-d H:i:s');
    }

    public static function isPast(string $datetime): bool
    {
        $target = strtotime($datetime);
        return $target && $target < time();
    }

    public static function isFuture(string $datetime): bool
    {
        $target = strtotime($datetime);
        return $target && $target > time();
    }
}
