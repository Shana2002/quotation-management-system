<?php
/** Quotation detail view. */
$statuses = ['draft', 'sent', 'accepted', 'rejected', 'expired'];
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><i class="bi bi-file-earmark-text"></i> <?= e($quotation['quotation_number']) ?></h4>
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
                        <h6 class="text-muted">Bill To</h6>
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

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr><th>#</th><th>Description</th><th class="text-center">Qty</th><th class="text-end">Unit Price</th><th class="text-end">Amount</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $i => $item): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= e($item['description']) ?></td>
                                    <td class="text-center"><?= e(number_format((float) $item['quantity'], 2)) ?></td>
                                    <td class="text-end"><?= e(money($item['unit_price'])) ?></td>
                                    <td class="text-end"><?= e(money($item['line_total'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="row justify-content-end">
                    <div class="col-sm-5">
                        <div class="d-flex justify-content-between small"><span class="text-muted">Subtotal</span><span><?= e(money($quotation['subtotal'])) ?></span></div>
                        <div class="d-flex justify-content-between small"><span class="text-muted">Discount</span><span>- <?= e(money($quotation['discount'])) ?></span></div>
                        <div class="d-flex justify-content-between small"><span class="text-muted">Tax</span><span><?= e(money($quotation['tax'])) ?></span></div>
                        <hr class="my-1">
                        <div class="d-flex justify-content-between fw-bold fs-5"><span>Total</span><span><?= e(money($quotation['total'])) ?></span></div>
                    </div>
                </div>

                <?php if (!empty($quotation['notes'])): ?>
                    <hr><h6 class="text-muted">Notes</h6><p class="small mb-0"><?= nl2br(e($quotation['notes'])) ?></p>
                <?php endif; ?>
                <?php if (!empty($quotation['terms'])): ?>
                    <hr><h6 class="text-muted">Terms &amp; Conditions</h6><p class="small text-muted mb-0"><?= nl2br(e($quotation['terms'])) ?></p>
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

<!-- Render the same verification QR on screen using a lightweight library -->
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.QRCode) {
            new QRCode(document.getElementById('qrcode'), {
                text: <?= json_encode($verifyUrl) ?>,
                width: 140, height: 140
            });
        }
    });
</script>
