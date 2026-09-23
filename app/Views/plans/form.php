<?php
/** Create/edit plan form. $plan is null when creating. $types: key => PlanTypeInterface. */
$editing = $plan !== null;
$action  = $editing ? url('/plans/' . $plan['id']) : url('/plans');

// Pretty-print existing parameters JSON for editing.
$paramsRaw = old('parameters', $plan['parameters'] ?? '');
$pretty = $paramsRaw;
if (is_string($paramsRaw) && $paramsRaw !== '') {
    $decoded = json_decode($paramsRaw, true);
    if (is_array($decoded)) {
        $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}

// Build a JS map of type => {note, defaults, tokens, summary, terms} for the live helpers.
$typeMeta = [];
foreach ($types as $key => $type) {
    $typeMeta[$key] = [
        'note'     => $type->formulaNote(),
        'defaults' => json_encode($type->defaultParameters(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'tokens'   => $type->availableTokens($type->defaultParameters()),
        'summary'  => $type->defaultSummary(),
        'terms'    => $type->defaultTerms(),
    ];
}
$selectedType = old('plan_type', $plan['plan_type'] ?? array_key_first($types));
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><?= $editing ? 'Edit Plan' : 'New Plan' ?></h4>
    <a href="<?= e(url('/plans')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="<?= e($action) ?>" method="post" class="row g-3" novalidate>
            <?= csrf_field() ?>

            <div class="col-md-6">
                <label class="form-label">Plan Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" required
                       value="<?= e(old('name', $plan['name'] ?? '')) ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">Plan Type <span class="text-danger">*</span></label>
                <select name="plan_type" id="plan-type" class="form-select">
                    <?php foreach ($types as $key => $type): ?>
                        <option value="<?= e($key) ?>" <?= $selectedType === $key ? 'selected' : '' ?>><?= e($type->label()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">Status</label>
                <?php $status = old('status', $plan['status'] ?? 'active'); ?>
                <select name="status" class="form-select">
                    <option value="active"   <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <div class="col-12">
                <label class="form-label">Description</label>
                <input type="text" name="description" class="form-control"
                       value="<?= e(old('description', $plan['description'] ?? '')) ?>">
            </div>

            <div class="col-12">
                <div class="alert alert-info py-2 mb-2 small" id="formula-note"></div>
                <label class="form-label d-flex justify-content-between align-items-center">
                    <span>Parameters (JSON) — rates / prices / durations</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="load-defaults">Load defaults for this type</button>
                </label>
                <textarea name="parameters" id="parameters" class="form-control font-monospace" rows="10" spellcheck="false"><?= e($pretty) ?></textarea>
                <div class="form-text">Must be valid JSON. Adjust the rates/prices to match the real product.</div>
            </div>

            <div class="col-12">
                <hr class="mt-2">
                <div class="alert alert-info py-2 mb-3 small">
                    <strong>Placeholders for this plan type</strong> — click to insert into the Investment Summary.
                    <div id="token-list" class="mt-1"></div>
                </div>

                <label class="form-label d-flex justify-content-between align-items-center">
                    <span>Investment Summary</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="load-letter-defaults">Load default summary &amp; terms</button>
                </label>
                <textarea name="summary_template" id="summary-template" class="form-control" rows="4"><?= e(old('summary_template', $plan['summary_template'] ?? '')) ?></textarea>
                <div class="form-text">One bullet per line. Values are filled in per quotation from the placeholders above.</div>
            </div>

            <div class="col-12">
                <label class="form-label">Terms &amp; Conditions</label>
                <textarea name="terms_template" id="terms-template" class="form-control" rows="7"><?= e(old('terms_template', $plan['terms_template'] ?? '')) ?></textarea>
                <div class="form-text">One clause per line — numbered automatically on the letter.</div>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> <?= $editing ? 'Update' : 'Create' ?> Plan</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const META = <?= json_encode($typeMeta) ?>;
    const sel = document.getElementById('plan-type');
    const note = document.getElementById('formula-note');
    const params = document.getElementById('parameters');
    const summary = document.getElementById('summary-template');
    const terms = document.getElementById('terms-template');
    const tokenList = document.getElementById('token-list');

    // Render the placeholder names this plan type can fill, as click-to-insert chips.
    function showTokens() {
        const m = META[sel.value] || {};
        const tokens = m.tokens || {};
        tokenList.innerHTML = '';

        Object.keys(tokens).forEach(function (name) {
            const code = '${' + name + '}';
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'btn btn-sm btn-outline-primary py-0 px-1 me-1 mb-1 font-monospace';
            chip.style.fontSize = '.75rem';
            chip.textContent = code;
            chip.title = tokens[name];
            chip.addEventListener('click', function () {
                summary.value += (summary.value && !summary.value.endsWith('\n') ? '\n' : '') + code;
                summary.focus();
            });
            tokenList.appendChild(chip);
        });

        if (!Object.keys(tokens).length) {
            tokenList.innerHTML = '<span class="text-muted">—</span>';
        }
    }

    function showNote() {
        const m = META[sel.value];
        note.textContent = m ? m.note : '';
    }
    document.getElementById('load-defaults').addEventListener('click', function () {
        const m = META[sel.value];
        if (m && (!params.value.trim() || confirm('Replace the current parameters with this type’s defaults?'))) {
            params.value = m.defaults;
        }
    });
    document.getElementById('load-letter-defaults').addEventListener('click', function () {
        const m = META[sel.value];
        if (m && confirm('Replace the Investment Summary and Terms & Conditions with this type’s defaults?')) {
            summary.value = m.summary || '';
            terms.value = m.terms || '';
        }
    });
    sel.addEventListener('change', function () {
        showNote();
        showTokens();
    });
    showNote();
    showTokens();
    // Pre-fill defaults when creating a brand-new plan with an empty editor.
    if (!params.value.trim()) { params.value = (META[sel.value] || {}).defaults || ''; }
    if (!summary.value.trim()) { summary.value = (META[sel.value] || {}).summary || ''; }
    if (!terms.value.trim()) { terms.value = (META[sel.value] || {}).terms || ''; }
})();
</script>
