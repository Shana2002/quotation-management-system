<?php
/** Dashboard overview. */

use App\Core\Auth;

$statusLabels = ['draft', 'sent', 'accepted', 'rejected', 'expired'];
$trendLabels  = array_map(fn ($r) => $r['ym'], $trend);
$trendCounts  = array_map(fn ($r) => $r['count'], $trend);
$trendValues  = array_map(fn ($r) => $r['total'], $trend);
$statusData   = array_map(fn ($s) => $byStatus[$s] ?? 0, $statusLabels);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0">Welcome, <?= e(auth()['name']) ?></h4>
        <small class="text-muted"><?= e(ucfirst((string) auth()['role_name'])) ?> overview</small>
    </div>
    <a href="<?= e(url('/quotations/create')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> New Quotation
    </a>
</div>

<!-- Stat cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-calendar-day"></i></span>
                <div>
                    <div class="stat-value"><?= (int) ($stats['today_count'] ?? 0) ?></div>
                    <div class="text-muted small">Quotations Today</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="stat-icon bg-success-subtle text-success"><i class="bi bi-calendar-month"></i></span>
                <div>
                    <div class="stat-value"><?= (int) ($stats['month_count'] ?? 0) ?></div>
                    <div class="text-muted small">This Month</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="stat-icon bg-info-subtle text-info"><i class="bi bi-cash-stack"></i></span>
                <div>
                    <div class="stat-value" style="font-size:1.15rem"><?= e(money($stats['month_value'] ?? 0)) ?></div>
                    <div class="text-muted small">Month Value</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-file-earmark-text"></i></span>
                <div>
                    <div class="stat-value"><?= (int) ($stats['total_count'] ?? 0) ?></div>
                    <div class="text-muted small">Total Quotations</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Secondary counts -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="card shadow-sm">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div><div class="text-muted small">Customers</div><div class="h4 mb-0"><?= (int) $customerCount ?></div></div>
                <i class="bi bi-people fs-2 text-primary opacity-50"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card shadow-sm">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div><div class="text-muted small">Active Plans</div><div class="h4 mb-0"><?= (int) $planCount ?></div></div>
                <i class="bi bi-box-seam fs-2 text-success opacity-50"></i>
            </div>
        </div>
    </div>
    <?php if (Auth::isAdmin() || Auth::isManager()): ?>
    <div class="col-sm-4">
        <div class="card shadow-sm">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small"><?= Auth::isAdmin() ? 'System Users' : 'My Executives' ?></div>
                    <div class="h4 mb-0"><?= (int) $teamCount ?></div>
                </div>
                <i class="bi bi-person-badge fs-2 text-info opacity-50"></i>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Charts -->
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-transparent"><i class="bi bi-graph-up"></i> Monthly Quotation Trend</div>
            <div class="card-body"><canvas id="trendChart" height="110"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-transparent"><i class="bi bi-pie-chart"></i> By Status</div>
            <div class="card-body"><canvas id="statusChart" height="180"></canvas></div>
        </div>
    </div>
</div>

<!-- Recent quotations -->
<div class="card shadow-sm">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history"></i> Recent Quotations</span>
        <a href="<?= e(url('/quotations')) ?>" class="btn btn-sm btn-outline-primary">View all</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Number</th><th>Customer</th><th>Created By</th>
                    <th class="text-end">Total</th><th>Status</th><th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recent === []): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No quotations yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($recent as $q): ?>
                        <tr class="cursor-pointer" onclick="window.location='<?= e(url('/quotations/' . $q['id'])) ?>'">
                            <td class="fw-semibold"><?= e($q['quotation_number']) ?></td>
                            <td><?= e($q['customer_name']) ?></td>
                            <td><?= e($q['created_by_name'] ?? '—') ?></td>
                            <td class="text-end"><?= e(money($q['total'])) ?></td>
                            <td><span class="badge text-bg-<?= e(status_badge($q['status'])) ?>"><?= e(ucfirst($q['status'])) ?></span></td>
                            <td class="small text-muted"><?= e(format_date($q['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const trendLabels = <?= json_encode($trendLabels) ?>;
    const trendCounts = <?= json_encode($trendCounts) ?>;
    const trendValues = <?= json_encode($trendValues) ?>;
    const statusData  = <?= json_encode($statusData) ?>;

    new Chart(document.getElementById('trendChart'), {
        type: 'bar',
        data: {
            labels: trendLabels,
            datasets: [
                { label: 'Count', data: trendCounts, backgroundColor: '#2563eb', yAxisID: 'y', borderRadius: 4 },
                { label: 'Value', data: trendValues, type: 'line', borderColor: '#16a34a', backgroundColor: '#16a34a', yAxisID: 'y1', tension: 0.3 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: {
                y:  { type: 'linear', position: 'left', beginAtZero: true, title: { display: true, text: 'Count' } },
                y1: { type: 'linear', position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, title: { display: true, text: 'Value' } }
            }
        }
    });

    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: ['Draft', 'Sent', 'Accepted', 'Rejected', 'Expired'],
            datasets: [{ data: statusData, backgroundColor: ['#6c757d', '#0dcaf0', '#198754', '#dc3545', '#ffc107'] }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });
});
</script>
