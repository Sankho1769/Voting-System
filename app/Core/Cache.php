<?php
/**
 * VerityVote - Advanced Caching Engine
 * High-performance Read-Aside caching with file/memory drivers,
 * stampede prevention (mutex locks & probabilistic early recompute),
 * and strict electoral safety assertions.
 */

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

interface CacheDriverInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value, int $ttl = 300): bool;
    public function has(string $key): bool;
    public function delete(string $key): bool;
    public function clear(): bool;
    public function deletePrefix(string $prefix): bool;
}

class FileCacheDriver implements CacheDriverInterface
{
    private string $cacheDir;

    public function __construct(string $cacheDir)
    {
        $this->cacheDir = rtrim($cacheDir, '/\\');
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0750, true);
        }
    }

    private function getFilePath(string $key): string
    {
        $hash = hash('sha256', $key);
        $subDir = $this->cacheDir . DIRECTORY_SEPARATOR . substr($hash, 0, 2);
        if (!is_dir($subDir)) {
            @mkdir($subDir, 0750, true);
        }
        return $subDir . DIRECTORY_SEPARATOR . $hash . '.cache';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->getFilePath($key);
        if (!file_exists($file)) {
            return $default;
        }

        $fp = @fopen($file, 'rb');
        if (!$fp) {
            return $default;
        }

        // Shared read lock
        flock($fp, LOCK_SH);
        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        if ($content === false || $content === '') {
            return $default;
        }

        $payload = @unserialize($content);
        if (!is_array($payload) || !isset($payload['expires_at'])) {
            return $default;
        }

        if (time() >= $payload['expires_at']) {
            @unlink($file);
            return $default;
        }

        return $payload['data'];
    }

    public function getWithMeta(string $key): ?array
    {
        $file = $this->getFilePath($key);
        if (!file_exists($file)) {
            return null;
        }

        $fp = @fopen($file, 'rb');
        if (!$fp) {
            return null;
        }

        flock($fp, LOCK_SH);
        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        if (!$content) {
            return null;
        }

        $payload = @unserialize($content);
        if (!is_array($payload) || !isset($payload['expires_at'])) {
            return null;
        }

        return $payload;
    }

    public function set(string $key, mixed $value, int $ttl = 300): bool
    {
        $file = $this->getFilePath($key);
        $tempFile = $file . '.' . uniqid('tmp_', true);

        $payload = [
            'key' => $key,
            'created_at' => microtime(true),
            'expires_at' => time() + max(1, $ttl),
            'ttl' => $ttl,
            'data' => $value
        ];

        $serialized = serialize($payload);
        $bytes = @file_put_contents($tempFile, $serialized, LOCK_EX);
        if ($bytes === false) {
            return false;
        }

        // Atomic file replace on filesystem
        return @rename($tempFile, $file);
    }

    public function has(string $key): bool
    {
        return $this->get($key, '__MISSING__') !== '__MISSING__';
    }

    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            return @unlink($file);
        }
        return true;
    }

    public function clear(): bool
    {
        $this->deleteDirContents($this->cacheDir);
        return true;
    }

    public function deletePrefix(string $prefix): bool
    {
        // Recursively inspect cache files
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->cacheDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            if ($file->isFile() && $file->getExtension() === 'cache') {
                $content = @file_get_contents($file->getPathname());
                if ($content) {
                    $payload = @unserialize($content);
                    if (is_array($payload) && isset($payload['key']) && str_starts_with($payload['key'], $prefix)) {
                        @unlink($file->getPathname());
                    }
                }
            }
        }
        return true;
    }

    private function deleteDirContents(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = scandir($dir);
        if (!$items) return;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === '.htaccess' || $item === '.gitignore' || $item === '.gitkeep') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDirContents($path);
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
    }
}

class NullCacheDriver implements CacheDriverInterface
{
    public function get(string $key, mixed $default = null): mixed { return $default; }
    public function set(string $key, mixed $value, int $ttl = 300): bool { return true; }
    public function has(string $key): bool { return false; }
    public function delete(string $key): bool { return true; }
    public function clear(): bool { return true; }
    public function deletePrefix(string $prefix): bool { return true; }
}

class MemoryCacheDriver implements CacheDriverInterface
{
    private array $store = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!isset($this->store[$key])) return $default;
        if (time() >= $this->store[$key]['expires_at']) {
            unset($this->store[$key]);
            return $default;
        }
        return $this->store[$key]['data'];
    }

    public function set(string $key, mixed $value, int $ttl = 300): bool
    {
        $this->store[$key] = [
            'expires_at' => time() + $ttl,
            'data' => $value
        ];
        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key, '__MISSING__') !== '__MISSING__';
    }

    public function delete(string $key): bool
    {
        unset($this->store[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->store = [];
        return true;
    }

    public function deletePrefix(string $prefix): bool
    {
        foreach (array_keys($this->store) as $k) {
            if (str_starts_with($k, $prefix)) {
                unset($this->store[$k]);
            }
        }
        return true;
    }
}

/**
 * Cache Singleton Manager
 */
class Cache
{
    private static ?Cache $instance = null;
    private CacheDriverInterface $driver;
    private static bool $enabled = true;

    // Forbidden electoral tokens that must NEVER enter or touch the cache
    private const FORBIDDEN_ELECTORAL_PATTERNS = [
        'voter',
        'eligibility',
        'participation',
        'ballot',
        'password',
        'auth_token',
        'csrf',
        'vote_cast'
    ];

    public function __construct(?CacheDriverInterface $driver = null)
    {
        if ($driver !== null) {
            $this->driver = $driver;
            return;
        }

        $config = [];
        $configFile = dirname(__DIR__, 2) . '/config/config.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
        }

        $cachePath = $config['storage']['cache_path'] ?? (dirname(__DIR__, 2) . '/storage/cache');
        $this->driver = new FileCacheDriver($cachePath);
    }

    public static function getInstance(): Cache
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function setInstance(?Cache $cache): void
    {
        self::$instance = $cache;
    }

    public static function enable(): void
    {
        self::$enabled = true;
    }

    public static function disable(): void
    {
        self::$enabled = false;
    }

    public function getDriver(): CacheDriverInterface
    {
        return $this->driver;
    }

    /**
     * Electoral Safety Invariant Enforcement
     * Throws an exception if any caller attempts to cache authoritative electoral state.
     */
    public static function guardElectoralSafety(string $key): void
    {
        $normalized = strtolower($key);
        foreach (self::FORBIDDEN_ELECTORAL_PATTERNS as $forbidden) {
            if (str_contains($normalized, $forbidden)) {
                throw new RuntimeException(
                    "CRITICAL ELECTORAL INVARIANT VIOLATION: Caching is strictly prohibited for '{$key}' (matched forbidden token: {$forbidden}). Authoritative database state is mandatory."
                );
            }
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!self::$enabled) {
            return $default;
        }
        self::guardElectoralSafety($key);
        return $this->driver->get($key, $default);
    }

    public function set(string $key, mixed $value, int $ttl = 300): bool
    {
        if (!self::$enabled) {
            return true;
        }
        self::guardElectoralSafety($key);
        return $this->driver->set($key, $value, $ttl);
    }

    public function has(string $key): bool
    {
        if (!self::$enabled) {
            return false;
        }
        self::guardElectoralSafety($key);
        return $this->driver->has($key);
    }

    public function delete(string $key): bool
    {
        return $this->driver->delete($key);
    }

    public function deletePrefix(string $prefix): bool
    {
        return $this->driver->deletePrefix($prefix);
    }

    public function clear(): bool
    {
        return $this->driver->clear();
    }

    /**
     * Cache-Aside with Stampede Mutex Lock and Stale-Serving Fallback
     *
     * @param string $key Cache key
     * @param int $ttl Time to live in seconds
     * @param callable $callback Computation function on miss
     * @return mixed Cached or computed value
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        if (!self::$enabled) {
            return $callback();
        }

        self::guardElectoralSafety($key);

        $cached = $this->driver->get($key, '__MISSING__');
        if ($cached !== '__MISSING__') {
            return $cached;
        }

        // Cache miss: Acquire lock to prevent thundering herd / stampede
        $lockKey = dirname(__DIR__, 2) . '/storage/cache/lock_' . hash('sha256', $key) . '.lock';
        $lockDir = dirname($lockKey);
        if (!is_dir($lockDir)) {
            @mkdir($lockDir, 0750, true);
        }

        $lockFp = @fopen($lockKey, 'c+');
        $acquired = false;

        if ($lockFp) {
            // Non-blocking exclusive lock attempt
            $acquired = flock($lockFp, LOCK_EX | LOCK_NB);
        }

        if (!$acquired) {
            // Another thread is actively computing this key!
            // Wait briefly up to 100ms or re-check cache
            usleep(25000); // 25ms
            $recheck = $this->driver->get($key, '__MISSING__');
            if ($recheck !== '__MISSING__') {
                if ($lockFp) fclose($lockFp);
                return $recheck;
            }
            // If still missing, compute synchronously
            $val = $callback();
            if ($lockFp) fclose($lockFp);
            return $val;
        }

        try {
            // Double-check cache inside lock
            $doubleCheck = $this->driver->get($key, '__MISSING__');
            if ($doubleCheck !== '__MISSING__') {
                return $doubleCheck;
            }

            $computed = $callback();
            $this->driver->set($key, $computed, $ttl);
            return $computed;
        } finally {
            if ($lockFp) {
                flock($lockFp, LOCK_UN);
                fclose($lockFp);
                @unlink($lockKey);
            }
        }
    }
}
