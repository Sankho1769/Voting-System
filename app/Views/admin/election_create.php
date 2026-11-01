<?php
use App\Core\View;
use App\Helpers\Sanitizer;
use App\Services\ElectionService;

$pageTitle = 'Create Election';
$categories = ElectionService::getCategories();
?>

<div class="container container-narrow section">
  <div style="margin-bottom: 1.5rem;">
    <a href="<?= View::url('/admin/elections') ?>" style="text-decoration: none; font-size: 0.9rem; font-weight: 600; color: var(--text-secondary); display: inline-flex; align-items: center; gap: 0.35rem;">
      &larr; Back to Elections Management
    </a>
  </div>

  <div class="card" style="padding: 2.5rem; border-top: 4px solid var(--brand-purple);">
    <div style="margin-bottom: 1.75rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-subtle);">
      <span class="badge badge-purple" style="margin-bottom: 0.5rem;">NEW BALLOT INITIALIZER</span>
      <h1 style="font-family: var(--font-display); font-size: 2.25rem; font-weight: 700; color: var(--text-primary); margin: 0 0 0.35rem 0;">
        Create Collegiate Election
      </h1>
      <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0;">
        Initialize a single-position election ballot. After creation, add contenders and configure voter eligibility whitelists before opening.
      </p>
    </div>

    <form action="<?= View::url('/admin/elections/create') ?>" method="POST" autocomplete="off">
      <?= $csrfField ?>

      <div class="form-group">
        <label for="title" class="form-label">
          Election Title <span class="required" style="color: var(--vermilion);">*</span>
        </label>
        <input 
          type="text" 
          id="title" 
          name="title" 
          class="form-control" 
          placeholder="e.g. Student Council General Election 2026"
          required 
          autofocus
        >
      </div>

      <div class="grid grid-2" style="gap: 1rem;">
        <div class="form-group">
          <label for="category" class="form-label">
            Election Category <span class="required" style="color: var(--vermilion);">*</span>
          </label>
          <select id="category" name="category" class="form-control" required>
            <?php foreach ($categories as $catKey => $catInfo): ?>
              <option value="<?= Sanitizer::e($catKey) ?>"><?= Sanitizer::e($catInfo['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-help" style="color: var(--text-muted);">Used for CricHeroes discovery categorization.</div>
        </div>

        <div class="form-group">
          <label for="department" class="form-label">
            Target Department / Branch
          </label>
          <input 
            type="text" 
            id="department" 
            name="department" 
            class="form-control" 
            placeholder="e.g. BCA, Computer Science, or Campus-Wide"
          >
          <div class="form-help" style="color: var(--text-muted);">Leave empty for campus-wide elections.</div>
        </div>
      </div>

      <div class="form-group">
        <label for="position" class="form-label">
          Single Contested Position <span class="required" style="color: var(--vermilion);">*</span>
        </label>
        <input 
          type="text" 
          id="position" 
          name="position" 
          class="form-control" 
          placeholder="e.g. President, Vice President, or Class Representative"
          required
        >
        <div class="form-help" style="color: var(--text-muted);">Each election represents exactly one institutional office.</div>
      </div>

      <div class="form-group">
        <label for="description" class="form-label">Instructions &amp; Description</label>
        <textarea 
          id="description" 
          name="description" 
          class="form-control" 
          rows="3" 
          placeholder="Detailed voter guidelines, manifesto review notes, or institutional context."
        ></textarea>
      </div>

      <div class="grid grid-2" style="gap: 1rem;">
        <div class="form-group">
          <label for="opens_at" class="form-label">
            Voting Opens At <span class="required" style="color: var(--vermilion);">*</span>
          </label>
          <input 
            type="datetime-local" 
            id="opens_at" 
            name="opens_at" 
            class="form-control" 
            required 
            value="<?= date('Y-m-d\TH:i', time() + 300) ?>"
          >
        </div>

        <div class="form-group">
          <label for="closes_at" class="form-label">
            Voting Closes At <span class="required" style="color: var(--vermilion);">*</span>
          </label>
          <input 
            type="datetime-local" 
            id="closes_at" 
            name="closes_at" 
            class="form-control" 
            required
            value="<?= date('Y-m-d\TH:i', time() + 86400) ?>"
          >
        </div>
      </div>

      <div class="form-group" style="margin-bottom: 2rem;">
        <label for="results_visibility" class="form-label">
          Result Visibility Policy <span class="required" style="color: var(--vermilion);">*</span>
        </label>
        <select id="results_visibility" name="results_visibility" class="form-control" required>
          <option value="live_provisional">Live Provisional Standings (Authenticated voters can observe live aggregate turnout)</option>
          <option value="after_closure_only">Standings Hidden Until Official Closure (Confidential tally until election ends)</option>
        </select>
      </div>

      <button type="submit" class="btn btn-primary btn-block btn-lg" style="justify-content: center;">
        Create Draft Election &rarr;
      </button>
    </form>
  </div>
</div>
