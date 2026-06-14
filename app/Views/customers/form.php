<?php
/** Create/edit customer form. $customer is null when creating. */
$editing = $customer !== null;
$action  = $editing ? url('/customers/' . $customer['id']) : url('/customers');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><?= $editing ? 'Edit Customer' : 'New Customer' ?></h4>
    <a href="<?= e(url('/customers')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="<?= e($action) ?>" method="post" class="row g-3" novalidate>
            <?= csrf_field() ?>

            <div class="col-md-6">
                <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" required
                       value="<?= e(old('name', $customer['name'] ?? '')) ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label">NIC Number <span class="text-danger">*</span></label>
                <input type="text" name="nic" class="form-control" required
                       value="<?= e(old('nic', $customer['nic'] ?? '')) ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label">Telephone</label>
                <input type="text" name="telephone" class="form-control"
                       value="<?= e(old('telephone', $customer['telephone'] ?? '')) ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control"
                       value="<?= e(old('email', $customer['email'] ?? '')) ?>">
            </div>

            <div class="col-12">
                <label class="form-label">Address</label>
                <textarea name="address" class="form-control" rows="2"><?= e(old('address', $customer['address'] ?? '')) ?></textarea>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> <?= $editing ? 'Update' : 'Create' ?> Customer</button>
            </div>
        </form>
    </div>
</div>
