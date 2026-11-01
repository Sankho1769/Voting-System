<?php
/**
 * VerityVote - Test Account Provisioning & Removal Tool
 * Secure CLI utility to provision or remove local testing accounts across all three portals.
 *
 * Usage:
 *   Provision: php bin/seed_test_accounts.php [login_id] [password]
 *   Remove:    php bin/seed_test_accounts.php --remove [login_id]
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    die("This provisioning utility can only be executed from the CLI.\n");
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Core\Logger;
use App\Helpers\Sanitizer;

echo "========================================================\n";
echo " VerityVote - Test Account Provisioning Tool\n";
echo "========================================================\n\n";

$db = Database::getInstance();

// Parse arguments
$isRemove = false;
$loginId = 'Shubham';
$password = null;

$args = array_slice($argv, 1);
if (!empty($args)) {
    if ($args[0] === '--remove' || $args[0] === '-r') {
        $isRemove = true;
        $loginId = $args[1] ?? 'Shubham';
    } else {
        $loginId = $args[0];
        $password = $args[1] ?? null;
    }
}

$cleanId = Sanitizer::cleanIdentifier($loginId);
$adminIdentifier = 'ADMIN_' . $cleanId;
$voterIdentifier = $cleanId;
$testElectionTitle = "[Test] Collegiate Council Representative ({$loginId})";

// =====================================================================
// REMOVAL WORKFLOW
// =====================================================================
if ($isRemove) {
    echo "Purging test account data for identifier '{$loginId}'...\n";

    // 1. Find user accounts
    $adminUser = $db->fetchOne("SELECT id FROM users WHERE identifier = ?", [$adminIdentifier], 's');
    $voterUser = $db->fetchOne("SELECT id FROM users WHERE identifier = ?", [$voterIdentifier], 's');
    $testElection = $db->fetchOne("SELECT id FROM elections WHERE title = ?", [$testElectionTitle], 's');

    // 2. Remove mappings
    $db->mutate("DELETE FROM portal_identifier_mappings WHERE portal_identifier = ? OR portal_identifier = ?", [
        $loginId,
        $cleanId
    ], 'ss');
    echo "  [OK] Removed portal_identifier_mappings.\n";

    // 3. Remove election data if exists
    if ($testElection) {
        $eId = (int)$testElection['id'];
        $db->mutate("DELETE FROM votes WHERE election_id = ?", [$eId], 'i');
        $db->mutate("DELETE FROM election_participation WHERE election_id = ?", [$eId], 'i');
        $db->mutate("DELETE FROM election_eligibility WHERE election_id = ?", [$eId], 'i');
        $db->mutate("DELETE FROM candidates WHERE election_id = ?", [$eId], 'i');
        $db->mutate("DELETE FROM elections WHERE id = ?", [$eId], 'i');
        echo "  [OK] Removed test election '{$testElectionTitle}' and related ballots.\n";
    }

    // 4. Remove candidates linked to voterUser
    if ($voterUser) {
        $db->mutate("DELETE FROM candidates WHERE user_id = ?", [(int)$voterUser['id']], 'i');
        $db->mutate("DELETE FROM election_eligibility WHERE user_id = ?", [(int)$voterUser['id']], 'i');
        $db->mutate("DELETE FROM election_participation WHERE user_id = ?", [(int)$voterUser['id']], 'i');
        $db->mutate("DELETE FROM users WHERE id = ?", [(int)$voterUser['id']], 'i');
        echo "  [OK] Removed institutional voter/candidate user '{$voterIdentifier}'.\n";
    }

    // 5. Remove admin user
    if ($adminUser) {
        $db->mutate("DELETE FROM users WHERE id = ?", [(int)$adminUser['id']], 'i');
        echo "  [OK] Removed administrator user '{$adminIdentifier}'.\n";
    }

    Logger::audit('TEST_ACCOUNTS_REMOVED', 'system', $cleanId, [
        'login_id' => $loginId,
        'action' => 'purged_test_data'
    ]);

    echo "\nTest accounts and associated test data removed cleanly.\n";
    exit(0);
}

// =====================================================================
// PROVISIONING WORKFLOW
// =====================================================================
if (empty($password)) {
    echo "Password not supplied in CLI argument.\n";
    echo "Enter password for test account '{$loginId}': ";
    $password = trim((string)fgets(STDIN));
    if (empty($password)) {
        die("Error: Password cannot be empty.\n");
    }
}

if (strlen($password) < 8) {
    die("Error: Password must be at least 8 characters long.\n");
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// 1. Provision Administrator Account
$adminUser = $db->fetchOne("SELECT id FROM users WHERE identifier = ?", [$adminIdentifier], 's');
if ($adminUser) {
    $adminId = (int)$adminUser['id'];
    $db->mutate(
        "UPDATE users SET password_hash = ?, status = 'active', must_change_password = 0, role = 'admin' WHERE id = ?",
        [$passwordHash, $adminId],
        'si'
    );
    echo "  [OK] Existing Administrator account '{$adminIdentifier}' (ID: {$adminId}) updated.\n";
} else {
    $adminId = $db->insert(
        "INSERT INTO users (identifier, role, full_name, email, department, password_hash, must_change_password, status)
         VALUES (?, 'admin', ?, ?, 'System Administration', ?, 0, 'active')",
        [
            $adminIdentifier,
            "{$loginId} (Administrator)",
            "admin." . strtolower($cleanId) . "@college.edu",
            $passwordHash
        ]
    );
    echo "  [OK] Administrator account created (Identifier: '{$adminIdentifier}', ID: {$adminId}).\n";
}

// 2. Provision Institutional Voter & Candidate Account
$voterUser = $db->fetchOne("SELECT id FROM users WHERE identifier = ?", [$voterIdentifier], 's');
if ($voterUser) {
    $voterId = (int)$voterUser['id'];
    $db->mutate(
        "UPDATE users SET password_hash = ?, status = 'active', must_change_password = 0 WHERE id = ?",
        [$passwordHash, $voterId],
        'si'
    );
    echo "  [OK] Existing Voter/Candidate account '{$voterIdentifier}' (ID: {$voterId}) updated.\n";
} else {
    $voterId = $db->insert(
        "INSERT INTO users (identifier, role, full_name, email, department, academic_year, password_hash, must_change_password, status)
         VALUES (?, 'student', ?, ?, 'Computer Science', '2024-2027', ?, 0, 'active')",
        [
            $voterIdentifier,
            "{$loginId} Banerjee",
            strtolower($cleanId) . "@college.edu",
            $passwordHash
        ]
    );
    echo "  [OK] Institutional account created (Identifier: '{$voterIdentifier}', ID: {$voterId}).\n";
}

// 3. Configure Portal-Specific Identifier Mappings
// Maps visible login ID across the 3 distinct portal namespaces
$mappings = [
    ['portal' => 'admin',     'identifier' => $cleanId, 'user_id' => $adminId],
    ['portal' => 'candidate', 'identifier' => $cleanId, 'user_id' => $voterId],
    ['portal' => 'voter',     'identifier' => $cleanId, 'user_id' => $voterId],
];

foreach ($mappings as $m) {
    $db->mutate(
        "INSERT INTO portal_identifier_mappings (portal, portal_identifier, user_id)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)",
        [$m['portal'], $m['identifier'], $m['user_id']],
        'ssi'
    );
    // Also store exact casing if different from cleanId
    if ($loginId !== $cleanId) {
        $db->mutate(
            "INSERT INTO portal_identifier_mappings (portal, portal_identifier, user_id)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)",
            [$m['portal'], $loginId, $m['user_id']],
            'ssi'
        );
    }
}
echo "  [OK] Portal identifier mappings registered for '{$loginId}' (Admin -> User {$adminId}, Candidate & Voter -> User {$voterId}).\n";

// 4. Provision Dedicated Test Election
$testElection = $db->fetchOne("SELECT id FROM elections WHERE title = ?", [$testElectionTitle], 's');
$now = time();
$opensAt = date('Y-m-d H:i:s', $now - 3600);
$closesAt = date('Y-m-d H:i:s', $now + 86400 * 7);

if ($testElection) {
    $electionId = (int)$testElection['id'];
    $db->mutate(
        "UPDATE elections SET status = 'open', opens_at = ?, closes_at = ?, results_visibility = 'live_provisional' WHERE id = ?",
        [$opensAt, $closesAt, $electionId],
        'ssi'
    );
    echo "  [OK] Dedicated test election updated (ID: {$electionId}).\n";
} else {
    $electionId = $db->insert(
        "INSERT INTO elections (title, position, description, status, opens_at, closes_at, results_visibility, created_by)
         VALUES (?, 'Council Representative', 'Dedicated test election for VerityVote portal and candidate dashboard verification.', 'open', ?, ?, 'live_provisional', ?)",
        [
            $testElectionTitle,
            $opensAt,
            $closesAt,
            $adminId
        ]
    );
    echo "  [OK] Dedicated test election created (Title: '{$testElectionTitle}', ID: {$electionId}).\n";
}

// 5. Provision Candidates in Test Election
// Candidate 1: Shubham (Linked to voterUser)
$cand1 = $db->fetchOne("SELECT id FROM candidates WHERE election_id = ? AND user_id = ?", [$electionId, $voterId], 'ii');
if ($cand1) {
    $cand1Id = (int)$cand1['id'];
    $db->mutate(
        "UPDATE candidates SET name = ?, approval_status = 'approved', manifesto = ? WHERE id = ?",
        [$loginId, "Dedicated candidate platform for {$loginId} in collegiate council election.", $cand1Id],
        'ssi'
    );
    echo "  [OK] Candidate profile '{$loginId}' updated (Candidate ID: {$cand1Id}).\n";
} else {
    $cand1Id = $db->insert(
        "INSERT INTO candidates (election_id, user_id, name, manifesto, approval_status, display_order)
         VALUES (?, ?, ?, ?, 'approved', 1)",
        [
            $electionId,
            $voterId,
            $loginId,
            "Dedicated candidate platform for {$loginId} in collegiate council election."
        ]
    );
    echo "  [OK] Candidate profile created (Candidate ID: {$cand1Id}, Name: '{$loginId}', Linked User: {$voterId}).\n";
}

// Candidate 2: Companion Opponent (Required so election has >= 2 approved candidates)
$cand2 = $db->fetchOne("SELECT id FROM candidates WHERE election_id = ? AND user_id IS NULL", [$electionId], 'i');
if (!$cand2) {
    $cand2Id = $db->insert(
        "INSERT INTO candidates (election_id, user_id, name, manifesto, approval_status, display_order)
         VALUES (?, NULL, 'Alex Rivera (Opponent)', 'Opponent platform initiatives for collegiate student welfare.', 'approved', 2)",
        [$electionId]
    );
    echo "  [OK] Companion opponent candidate created (Candidate ID: {$cand2Id}).\n";
}

// 6. Whitelist Voter for Test Election
$elig = $db->fetchOne("SELECT id FROM election_eligibility WHERE election_id = ? AND user_id = ?", [$electionId, $voterId], 'ii');
if (!$elig) {
    $db->insert(
        "INSERT INTO election_eligibility (election_id, user_id, assigned_by) VALUES (?, ?, ?)",
        [$electionId, $voterId, $adminId]
    );
    echo "  [OK] Voter eligibility whitelisted for election {$electionId}.\n";
} else {
    echo "  [OK] Voter eligibility verified for election {$electionId}.\n";
}

Logger::audit('TEST_ACCOUNTS_PROVISIONED', 'system', $cleanId, [
    'login_id' => $loginId,
    'admin_user_id' => $adminId,
    'voter_user_id' => $voterId,
    'test_election_id' => $electionId
]);

echo "\n========================================================\n";
echo " Provisioning Complete - Credentials Ready for Testing\n";
echo "========================================================\n\n";

echo "Visible Login Credentials:\n";
echo "  Login ID: {$loginId}\n";
echo "  Password: [PROTECTED - Verified with bcrypt hash]\n\n";

echo "Portal Access Destinations:\n";
echo "  1. Admin Portal:     http://localhost/Baymax_Voting_System/public/login/admin\n";
echo "     -> Authenticates as Administrator (User ID: {$adminId})\n";
echo "     -> Redirects to: /public/admin\n\n";

echo "  2. Candidate Portal: http://localhost/Baymax_Voting_System/public/login/candidate\n";
echo "     -> Authenticates as Candidate (User ID: {$voterId}, Candidate ID: {$cand1Id})\n";
echo "     -> Redirects to: /public/candidate\n\n";

echo "  3. Voter Portal:     http://localhost/Baymax_Voting_System/public/login/voter\n";
echo "     -> Authenticates as Eligible Voter (User ID: {$voterId})\n";
echo "     -> Redirects to: /public/voter\n\n";

echo "To remove these test accounts later, run:\n";
echo "  php bin/seed_test_accounts.php --remove {$loginId}\n";
echo "  or: php bin/remove_test_accounts.php {$loginId}\n\n";
