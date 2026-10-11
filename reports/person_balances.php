<?php
/** مانده اشخاص */
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
$page_title = 'مانده اشخاص';

$typeFilter = req('type');
$search = req('search');

$sql = 'SELECT * FROM persons WHERE 1=1';
$params = [];
if (in_array($typeFilter, ['customer', 'supplier', 'both'], true)) {
    $sql .= ' AND person_type IN (?, ?)';
    $params[] = $typeFilter;
    $params[] = 'both';
}
if ($search !== '') {
    $sql .= ' AND (name LIKE ? OR code LIKE ? OR phone LIKE ?)';
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like]);
}
$sql .= ' ORDER BY name';
$persons = DB::all($sql, $params);

$totalDebtor = 0;   // بدهکار به ما
$totalCreditor = 0; // بستانکار (ما بدهکاریم)
foreach ($persons as &$p) {
    $p['balance'] = person_balance((int)$p['id']);
    if ($p['balance'] > 0) {
        $totalDebtor += $p['balance'];
    } else {
        $totalCreditor += abs($p['balance']);
    }
}
unset($p);

include __DIR__ . '/../includes/layout/header.php';
?>
<div class="stat-grid">
    <div class="stat-card green">
        <div class="stat-label">مجموع مطالبات (بدهکاران به ما)</div>
        <div class="stat-value"><?= money($totalDebtor) ?> <small>ریال</small></div>
    </div>
    <div class="stat-card red">
        <div class="stat-label">مجموع بدهی‌ها (بستانکاران)</div>
        <div class="stat-value"><?= money($totalCreditor) ?> <small>ریال</small></div>
    </div>
    <div class="stat-card blue">
        <div class="stat-label">خالص مانده</div>
        <div class="stat-value"><?= money($totalDebtor - $totalCreditor) ?> <small>ریال</small></div>
    </div>
</div>

<div class="card">
    <form method="get" class="report-filters">
        <div class="form-group">
            <label>نوعیت</label>
            <select class="form-control" name="type">
                <option value="">همه</option>
                <option value="customer" <?= $typeFilter === 'customer' ? 'selected' : '' ?>>مشتری</option>
                <option value="supplier" <?= $typeFilter === 'supplier' ? 'selected' : '' ?>>تأمین‌کننده</option>
            </select>
        </div>
        <div class="form-group">
            <label>جستجو</label>
            <input class="form-control" type="text" name="search" value="<?= e($search) ?>">
        </div>
        <button class="btn btn-primary" type="submit">فیلتر</button>
        <button class="btn btn-outline no-print" type="button" onclick="window.print()">چاپ</button>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>کد</th><th>نام</th><th>نوعیت</th><th>تلفن</th>
                <th>مانده اول دوره</th><th>مانده فعلی</th><th>وضعیت</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$persons): ?>
                <tr><td colspan="7" class="empty-state">شخصی یافت نشد.</td></tr>
            <?php endif; ?>
            <?php foreach ($persons as $p): ?>
                <tr>
                    <td><?= e($p['code']) ?></td>
                    <td><?= e($p['name']) ?></td>
                    <td><span class="badge badge-secondary"><?= e(person_type_label($p['person_type'])) ?></span></td>
                    <td><?= e($p['phone'] ?? '—') ?></td>
                    <td class="num"><?= money($p['opening_balance']) ?></td>
                    <td class="num <?= $p['balance'] > 0 ? 'text-danger' : ($p['balance'] < 0 ? 'text-success' : '') ?>">
                        <?= money(abs($p['balance'])) ?>
                    </td>
                    <td>
                        <?php if ($p['balance'] > 0): ?>
                            <span class="badge badge-danger">بدهکار به ما</span>
                        <?php elseif ($p['balance'] < 0): ?>
                            <span class="badge badge-success">بستانکار از ما</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">تصفیه‌شده</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/layout/footer.php'; ?>
