/* =====================================================================
   QMS — front-end behaviour
   - dark/light theme toggle persisted in localStorage
   - mobile sidebar toggle
   - confirm-before-submit for destructive actions
   - dynamic quotation line items + live totals
   ===================================================================== */
(function () {
    'use strict';

    /* ---------------- Theme ---------------- */
    const THEME_KEY = 'qms-theme';

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-bs-theme', theme);
        const icon = document.querySelector('[data-theme-icon]');
        if (icon) {
            icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
        }
    }

    function initTheme() {
        const saved = localStorage.getItem(THEME_KEY) || 'light';
        applyTheme(saved);

        const toggle = document.querySelector('[data-theme-toggle]');
        if (toggle) {
            toggle.addEventListener('click', function () {
                const current = document.documentElement.getAttribute('data-bs-theme') || 'light';
                const next = current === 'dark' ? 'light' : 'dark';
                localStorage.setItem(THEME_KEY, next);
                applyTheme(next);
            });
        }
    }

    /* ---------------- Sidebar (mobile) ---------------- */
    function initSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const backdrop = document.querySelector('.sidebar-backdrop');
        const toggle = document.querySelector('[data-sidebar-toggle]');
        if (!sidebar) return;

        function open() { sidebar.classList.add('show'); if (backdrop) backdrop.classList.add('show'); }
        function close() { sidebar.classList.remove('show'); if (backdrop) backdrop.classList.remove('show'); }

        if (toggle) toggle.addEventListener('click', open);
        if (backdrop) backdrop.addEventListener('click', close);
    }

    /* ---------------- Confirm destructive actions ---------------- */
    function initConfirms() {
        document.querySelectorAll('form[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                if (!window.confirm(form.getAttribute('data-confirm'))) {
                    e.preventDefault();
                }
            });
        });
    }

    /* ---------------- Quotation builder ---------------- */
    function initQuotationBuilder() {
        const table = document.querySelector('#items-table');
        if (!table) return;

        const body = table.querySelector('tbody');
        const addBtn = document.querySelector('#add-item');
        const plans = window.QMS_PLANS || [];

        function rowTemplate(index) {
            const options = plans.map(function (p) {
                return '<option value="' + p.id + '" data-price="' + p.amount + '">' +
                    p.name + ' (' + p.amount + ')</option>';
            }).join('');
            return '' +
                '<tr class="item-row">' +
                '  <td style="min-width:180px">' +
                '    <select class="form-select form-select-sm item-plan" name="items[' + index + '][plan_id]">' +
                '      <option value="">— Custom —</option>' + options +
                '    </select>' +
                '  </td>' +
                '  <td><input type="text" class="form-control form-control-sm item-desc" name="items[' + index + '][description]" placeholder="Description" required></td>' +
                '  <td style="width:90px"><input type="number" step="0.01" min="0" class="form-control form-control-sm item-qty" name="items[' + index + '][quantity]" value="1"></td>' +
                '  <td style="width:130px"><input type="number" step="0.01" min="0" class="form-control form-control-sm item-price" name="items[' + index + '][unit_price]" value="0"></td>' +
                '  <td style="width:140px" class="text-end align-middle item-total">0.00</td>' +
                '  <td class="align-middle"><button type="button" class="btn btn-sm btn-outline-danger remove-item"><i class="bi bi-x-lg"></i></button></td>' +
                '</tr>';
        }

        let index = body.querySelectorAll('.item-row').length;

        function recalc() {
            let subtotal = 0;
            body.querySelectorAll('.item-row').forEach(function (row) {
                const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
                const price = parseFloat(row.querySelector('.item-price').value) || 0;
                const total = qty * price;
                row.querySelector('.item-total').textContent = total.toFixed(2);
                subtotal += total;
            });

            const discount = parseFloat(document.querySelector('#discount').value) || 0;
            const taxRate = parseFloat(document.querySelector('#tax_rate').value) || 0;
            const taxable = Math.max(subtotal - discount, 0);
            const tax = taxable * (taxRate / 100);
            const grand = taxable + tax;

            document.querySelector('#subtotal-display').textContent = subtotal.toFixed(2);
            document.querySelector('#tax-display').textContent = tax.toFixed(2);
            document.querySelector('#total-display').textContent = grand.toFixed(2);
            document.querySelector('#tax-amount').value = tax.toFixed(2);
        }

        function wireRow(row) {
            row.querySelector('.remove-item').addEventListener('click', function () {
                if (body.querySelectorAll('.item-row').length > 1) {
                    row.remove();
                    recalc();
                }
            });
            const planSel = row.querySelector('.item-plan');
            planSel.addEventListener('change', function () {
                const opt = planSel.options[planSel.selectedIndex];
                const price = opt.getAttribute('data-price');
                if (price) {
                    row.querySelector('.item-price').value = price;
                    if (!row.querySelector('.item-desc').value) {
                        row.querySelector('.item-desc').value = opt.text.replace(/\s*\(.*\)$/, '');
                    }
                }
                recalc();
            });
            row.querySelectorAll('.item-qty, .item-price').forEach(function (inp) {
                inp.addEventListener('input', recalc);
            });
        }

        if (addBtn) {
            addBtn.addEventListener('click', function () {
                body.insertAdjacentHTML('beforeend', rowTemplate(index++));
                wireRow(body.lastElementChild);
                recalc();
            });
        }

        body.querySelectorAll('.item-row').forEach(wireRow);
        ['#discount', '#tax_rate'].forEach(function (sel) {
            const el = document.querySelector(sel);
            if (el) el.addEventListener('input', recalc);
        });
        recalc();
    }

    /* ---------------- Table search ---------------- */
    function initTableSearch() {
        document.querySelectorAll('[data-table-filter]').forEach(function (input) {
            const target = document.querySelector(input.getAttribute('data-table-filter'));
            if (!target) return;
            input.addEventListener('input', function () {
                const term = input.value.toLowerCase();
                target.querySelectorAll('tbody tr').forEach(function (row) {
                    row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
                });
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initTheme();
        initSidebar();
        initConfirms();
        initQuotationBuilder();
        initTableSearch();
    });

    // Apply theme as early as possible to avoid a flash.
    applyTheme(localStorage.getItem(THEME_KEY) || 'light');
})();
