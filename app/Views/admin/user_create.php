<?php
use App\Core\View;
use App\Helpers\Sanitizer;
$pageTitle = 'Provision Collegiate Account';
?>

<div class="container container-narrow">
  <div style="margin-bottom: 1.5rem;">
    <a href="<?= View::url('/admin/users') ?>" style="text-decoration: none; font-size: 0.9rem; font-weight: 500;">
      &larr; Back to Authorized Accounts
    </a>
  </div>

  <div class="card card-ivory" style="padding: 2.25rem;">
    <div style="margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--color-border-medium);">
      <h2>Provision Collegiate Account</h2>
      <p style="color: var(--color-text-secondary); font-size: 0.95rem;">
        Authorize a new student, faculty member, or election officer. A cryptographically random temporary password will be generated for initial handoff.
      </p>
    </div>

    <form action="<?= View::url('/admin/users/create') ?>" method="POST" autocomplete="off">
      <?= $csrfField ?>

      <div class="form-group">
        <label for="identifier" class="form-label">
          Institutional Identifier (Roll No. or Employee ID) <span class="required">*</span>
        </label>
        <input 
          type="text" 
          id="identifier" 
          name="identifier" 
          class="form-control" 
          placeholder="e.g. BCA2024-042 or EMP-209"
          required 
          autofocus
        >
        <div class="form-help">Unique college roll number or faculty identity number.</div>
      </div>

      <div class="form-group">
        <label for="role" class="form-label">
          Institutional Role <span class="required">*</span>
        </label>
        <select id="role" name="role" class="form-control" required>
          <option value="student">Student (Class/Union elections eligible)</option>
          <option value="staff">Staff / Faculty (Staff representative elections)</option>
          <option value="candidate">Candidate (Contender account for Candidate Portal)</option>
          <option value="election_officer">Election Officer (Assigned election manager)</option>
          <option value="admin">System Administrator (Full governance)</option>
        </select>
      </div>

      <div class="form-group">
        <label for="full_name" class="form-label">
          Full Legal / Academic Name <span class="required">*</span>
        </label>
        <input 
          type="text" 
          id="full_name" 
          name="full_name" 
          class="form-control" 
          placeholder="e.g. Arindam Mukherjee"
          required
        >
      </div>

      <div class="form-group">
        <label for="email" class="form-label">Email Address (Optional)</label>
        <input 
          type="email" 
          id="email" 
          name="email" 
          class="form-control" 
          placeholder="e.g. student@college.edu"
        >
      </div>

      <div class="grid grid-2" style="gap: 1rem;">
        <div class="form-group">
          <label for="department" class="form-label">Department / School</label>
          <input 
            type="text" 
            id="department" 
            name="department" 
            class="form-control" 
            placeholder="e.g. Computer Science"
          >
        </div>

        <div class="form-group">
          <label for="academic_year" class="form-label">Academic Batch / Year</label>
          <input 
            type="text" 
            id="academic_year" 
            name="academic_year" 
            class="form-control" 
            placeholder="e.g. 2024-2027"
          >
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top: 1rem;">
        Provision Account &amp; Generate Temporary Credential
      </button>
    </form>
  </div>
</div>
