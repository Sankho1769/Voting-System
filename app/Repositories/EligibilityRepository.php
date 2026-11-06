<?php
namespace App\Repositories;

use App\Core\Database;

/**
 * Voter Eligibility Mapping Repository
 */
class EligibilityRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function isEligible(int $electionId, int $userId): bool
    {
        // ARCHITECTURAL INVARIANT (Phase 12): Authoritative electoral decision gate.
        // MUST NEVER READ FROM CACHE. Direct database query is mandatory to ensure eligibility integrity.
        $val = $this->db->fetchValue(
            "SELECT id FROM election_eligibility WHERE election_id = ? AND user_id = ?",
            [$electionId, $userId],
            'ii'
        );
        return $val !== null;
    }

    public function getVotersForElection(int $electionId, int $limit = 200, int $offset = 0): array
    {
        $sql = "SELECT ee.id as eligibility_id, ee.assigned_at, 
                       u.id as user_id, u.identifier, u.full_name, u.role, u.department, u.academic_year, u.status,
                       assigner.full_name as assigned_by_name,
                       ep.voted_at,
                       CASE WHEN ep.id IS NOT NULL THEN 1 ELSE 0 END as has_participated
                FROM election_eligibility ee
                JOIN users u ON ee.user_id = u.id
                LEFT JOIN users assigner ON ee.assigned_by = assigner.id
                LEFT JOIN election_participation ep ON ee.election_id = ep.election_id AND ee.user_id = ep.user_id
                WHERE ee.election_id = ?
                ORDER BY u.identifier ASC
                LIMIT ? OFFSET ?";

        return $this->db->fetchAll($sql, [$electionId, $limit, $offset], 'iii');
    }

    public function countEligible(int $electionId): int
    {
        return (int)$this->db->fetchValue(
            "SELECT COUNT(*) FROM election_eligibility WHERE election_id = ?",
            [$electionId],
            'i'
        );
    }

    public function assign(int $electionId, int $userId, int $assignedBy): bool
    {
        $sql = "INSERT IGNORE INTO election_eligibility (election_id, user_id, assigned_by) VALUES (?, ?, ?)";
        return $this->db->mutate($sql, [$electionId, $userId, $assignedBy], 'iii') > 0;
    }

    public function remove(int $electionId, int $userId): bool
    {
        $sql = "DELETE FROM election_eligibility WHERE election_id = ? AND user_id = ?";
        return $this->db->mutate($sql, [$electionId, $userId], 'ii') > 0;
    }

    public function assignBatch(int $electionId, array $userIds, int $assignedBy): int
    {
        $count = 0;
        foreach ($userIds as $userId) {
            if ($this->assign($electionId, (int)$userId, $assignedBy)) {
                $count++;
            }
        }
        return $count;
    }

    public function assignByCriteria(
        int $electionId,
        ?string $role,
        ?string $department,
        ?string $academicYear,
        int $assignedBy
    ): int {
        $where = ["status = 'active'"];
        $params = [];
        $types = '';

        if (!empty($role)) {
            $where[] = "role = ?";
            $params[] = $role;
            $types .= 's';
        }

        if (!empty($department)) {
            $where[] = "department = ?";
            $params[] = $department;
            $types .= 's';
        }

        if (!empty($academicYear)) {
            $where[] = "academic_year = ?";
            $params[] = $academicYear;
            $types .= 's';
        }

        $whereClause = implode(' AND ', $where);
        $users = $this->db->fetchAll("SELECT id FROM users WHERE {$whereClause}", $params, $types);

        $inserted = 0;
        foreach ($users as $user) {
            if ($this->assign($electionId, (int)$user['id'], $assignedBy)) {
                $inserted++;
            }
        }

        return $inserted;
    }
}
