<?php $title = 'Server Error'; ?>
<div class="text-center py-5">
    <div class="display-1 fw-bold text-danger">500</div>
    <h2 class="mb-3">Something went wrong</h2>
    <p class="text-muted">An unexpected error occurred. The issue has been logged.</p>
    <a href="<?= e(url('/dashboard')) ?>" class="btn btn-primary"><i class="bi bi-house"></i> Go Home</a>
</div>
