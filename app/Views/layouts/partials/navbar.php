<?php
/** Top bar with sidebar toggle, theme switch and user menu. */
$current = auth();
$roleLabel = ucfirst((string) ($current['role_name'] ?? ''));
?>
<header class="topbar d-flex align-items-center justify-content-between px-3 py-2">
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-outline-secondary d-lg-none" data-sidebar-toggle aria-label="Toggle menu">
            <i class="bi bi-list"></i>
        </button>
        <span class="fw-semibold d-none d-sm-inline"><?= e($title ?? 'Dashboard') ?></span>
    </div>

    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-outline-secondary" data-theme-toggle title="Toggle theme">
            <i class="bi bi-moon-stars-fill" data-theme-icon></i>
        </button>

        <div class="dropdown">
            <button class="btn btn-outline-secondary dropdown-toggle d-flex align-items-center gap-2"
                    data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle"></i>
                <span class="d-none d-sm-inline"><?= e($current['name'] ?? 'User') ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li class="dropdown-header">
                    <?= e($current['name'] ?? '') ?><br>
                    <small class="text-muted"><?= e($roleLabel) ?></small>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form action="<?= e(url('/logout')) ?>" method="post" class="px-1">
                        <?= csrf_field() ?>
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="bi bi-box-arrow-right"></i> Sign out
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
