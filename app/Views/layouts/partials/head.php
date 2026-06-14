<?php
/** Shared <head> markup. Expects optional $title. */
$pageTitle = isset($title) ? $title . ' · ' : '';
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= e($pageTitle) ?><?= e(config('app_name', 'QMS')) ?></title>

<!-- Bootstrap 5.3 + Icons (CDN; see DEPLOY.md for a local-vendor fallback) -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= e(asset('css/app.css')) ?>" rel="stylesheet">

<!-- Set theme before paint to avoid flash of incorrect theme -->
<script>
    (function () {
        var t = localStorage.getItem('qms-theme') || 'light';
        document.documentElement.setAttribute('data-bs-theme', t);
    })();
</script>
