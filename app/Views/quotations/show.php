<?php
/** Plan-type quotation detail view. */
$statuses = ['draft', 'sent', 'accepted', 'rejected', 'expired'];
$headers = $projection['headers'] ?? [];
$rows    = $projection['rows'] ?? [];
$summary = $projection['summary'] ?? [];
$benefits = trim((string) ($projection['benefits'] ?? ''));
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0"><i class="bi bi-file-earmark-text"></i> <?= e($quotation['quotation_number']) ?></h4>
        <span class="badge text-bg-info mt-1"><?= e($projection['plan_label'] ?? $quotation['plan_type']) ?></span>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= e(url('/quotations/' . $quotation['id'] . '/pdf')) ?>" class="btn btn-danger"><i class="bi bi-file-pdf"></i> Download PDF</a>
        <a href="<?= e(url('/quotations')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-sm-6">
                        <h6 class="text-muted">Prepared For</h6>
                        <div class="fw-bold"><?= e($quotation['customer_name']) ?></div>
                        <div class="small"><?= nl2br(e($quotation['customer_address'] ?? '')) ?></div>
                        <div class="small">NIC: <?= e($quotation['customer_nic'] ?? '—') ?></div>
                        <div class="small">Tel: <?= e($quotation['customer_telephone'] ?? '—') ?></div>
                    </div>
                    <div class="col-sm-6 text-sm-end">
                        <div class="small">Date: <strong><?= e(format_date($quotation['created_at'])) ?></strong></div>
                        <div class="small">Valid Until: <strong><?= e(format_date($quotation['expiry_date'])) ?></strong></div>
                        <div class="small">Prepared By: <strong><?= e($quotation['created_by_name'] ?? '—') ?></strong></div>
                        <div class="mt-2"><span class="badge text-bg-<?= e(status_badge($quotation['status'])) ?>"><?= e(ucfirst($quotation['status'])) ?></span></div>
                    </div>
                </div>

                <?php if (!empty($projection['intro'])): ?>
                    <p><?= e($projection['intro']) ?></p>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr><?php foreach ($headers as $h): ?><th class="text-center"><?= e($h) ?></th><?php endforeach; ?></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr><?php foreach ($row as $cell): ?><td class="text-center fw-semibold"><?= e($cell) ?></td><?php endforeach; ?></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($summary !== []): ?>
                    <div class="row justify-content-end">
                        <div class="col-sm-6">
                            <?php foreach ($summary as $label => $value): ?>
                                <div class="d-flex justify-content-between small <?= str_contains(strtolower($label), 'total') || str_contains(strtolower($label), 'maturity') ? 'fw-bold fs-6 border-top pt-1 mt-1' : '' ?>">
                                    <span class="text-muted"><?= e($label) ?></span><span><?= e($value) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($benefits !== ''): ?>
                    <hr><h6 class="text-muted">Benefits &amp; Conditions</h6>
                    <div class="small text-muted"><?= nl2br(e($benefits)) ?></div>
                <?php endif; ?>

                <?php if (!empty($quotation['notes'])): ?>
                    <hr><h6 class="text-muted">Notes</h6><p class="small mb-0"><?= nl2br(e($quotation['notes'])) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-transparent">Update Status</div>
            <div class="card-body">
                <form action="<?= e(url('/quotations/' . $quotation['id'] . '/status')) ?>" method="post" class="d-flex gap-2">
                    <?= csrf_field() ?>
                    <select name="status" class="form-select">
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= e($s) ?>" <?= $quotation['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary">Update</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-transparent">Verification</div>
            <div class="card-body text-center">
                <div id="qrcode" class="d-flex justify-content-center mb-2"></div>
                <a href="<?= e($verifyUrl) ?>" target="_blank" class="small text-break"><?= e($verifyUrl) ?></a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.QRCode) {
            new QRCode(document.getElementById('qrcode'), { text: <?= json_encode($verifyUrl) ?>, width: 140, height: 140 });
        }
    });
</script>
