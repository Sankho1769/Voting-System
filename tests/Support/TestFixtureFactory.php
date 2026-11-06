<?php
/**
 * VerityVote - Test Fixture & Factory System
 * 
 * Provisions dynamic, isolated test records (Admin, Candidate, Voter, Staff, Officer,
 * Test Election, Candidates, Eligibility Whitelists, and Portal Mappings)
 * without hardcoding fixed identities or secrets.
 */

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Database;
use RuntimeException;

class TestFixtureFactory
{
    private static ?array $currentFixtures = null;

    /**
     * Create or retrieve complete set of dynamic fixtures in the specified database.
     */
    public static function createSuiteFixtures(?Database $db = null): array
    {
        $db = $db ?? Database::getInstance();

        $token = bin2hex(random_bytes(3));
        $password = 'TestFixture#Pass_' . bin2hex(random_bytes(4));
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $adminIdent = 'TF_ADM_' . $token;
        $voterIdent = 'TF_VTR_' . $token;
        $staffIdent = 'TF_STF_' . $token;
        $officerIdent = 'TF_OFF_' . $token;
        $visibleLoginId = 'TF_USR_' . $token;

        // 1. Provision Administrator
        $adminId = $db->insert(
            "INSERT INTO users (identifier, role, full_name, email, department, password_hash, must_change_password, status)
             VALUES (?, 'admin', ?, ?, 'System Administration', ?, 0, 'active')",
            [
                $adminIdent,
                "Test Suite Admin {$token}",
                "admin_{$token}@verityvote.test",
                $passwordHash
            ]
        );

        // 2. Provision Unified Institutional Account (Student Voter & Candidate)
        $voterId = $db->insert(
            "INSERT INTO users (identifier, role, full_name, email, department, academic_year, password_hash, must_change_password, status)
             VALUES (?, 'student', ?, ?, 'Computer Science', '2024-2027', ?, 0, 'active')",
            [
                $voterIdent,
                "Test Candidate Voter {$token}",
                "voter_{$token}@verityvote.test",
                $passwordHash
            ]
        );

        // 3. Provision Staff Voter Account
        $staffId = $db->insert(
            "INSERT INTO users (identifier, role, full_name, email, department, password_hash, must_change_password, status)
             VALUES (?, 'staff', ?, ?, 'Academic Council', ?, 0, 'active')",
            [
                $staffIdent,
                "Test Staff Voter {$token}",
                "staff_{$token}@verityvote.test",
                $passwordHash
            ]
        );

        // 4. Provision Election Officer Account
        $officerId = $db->insert(
            "INSERT INTO users (identifier, role, full_name, email, department, password_hash, must_change_password, status)
             VALUES (?, 'election_officer', ?, ?, 'Electoral Board', ?, 0, 'active')",
            [
                $officerIdent,
                "Test Officer {$token}",
                "officer_{$token}@verityvote.test",
                $passwordHash
            ]
        );

        // 5. Configure Portal-Specific Identifier Mappings for Visible Login ID
        $mappings = [
            ['portal' => 'admin',     'identifier' => $visibleLoginId, 'user_id' => $adminId],
            ['portal' => 'candidate', 'identifier' => $visibleLoginId, 'user_id' => $voterId],
            ['portal' => 'voter',     'identifier' => $visibleLoginId, 'user_id' => $voterId],
        ];

        foreach ($mappings as $m) {
            $db->mutate(
                "INSERT INTO portal_identifier_mappings (portal, portal_identifier, user_id)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)",
                [$m['portal'], $m['identifier'], $m['user_id']],
                'ssi'
            );
        }

        // 6. Provision Dedicated Test Election
        $now = time();
        $opensAt = date('Y-m-d H:i:s', $now - 3600);
        $closesAt = date('Y-m-d H:i:s', $now + 86400 * 7);
        $electionTitle = "[Test Suite] Council Representative Election {$token}";

        $electionId = $db->insert(
            "INSERT INTO elections (title, position, category, department, description, status, opens_at, closes_at, results_visibility, created_by)
             VALUES (?, 'Council Representative', 'student_council', 'Computer Science', 'Dedicated automated test election', 'open', ?, ?, 'live_provisional', ?)",
            [
                $electionTitle,
                $opensAt,
                $closesAt,
                $adminId
            ]
        );

        // 7. Provision Approved Candidate (Linked to the unified user account)
        $candId = $db->insert(
            "INSERT INTO candidates (election_id, user_id, name, manifesto, approval_status, display_order)
             VALUES (?, ?, ?, ?, 'approved', 1)",
            [
                $electionId,
                $voterId,
                "Test Candidate Voter {$token}",
                "Committed to institutional student representation for {$token}."
            ]
        );

        // 8. Provision Opponent Candidate
        $opponentId = $db->insert(
            "INSERT INTO candidates (election_id, user_id, name, manifesto, approval_status, display_order)
             VALUES (?, NULL, ?, ?, 'approved', 2)",
            [
                $electionId,
                "Opponent Contender {$token}",
                "Independent representation and campus voice."
            ]
        );

        // 9. Assign Whitelist Eligibility
        $db->mutate(
            "INSERT INTO election_eligibility (election_id, user_id, assigned_by) VALUES (?, ?, ?)",
            [$electionId, $voterId, $adminId],
            'iii'
        );
        $db->mutate(
            "INSERT INTO election_eligibility (election_id, user_id, assigned_by) VALUES (?, ?, ?)",
            [$electionId, $staffId, $adminId],
            'iii'
        );

        self::$currentFixtures = [
            'token' => $token,
            'password' => $password,
            'password_hash' => $passwordHash,
            'visible_id' => $visibleLoginId,
            'admin_id' => $adminId,
            'admin_identifier' => $adminIdent,
            'voter_id' => $voterId,
            'voter_identifier' => $voterIdent,
            'staff_id' => $staffId,
            'staff_identifier' => $staffIdent,
            'officer_id' => $officerId,
            'officer_identifier' => $officerIdent,
            'election_id' => $electionId,
            'election_title' => $electionTitle,
            'candidate_id' => $candId,
            'candidate_id_syntax' => 'CAND-' . $candId,
            'opponent_id' => $opponentId,
        ];

        return self::$currentFixtures;
    }

    /**
     * Get currently cached fixtures, or generate new ones if none exist.
     */
    public static function getSuiteFixtures(?Database $db = null): array
    {
        if (self::$currentFixtures === null) {
            return self::createSuiteFixtures($db);
        }
        return self::$currentFixtures;
    }

    /**
     * Reset cached fixture state.
     */
    public static function reset(): void
    {
        self::$currentFixtures = null;
    }
}
