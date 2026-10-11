<?php
/** گزارش فاکتورهای خرید و فروش */
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
$page_title = 'گزارش فاکتورها';

$type = req('type');
$status = req('status');
$personId = (int)req('person_id');
$dateFrom = jdate_to_gregorian(req('date_from'));
$dateTo = jdate_to_gregorian(req('date_to'));

$sql = 'SELECT i.*, p.name AS person_name FROM invoices i
        LEFT JOIN persons p ON p.id = i.person_id
        WHERE 1=1';
$params = [];
if (in_array($type, ['sale', 'purchase'], true)) {
    $sql .= ' AND i.invoice_type = ?';
    $params[] = $type;
}
if (in_array($status, ['unpaid', 'partial', 'paid'], true)) {
    $sql .= ' AND i.status = ?';
    $params[] = $status;
}
if ($personId) {
    $sql .= ' AND i.person_id = ?';
    $params[] = $personId;
}
if ($dateFrom) {
    $sql .= ' AND i.invoice_date >= ?';
    $params[] = $dateFrom;
}
if ($dateTo) {
    $sql .= ' AND i.invoice_date <= ?';
    $params[] = $dateTo;
}
$sql .= ' ORDER BY i.invoice_date, i.id';
$invoices = DB::all($sql, $params);

$sumItems = 0;
$sumDiscount = 0;
$sumTax = 0;
$sumGrand = 0;
$sumPaid = 0;
foreach ($invoices as $inv) {
    $sumItems += (float)$inv['items_total'];
    $sumDiscount += (float)$inv['discount_total'];
    $sumTax += (float)$inv['tax_total'];
    $sumGrand += (float)$inv['grand_total'];
    $sumPaid += (float)$inv['paid_amount'];
}

$persons = DB::all('SELECT * FROM persons ORDER BY name');

include __DIR__ . '/../includes/layout/header.php';
?>
<div class="card">
    <form method="get" class="report-filters">
        <div class="form-group">
            <label>نوع فاکتور</label>
            <select class="form-control" name="type">
                <option value="">همه</option>
                <option value="sale" <?= $type === 'sale' ? 'selected' : '' ?>>فروش</option>
                <option value="purchase" <?= $type === 'purchase' ? 'selected' : '' ?>>خرید</option>
            </select>
        </div>
        <div class="form-group">
            <label>شخص</label>
            <select class="form-control" name="person_id">
                <option value="">همه</option>
                <?php foreach ($persons as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $personId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
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
        <button class="btn btn-primary" type="submit">نمایش</button>
        <button class="btn btn-outline no-print" type="button" onclick="window.print()">چاپ</button>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>شماره</th><th>نوع</th><th>شخص</th><th>تاریخ</th>
                <th>جمع اقلام</th><th>تخفیف</th><th>مالیات</th><th>مبلغ کل</th><th>پرداخت‌شده</th><th>مانده</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$invoices): ?>
                <tr><td colspan="10" class="empty-state">فاکتوری یافت نشد.</td></tr>
            <?php endif; ?>
            <?php foreach ($invoices as $inv): ?>
                <tr>
                    <td>
                        <a href="<?= url('invoice_view.php?id=' . (int)$inv['id']) ?>"><?= e($inv['invoice_no']) ?></a>
                    </td>
                    <td><span class="badge badge-info"><?= e(invoice_type_label($inv['invoice_type'])) ?></span></td>
                    <td><?= e($inv['person_name'] ?? '—') ?></td>
                    <td><?= e(jdate($inv['invoice_date'])) ?></td>
                    <td class="num"><?= money($inv['items_total']) ?></td>
                    <td class="num"><?= money($inv['discount_total']) ?></td>
                    <td class="num"><?= money($inv['tax_total']) ?></td>
                    <td class="num"><?= money($inv['grand_total']) ?></td>
                    <td class="num"><?= money($inv['paid_amount']) ?></td>
                    <td class="num"><?= money((float)$inv['grand_total'] - (float)$inv['paid_amount']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <?php if ($invoices): ?>
            <tfoot>
            <tr>
                <td colspan="4">جمع</td>
                <td class="num"><?= money($sumItems) ?></td>
                <td class="num"><?= money($sumDiscount) ?></td>
                <td class="num"><?= money($sumTax) ?></td>
                <td class="num"><?= money($sumGrand) ?></td>
                <td class="num"><?= money($sumPaid) ?></td>
                <td class="num"><?= money($sumGrand - $sumPaid) ?></td>
            </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/layout/footer.php'; ?>
