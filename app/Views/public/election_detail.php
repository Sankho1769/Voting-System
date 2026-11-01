<?php
use App\Core\Auth;
use App\Core\View;
use App\Helpers\Sanitizer;

$isOpen = ($election['status'] === 'open');
$isResults = ($election['status'] === 'results_published');
$isClosed = ($election['status'] === 'closed');
$isDraft = ($election['status'] === 'draft');

$catKey = $election['category'] ?? 'other';
$catName = ucwords(str_replace('_', ' ', $catKey));
?>

<div class="container section">

  <!-- =========================================================================
       MATCH-CENTER STYLE HERO BANNER
       ========================================================================= -->
  <div class="card" style="padding: 2.25rem; margin-bottom: 2rem; position: relative; overflow: hidden; background: linear-gradient(135deg, var(--surface-card) 0%, var(--surface-lavender) 100%);">
    
    <!-- Top Meta Row -->
    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1.25rem;">
      <div style="display: flex; align-items: center; gap: 0.75rem;">
        <span class="badge badge-purple"><?= Sanitizer::e($catName) ?></span>
        <?php if (!empty($election['department'])): ?>
          <span class="badge badge-outline"><?= Sanitizer::e($election['department']) ?></span>
        <?php else: ?>
          <span class="badge badge-outline">Campus-Wide</span>
        <?php endif; ?>
      </div>

      <div>
        <span class="status-pill <?= Sanitizer::e($election['status']) ?>">
          <span class="status-dot"></span>
          <span>
            <?php
              if ($isOpen) echo 'VOTING LIVE';
              elseif ($isResults) echo 'RESULTS CERTIFIED';
              elseif ($isClosed) echo 'POLLS CLOSED';
              else echo ucfirst($election['status']);
            ?>
          </span>
        </span>
      </div>
    </div>

    <!-- Title & Position -->
    <h1 style="font-family: var(--font-display); font-size: 2.75rem; font-weight: 700; color: var(--text-primary); margin: 0 0 0.5rem 0;">
      <?= Sanitizer::e($election['title']) ?>
    </h1>

    <div style="font-size: 1.15rem; color: var(--text-secondary); margin-bottom: 1.5rem;">
      Contesting for: <strong style="color: var(--brand-purple);"><?= Sanitizer::e($election['position'] ?? 'Elected Representative') ?></strong>
    </div>

    <!-- Timing & Actions Strip -->
    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1.25rem; padding-top: 1.25rem; border-top: 1px solid var(--border-subtle);">
      
      <div style="display: flex; align-items: center; gap: 1.5rem; font-size: 0.95rem;">
        <div>
          <span style="color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; display: block;">Window</span>
          <strong>
            <?= !empty($election['start_time']) ? Sanitizer::e(date('M j, Y H:i', strtotime($election['start_time']))) : 'Scheduled' ?>
            &ndash;
            <?= !empty($election['end_time']) ? Sanitizer::e(date('M j, Y H:i', strtotime($election['end_time']))) : 'TBD' ?>
          </strong>
        </div>

        <?php if ($isOpen && !empty($election['end_time'])): ?>
          <div>
            <span style="color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; display: block;">Time Remaining</span>
            <span class="countdown-ticker" data-countdown-target="<?= Sanitizer::e($election['end_time']) ?>" style="font-weight: 700; color: var(--vermilion);">
              Calculating...
            </span>
          </div>
        <?php endif; ?>
      </div>

      <!-- Action Button -->
      <div>
        <?php if ($isOpen): ?>
          <?php if (Auth::isLoggedIn() && Auth::isVoter()): ?>
            <?php if (!empty($hasParticipated)): ?>
              <a href="<?= View::url('/confirmation?election_id=' . (int)$election['id']) ?>" class="btn btn-secondary">
                View Cryptographic Receipt &rarr;
              </a>
            <?php elseif (!empty($isEligible)): ?>
              <a href="<?= View::url('/ballot?election_id=' . (int)$election['id']) ?>" class="btn btn-lime-pill" style="padding: 0.75rem 1.75rem; font-weight: 700;">
                <span>Cast Official Ballot</span>
                <span class="btn-hero-circle" style="width: 26px; height: 26px;">&rarr;</span>
              </a>
            <?php else: ?>
              <button type="button" class="btn btn-secondary" disabled title="Your institutional account is not on this election's eligibility whitelist">
                Not Whitelisted for this Election
              </button>
            <?php endif; ?>
          <?php else: ?>
            <a href="<?= View::url('/login/voter') ?>" class="btn btn-primary">
              Log in to Cast Ballot &rarr;
            </a>
          <?php endif; ?>
        <?php elseif ($isResults): ?>
          <a href="<?= View::url('/results?election_id=' . (int)$election['id']) ?>" class="btn btn-primary">
            View Certified Results &rarr;
          </a>
        <?php else: ?>
          <span class="badge badge-outline" style="padding: 0.6rem 1rem;">
            Voting starts <?= !empty($election['start_time']) ? Sanitizer::e(date('M j, Y', strtotime($election['start_time']))) : 'soon' ?>
          </span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- =========================================================================
       CRICHEROES-INSPIRED SUB-NAVIGATION & DETAIL TABS
       ========================================================================= -->
  <div class="grid grid-3" style="align-items: start;">

    <!-- Left 2 Columns: Contenders & Manifesto Statements -->
    <div style="grid-column: span 2;">
      <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
        <h2 style="font-family: var(--font-display); font-size: 1.85rem; font-weight: 700; color: var(--text-primary); margin: 0;">
          Approved Candidates (<?= count($candidates) ?>)
        </h2>
        <span style="font-size: 0.85rem; color: var(--text-muted);">
          Alphabetical Ballot Order
        </span>
      </div>

      <?php if (!empty($candidates)): ?>
        <div style="display: flex; flex-direction: column; gap: 1.25rem;">
          <?php foreach ($candidates as $candidate): ?>
            <div class="candidate-card">
              <div class="candidate-avatar">
                <?php if (!empty($candidate['photo_url'])): ?>
                  <img src="<?= Sanitizer::e($candidate['photo_url']) ?>" alt="<?= Sanitizer::e($candidate['name']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                  <span style="font-family: var(--font-display); font-weight: 800; font-size: 1.4rem; color: var(--brand-purple);">
                    <?= Sanitizer::e(strtoupper(substr($candidate['name'], 0, 1))) ?>
                  </span>
                <?php endif; ?>
              </div>

              <div class="candidate-info" style="flex: 1;">
                <div style="display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 0.5rem;">
                  <h3 class="candidate-name" style="margin: 0;">
                    <?= Sanitizer::e($candidate['name']) ?>
                  </h3>
                  <span style="font-family: var(--font-mono); font-size: 0.78rem; color: var(--text-muted);">
                    ID: <?= Sanitizer::e($candidate['candidate_id_string'] ?? ('CAND-' . $candidate['id'])) ?>
                  </span>
                </div>

                <div style="font-size: 0.85rem; color: var(--brand-purple); font-weight: 600; margin: 0.25rem 0 0.5rem 0;">
                  <?= Sanitizer::e($candidate['department'] ?? 'Campus Representative') ?>
                </div>

                <?php if (!empty($candidate['manifesto'])): ?>
                  <p class="candidate-manifesto" style="margin: 0; line-height: 1.5; font-size: 0.9rem;">
                    &ldquo;<?= Sanitizer::e($candidate['manifesto']) ?>&rdquo;
                  </p>
                <?php else: ?>
                  <p style="color: var(--text-muted); font-size: 0.85rem; font-style: italic; margin: 0;">
                    No personal manifesto submitted. Contesting on official platform.
                  </p>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="card" style="padding: 2.5rem; text-align: center;">
          <p style="color: var(--text-muted); margin: 0;">No candidates approved for this election yet.</p>
        </div>
      <?php endif; ?>
    </div>

    <!-- Right 1 Column: Election Rules, Institutional Whitelist & Security Info -->
    <div>
      <!-- Election Rules & Whitelist Card -->
      <div class="card" style="padding: 1.75rem; margin-bottom: 1.5rem;">
        <h3 style="font-family: var(--font-display); font-size: 1.35rem; margin-bottom: 1rem; color: var(--text-primary);">
          Voter Whitelist &amp; Rules
        </h3>
        
        <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.85rem; font-size: 0.88rem; color: var(--text-secondary);">
          <li style="display: flex; gap: 0.6rem; align-items: flex-start;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--brand-purple)" stroke-width="2.5" style="flex-shrink: 0; margin-top: 2px;"><polyline points="20 6 9 17 4 12"/></svg>
            <span><strong>Closed Institutional Access:</strong> Only enrolled students &amp; faculty on the certified whitelist can vote.</span>
          </li>
          <li style="display: flex; gap: 0.6rem; align-items: flex-start;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--brand-purple)" stroke-width="2.5" style="flex-shrink: 0; margin-top: 2px;"><polyline points="20 6 9 17 4 12"/></svg>
            <span><strong>One Voter, One Ballot:</strong> Atomic database locking rejects any duplicate voting attempts.</span>
          </li>
          <li style="display: flex; gap: 0.6rem; align-items: flex-start;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--brand-purple)" stroke-width="2.5" style="flex-shrink: 0; margin-top: 2px;"><polyline points="20 6 9 17 4 12"/></svg>
            <span><strong>Secret Ballot:</strong> Your vote choice is never stored alongside your student roll number.</span>
          </li>
          <li style="display: flex; gap: 0.6rem; align-items: flex-start;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--brand-purple)" stroke-width="2.5" style="flex-shrink: 0; margin-top: 2px;"><polyline points="20 6 9 17 4 12"/></svg>
            <span><strong>Cryptographic Receipt:</strong> Receive a unique verification code to audit participation without revealing choice.</span>
          </li>
        </ul>
      </div>

      <!-- Quick Links Card -->
      <div class="card" style="padding: 1.5rem;">
        <h4 style="font-family: var(--font-display); font-size: 1.15rem; margin-bottom: 0.75rem; color: var(--text-primary);">
          Election Links
        </h4>
        <div style="display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.9rem;">
          <a href="<?= View::url('/explore') ?>" style="color: var(--brand-purple); font-weight: 500;">&larr; Return to All Elections</a>
          <a href="<?= View::url('/candidates') ?>" style="color: var(--brand-purple); font-weight: 500;">Meet All Campus Candidates &rarr;</a>
          <a href="<?= View::url('/how-it-works') ?>" style="color: var(--brand-purple); font-weight: 500;">How Voting Works &rarr;</a>
        </div>
      </div>
    </div>

  </div>

</div>
