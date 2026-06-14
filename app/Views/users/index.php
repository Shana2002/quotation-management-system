<?php /** Users listing (managers + executives). */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-person-badge"></i> <?= e($title) ?></h4>
    <a href="<?= e(url('/users/create')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New User</a>
</div>

<?php
/** Small reusable renderer for a user table. */
$renderTable = static function (array $rows): void {
    ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Manager</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">None found.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $u): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($u['name']) ?></td>
                        <td><?= e($u['email']) ?></td>
                        <td><?= e($u['phone'] ?: '—') ?></td>
                        <td><?= e($u['manager_name'] ?? '—') ?></td>
                        <td><span class="badge text-bg-<?= e(status_badge($u['status'])) ?>"><?= e(ucfirst($u['status'])) ?></span></td>
                        <td class="text-end text-nowrap">
                            <a href="<?= e(url('/users/' . $u['id'] . '/edit')) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                            <form action="<?= e(url('/users/' . $u['id'] . '/delete')) ?>" method="post" class="d-inline" data-confirm="Delete this user?">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
};
?>

<?php if (can('admin')): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-transparent fw-semibold"><i class="bi bi-person-gear"></i> Managers</div>
        <div class="card-body"><?php $renderTable($managers); ?></div>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header bg-transparent fw-semibold"><i class="bi bi-person"></i> Executives</div>
    <div class="card-body"><?php $renderTable($executives); ?></div>
</div>
