<?php
/** Plan-type quotation builder. */
$currency = config('currency_symbol', 'Rs.');

/** Render one input field. */
$renderField = static function (array $f) use ($currency): string {
    $name = e($f['name']);
    $label = e($f['label']);
    $req = !empty($f['required']) ? 'required' : '';
    $reqMark = !empty($f['required']) ? ' <span class="text-danger">*</span>' : '';
    $help = !empty($f['help']) ? '<div class="form-text">' . e($f['help']) . '</div>' : '';

    if (($f['type'] ?? 'text') === 'select') {
        $opts = '<option value="">— Select —</option>';
        foreach (($f['options'] ?? []) as $val => $text) {
            $opts .= '<option value="' . e($val) . '">' . e($text) . '</option>';
        }
        $control = '<select name="' . $name . '" class="form-select" ' . $req . '>' . $opts . '</select>';
    } else {
        $type = e($f['type'] ?? 'text');
        $step = isset($f['step']) ? ' step="' . e($f['step']) . '"' : '';
        $min  = ($type === 'number') ? ' min="0"' : '';
        $control = '<input type="' . $type . '" name="' . $name . '" class="form-control"' . $step . $min . ' ' . $req . '>';
    }

    return '<div class="col-md-6"><label class="form-label">' . $label . $reqMark . '</label>' . $control . $help . '</div>';
};
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-file-earmark-plus"></i> New Quotation</h4>
    <a href="<?= e(url('/quotations')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<?php if ($plans === []): ?>
    <div class="alert alert-warning">No active plans are available. An admin must create/activate a plan first.</div>
<?php else: ?>
<form action="<?= e(url('/quotations')) ?>" method="post" novalidate>
    <?= csrf_field() ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-transparent">Customer &amp; Plan</div>
                <div class="card-body row g-3">
                    <div class="col-md-7">
                        <label class="form-label">Customer <span class="text-danger">*</span></label>
                        <select name="customer_id" class="form-select" required>
                            <option value="">— Select customer —</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= (int) $c['id'] ?>" <?= $selectedCustomer === (int) $c['id'] ? 'selected' : '' ?>>
                                    <?= e($c['name']) ?> (<?= e($c['nic']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text"><a href="<?= e(url('/customers/create')) ?>" target="_blank">+ Add a new customer</a></div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Plan <span class="text-danger">*</span></label>
                        <select name="plan_id" id="plan-select" class="form-select" required>
                            <option value="">— Select plan —</option>
                            <?php foreach ($plans as $p): ?>
                                <option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <?php foreach ($planForms as $planId => $form): ?>
                <div class="card shadow-sm mb-4 plan-fields" data-plan="<?= (int) $planId ?>" style="display:none">
                    <div class="card-header bg-transparent"><?= e($form['type_label']) ?> — Details</div>
                    <div class="card-body">
                        <div class="alert alert-info py-2 small"><?= e($form['note']) ?></div>
                        <div class="row g-3">
                            <?php foreach ($form['fields'] as $field): ?>
                                <?= $renderField($field) ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-transparent">Quotation Settings</div>
                <div class="card-body">
                    <label class="form-label">Expiry Date</label>
                    <input type="date" name="expiry_date" class="form-control mb-3" value="<?= e(date('Y-m-d', strtotime('+30 days'))) ?>">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select mb-3">
                        <option value="draft">Draft</option>
                        <option value="sent">Sent</option>
                    </select>
                    <label class="form-label">Internal Notes</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-lg"></i> Create Quotation</button>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    const sel = document.getElementById('plan-select');
    const blocks = document.querySelectorAll('.plan-fields');
    function sync() {
        blocks.forEach(function (b) {
            const match = b.getAttribute('data-plan') === sel.value;
            b.style.display = match ? '' : 'none';
            // Enable inputs only for the active plan so others aren't submitted/validated.
            b.querySelectorAll('input,select').forEach(function (i) { i.disabled = !match; });
        });
    }
    sel.addEventListener('change', sync);
    sync();
})();
</script>
<?php endif; ?>
