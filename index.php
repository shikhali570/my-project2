<?php
/** داشبورد */
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
$page_title = 'داشبورد';

// شاخص‌های اصلی
$cashTotal = cash_balance_total();

$receivable = 0;
$payable = 0;
foreach (DB::all('SELECT id FROM persons') as $p) {
    $bal = person_balance((int)$p['id']);
    if ($bal > 0) {
        $receivable += $bal;
    } elseif ($bal < 0) {
        $payable += abs($bal);
    }
}

$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$saleMonth = (float)DB::value(
    "SELECT COALESCE(SUM(grand_total),0) FROM invoices WHERE invoice_type='sale' AND invoice_date BETWEEN ? AND ?",
    [$monthStart, $monthEnd], 0
);
$purchaseMonth = (float)DB::value(
    "SELECT COALESCE(SUM(grand_total),0) FROM invoices WHERE invoice_type='purchase' AND invoice_date BETWEEN ? AND ?",
    [$monthStart, $monthEnd], 0
);

$yearStart = date('Y-01-01');
$incomeTotal = (float)DB::value(
    "SELECT COALESCE(SUM(jl.credit - jl.debit),0) FROM journal_lines jl
     JOIN journal_entries je ON je.id = jl.entry_id
     JOIN accounts a ON a.id = jl.account_id
     WHERE a.account_type = 'income' AND je.entry_date >= ?",
    [$yearStart], 0
);
$expenseTotal = (float)DB::value(
    "SELECT COALESCE(SUM(jl.debit - jl.credit),0) FROM journal_lines jl
     JOIN journal_entries je ON je.id = jl.entry_id
     JOIN accounts a ON a.id = jl.account_id
     WHERE a.account_type = 'expense' AND je.entry_date >= ?",
    [$yearStart], 0
);
$netProfit = $incomeTotal - $expenseTotal;

// نمودار ۶ ماه اخیر
$chart = [];
for ($i = 5; $i >= 0; $i--) {
    $start = date('Y-m-01', strtotime(date('Y-m-01') . " -$i months"));
    $end = date('Y-m-t', strtotime($start));
    $chart[] = [
        'label'    => jdate($start, '/'),
        'sale'     => (float)DB::value(
            "SELECT COALESCE(SUM(grand_total),0) FROM invoices WHERE invoice_type='sale' AND invoice_date BETWEEN ? AND ?",
            [$start, $end], 0),
        'purchase' => (float)DB::value(
            "SELECT COALESCE(SUM(grand_total),0) FROM invoices WHERE invoice_type='purchase' AND invoice_date BETWEEN ? AND ?",
            [$start, $end], 0),
    ];
}
$maxBar = 1;
foreach ($chart as $c) {
    $maxBar = max($maxBar, $c['sale'], $c['purchase']);
}

$recentInvoices = DB::all(
    'SELECT i.*, p.name AS person_name FROM invoices i
     LEFT JOIN persons p ON p.id = i.person_id
     ORDER BY i.id DESC LIMIT 6'
);
$recentTxns = DB::all(
    'SELECT t.*, p.name AS person_name, a.name AS account_name FROM transactions t
     LEFT JOIN persons p ON p.id = t.person_id
     LEFT JOIN accounts a ON a.id = t.account_id
     ORDER BY t.id DESC LIMIT 6'
);

include __DIR__ . '/includes/layout/header.php';
?>
<div class="stat-grid">
    <div class="stat-card blue">
        <div class="stat-label">موجودی صندوق و بانک</div>
        <div class="stat-value"><?= money($cashTotal) ?> <small>ریال</small></div>
        <div class="stat-hint">مجموع مانده حساب‌های نقدی</div>
    </div>
    <div class="stat-card green">
        <div class="stat-label">مطالبات از اشخاص</div>
        <div class="stat-value"><?= money($receivable) ?> <small>ریال</small></div>
        <div class="stat-hint">طلب شما از مشتریان</div>
    </div>
    <div class="stat-card red">
        <div class="stat-label">بدهی به اشخاص</div>
        <div class="stat-value"><?= money($payable) ?> <small>ریال</small></div>
        <div class="stat-hint">بدهی شما به تأمین‌کنندگان</div>
    </div>
    <div class="stat-card purple">
        <div class="stat-label">فروش این ماه</div>
        <div class="stat-value"><?= money($saleMonth) ?> <small>ریال</small></div>
        <div class="stat-hint">خرید این ماه: <?= money($purchaseMonth) ?> ریال</div>
    </div>
    <div class="stat-card orange">
        <div class="stat-label">سود خالص سال جاری</div>
        <div class="stat-value <?= $netProfit >= 0 ? 'text-success' : 'text-danger' ?>"><?= money($netProfit) ?> <small>ریال</small></div>
        <div class="stat-hint">درآمد: <?= money($incomeTotal) ?> — هزینه: <?= money($expenseTotal) ?></div>
    </div>
</div>

<div class="card">
    <h3 class="card-title">گردش شش ماه اخیر (ریال)</h3>
    <div class="bar-chart">
        <?php foreach ($chart as $c): ?>
        <div class="bar-group">
            <div class="bar-pair">
                <div class="bar sale" title="فروش: <?= money($c['sale']) ?>" style="height: <?= round($c['sale'] / $maxBar * 100) ?>%"></div>
                <div class="bar purchase" title="خرید: <?= money($c['purchase']) ?>" style="height: <?= round($c['purchase'] / $maxBar * 100) ?>%"></div>
            </div>
            <div class="bar-label"><?= e($c['label']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="legend">
        <span><i style="background:#2563eb"></i> فروش</span>
        <span><i style="background:#d97706"></i> خرید</span>
    </div>
</div>

<div class="card">
    <h3 class="card-title">
        آخرین فاکتورها
        <a class="btn btn-sm btn-outline" href="invoices.php">مشاهده همه</a>
    </h3>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>شماره</th><th>نوع</th><th>شخص</th><th>تاریخ</th>
                <th>مبلغ کل</th><th>وضعیت</th><th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$recentInvoices): ?>
                <tr><td colspan="7" class="empty-state">هنوز فاکتوری ثبت نشده است.</td></tr>
            <?php endif; ?>
            <?php foreach ($recentInvoices as $inv): ?>
                <tr>
                    <td><?= e($inv['invoice_no']) ?></td>
                    <td><span class="badge badge-info"><?= e(invoice_type_label($inv['invoice_type'])) ?></span></td>
                    <td><?= e($inv['person_name']) ?></td>
                    <td><?= e(jdate($inv['invoice_date'])) ?></td>
                    <td class="num"><?= money($inv['grand_total']) ?></td>
                    <td><?= status_badge($inv['status']) ?></td>
                    <td><a class="btn btn-sm btn-outline" href="invoice_view.php?id=<?= (int)$inv['id'] ?>">مشاهده</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h3 class="card-title">
        آخرین دریافت و پرداخت‌ها
        <a class="btn btn-sm btn-outline" href="transactions.php">مشاهده همه</a>
    </h3>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>شماره</th><th>نوع</th><th>شخص</th><th>حساب</th><th>تاریخ</th><th>مبلغ</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$recentTxns): ?>
                <tr><td colspan="6" class="empty-state">سندی ثبت نشده است.</td></tr>
            <?php endif; ?>
            <?php foreach ($recentTxns as $t): ?>
                <tr>
                    <td><?= e($t['txn_no']) ?></td>
                    <td>
                        <span class="badge <?= $t['txn_type'] === 'receive' ? 'badge-success' : 'badge-danger' ?>">
                            <?= e(txn_type_label($t['txn_type'])) ?>
                        </span>
                    </td>
                    <td><?= e($t['person_name'] ?? '—') ?></td>
                    <td><?= e($t['account_name']) ?></td>
                    <td><?= e(jdate($t['txn_date'])) ?></td>
                    <td class="num"><?= money($t['amount']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/includes/layout/footer.php'; ?>
