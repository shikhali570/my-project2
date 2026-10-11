<?php
/** دریافت و پرداخت وجه */
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
$page_title = 'دریافت و پرداخت';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = req('action');

    if ($action === 'save') {
        require_edit_access();
        $type = req('txn_type') === 'payment' ? 'payment' : 'receive';
        $amount = req_num('amount');
        $accountId = (int)req('account_id');
        $personId = (int)req('person_id') ?: null;
        $txnDate = jdate_to_gregorian(req('txn_date')) ?: today_gregorian();
        $invoiceId = (int)req('invoice_id') ?: null;

        if ($amount <= 0 || !$accountId) {
            flash_set('danger', 'مبلغ و حساب الزامی است.');
        } else {
            try {
                DB::begin();
                $txnId = DB::insert('transactions', [
                    'txn_no'     => next_doc_no($type === 'receive' ? 'R' : 'P', 'transactions', 'txn_no'),
                    'txn_type'   => $type,
                    'txn_date'   => $txnDate,
                    'person_id'  => $personId,
                    'account_id' => $accountId,
                    'invoice_id' => $invoiceId,
                    'amount'     => $amount,
                    'description'=> req('description'),
                    'user_id'    => current_user_id(),
                    'created_at' => now_datetime(),
                ]);
                $txn = DB::one('SELECT * FROM transactions WHERE id = ?', [$txnId]);
                post_transaction_journal($txn, current_user_id());
                if ($invoiceId) {
                    refresh_invoice_status($invoiceId);
                }
                DB::commit();
                flash_set('success', 'سند ' . txn_type_label($type) . ' وجه ثبت شد.');
            } catch (Throwable $e) {
                DB::rollBack();
                flash_set('danger', 'خطا در ثبت سند: ' . $e->getMessage());
            }
        }
        redirect('transactions.php');
    }

    if ($action === 'delete') {
        require_edit_access();
        $id = (int)req('id');
        $txn = DB::one('SELECT * FROM transactions WHERE id = ?', [$id]);
        if ($txn) {
            delete_journal_for('transaction', $id);
            DB::delete('transactions', 'id = ?', [$id]);
            if ($txn['invoice_id']) {
                refresh_invoice_status((int)$txn['invoice_id']);
            }
            flash_set('success', 'سند حذف شد.');
        }
        redirect('transactions.php');
    }
}

$typeFilter = req('type');
$search = req('search');
$dateFrom = jdate_to_gregorian(req('date_from'));
$dateTo = jdate_to_gregorian(req('date_to'));

$sql = 'SELECT t.*, p.name AS person_name, a.name AS account_name, i.invoice_no
        FROM transactions t
        LEFT JOIN persons p ON p.id = t.person_id
        LEFT JOIN accounts a ON a.id = t.account_id
        LEFT JOIN invoices i ON i.id = t.invoice_id
        WHERE 1=1';
$params = [];
if (in_array($typeFilter, ['receive', 'payment'], true)) {
    $sql .= ' AND t.txn_type = ?';
    $params[] = $typeFilter;
}
if ($search !== '') {
    $sql .= ' AND (t.txn_no LIKE ? OR p.name LIKE ? OR t.description LIKE ?)';
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like]);
}
if ($dateFrom) {
    $sql .= ' AND t.txn_date >= ?';
    $params[] = $dateFrom;
}
if ($dateTo) {
    $sql .= ' AND t.txn_date <= ?';
    $params[] = $dateTo;
}
$sql .= ' ORDER BY t.id DESC';
$txns = DB::all($sql, $params);

$persons = DB::all('SELECT * FROM persons ORDER BY name');
$cashAccounts = DB::all("SELECT * FROM accounts WHERE system_key IN ('cash','bank') ORDER BY code");
$openInvoices = DB::all("SELECT id, invoice_no, invoice_type, grand_total, paid_amount FROM invoices WHERE status != 'paid' ORDER BY id DESC");

include __DIR__ . '/includes/layout/header.php';
?>

<?php if (can_edit()): ?>
<div class="card">
    <h3 class="card-title">ثبت سند جدید</h3>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <div class="form-grid">
            <div class="form-group">
                <label>نوع سند</label>
                <select class="form-control" name="txn_type" id="txn-type">
                    <option value="receive">دریافت وجه</option>
                    <option value="payment">پرداخت وجه</option>
                </select>
            </div>
            <div class="form-group">
                <label id="txn-amount-label">مبلغ (ریال) *</label>
                <input class="form-control" type="text" name="amount" required>
            </div>
            <div class="form-group">
                <label>تاریخ (شمسی)</label>
                <input class="form-control" type="text" name="txn_date" value="<?= e(jdate(today_gregorian())) ?>">
            </div>
            <div class="form-group">
                <label>حساب صندوق / بانک *</label>
                <select class="form-control" name="account_id" required>
                    <?php foreach ($cashAccounts as $acc): ?>
                        <option value="<?= (int)$acc['id'] ?>"><?= e($acc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>شخص (اختیاری)</label>
                <select class="form-control" name="person_id">
                    <option value="">— بدون شخص —</option>
                    <?php foreach ($persons as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"><?= e($p['code'] . ' — ' . $p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>فاکتور مرتبط (اختیاری)</label>
                <select class="form-control" name="invoice_id">
                    <option value="">— بدون فاکتور —</option>
                    <?php foreach ($openInvoices as $inv): ?>
                        <option value="<?= (int)$inv['id'] ?>">
                            <?= e($inv['invoice_no'] . ' (' . invoice_type_label($inv['invoice_type']) . ') — مانده: ' . money((float)$inv['grand_total'] - (float)$inv['paid_amount'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="grid-column:1/-1">
                <label>توضیحات</label>
                <input class="form-control" type="text" name="description">
            </div>
        </div>
        <button class="btn btn-primary" type="submit">ثبت سند</button>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <form method="get" class="report-filters">
        <div class="form-group">
            <label>نوع</label>
            <select class="form-control" name="type">
                <option value="">همه</option>
                <option value="receive" <?= $typeFilter === 'receive' ? 'selected' : '' ?>>دریافت</option>
                <option value="payment" <?= $typeFilter === 'payment' ? 'selected' : '' ?>>پرداخت</option>
            </select>
        </div>
        <div class="form-group">
            <label>جستجو</label>
            <input class="form-control" type="text" name="search" value="<?= e($search) ?>">
        </div>
        <div class="form-group">
            <label>از تاریخ (شمسی)</label>
            <input class="form-control" type="text" name="date_from" value="<?= e(req('date_from')) ?>" placeholder="1404/01/01">
        </div>
        <div class="form-group">
            <label>تا تاریخ (شمسی)</label>
            <input class="form-control" type="text" name="date_to" value="<?= e(req('date_to')) ?>" placeholder="1404/12/29">
        </div>
        <button class="btn btn-primary" type="submit">فیلتر</button>
        <a class="btn btn-outline" href="transactions.php">پاک‌کردن</a>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>شماره سند</th><th>نوع</th><th>تاریخ</th><th>شخص</th><th>حساب</th>
                <th>فاکتور</th><th>مبلغ</th><th>توضیحات</th><th>عملیات</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$txns): ?>
                <tr><td colspan="9" class="empty-state">سندی یافت نشد.</td></tr>
            <?php endif; ?>
            <?php foreach ($txns as $t): ?>
                <tr>
                    <td><strong><?= e($t['txn_no']) ?></strong></td>
                    <td><span class="badge <?= $t['txn_type'] === 'receive' ? 'badge-success' : 'badge-danger' ?>"><?= e(txn_type_label($t['txn_type'])) ?></span></td>
                    <td><?= e(jdate($t['txn_date'])) ?></td>
                    <td><?= e($t['person_name'] ?? '—') ?></td>
                    <td><?= e($t['account_name']) ?></td>
                    <td><?= $t['invoice_no'] ? '<a href="invoice_view.php?id=' . (int)$t['invoice_id'] . '">' . e($t['invoice_no']) . '</a>' : '—' ?></td>
                    <td class="num"><?= money($t['amount']) ?></td>
                    <td><?= e($t['description'] ?? '') ?></td>
                    <td>
                        <?php if (can_edit()): ?>
                            <form method="post" class="confirm-delete" style="display:inline" data-confirm="این سند حذف شود؟">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                <button class="btn btn-sm btn-danger" type="submit">حذف</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/includes/layout/footer.php'; ?>
