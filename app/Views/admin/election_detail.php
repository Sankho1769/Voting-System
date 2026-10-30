<?php
use App\Core\View;
use App\Helpers\Sanitizer;
use App\Helpers\TimeHelper;
$pageTitle = 'Election Control: ' . $election['title'];
$isLocked = in_array($election['status'], ['open', 'closed', 'results_published'], true);
?>

<div class="container container-wide">
  <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
    <a href="<?= View::url('/admin/elections') ?>" style="text-decoration: none; font-size: 0.9rem; font-weight: 500;">
      &larr; Back to Elections List
    </a>
    <div style="display: flex; gap: 0.5rem;">
      <a href="<?= View::url('/results?id=' . $election['id']) ?>" class="btn btn-secondary btn-sm" target="_blank">
        Preview Standings &rarr;
      </a>
      <a href="<?= View::url('/admin/reports/csv?id=' . $election['id']) ?>" class="btn btn-secondary btn-sm">
        &darr; Export Tally CSV
      </a>
    </div>
  </div>

  <!-- Header Banner & State Controls -->
  <div class="card card-ivory" style="padding: 2rem; margin-bottom: 2rem;">
    <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
      <div>
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
          <span class="badge badge-<?= Sanitizer::e($election['status']) ?>" style="font-size: 0.85rem;">
            <?= ucfirst(str_replace('_', ' ', $election['status'])) ?>
          </span>
          <span style="font-size: 0.85rem; color: var(--color-text-secondary);">
            Visibility: <strong><?= $election['results_visibility'] === 'live_provisional' ? 'Live Provisional' : 'Confidential until closure' ?></strong>
          </span>
        </div>
        <h1 style="font-size: 2.2rem; margin-bottom: 0.25rem;"><?= Sanitizer::e($election['title']) ?></h1>
        <div style="font-size: 1.15rem; font-weight: 600; color: var(--color-emerald-green);">
          Office: <?= Sanitizer::e($election['position']) ?>
        </div>
      </div>

      <!-- State Transition Buttons -->
      <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <?php if ($election['status'] === 'draft'): ?>
          <form action="<?= View::url('/admin/elections/status') ?>" method="POST" onsubmit="return confirm('Schedule this election? At least 2 approved candidates required.');">
            <?= $csrfField ?>
            <input type="hidden" name="election_id" value="<?= (int)$election['id'] ?>">
            <input type="hidden" name="status" value="scheduled">
            <button type="submit" class="btn btn-secondary btn-sm">Set to Scheduled</button>
          </form>

          <form action="<?= View::url('/admin/elections/status') ?>" method="POST" onsubmit="return confirm('Open voting immediately? This will LOCK candidate and ballot configurations.');">
            <?= $csrfField ?>
            <input type="hidden" name="election_id" value="<?= (int)$election['id'] ?>">
            <input type="hidden" name="status" value="open">
            <button type="submit" class="btn btn-primary btn-sm">Open Voting Now</button>
          </form>
        <?php elseif ($election['status'] === 'scheduled'): ?>
          <form action="<?= View::url('/admin/elections/status') ?>" method="POST" onsubmit="return confirm('Open voting immediately? This will LOCK candidates and eligibility.');">
            <?= $csrfField ?>
            <input type="hidden" name="election_id" value="<?= (int)$election['id'] ?>">
            <input type="hidden" name="status" value="open">
            <button type="submit" class="btn btn-primary btn-sm">Open Voting Now</button>
          </form>
        <?php elseif ($election['status'] === 'open'): ?>
          <form action="<?= View::url('/admin/elections/status') ?>" method="POST" onsubmit="return confirm('Close voting? No further ballots will be accepted.');">
            <?= $csrfField ?>
            <input type="hidden" name="election_id" value="<?= (int)$election['id'] ?>">
            <input type="hidden" name="status" value="closed">
            <button type="submit" class="btn btn-danger btn-sm">Close Election</button>
          </form>
        <?php elseif ($election['status'] === 'closed'): ?>
          <form action="<?= View::url('/admin/elections/status') ?>" method="POST" onsubmit="return confirm('Publish final official results to all collegiate voters?');">
            <?= $csrfField ?>
            <input type="hidden" name="election_id" value="<?= (int)$election['id'] ?>">
            <input type="hidden" name="status" value="results_published">
            <button type="submit" class="btn btn-primary btn-sm">Publish Official Results</button>
          </form>
        <?php endif; ?>

        <?php if (!in_array($election['status'], ['cancelled', 'results_published'], true)): ?>
          <form action="<?= View::url('/admin/elections/status') ?>" method="POST" onsubmit="return confirm('Cancel this election? This cannot be undone.');">
            <?= $csrfField ?>
            <input type="hidden" name="election_id" value="<?= (int)$election['id'] ?>">
            <input type="hidden" name="status" value="cancelled">
            <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--color-vermilion);">Cancel Election</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- Quick Stats -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; border-top: 1px solid var(--color-border-medium); padding-top: 1.25rem; font-size: 0.9rem;">
      <div>
        <span style="color: var(--color-text-muted); display: block; font-size: 0.8rem; text-transform: uppercase;">Voting Period</span>
        <strong><?= TimeHelper::format($election['opens_at'], 'd M Y, H:i') ?></strong> &rarr; <strong><?= TimeHelper::format($election['closes_at'], 'd M Y, H:i') ?></strong>
      </div>
      <div>
        <span style="color: var(--color-text-muted); display: block; font-size: 0.8rem; text-transform: uppercase;">Approved Contenders</span>
        <strong><?= count($candidates) ?> candidates</strong>
      </div>
      <div>
        <span style="color: var(--color-text-muted); display: block; font-size: 0.8rem; text-transform: uppercase;">Eligible Whitelist</span>
        <strong><?= (int)$election['eligible_voters_count'] ?> voters</strong>
      </div>
      <div>
        <span style="color: var(--color-text-muted); display: block; font-size: 0.8rem; text-transform: uppercase;">Ballots Accepted</span>
        <strong style="color: var(--color-emerald-green); font-size: 1.1rem;"><?= (int)$election['total_votes_cast'] ?></strong>
      </div>
    </div>
  </div>

  <div class="grid grid-2" style="align-items: flex-start; gap: 2rem;">
    <!-- Column 1: Candidate Management -->
    <div>
      <div class="card">
        <div class="card-header">
          <h2>Candidates Contesting</h2>
          <span style="font-size: 0.85rem; color: var(--color-text-muted);"><?= count($candidates) ?> total</span>
        </div>

        <?php if ($isLocked): ?>
          <div class="alert alert-info" style="font-size: 0.85rem; margin-bottom: 1rem;">
            Candidate list is locked while election is <strong><?= Sanitizer::e($election['status']) ?></strong>.
          </div>
        <?php endif; ?>

        <div style="margin-bottom: 1.5rem;">
          <?php if (empty($candidates)): ?>
            <p style="color: var(--color-text-muted); padding: 1rem 0;">No candidates added yet. At least 2 approved candidates are required before opening.</p>
          <?php else: ?>
            <div class="table-container" style="border: 1px solid var(--color-border-subtle); margin-bottom: 1rem;">
              <table class="table">
                <thead>
                  <tr>
                    <th>Candidate</th>
                    <th>Approval</th>
                    <th>Manifesto</th>
                    <?php if (!$isLocked): ?>
                      <th>Action</th>
                    <?php endif; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($candidates as $cand): ?>
                    <tr>
                      <td style="display: flex; align-items: center; gap: 0.75rem;">
                        <?php if (!empty($cand['photo_path'])): ?>
                          <img src="<?= View::asset($cand['photo_path']) ?>" alt="" style="width: 2.25rem; height: 2.25rem; border-radius: 4px; object-fit: cover;">
                        <?php else: ?>
                          <div style="width: 2.25rem; height: 2.25rem; background: var(--color-warm-ivory); border-radius: 4px; display: flex; align-items: center; justify-content: center; font-weight: 700; color: var(--color-emerald-green);">
                            <?= strtoupper(substr($cand['name'], 0, 1)) ?>
                          </div>
                        <?php endif; ?>
                        <div>
                          <strong><?= Sanitizer::e($cand['name']) ?></strong>
                          <?php if (!empty($cand['user_identifier'])): ?>
                            <div style="font-size: 0.75rem; color: var(--color-emerald-green); font-weight: 500;">
                              &check; Linked: <?= Sanitizer::e($cand['user_identifier']) ?>
                            </div>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td>
                        <span class="badge" style="background-color: <?= $cand['approval_status'] === 'approved' ? 'var(--color-emerald-subtle)' : 'var(--color-vermilion-subtle)' ?>; color: <?= $cand['approval_status'] === 'approved' ? 'var(--color-emerald-dark)' : 'var(--color-vermilion-dark)' ?>;">
                          <?= ucfirst($cand['approval_status']) ?>
                        </span>
                      </td>
                      <td style="max-width: 200px; font-size: 0.8rem; color: var(--color-text-secondary);">
                        <?= Sanitizer::e(substr($cand['manifesto'] ?: 'No manifesto', 0, 60)) ?>...
                      </td>
                      <?php if (!$isLocked): ?>
                        <td>
                          <form action="<?= View::url('/admin/candidates/delete') ?>" method="POST" onsubmit="return confirm('Remove candidate <?= Sanitizer::e($cand['name']) ?>?');">
                            <?= $csrfField ?>
                            <input type="hidden" name="candidate_id" value="<?= (int)$cand['id'] ?>">
                            <input type="hidden" name="election_id" value="<?= (int)$election['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger" style="padding: 0.2rem 0.5rem; font-size: 0.75rem;">Delete</button>
                          </form>
                        </td>
                      <?php endif; ?>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <!-- Add Candidate Form (only if not locked) -->
        <?php if (!$isLocked): ?>
          <div style="background-color: var(--color-surface-muted); padding: 1.25rem; border-radius: var(--radius-sm);">
            <h4 style="margin-bottom: 0.85rem;">+ Add Approved Contender</h4>
            <form action="<?= View::url('/admin/candidates/create') ?>" method="POST" enctype="multipart/form-data">
              <?= $csrfField ?>
              <input type="hidden" name="election_id" value="<?= (int)$election['id'] ?>">

              <div class="form-group">
                <label for="user_id" class="form-label">Link Collegiate User Account (Optional)</label>
                <select id="user_id" name="user_id" class="form-control" onchange="const opt = this.options[this.selectedIndex]; if(opt.dataset.name && !document.getElementById('name').value) { document.getElementById('name').value = opt.dataset.name; }">
                  <option value="">-- No linked account (Standalone contender) --</option>
                  <?php if (!empty($availableUsers)): ?>
                    <?php foreach ($availableUsers as $au): ?>
                      <option value="<?= (int)$au['id'] ?>" data-name="<?= Sanitizer::e($au['full_name']) ?>">
                        <?= Sanitizer::e($au['full_name']) ?> (<?= Sanitizer::e($au['identifier']) ?> &bull; <?= ucfirst($au['role']) ?>)
                      </option>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </select>
                <div class="form-help">Links this candidacy to the student/staff account so they can sign in via Candidate Portal.</div>
              </div>

              <div class="form-group">
                <label for="name" class="form-label">Candidate Name <span class="required">*</span></label>
                <input type="text" id="name" name="name" class="form-control" required placeholder="e.g. Priyanshu Roy">
              </div>

              <div class="form-group">
                <label for="manifesto" class="form-label">Manifesto / Platform</label>
                <textarea id="manifesto" name="manifesto" class="form-control" rows="2" placeholder="Key initiatives and campaign statement"></textarea>
              </div>

              <div class="form-group">
                <label for="photo" class="form-label">Profile Photo (Max 2MB, JPEG/PNG/WebP)</label>
                <input type="file" id="photo" name="photo" class="form-control" accept="image/jpeg,image/png,image/webp">
              </div>

              <button type="submit" class="btn btn-primary btn-block">Add Candidate</button>
            </form>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Column 2: Voter Eligibility Provisioning -->
    <div>
      <div class="card">
        <div class="card-header">
          <h2>Voter Whitelist</h2>
          <span style="font-size: 0.85rem; color: var(--color-text-muted);"><?= (int)$election['eligible_voters_count'] ?> assigned</span>
        </div>

        <?php if ($isLocked): ?>
          <div class="alert alert-info" style="font-size: 0.85rem; margin-bottom: 1rem;">
            Voter whitelist is locked while election is <strong><?= Sanitizer::e($election['status']) ?></strong>.
          </div>
        <?php endif; ?>

        <!-- Batch Assignment Controls (only if not locked) -->
        <?php if (!$isLocked): ?>
          <div style="background-color: var(--color-warm-ivory); border: 1px solid var(--color-border-medium); border-radius: var(--radius-sm); padding: 1.25rem; margin-bottom: 1.5rem;">
            <h4 style="margin-bottom: 0.5rem;">Whitelist Criteria Assignment</h4>
            <p style="font-size: 0.825rem; color: var(--color-text-secondary); margin-bottom: 1rem;">
              Quickly authorize voters by institutional role and department.
            </p>

            <form action="<?= View::url('/admin/eligibility/assign-criteria') ?>" method="POST">
              <?= $csrfField ?>
              <input type="hidden" name="election_id" value="<?= (int)$election['id'] ?>">

              <div class="grid grid-2" style="gap: 0.75rem; margin-bottom: 1rem;">
                <div>
                  <label for="elig_role" class="form-label" style="font-size: 0.8rem;">Role</label>
                  <select id="elig_role" name="role" class="form-control" style="font-size: 0.85rem;">
                    <option value="">All Roles</option>
                    <option value="student">Students Only</option>
                    <option value="staff">Staff Only</option>
                  </select>
                </div>
                <div>
                  <label for="elig_dept" class="form-label" style="font-size: 0.8rem;">Department</label>
                  <select id="elig_dept" name="department" class="form-control" style="font-size: 0.85rem;">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                      <option value="<?= Sanitizer::e($dept) ?>"><?= Sanitizer::e($dept) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <button type="submit" class="btn btn-primary btn-sm btn-block">
                Authorize Voters by Criteria
              </button>
            </form>
          </div>
        <?php endif; ?>

        <!-- Eligible Voters Table -->
        <h4>Enrolled Voters</h4>
        <div class="table-container" style="max-height: 400px; overflow-y: auto;">
          <table class="table">
            <thead>
              <tr>
                <th>Voter ID</th>
                <th>Name &amp; Dept</th>
                <th>Role</th>
                <th>Participation</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($eligibleVoters)): ?>
                <tr>
                  <td colspan="4" style="text-align: center; color: var(--color-text-muted); padding: 1.5rem;">
                    No voters enrolled on whitelist yet.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($eligibleVoters as $voter): ?>
                  <tr>
                    <td><strong><?= Sanitizer::e($voter['identifier']) ?></strong></td>
                    <td>
                      <?= Sanitizer::e($voter['full_name']) ?>
                      <div style="font-size: 0.75rem; color: var(--color-text-muted);"><?= Sanitizer::e($voter['department'] ?: '—') ?></div>
                    </td>
                    <td><span class="role-badge" style="font-size: 0.7rem;"><?= Sanitizer::e($voter['role']) ?></span></td>
                    <td>
                      <?php if ((bool)$voter['has_participated']): ?>
                        <span class="badge" style="background-color: var(--color-emerald-subtle); color: var(--color-emerald-green); font-size: 0.75rem;">
                          &check; Voted
                        </span>
                      <?php else: ?>
                        <span style="font-size: 0.8rem; color: var(--color-text-muted);">Awaiting</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
