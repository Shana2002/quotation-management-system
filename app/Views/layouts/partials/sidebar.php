<?php
/** Sidebar navigation. Visibility of items is role-aware via can(). */
?>
<aside class="sidebar">
    <div class="d-flex align-items-center gap-2 px-3 py-3 border-bottom">
        <span class="auth-logo" style="width:38px;height:38px;font-size:1rem;">Q</span>
        <span class="brand">QMS</span>
    </div>

    <nav class="nav flex-column py-2">
        <a href="<?= e(url('/dashboard')) ?>" class="nav-link <?= active_class('/dashboard') ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="nav-section">Operations</div>
        <a href="<?= e(url('/quotations')) ?>" class="nav-link <?= active_class('/quotations') ?>">
            <i class="bi bi-file-earmark-text"></i> Quotations
        </a>
        <a href="<?= e(url('/customers')) ?>" class="nav-link <?= active_class('/customers') ?>">
            <i class="bi bi-people"></i> Customers
        </a>
        <a href="<?= e(url('/plans')) ?>" class="nav-link <?= active_class('/plans') ?>">
            <i class="bi bi-box-seam"></i> Plans
        </a>

        <?php if (can('admin', 'manager')): ?>
            <div class="nav-section">Team</div>
            <a href="<?= e(url('/users')) ?>" class="nav-link <?= active_class('/users') ?>">
                <i class="bi bi-person-badge"></i>
                <?= can('admin') ? 'Managers & Executives' : 'My Executives' ?>
            </a>
            <a href="<?= e(url('/reports')) ?>" class="nav-link <?= active_class('/reports') ?>">
                <i class="bi bi-graph-up"></i> Reports
            </a>
        <?php endif; ?>

        <?php if (can('admin')): ?>
            <div class="nav-section">Administration</div>
            <a href="<?= e(url('/settings')) ?>" class="nav-link <?= active_class('/settings') ?>">
                <i class="bi bi-gear"></i> Settings
            </a>
            <a href="<?= e(url('/logs')) ?>" class="nav-link <?= active_class('/logs') ?>">
                <i class="bi bi-clock-history"></i> Activity Logs
            </a>
        <?php endif; ?>
    </nav>
</aside>
<div class="sidebar-backdrop"></div>
