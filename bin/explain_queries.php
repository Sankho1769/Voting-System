<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
use App\Core\Database;

$db = Database::getInstance();

echo "Running EXPLAIN Analysis on Core Queries BEFORE Migration 006:\n\n";

$queries = [
    'Candidates by Election (Sorting)' => "EXPLAIN SELECT c.*, u.identifier as user_identifier, u.department as user_department
             FROM candidates c 
             LEFT JOIN users u ON c.user_id = u.id
             WHERE c.election_id = 1 AND c.approval_status = 'approved' 
             ORDER BY c.display_order ASC, c.name ASC",

    'Votes Count by Election & Candidate' => "EXPLAIN SELECT candidate_id, COUNT(*) as vote_count 
             FROM votes 
             WHERE election_id = 1 
             GROUP BY candidate_id",

    'Elections Filtered by Status & Category' => "EXPLAIN SELECT * 
             FROM elections 
             WHERE status = 'open' AND category = 'student_council' 
             ORDER BY opens_at DESC",

    'Elections Filtered (Using New Index)' => "EXPLAIN SELECT * 
             FROM elections USE INDEX (idx_elections_status_category_dates)
             WHERE status = 'open' AND category = 'student_council' 
             ORDER BY opens_at DESC",

    'Audit Logs by Entity' => "EXPLAIN SELECT * 
             FROM audit_logs 
             WHERE entity_type = 'election' AND entity_id = '1' 
             ORDER BY created_at DESC LIMIT 50",

    'Audit Logs by User and Date' => "EXPLAIN SELECT * 
             FROM audit_logs 
             WHERE user_id = 1 
             ORDER BY created_at DESC LIMIT 50"
];

foreach ($queries as $label => $sql) {
    echo "=== {$label} ===\n";
    $rows = $db->fetchAll($sql);
    foreach ($rows as $r) {
        printf("Table: %-12s | Type: %-8s | Possible Keys: %-30s | Key: %-25s | Rows: %-5d | Extra: %s\n",
            $r['table'] ?? '',
            $r['type'] ?? '',
            $r['possible_keys'] ?? 'NULL',
            $r['key'] ?? 'NULL',
            (int)($r['rows'] ?? 0),
            $r['Extra'] ?? ''
        );
    }
    echo "\n";
}
