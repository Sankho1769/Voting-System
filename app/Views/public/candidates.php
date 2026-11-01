<?php
use App\Core\Auth;
use App\Core\View;
use App\Helpers\Sanitizer;
?>

<div class="container section">

  <!-- Header -->
  <div style="margin-bottom: 2.5rem;">
    <span class="badge badge-purple" style="margin-bottom: 0.5rem;">CAMPUS CONTENDERS</span>
    <h1 style="font-family: var(--font-display); font-size: 2.75rem; font-weight: 700; color: var(--text-primary); margin: 0;">
      Candidates Directory
    </h1>
    <p style="color: var(--text-muted); margin: 0.35rem 0 0 0; font-size: 1.05rem;">
      Meet the certified student and faculty contenders running in campus elections.
    </p>
  </div>

  <!-- Search & Filter Bar -->
  <div class="search-filter-bar" style="margin-bottom: 2rem;">
    <div class="filter-bar-inner">
      <div class="filter-input-group" style="flex: 1; position: relative;">
        <span style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </span>
        <input type="text" id="candidate-search" class="form-input" placeholder="Search contenders by name, election, or department..." style="padding-left: 2.75rem; border-radius: var(--radius-md);" oninput="filterCandidates(this.value)">
      </div>
    </div>
  </div>

  <!-- Candidates Grid -->
  <?php if (!empty($candidates)): ?>
    <div class="grid grid-2" id="candidates-container">
      <?php foreach ($candidates as $cand): ?>
        <?php
          $candSearch = strtolower(($cand['name'] ?? '') . ' ' . ($cand['election_title'] ?? '') . ' ' . ($cand['department'] ?? ''));
        ?>
        <div class="card candidate-list-card" data-search="<?= Sanitizer::e($candSearch) ?>" style="padding: 1.75rem; display: flex; gap: 1.25rem;">
          <div class="candidate-avatar" style="flex-shrink: 0; width: 68px; height: 68px;">
            <?php if (!empty($cand['photo_url'])): ?>
              <img src="<?= Sanitizer::e($cand['photo_url']) ?>" alt="<?= Sanitizer::e($cand['name']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
            <?php else: ?>
              <span style="font-family: var(--font-display); font-weight: 800; font-size: 1.75rem; color: var(--brand-purple);">
                <?= Sanitizer::e(strtoupper(substr($cand['name'], 0, 1))) ?>
              </span>
            <?php endif; ?>
          </div>

          <div style="flex: 1; min-width: 0;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.25rem;">
              <h3 style="font-family: var(--font-display); font-size: 1.45rem; font-weight: 700; color: var(--text-primary); margin: 0;">
                <?= Sanitizer::e($cand['name']) ?>
              </h3>
              <span class="badge badge-outline" style="font-size: 0.75rem; font-family: var(--font-mono);">
                <?= Sanitizer::e($cand['candidate_id_string'] ?? ('CAND-' . $cand['id'])) ?>
              </span>
            </div>

            <div style="font-size: 0.85rem; color: var(--brand-purple); font-weight: 600; margin-bottom: 0.5rem;">
              Contesting: 
              <a href="<?= View::url('/election?id=' . (int)$cand['election_id']) ?>" style="color: inherit; text-decoration: underline;">
                <?= Sanitizer::e($cand['election_title'] ?? 'Campus Election') ?>
              </a>
            </div>

            <?php if (!empty($cand['manifesto'])): ?>
              <p style="color: var(--text-secondary); font-size: 0.88rem; line-height: 1.5; margin: 0 0 0.75rem 0; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                &ldquo;<?= Sanitizer::e($cand['manifesto']) ?>&rdquo;
              </p>
            <?php else: ?>
              <p style="color: var(--text-muted); font-size: 0.85rem; font-style: italic; margin: 0 0 0.75rem 0;">
                Official campaign statement pending.
              </p>
            <?php endif; ?>

            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 0.75rem; border-top: 1px dashed var(--border-subtle); font-size: 0.82rem;">
              <span style="color: var(--text-muted);">
                Dept: <strong><?= Sanitizer::e($cand['department'] ?? 'General') ?></strong>
              </span>
              <a href="<?= View::url('/election?id=' . (int)$cand['election_id']) ?>" class="btn btn-secondary btn-sm" style="padding: 0.25rem 0.65rem; font-size: 0.78rem;">
                View Ballot &rarr;
              </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty-state" style="background-color: var(--surface-card); border-radius: var(--radius-lg); padding: 3.5rem; text-align: center; border: 1px solid var(--border-subtle);">
      <h3 style="font-family: var(--font-display); font-size: 1.75rem; color: var(--text-primary); margin-bottom: 0.5rem;">No Approved Candidates Registered</h3>
      <p style="color: var(--text-muted); max-width: 480px; margin: 0 auto 1.5rem auto;">
        Candidates will appear here once officially approved by election administrators.
      </p>
      <a href="<?= View::url('/explore') ?>" class="btn btn-secondary btn-sm">Explore Active Elections</a>
    </div>
  <?php endif; ?>

</div>

<script>
function filterCandidates(query) {
  const q = query.toLowerCase().trim();
  const cards = document.querySelectorAll('.candidate-list-card');
  cards.forEach(card => {
    const text = card.dataset.search || card.textContent.toLowerCase();
    card.style.display = (q === '' || text.includes(q)) ? 'flex' : 'none';
  });
}
</script>
