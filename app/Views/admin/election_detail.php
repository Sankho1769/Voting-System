<?php
use App\Core\View;
use App\Helpers\Sanitizer;
use App\Helpers\TimeHelper;
$pageTitle = 'Election Control: ' . $election['title'];
$isLocked = in_array($election['status'], ['open', 'closed', 'results_published'], true);
?>

<div class="container container-wide section">
  <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <a href="<?= View::url('/admin/elections') ?>" style="text-decoration: none; font-size: 0.9rem; font-weight: 600; color: var(--text-secondary); display: inline-flex; align-items: center; gap: 0.35rem;">
      &larr; Back to Elections List
    </a>
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
      <?php if (!$isLocked): ?>
        <a href="<?= View::url('/admin/elections/edit?id=' . $election['id']) ?>" class="btn btn-secondary btn-sm" style="border-color: var(--brand-purple); color: var(--brand-purple); font-weight: 600;">
          &#9998; Edit Configuration
        </a>
      <?php endif; ?>
      <a href="<?= View::url('/election?id=' . $election['id']) ?>" class="btn btn-secondary btn-sm" target="_blank">
        Public View &rarr;
      </a>
      <a href="<?= View::url('/results?id=' . $election['id']) ?>" class="btn btn-secondary btn-sm" target="_blank">
        Live Standings &rarr;
      </a>
      <a href="<?= View::url('/admin/reports/csv?id=' . $election['id']) ?>" class="btn btn-secondary btn-sm">
        &darr; Export Tally CSV
      </a>
    </div>
  </div>

  <!-- Header Banner & State Controls -->
  <div class="card" style="padding: 2.25rem; margin-bottom: 2rem; border-top: 4px solid var(--brand-purple);">
    <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 1.25rem; margin-bottom: 1.5rem;">
      <div>
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem; flex-wrap: wrap;">
          <span class="status-pill <?= Sanitizer::e($election['status']) ?>">
            <span class="status-dot"></span>
            <span><?= ucfirst(str_replace('_', ' ', $election['status'])) ?></span>
          </span>
          <span class="badge badge-purple">
            <?= Sanitizer::e(ucwords(str_replace('_', ' ', $election['category'] ?? 'General'))) ?>
          </span>
          <span style="font-size: 0.85rem; color: var(--text-muted);">
            Visibility: <strong><?= $election['results_visibility'] === 'live_provisional' ? 'Live Provisional' : 'Confidential until closure' ?></strong>
          </span>
        </div>
        <h1 style="font-family: var(--font-display); font-size: 2.35rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem;">
          <?= Sanitizer::e($election['title']) ?>
        </h1>
        <div style="font-size: 1.15rem; font-weight: 600; color: var(--brand-purple);">
          Contested Office: <?= Sanitizer::e($election['position']) ?>
          <?php if (!empty($election['department'])): ?>
            &bull; <span style="color: var(--text-muted);"><?= Sanitizer::e($election['department']) ?></span>
          <?php endif; ?>
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
            <button type="submit" class="btn btn-outline btn-sm" style="color: var(--vermilion); border-color: var(--vermilion);">Cancel</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- Quick Stats -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; border-top: 1px solid var(--border-subtle); padding-top: 1.25rem; font-size: 0.9rem;">
      <div>
        <span style="color: var(--text-muted); display: block; font-size: 0.78rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Voting Period</span>
        <strong><?= TimeHelper::format($election['opens_at'], 'd M Y, H:i') ?></strong> &rarr; <strong><?= TimeHelper::format($election['closes_at'], 'd M Y, H:i') ?></strong>
      </div>
      <div>
        <span style="color: var(--text-muted); display: block; font-size: 0.78rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Approved Contenders</span>
        <strong><?= count($candidates) ?> candidates</strong>
      </div>
      <div>
        <span style="color: var(--text-muted); display: block; font-size: 0.78rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Eligible Whitelist</span>
        <strong><?= (int)$election['eligible_voters_count'] ?> voters</strong>
      </div>
      <div>
        <span style="color: var(--text-muted); display: block; font-size: 0.78rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Ballots Accepted</span>
        <strong style="color: var(--brand-purple); font-size: 1.15rem; font-family: var(--font-mono);"><?= (int)$election['total_votes_cast'] ?></strong>
      </div>
    </div>
  </div>

  <div class="grid grid-2" style="align-items: flex-start; gap: 2rem;">
    <!-- Column 1: Candidate Management -->
    <div>
      <div class="card" style="padding: 1.75rem;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 1.25rem;">
          <h2 style="font-family: var(--font-display); font-size: 1.6rem; margin: 0; color: var(--text-primary);">Candidates Contesting</h2>
          <span style="font-size: 0.85rem; color: var(--text-muted);"><?= count($candidates) ?> total</span>
        </div>

        <?php if ($isLocked): ?>
          <div class="alert alert-info" style="font-size: 0.85rem; margin-bottom: 1rem;">
            Candidate list is locked while election is <strong><?= Sanitizer::e($election['status']) ?></strong>.
          </div>
        <?php endif; ?>

        <div style="margin-bottom: 1.5rem;">
          <?php if (empty($candidates)): ?>
            <p style="color: var(--text-muted); padding: 1rem 0;">No candidates added yet. At least 2 approved candidates are required before opening.</p>
          <?php else: ?>
            <div class="table-container" style="border: 1px solid var(--border-subtle); margin-bottom: 1rem; border-radius: var(--radius-md);">
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
                          <div style="width: 2.25rem; height: 2.25rem; background: var(--surface-lavender); border-radius: 4px; display: flex; align-items: center; justify-content: center; font-weight: 700; color: var(--brand-purple);">
                            <?= strtoupper(substr($cand['name'], 0, 1)) ?>
                          </div>
                        <?php endif; ?>
                        <div>
                          <strong><?= Sanitizer::e($cand['name']) ?></strong>
                          <?php if (!empty($cand['user_identifier'])): ?>
                            <div style="font-size: 0.75rem; color: #16a34a; font-weight: 500;">
                              &check; Linked: <?= Sanitizer::e($cand['user_identifier']) ?>
                            </div>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td>
                        <span class="badge <?= $cand['approval_status'] === 'approved' ? 'badge-purple' : ($cand['approval_status'] === 'pending' ? 'badge-secondary' : 'badge-outline') ?>" style="font-size: 0.72rem;">
                          <?= ucfirst($cand['approval_status']) ?>
                        </span>
                        <?php if (!$isLocked): ?>
                          <div style="display: flex; gap: 0.25rem; margin-top: 0.35rem;">
                            <?php if ($cand['approval_status'] !== 'approved'): ?>
                              <form action="<?= View::url('/admin/candidates/status') ?>" method="POST" style="display: inline;">
                                <?= $csrfField ?>
                                <input type="hidden" name="candidate_id" value="<?= (int)$cand['id'] ?>">
                                <input type="hidden" name="election_id" value="<?= (int)$election['id'] ?>">
                                <input type="hidden" name="status" value="approved">
                                <button type="submit" class="btn btn-secondary btn-sm" style="padding: 0.15rem 0.4rem; font-size: 0.68rem; color: #16a34a; border-color: #16a34a;">Approve</button>
                              </form>
                            <?php endif; ?>
                            <?php if ($cand['approval_status'] !== 'rejected'): ?>
                              <form action="<?= View::url('/admin/candidates/status') ?>" method="POST" style="display: inline;">
                                <?= $csrfField ?>
                                <input type="hidden" name="candidate_id" value="<?= (int)$cand['id'] ?>">
                                <input type="hidden" name="election_id" value="<?= (int)$election['id'] ?>">
                                <input type="hidden" name="status" value="rejected">
                                <button type="submit" class="btn btn-secondary btn-sm" style="padding: 0.15rem 0.4rem; font-size: 0.68rem; color: var(--vermilion); border-color: var(--vermilion);">Reject</button>
                              </form>
                            <?php endif; ?>
                          </div>
                        <?php endif; ?>
                      </td>
                      <td style="max-width: 200px; font-size: 0.8rem; color: var(--text-muted);">
                        <?= Sanitizer::e(substr($cand['manifesto'] ?: 'No manifesto', 0, 60)) ?>...
                      </td>
                      <?php if (!$isLocked): ?>
                        <td style="white-space: nowrap;">
                          <form action="<?= View::url('/admin/candidates/delete') ?>" method="POST" style="display: inline;" onsubmit="return confirm('Remove candidate <?= Sanitizer::e($cand['name']) ?>?');">
                            <?= $csrfField ?>
                            <input type="hidden" name="candidate_id" value="<?= (int)$cand['id'] ?>">
                            <input type="hidden" name="election_id" value="<?= (int)$election['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline" style="padding: 0.2rem 0.5rem; font-size: 0.75rem; color: var(--vermilion); border-color: var(--vermilion);">Delete</button>
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
          <div style="background-color: var(--surface-secondary); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-subtle);">
            <h4 style="font-family: var(--font-display); font-size: 1.25rem; margin-bottom: 0.85rem; color: var(--text-primary);">+ Add Approved Contender</h4>
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
                <div class="form-help" style="color: var(--text-muted);">Links this candidacy to the student/staff account so they can sign in via Candidate Portal.</div>
              </div>

              <div class="form-group">
                <label for="name" class="form-label">Candidate Name <span class="required" style="color: var(--vermilion);">*</span></label>
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

              <button type="submit" class="btn btn-primary btn-block" style="justify-content: center;">Add Candidate</button>
            </form>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Column 2: Voter Eligibility Provisioning -->
    <div>
      <div class="card" style="padding: 1.75rem;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 1.25rem;">
          <h2 style="font-family: var(--font-display); font-size: 1.6rem; margin: 0; color: var(--text-primary);">Voter Whitelist</h2>
          <span style="font-size: 0.85rem; color: var(--text-muted);"><?= (int)$election['eligible_voters_count'] ?> assigned</span>
        </div>

        <?php if ($isLocked): ?>
          <div class="alert alert-info" style="font-size: 0.85rem; margin-bottom: 1rem;">
            Voter whitelist is locked while election is <strong><?= Sanitizer::e($election['status']) ?></strong>.
          </div>
        <?php endif; ?>

        <!-- Batch Assignment Controls (only if not locked) -->
        <?php if (!$isLocked): ?>
          <div style="background-color: var(--surface-secondary); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 1.5rem; margin-bottom: 1.5rem;">
            <h4 style="font-family: var(--font-display); font-size: 1.25rem; margin-bottom: 0.5rem; color: var(--text-primary);">Whitelist Criteria Assignment</h4>
            <p style="font-size: 0.825rem; color: var(--text-muted); margin-bottom: 1rem;">
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

              <button type="submit" class="btn btn-primary btn-sm btn-block" style="justify-content: center;">
                Authorize Voters by Criteria
              </button>
            </form>

            <div style="border-top: 1px solid var(--border-subtle); margin-top: 1.25rem; padding-top: 1rem;">
              <h5 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--text-primary);">Enroll Single Voter by Roll Number / ID</h5>
              <form action="<?= View::url('/admin/eligibility/assign-individual') ?>" method="POST" style="display: flex; gap: 0.5rem; align-items: flex-end;">
                <?= $csrfField ?>
                <input type="hidden" name="election_id" value="<?= (int)$election['id'] ?>">
                <div style="flex: 1;">
                  <label for="individual_identifier" class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Institutional Roll No / ID</label>
                  <input type="text" id="individual_identifier" name="identifier" class="form-control" style="font-size: 0.85rem;" required placeholder="e.g. BCA2024-001">
                </div>
                <button type="submit" class="btn btn-secondary btn-sm" style="height: 38px;">
                  + Enroll Voter
                </button>
              </form>
            </div>
          </div>
        <?php endif; ?>

        <!-- Eligible Voters Table -->
        <h4 style="font-family: var(--font-display); font-size: 1.2rem; color: var(--text-primary); margin-bottom: 0.75rem;">Enrolled Voters</h4>
        <div class="table-container" style="max-height: 400px; overflow-y: auto; border: 1px solid var(--border-subtle); border-radius: var(--radius-md);">
          <table class="table">
            <thead>
              <tr>
                <th>Voter ID</th>
                <th>Name &amp; Dept</th>
                <th>Role</th>
                <th>Participation</th>
                <?php if (!$isLocked): ?>
                  <th>Action</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($eligibleVoters)): ?>
                <tr>
                  <td colspan="<?= !$isLocked ? '5' : '4' ?>" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">
                    No voters enrolled on whitelist yet.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($eligibleVoters as $voter): ?>
                  <tr>
                    <td><strong><?= Sanitizer::e($voter['identifier']) ?></strong></td>
                    <td>
                      <?= Sanitizer::e($voter['full_name']) ?>
                      <div style="font-size: 0.75rem; color: var(--text-muted);"><?= Sanitizer::e($voter['department'] ?: '—') ?></div>
                    </td>
                    <td><span class="role-badge" style="font-size: 0.7rem;"><?= Sanitizer::e($voter['role']) ?></span></td>
                    <td>
                      <?php if ((bool)$voter['has_participated']): ?>
                        <span class="badge" style="background-color: var(--surface-lavender); color: var(--brand-purple); font-size: 0.75rem; font-weight: 700;">
                          &check; Voted
                        </span>
                      <?php else: ?>
                        <span style="font-size: 0.8rem; color: var(--text-muted);">Awaiting</span>
                      <?php endif; ?>
                    </td>
                    <?php if (!$isLocked): ?>
                      <td>
                        <?php if (!(bool)$voter['has_participated']): ?>
                          <form action="<?= View::url('/admin/eligibility/remove') ?>" method="POST" onsubmit="return confirm('Remove voter <?= Sanitizer::e($voter['identifier']) ?> from this election whitelist?');">
                            <?= $csrfField ?>
                            <input type="hidden" name="election_id" value="<?= (int)$election['id'] ?>">
                            <input type="hidden" name="user_id" value="<?= (int)$voter['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline" style="padding: 0.15rem 0.4rem; font-size: 0.7rem; color: var(--vermilion); border-color: var(--vermilion);">Remove</button>
                          </form>
                        <?php else: ?>
                          <span style="font-size: 0.75rem; color: var(--text-muted);">Locked (Voted)</span>
                        <?php endif; ?>
                      </td>
                    <?php endif; ?>
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
