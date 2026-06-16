<?php
/** Create/edit user form. $user is null when creating. */

use App\Core\Auth;

$editing  = $user !== null;
$action   = $editing ? url('/users/' . $user['id']) : url('/users');
$roleVal  = old('role', $user['role_name'] ?? 'executive');
$statusVal = old('status', $user['status'] ?? 'active');
$mgrVal   = old('manager_id', $user['manager_id'] ?? '');
$isManagerActor = Auth::isManager();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><?= $editing ? 'Edit User' : 'New User' ?></h4>
    <a href="<?= e(url('/users')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="<?= e($action) ?>" method="post" class="row g-3" novalidate>
            <?= csrf_field() ?>

            <div class="col-md-6">
                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" required value="<?= e(old('name', $user['name'] ?? '')) ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label">Email <span class="text-danger">*</span></label>
                <input type="email" name="email" class="form-control" required value="<?= e(old('email', $user['email'] ?? '')) ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label">Phone <span class="text-danger">*</span></label>
                <input type="text" name="phone" class="form-control" required value="<?= e(old('phone', $user['phone'] ?? '')) ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label">Position <span class="text-danger">*</span></label>
                <input type="text" name="position" class="form-control" required value="<?= e(old('position', $user['position'] ?? '')) ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label">Password <?= $editing ? '<span class="text-muted small">(leave blank to keep)</span>' : '<span class="text-danger">*</span>' ?></label>
                <input type="password" name="password" class="form-control" <?= $editing ? '' : 'required' ?> minlength="8">
            </div>

            <div class="col-md-4">
                <label class="form-label">Role</label>
                <?php if ($isManagerActor): ?>
                    <input type="text" class="form-control" value="Executive" disabled>
                    <input type="hidden" name="role" value="executive">
                <?php else: ?>
                    <select name="role" class="form-select" id="role-select">
                        <option value="admin"     <?= $roleVal === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="manager"   <?= $roleVal === 'manager' ? 'selected' : '' ?>>Manager</option>
                        <option value="executive" <?= $roleVal === 'executive' ? 'selected' : '' ?>>Executive</option>
                    </select>
                <?php endif; ?>
            </div>

            <div class="col-md-4" id="manager-wrap">
                <label class="form-label">Assigned Manager <span class="text-muted small">(executives)</span></label>
                <select name="manager_id" class="form-select" <?= $isManagerActor ? 'disabled' : '' ?>>
                    <option value="">— None —</option>
                    <?php foreach ($managers as $m): ?>
                        <option value="<?= (int) $m['id'] ?>" <?= (string) $mgrVal === (string) $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($isManagerActor): ?>
                    <input type="hidden" name="manager_id" value="<?= (int) Auth::id() ?>">
                <?php endif; ?>
            </div>

            <div class="col-md-4">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="active"   <?= $statusVal === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $statusVal === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> <?= $editing ? 'Update' : 'Create' ?> User</button>
            </div>
        </form>
    </div>
</div>

<?php if (!$isManagerActor): ?>
<script>
// Show the manager dropdown only when role = executive.
document.addEventListener('DOMContentLoaded', function () {
    const role = document.getElementById('role-select');
    const wrap = document.getElementById('manager-wrap');
    function sync() { wrap.style.display = role.value === 'executive' ? '' : 'none'; }
    role.addEventListener('change', sync);
    sync();
});
</script>
<?php endif; ?>
