<?php
use App\Core\Auth;
use App\Core\View;
use App\Helpers\Sanitizer;
use App\Helpers\TimeHelper;

$pageTitle = 'Candidate Control Center';
$user = Auth::user();
?>

<div class="container container-wide section">
  <!-- Header Bar -->
  <div class="page-header" style="margin-bottom: 2rem;">
    <div>
      <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem;">
        <span class="badge badge-purple" style="font-size: 0.75rem; text-transform: uppercase;">
          Candidate Portal
        </span>
        <span style="font-size: 0.85rem; color: var(--text-muted); font-family: var(--font-mono);">
          ID: <?= Sanitizer::e(Auth::identifier()) ?>
        </span>
      </div>
      <h1 style="font-family: var(--font-display); font-size: 2.75rem; font-weight: 700; color: var(--text-primary); margin: 0 0 0.25rem 0;">
        Candidate Control Center
      </h1>
      <div style="color: var(--text-muted); font-size: 1rem;">
        Contender Platform &bull; Candidacy Status Verification &bull; Live Election Standings
      </div>
    </div>
    <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
      <?php if (!empty($is_eligible_voter)): ?>
        <a href="<?= View::url('/voter') ?>" class="btn btn-secondary btn-sm" style="border-color: var(--brand-purple); color: var(--brand-purple); font-weight: 600;">
          &rarr; Go to Voter Portal (<?= (int)$eligible_elections_count ?> Eligible)
        </a>
      <?php endif; ?>
      <a href="<?= View::url('/change-password') ?>" class="btn btn-outline btn-sm">Change Password</a>
    </div>
  </div>

  <!-- Dual Permission Alert: If candidate is ALSO eligible to vote -->
  <?php if (!empty($is_eligible_voter)): ?>
    <div class="card" style="padding: 1.25rem 1.75rem; margin-bottom: 2rem; border-left: 4px solid var(--accent-lime); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; background-color: var(--surface-card);">
      <div>
        <strong style="color: #4B6300; display: block; font-size: 1.05rem; margin-bottom: 0.25rem;">
          Dual Authorization: You are an Eligible Voter
        </strong>
        <p style="margin: 0; font-size: 0.9rem; color: var(--text-secondary);">
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
      <div style="width: 4.5rem; height: 4.5rem; border-radius: 50%; background: var(--surface-lavender); border: 2px solid var(--brand-purple); display: flex; align-items: center; justify-content: center; font-size: 1.75rem; font-weight: 800; font-family: var(--font-display); color: var(--brand-purple); overflow: hidden;">
        <?php if (!empty($profiles[0]['candidacy']['photo_path'])): ?>
          <img src="<?= View::asset($profiles[0]['candidacy']['photo_path']) ?>" alt="<?= Sanitizer::e($user['full_name']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
        <?php else: ?>
          <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
        <?php endif; ?>
      </div>
      <div style="flex: 1; min-width: 250px;">
        <h2 style="font-family: var(--font-display); font-size: 1.65rem; margin-bottom: 0.25rem; color: var(--text-primary);"><?= Sanitizer::e($user['full_name']) ?></h2>
        <div style="font-size: 0.9rem; color: var(--text-muted); display: flex; gap: 1rem; flex-wrap: wrap;">
          <span>Identifier: <strong><?= Sanitizer::e($user['identifier']) ?></strong></span>
          <span>Department: <strong><?= Sanitizer::e($user['department'] ?? 'Collegiate') ?></strong></span>
          <span>Account Role: <strong><?= ucfirst(Sanitizer::e($user['role'])) ?></strong></span>
          <span>Status: <span class="badge badge-purple"><?= ucfirst(Sanitizer::e($user['status'])) ?></span></span>
        </div>
      </div>
    </div>
  </div>

  <!-- Contested Races Section -->
  <h3 style="font-size: 1.55rem; margin-bottom: 1.25rem; font-family: var(--font-display); font-weight: 700; color: var(--text-primary);">
    Your Candidacy Profiles &amp; Contested Elections
  </h3>

  <?php if (empty($profiles)): ?>
    <div class="card" style="text-align: center; padding: 3rem 1.5rem;">
      <h3 style="font-family: var(--font-display); font-size: 1.5rem; margin-bottom: 0.5rem; color: var(--text-primary);">No Active Candidacy Profiles Found</h3>
      <p style="color: var(--text-muted); max-width: 520px; margin: 0 auto 1.5rem auto;">
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
        <div class="card" style="padding: 2rem; border-top: 4px solid <?= $approvalStatus === 'approved' ? 'var(--brand-purple)' : 'var(--vermilion)' ?>;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
            <div>
              <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                <span class="status-pill <?= Sanitizer::e($electionStatus) ?>">
                  <span class="status-dot"></span>
                  <span>Election <?= ucfirst(str_replace('_', ' ', $electionStatus)) ?></span>
                </span>
                <span class="badge <?= $approvalStatus === 'approved' ? 'badge-purple' : 'badge-outline' ?>">
                  Candidacy <?= ucfirst($approvalStatus) ?>
                </span>
              </div>
              <h2 style="font-family: var(--font-display); font-size: 1.85rem; font-weight: 700; margin-bottom: 0.25rem; color: var(--text-primary);">
                <?= Sanitizer::e($cand['election_title']) ?>
              </h2>
              <div style="font-size: 1.05rem; font-weight: 600; color: var(--brand-purple);">
                Position Contested: <?= Sanitizer::e($cand['position']) ?>
              </div>
            </div>

            <div style="text-align: right; font-size: 0.85rem; color: var(--text-muted);">
              <div>Voting Opens: <strong><?= TimeHelper::format($cand['opens_at'], 'd M Y, H:i') ?></strong></div>
              <div>Voting Closes: <strong><?= TimeHelper::format($cand['closes_at'], 'd M Y, H:i') ?></strong></div>
            </div>
          </div>

          <!-- Campaign Platform Manifesto -->
          <div style="background-color: var(--surface-secondary); padding: 1.25rem 1.5rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; border: 1px solid var(--border-subtle);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
              <h4 style="margin: 0; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); font-weight: 700;">
                Campaign Manifesto &amp; Platform Statement
              </h4>
              <?php if (!in_array($electionStatus, ['closed', 'results_published', 'cancelled'], true)): ?>
                <button type="button" class="btn btn-secondary btn-sm" onclick="const f = document.getElementById('edit-cand-form-<?= (int)$cand['id'] ?>'); f.style.display = f.style.display === 'none' ? 'block' : 'none';" style="font-size: 0.75rem; padding: 0.2rem 0.5rem;">
                  &#9998; Edit Platform
                </button>
              <?php endif; ?>
            </div>
            <div style="font-size: 0.95rem; line-height: 1.6; color: var(--text-primary); font-style: italic;">
              &ldquo;<?= nl2br(Sanitizer::e($cand['manifesto'] ?: 'No campaign manifesto submitted yet.')) ?>&rdquo;
            </div>

            <?php if (!in_array($electionStatus, ['closed', 'results_published', 'cancelled'], true)): ?>
              <div id="edit-cand-form-<?= (int)$cand['id'] ?>" style="display: none; margin-top: 1.25rem; border-top: 1px dashed var(--border-subtle); padding-top: 1rem;">
                <form action="<?= View::url('/candidate/edit') ?>" method="POST" enctype="multipart/form-data">
                  <?= $csrfField ?>
                  <input type="hidden" name="candidate_id" value="<?= (int)$cand['id'] ?>">
                  <div class="form-group" style="margin-bottom: 0.85rem;">
                    <label class="form-label" style="font-size: 0.85rem;">Update Campaign Manifesto Statement</label>
                    <textarea name="manifesto" class="form-control" rows="3" style="font-size: 0.9rem;" placeholder="Write your key initiatives and campaign message..."><?= Sanitizer::e($cand['manifesto'] ?? '') ?></textarea>
                  </div>
                  <div class="form-group" style="margin-bottom: 0.85rem;">
                    <label class="form-label" style="font-size: 0.85rem;">Update Official Campaign Photograph (JPEG/PNG/WebP, max 2MB)</label>
                    <input type="file" name="photo" class="form-control" accept="image/jpeg,image/png,image/webp" style="font-size: 0.85rem;">
                  </div>
                  <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary btn-sm">Save Profile Updates</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('edit-cand-form-<?= (int)$cand['id'] ?>').style.display = 'none';">Cancel</button>
                  </div>
                </form>
              </div>
            <?php endif; ?>
          </div>

          <!-- Contesting Opponents -->
          <div style="margin-bottom: 1.5rem;">
            <h4 style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); margin-bottom: 0.75rem; font-weight: 700;">
              Contending Opponents in This Race (<?= count($opponents) ?>)
            </h4>
            <?php if (empty($opponents)): ?>
              <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0;">
                No other candidates registered yet for this position.
              </p>
            <?php else: ?>
              <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <?php foreach ($opponents as $opp): ?>
                  <div style="display: flex; align-items: center; gap: 0.75rem; background: var(--surface-secondary); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 0.6rem 1rem;">
                    <div style="width: 2rem; height: 2rem; border-radius: 50%; background: var(--surface-lavender); display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 700; color: var(--brand-purple);">
                      <?= strtoupper(substr($opp['name'], 0, 1)) ?>
                    </div>
                    <div>
                      <strong style="font-size: 0.9rem; color: var(--text-primary);"><?= Sanitizer::e($opp['name']) ?></strong>
                      <div style="font-size: 0.75rem; color: var(--text-muted);">
                        <?= Sanitizer::e($opp['user_department'] ?? 'Collegiate') ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Standings & Results Module -->
          <div style="border-top: 1px solid var(--border-subtle); padding-top: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
              <h4 style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); margin: 0; font-weight: 700;">
                Electoral Performance &amp; Standings
              </h4>
              <span style="font-size: 0.85rem; color: var(--text-muted);">
                <?= Sanitizer::e($item['results_label'] ?? 'Provisional') ?>
              </span>
            </div>

            <?php if ($resultsVisible && !empty($results['candidates'])): ?>
              <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                <?php foreach ($results['candidates'] as $rc): ?>
                  <?php $isSelf = ((int)$rc['candidate_id'] === (int)$cand['id']); ?>
                  <div style="background-color: var(--surface-secondary); padding: 1rem; border-radius: var(--radius-md); border: 1px solid <?= $isSelf ? 'var(--brand-purple)' : 'var(--border-subtle)' ?>;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.9rem; margin-bottom: 0.35rem;">
                      <span>
                        <strong style="color: var(--text-primary);"><?= Sanitizer::e($rc['candidate_name']) ?></strong>
                        <?php if ($isSelf): ?>
                          <span class="badge badge-purple" style="font-size: 0.7rem; margin-left: 0.5rem;">Your Campaign</span>
                        <?php endif; ?>
                      </span>
                      <span>
                        <strong><?= (int)$rc['vote_count'] ?> votes</strong> (<?= number_format($rc['percentage'], 1) ?>%)
                      </span>
                    </div>
                    <div class="progress-track" style="height: 10px; background-color: var(--border-subtle); border-radius: 5px; overflow: hidden;">
                      <div class="progress-fill" style="width: <?= $rc['percentage'] ?>%; height: 100%; background: <?= $isSelf ? 'linear-gradient(90deg, #D7FF36, #84cc16)' : 'var(--brand-purple)' ?>; border-radius: 5px;"></div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div style="background-color: var(--surface-secondary); padding: 1rem; border-radius: var(--radius-md); font-size: 0.875rem; color: var(--text-muted);">
                <em>Standings for this race are confidential during voting and will be certified after election closure.</em>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
