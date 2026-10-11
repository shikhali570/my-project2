<?php
/** دفتر روزنامه */
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
$page_title = 'دفتر روزنامه';

$dateFrom = jdate_to_gregorian(req('date_from')) ?: date('Y-m-01');
$dateTo = jdate_to_gregorian(req('date_to')) ?: today_gregorian();

$entries = DB::all(
    'SELECT je.*, u.full_name AS user_name FROM journal_entries je
     LEFT JOIN users u ON u.id = je.user_id
     WHERE je.entry_date BETWEEN ? AND ?
     ORDER BY je.entry_date, je.id',
    [$dateFrom, $dateTo]
);

$totalDebit = 0;
$totalCredit = 0;
foreach ($entries as &$entry) {
    $entry['lines'] = DB::all(
        'SELECT jl.*, a.code AS account_code, a.name AS account_name FROM journal_lines jl
         JOIN accounts a ON a.id = jl.account_id
         WHERE jl.entry_id = ? ORDER BY jl.id',
        [(int)$entry['id']]
    );
    foreach ($entry['lines'] as $line) {
        $totalDebit += (float)$line['debit'];
        $totalCredit += (float)$line['credit'];
    }
}
unset($entry);

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

    <?php if (!$entries): ?>
        <div class="empty-state">در این بازه زمانی سندی ثبت نشده است.</div>
    <?php else: ?>
        <?php foreach ($entries as $entry): ?>
            <div style="border:1px solid var(--border); border-radius:10px; margin-bottom:14px; overflow:hidden">
                <div style="background:#f8fafc; padding:10px 14px; display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px">
                    <div>
                        <strong>سند <?= e($entry['entry_no']) ?></strong>
                        — <?= e(jdate($entry['entry_date'])) ?>
                        <span class="text-muted"><?= e($entry['description'] ?? '') ?></span>
                    </div>
                    <div class="text-muted">
                        <?php if ($entry['ref_type'] === 'invoice' && $entry['ref_id']): ?>
                            <a href="<?= url('invoice_view.php?id=' . (int)$entry['ref_id']) ?>">مشاهده فاکتور</a> |
                        <?php endif; ?>
                        ثبت‌کننده: <?= e($entry['user_name'] ?? '—') ?>
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                        <tr><th>کد حساب</th><th>نام حساب</th><th>شرح</th><th>بدهکار</th><th>بستانکار</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($entry['lines'] as $line): ?>
                            <tr>
                                <td><?= e($line['account_code']) ?></td>
                                <td><?= e($line['account_name']) ?></td>
                                <td class="text-muted"><?= e($line['description'] ?? '') ?></td>
                                <td class="num"><?= $line['debit'] > 0 ? money($line['debit']) : '—' ?></td>
                                <td class="num"><?= $line['credit'] > 0 ? money($line['credit']) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="total-box" style="max-width:100%">
            <div class="total-row"><span>جمع بدهکار</span><span><?= money($totalDebit) ?> ریال</span></div>
            <div class="total-row"><span>جمع بستانکار</span><span><?= money($totalCredit) ?> ریال</span></div>
            <div class="total-row grand">
                <span>وضعیت</span>
                <span class="<?= abs($totalDebit - $totalCredit) < 0.01 ? 'text-success' : 'text-danger' ?>">
                    <?= abs($totalDebit - $totalCredit) < 0.01 ? 'تراز است ✓' : 'اختلاف: ' . money(abs($totalDebit - $totalCredit)) ?>
                </span>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/layout/footer.php'; ?>
