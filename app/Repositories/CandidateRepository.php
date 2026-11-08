<?php
namespace App\Repositories;

use App\Core\Database;

/**
 * Candidate Data Access Repository
 */
class CandidateRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT c.*, e.title as election_title, e.status as election_status, e.position,
                    u.identifier as user_identifier, u.role as user_role, u.department as user_department
             FROM candidates c
             JOIN elections e ON c.election_id = e.id
             LEFT JOIN users u ON c.user_id = u.id
             WHERE c.id = ?",
            [$id],
            'i'
        );
    }

    /**
     * Find all candidate profiles linked to a collegiate user ID
     */
    public function findByUserId(int $userId): array
    {
        $sql = "SELECT c.*, e.title as election_title, e.position, e.status as election_status,
                       e.opens_at, e.closes_at, e.results_visibility
                FROM candidates c
                JOIN elections e ON c.election_id = e.id
                WHERE c.user_id = ?
                ORDER BY e.created_at DESC, c.id DESC";
        return $this->db->fetchAll($sql, [$userId], 'i');
    }

    /**
     * Get primary active candidate profile for user
     */
    public function getPrimaryByUserId(int $userId): ?array
    {
        $profiles = $this->findByUserId($userId);
        return !empty($profiles) ? $profiles[0] : null;
    }

    /**
     * Lookup candidate by candidate identifier (e.g. 'CAND-01', 'CAND1', integer ID, or candidate code/name)
     */
    public function findByCandidateIdentifier(string $identifier): ?array
    {
        $trimmed = trim($identifier);
        $cleanId = preg_replace('/^CAND-?/i', '', $trimmed);
        if (is_numeric($cleanId)) {
            $cand = $this->findById((int)$cleanId);
            if ($cand) {
                return $cand;
            }
        }

        // Also check if candidate identifier matches a candidate by name or linked user identifier
        $sql = "SELECT c.*, u.identifier as user_identifier, u.department as user_department
                FROM candidates c
                LEFT JOIN users u ON c.user_id = u.id
                WHERE u.identifier = ? OR c.name = ?
                LIMIT 1";
        return $this->db->fetchOne($sql, [$trimmed, $trimmed], 'ss');
    }

    public function getByElectionId(int $electionId, bool $approvedOnly = true): array
    {
        if ($approvedOnly) {
            $sql = "SELECT c.*, u.identifier as user_identifier, u.department as user_department
                    FROM candidates c
                    LEFT JOIN users u ON c.user_id = u.id
                    WHERE c.election_id = ? AND c.approval_status = 'approved'
                    ORDER BY c.display_order ASC, c.name ASC";
            return $this->db->fetchAll($sql, [$electionId], 'i');
        }

        $sql = "SELECT c.*, u.identifier as user_identifier, u.department as user_department
                FROM candidates c
                LEFT JOIN users u ON c.user_id = u.id
                WHERE c.election_id = ?
                ORDER BY c.display_order ASC, c.name ASC";
        return $this->db->fetchAll($sql, [$electionId], 'i');
    }

    public function countApproved(int $electionId): int
    {
        return (int)$this->db->fetchValue(
            "SELECT COUNT(*) FROM candidates WHERE election_id = ? AND approval_status = 'approved'",
            [$electionId],
            'i'
        );
    }

    public function create(array $data): int
    {
        $userId = !empty($data['user_id']) ? (int)$data['user_id'] : null;

        $sql = "INSERT INTO candidates
                (election_id, user_id, name, manifesto, photo_path, approval_status, display_order)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        return $this->db->insert($sql, [
            (int)$data['election_id'],
            $userId,
            $data['name'],
            $data['manifesto'] ?? null,
            $data['photo_path'] ?? null,
            $data['approval_status'] ?? 'approved',
            (int)($data['display_order'] ?? 0)
        ]);
    }

    public function update(int $id, array $data): bool
    {
        $userId = array_key_exists('user_id', $data)
            ? (!empty($data['user_id']) ? (int)$data['user_id'] : null)
            : null;

        if (array_key_exists('user_id', $data)) {
            $sql = "UPDATE candidates
                    SET user_id = ?,
                        name = ?,
                        manifesto = ?,
                        photo_path = COALESCE(?, photo_path),
                        approval_status = ?,
                        display_order = ?
                    WHERE id = ?";

            return $this->db->mutate($sql, [
                $userId,
                $data['name'],
                $data['manifesto'] ?? null,
                $data['photo_path'] ?? null,
                $data['approval_status'] ?? 'approved',
                (int)($data['display_order'] ?? 0),
                $id
            ]) >= 0;
        }

        $sql = "UPDATE candidates
                SET name = ?,
                    manifesto = ?,
                    photo_path = COALESCE(?, photo_path),
                    approval_status = ?,
                    display_order = ?
                WHERE id = ?";

        return $this->db->mutate($sql, [
            $data['name'],
            $data['manifesto'] ?? null,
            $data['photo_path'] ?? null,
            $data['approval_status'] ?? 'approved',
            (int)($data['display_order'] ?? 0),
            $id
        ]) >= 0;
    }

    public function updateStatus(int $id, string $status): bool
    {
        $sql = "UPDATE candidates SET approval_status = ? WHERE id = ?";
        return $this->db->mutate($sql, [$status, $id], 'si') > 0;
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM candidates WHERE id = ?";
        return $this->db->mutate($sql, [$id], 'i') > 0;
    }

    public function getAllApprovedWithElections(?string $search = null, ?string $category = null, ?int $limit = null, int $offset = 0): array
    {
        $params = [];
        $types = '';
        $where = ["c.approval_status = 'approved'", "e.status IN ('scheduled', 'open', 'closed', 'results_published')"];

        if (!empty($category) && $category !== 'all') {
            $where[] = "e.category = ?";
            $params[] = $category;
            $types .= 's';
        }

        if (!empty($search)) {
            $term = '%' . $search . '%';
            $where[] = "(c.name LIKE ? OR c.manifesto LIKE ? OR e.title LIKE ? OR e.position LIKE ?)";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $types .= 'ssss';
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT c.*, e.title as election_title, e.position, e.category, e.department, e.status as election_status,
                       u.identifier as user_identifier, u.department as user_department
                FROM candidates c
                JOIN elections e ON c.election_id = e.id
                LEFT JOIN users u ON c.user_id = u.id
                WHERE {$whereClause}
                ORDER BY e.opens_at DESC, c.display_order ASC, c.name ASC";

        if ($limit !== null) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            $types .= 'ii';
        }

        return $this->db->fetchAll($sql, $params, $types);
    }

    public function countApprovedWithElections(?string $search = null, ?string $category = null): int
    {
        $params = [];
        $types = '';
        $where = ["c.approval_status = 'approved'", "e.status IN ('scheduled', 'open', 'closed', 'results_published')"];

        if (!empty($category) && $category !== 'all') {
            $where[] = "e.category = ?";
            $params[] = $category;
            $types .= 's';
        }

        if (!empty($search)) {
            $term = '%' . $search . '%';
            $where[] = "(c.name LIKE ? OR c.manifesto LIKE ? OR e.title LIKE ? OR e.position LIKE ?)";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $types .= 'ssss';
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT COUNT(*)
                FROM candidates c
                JOIN elections e ON c.election_id = e.id
                LEFT JOIN users u ON c.user_id = u.id
                WHERE {$whereClause}";

        return (int)$this->db->fetchValue($sql, $params, $types);
    }
}
