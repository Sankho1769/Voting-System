<?php
namespace App\Repositories;

use App\Core\Database;

/**
 * Login Attempt and Throttling Data Access Repository
 */
class LoginAttemptRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function recordAttempt(string $identifier, string $ip, bool $isSuccess): void
    {
        $sql = "INSERT INTO login_attempts (identifier, ip_address, is_successful, attempted_at) VALUES (?, ?, ?, NOW())";
        $this->db->execute($sql, [$identifier, $ip, $isSuccess ? 1 : 0], 'ssi');
    }

    public function getRecentFailedCount(string $identifier, string $ip, int $windowSeconds): int
    {
        $windowTime = date('Y-m-d H:i:s', time() - $windowSeconds);
        $sql = "SELECT COUNT(*) FROM login_attempts 
                WHERE identifier = ? 
                  AND is_successful = 0 
                  AND attempted_at >= ?";

        return (int)$this->db->fetchValue($sql, [$identifier, $windowTime], 'ss');
    }

    public function isThrottled(string $identifier, string $ip, int $maxAttempts = 5, int $windowSeconds = 900): bool
    {
        $failures = $this->getRecentFailedCount($identifier, $ip, $windowSeconds);
        return $failures >= $maxAttempts;
    }

    public function clearSuccessful(string $identifier, string $ip): void
    {
        $sql = "DELETE FROM login_attempts WHERE identifier = ?";
        $this->db->mutate($sql, [$identifier], 's');
    }
}
