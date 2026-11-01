<?php
use App\Core\Auth;
use App\Core\View;
use App\Helpers\Sanitizer;
use App\Helpers\TimeHelper;
$pageTitle = 'Member Elections Portal';
?>

<div class="container section">
  <div class="page-header" style="margin-bottom: 2rem;">
    <div>
      <span class="badge badge-purple" style="margin-bottom: 0.5rem;">VOTER DASHBOARD</span>
      <h1 style="font-family: var(--font-display); font-size: 2.75rem; font-weight: 700; color: var(--text-primary); margin: 0 0 0.5rem 0;">
        Institutional Ballot Box
      </h1>
      <div style="color: var(--text-muted); font-size: 1rem;">
        Active ballot positions for <strong><?= Sanitizer::e(Auth::user()['full_name'] ?? Auth::identifier()) ?></strong> (ID: <?= Sanitizer::e(Auth::identifier()) ?> &bull; <?= Sanitizer::e(Auth::user()['department'] ?? 'Campus') ?>)
      </div>
    </div>
    <?php if (Auth::isCandidate()): ?>
      <div>
        <a href="<?= View::url('/candidate') ?>" class="btn btn-secondary btn-sm" style="border-color: var(--vermilion); color: var(--vermilion); font-weight: 600;">
          Switch to Candidate Desk &rarr;
        </a>
      </div>
    <?php endif; ?>
  </div>

  <?php if (Auth::isCandidate()): ?>
    <div class="card" style="padding: 1.25rem 1.75rem; margin-bottom: 2rem; border-left: 4px solid var(--vermilion); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; background-color: var(--surface-card);">
      <div style="font-size: 0.92rem;">
        <strong style="color: var(--vermilion);">Candidate Dual-Access:</strong> You have an active candidate platform. You can monitor your campaign standings from the Candidate Desk.
      </div>
      <a href="<?= View::url('/candidate') ?>" class="btn btn-sm btn-secondary" style="border-color: var(--vermilion); color: var(--vermilion); padding: 0.35rem 0.85rem;">
        Open Candidate Desk &rarr;
      </a>
    </div>
  <?php endif; ?>

  <?php if (empty($elections)): ?>
    <div class="card" style="text-align: center; padding: 3.5rem 1.5rem; background-color: var(--surface-card);">
      <div style="width: 56px; height: 56px; border-radius: 50%; background-color: var(--surface-lavender); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem auto;">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--brand-purple)" stroke-width="2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
      </div>
      <h3 style="font-family: var(--font-display); font-size: 1.75rem; color: var(--text-primary); margin-bottom: 0.5rem;">No Current Eligible Elections</h3>
      <p style="color: var(--text-muted); max-width: 500px; margin: 0 auto 1.5rem auto; line-height: 1.6;">
        You are not currently enrolled on the voter whitelist for any open elections. Eligibility lists are certified by election officers based on department and year of enrollment.
      </p>
      <a href="<?= View::url('/explore') ?>" class="btn btn-secondary btn-sm">Explore All Campus Elections</a>
    </div>
  <?php else: ?>
    <div class="grid grid-2">
      <?php foreach ($elections as $election): ?>
        <?php
          $status = $election['status'];
          $hasVoted = (bool)($election['has_participated'] ?? false);
          $isOpen = ($status === 'open');
          $catName = ucwords(str_replace('_', ' ', $election['category'] ?? 'General'));
        ?>
        <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; padding: 1.75rem;">
          <div>
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
              <span class="badge badge-purple">
                <?= Sanitizer::e($catName) ?>
              </span>
              <?php if ($hasVoted): ?>
                <span class="badge" style="background-color: var(--surface-lavender); color: var(--brand-purple); font-weight: 700;">
                  &check; Ballot Cast
                </span>
              <?php else: ?>
                <span class="status-pill <?= Sanitizer::e($status) ?>">
                  <span class="status-dot"></span>
                  <span><?= $isOpen ? 'VOTING OPEN' : ucfirst($status) ?></span>
                </span>
              <?php endif; ?>
            </div>

            <h3 style="font-family: var(--font-display); font-size: 1.55rem; color: var(--text-primary); margin-bottom: 0.35rem;">
              <a href="<?= View::url('/election?id=' . $election['id']) ?>" style="color: inherit;">
                <?= Sanitizer::e($election['title']) ?>
              </a>
            </h3>

            <div style="font-weight: 600; color: var(--brand-purple); font-size: 0.95rem; margin-bottom: 0.75rem;">
              Position: <?= Sanitizer::e($election['position']) ?>
            </div>

            <p style="color: var(--text-secondary); font-size: 0.9rem; line-height: 1.5; margin-bottom: 1.25rem;">
              <?= nl2br(Sanitizer::e($election['description'] ?: 'Official collegiate election.')) ?>
            </p>

            <div style="background-color: var(--surface-secondary); padding: 0.85rem 1rem; border-radius: var(--radius-md); font-size: 0.825rem; margin-bottom: 1.25rem; line-height: 1.5; border: 1px solid var(--border-subtle);">
              <div><strong>Opens:</strong> <?= TimeHelper::format($election['opens_at']) ?></div>
              <div><strong>Closes:</strong> <?= TimeHelper::format($election['closes_at']) ?></div>
              <div><strong>Approved Contenders:</strong> <?= (int)$election['approved_candidates_count'] ?> candidates</div>
            </div>
          </div>

          <div style="border-top: 1px solid var(--border-subtle); padding-top: 1rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
            <?php if ($isOpen && !$hasVoted): ?>
              <a href="<?= View::url('/ballot?id=' . $election['id']) ?>" class="btn btn-primary btn-block" style="justify-content: center;">
                Cast Official Ballot &rarr;
              </a>
            <?php elseif ($hasVoted): ?>
              <div style="font-size: 0.85rem; color: var(--text-muted);">
                Voted on <?= TimeHelper::format($election['voted_at'] ?? '') ?>
              </div>
              <a href="<?= View::url('/confirmation?id=' . $election['id']) ?>" class="btn btn-secondary btn-sm">
                View Receipt
              </a>
            <?php elseif ($status === 'scheduled'): ?>
              <span style="font-size: 0.85rem; color: var(--text-muted); font-style: italic;">
                Voting will open at scheduled time
              </span>
            <?php else: ?>
              <a href="<?= View::url('/results?id=' . $election['id']) ?>" class="btn btn-secondary btn-sm">
                View Results &rarr;
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
