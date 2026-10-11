<?php
/** تنخواه و هزینه‌ها */
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
$page_title = 'تنخواه و هزینه‌ها';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = req('action');

    if ($action === 'save') {
        require_edit_access();
        $amount = req_num('amount');
        $categoryId = (int)req('category_account_id');
        $accountId = (int)req('account_id');
        $expenseDate = jdate_to_gregorian(req('expense_date')) ?: today_gregorian();

        if ($amount <= 0 || !$categoryId || !$accountId) {
            flash_set('danger', 'مبلغ، حساب هزینه و حساب پرداخت الزامی است.');
        } else {
            try {
                DB::begin();
                $expenseId = DB::insert('expenses', [
                    'expense_no'          => next_doc_no('E', 'expenses', 'expense_no'),
                    'expense_date'        => $expenseDate,
                    'category_account_id' => $categoryId,
                    'account_id'          => $accountId,
                    'amount'              => $amount,
                    'description'         => req('description'),
                    'user_id'             => current_user_id(),
                    'created_at'          => now_datetime(),
                ]);
                $expense = DB::one('SELECT * FROM expenses WHERE id = ?', [$expenseId]);
                post_expense_journal($expense, current_user_id());
                DB::commit();
                flash_set('success', 'سند هزینه ثبت شد.');
            } catch (Throwable $e) {
                DB::rollBack();
                flash_set('danger', 'خطا در ثبت هزینه: ' . $e->getMessage());
            }
        }
        redirect('expenses.php');
    }

    if ($action === 'delete') {
        require_edit_access();
        $id = (int)req('id');
        delete_journal_for('expense', $id);
        DB::delete('expenses', 'id = ?', [$id]);
        flash_set('success', 'سند هزینه حذف شد.');
        redirect('expenses.php');
    }
}

$expenseAccounts = DB::all("SELECT * FROM accounts WHERE account_type = 'expense' AND system_key IS NULL ORDER BY code");
$cashAccounts = DB::all("SELECT * FROM accounts WHERE system_key IN ('cash','bank') ORDER BY code");

$dateFrom = jdate_to_gregorian(req('date_from'));
$dateTo = jdate_to_gregorian(req('date_to'));
$sql = 'SELECT x.*, ca.name AS category_name, pa.name AS account_name
        FROM expenses x
        LEFT JOIN accounts ca ON ca.id = x.category_account_id
        LEFT JOIN accounts pa ON pa.id = x.account_id
        WHERE 1=1';
$params = [];
if ($dateFrom) {
    $sql .= ' AND x.expense_date >= ?';
    $params[] = $dateFrom;
}
if ($dateTo) {
    $sql .= ' AND x.expense_date <= ?';
    $params[] = $dateTo;
}
$sql .= ' ORDER BY x.id DESC';
$expenses = DB::all($sql, $params);
$sumAll = array_sum(array_map(fn($r) => (float)$r['amount'], $expenses));

include __DIR__ . '/includes/layout/header.php';
?>

<?php if (can_edit()): ?>
<div class="card">
    <h3 class="card-title">ثبت سند هزینه جدید</h3>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <div class="form-grid">
            <div class="form-group">
                <label>مبلغ (ریال) *</label>
                <input class="form-control" type="text" name="amount" required>
            </div>
            <div class="form-group">
                <label>تاریخ (شمسی)</label>
                <input class="form-control" type="text" name="expense_date" value="<?= e(jdate(today_gregorian())) ?>">
            </div>
            <div class="form-group">
                <label>حساب هزینه *</label>
                <select class="form-control" name="category_account_id" required>
                    <?php foreach ($expenseAccounts as $acc): ?>
                        <option value="<?= (int)$acc['id'] ?>"><?= e($acc['code'] . ' — ' . $acc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>حساب پرداخت (صندوق / بانک) *</label>
                <select class="form-control" name="account_id" required>
                    <?php foreach ($cashAccounts as $acc): ?>
                        <option value="<?= (int)$acc['id'] ?>"><?= e($acc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="grid-column:1/-1">
                <label>توضیحات</label>
                <input class="form-control" type="text" name="description" placeholder="شرح هزینه">
            </div>
        </div>
        <button class="btn btn-primary" type="submit">ثبت سند هزینه</button>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <form method="get" class="report-filters">
        <div class="form-group">
            <label>از تاریخ (شمسی)</label>
            <input class="form-control" type="text" name="date_from" value="<?= e(req('date_from')) ?>" placeholder="1404/01/01">
        </div>
        <div class="form-group">
            <label>تا تاریخ (شمسی)</label>
            <input class="form-control" type="text" name="date_to" value="<?= e(req('date_to')) ?>" placeholder="1404/12/29">
        </div>
        <button class="btn btn-primary" type="submit">فیلتر</button>
        <a class="btn btn-outline" href="expenses.php">پاک‌کردن</a>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>شماره سند</th><th>تاریخ</th><th>حساب هزینه</th><th>حساب پرداخت</th>
                <th>مبلغ</th><th>توضیحات</th><th>عملیات</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$expenses): ?>
                <tr><td colspan="7" class="empty-state">سند هزینه‌ای یافت نشد.</td></tr>
            <?php endif; ?>
            <?php foreach ($expenses as $x): ?>
                <tr>
                    <td><strong><?= e($x['expense_no']) ?></strong></td>
                    <td><?= e(jdate($x['expense_date'])) ?></td>
                    <td><?= e($x['category_name']) ?></td>
                    <td><?= e($x['account_name']) ?></td>
                    <td class="num"><?= money($x['amount']) ?></td>
                    <td><?= e($x['description'] ?? '') ?></td>
                    <td>
                        <?php if (can_edit()): ?>
                            <form method="post" class="confirm-delete" style="display:inline" data-confirm="این سند هزینه حذف شود؟">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$x['id'] ?>">
                                <button class="btn btn-sm btn-danger" type="submit">حذف</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <?php if ($expenses): ?>
            <tfoot>
            <tr>
                <td colspan="4">جمع کل</td>
                <td class="num"><?= money($sumAll) ?></td>
                <td colspan="2"></td>
            </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>
<?php include __DIR__ . '/includes/layout/footer.php'; ?>
