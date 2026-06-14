<?php /** Login activity viewer. */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-shield-lock"></i> Login Activity</h4>
    <a href="<?= e(url('/logs')) ?>" class="btn btn-outline-secondary"><i class="bi bi-clock-history"></i> Activity Logs</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <input type="text" class="form-control mb-3" placeholder="Filter visible rows..." data-table-filter="#login-table">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" id="login-table">
                <thead><tr><th>When</th><th>User</th><th>Email</th><th>Result</th><th>IP</th><th>User Agent</th></tr></thead>
                <tbody>
                    <?php if ($attempts === []): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No login attempts recorded.</td></tr>
                    <?php else: ?>
                        <?php foreach ($attempts as $a): ?>
                            <tr>
                                <td class="small text-muted text-nowrap"><?= e(format_date($a['created_at'], 'd M Y H:i')) ?></td>
                                <td><?= e($a['user_name'] ?? '—') ?></td>
                                <td class="small"><?= e($a['email']) ?></td>
                                <td><span class="badge text-bg-<?= $a['status'] === 'success' ? 'success' : 'danger' ?>"><?= e(ucfirst($a['status'])) ?></span></td>
                                <td class="small text-muted"><?= e($a['ip_address'] ?? '') ?></td>
                                <td class="small text-muted text-truncate" style="max-width:240px"><?= e($a['user_agent'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
