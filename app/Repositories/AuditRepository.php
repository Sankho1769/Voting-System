<?php
namespace App\Repositories;

use App\Core\Database;

/**
 * Audit Log Data Access Repository
 */
class AuditRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function getRecent(int $limit = 50, int $offset = 0, ?string $action = null, ?string $search = null): array
    {
        $params = [];
        $types = '';
        $where = ['1=1'];

        if (!empty($action)) {
            $where[] = "a.action = ?";
            $params[] = $action;
            $types .= 's';
        }

        if (!empty($search)) {
            $where[] = "(a.entity_id LIKE ? OR a.entity_type LIKE ? OR a.correlation_id LIKE ? OR u.identifier LIKE ? OR a.ip_address LIKE ?)";
            $term = '%' . $search . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $types .= 'sssss';
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT a.*, u.identifier, u.full_name as user_name, u.role as user_role 
                FROM audit_logs a 
                LEFT JOIN users u ON a.user_id = u.id 
                WHERE {$whereClause} 
                ORDER BY a.created_at DESC 
                LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';

        return $this->db->fetchAll($sql, $params, $types);
    }

    public function countAll(?string $action = null, ?string $search = null): int
    {
        $params = [];
        $types = '';
        $where = ['1=1'];

        if (!empty($action)) {
            $where[] = "action = ?";
            $params[] = $action;
            $types .= 's';
        }

        if (!empty($search)) {
            $where[] = "(a.entity_id LIKE ? OR a.entity_type LIKE ? OR a.correlation_id LIKE ? OR a.ip_address LIKE ?)";
            $term = '%' . $search . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $types .= 'ssss';
        }

        $whereClause = implode(' AND ', $where);
        return (int)$this->db->fetchValue("SELECT COUNT(*) FROM audit_logs a WHERE {$whereClause}", $params, $types);
    }

    public function getDistinctActions(): array
    {
        $rows = $this->db->fetchAll("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC");
        return array_column($rows, 'action');
    }
}
