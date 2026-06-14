<?php
/** Quotation builder. */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-file-earmark-plus"></i> New Quotation</h4>
    <a href="<?= e(url('/quotations')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<form action="<?= e(url('/quotations')) ?>" method="post" novalidate>
    <?= csrf_field() ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-transparent">Customer &amp; Validity</div>
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
                        <div class="form-text">
                            <a href="<?= e(url('/customers/create')) ?>" target="_blank">+ Add a new customer</a>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expiry_date" class="form-control" value="<?= e(date('Y-m-d', strtotime('+30 days'))) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="draft">Draft</option>
                            <option value="sent">Sent</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                    <span>Line Items</span>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="add-item"><i class="bi bi-plus-lg"></i> Add Item</button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table align-middle" id="items-table">
                            <thead>
                                <tr><th>Plan</th><th>Description</th><th>Qty</th><th>Unit Price</th><th class="text-end">Total</th><th></th></tr>
                            </thead>
                            <tbody>
                                <tr class="item-row">
                                    <td style="min-width:180px">
                                        <select class="form-select form-select-sm item-plan" name="items[0][plan_id]">
                                            <option value="">— Custom —</option>
                                            <?php foreach ($plans as $p): ?>
                                                <option value="<?= (int) $p['id'] ?>" data-price="<?= e($p['amount']) ?>">
                                                    <?= e($p['name']) ?> (<?= e(number_format((float) $p['amount'], 2)) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><input type="text" class="form-control form-control-sm item-desc" name="items[0][description]" placeholder="Description" required></td>
                                    <td style="width:90px"><input type="number" step="0.01" min="0" class="form-control form-control-sm item-qty" name="items[0][quantity]" value="1"></td>
                                    <td style="width:130px"><input type="number" step="0.01" min="0" class="form-control form-control-sm item-price" name="items[0][unit_price]" value="0"></td>
                                    <td style="width:140px" class="text-end align-middle item-total">0.00</td>
                                    <td class="align-middle"><button type="button" class="btn btn-sm btn-outline-danger remove-item"><i class="bi bi-x-lg"></i></button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-transparent">Summary</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal</span><span id="subtotal-display">0.00</span>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Discount</label>
                        <input type="number" step="0.01" min="0" name="discount" id="discount" class="form-control form-control-sm" value="0">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Tax Rate (%)</label>
                        <input type="number" step="0.01" min="0" name="tax_rate" id="tax_rate" class="form-control form-control-sm" value="<?= e($taxRate) ?>">
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Tax</span><span id="tax-display">0.00</span>
                    </div>
                    <input type="hidden" name="tax_amount" id="tax-amount" value="0">
                    <hr>
                    <div class="d-flex justify-content-between fs-5 fw-bold">
                        <span>Total</span><span id="total-display">0.00</span>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-transparent">Notes &amp; Terms</div>
                <div class="card-body">
                    <label class="form-label small">Internal Notes</label>
                    <textarea name="notes" class="form-control mb-3" rows="2"></textarea>
                    <label class="form-label small">Terms &amp; Conditions</label>
                    <textarea name="terms" class="form-control" rows="4"><?= e($defaultTerms) ?></textarea>
                </div>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-lg"></i> Save Quotation</button>
            </div>
        </div>
    </div>
</form>

<script>
    // Provide active plans to the line-item builder in app.js.
    window.QMS_PLANS = <?= json_encode(array_map(fn ($p) => [
        'id' => (int) $p['id'], 'name' => $p['name'], 'amount' => (float) $p['amount'],
    ], $plans)) ?>;
</script>
