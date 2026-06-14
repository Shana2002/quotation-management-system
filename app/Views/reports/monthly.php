<?php
/** Monthly report with a per-day chart. */
$labels = array_map(fn ($r) => $r['day'], $rows);
$counts = array_map(fn ($r) => (int) $r['count'], $rows);
$totals = array_map(fn ($r) => (float) $r['total'], $rows);
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="bi bi-calendar-month"></i> Monthly Report</h4>
    <div class="d-flex gap-2">
        <form method="get" action="<?= e(url('/reports/monthly')) ?>" class="d-flex gap-2">
            <input type="month" name="month" class="form-control" value="<?= e($month) ?>">
            <button class="btn btn-primary">View</button>
        </form>
        <a href="<?= e(url('/reports/export?type=monthly&month=' . urlencode($month))) ?>" class="btn btn-danger"><i class="bi bi-file-pdf"></i> PDF</a>
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

<div class="card shadow-sm mb-4">
    <div class="card-header bg-transparent">Daily Breakdown — <?= e($month) ?></div>
    <div class="card-body"><canvas id="monthChart" height="90"></canvas></div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Date</th><th class="text-end">Quotations</th><th class="text-end">Accepted</th><th class="text-end">Total Value</th></tr></thead>
            <tbody>
                <?php if ($rows === []): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">No data for this month.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= e(format_date($r['day'])) ?></td>
                            <td class="text-end"><?= (int) $r['count'] ?></td>
                            <td class="text-end"><?= (int) $r['accepted'] ?></td>
                            <td class="text-end"><?= e(money($r['total'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    new Chart(document.getElementById('monthChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [{ label: 'Value', data: <?= json_encode($totals) ?>, backgroundColor: '#2563eb', borderRadius: 4 }]
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
    });
});
</script>
