/* اسکریپت‌های عمومی سامانه حسابداری */
(function () {
    'use strict';

    /** تایید حذف */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (form.classList.contains('confirm-delete')) {
            if (!confirm(form.getAttribute('data-confirm') || 'آیا از حذف این مورد مطمئن هستید؟')) {
                e.preventDefault();
            }
        }
    });

    /** قالب‌بندی عددی برای نمایش مبلغ */
    function fmt(n) {
        n = Number(n) || 0;
        return n.toLocaleString('en-US', { maximumFractionDigits: 0 });
    }

    /* ================= فرم فاکتور ================= */
    var itemsBody = document.getElementById('items-body');
    if (itemsBody) {
        var addBtn = document.getElementById('add-item-row');
        var productData = {};
        try {
            productData = JSON.parse(document.getElementById('products-data')?.textContent || '{}');
        } catch (err) { /* ignore */ }

        function reindexRows() {
            var rows = itemsBody.querySelectorAll('tr.item-row');
            rows.forEach(function (row, idx) {
                row.querySelectorAll('[name]').forEach(function (inp) {
                    inp.name = inp.name.replace(/items\[\d+\]/, 'items[' + idx + ']');
                });
            });
        }

        function calcRow(row) {
            var qty = parseFloat(row.querySelector('.i-qty').value) || 0;
            var price = parseFloat(row.querySelector('.i-price').value) || 0;
            var discount = parseFloat(row.querySelector('.i-discount').value) || 0;
            var total = qty * price - discount;
            row.querySelector('.i-total').textContent = fmt(total);
            return total;
        }

        function calcInvoice() {
            var rows = itemsBody.querySelectorAll('tr.item-row');
            var itemsTotal = 0, discountTotal = 0;
            rows.forEach(function (row) {
                var qty = parseFloat(row.querySelector('.i-qty').value) || 0;
                var price = parseFloat(row.querySelector('.i-price').value) || 0;
                var discount = parseFloat(row.querySelector('.i-discount').value) || 0;
                itemsTotal += qty * price;
                discountTotal += discount;
            });
            var invoiceDiscount = parseFloat(document.getElementById('invoice-discount')?.value) || 0;
            var taxPercent = parseFloat(document.getElementById('invoice-tax')?.value) || 0;
            var net = itemsTotal - discountTotal - invoiceDiscount;
            if (net < 0) { net = 0; }
            var tax = Math.round(net * taxPercent / 100);
            var grand = net + tax;

            setText('sum-items', fmt(itemsTotal));
            setText('sum-discount', fmt(discountTotal + invoiceDiscount));
            setText('sum-tax', fmt(tax));
            setText('sum-grand', fmt(grand));
        }

        function setText(id, text) {
            var el = document.getElementById(id);
            if (el) { el.textContent = text; }
        }

        itemsBody.addEventListener('input', function (e) {
            var row = e.target.closest('tr.item-row');
            if (row) { calcRow(row); calcInvoice(); }
        });

        itemsBody.addEventListener('change', function (e) {
            if (e.target.classList.contains('i-product')) {
                var row = e.target.closest('tr.item-row');
                var p = productData[e.target.value];
                if (p && row) {
                    var typeField = document.getElementById('invoice-type');
                    var price = (typeField && typeField.value === 'purchase') ? p.buy_price : p.sell_price;
                    row.querySelector('.i-price').value = price || 0;
                    if (!row.querySelector('.i-desc').value) {
                        row.querySelector('.i-desc').value = p.name;
                    }
                    calcRow(row);
                    calcInvoice();
                }
            }
        });

        if (addBtn) {
            addBtn.addEventListener('click', function () {
                var rows = itemsBody.querySelectorAll('tr.item-row');
                var last = rows[rows.length - 1];
                var clone = last.cloneNode(true);
                clone.querySelectorAll('input').forEach(function (inp) {
                    if (inp.classList.contains('i-qty')) { inp.value = '1'; }
                    else if (inp.classList.contains('i-price') || inp.classList.contains('i-discount')) { inp.value = '0'; }
                    else { inp.value = ''; }
                });
                var sel = clone.querySelector('select');
                if (sel) { sel.selectedIndex = 0; }
                clone.querySelector('.i-total').textContent = '0';
                itemsBody.appendChild(clone);
                reindexRows();
            });
        }

        itemsBody.addEventListener('click', function (e) {
            if (e.target.classList.contains('remove-row')) {
                var rows = itemsBody.querySelectorAll('tr.item-row');
                if (rows.length > 1) {
                    e.target.closest('tr').remove();
                    reindexRows();
                    calcInvoice();
                }
            }
        });

        ['invoice-discount', 'invoice-tax'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) { el.addEventListener('input', calcInvoice); }
        });

        calcInvoice();
    }

    /* ================= انتخاب حساب‌های صندوق/بانک ================= */
    var txnType = document.getElementById('txn-type');
    if (txnType) {
        txnType.addEventListener('change', function () {
            var label = document.getElementById('txn-amount-label');
            if (label) {
                label.textContent = this.value === 'receive' ? 'مبلغ دریافتی' : 'مبلغ پرداختی';
            }
        });
    }

    /* ================= چاپ ================= */
    var printBtn = document.getElementById('print-btn');
    if (printBtn) {
        printBtn.addEventListener('click', function () { window.print(); });
    }
})();
