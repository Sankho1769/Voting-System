<?php
use App\Core\View;
use App\Helpers\Sanitizer;
$pageTitle = 'Create Election';
?>

<div class="container container-narrow">
  <div style="margin-bottom: 1.5rem;">
    <a href="<?= View::url('/admin/elections') ?>" style="text-decoration: none; font-size: 0.9rem; font-weight: 500;">
      &larr; Back to Elections
    </a>
  </div>

  <div class="card card-ivory" style="padding: 2.25rem;">
    <div style="margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--color-border-medium);">
      <h2>Create Collegiate Election</h2>
      <p style="color: var(--color-text-secondary); font-size: 0.95rem;">
        Initialize a single-position election ballot. After creation, you will add candidates and configure voter eligibility before scheduling.
      </p>
    </div>

    <form action="<?= View::url('/admin/elections/create') ?>" method="POST" autocomplete="off">
      <?= $csrfField ?>

      <div class="form-group">
        <label for="title" class="form-label">
          Election Title <span class="required">*</span>
        </label>
        <input 
          type="text" 
          id="title" 
          name="title" 
          class="form-control" 
          placeholder="e.g. Student Union General Election 2026"
          required 
          autofocus
        >
      </div>

      <div class="form-group">
        <label for="position" class="form-label">
          Single Contested Position <span class="required">*</span>
        </label>
        <input 
          type="text" 
          id="position" 
          name="position" 
          class="form-control" 
          placeholder="e.g. President or Class Representative"
          required
        >
        <div class="form-help">Each election represents exactly one institutional office.</div>
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
            Voting Opens At <span class="required">*</span>
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
            Voting Closes At <span class="required">*</span>
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
          Result Visibility Policy <span class="required">*</span>
        </label>
        <select id="results_visibility" name="results_visibility" class="form-control" required>
          <option value="live_provisional">Live Provisional Standings (Authenticated voters can observe live aggregate turnout)</option>
          <option value="after_closure_only">Standings Hidden Until Official Closure (Confidential tally until election ends)</option>
        </select>
      </div>

      <button type="submit" class="btn btn-primary btn-block btn-lg">
        Create Draft Election &rarr;
      </button>
    </form>
  </div>
</div>
