<?php
use App\Core\View;
use App\Helpers\Sanitizer;
use App\Helpers\TimeHelper;
$pageTitle = 'Manage Elections';
?>

<div class="container container-wide">
  <div class="page-header">
    <div class="page-header-title">
      <h1>Collegiate Elections</h1>
      <div class="page-header-subtitle">
        Single-position ballot configurations &bull; Lifecycle governance &bull; Voter eligibility
      </div>
    </div>
    <div>
      <a href="<?= View::url('/admin/elections/create') ?>" class="btn btn-primary btn-sm">+ Create Election</a>
    </div>
  </div>

  <div class="card" style="padding: 0; overflow: hidden;">
    <div class="table-container" style="border: none; margin: 0;">
      <table class="table">
        <thead>
          <tr>
            <th>Election Title &amp; Position</th>
            <th>Lifecycle Status</th>
            <th>Approved Contenders</th>
            <th>Eligible Voters</th>
            <th>Votes Cast</th>
            <th>Schedule Window</th>
            <th>Results Visibility</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($elections)): ?>
            <tr>
              <td colspan="8" style="text-align: center; color: var(--color-text-muted); padding: 2.5rem;">
                No elections created yet. Click "Create Election" to initialize a ballot.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($elections as $el): ?>
              <tr>
                <td>
                  <strong><?= Sanitizer::e($el['title']) ?></strong>
                  <div style="font-size: 0.8rem; color: var(--color-text-muted);">Position: <?= Sanitizer::e($el['position']) ?></div>
                </td>
                <td>
                  <span class="badge badge-<?= Sanitizer::e($el['status']) ?>">
                    <?= ucfirst(str_replace('_', ' ', $el['status'])) ?>
                  </span>
                </td>
                <td><?= (int)$el['approved_candidates_count'] ?></td>
                <td><?= (int)$el['eligible_voters_count'] ?></td>
                <td><strong><?= (int)$el['total_votes_cast'] ?></strong></td>
                <td style="font-size: 0.825rem;">
                  <div>Opens: <?= TimeHelper::format($el['opens_at'], 'd M, H:i') ?></div>
                  <div>Closes: <?= TimeHelper::format($el['closes_at'], 'd M, H:i') ?></div>
                </td>
                <td style="font-size: 0.8rem; color: var(--color-text-secondary);">
                  <?= $el['results_visibility'] === 'live_provisional' ? 'Live Provisional' : 'After Closure Only' ?>
                </td>
                <td style="text-align: right;">
                  <a href="<?= View::url('/admin/elections/detail?id=' . $el['id']) ?>" class="btn btn-secondary btn-sm">
                    Manage Ballot &rarr;
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
