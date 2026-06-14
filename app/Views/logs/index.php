<?php
/** Activity log viewer with simple pagination. */
$totalPages = max(1, (int) ceil($total / $perPage));
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-clock-history"></i> Activity Logs</h4>
    <a href="<?= e(url('/logs/login')) ?>" class="btn btn-outline-secondary"><i class="bi bi-shield-lock"></i> Login Activity</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <input type="text" class="form-control mb-3" placeholder="Filter visible rows..." data-table-filter="#log-table">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" id="log-table">
                <thead><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>Description</th><th>IP</th></tr></thead>
                <tbody>
                    <?php if ($logs === []): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No activity yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="small text-muted text-nowrap"><?= e(format_date($log['created_at'], 'd M Y H:i')) ?></td>
                                <td><?= e($log['user_name'] ?? 'System') ?></td>
                                <td><span class="badge text-bg-light border"><?= e($log['action']) ?></span></td>
                                <td class="small"><?= e($log['entity_type'] ?? '—') ?><?= $log['entity_id'] ? ' #' . (int) $log['entity_id'] : '' ?></td>
                                <td class="small"><?= e($log['description'] ?? '') ?></td>
                                <td class="small text-muted"><?= e($log['ip_address'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="mt-3">
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= e(url('/logs?page=' . $p)) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>
