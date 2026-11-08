<?php
use App\Core\View;
use App\Helpers\Sanitizer;
use App\Services\ElectionService;

$pageTitle = 'Edit Election Configuration: ' . $election['title'];
$categories = ElectionService::getCategories();
?>

<div class="container container-narrow section">
  <div style="margin-bottom: 1.5rem;">
    <a href="<?= View::url('/admin/elections/detail?id=' . $election['id']) ?>" style="text-decoration: none; font-size: 0.9rem; font-weight: 600; color: var(--text-secondary); display: inline-flex; align-items: center; gap: 0.35rem;">
      &larr; Back to Election Control Center
    </a>
  </div>

  <div class="card" style="padding: 2.25rem; border-top: 4px solid var(--brand-purple);">
    <div style="margin-bottom: 1.75rem;">
      <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
        <span class="status-pill <?= Sanitizer::e($election['status']) ?>">
          <span class="status-dot"></span>
          <span>Status: <?= ucfirst(str_replace('_', ' ', $election['status'])) ?></span>
        </span>
        <span class="badge badge-purple">Configuration Mode</span>
      </div>
      <h1 style="font-family: var(--font-display); font-size: 2.25rem; font-weight: 700; color: var(--text-primary); margin: 0 0 0.25rem 0;">
        Edit Election Configuration
      </h1>
      <div style="color: var(--text-muted); font-size: 0.95rem;">
        Modify ballot metadata, scheduling windows, and visibility policies.
      </div>
    </div>

    <form action="<?= View::url('/admin/elections/edit?id=' . $election['id']) ?>" method="POST">
      <?= $csrfField ?>
      <input type="hidden" name="id" value="<?= (int)$election['id'] ?>">

      <div class="form-group">
        <label for="title" class="form-label">Election Title <span style="color: var(--vermilion);">*</span></label>
        <input
          type="text"
          id="title"
          name="title"
          class="form-control"
          required
          value="<?= Sanitizer::e($election['title']) ?>"
          placeholder="e.g. 2026 Student Council Presidential Election"
        >
      </div>

      <div class="grid grid-2" style="gap: 1rem;">
        <div class="form-group">
          <label for="position" class="form-label">Contested Position / Office <span style="color: var(--vermilion);">*</span></label>
          <input
            type="text"
            id="position"
            name="position"
            class="form-control"
            required
            value="<?= Sanitizer::e($election['position']) ?>"
            placeholder="e.g. Council President"
          >
        </div>

        <div class="form-group">
          <label for="category" class="form-label">Electoral Category</label>
          <select id="category" name="category" class="form-control">
            <?php foreach ($categories as $catKey => $catLabel): ?>
              <option value="<?= Sanitizer::e($catKey) ?>" <?= ($election['category'] ?? '') === $catKey ? 'selected' : '' ?>>
                <?= Sanitizer::e($catLabel) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label for="department" class="form-label">Department Scope (Optional)</label>
        <input
          type="text"
          id="department"
          name="department"
          class="form-control"
          value="<?= Sanitizer::e($election['department'] ?? '') ?>"
          placeholder="Leave blank for college-wide elections, or enter department name"
        >
        <div class="form-help" style="color: var(--text-muted); font-size: 0.8rem;">
          Leave empty for campus-wide universal collegiate elections.
        </div>
      </div>

      <div class="form-group">
        <label for="description" class="form-label">Election Overview &amp; Guidelines</label>
        <textarea
          id="description"
          name="description"
          class="form-control"
          rows="3"
          placeholder="Detailed election scope, voting eligibility requirements, and office duties..."
        ><?= Sanitizer::e($election['description'] ?? '') ?></textarea>
      </div>

      <div class="grid grid-2" style="gap: 1rem;">
        <div class="form-group">
          <label for="opens_at" class="form-label">Voting Opens At <span style="color: var(--vermilion);">*</span></label>
          <input
            type="datetime-local"
            id="opens_at"
            name="opens_at"
            class="form-control"
            required
            value="<?= date('Y-m-d\TH:i', strtotime($election['opens_at'])) ?>"
          >
        </div>

        <div class="form-group">
          <label for="closes_at" class="form-label">Voting Closes At <span style="color: var(--vermilion);">*</span></label>
          <input
            type="datetime-local"
            id="closes_at"
            name="closes_at"
            class="form-control"
            required
            value="<?= date('Y-m-d\TH:i', strtotime($election['closes_at'])) ?>"
          >
        </div>
      </div>

      <div class="form-group">
        <label for="results_visibility" class="form-label">Live Results Visibility Policy</label>
        <select id="results_visibility" name="results_visibility" class="form-control">
          <option value="live_provisional" <?= ($election['results_visibility'] ?? '') === 'live_provisional' ? 'selected' : '' ?>>
            Live Provisional Standings (Transparent real-time tally during voting)
          </option>
          <option value="after_closure_only" <?= ($election['results_visibility'] ?? '') === 'after_closure_only' ? 'selected' : '' ?>>
            Confidential until Closure (Results hidden until election is officially closed)
          </option>
        </select>
      </div>

      <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 2rem;">
        <a href="<?= View::url('/admin/elections/detail?id=' . $election['id']) ?>" class="btn btn-secondary">
          Cancel
        </a>
        <button type="submit" class="btn btn-primary">
          Save Configuration Changes
        </button>
      </div>
    </form>
  </div>
</div>
