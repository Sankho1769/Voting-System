<?php
use App\Core\View;
use App\Helpers\Sanitizer;
use App\Helpers\TimeHelper;
$pageTitle = 'Institutional Reports';
?>

<div class="container container-wide">
  <div class="page-header">
    <div class="page-header-title">
      <h1>Institutional Reports &amp; Analytics</h1>
      <div class="page-header-subtitle">
        Certified election tallies &bull; Voter turnout audits &bull; Safe CSV exports
      </div>
    </div>
  </div>

  <div class="card" style="padding: 0; overflow: hidden;">
    <div class="card-header" style="margin: 1.5rem 1.5rem 0 1.5rem;">
      <h2>Election Tally Reports</h2>
    </div>

    <div class="table-container" style="border: none; margin: 0;">
      <table class="table">
        <thead>
          <tr>
            <th>Election &amp; Office</th>
            <th>Status</th>
            <th>Valid Ballots</th>
            <th>Eligible Voters</th>
            <th>Turnout</th>
            <th style="text-align: right;">Export Certified Reports</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($elections)): ?>
            <tr>
              <td colspan="6" style="text-align: center; color: var(--color-text-muted); padding: 2rem;">
                No elections available.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($elections as $el): ?>
              <?php
                $turnout = $el['eligible_voters_count'] > 0 
                  ? round(($el['total_votes_cast'] / $el['eligible_voters_count']) * 100, 1) 
                  : 0;
              ?>
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
                <td><strong><?= (int)$el['total_votes_cast'] ?></strong></td>
                <td><?= (int)$el['eligible_voters_count'] ?></td>
                <td><?= $turnout ?>%</td>
                <td style="text-align: right;">
                  <a href="<?= View::url('/admin/reports/csv?id=' . $el['id']) ?>" class="btn btn-secondary btn-sm">
                    &darr; Candidate Results CSV
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
