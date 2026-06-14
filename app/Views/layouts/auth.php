<?php
/** Minimal layout for unauthenticated pages (login). Expects $content. */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/partials/head.php'; ?>
</head>
<body>
    <div class="auth-wrapper">
        <?= $content ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
