<?php /** Employee performance report. */ ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="bi bi-trophy"></i> Employee Performance</h4>
    <a href="<?= e(url('/reports/export?type=performance')) ?>" class="btn btn-danger"><i class="bi bi-file-pdf"></i> PDF</a>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Employee</th><th>Role</th>
                    <th class="text-end">Quotations</th><th class="text-end">Accepted</th>
                    <th class="text-end">Total Value</th><th class="text-end">Accepted Value</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows === []): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No data available.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($r['employee']) ?></td>
                            <td><span class="badge text-bg-secondary"><?= e(ucfirst((string) $r['role'])) ?></span></td>
                            <td class="text-end"><?= (int) $r['total_count'] ?></td>
                            <td class="text-end"><?= (int) $r['accepted_count'] ?></td>
                            <td class="text-end"><?= e(money($r['total_value'])) ?></td>
                            <td class="text-end"><?= e(money($r['accepted_value'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
