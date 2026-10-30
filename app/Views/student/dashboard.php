<?php
use App\Core\Auth;
use App\Core\View;
use App\Helpers\Sanitizer;
use App\Helpers\TimeHelper;
$pageTitle = 'Member Elections Portal';
?>

<div class="container">
  <div class="page-header">
    <div class="page-header-title">
      <h1>Institutional Elections</h1>
      <div class="page-header-subtitle">
        Active ballot positions for <strong><?= Sanitizer::e(Auth::user()['full_name']) ?></strong> (ID: <?= Sanitizer::e(Auth::identifier()) ?> &bull; <?= Sanitizer::e(Auth::user()['department'] ?? 'College') ?>)
      </div>
    </div>
    <?php if (Auth::isCandidate()): ?>
      <div>
        <a href="<?= View::url('/candidate') ?>" class="btn btn-secondary btn-sm" style="border-color: var(--color-vermilion); color: var(--color-vermilion);">
          &rarr; Switch to Candidate Portal
        </a>
      </div>
    <?php endif; ?>
  </div>

  <?php if (Auth::isCandidate()): ?>
    <div class="card card-ivory" style="padding: 1rem 1.5rem; margin-bottom: 1.5rem; border-left: 4px solid var(--color-vermilion); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
      <div style="font-size: 0.9rem;">
        <strong style="color: var(--color-vermilion);">Candidate Dual-Access:</strong> You have an active candidate platform. You can monitor your campaign standings from the Candidate Portal.
      </div>
      <a href="<?= View::url('/candidate') ?>" class="btn btn-sm btn-secondary" style="border-color: var(--color-vermilion); color: var(--color-vermilion); padding: 0.25rem 0.75rem;">
        View Candidate Dashboard &rarr;
      </a>
    </div>
  <?php endif; ?>

  <?php if (empty($elections)): ?>
    <div class="card card-ivory" style="text-align: center; padding: 3rem 1.5rem;">
      <h3 style="margin-bottom: 0.5rem;">No Current Eligible Elections</h3>
      <p style="color: var(--color-text-secondary); max-width: 500px; margin: 0 auto;">
        You are not currently enrolled on the voter whitelist for any upcoming or open elections. Eligibility lists are authorized by election officers.
      </p>
    </div>
  <?php else: ?>
    <div class="grid grid-2">
      <?php foreach ($elections as $election): ?>
        <?php
          $status = $election['status'];
          $hasVoted = (bool)($election['has_participated'] ?? false);
          $isOpen = ($status === 'open');
        ?>
        <div class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
          <div>
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.85rem;">
              <span class="badge badge-<?= Sanitizer::e($status) ?>">
                <?= ucfirst(str_replace('_', ' ', $status)) ?>
              </span>
              <?php if ($hasVoted): ?>
                <span class="badge" style="background-color: var(--color-emerald-subtle); color: var(--color-emerald-green); font-weight: 700;">
                  &check; Ballot Submitted
                </span>
              <?php endif; ?>
            </div>

            <h3 style="margin-bottom: 0.25rem;">
              <?= Sanitizer::e($election['title']) ?>
            </h3>
            <div style="font-weight: 600; color: var(--color-emerald-green); font-size: 0.95rem; margin-bottom: 0.75rem;">
              Position: <?= Sanitizer::e($election['position']) ?>
            </div>

            <p style="color: var(--color-text-secondary); font-size: 0.9rem; margin-bottom: 1rem;">
              <?= nl2br(Sanitizer::e($election['description'] ?: 'Official collegiate election.')) ?>
            </p>

            <div style="background-color: var(--color-surface-muted); padding: 0.75rem 1rem; border-radius: var(--radius-sm); font-size: 0.825rem; margin-bottom: 1.25rem; line-height: 1.4;">
              <div><strong>Opens:</strong> <?= TimeHelper::format($election['opens_at']) ?></div>
              <div><strong>Closes:</strong> <?= TimeHelper::format($election['closes_at']) ?></div>
              <div><strong>Approved Contenders:</strong> <?= (int)$election['approved_candidates_count'] ?> candidates</div>
            </div>
          </div>

          <div style="border-top: 1px solid var(--color-border-subtle); padding-top: 1rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
            <?php if ($isOpen && !$hasVoted): ?>
              <a href="<?= View::url('/ballot?id=' . $election['id']) ?>" class="btn btn-primary btn-block">
                Cast Official Ballot &rarr;
              </a>
            <?php elseif ($hasVoted): ?>
              <div style="font-size: 0.85rem; color: var(--color-text-muted);">
                Voted on <?= TimeHelper::format($election['voted_at'] ?? '') ?>
              </div>
              <a href="<?= View::url('/results?id=' . $election['id']) ?>" class="btn btn-secondary btn-sm">
                View Standings
              </a>
            <?php elseif ($status === 'scheduled'): ?>
              <span style="font-size: 0.85rem; color: var(--color-text-muted); font-style: italic;">
                Voting will open at scheduled time
              </span>
            <?php else: ?>
              <a href="<?= View::url('/results?id=' . $election['id']) ?>" class="btn btn-secondary btn-sm">
                View Election Results &rarr;
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
