<?php /** Reports landing page. */ ?>
<h4 class="mb-4"><i class="bi bi-graph-up"></i> Reports</h4>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body text-center">
                <i class="bi bi-calendar-day fs-1 text-primary"></i>
                <h5 class="mt-3">Daily Report</h5>
                <p class="text-muted small">Quotations created on a specific day with totals.</p>
                <a href="<?= e(url('/reports/daily')) ?>" class="btn btn-outline-primary">Open</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body text-center">
                <i class="bi bi-calendar-month fs-1 text-success"></i>
                <h5 class="mt-3">Monthly Report</h5>
                <p class="text-muted small">Per-day breakdown and totals for a chosen month.</p>
                <a href="<?= e(url('/reports/monthly')) ?>" class="btn btn-outline-success">Open</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body text-center">
                <i class="bi bi-trophy fs-1 text-warning"></i>
                <h5 class="mt-3">Employee Performance</h5>
                <p class="text-muted small">Quotation counts and values per team member.</p>
                <a href="<?= e(url('/reports/performance')) ?>" class="btn btn-outline-warning">Open</a>
            </div>
        </div>
    </div>
</div>
