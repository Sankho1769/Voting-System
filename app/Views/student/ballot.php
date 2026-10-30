<?php
use App\Core\View;
use App\Helpers\Sanitizer;
use App\Helpers\TimeHelper;
$pageTitle = 'Cast Ballot — ' . $election['title'];
?>

<div class="container container-narrow">
  <div style="margin-bottom: 1.5rem;">
    <a href="<?= View::url('/dashboard') ?>" style="text-decoration: none; font-size: 0.9rem; font-weight: 500;">
      &larr; Back to Elections Portal
    </a>
  </div>

  <div class="card card-ivory" style="margin-bottom: 2rem;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
      <span class="badge badge-open">Voting Open</span>
      <span style="font-size: 0.85rem; color: var(--color-text-secondary);">
        Deadline: <strong><?= TimeHelper::format($election['closes_at']) ?></strong>
      </span>
    </div>

    <h1 style="font-size: 2rem; margin-bottom: 0.25rem;">
      <?= Sanitizer::e($election['title']) ?>
    </h1>
    <div style="font-size: 1.1rem; font-weight: 600; color: var(--color-emerald-green); margin-bottom: 0.85rem;">
      Contested Position: <?= Sanitizer::e($election['position']) ?>
    </div>

    <p style="color: var(--color-text-secondary); font-size: 0.95rem; margin-bottom: 0;">
      <?= nl2br(Sanitizer::e($election['description'] ?: 'Please review the candidate manifestos carefully and make your selection below.')) ?>
    </p>
  </div>

  <form id="ballot-form" action="<?= View::url('/ballot') ?>" method="POST">
    <?= $csrfField ?>
    <input type="hidden" name="election_id" value="<?= (int)$election['id'] ?>">

    <div style="margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between;">
      <h2 style="font-size: 1.35rem;">Approved Candidates</h2>
      <span style="font-size: 0.85rem; color: var(--color-text-muted);">Select exactly one candidate</span>
    </div>

    <div class="candidates-selection" style="margin-bottom: 2rem;">
      <?php foreach ($candidates as $cand): ?>
        <div 
          class="candidate-card" 
          tabindex="0" 
          role="radio" 
          aria-checked="false" 
          data-candidate-id="<?= (int)$cand['id'] ?>"
          data-candidate-name="<?= Sanitizer::e($cand['name']) ?>"
        >
          <div style="display: flex; align-items: center; gap: 1rem;">
            <input 
              type="radio" 
              name="candidate_id" 
              value="<?= (int)$cand['id'] ?>" 
              id="cand-<?= (int)$cand['id'] ?>"
              style="width: 1.25rem; height: 1.25rem; accent-color: var(--color-emerald-green); cursor: pointer;"
              required
            >
            <?php if (!empty($cand['photo_path'])): ?>
              <img src="<?= View::asset($cand['photo_path']) ?>" alt="<?= Sanitizer::e($cand['name']) ?>" class="candidate-avatar">
            <?php else: ?>
              <div class="candidate-avatar" aria-hidden="true">
                <?= strtoupper(substr($cand['name'], 0, 1)) ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="candidate-details">
            <h3 class="candidate-name"><?= Sanitizer::e($cand['name']) ?></h3>
            <div class="candidate-manifesto">
              <?= nl2br(Sanitizer::e($cand['manifesto'] ?: 'No personal manifesto submitted.')) ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div style="background-color: #FFFFFF; border: 1px solid var(--color-border-subtle); border-radius: var(--radius-md); padding: 1.5rem; margin-bottom: 2rem;">
      <h4 style="margin-bottom: 0.5rem;">Ballot Secrecy &amp; Integrity Notice</h4>
      <p style="font-size: 0.85rem; color: var(--color-text-secondary); margin-bottom: 1rem;">
        Under the VerityVote architecture, voter participation is verified separately from candidate selections. 
        Your student/staff ID will be marked as having participated, but your candidate choice is stored in an unlinked ballot record.
      </p>
      <button type="button" id="btn-submit-ballot" class="btn btn-primary btn-block btn-lg" disabled>
        Review &amp; Cast Official Ballot
      </button>
    </div>
  </form>
</div>

<!-- Modal Dialog: Review and Confirm Vote -->
<div id="modal-confirm-vote" class="modal-backdrop" aria-hidden="true" role="dialog" aria-modal="true">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 style="margin: 0; font-size: 1.25rem;">Confirm Your Ballot</h3>
    </div>
    <div class="modal-body">
      <p style="font-size: 0.95rem; margin-bottom: 1rem;">
        You are about to cast your official vote for:
      </p>
      <div style="background-color: var(--color-emerald-subtle); border: 1px solid #BBE1CF; border-radius: var(--radius-sm); padding: 1.25rem; text-align: center; margin-bottom: 1.25rem;">
        <span style="display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-emerald-dark); font-weight: 600;">Candidate</span>
        <strong id="confirm-candidate-name" style="font-size: 1.5rem; font-family: var(--font-display); color: var(--color-emerald-dark);">
          —
        </strong>
      </div>
      <p style="font-size: 0.85rem; color: var(--color-vermilion); font-weight: 600; margin-bottom: 0;">
        &bull; This submission is irreversible. You cannot modify or recast your ballot once confirmed.
      </p>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeModal('modal-confirm-vote')">
        Cancel
      </button>
      <button type="button" id="btn-final-cast-vote" class="btn btn-primary">
        Confirm &amp; Submit Ballot
      </button>
    </div>
  </div>
</div>

<?php
$extraScripts = '<script src="' . View::asset('js/vote.js') . '"></script>';
?>
