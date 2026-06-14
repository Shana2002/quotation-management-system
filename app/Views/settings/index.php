<?php
/** Company settings form. $settings is a key=>value map. */
$logo = $settings['company_logo'] ?? '';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-gear"></i> Company Settings</h4>
</div>

<form action="<?= e(url('/settings')) ?>" method="post" enctype="multipart/form-data" class="row g-4" novalidate>
    <?= csrf_field() ?>

    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-transparent">Company Information</div>
            <div class="card-body row g-3">
                <div class="col-md-8">
                    <label class="form-label">Company Name <span class="text-danger">*</span></label>
                    <input type="text" name="company_name" class="form-control" required
                           value="<?= e(old('company_name', $settings['company_name'] ?? '')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Registration No.</label>
                    <input type="text" name="company_reg_no" class="form-control"
                           value="<?= e(old('company_reg_no', $settings['company_reg_no'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="company_email" class="form-control"
                           value="<?= e(old('company_email', $settings['company_email'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input type="text" name="company_phone" class="form-control"
                           value="<?= e(old('company_phone', $settings['company_phone'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Website</label>
                    <input type="text" name="company_website" class="form-control"
                           value="<?= e(old('company_website', $settings['company_website'] ?? '')) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea name="company_address" class="form-control" rows="2"><?= e(old('company_address', $settings['company_address'] ?? '')) ?></textarea>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-4">
            <div class="card-header bg-transparent">Quotation Defaults</div>
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label">Number Prefix <span class="text-danger">*</span></label>
                    <input type="text" name="quotation_prefix" class="form-control" required
                           value="<?= e(old('quotation_prefix', $settings['quotation_prefix'] ?? 'QTN')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Default Tax Rate (%)</label>
                    <input type="number" step="0.01" min="0" name="tax_rate" class="form-control"
                           value="<?= e(old('tax_rate', $settings['tax_rate'] ?? '0')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Currency Symbol</label>
                    <input type="text" name="currency_symbol" class="form-control"
                           value="<?= e(old('currency_symbol', $settings['currency_symbol'] ?? 'Rs.')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">PDF Signatory Name</label>
                    <input type="text" name="signatory_name" class="form-control"
                           value="<?= e(old('signatory_name', $settings['signatory_name'] ?? '')) ?>">
                    <div class="form-text">Printed in the signature block of the quotation letter.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">PDF Signatory Title</label>
                    <input type="text" name="signatory_title" class="form-control"
                           value="<?= e(old('signatory_title', $settings['signatory_title'] ?? '')) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Default Terms &amp; Conditions</label>
                    <textarea name="default_terms" class="form-control" rows="4"><?= e(old('default_terms', $settings['default_terms'] ?? '')) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-transparent">Company Logo</div>
            <div class="card-body text-center">
                <?php if ($logo !== ''): ?>
                    <img src="<?= e(asset('uploads/' . $logo)) ?>" alt="Logo" class="img-fluid mb-3 border rounded p-2" style="max-height:140px">
                <?php else: ?>
                    <div class="text-muted py-4"><i class="bi bi-image fs-1"></i><div>No logo uploaded</div></div>
                <?php endif; ?>
                <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/gif,image/webp">
                <div class="form-text">PNG/JPG/GIF/WebP, max 2 MB. Appears on quotation PDFs.</div>
            </div>
        </div>

        <div class="d-grid mt-4">
            <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-lg"></i> Save Settings</button>
        </div>
    </div>
</form>
