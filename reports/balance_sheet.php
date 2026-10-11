<?php
/** ترازنامه */
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
$page_title = 'ترازنامه';

$asOf = jdate_to_gregorian(req('as_of')) ?: today_gregorian();

function balances_by_type($type, $asOf)
{
    return DB::all(
        'SELECT a.id, a.code, a.name,
                COALESCE(SUM(jl.debit),0) - COALESCE(SUM(jl.credit),0) AS balance
         FROM accounts a
         LEFT JOIN journal_lines jl ON jl.account_id = a.id
         LEFT JOIN journal_entries je ON je.id = jl.entry_id AND je.entry_date <= ?
         WHERE a.account_type = ?
         GROUP BY a.id, a.code, a.name
         ORDER BY a.code',
        [$asOf, $type]
    );
}

$assets = balances_by_type('asset', $asOf);
$liabilities = balances_by_type('liability', $asOf);
$equities = balances_by_type('equity', $asOf);

// سود/زیان انباشته تا تاریخ
$income = (float)DB::value(
    "SELECT COALESCE(SUM(jl.credit - jl.debit),0) FROM journal_lines jl
     JOIN journal_entries je ON je.id = jl.entry_id
     JOIN accounts a ON a.id = jl.account_id
     WHERE a.account_type = 'income' AND je.entry_date <= ?",
    [$asOf], 0
);
$expense = (float)DB::value(
    "SELECT COALESCE(SUM(jl.debit - jl.credit),0) FROM journal_lines jl
     JOIN journal_entries je ON je.id = jl.entry_id
     JOIN accounts a ON a.id = jl.account_id
     WHERE a.account_type = 'expense' AND je.entry_date <= ?",
    [$asOf], 0
);
$netIncome = $income - $expense;

$assetTotal = array_sum(array_map(fn($r) => (float)$r['balance'], $assets));
// بدهی و سرمایه: مانده طبیعی بستانکار است
$liabilityTotal = -array_sum(array_map(fn($r) => (float)$r['balance'], $liabilities));
$equityTotal = -array_sum(array_map(fn($r) => (float)$r['balance'], $equities));
$rightTotal = $liabilityTotal + $equityTotal + $netIncome;
$diff = $assetTotal - $rightTotal;

include __DIR__ . '/../includes/layout/header.php';
?>
<div class="card">
    <form method="get" class="report-filters">
        <div class="form-group">
            <label>تا تاریخ (شمسی)</label>
            <input class="form-control" type="text" name="as_of" value="<?= e(req('as_of', jdate($asOf))) ?>">
        </div>
        <button class="btn btn-primary" type="submit">نمایش</button>
        <button class="btn btn-outline no-print" type="button" onclick="window.print()">چاپ</button>
    </form>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px">
        <div>
            <h3 class="card-title">دارایی‌ها</h3>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>کد</th><th>نام حساب</th><th>مانده (ریال)</th></tr></thead>
                    <tbody>
                    <?php foreach ($assets as $r): ?>
                        <?php if (abs((float)$r['balance']) < 0.01) continue; ?>
                        <tr>
                            <td><?= e($r['code']) ?></td>
                            <td><?= e($r['name']) ?></td>
                            <td class="num"><?= money($r['balance']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                    <tr><td colspan="2">جمع دارایی‌ها</td><td class="num"><?= money($assetTotal) ?></td></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div>
            <h3 class="card-title">بدهی‌ها و سرمایه</h3>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>کد</th><th>نام حساب</th><th>مانده (ریال)</th></tr></thead>
                    <tbody>
                    <?php foreach ($liabilities as $r): ?>
                        <?php if (abs((float)$r['balance']) < 0.01) continue; ?>
                        <tr>
                            <td><?= e($r['code']) ?></td>
                            <td><?= e($r['name']) ?></td>
                            <td class="num"><?= money(abs((float)$r['balance'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php foreach ($equities as $r): ?>
                        <?php if (abs((float)$r['balance']) < 0.01) continue; ?>
                        <tr>
                            <td><?= e($r['code']) ?></td>
                            <td><?= e($r['name']) ?></td>
                            <td class="num"><?= money(abs((float)$r['balance'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td>—</td>
                        <td><?= $netIncome >= 0 ? 'سود خالص دوره' : 'زیان خالص دوره' ?></td>
                        <td class="num"><?= money(abs($netIncome)) ?></td>
                    </tr>
                    </tbody>
                    <tfoot>
                    <tr><td colspan="2">جمع بدهی‌ها و سرمایه</td><td class="num"><?= money($rightTotal) ?></td></tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="total-box" style="max-width:100%; margin-top:20px">
        <div class="total-row"><span>جمع دارایی‌ها</span><span><?= money($assetTotal) ?> ریال</span></div>
        <div class="total-row"><span>جمع بدهی‌ها + سرمایه + سود دوره</span><span><?= money($rightTotal) ?> ریال</span></div>
        <div class="total-row grand">
            <span>وضعیت معادله حسابداری</span>
            <span class="<?= abs($diff) < 0.01 ? 'text-success' : 'text-danger' ?>">
                <?= abs($diff) < 0.01 ? 'برقرار است ✓' : 'اختلاف: ' . money(abs($diff)) . ' ریال' ?>
            </span>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/layout/footer.php'; ?>
