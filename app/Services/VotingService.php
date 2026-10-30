<?php
namespace App\Services;

use App\Core\Logger;
use App\Repositories\VotingRepository;
use RuntimeException;

/**
 * High-Integrity Voting Engine Coordinator
 */
class VotingService
{
    private VotingRepository $votingRepo;
    private string $hmacSecret;

    public function __construct(?VotingRepository $votingRepo = null, ?string $hmacSecret = null)
    {
        $this->votingRepo = $votingRepo ?? new VotingRepository();

        if ($hmacSecret === null) {
            $configFile = dirname(__DIR__, 2) . '/config/config.php';
            if (!file_exists($configFile)) {
                $configFile = dirname(__DIR__, 2) . '/config/config.example.php';
            }
            $config = require $configFile;
            $this->hmacSecret = $config['security']['hmac_secret'] ?? 'fallback_verity_vote_hmac_secret_2026';
        } else {
            $this->hmacSecret = $hmacSecret;
        }
    }

    public function castVote(int $electionId, int $candidateId, int $userId): array
    {
        try {
            $receipt = $this->votingRepo->recordBallot($electionId, $candidateId, $userId, $this->hmacSecret);

            // Audit participation ONLY (never logs candidate choice to preserve ballot secrecy)
            Logger::audit('BALLOT_CAST_ACCEPTED', 'election', (string)$electionId, [
                'receipt_prefix' => substr($receipt, 0, 8) . '...'
            ], $userId);

            return [
                'success' => true,
                'confirmation_receipt' => $receipt,
                'voted_at' => date('Y-m-d H:i:s')
            ];
        } catch (RuntimeException $e) {
            Logger::audit('BALLOT_REJECTED', 'election', (string)$electionId, [
                'reason' => $e->getMessage()
            ], $userId);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        } catch (\Throwable $e) {
            Logger::fileLog('CRITICAL', "Ballot processing exception: " . $e->getMessage());

            return [
                'success' => false,
                'error' => 'An unexpected internal error occurred while recording your ballot. Please try again.'
            ];
        }
    }
}
