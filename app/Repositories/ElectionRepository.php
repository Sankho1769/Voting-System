<?php
namespace App\Repositories;

use App\Core\Database;

/**
 * Election Data Access Repository
 */
class ElectionRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        $this->autoSyncStatuses();
        return $this->db->fetchOne(
            "SELECT e.*, u.full_name as creator_name,
                    (SELECT COUNT(*) FROM candidates c WHERE c.election_id = e.id AND c.approval_status = 'approved') as approved_candidates_count,
                    (SELECT COUNT(*) FROM election_eligibility ee WHERE ee.election_id = e.id) as eligible_voters_count,
                    (SELECT COUNT(*) FROM election_participation ep WHERE ep.election_id = e.id) as total_votes_cast
             FROM elections e
             LEFT JOIN users u ON e.created_by = u.id
             WHERE e.id = ?",
            [$id],
            'i'
        );
    }

    public function getAll(array $filters = []): array
    {
        $this->autoSyncStatuses();
        $params = [];
        $types = '';
        $where = ['1=1'];

        if (!empty($filters['status'])) {
            $where[] = "e.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $where[] = "(e.title LIKE ? OR e.position LIKE ?)";
            $params[] = $term;
            $params[] = $term;
            $types .= 'ss';
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT e.*, u.full_name as creator_name,
                       (SELECT COUNT(*) FROM candidates c WHERE c.election_id = e.id AND c.approval_status = 'approved') as approved_candidates_count,
                       (SELECT COUNT(*) FROM election_eligibility ee WHERE ee.election_id = e.id) as eligible_voters_count,
                       (SELECT COUNT(*) FROM election_participation ep WHERE ep.election_id = e.id) as total_votes_cast
                FROM elections e
                LEFT JOIN users u ON e.created_by = u.id
                WHERE {$whereClause}
                ORDER BY e.opens_at DESC";

        return $this->db->fetchAll($sql, $params, $types);
    }

    public function getEligibleForUser(int $userId): array
    {
        $this->autoSyncStatuses();
        $sql = "SELECT e.*, u.full_name as creator_name,
                       (SELECT COUNT(*) FROM candidates c WHERE c.election_id = e.id AND c.approval_status = 'approved') as approved_candidates_count,
                       ep.voted_at,
                       ep.confirmation_hash,
                       CASE WHEN ep.id IS NOT NULL THEN 1 ELSE 0 END as has_participated
                FROM elections e
                JOIN election_eligibility ee ON e.id = ee.election_id AND ee.user_id = ?
                LEFT JOIN users u ON e.created_by = u.id
                LEFT JOIN election_participation ep ON e.id = ep.election_id AND ep.user_id = ?
                WHERE e.status IN ('scheduled', 'open', 'closed', 'results_published')
                ORDER BY 
                   CASE 
                     WHEN e.status = 'open' AND ep.id IS NULL THEN 1
                     WHEN e.status = 'open' AND ep.id IS NOT NULL THEN 2
                     WHEN e.status = 'scheduled' THEN 3
                     WHEN e.status = 'results_published' THEN 4
                     ELSE 5
                   END,
                   e.opens_at ASC";

        return $this->db->fetchAll($sql, [$userId, $userId], 'ii');
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO elections 
                (title, position, description, status, opens_at, closes_at, results_visibility, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        return $this->db->insert($sql, [
            $data['title'],
            $data['position'],
            $data['description'] ?? null,
            $data['status'] ?? 'draft',
            $data['opens_at'],
            $data['closes_at'],
            $data['results_visibility'] ?? 'live_provisional',
            (int)$data['created_by']
        ], 'sssssssi');
    }

    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE elections 
                SET title = ?, 
                    position = ?, 
                    description = ?, 
                    opens_at = ?, 
                    closes_at = ?, 
                    results_visibility = ? 
                WHERE id = ?";

        return $this->db->mutate($sql, [
            $data['title'],
            $data['position'],
            $data['description'] ?? null,
            $data['opens_at'],
            $data['closes_at'],
            $data['results_visibility'] ?? 'live_provisional',
            $id
        ], 'ssssssi') >= 0;
    }

    public function updateStatus(int $id, string $status): bool
    {
        $sql = "UPDATE elections SET status = ? WHERE id = ?";
        return $this->db->mutate($sql, [$status, $id], 'si') > 0;
    }

    /**
     * Automatic synchronization of election statuses based on real server time
     */
    public function autoSyncStatuses(): void
    {
        // Scheduled -> Open (only if at least 2 approved candidates exist)
        $scheduledElections = $this->db->fetchAll(
            "SELECT id FROM elections WHERE status = 'scheduled' AND opens_at <= NOW() AND closes_at > NOW()"
        );

        foreach ($scheduledElections as $e) {
            $candidateCount = (int)$this->db->fetchValue(
                "SELECT COUNT(*) FROM candidates WHERE election_id = ? AND approval_status = 'approved'",
                [$e['id']],
                'i'
            );
            if ($candidateCount >= 2) {
                $this->db->mutate("UPDATE elections SET status = 'open' WHERE id = ?", [$e['id']], 'i');
            }
        }

        // Open -> Closed (when closes_at has passed)
        $this->db->mutate(
            "UPDATE elections SET status = 'closed' WHERE status = 'open' AND closes_at <= NOW()"
        );
    }
}
