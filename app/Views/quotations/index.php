<?php /** Quotations listing (role-scoped). */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-file-earmark-text"></i> Quotations</h4>
    <a href="<?= e(url('/quotations/create')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Quotation</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <input type="text" class="form-control mb-3" placeholder="Search quotations..." data-table-filter="#q-table">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="q-table">
                <thead>
                    <tr>
                        <th>Number</th><th>Customer</th><th>Created By</th>
                        <th class="text-end">Total</th><th>Status</th><th>Expiry</th><th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($quotations === []): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No quotations yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($quotations as $q): ?>
                            <tr>
                                <td class="fw-semibold"><a href="<?= e(url('/quotations/' . $q['id'])) ?>" class="text-decoration-none"><?= e($q['quotation_number']) ?></a></td>
                                <td><?= e($q['customer_name']) ?></td>
                                <td><?= e($q['created_by_name'] ?? '—') ?></td>
                                <td class="text-end"><?= e(money($q['total'])) ?></td>
                                <td><span class="badge text-bg-<?= e(status_badge($q['status'])) ?>"><?= e(ucfirst($q['status'])) ?></span></td>
                                <td class="small text-muted"><?= e(format_date($q['expiry_date'])) ?></td>
                                <td class="text-end text-nowrap">
                                    <a href="<?= e(url('/quotations/' . $q['id'])) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                                    <a href="<?= e(url('/quotations/' . $q['id'] . '/pdf')) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-pdf"></i></a>
                                    <?php if (can('admin', 'manager')): ?>
                                        <form action="<?= e(url('/quotations/' . $q['id'] . '/delete')) ?>" method="post" class="d-inline" data-confirm="Delete this quotation?">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
