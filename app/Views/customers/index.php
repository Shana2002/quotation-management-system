<?php /** Customers listing. */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-people"></i> Customers</h4>
    <a href="<?= e(url('/customers/create')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Customer</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="get" action="<?= e(url('/customers')) ?>" class="mb-3">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-control" placeholder="Search by name, NIC, email or phone..."
                       value="<?= e($term) ?>">
                <button class="btn btn-outline-primary">Search</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th>Name</th><th>NIC</th><th>Telephone</th><th>Email</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    <?php if ($customers === []): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No customers found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($customers as $c): ?>
                            <tr>
                                <td class="fw-semibold">
                                    <a href="<?= e(url('/customers/' . $c['id'])) ?>" class="text-decoration-none"><?= e($c['name']) ?></a>
                                </td>
                                <td><?= e($c['nic']) ?></td>
                                <td><?= e($c['telephone'] ?: '—') ?></td>
                                <td><?= e($c['email'] ?: '—') ?></td>
                                <td class="text-end text-nowrap">
                                    <a href="<?= e(url('/customers/' . $c['id'])) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                                    <a href="<?= e(url('/customers/' . $c['id'] . '/edit')) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                    <?php if (can('admin', 'manager')): ?>
                                        <form action="<?= e(url('/customers/' . $c['id'] . '/delete')) ?>" method="post" class="d-inline" data-confirm="Delete this customer?">
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
