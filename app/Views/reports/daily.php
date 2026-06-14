<?php /** Daily report. */ ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="bi bi-calendar-day"></i> Daily Report</h4>
    <div class="d-flex gap-2">
        <form method="get" action="<?= e(url('/reports/daily')) ?>" class="d-flex gap-2">
            <input type="date" name="date" class="form-control" value="<?= e($date) ?>">
            <button class="btn btn-primary">View</button>
        </form>
        <a href="<?= e(url('/reports/export?type=daily&date=' . urlencode($date))) ?>" class="btn btn-danger"><i class="bi bi-file-pdf"></i> PDF</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6">
        <div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Quotations</div><div class="h3 mb-0"><?= (int) $summary['count'] ?></div></div></div>
    </div>
    <div class="col-sm-6">
        <div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Total Value</div><div class="h3 mb-0"><?= e(money($summary['total'])) ?></div></div></div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-transparent">Quotations on <?= e(format_date($date)) ?></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Number</th><th>Customer</th><th>Created By</th><th class="text-end">Total</th><th>Status</th><th>Time</th></tr></thead>
            <tbody>
                <?php if ($rows === []): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No quotations on this date.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($r['quotation_number']) ?></td>
                            <td><?= e($r['customer_name']) ?></td>
                            <td><?= e($r['created_by_name'] ?? '—') ?></td>
                            <td class="text-end"><?= e(money($r['total'])) ?></td>
                            <td><span class="badge text-bg-<?= e(status_badge($r['status'])) ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                            <td class="small text-muted"><?= e(format_date($r['created_at'], 'H:i')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
