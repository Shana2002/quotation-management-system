<?php /** Customer detail + quotation history. */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-person-vcard"></i> <?= e($customer['name']) ?></h4>
    <div>
        <a href="<?= e(url('/customers/' . $customer['id'] . '/edit')) ?>" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i> Edit</a>
        <a href="<?= e(url('/customers')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-transparent">Details</div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">NIC</span><span><?= e($customer['nic']) ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Telephone</span><span><?= e($customer['telephone'] ?: '—') ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Email</span><span><?= e($customer['email'] ?: '—') ?></span></li>
                <li class="list-group-item"><span class="text-muted d-block mb-1">Address</span><?= nl2br(e($customer['address'] ?: '—')) ?></li>
            </ul>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <span><i class="bi bi-file-earmark-text"></i> Quotation History</span>
                <a href="<?= e(url('/quotations/create?customer_id=' . $customer['id'])) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> New</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Number</th><th class="text-end">Total</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php if ($history === []): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No quotations for this customer.</td></tr>
                        <?php else: ?>
                            <?php foreach ($history as $q): ?>
                                <tr class="cursor-pointer" onclick="window.location='<?= e(url('/quotations/' . $q['id'])) ?>'">
                                    <td class="fw-semibold"><?= e($q['quotation_number']) ?></td>
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
    </div>
</div>
