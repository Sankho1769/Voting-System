<?php
namespace App\Repositories;

use App\Core\Database;

/**
 * User Data Access Repository
 */
class UserRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM users WHERE id = ?",
            [$id],
            'i'
        );
    }

    public function findByIdentifier(string $identifier): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM users WHERE identifier = ?",
            [$identifier],
            's'
        );
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO users 
                (identifier, role, full_name, email, department, academic_year, password_hash, must_change_password, status, temp_password_expires_at, auth_version) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";

        return $this->db->insert($sql, [
            $data['identifier'],
            $data['role'],
            $data['full_name'],
            $data['email'] ?? null,
            $data['department'] ?? null,
            $data['academic_year'] ?? null,
            $data['password_hash'],
            (int)($data['must_change_password'] ?? 1),
            $data['status'] ?? 'active',
            $data['temp_password_expires_at'] ?? null,
        ]);
    }

    public function updatePassword(int $userId, string $newPasswordHash): bool
    {
        $sql = "UPDATE users 
                SET password_hash = ?, 
                    must_change_password = 0, 
                    temp_password_expires_at = NULL, 
                    auth_version = auth_version + 1,
                    updated_at = NOW() 
                WHERE id = ?";

        return $this->db->mutate($sql, [$newPasswordHash, $userId], 'si') > 0;
    }

    public function resetTemporaryPassword(int $userId, string $tempPasswordHash, string $expiresAt): bool
    {
        $sql = "UPDATE users 
                SET password_hash = ?, 
                    must_change_password = 1, 
                    temp_password_expires_at = ?, 
                    auth_version = auth_version + 1,
                    updated_at = NOW() 
                WHERE id = ?";

        return $this->db->mutate($sql, [$tempPasswordHash, $expiresAt, $userId], 'ssi') > 0;
    }

    public function updateStatus(int $userId, string $status): bool
    {
        $sql = "UPDATE users 
                SET status = ?, 
                    auth_version = auth_version + 1,
                    updated_at = NOW() 
                WHERE id = ?";

        return $this->db->mutate($sql, [$status, $userId], 'si') > 0;
    }

    public function getAll(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $params = [];
        $types = '';
        $where = ['1=1'];

        if (!empty($filters['role'])) {
            $where[] = "role = ?";
            $params[] = $filters['role'];
            $types .= 's';
        }

        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $where[] = "(identifier LIKE ? OR full_name LIKE ? OR department LIKE ?)";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $types .= 'sss';
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT id, identifier, role, full_name, email, department, academic_year, status, must_change_password, created_at, updated_at 
                FROM users 
                WHERE {$whereClause} 
                ORDER BY id DESC 
                LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';

        return $this->db->fetchAll($sql, $params, $types);
    }

    public function countAll(array $filters = []): int
    {
        $params = [];
        $types = '';
        $where = ['1=1'];

        if (!empty($filters['role'])) {
            $where[] = "role = ?";
            $params[] = $filters['role'];
            $types .= 's';
        }

        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $where[] = "(identifier LIKE ? OR full_name LIKE ? OR department LIKE ?)";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $types .= 'sss';
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) FROM users WHERE {$whereClause}";

        return (int)$this->db->fetchValue($sql, $params, $types);
    }

    public function getDepartments(): array
    {
        $rows = $this->db->fetchAll("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department ASC");
        return array_column($rows, 'department');
    }
}
