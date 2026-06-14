<?php /** Public quotation verification result. Rendered in layouts/auth. */ ?>
<div class="card auth-card">
    <div class="card-body p-4 p-md-5 text-center">
        <?php if ($quotation === null): ?>
            <div class="text-danger mb-3"><i class="bi bi-shield-x" style="font-size:3rem"></i></div>
            <h4 class="fw-bold">Not Verified</h4>
            <p class="text-muted">No quotation matches this verification code. It may be invalid or expired.</p>
        <?php else: ?>
            <div class="text-success mb-3"><i class="bi bi-shield-check" style="font-size:3rem"></i></div>
            <h4 class="fw-bold mb-1">Authentic Quotation</h4>
            <p class="text-muted small mb-4">Issued by <?= e($company) ?></p>

            <ul class="list-group list-group-flush text-start mb-3">
                <li class="list-group-item d-flex justify-content-between bg-transparent">
                    <span class="text-muted">Number</span><span class="fw-semibold"><?= e($quotation['quotation_number']) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between bg-transparent">
                    <span class="text-muted">Customer</span><span><?= e($quotation['customer_name']) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between bg-transparent">
                    <span class="text-muted">Total</span><span class="fw-semibold"><?= e(money($quotation['total'])) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between bg-transparent">
                    <span class="text-muted">Issued</span><span><?= e(format_date($quotation['created_at'])) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between bg-transparent">
                    <span class="text-muted">Valid Until</span><span><?= e(format_date($quotation['expiry_date'])) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between bg-transparent">
                    <span class="text-muted">Status</span>
                    <span class="badge text-bg-<?= e(status_badge($quotation['status'])) ?>"><?= e(ucfirst($quotation['status'])) ?></span>
                </li>
            </ul>
        <?php endif; ?>
    </div>
</div>
