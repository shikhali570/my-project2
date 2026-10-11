<?php
/** لیست فاکتورهای خرید و فروش */
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$type = req('type', 'sale') === 'purchase' ? 'purchase' : 'sale';
$page_title = $type === 'sale' ? 'فاکتورهای فروش' : 'فاکتورهای خرید';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (req('action') === 'delete') {
        require_edit_access();
        $id = (int)req('id');
        $paid = (float)DB::value('SELECT COALESCE(SUM(amount),0) FROM transactions WHERE invoice_id = ?', [$id], 0);
        if ($paid > 0) {
            flash_set('danger', 'این فاکتور دارای پرداخت است؛ ابتدا اسناد دریافت/پرداخت مرتبط را حذف کنید.');
        } else {
            delete_journal_for('invoice', $id);
            DB::delete('invoice_items', 'invoice_id = ?', [$id]);
            DB::delete('invoices', 'id = ?', [$id]);
            flash_set('success', 'فاکتور حذف شد.');
        }
        redirect('invoices.php?type=' . $type);
    }
}

$search = req('search');
$status = req('status');
$dateFrom = jdate_to_gregorian(req('date_from'));
$dateTo = jdate_to_gregorian(req('date_to'));

$sql = 'SELECT i.*, p.name AS person_name FROM invoices i
        LEFT JOIN persons p ON p.id = i.person_id
        WHERE i.invoice_type = ?';
$params = [$type];
if ($search !== '') {
    $sql .= ' AND (i.invoice_no LIKE ? OR p.name LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}
if (in_array($status, ['unpaid', 'partial', 'paid'], true)) {
    $sql .= ' AND i.status = ?';
    $params[] = $status;
}
if ($dateFrom) {
    $sql .= ' AND i.invoice_date >= ?';
    $params[] = $dateFrom;
}
if ($dateTo) {
    $sql .= ' AND i.invoice_date <= ?';
    $params[] = $dateTo;
}
$sql .= ' ORDER BY i.id DESC';
$invoices = DB::all($sql, $params);

$sumGrand = array_sum(array_map(fn($r) => (float)$r['grand_total'], $invoices));
$sumPaid = array_sum(array_map(fn($r) => (float)$r['paid_amount'], $invoices));

include __DIR__ . '/includes/layout/header.php';
?>
<div class="flex-between mb-20">
    <div>
        <a class="btn <?= $type === 'sale' ? 'btn-primary' : 'btn-outline' ?>" href="invoices.php?type=sale">فروش</a>
        <a class="btn <?= $type === 'purchase' ? 'btn-primary' : 'btn-outline' ?>" href="invoices.php?type=purchase">خرید</a>
    </div>
    <?php if (can_edit()): ?>
        <a class="btn btn-success" href="invoice_edit.php?type=<?= $type ?>">+ فاکتور جدید <?= e(invoice_type_label($type)) ?></a>
    <?php endif; ?>
</div>

<div class="card">
    <form method="get" class="report-filters">
        <input type="hidden" name="type" value="<?= e($type) ?>">
        <div class="form-group">
            <label>جستجو (شماره / شخص)</label>
            <input class="form-control" type="text" name="search" value="<?= e($search) ?>">
        </div>
        <div class="form-group">
            <label>وضعیت</label>
            <select class="form-control" name="status">
                <option value="">همه</option>
                <option value="unpaid" <?= $status === 'unpaid' ? 'selected' : '' ?>>پرداخت‌نشده</option>
                <option value="partial" <?= $status === 'partial' ? 'selected' : '' ?>>پرداخت جزئی</option>
                <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>پرداخت‌شده</option>
            </select>
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
        <a class="btn btn-outline" href="invoices.php?type=<?= e($type) ?>">پاک‌کردن</a>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>شماره فاکتور</th><th>شخص</th><th>تاریخ</th><th>سررسید</th>
                <th>مبلغ کل</th><th>پرداخت‌شده</th><th>مانده</th><th>وضعیت</th><th>عملیات</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$invoices): ?>
                <tr><td colspan="9" class="empty-state">فاکتوری یافت نشد.</td></tr>
            <?php endif; ?>
            <?php foreach ($invoices as $inv): ?>
                <tr>
                    <td><strong><?= e($inv['invoice_no']) ?></strong></td>
                    <td><?= e($inv['person_name'] ?? '—') ?></td>
                    <td><?= e(jdate($inv['invoice_date'])) ?></td>
                    <td><?= $inv['due_date'] ? e(jdate($inv['due_date'])) : '—' ?></td>
                    <td class="num"><?= money($inv['grand_total']) ?></td>
                    <td class="num"><?= money($inv['paid_amount']) ?></td>
                    <td class="num"><?= money((float)$inv['grand_total'] - (float)$inv['paid_amount']) ?></td>
                    <td><?= status_badge($inv['status']) ?></td>
                    <td>
                        <a class="btn btn-sm btn-outline" href="invoice_view.php?id=<?= (int)$inv['id'] ?>">مشاهده</a>
                        <?php if (can_edit()): ?>
                            <a class="btn btn-sm btn-outline" href="invoice_edit.php?id=<?= (int)$inv['id'] ?>">ویرایش</a>
                            <form method="post" class="confirm-delete" style="display:inline"
                                  data-confirm="آیا از حذف فاکتور <?= e($inv['invoice_no']) ?> مطمئن هستید؟">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$inv['id'] ?>">
                                <button class="btn btn-sm btn-danger" type="submit">حذف</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <?php if ($invoices): ?>
            <tfoot>
            <tr>
                <td colspan="4">جمع</td>
                <td class="num"><?= money($sumGrand) ?></td>
                <td class="num"><?= money($sumPaid) ?></td>
                <td class="num"><?= money($sumGrand - $sumPaid) ?></td>
                <td colspan="2"></td>
            </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>
<?php include __DIR__ . '/includes/layout/footer.php'; ?>
