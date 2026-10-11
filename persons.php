<?php
/** مدیریت اشخاص (مشتریان و تأمین‌کنندگان) */
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
$page_title = 'اشخاص';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = req('action');

    if ($action === 'save') {
        require_edit_access();
        $name = req('name');
        $code = req('code') ?: next_doc_no('P', 'persons', 'code');
        $type = req('person_type', 'customer');
        if (!in_array($type, ['customer', 'supplier', 'both'], true)) {
            $type = 'customer';
        }
        if ($name === '') {
            flash_set('danger', 'نام شخص الزامی است.');
        } else {
            $data = [
                'code'            => $code,
                'name'            => $name,
                'person_type'     => $type,
                'phone'           => req('phone'),
                'address'         => req('address'),
                'notes'           => req('notes'),
                'opening_balance' => req_num('opening_balance'),
            ];
            $id = (int)req('id');
            if ($id > 0) {
                DB::update('persons', $data, 'id = ?', [$id]);
                flash_set('success', 'اطلاعات شخص بروزرسانی شد.');
            } else {
                $data['created_at'] = now_datetime();
                $id = DB::insert('persons', $data);
                flash_set('success', 'شخص جدید ثبت شد.');
            }
            // ثبت/بروزرسانی سند افتتاحیه مانده اول دوره
            post_person_opening(DB::one('SELECT * FROM persons WHERE id = ?', [$id]));
        }
        redirect('persons.php');
    }

    if ($action === 'delete') {
        require_edit_access();
        $id = (int)req('id');
        $used = DB::value('SELECT COUNT(*) FROM invoices WHERE person_id = ?', [$id], 0)
              + DB::value('SELECT COUNT(*) FROM transactions WHERE person_id = ?', [$id], 0);
        if ($used > 0) {
            flash_set('danger', 'این شخص دارای اسناد است و قابل حذف نیست.');
        } else {
            DB::delete('persons', 'id = ?', [$id]);
            flash_set('success', 'شخص حذف شد.');
        }
        redirect('persons.php');
    }
}

// فرم ویرایش
$editPerson = null;
if (req('action') === 'edit' && req('id')) {
    $editPerson = DB::one('SELECT * FROM persons WHERE id = ?', [(int)req('id')]);
}

$search = req('search');
$sql = 'SELECT p.*, 0 AS balance FROM persons p';
$params = [];
if ($search !== '') {
    $sql .= ' WHERE p.name LIKE ? OR p.code LIKE ? OR p.phone LIKE ?';
    $like = '%' . $search . '%';
    $params = [$like, $like, $like];
}
$sql .= ' ORDER BY p.id DESC';
$persons = DB::all($sql, $params);
foreach ($persons as &$p) {
    $p['balance'] = person_balance((int)$p['id']);
}
unset($p);

include __DIR__ . '/includes/layout/header.php';
?>

<?php if ($editPerson || req('action') === 'new'): ?>
<div class="card">
    <h3 class="card-title"><?= $editPerson ? 'ویرایش شخص: ' . e($editPerson['name']) : 'ثبت شخص جدید' ?></h3>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int)($editPerson['id'] ?? 0) ?>">
        <div class="form-grid">
            <div class="form-group">
                <label>نام شخص / شرکت *</label>
                <input class="form-control" type="text" name="name" value="<?= e($editPerson['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>کد شخص</label>
                <input class="form-control" type="text" name="code" value="<?= e($editPerson['code'] ?? '') ?>" placeholder="خودکار">
            </div>
            <div class="form-group">
                <label>نوعیت</label>
                <select class="form-control" name="person_type">
                    <?php foreach (['customer' => 'مشتری', 'supplier' => 'تأمین‌کننده', 'both' => 'هر دو'] as $val => $lbl): ?>
                        <option value="<?= $val ?>" <?= (($editPerson['person_type'] ?? '') === $val) ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>تلفن</label>
                <input class="form-control" type="text" name="phone" value="<?= e($editPerson['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>مانده اول دوره (ریال)</label>
                <input class="form-control" type="text" name="opening_balance" value="<?= e($editPerson['opening_balance'] ?? '0') ?>">
            </div>
            <div class="form-group">
                <label>آدرس</label>
                <input class="form-control" type="text" name="address" value="<?= e($editPerson['address'] ?? '') ?>">
            </div>
            <div class="form-group" style="grid-column:1/-1">
                <label>توضیحات</label>
                <textarea class="form-control" name="notes"><?= e($editPerson['notes'] ?? '') ?></textarea>
            </div>
        </div>
        <button class="btn btn-primary" type="submit">ذخیره</button>
        <a class="btn btn-outline" href="persons.php">انصراف</a>
    </form>
</div>
<?php else: ?>
<div class="flex-between mb-20">
    <form method="get" class="form-inline">
        <input class="form-control" type="text" name="search" value="<?= e($search) ?>" placeholder="جستجو بر اساس نام، کد یا تلفن...">
        <button class="btn btn-outline" type="submit">جستجو</button>
    </form>
    <?php if (can_edit()): ?>
        <a class="btn btn-primary" href="persons.php?action=new">+ ثبت شخص جدید</a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>کد</th><th>نام</th><th>نوعیت</th><th>تلفن</th>
                <th>مانده (ریال)</th><th>عملیات</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$persons): ?>
                <tr><td colspan="6" class="empty-state">شخصی یافت نشد.</td></tr>
            <?php endif; ?>
            <?php foreach ($persons as $p): ?>
                <tr>
                    <td><?= e($p['code']) ?></td>
                    <td><?= e($p['name']) ?></td>
                    <td><span class="badge badge-secondary"><?= e(person_type_label($p['person_type'])) ?></span></td>
                    <td><?= e($p['phone'] ?? '—') ?></td>
                    <td class="num <?= $p['balance'] > 0 ? 'text-danger' : ($p['balance'] < 0 ? 'text-success' : '') ?>">
                        <?= money($p['balance']) ?>
                    </td>
                    <td>
                        <?php if (can_edit()): ?>
                            <a class="btn btn-sm btn-outline" href="persons.php?action=edit&id=<?= (int)$p['id'] ?>">ویرایش</a>
                            <form method="post" class="confirm-delete" style="display:inline"
                                  data-confirm="آیا از حذف «<?= e($p['name']) ?>» مطمئن هستید؟">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <button class="btn btn-sm btn-danger" type="submit">حذف</button>
                            </form>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/layout/footer.php'; ?>
