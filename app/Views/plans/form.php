<?php
/** Create/edit plan form. $plan is null when creating. */
$editing = $plan !== null;
$action  = $editing ? url('/plans/' . $plan['id']) : url('/plans');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><?= $editing ? 'Edit Plan' : 'New Plan' ?></h4>
    <a href="<?= e(url('/plans')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="<?= e($action) ?>" method="post" class="row g-3" novalidate>
            <?= csrf_field() ?>

            <div class="col-md-8">
                <label class="form-label">Plan Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" required
                       value="<?= e(old('name', $plan['name'] ?? '')) ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">Amount <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0" name="amount" class="form-control" required
                       value="<?= e(old('amount', $plan['amount'] ?? '')) ?>">
            </div>

            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3"><?= e(old('description', $plan['description'] ?? '')) ?></textarea>
            </div>

            <div class="col-md-4">
                <label class="form-label">Status</label>
                <?php $status = old('status', $plan['status'] ?? 'active'); ?>
                <select name="status" class="form-select">
                    <option value="active"   <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> <?= $editing ? 'Update' : 'Create' ?> Plan</button>
            </div>
        </form>
    </div>
</div>
