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

        $userId = !empty($filters['user_id']) ? (int)$filters['user_id'] : null;

        if (!empty($filters['status'])) {
            $where[] = "e.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }

        if (!empty($filters['category']) && $filters['category'] !== 'all') {
            $where[] = "e.category = ?";
            $params[] = $filters['category'];
            $types .= 's';
        }

        if (!empty($filters['department']) && $filters['department'] !== 'all') {
            $where[] = "(e.department = ? OR e.department IS NULL)";
            $params[] = $filters['department'];
            $types .= 's';
        }

        if (!empty($filters['scope'])) {
            switch ($filters['scope']) {
                case 'open':
                case 'live':
                    $where[] = "e.status = 'open'";
                    break;
                case 'upcoming':
                    $where[] = "e.status IN ('scheduled', 'draft')";
                    break;
                case 'completed':
                    $where[] = "e.status IN ('closed', 'results_published')";
                    break;
                case 'results':
                    $where[] = "e.status = 'results_published'";
                    break;
            }
        }

        if (!empty($filters['for_you']) && $userId !== null) {
            $where[] = "ee.id IS NOT NULL";
        }

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $where[] = "(e.title LIKE ? OR e.position LIKE ? OR e.department LIKE ? OR e.category LIKE ?)";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $types .= 'ssss';
        }

        $joinEligibility = '';
        $selectEligibility = '';
        if ($userId !== null) {
            $selectEligibility = ", CASE WHEN ee.id IS NOT NULL THEN 1 ELSE 0 END as is_eligible,
                                  CASE WHEN ep.id IS NOT NULL THEN 1 ELSE 0 END as has_participated,
                                  ep.voted_at as user_voted_at,
                                  ep.confirmation_hash as user_confirmation_hash";
            $joinEligibility = "LEFT JOIN election_eligibility ee ON e.id = ee.election_id AND ee.user_id = {$userId}
                                LEFT JOIN election_participation ep ON e.id = ep.election_id AND ep.user_id = {$userId}";
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT e.*, u.full_name as creator_name,
                       (SELECT COUNT(*) FROM candidates c WHERE c.election_id = e.id AND c.approval_status = 'approved') as approved_candidates_count,
                       (SELECT COUNT(*) FROM election_eligibility eel WHERE eel.election_id = e.id) as eligible_voters_count,
                       (SELECT COUNT(*) FROM election_participation epc WHERE epc.election_id = e.id) as total_votes_cast
                       {$selectEligibility}
                FROM elections e
                LEFT JOIN users u ON e.created_by = u.id
                {$joinEligibility}
                WHERE {$whereClause}
                ORDER BY 
                   CASE 
                     WHEN e.status = 'open' THEN 1
                     WHEN e.status = 'scheduled' THEN 2
                     WHEN e.status = 'results_published' THEN 3
                     WHEN e.status = 'closed' THEN 4
                     ELSE 5
                   END,
                   e.opens_at ASC";

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
        $category = !empty($data['category']) ? $data['category'] : 'student_council';
        $department = !empty($data['department']) ? $data['department'] : null;

        $sql = "INSERT INTO elections 
                (title, position, category, department, description, status, opens_at, closes_at, results_visibility, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        return $this->db->insert($sql, [
            $data['title'],
            $data['position'],
            $category,
            $department,
            $data['description'] ?? null,
            $data['status'] ?? 'draft',
            $data['opens_at'],
            $data['closes_at'],
            $data['results_visibility'] ?? 'live_provisional',
            (int)$data['created_by']
        ], 'sssssssssi');
    }

    public function update(int $id, array $data): bool
    {
        $category = !empty($data['category']) ? $data['category'] : 'student_council';
        $department = !empty($data['department']) ? $data['department'] : null;

        $sql = "UPDATE elections 
                SET title = ?, 
                    position = ?, 
                    category = ?, 
                    department = ?, 
                    description = ?, 
                    opens_at = ?, 
                    closes_at = ?, 
                    results_visibility = ? 
                WHERE id = ?";

        return $this->db->mutate($sql, [
            $data['title'],
            $data['position'],
            $category,
            $department,
            $data['description'] ?? null,
            $data['opens_at'],
            $data['closes_at'],
            $data['results_visibility'] ?? 'live_provisional',
            $id
        ], 'ssssssssi') >= 0;
    }

    public function getCategoriesSummary(): array
    {
        $sql = "SELECT category, COUNT(*) as total_count,
                       SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_count,
                       SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as upcoming_count,
                       SUM(CASE WHEN status IN ('closed', 'results_published') THEN 1 ELSE 0 END) as completed_count
                FROM elections
                GROUP BY category
                ORDER BY total_count DESC";
        return $this->db->fetchAll($sql);
    }

    public function getDepartments(): array
    {
        $sql = "SELECT DISTINCT department FROM (
                    SELECT department FROM elections WHERE department IS NOT NULL AND department != ''
                    UNION
                    SELECT department FROM users WHERE department IS NOT NULL AND department != ''
                ) as depts ORDER BY department ASC";
        $rows = $this->db->fetchAll($sql);
        return array_map(fn($r) => $r['department'], $rows);
    }

    public function getStats(): array
    {
        $this->autoSyncStatuses();
        $totalElections = (int)$this->db->fetchValue("SELECT COUNT(*) FROM elections");
        $activeElections = (int)$this->db->fetchValue("SELECT COUNT(*) FROM elections WHERE status = 'open'");
        $upcomingElections = (int)$this->db->fetchValue("SELECT COUNT(*) FROM elections WHERE status = 'scheduled'");
        $totalCandidates = (int)$this->db->fetchValue("SELECT COUNT(*) FROM candidates WHERE approval_status = 'approved'");
        $totalVotes = (int)$this->db->fetchValue("SELECT COUNT(*) FROM votes");
        $totalVoters = (int)$this->db->fetchValue("SELECT COUNT(*) FROM users WHERE role IN ('student', 'staff', 'candidate')");

        return [
            'total_elections' => $totalElections,
            'active_elections' => $activeElections,
            'upcoming_elections' => $upcomingElections,
            'total_candidates' => $totalCandidates,
            'total_votes' => $totalVotes,
            'total_voters' => $totalVoters
        ];
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
