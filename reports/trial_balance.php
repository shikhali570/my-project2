<?php
/** تراز آزمایشی */
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
$page_title = 'تراز آزمایشی';

$dateFrom = jdate_to_gregorian(req('date_from')) ?: date('Y-01-01');
$dateTo = jdate_to_gregorian(req('date_to')) ?: today_gregorian();

$rows = DB::all(
    'SELECT a.id, a.code, a.name, a.account_type,
            COALESCE(SUM(jl.debit),0) AS total_debit,
            COALESCE(SUM(jl.credit),0) AS total_credit
     FROM accounts a
     LEFT JOIN journal_lines jl ON jl.account_id = a.id
     LEFT JOIN journal_entries je ON je.id = jl.entry_id AND je.entry_date BETWEEN ? AND ?
     GROUP BY a.id, a.code, a.name, a.account_type
     ORDER BY a.code',
    [$dateFrom, $dateTo]
);

$sumDebit = 0;
$sumCredit = 0;
foreach ($rows as $r) {
    $sumDebit += (float)$r['total_debit'];
    $sumCredit += (float)$r['total_credit'];
}

include __DIR__ . '/../includes/layout/header.php';
?>
<div class="card">
    <form method="get" class="report-filters">
        <div class="form-group">
            <label>از تاریخ (شمسی)</label>
            <input class="form-control" type="text" name="date_from" value="<?= e(req('date_from', jdate($dateFrom))) ?>">
        </div>
        <div class="form-group">
            <label>تا تاریخ (شمسی)</label>
            <input class="form-control" type="text" name="date_to" value="<?= e(req('date_to', jdate($dateTo))) ?>">
        </div>
        <button class="btn btn-primary" type="submit">نمایش</button>
        <button class="btn btn-outline no-print" type="button" onclick="window.print()">چاپ</button>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>کد حساب</th><th>نام حساب</th><th>نوع</th>
                <th>گردش بدهکار</th><th>گردش بستانکار</th><th>مانده بدهکار</th><th>مانده بستانکار</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <?php
                $balance = (float)$r['total_debit'] - (float)$r['total_credit'];
                if ((float)$r['total_debit'] == 0 && (float)$r['total_credit'] == 0) {
                    continue; // حساب بدون گردش
                }
                ?>
                <tr>
                    <td><?= e($r['code']) ?></td>
                    <td><?= e($r['name']) ?></td>
                    <td><span class="badge badge-secondary"><?= e(account_type_label($r['account_type'])) ?></span></td>
                    <td class="num"><?= money($r['total_debit']) ?></td>
                    <td class="num"><?= money($r['total_credit']) ?></td>
                    <td class="num"><?= $balance > 0 ? money($balance) : '—' ?></td>
                    <td class="num"><?= $balance < 0 ? money(abs($balance)) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
            <tr>
                <td colspan="3">جمع</td>
                <td class="num"><?= money($sumDebit) ?></td>
                <td class="num"><?= money($sumCredit) ?></td>
                <td colspan="2" class="<?= abs($sumDebit - $sumCredit) < 0.01 ? 'text-success' : 'text-danger' ?>">
                    <?= abs($sumDebit - $sumCredit) < 0.01 ? 'تراز است ✓' : 'اختلاف ' . money(abs($sumDebit - $sumCredit)) . ' ریال' ?>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/layout/footer.php'; ?>
