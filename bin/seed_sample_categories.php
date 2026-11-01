<?php
/**
 * VerityVote - Ensure all 8 categories have active/discoverable elections
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;

$db = Database::getInstance();

$catsNeeded = [
    'class_rep' => [
        'title' => 'BCA Class Representative Election 2026',
        'position' => 'Class Representative',
        'department' => 'BCA',
        'desc' => 'Annual representative election for the Bachelor of Computer Applications semester delegation.',
        'status' => 'open'
    ],
    'university' => [
        'title' => 'University Academic Senate Delegate 2026',
        'position' => 'Senate Delegate',
        'department' => 'University Senate',
        'desc' => 'Collegiate delegation to the Central University Academic Council and Senate.',
        'status' => 'open'
    ]
];

foreach ($catsNeeded as $catKey => $info) {
    $existing = $db->fetchOne("SELECT id FROM elections WHERE category = ?", [$catKey]);
    if (!$existing) {
        $now = date('Y-m-d H:i:s');
        $end = date('Y-m-d H:i:s', time() + 86400 * 3);
        $db->execute(
            "INSERT INTO elections (title, position, description, category, department, opens_at, closes_at, status, results_visibility, created_by, created_at, updated_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'live_provisional', 1, ?, ?)",
            [$info['title'], $info['position'], $info['desc'], $catKey, $info['department'], $now, $end, $info['status'], $now, $now]
        );
        $elId = (int)$db->lastInsertId();
        echo "Created sample election for {$catKey} (ID: {$elId})\n";

        // Seed 2 candidates
        $db->execute(
            "INSERT INTO candidates (election_id, name, manifesto, approval_status, created_at) VALUES 
             (?, 'Aarav Sharma', 'Committed to academic support, peer mentorship, and transparent communications.', 'approved', ?),
             (?, 'Ananya Verma', 'Advocating for updated lab resources, extended library hours, and student workshops.', 'approved', ?)",
            [$elId, $now, $elId, $now]
        );

        // Seed eligibility for existing users
        $users = $db->fetchAll("SELECT id FROM users LIMIT 25");
        foreach ($users as $u) {
            $db->execute("INSERT IGNORE INTO election_eligibility (election_id, user_id, assigned_at) VALUES (?, ?, ?)", [$elId, $u['id'], $now]);
        }
    } else {
        echo "Category {$catKey} already has election ID {$existing['id']}\n";
    }
}

$summary = $db->fetchAll("SELECT category, COUNT(*) as cnt FROM elections GROUP BY category ORDER BY cnt DESC");
echo "\nFinal Category Distribution across all 8 Categories:\n";
foreach ($summary as $s) {
    echo "• {$s['category']}: {$s['cnt']} elections\n";
}
