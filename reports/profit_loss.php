<?php
/** صورت سود و زیان */
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
$page_title = 'صورت سود و زیان';

$dateFrom = jdate_to_gregorian(req('date_from')) ?: date('Y-01-01');
$dateTo = jdate_to_gregorian(req('date_to')) ?: today_gregorian();

function account_group_summary($type, $dateFrom, $dateTo)
{
    return DB::all(
        'SELECT a.id, a.code, a.name,
                COALESCE(SUM(jl.debit),0) AS total_debit,
                COALESCE(SUM(jl.credit),0) AS total_credit
         FROM accounts a
         LEFT JOIN journal_lines jl ON jl.account_id = a.id
         LEFT JOIN journal_entries je ON je.id = jl.entry_id AND je.entry_date BETWEEN ? AND ?
         WHERE a.account_type = ?
         GROUP BY a.id, a.code, a.name
         ORDER BY a.code',
        [$dateFrom, $dateTo, $type]
    );
}

$incomes = account_group_summary('income', $dateFrom, $dateTo);
$expenses = account_group_summary('expense', $dateFrom, $dateTo);

$incomeTotal = 0;
foreach ($incomes as $r) {
    $incomeTotal += (float)$r['total_credit'] - (float)$r['total_debit'];
}
$expenseTotal = 0;
foreach ($expenses as $r) {
    $expenseTotal += (float)$r['total_debit'] - (float)$r['total_credit'];
}
$net = $incomeTotal - $expenseTotal;

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

    <h3 class="card-title">درآمدها</h3>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>کد</th><th>نام حساب</th><th>مبلغ (ریال)</th></tr></thead>
            <tbody>
            <?php $hasIncome = false; ?>
            <?php foreach ($incomes as $r): ?>
                <?php $amount = (float)$r['total_credit'] - (float)$r['total_debit']; if ($amount == 0) continue; $hasIncome = true; ?>
                <tr>
                    <td><?= e($r['code']) ?></td>
                    <td><?= e($r['name']) ?></td>
                    <td class="num"><?= money($amount) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$hasIncome): ?>
                <tr><td colspan="3" class="empty-state">درآمدی ثبت نشده است.</td></tr>
            <?php endif; ?>
            </tbody>
            <tfoot><tr><td colspan="2">جمع درآمدها</td><td class="num"><?= money($incomeTotal) ?></td></tr></tfoot>
        </table>
    </div>

    <h3 class="card-title" style="margin-top:24px">هزینه‌ها</h3>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>کد</th><th>نام حساب</th><th>مبلغ (ریال)</th></tr></thead>
            <tbody>
            <?php $hasExpense = false; ?>
            <?php foreach ($expenses as $r): ?>
                <?php $amount = (float)$r['total_debit'] - (float)$r['total_credit']; if ($amount == 0) continue; $hasExpense = true; ?>
                <tr>
                    <td><?= e($r['code']) ?></td>
                    <td><?= e($r['name']) ?></td>
                    <td class="num"><?= money($amount) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$hasExpense): ?>
                <tr><td colspan="3" class="empty-state">هزینه‌ای ثبت نشده است.</td></tr>
            <?php endif; ?>
            </tbody>
            <tfoot><tr><td colspan="2">جمع هزینه‌ها</td><td class="num"><?= money($expenseTotal) ?></td></tr></tfoot>
        </table>
    </div>

    <div class="total-box" style="max-width:100%; margin-top:20px">
        <div class="total-row grand">
            <span><?= $net >= 0 ? 'سود خالص دوره' : 'زیان خالص دوره' ?></span>
            <span class="<?= $net >= 0 ? 'text-success' : 'text-danger' ?>"><?= money(abs($net)) ?> ریال</span>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/layout/footer.php'; ?>
