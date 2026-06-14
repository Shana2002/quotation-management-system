<?php /** Plans listing. */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-box-seam"></i> Plans</h4>
    <?php if (can('admin')): ?>
        <a href="<?= e(url('/plans/create')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Plan</a>
    <?php endif; ?>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <input type="text" class="form-control mb-3" placeholder="Search plans..."
               data-table-filter="#plans-table">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="plans-table">
                <thead>
                    <tr>
                        <th>Name</th><th>Description</th>
                        <th class="text-end">Amount</th><th>Status</th>
                        <?php if (can('admin')): ?><th class="text-end">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($plans === []): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No plans defined.</td></tr>
                    <?php else: ?>
                        <?php foreach ($plans as $plan): ?>
                            <tr>
                                <td class="fw-semibold"><?= e($plan['name']) ?></td>
                                <td class="text-muted small"><?= e(mb_strimwidth((string) $plan['description'], 0, 70, '…')) ?></td>
                                <td class="text-end"><?= e(money($plan['amount'])) ?></td>
                                <td><span class="badge text-bg-<?= e(status_badge($plan['status'])) ?>"><?= e(ucfirst($plan['status'])) ?></span></td>
                                <?php if (can('admin')): ?>
                                    <td class="text-end text-nowrap">
                                        <a href="<?= e(url('/plans/' . $plan['id'] . '/edit')) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                        <form action="<?= e(url('/plans/' . $plan['id'] . '/delete')) ?>" method="post" class="d-inline" data-confirm="Delete this plan?">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
