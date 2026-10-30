<?php
use App\Core\View;
use App\Helpers\Sanitizer;
use App\Helpers\TimeHelper;
$pageTitle = 'Election Officer Portal';
?>

<div class="container container-wide">
  <div class="page-header">
    <div class="page-header-title">
      <h1>Election Officer Desk</h1>
      <div class="page-header-subtitle">
        Supervised Collegiate Elections &bull; Candidate Certification &bull; Ballot Oversight
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h2>Assigned Collegiate Elections</h2>
    </div>

    <?php if (empty($elections)): ?>
      <p style="color: var(--color-text-muted); padding: 1.5rem 0;">No active elections currently assigned to your desk.</p>
    <?php else: ?>
      <div class="table-container">
        <table class="table">
          <thead>
            <tr>
              <th>Title &amp; Position</th>
              <th>Status</th>
              <th>Approved Candidates</th>
              <th>Eligible Voters</th>
              <th>Votes Cast</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($elections as $el): ?>
              <tr>
                <td>
                  <strong><?= Sanitizer::e($el['title']) ?></strong>
                  <div style="font-size: 0.8rem; color: var(--color-text-muted);"><?= Sanitizer::e($el['position']) ?></div>
                </td>
                <td>
                  <span class="badge badge-<?= Sanitizer::e($el['status']) ?>">
                    <?= ucfirst(str_replace('_', ' ', $el['status'])) ?>
                  </span>
                </td>
                <td><?= (int)$el['approved_candidates_count'] ?></td>
                <td><?= (int)$el['eligible_voters_count'] ?></td>
                <td><strong><?= (int)$el['total_votes_cast'] ?></strong></td>
                <td>
                  <a href="<?= View::url('/admin/elections/detail?id=' . $el['id']) ?>" class="btn btn-secondary btn-sm">
                    Manage &rarr;
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
