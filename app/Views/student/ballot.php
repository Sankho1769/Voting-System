<?php
use App\Core\View;
use App\Helpers\Sanitizer;
use App\Helpers\TimeHelper;
$pageTitle = 'Cast Official Ballot — ' . $election['title'];
?>

<div class="container container-narrow section">
  <div style="margin-bottom: 1.5rem;">
    <a href="<?= View::url('/voter') ?>" style="text-decoration: none; font-size: 0.9rem; font-weight: 600; color: var(--text-secondary); display: inline-flex; align-items: center; gap: 0.35rem;">
      &larr; Return to Ballot Box
    </a>
  </div>

  <!-- Header Card (Ballot Context) -->
  <div class="card" style="margin-bottom: 2rem; padding: 2.25rem; border-top: 4px solid var(--brand-purple);">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
      <span class="status-pill open">
        <span class="status-dot"></span>
        <span>VOTING OPEN</span>
      </span>
      <span style="font-size: 0.85rem; color: var(--text-muted);">
        Deadline: <strong><?= TimeHelper::format($election['closes_at']) ?></strong>
      </span>
    </div>

    <h1 style="font-family: var(--font-display); font-size: 2.35rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem;">
      <?= Sanitizer::e($election['title']) ?>
    </h1>
    <div style="font-size: 1.1rem; font-weight: 600; color: var(--brand-purple); margin-bottom: 0.85rem;">
      Position: <?= Sanitizer::e($election['position']) ?>
    </div>

    <p style="color: var(--text-secondary); font-size: 0.95rem; line-height: 1.5; margin-bottom: 0;">
      <?= nl2br(Sanitizer::e($election['description'] ?: 'Please review the candidates and manifestos carefully. Select your choice below.')) ?>
    </p>
  </div>

  <form id="ballot-form" action="<?= View::url('/ballot') ?>" method="POST">
    <?= $csrfField ?>
    <input type="hidden" name="election_id" value="<?= (int)$election['id'] ?>">
    <?php if (!empty($ballotToken)): ?>
      <input type="hidden" name="ballot_token" value="<?= htmlspecialchars($ballotToken, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>

    <div style="margin-bottom: 1.25rem; display: flex; align-items: baseline; justify-content: space-between;">
      <h2 style="font-family: var(--font-display); font-size: 1.6rem; font-weight: 700; color: var(--text-primary); margin: 0;">
        Ballot Selection
      </h2>
      <span style="font-size: 0.85rem; color: var(--text-muted);">Choose one contender</span>
    </div>

    <!-- Candidate Selection Cards (Styled after Reference 1 Floating Ballot Card) -->
    <div class="candidates-selection" style="margin-bottom: 2rem; display: flex; flex-direction: column; gap: 1rem;">
      <?php foreach ($candidates as $cand): ?>
        <div 
          class="candidate-card" 
          tabindex="0" 
          role="radio" 
          aria-checked="false" 
          data-candidate-id="<?= (int)$cand['id'] ?>"
          data-candidate-name="<?= Sanitizer::e($cand['name']) ?>"
          style="cursor: pointer; transition: all var(--transition-fast);"
        >
          <div style="display: flex; align-items: center; gap: 1rem;">
            <input 
              type="radio" 
              name="candidate_id" 
              value="<?= (int)$cand['id'] ?>" 
              id="cand-<?= (int)$cand['id'] ?>"
              style="width: 1.35rem; height: 1.35rem; accent-color: var(--accent-lime); cursor: pointer;"
              required
            >
            <?php if (!empty($cand['photo_path'])): ?>
              <img src="<?= View::asset($cand['photo_path']) ?>" alt="<?= Sanitizer::e($cand['name']) ?>" class="candidate-avatar">
            <?php else: ?>
              <div class="candidate-avatar" aria-hidden="true" style="background-color: var(--surface-lavender); color: var(--brand-purple); font-family: var(--font-display); font-weight: 800; font-size: 1.4rem;">
                <?= strtoupper(substr($cand['name'], 0, 1)) ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="candidate-details" style="flex: 1;">
            <h3 class="candidate-name" style="font-family: var(--font-display); font-size: 1.35rem; margin: 0 0 0.25rem 0;">
              <?= Sanitizer::e($cand['name']) ?>
            </h3>
            <div class="candidate-manifesto" style="color: var(--text-secondary); font-size: 0.88rem; line-height: 1.45;">
              <?= nl2br(Sanitizer::e($cand['manifesto'] ?: 'No personal campaign statement submitted.')) ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Ballot Secrecy & Submit Notice -->
    <div class="card" style="padding: 1.75rem; margin-bottom: 2.5rem; background-color: var(--surface-card);">
      <h4 style="font-family: var(--font-display); font-size: 1.25rem; margin-bottom: 0.5rem; color: var(--text-primary);">
        Ballot Secrecy &amp; Integrity Guarantee
      </h4>
      <p style="font-size: 0.88rem; color: var(--text-secondary); line-height: 1.5; margin-bottom: 1.25rem;">
        Under VerityVote's decoupled architecture, your participation timestamp is recorded to guarantee <strong>zero duplicate votes</strong>, but your candidate choice is deposited anonymously with zero foreign keys to your student ID.
      </p>
      <button type="button" id="btn-submit-ballot" class="btn btn-primary btn-block btn-lg" style="justify-content: center;" disabled>
        Review &amp; Cast Official Ballot &rarr;
      </button>
    </div>
  </form>
</div>

<!-- Modal Dialog: Review and Confirm Vote -->
<div id="modal-confirm-vote" class="modal-backdrop" aria-hidden="true" role="dialog" aria-modal="true">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 style="font-family: var(--font-display); margin: 0; font-size: 1.45rem; color: var(--text-primary);">Confirm Your Ballot</h3>
    </div>
    <div class="modal-body">
      <p style="font-size: 0.95rem; margin-bottom: 1rem; color: var(--text-secondary);">
        You are about to cast your irreversible official vote for:
      </p>
      <div style="background-color: var(--surface-lavender); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 1.25rem; text-align: center; margin-bottom: 1.25rem;">
        <span style="display: block; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--brand-purple); font-weight: 700;">Selected Candidate</span>
        <strong id="confirm-candidate-name" style="font-size: 1.65rem; font-family: var(--font-display); color: var(--brand-purple);">
          —
        </strong>
      </div>
      <p style="font-size: 0.85rem; color: var(--vermilion); font-weight: 600; margin-bottom: 0;">
        &bull; This submission is final and recorded in an immutable ledger. You cannot modify or recast your ballot.
      </p>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeModal('modal-confirm-vote')">
        Change Selection
      </button>
      <button type="button" id="btn-final-cast-vote" class="btn btn-primary">
        Confirm &amp; Deposit Ballot
      </button>
    </div>
  </div>
</div>

<?php
$extraScripts = '<script src="' . View::asset('js/vote.js') . '"></script>';
?>
