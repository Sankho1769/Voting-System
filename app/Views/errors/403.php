<?php
use App\Core\View;
use App\Helpers\Sanitizer;
$pageTitle = '403 Forbidden';
?>

<div class="container container-narrow">
  <div class="card" style="padding: 3rem 2rem; text-align: center; margin-top: 3rem;">
    <div style="font-family: var(--font-display); font-size: 3.5rem; color: var(--color-vermilion); font-weight: 700; line-height: 1; margin-bottom: 0.5rem;">
      403
    </div>
    <h1 style="font-size: 1.75rem; margin-bottom: 0.75rem;">
      <?= Sanitizer::e($title ?? 'Access Forbidden') ?>
    </h1>
    <p style="color: var(--color-text-secondary); max-width: 480px; margin: 0 auto 1.5rem auto;">
      <?= Sanitizer::e($message ?? 'You do not have the required institutional authorization to access this resource.') ?>
    </p>
    <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
      <?php if (!empty($returnUrl)): ?>
        <a href="<?= Sanitizer::e($returnUrl) ?>" class="btn btn-primary">
          Try Again
        </a>
      <?php endif; ?>
      <a href="<?= View::url('/login') ?>" class="btn btn-secondary">
        Return to Login Portals
      </a>
      <a href="<?= View::url('/') ?>" class="btn btn-outline">
        Home
      </a>
    </div>
  </div>
</div>
