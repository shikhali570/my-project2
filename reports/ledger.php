<?php
/** دفتر کل — گردش یک حساب */
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
$page_title = 'دفتر کل';

$accountId = (int)req('account_id');
$dateFrom = jdate_to_gregorian(req('date_from')) ?: date('Y-m-01');
$dateTo = jdate_to_gregorian(req('date_to')) ?: today_gregorian();

$accounts = DB::all('SELECT * FROM accounts ORDER BY code');
$selected = $accountId ? account_by_id($accountId) : null;

$opening = 0;
$lines = [];
$totalDebit = 0;
$totalCredit = 0;
if ($selected) {
    $opening = (float)DB::value(
        'SELECT COALESCE(SUM(jl.debit),0) - COALESCE(SUM(jl.credit),0)
         FROM journal_lines jl JOIN journal_entries je ON je.id = jl.entry_id
         WHERE jl.account_id = ? AND je.entry_date < ?',
        [$accountId, $dateFrom], 0
    );
    $lines = DB::all(
        'SELECT jl.*, je.entry_no, je.entry_date, je.description AS entry_desc
         FROM journal_lines jl JOIN journal_entries je ON je.id = jl.entry_id
         WHERE jl.account_id = ? AND je.entry_date BETWEEN ? AND ?
         ORDER BY je.entry_date, je.id',
        [$accountId, $dateFrom, $dateTo]
    );
    foreach ($lines as $l) {
        $totalDebit += (float)$l['debit'];
        $totalCredit += (float)$l['credit'];
    }
}
$closing = $opening + $totalDebit - $totalCredit;

include __DIR__ . '/../includes/layout/header.php';
?>
<div class="card">
    <form method="get" class="report-filters">
        <div class="form-group" style="min-width:260px">
            <label>حساب</label>
            <select class="form-control" name="account_id">
                <option value="">— انتخاب کنید —</option>
                <?php foreach ($accounts as $a): ?>
                    <option value="<?= (int)$a['id'] ?>" <?= $accountId === (int)$a['id'] ? 'selected' : '' ?>>
                        <?= e($a['code'] . ' — ' . $a['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
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

    <?php if (!$selected): ?>
        <div class="empty-state">یک حساب را انتخاب کنید.</div>
    <?php else: ?>
        <h3 class="card-title">
            گردش حساب <?= e($selected['code'] . ' — ' . $selected['name']) ?>
            <span class="badge badge-secondary"><?= e(account_type_label($selected['account_type'])) ?></span>
        </h3>

        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr><th>تاریخ</th><th>شماره سند</th><th>شرح</th><th>بدهکار</th><th>بستانکار</th><th>مانده</th></tr>
                </thead>
                <tbody>
                <tr style="background:#f8fafc">
                    <td colspan="5"><strong>مانده اول دوره</strong></td>
                    <td class="num"><strong><?= money($opening) ?></strong></td>
                </tr>
                <?php $running = $opening; ?>
                <?php if (!$lines): ?>
                    <tr><td colspan="6" class="empty-state">گردشی در این بازه ثبت نشده است.</td></tr>
                <?php endif; ?>
                <?php foreach ($lines as $l): ?>
                    <?php $running += (float)$l['debit'] - (float)$l['credit']; ?>
                    <tr>
                        <td><?= e(jdate($l['entry_date'])) ?></td>
                        <td><?= e($l['entry_no']) ?></td>
                        <td class="text-muted"><?= e($l['description'] ?: $l['entry_desc']) ?></td>
                        <td class="num"><?= $l['debit'] > 0 ? money($l['debit']) : '—' ?></td>
                        <td class="num"><?= $l['credit'] > 0 ? money($l['credit']) : '—' ?></td>
                        <td class="num"><?= money($running) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                <tr>
                    <td colspan="3">جمع گردش دوره</td>
                    <td class="num"><?= money($totalDebit) ?></td>
                    <td class="num"><?= money($totalCredit) ?></td>
                    <td class="num"><?= money($closing) ?></td>
                </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/layout/footer.php'; ?>
