<?php
/** نمایش فاکتور + ثبت پرداخت + چاپ */
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$id = (int)req('id');
$invoice = DB::one('SELECT * FROM invoices WHERE id = ?', [$id]);
if (!$invoice) {
    flash_set('danger', 'فاکتور مورد نظر یافت نشد.');
    redirect('invoices.php');
}
$page_title = 'فاکتور ' . invoice_type_label($invoice['invoice_type']) . ' ' . $invoice['invoice_no'];

$person = DB::one('SELECT * FROM persons WHERE id = ?', [(int)$invoice['person_id']]);
$items = DB::all(
    'SELECT ii.*, p.name AS product_name, p.unit FROM invoice_items ii
     LEFT JOIN products p ON p.id = ii.product_id
     WHERE ii.invoice_id = ? ORDER BY ii.id',
    [$id]
);
$payments = DB::all(
    'SELECT t.*, a.name AS account_name FROM transactions t
     LEFT JOIN accounts a ON a.id = t.account_id
     WHERE t.invoice_id = ? ORDER BY t.id',
    [$id]
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = req('action');

    if ($action === 'pay') {
        require_edit_access();
        $amount = req_num('amount');
        $payDate = jdate_to_gregorian(req('pay_date')) ?: today_gregorian();
        $accountId = (int)req('account_id');
        if ($amount <= 0 || !$accountId) {
            flash_set('danger', 'مبلغ و حساب پرداخت الزامی است.');
        } else {
            try {
                DB::begin();
                $txnId = DB::insert('transactions', [
                    'txn_no'     => next_doc_no($invoice['invoice_type'] === 'sale' ? 'R' : 'P', 'transactions', 'txn_no'),
                    'txn_type'   => $invoice['invoice_type'] === 'sale' ? 'receive' : 'payment',
                    'txn_date'   => $payDate,
                    'person_id'  => $invoice['person_id'],
                    'account_id' => $accountId,
                    'invoice_id' => $id,
                    'amount'     => $amount,
                    'description'=> req('description') ?: ('پرداخت بابت فاکتور ' . $invoice['invoice_no']),
                    'user_id'    => current_user_id(),
                    'created_at' => now_datetime(),
                ]);
                $txn = DB::one('SELECT * FROM transactions WHERE id = ?', [$txnId]);
                post_transaction_journal($txn, current_user_id());
                refresh_invoice_status($id);
                DB::commit();
                flash_set('success', 'پرداخت به مبلغ ' . money($amount) . ' ریال ثبت شد.');
            } catch (Throwable $e) {
                DB::rollBack();
                flash_set('danger', 'خطا در ثبت پرداخت: ' . $e->getMessage());
            }
        }
        redirect('invoice_view.php?id=' . $id);
    }

    if ($action === 'delete_payment') {
        require_edit_access();
        $txnId = (int)req('txn_id');
        $txn = DB::one('SELECT * FROM transactions WHERE id = ? AND invoice_id = ?', [$txnId, $id]);
        if ($txn) {
            delete_journal_for('transaction', $txnId);
            DB::delete('transactions', 'id = ?', [$txnId]);
            refresh_invoice_status($id);
            flash_set('success', 'سند پرداخت حذف شد.');
        }
        redirect('invoice_view.php?id=' . $id);
    }

    if ($action === 'delete_invoice') {
        require_edit_access();
        $paid = (float)DB::value('SELECT COALESCE(SUM(amount),0) FROM transactions WHERE invoice_id = ?', [$id], 0);
        if ($paid > 0) {
            flash_set('danger', 'ابتدا اسناد پرداخت این فاکتور را حذف کنید.');
        } else {
            delete_journal_for('invoice', $id);
            DB::delete('invoice_items', 'invoice_id = ?', [$id]);
            DB::delete('invoices', 'id = ?', [$id]);
            flash_set('success', 'فاکتور حذف شد.');
            redirect('invoices.php?type=' . $invoice['invoice_type']);
        }
        redirect('invoice_view.php?id=' . $id);
    }
}

$cashAccounts = DB::all("SELECT * FROM accounts WHERE system_key IN ('cash','bank') ORDER BY code");
$company = [
    'name'    => setting('company_name', config('app.name')),
    'phone'   => setting('company_phone'),
    'address' => setting('company_address'),
    'economic_code' => setting('company_economic_code'),
];

include __DIR__ . '/includes/layout/header.php';
?>
<div class="flex-between mb-20 no-print">
    <div>
        <a class="btn btn-outline" href="invoices.php?type=<?= e($invoice['invoice_type']) ?>">بازگشت به لیست</a>
        <?php if (can_edit()): ?>
            <a class="btn btn-outline" href="invoice_edit.php?id=<?= (int)$id ?>">ویرایش فاکتور</a>
        <?php endif; ?>
    </div>
    <div>
        <button class="btn btn-primary" type="button" id="print-btn">چاپ فاکتور</button>
        <?php if (can_edit()): ?>
            <form method="post" class="confirm-delete" style="display:inline"
                  data-confirm="آیا از حذف کامل این فاکتور مطمئن هستید؟">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_invoice">
                <button class="btn btn-danger" type="submit">حذف فاکتور</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="invoice-head">
        <div>
            <h2 style="margin:0 0 6px"><?= e($company['name']) ?></h2>
            <div class="text-muted"><?= e($company['address']) ?> | تلفن: <?= e($company['phone']) ?></div>
            <?php if ($company['economic_code']): ?>
                <div class="text-muted">کد اقتصادی: <?= e($company['economic_code']) ?></div>
            <?php endif; ?>
        </div>
        <div>
            <table class="invoice-meta">
                <tr><td class="text-muted">شماره فاکتور:</td><td><strong><?= e($invoice['invoice_no']) ?></strong></td></tr>
                <tr><td class="text-muted">نوع:</td><td><?= e(invoice_type_label($invoice['invoice_type'])) ?></td></tr>
                <tr><td class="text-muted">تاریخ:</td><td><?= e(jdate($invoice['invoice_date'])) ?></td></tr>
                <?php if ($invoice['due_date']): ?>
                    <tr><td class="text-muted">سررسید:</td><td><?= e(jdate($invoice['due_date'])) ?></td></tr>
                <?php endif; ?>
                <tr><td class="text-muted">وضعیت:</td><td><?= status_badge($invoice['status']) ?></td></tr>
            </table>
        </div>
    </div>

    <div class="invoice-head">
        <div>
            <strong><?= $invoice['invoice_type'] === 'sale' ? 'خریدار' : 'فروشنده' ?>:</strong>
            <?= e($person['name'] ?? '—') ?>
            <div class="text-muted"><?= e($person['address'] ?? '') ?></div>
            <div class="text-muted"><?= e($person['phone'] ?? '') ?></div>
        </div>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>#</th><th>شرح کالا / خدمات</th><th>تعداد</th><th>قیمت واحد</th><th>تخفیف</th><th>جمع</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $i => $item): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($item['description'] ?: ($item['product_name'] ?? '—')) ?></td>
                    <td class="num"><?= qty_format($item['qty']) ?> <?= e($item['unit'] ?? '') ?></td>
                    <td class="num"><?= money($item['unit_price']) ?></td>
                    <td class="num"><?= money($item['discount']) ?></td>
                    <td class="num"><?= money($item['total']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="total-box">
        <div class="total-row"><span>جمع اقلام</span><span><?= money($invoice['items_total']) ?> ریال</span></div>
        <div class="total-row"><span>تخفیف</span><span><?= money($invoice['discount_total']) ?> ریال</span></div>
        <div class="total-row"><span>مالیات (<?= e($invoice['tax_percent']) ?>%)</span><span><?= money($invoice['tax_total']) ?> ریال</span></div>
        <div class="total-row grand"><span>مبلغ کل</span><span><?= money($invoice['grand_total']) ?> ریال</span></div>
        <div class="total-row"><span>پرداخت‌شده</span><span class="text-success"><?= money($invoice['paid_amount']) ?> ریال</span></div>
        <div class="total-row"><span>مانده</span><span class="text-danger"><?= money((float)$invoice['grand_total'] - (float)$invoice['paid_amount']) ?> ریال</span></div>
    </div>

    <?php if ($invoice['description']): ?>
        <p class="text-muted" style="margin-top:16px">توضیحات: <?= e($invoice['description']) ?></p>
    <?php endif; ?>
</div>

<div class="card no-print">
    <h3 class="card-title">پرداخت‌های این فاکتور</h3>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>شماره سند</th><th>نوع</th><th>تاریخ</th><th>حساب</th><th>مبلغ</th><th>توضیحات</th><th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$payments): ?>
                <tr><td colspan="7" class="empty-state">پرداختی ثبت نشده است.</td></tr>
            <?php endif; ?>
            <?php foreach ($payments as $pay): ?>
                <tr>
                    <td><?= e($pay['txn_no']) ?></td>
                    <td><span class="badge <?= $pay['txn_type'] === 'receive' ? 'badge-success' : 'badge-danger' ?>"><?= e(txn_type_label($pay['txn_type'])) ?></span></td>
                    <td><?= e(jdate($pay['txn_date'])) ?></td>
                    <td><?= e($pay['account_name'] ?? '—') ?></td>
                    <td class="num"><?= money($pay['amount']) ?></td>
                    <td><?= e($pay['description'] ?? '') ?></td>
                    <td>
                        <?php if (can_edit()): ?>
                            <form method="post" class="confirm-delete" style="display:inline" data-confirm="این سند پرداخت حذف شود؟">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_payment">
                                <input type="hidden" name="txn_id" value="<?= (int)$pay['id'] ?>">
                                <button class="btn btn-sm btn-danger" type="submit">حذف</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (can_edit()): ?>
        <h3 class="card-title" style="margin-top:22px">ثبت پرداخت جدید</h3>
        <form method="post" class="form-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="pay">
            <div class="form-group">
                <label>مبلغ (ریال)</label>
                <input class="form-control" type="text" name="amount" required
                       value="<?= e((float)$invoice['grand_total'] - (float)$invoice['paid_amount']) ?>">
            </div>
            <div class="form-group">
                <label>تاریخ (شمسی)</label>
                <input class="form-control" type="text" name="pay_date" value="<?= e(jdate(today_gregorian())) ?>">
            </div>
            <div class="form-group">
                <label>حساب</label>
                <select class="form-control" name="account_id" required>
                    <?php foreach ($cashAccounts as $acc): ?>
                        <option value="<?= (int)$acc['id'] ?>"><?= e($acc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>توضیحات</label>
                <input class="form-control" type="text" name="description">
            </div>
            <button class="btn btn-success" type="submit">ثبت پرداخت</button>
        </form>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/layout/footer.php'; ?>
