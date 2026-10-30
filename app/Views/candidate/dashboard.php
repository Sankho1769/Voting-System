<?php
use App\Core\Auth;
use App\Core\View;
use App\Helpers\Sanitizer;
use App\Helpers\TimeHelper;

$pageTitle = 'Candidate Dashboard';
$user = Auth::user();
?>

<div class="container container-wide" style="margin-top: 1.5rem; margin-bottom: 3rem;">
  <!-- Header Bar -->
  <div class="page-header" style="margin-bottom: 1.5rem;">
    <div class="page-header-title">
      <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
        <span class="badge" style="background-color: var(--color-vermilion-subtle); color: var(--color-vermilion-dark); font-size: 0.75rem; text-transform: uppercase;">
          Candidate Portal
        </span>
        <span style="font-size: 0.85rem; color: var(--color-text-muted);">
          ID: <?= Sanitizer::e(Auth::identifier()) ?>
        </span>
      </div>
      <h1>Candidate Control Center</h1>
      <div class="page-header-subtitle">
        Contender Platform &bull; Candidacy Status Verification &bull; Live Election Standings
      </div>
    </div>
    <div style="display: flex; gap: 0.75rem; align-items: center;">
      <?php if (!empty($is_eligible_voter)): ?>
        <a href="<?= View::url('/voter') ?>" class="btn btn-secondary btn-sm" style="border-color: var(--color-emerald-green); color: var(--color-emerald-green);">
          &rarr; Go to Voter Portal (<?= (int)$eligible_elections_count ?> Eligible)
        </a>
      <?php endif; ?>
      <a href="<?= View::url('/change-password') ?>" class="btn btn-secondary btn-sm">Change Password</a>
    </div>
  </div>

  <!-- Dual Permission Alert: If candidate is ALSO eligible to vote -->
  <?php if (!empty($is_eligible_voter)): ?>
    <div class="card card-ivory" style="padding: 1.25rem 1.75rem; margin-bottom: 2rem; border-left: 4px solid var(--color-emerald-green); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
      <div>
        <strong style="color: var(--color-emerald-green); display: block; font-size: 1.05rem; margin-bottom: 0.25rem;">
          Dual Authorization: You are an Eligible Voter
        </strong>
        <p style="margin: 0; font-size: 0.9rem; color: var(--color-text-secondary);">
          Your collegiate account is on the voter whitelist for <strong><?= (int)$eligible_elections_count ?></strong> active election(s). You can cast your confidential ballot in your designated races through the Voter Portal.
        </p>
      </div>
      <a href="<?= View::url('/voter') ?>" class="btn btn-primary btn-sm">
        Enter Voter Ballot Box &rarr;
      </a>
    </div>
  <?php endif; ?>

  <!-- Candidate Identity Capsule -->
  <div class="card" style="padding: 1.75rem; margin-bottom: 2rem;">
    <div style="display: flex; gap: 1.5rem; align-items: center; flex-wrap: wrap;">
      <div style="width: 4.5rem; height: 4.5rem; border-radius: 50%; background: var(--color-warm-ivory); border: 2px solid var(--color-emerald-green); display: flex; align-items: center; justify-content: center; font-size: 1.75rem; font-weight: 700; color: var(--color-emerald-green); overflow: hidden;">
        <?php if (!empty($profiles[0]['candidacy']['photo_path'])): ?>
          <img src="<?= View::asset($profiles[0]['candidacy']['photo_path']) ?>" alt="<?= Sanitizer::e($user['full_name']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
        <?php else: ?>
          <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
        <?php endif; ?>
      </div>
      <div style="flex: 1; min-width: 250px;">
        <h2 style="font-size: 1.45rem; margin-bottom: 0.25rem;"><?= Sanitizer::e($user['full_name']) ?></h2>
        <div style="font-size: 0.9rem; color: var(--color-text-secondary); display: flex; gap: 1rem; flex-wrap: wrap;">
          <span>Identifier: <strong><?= Sanitizer::e($user['identifier']) ?></strong></span>
          <span>Department: <strong><?= Sanitizer::e($user['department'] ?? 'Collegiate') ?></strong></span>
          <span>Account Role: <strong><?= ucfirst(Sanitizer::e($user['role'])) ?></strong></span>
          <span>Status: <span class="badge badge-active"><?= ucfirst(Sanitizer::e($user['status'])) ?></span></span>
        </div>
      </div>
    </div>
  </div>

  <!-- Contested Races Section -->
  <h3 style="font-size: 1.35rem; margin-bottom: 1.25rem; font-family: var(--font-serif);">
    Your Candidacy Profiles &amp; Assigned Elections
  </h3>

  <?php if (empty($profiles)): ?>
    <div class="card card-ivory" style="text-align: center; padding: 3rem 1.5rem;">
      <h3 style="margin-bottom: 0.5rem;">No Active Candidacy Profiles Found</h3>
      <p style="color: var(--color-text-secondary); max-width: 520px; margin: 0 auto 1.5rem auto;">
        You do not currently contest in any approved single-position elections. Candidates must be nominated or assigned by election officers or college administration.
      </p>
      <?php if (!empty($is_eligible_voter)): ?>
        <a href="<?= View::url('/voter') ?>" class="btn btn-primary">Go to Voter Portal &rarr;</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div style="display: flex; flex-direction: column; gap: 2rem;">
      <?php foreach ($profiles as $item): ?>
        <?php 
          $cand = $item['candidacy'];
          $electionStatus = $cand['election_status'];
          $approvalStatus = $cand['approval_status'];
          $opponents = $item['opponents'];
          $resultsVisible = $item['results_visible'];
          $results = $item['results'];
        ?>
        <div class="card" style="padding: 2rem; border-top: 4px solid <?= $approvalStatus === 'approved' ? 'var(--color-emerald-green)' : 'var(--color-vermilion)' ?>;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
            <div>
              <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                <span class="badge badge-<?= Sanitizer::e($electionStatus) ?>">
                  Election <?= ucfirst(str_replace('_', ' ', $electionStatus)) ?>
                </span>
                <span class="badge" style="background-color: <?= $approvalStatus === 'approved' ? 'var(--color-emerald-subtle)' : 'var(--color-vermilion-subtle)' ?>; color: <?= $approvalStatus === 'approved' ? 'var(--color-emerald-dark)' : 'var(--color-vermilion-dark)' ?>;">
                  Candidacy <?= ucfirst($approvalStatus) ?>
                </span>
              </div>
              <h2 style="font-size: 1.75rem; margin-bottom: 0.25rem;">
                <?= Sanitizer::e($cand['election_title']) ?>
              </h2>
              <div style="font-size: 1.1rem; font-weight: 600; color: var(--color-emerald-green);">
                Position Contested: <?= Sanitizer::e($cand['position']) ?>
              </div>
            </div>

            <div style="text-align: right; font-size: 0.85rem; color: var(--color-text-secondary);">
              <div>Voting Opens: <strong><?= TimeHelper::format($cand['opens_at'], 'd M Y, H:i') ?></strong></div>
              <div>Voting Closes: <strong><?= TimeHelper::format($cand['closes_at'], 'd M Y, H:i') ?></strong></div>
            </div>
          </div>

          <!-- Campaign Platform Manifesto -->
          <div style="background-color: var(--color-surface-muted); padding: 1.25rem 1.5rem; border-radius: var(--radius-sm); margin-bottom: 1.5rem;">
            <h4 style="margin-bottom: 0.5rem; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-text-muted);">
              Campaign Manifesto &amp; Platform Statement
            </h4>
            <div style="font-size: 0.95rem; line-height: 1.6; color: var(--color-text-primary); font-style: italic;">
              "<?= nl2br(Sanitizer::e($cand['manifesto'] ?: 'No campaign manifesto submitted yet.')) ?>"
            </div>
          </div>

          <!-- Contesting Opponents -->
          <div style="margin-bottom: 1.5rem;">
            <h4 style="font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-text-muted); margin-bottom: 0.75rem;">
              Contending Opponents in This Race (<?= count($opponents) ?>)
            </h4>
            <?php if (empty($opponents)): ?>
              <p style="font-size: 0.9rem; color: var(--color-text-secondary); margin: 0;">
                No other candidates registered yet for this position.
              </p>
            <?php else: ?>
              <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <?php foreach ($opponents as $opp): ?>
                  <div style="display: flex; align-items: center; gap: 0.75rem; background: #FFFFFF; border: 1px solid var(--color-border-medium); border-radius: var(--radius-sm); padding: 0.6rem 1rem;">
                    <div style="width: 2rem; height: 2rem; border-radius: 50%; background: var(--color-warm-ivory); display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 700; color: var(--color-emerald-green);">
                      <?= strtoupper(substr($opp['name'], 0, 1)) ?>
                    </div>
                    <div>
                      <strong style="font-size: 0.9rem;"><?= Sanitizer::e($opp['name']) ?></strong>
                      <div style="font-size: 0.75rem; color: var(--color-text-muted);">
                        <?= Sanitizer::e($opp['user_department'] ?? 'Collegiate') ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Standings & Results Module -->
          <div style="border-top: 1px solid var(--color-border-medium); padding-top: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
              <h4 style="font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-text-muted); margin: 0;">
                Electoral Performance &amp; Standings
              </h4>
              <span style="font-size: 0.85rem; color: var(--color-text-secondary);">
                <?= Sanitizer::e($item['results_label'] ?? 'Provisional') ?>
              </span>
            </div>

            <?php if ($resultsVisible && !empty($results['candidates'])): ?>
              <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <?php foreach ($results['candidates'] as $rc): ?>
                  <?php $isSelf = ((int)$rc['candidate_id'] === (int)$cand['id']); ?>
                  <div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.9rem; margin-bottom: 0.25rem;">
                      <span>
                        <strong><?= Sanitizer::e($rc['candidate_name']) ?></strong>
                        <?php if ($isSelf): ?>
                          <span class="badge badge-active" style="font-size: 0.7rem; margin-left: 0.5rem;">Your Campaign</span>
                        <?php endif; ?>
                      </span>
                      <span>
                        <strong><?= (int)$rc['vote_count'] ?> votes</strong> (<?= number_format($rc['percentage'], 1) ?>%)
                      </span>
                    </div>
                    <div class="progress-bar" style="height: 8px;">
                      <div class="progress-fill" style="width: <?= $rc['percentage'] ?>%; background-color: <?= $isSelf ? 'var(--color-emerald-green)' : 'var(--color-text-muted)' ?>;"></div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div style="background-color: var(--color-surface-muted); padding: 1rem; border-radius: var(--radius-sm); font-size: 0.875rem; color: var(--color-text-secondary);">
                <em>Standings for this race are confidential during voting and will be certified after election closure.</em>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
