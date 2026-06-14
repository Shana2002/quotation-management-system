<?php $title = 'Session Expired'; ?>
<div class="text-center py-5">
    <div class="display-1 fw-bold text-warning">419</div>
    <h2 class="mb-3">Page Expired</h2>
    <p class="text-muted">Your session or security token has expired. Please go back and try again.</p>
    <a href="<?= e(url('/dashboard')) ?>" class="btn btn-primary"><i class="bi bi-arrow-left"></i> Back to safety</a>
</div>
