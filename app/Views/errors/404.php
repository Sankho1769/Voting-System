<?php
use App\Core\View;
use App\Helpers\Sanitizer;
$pageTitle = '404 Not Found';
?>

<div class="container container-narrow">
  <div class="card" style="padding: 3rem 2rem; text-align: center; margin-top: 3rem;">
    <div style="font-family: var(--font-display); font-size: 3.5rem; color: var(--color-text-muted); font-weight: 700; line-height: 1; margin-bottom: 0.5rem;">
      404
    </div>
    <h1 style="font-size: 1.75rem; margin-bottom: 0.75rem;">Page Not Found</h1>
    <p style="color: var(--color-text-secondary); max-width: 480px; margin: 0 auto 1.5rem auto;">
      The requested collegiate resource or ballot does not exist or has been relocated.
    </p>
    <a href="<?= View::url('/') ?>" class="btn btn-secondary">
      Return to Home Portal
    </a>
  </div>
</div>
