<?php
use App\Core\View;
use App\Helpers\Sanitizer;
use App\Helpers\TimeHelper;
$pageTitle = 'Authorized Accounts';
?>

<div class="container container-wide">
  <div class="page-header">
    <div class="page-header-title">
      <h1>Authorized Collegiate Accounts</h1>
      <div class="page-header-subtitle">
        Students, Faculty, Election Officers &bull; Public registration strictly disabled
      </div>
    </div>
    <div style="display: flex; gap: 0.75rem;">
      <a href="<?= View::url('/admin/users/create') ?>" class="btn btn-primary btn-sm">+ Provision Account</a>
      <a href="<?= View::url('/admin/users/import') ?>" class="btn btn-secondary btn-sm">&uarr; Bulk Import CSV</a>
    </div>
  </div>

  <!-- Filter & Search Bar -->
  <div class="card" style="padding: 1.25rem; margin-bottom: 1.5rem;">
    <form action="<?= View::url('/admin/users') ?>" method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
      <div style="flex: 1; min-width: 220px;">
        <label for="search" class="form-label">Search Identifier or Name</label>
        <input 
          type="text" 
          id="search" 
          name="search" 
          class="form-control" 
          placeholder="e.g. BCA2024 or John" 
          value="<?= Sanitizer::e($filters['search'] ?? '') ?>"
        >
      </div>

      <div style="width: 160px;">
        <label for="role" class="form-label">Filter Role</label>
        <select id="role" name="role" class="form-control">
          <option value="">All Roles</option>
          <option value="student" <?= ($filters['role'] ?? '') === 'student' ? 'selected' : '' ?>>Student</option>
          <option value="staff" <?= ($filters['role'] ?? '') === 'staff' ? 'selected' : '' ?>>Staff</option>
          <option value="candidate" <?= ($filters['role'] ?? '') === 'candidate' ? 'selected' : '' ?>>Candidate</option>
          <option value="election_officer" <?= ($filters['role'] ?? '') === 'election_officer' ? 'selected' : '' ?>>Election Officer</option>
          <option value="admin" <?= ($filters['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrator</option>
        </select>
      </div>

      <div style="width: 140px;">
        <label for="status" class="form-label">Status</label>
        <select id="status" name="status" class="form-control">
          <option value="">All Statuses</option>
          <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="suspended" <?= ($filters['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspended</option>
          <option value="deactivated" <?= ($filters['status'] ?? '') === 'deactivated' ? 'selected' : '' ?>>Deactivated</option>
        </select>
      </div>

      <div>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <a href="<?= View::url('/admin/users') ?>" class="btn btn-secondary" style="margin-left: 0.25rem;">Reset</a>
      </div>
    </form>
  </div>

  <!-- Users Table -->
  <div class="card" style="padding: 0; overflow: hidden;">
    <div class="table-container" style="border: none; margin: 0;">
      <table class="table">
        <thead>
          <tr>
            <th>Institutional ID</th>
            <th>Full Name &amp; Email</th>
            <th>Role</th>
            <th>Department / Year</th>
            <th>Status</th>
            <th>Temp Password</th>
            <th>Provisioned</th>
            <th style="text-align: right;">Administrative Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($users)): ?>
            <tr>
              <td colspan="8" style="text-align: center; color: var(--color-text-muted); padding: 2rem;">
                No matching collegiate accounts found.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($users as $u): ?>
              <tr>
                <td>
                  <strong><?= Sanitizer::e($u['identifier']) ?></strong>
                </td>
                <td>
                  <?= Sanitizer::e($u['full_name']) ?>
                  <?php if (!empty($u['email'])): ?>
                    <div style="font-size: 0.78rem; color: var(--color-text-muted);"><?= Sanitizer::e($u['email']) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="role-badge" style="font-size: 0.75rem;">
                    <?= Sanitizer::e($u['role']) ?>
                  </span>
                </td>
                <td>
                  <?= Sanitizer::e($u['department'] ?: '—') ?>
                  <?php if (!empty($u['academic_year'])): ?>
                    <span style="font-size: 0.8rem; color: var(--color-text-muted);">(<?= Sanitizer::e($u['academic_year']) ?>)</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge" style="background-color: <?= $u['status'] === 'active' ? 'var(--color-emerald-subtle)' : 'var(--color-vermilion-subtle)' ?>; color: <?= $u['status'] === 'active' ? 'var(--color-emerald-dark)' : 'var(--color-vermilion-dark)' ?>;">
                    <?= ucfirst($u['status']) ?>
                  </span>
                </td>
                <td>
                  <?php if ((bool)$u['must_change_password']): ?>
                    <span style="color: var(--color-vermilion); font-size: 0.8rem; font-weight: 600;">Pending Change</span>
                  <?php else: ?>
                    <span style="color: var(--color-emerald-green); font-size: 0.8rem;">Established</span>
                  <?php endif; ?>
                </td>
                <td style="font-size: 0.825rem; color: var(--color-text-muted);">
                  <?= TimeHelper::format($u['created_at'], 'd M Y') ?>
                </td>
                <td style="text-align: right;">
                  <!-- Reset Temp Password -->
                  <form action="<?= View::url('/admin/users/reset-password') ?>" method="POST" style="display: inline-block;" onsubmit="return confirm('Generate a new temporary password for <?= Sanitizer::e($u['identifier']) ?>?');">
                    <?= $csrfField ?>
                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                    <button type="submit" class="btn btn-secondary btn-sm" title="Issue Temporary Password">
                      Reset Password
                    </button>
                  </form>

                  <!-- Toggle Status -->
                  <?php if ($u['id'] !== $currentUser['id']): ?>
                    <form action="<?= View::url('/admin/users/status') ?>" method="POST" style="display: inline-block; margin-left: 0.25rem;">
                      <?= $csrfField ?>
                      <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                      <input type="hidden" name="status" value="<?= $u['status'] === 'active' ? 'suspended' : 'active' ?>">
                      <button type="submit" class="btn btn-sm <?= $u['status'] === 'active' ? 'btn-danger' : 'btn-secondary' ?>">
                        <?= $u['status'] === 'active' ? 'Suspend' : 'Activate' ?>
                      </button>
                    </form>
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
