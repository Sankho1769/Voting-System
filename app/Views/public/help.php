<?php
use App\Core\Auth;
use App\Core\View;
use App\Helpers\Sanitizer;
?>

<div class="container section">

  <!-- Header -->
  <div style="max-width: 720px; margin: 0 auto 3rem auto; text-align: center;">
    <span class="badge badge-purple" style="margin-bottom: 0.5rem;">SUPPORT &amp; FAQS</span>
    <h1 style="font-family: var(--font-display); font-size: 3rem; font-weight: 700; color: var(--text-primary); margin: 0 0 0.75rem 0;">
      Help &amp; Frequently Asked Questions
    </h1>
    <p style="color: var(--text-muted); font-size: 1.15rem;">
      Everything you need to know about accessing your portal, voting, and technical requirements.
    </p>
  </div>

  <div style="max-width: 800px; margin: 0 auto; display: flex; flex-direction: column; gap: 1.5rem;">

    <div class="card" style="padding: 1.75rem;">
      <h3 style="font-family: var(--font-display); font-size: 1.35rem; color: var(--text-primary); margin: 0 0 0.5rem 0;">
        Which login portal should I use?
      </h3>
      <p style="color: var(--text-secondary); line-height: 1.6; margin: 0;">
        VerityVote provides three dedicated login portals:
      </p>
      <ul style="margin: 0.5rem 0 0 1.25rem; color: var(--text-secondary); line-height: 1.6;">
        <li><strong>Voter Portal (<a href="<?= View::url('/login/voter') ?>">/login/voter</a>):</strong> For students and faculty to cast ballots and review participation receipts.</li>
        <li><strong>Candidate Portal (<a href="<?= View::url('/login/candidate') ?>">/login/candidate</a>):</strong> For certified candidates to review standings, update manifestos, and see opponents.</li>
        <li><strong>Admin Portal (<a href="<?= View::url('/login/admin') ?>">/login/admin</a>):</strong> For election administrators and returning officers to configure ballots and monitor audits.</li>
      </ul>
    </div>

    <div class="card" style="padding: 1.75rem;">
      <h3 style="font-family: var(--font-display); font-size: 1.35rem; color: var(--text-primary); margin: 0 0 0.5rem 0;">
        How is my vote kept anonymous?
      </h3>
      <p style="color: var(--text-secondary); line-height: 1.6; margin: 0;">
        VerityVote uses architectural ballot decoupling. When you vote, your student record is updated only to confirm that you have participated (preventing duplicate votes). Your choice of candidate is stored in an entirely separate table without your user ID, IP address, or browser signature.
      </p>
    </div>

    <div class="card" style="padding: 1.75rem;">
      <h3 style="font-family: var(--font-display); font-size: 1.35rem; color: var(--text-primary); margin: 0 0 0.5rem 0;">
        What should I do if my login fails?
      </h3>
      <p style="color: var(--text-secondary); line-height: 1.6; margin: 0;">
        Ensure you are using the correct login ID (institutional roll number or username) and password. If you are logging in with a temporary password provided by the administrator, you will be prompted to set a permanent password upon first login.
      </p>
    </div>

    <div class="card" style="padding: 1.75rem;">
      <h3 style="font-family: var(--font-display); font-size: 1.35rem; color: var(--text-primary); margin: 0 0 0.5rem 0;">
        How can administrators inspect application data?
      </h3>
      <p style="color: var(--text-secondary); line-height: 1.6; margin: 0;">
        As a BCA academic project running under XAMPP, all application tables (<code>users</code>, <code>elections</code>, <code>candidates</code>, <code>election_eligibility</code>, <code>election_participation</code>, <code>votes</code>, <code>audit_logs</code>) are stored in MySQL/MariaDB and can be directly inspected and exported through phpMyAdmin at <code>http://localhost/phpmyadmin</code>.
      </p>
    </div>

  </div>

</div>
