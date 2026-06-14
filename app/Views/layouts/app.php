<?php
/** Main authenticated application shell. Expects $content and optional $title. */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/partials/head.php'; ?>
    <!-- Chart.js for dashboard / report charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>
    <div class="app-shell">
        <?php include __DIR__ . '/partials/sidebar.php'; ?>

        <div class="main-content">
            <?php include __DIR__ . '/partials/navbar.php'; ?>

            <main class="content-area">
                <?php include __DIR__ . '/partials/flash.php'; ?>
                <?= $content ?>
            </main>

            <footer class="text-center text-muted small py-3 border-top no-print">
                &copy; <?= date('Y') ?> <?= e(config('app_name', 'QMS')) ?>. All rights reserved.
            </footer>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= e(asset('js/app.js')) ?>"></script>
    <?php if (!empty($scripts)): ?><?= $scripts ?><?php endif; ?>
</body>
</html>
