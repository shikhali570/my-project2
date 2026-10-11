<?php
/** مدیریت کاربران (فقط مدیر سیستم) */
require_once __DIR__ . '/includes/bootstrap.php';
require_role(['admin']);
$page_title = 'کاربران';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = req('action');

    if ($action === 'save') {
        $id = (int)req('id');
        $username = req('username');
        $fullName = req('full_name');
        $role = req('role');
        $password = req('password');
        $isActive = req('is_active') === '1' ? 1 : 0;

        if (!in_array($role, ['admin', 'accountant', 'user'], true)) {
            $role = 'user';
        }
        if ($username === '' || $fullName === '') {
            flash_set('danger', 'نام کاربری و نام کامل الزامی است.');
        } elseif ($id === 0 && strlen($password) < 6) {
            flash_set('danger', 'رمز عبور باید حداقل ۶ کاراکتر باشد.');
        } else {
            $dup = DB::value('SELECT id FROM users WHERE username = ? AND id != ?', [$username, $id]);
            if ($dup) {
                flash_set('danger', 'این نام کاربری قبلاً ثبت شده است.');
            } else {
                $data = [
                    'username'  => $username,
                    'full_name' => $fullName,
                    'role'      => $role,
                    'is_active' => $isActive,
                ];
                if ($password !== '') {
                    $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                }
                if ($id > 0) {
                    if ($id === current_user_id() && ($role !== 'admin' || !$isActive)) {
                        flash_set('danger', 'امکان تغییر نقش یا غیرفعال‌سازی حساب خودتان وجود ندارد.');
                        redirect('users.php');
                    }
                    DB::update('users', $data, 'id = ?', [$id]);
                    flash_set('success', 'کاربر بروزرسانی شد.');
                    if ($id === current_user_id()) {
                        $_SESSION['user']['full_name'] = $fullName;
                    }
                } else {
                    $data['password_hash'] = $data['password_hash'] ?? password_hash($password, PASSWORD_DEFAULT);
                    $data['created_at'] = now_datetime();
                    DB::insert('users', $data);
                    flash_set('success', 'کاربر جدید ثبت شد.');
                }
            }
        }
        redirect('users.php');
    }

    if ($action === 'delete') {
        $id = (int)req('id');
        if ($id === current_user_id()) {
            flash_set('danger', 'امکان حذف حساب خودتان وجود ندارد.');
        } else {
            DB::delete('users', 'id = ?', [$id]);
            flash_set('success', 'کاربر حذف شد.');
        }
        redirect('users.php');
    }
}

$editUser = null;
if (req('action') === 'edit' && req('id')) {
    $editUser = DB::one('SELECT * FROM users WHERE id = ?', [(int)req('id')]);
}
$users = DB::all('SELECT * FROM users ORDER BY id');

include __DIR__ . '/includes/layout/header.php';
?>

<?php if ($editUser || req('action') === 'new'): ?>
<div class="card">
    <h3 class="card-title"><?= $editUser ? 'ویرایش کاربر: ' . e($editUser['username']) : 'ثبت کاربر جدید' ?></h3>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int)($editUser['id'] ?? 0) ?>">
        <div class="form-grid">
            <div class="form-group">
                <label>نام کاربری *</label>
                <input class="form-control" type="text" name="username" value="<?= e($editUser['username'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>نام کامل *</label>
                <input class="form-control" type="text" name="full_name" value="<?= e($editUser['full_name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>نقش</label>
                <select class="form-control" name="role">
                    <?php foreach (['admin' => 'مدیر سیستم', 'accountant' => 'حسابدار', 'user' => 'کاربر ساده'] as $val => $lbl): ?>
                        <option value="<?= $val ?>" <?= (($editUser['role'] ?? '') === $val) ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>رمز عبور <?= $editUser ? '(برای عدم تغییر، خالی بگذارید)' : '*' ?></label>
                <input class="form-control" type="password" name="password" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label>وضعیت</label>
                <select class="form-control" name="is_active">
                    <option value="1" <?= (int)($editUser['is_active'] ?? 1) === 1 ? 'selected' : '' ?>>فعال</option>
                    <option value="0" <?= (int)($editUser['is_active'] ?? 1) === 0 ? 'selected' : '' ?>>غیرفعال</option>
                </select>
            </div>
        </div>
        <button class="btn btn-primary" type="submit">ذخیره</button>
        <a class="btn btn-outline" href="users.php">انصراف</a>
    </form>
</div>
<?php else: ?>
<div class="flex-between mb-20">
    <h3 class="mt-0">فهرست کاربران</h3>
    <a class="btn btn-primary" href="users.php?action=new">+ ثبت کاربر جدید</a>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>نام کاربری</th><th>نام کامل</th><th>نقش</th><th>وضعیت</th><th>تاریخ ثبت‌نام</th><th>عملیات</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><strong><?= e($u['username']) ?></strong></td>
                    <td><?= e($u['full_name']) ?></td>
                    <td><span class="badge badge-info"><?= e(role_label($u['role'])) ?></span></td>
                    <td>
                        <?= (int)$u['is_active']
                            ? '<span class="badge badge-success">فعال</span>'
                            : '<span class="badge badge-danger">غیرفعال</span>' ?>
                    </td>
                    <td><?= e(jdate(substr($u['created_at'], 0, 10))) ?></td>
                    <td>
                        <a class="btn btn-sm btn-outline" href="users.php?action=edit&id=<?= (int)$u['id'] ?>">ویرایش</a>
                        <?php if ((int)$u['id'] !== current_user_id()): ?>
                            <form method="post" class="confirm-delete" style="display:inline"
                                  data-confirm="کاربر «<?= e($u['username']) ?>» حذف شود؟">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                <button class="btn btn-sm btn-danger" type="submit">حذف</button>
                            </form>
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
