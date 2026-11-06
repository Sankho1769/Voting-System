<?php
namespace App\Repositories;

use App\Core\Database;
use RuntimeException;

/**
 * Voting Engine Data Access and Transaction Coordinator
 * Guarantees ACID atomicity, double-vote prevention, and ballot secrecy.
 */
class VotingRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function hasParticipated(int $electionId, int $userId): bool
    {
        $val = $this->db->fetchValue(
            "SELECT id FROM election_participation WHERE election_id = ? AND user_id = ?",
            [$electionId, $userId],
            'ii'
        );
        return $val !== null;
    }

    public function getParticipation(int $electionId, int $userId): ?array
    {
        return $this->db->fetchOne(
            "SELECT id, election_id, user_id, voted_at, confirmation_hash 
             FROM election_participation 
             WHERE election_id = ? AND user_id = ?",
            [$electionId, $userId],
            'ii'
        );
    }

    /**
     * Submit an atomic, transaction-safe ballot
     *
     * @param int $electionId
     * @param int $candidateId
     * @param int $userId
     * @param string $hmacSecret
     * @return string Cryptographic confirmation receipt hash
     * @throws RuntimeException on validation failure or concurrency conflict
     */
    public function recordBallot(int $electionId, int $candidateId, int $userId, string $hmacSecret): string
    {
        // ARCHITECTURAL INVARIANT (Phase 12): Electoral decision gates and ballot writes
        // MUST NEVER READ FROM CACHE. Authoritative ACID transactions are mandatory.
        $maxAttempts = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            // Idempotency check on retry: If a prior attempt committed before connection drop,
            // return existing cryptographic receipt rather than throwing or failing.
            if ($attempt > 1) {
                $existing = $this->getParticipation($electionId, $userId);
                if ($existing !== null && !empty($existing['confirmation_hash'])) {
                    return $existing['confirmation_hash'];
                }
            }

            $this->db->beginTransaction();

            try {
                // 1. Verify election is open and server time is within window (row locked)
                $election = $this->db->fetchOne(
                    "SELECT id, status, opens_at, closes_at FROM elections WHERE id = ? LOCK IN SHARE MODE",
                    [$electionId],
                    'i'
                );

                if (!$election) {
                    throw new RuntimeException("Election does not exist.");
                }

                if ($election['status'] !== 'open') {
                    throw new RuntimeException("Election is not currently open for voting (Current status: {$election['status']}).");
                }

                $now = time();
                $opensAt = strtotime($election['opens_at']);
                $closesAt = strtotime($election['closes_at']);

                if ($now < $opensAt) {
                    throw new RuntimeException("Voting has not yet opened for this election.");
                }

                if ($now > $closesAt) {
                    throw new RuntimeException("Voting has closed for this election.");
                }

                // 2. Verify candidate belongs to this election and is approved
                $candidate = $this->db->fetchOne(
                    "SELECT id, election_id, approval_status FROM candidates WHERE id = ? AND election_id = ? LOCK IN SHARE MODE",
                    [$candidateId, $electionId],
                    'ii'
                );

                if (!$candidate) {
                    throw new RuntimeException("Selected candidate does not contest in this election.");
                }

                if ($candidate['approval_status'] !== 'approved') {
                    throw new RuntimeException("Selected candidate is not approved for this election.");
                }

                // 3. Verify user is active, has authorized voter role, and has permanent password
                $user = $this->db->fetchOne(
                    "SELECT id, status, role, must_change_password FROM users WHERE id = ? LOCK IN SHARE MODE",
                    [$userId],
                    'i'
                );

                if (!$user || $user['status'] !== 'active') {
                    throw new RuntimeException("User account is inactive, pending approval, suspended, or disabled.");
                }

                if (!in_array($user['role'], ['student', 'staff', 'candidate'], true)) {
                    throw new RuntimeException("Unauthorized: Only student, staff, and candidate accounts are authorized to cast ballots.");
                }

                if ((bool)$user['must_change_password']) {
                    throw new RuntimeException("You must change your temporary password before casting a vote.");
                }

                // 4. Verify user is on the eligibility whitelist (row locked FOR UPDATE to serialize per voter)
                $eligible = $this->db->fetchOne(
                    "SELECT id FROM election_eligibility WHERE election_id = ? AND user_id = ? FOR UPDATE",
                    [$electionId, $userId],
                    'ii'
                );

                if (!$eligible) {
                    throw new RuntimeException("You are not eligible to vote in this election.");
                }

                // 5. Verify user has not already participated
                $existingParticipation = $this->db->fetchOne(
                    "SELECT id, confirmation_hash FROM election_participation WHERE election_id = ? AND user_id = ?",
                    [$electionId, $userId],
                    'ii'
                );

                if ($existingParticipation) {
                    throw new RuntimeException("Ballot rejected: You have already cast your vote in this election.");
                }

                // 6. Generate cryptographic confirmation receipt
                $receipt = hash_hmac(
                    'sha256',
                    "VERITY-ELECT#{$electionId}-USER#{$userId}-TIME#" . microtime(true) . bin2hex(random_bytes(8)),
                    $hmacSecret
                );

                // 7. Insert participation record (Protected by UNIQUE KEY uk_participation_election_user)
                $this->db->execute(
                    "INSERT INTO election_participation (election_id, user_id, confirmation_hash, voted_at) VALUES (?, ?, ?, NOW())",
                    [$electionId, $userId, $receipt],
                    'iis'
                );

                // 8. Insert anonymous ballot into votes table
                // Notice: user_id is NOT stored here, separating participation identity from voter choice!
                $this->db->execute(
                    "INSERT INTO votes (election_id, candidate_id, cast_at) VALUES (?, ?, NOW())",
                    [$electionId, $candidateId],
                    'ii'
                );

                // 9. Commit atomic transaction
                $this->db->commit();

                return $receipt;
            } catch (\Throwable $e) {
                $this->db->rollback();

                $isDeadlock = strpos($e->getMessage(), 'Deadlock') !== false 
                           || strpos($e->getMessage(), '1213') !== false
                           || strpos($e->getMessage(), 'Lock wait timeout') !== false;

                if ($isDeadlock && $attempt < $maxAttempts) {
                    // Exponential backoff with random jitter: (2^attempt * 10ms) + rand(5ms, 25ms)
                    $backoffMicros = (int)(pow(2, $attempt) * 10000 + random_int(5000, 25000));
                    usleep($backoffMicros);
                    continue;
                }

                if (strpos($e->getMessage(), 'Duplicate entry') !== false || strpos($e->getMessage(), '1062') !== false) {
                    throw new RuntimeException("Ballot rejected: You have already cast your vote in this election.", 0, $e);
                }

                if ($isDeadlock) {
                    throw new RuntimeException("Ballot rejected: Concurrency conflict detected while recording vote. Please try again.", 0, $e);
                }

                throw $e;
            }
        }

        throw new RuntimeException("Ballot submission timed out under high concurrency.");
    }

    /**
     * Compute aggregate results for an election
     */
    public function getResults(int $electionId): array
    {
        $sql = "SELECT c.id as candidate_id, c.name as candidate_name, c.photo_path, c.manifesto,
                       COUNT(v.id) as vote_count
                FROM candidates c
                LEFT JOIN votes v ON c.id = v.candidate_id AND v.election_id = ?
                WHERE c.election_id = ? AND c.approval_status = 'approved'
                GROUP BY c.id, c.name, c.photo_path, c.manifesto
                ORDER BY vote_count DESC, c.name ASC";

        $candidateResults = $this->db->fetchAll($sql, [$electionId, $electionId], 'ii');

        $totalValidBallots = (int)$this->db->fetchValue(
            "SELECT COUNT(*) FROM votes WHERE election_id = ?",
            [$electionId],
            'i'
        );

        $eligibleVotersCount = (int)$this->db->fetchValue(
            "SELECT COUNT(*) FROM election_eligibility WHERE election_id = ?",
            [$electionId],
            'i'
        );

        $participationCount = (int)$this->db->fetchValue(
            "SELECT COUNT(*) FROM election_participation WHERE election_id = ?",
            [$electionId],
            'i'
        );

        // Calculate percentages and detect winners / ties
        $maxVotes = -1;
        $winnerCount = 0;
        $winner = null;
        $isTie = false;

        $results = [];
        foreach ($candidateResults as $cand) {
            $votes = (int)$cand['vote_count'];
            $percentage = $totalValidBallots > 0 
                ? round(($votes / $totalValidBallots) * 100, 2) 
                : 0.0;

            if ($votes > $maxVotes) {
                $maxVotes = $votes;
                $winnerCount = 1;
                $winner = $cand;
            } elseif ($votes === $maxVotes && $votes > 0) {
                $winnerCount++;
            }

            $cand['percentage'] = $percentage;
            $results[] = $cand;
        }

        if ($winnerCount > 1 && $maxVotes > 0) {
            $isTie = true;
            $winner = null;
        }

        $turnoutPercentage = $eligibleVotersCount > 0 
            ? round(($participationCount / $eligibleVotersCount) * 100, 2) 
            : 0.0;

        return [
            'candidates' => $results,
            'total_ballots' => $totalValidBallots,
            'participation_count' => $participationCount,
            'eligible_voters' => $eligibleVotersCount,
            'turnout_percentage' => $turnoutPercentage,
            'is_tie' => $isTie,
            'winner' => $winner,
            'has_votes' => $totalValidBallots > 0
        ];
    }
}
