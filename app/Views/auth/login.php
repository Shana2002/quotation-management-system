<?php
/** Login form. Rendered inside layouts/auth. */

use App\Core\Flash;

$messages = Flash::pull();
?>
<div class="card auth-card">
    <div class="card-body p-4 p-md-5">
        <div class="text-center mb-4">
            <span class="auth-logo mb-3">Q</span>
            <h4 class="fw-bold mb-1"><?= e(config('app_name', 'QMS')) ?></h4>
            <p class="text-muted small mb-0">Sign in to your account</p>
        </div>

        <?php foreach ($messages as $type => $message): ?>
            <div class="alert alert-<?= e($type) ?> py-2"><?= e($message) ?></div>
        <?php endforeach; ?>

        <?php foreach (errors() as $error): ?>
            <div class="alert alert-danger py-2"><?= e($error) ?></div>
        <?php endforeach; ?>

        <form action="<?= e(url('/login')) ?>" method="post" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="email" class="form-label">Email address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" class="form-control" id="email" name="email"
                           value="<?= e(old('email')) ?>" placeholder="you@example.com" required autofocus>
                </div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password"
                           placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2">
                <i class="bi bi-box-arrow-in-right"></i> Sign In
            </button>
        </form>

        <div class="mt-4 small text-muted border-top pt-3">
            <div class="fw-semibold mb-1">Demo accounts</div>
            <div>Admin: <code>admin@qms.local</code> / <code>Admin@123</code></div>
            <div>Manager: <code>manager@qms.local</code> / <code>Manager@123</code></div>
            <div>Executive: <code>executive@qms.local</code> / <code>Executive@123</code></div>
        </div>
    </div>
</div>
