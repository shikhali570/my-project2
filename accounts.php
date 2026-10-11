<?php
/** کدینگ حساب‌ها (نمودار حساب‌ها) */
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
$page_title = 'کدینگ حساب‌ها';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = req('action');

    if ($action === 'save') {
        require_edit_access();
        $name = req('name');
        $code = req('code');
        $type = req('account_type');
        if ($name === '' || $code === '' || !in_array($type, ['asset', 'liability', 'equity', 'income', 'expense'], true)) {
            flash_set('danger', 'کد، نام و نوع حساب الزامی است.');
        } else {
            $dup = DB::value('SELECT id FROM accounts WHERE code = ? AND id != ?', [$code, (int)req('id')]);
            if ($dup) {
                flash_set('danger', 'کد حساب تکراری است.');
            } else {
                $data = [
                    'code'         => $code,
                    'name'         => $name,
                    'account_type' => $type,
                    'parent_id'    => req('parent_id') ? (int)req('parent_id') : null,
                    'system_key'   => req('system_key') ?: null,
                ];
                $id = (int)req('id');
                if ($id > 0) {
                    $existing = account_by_id($id);
                    if ($existing && $existing['system_key'] && $data['system_key'] !== $existing['system_key']) {
                        $data['system_key'] = $existing['system_key']; // کلید سیستمی حساب‌های ویژه تغییر نمی‌کند
                    }
                    DB::update('accounts', $data, 'id = ?', [$id]);
                    flash_set('success', 'حساب بروزرسانی شد.');
                } else {
                    $data['created_at'] = now_datetime();
                    DB::insert('accounts', $data);
                    flash_set('success', 'حساب جدید ثبت شد.');
                }
            }
        }
        redirect('accounts.php');
    }

    if ($action === 'delete') {
        require_edit_access();
        $id = (int)req('id');
        $acct = account_by_id($id);
        if ($acct && $acct['system_key']) {
            flash_set('danger', 'حساب‌های ویژه سیستم قابل حذف نیستند.');
        } else {
            $hasChild = DB::value('SELECT COUNT(*) FROM accounts WHERE parent_id = ?', [$id], 0);
            $used = DB::value('SELECT COUNT(*) FROM journal_lines WHERE account_id = ?', [$id], 0)
                  + DB::value('SELECT COUNT(*) FROM transactions WHERE account_id = ?', [$id], 0)
                  + DB::value('SELECT COUNT(*) FROM expenses WHERE category_account_id = ? OR account_id = ?', [$id, $id], 0);
            if ($hasChild > 0) {
                flash_set('danger', 'این حساب دارای زیرمجموعه است و قابل حذف نیست.');
            } elseif ($used > 0) {
                flash_set('danger', 'این حساب در اسناد استفاده شده و قابل حذف نیست.');
            } else {
                DB::delete('accounts', 'id = ?', [$id]);
                flash_set('success', 'حساب حذف شد.');
            }
        }
        redirect('accounts.php');
    }
}

$editAccount = null;
if (req('action') === 'edit' && req('id')) {
    $editAccount = DB::one('SELECT * FROM accounts WHERE id = ?', [(int)req('id')]);
}

$accounts = DB::all('SELECT * FROM accounts ORDER BY code');
$byId = [];
foreach ($accounts as $a) {
    $byId[(int)$a['id']] = $a;
}

// محاسبه مانده هر حساب
$balances = [];
foreach ($accounts as $a) {
    $balances[(int)$a['id']] = account_balance((int)$a['id']);
}

/** نمایش بازگشتی درخت حساب‌ها */
function render_accounts_tree($accounts, $byId, $balances, $parentId = null, $depth = 0)
{
    foreach ($accounts as $a) {
        if ((int)($a['parent_id'] ?? 0) !== (int)($parentId ?? 0) && !(empty($a['parent_id']) && empty($parentId))) {
            continue;
        }
        $pad = str_repeat('&nbsp;&nbsp;&nbsp;', $depth);
        $bal = $balances[(int)$a['id']];
        $sysBadge = $a['system_key'] ? ' <span class="badge badge-info">' . e($a['system_key']) . '</span>' : '';
        echo '<tr>';
        echo '<td>' . $pad . '<strong>' . e($a['code']) . '</strong></td>';
        echo '<td>' . $pad . e($a['name']) . $sysBadge . '</td>';
        echo '<td><span class="badge badge-secondary">' . e(account_type_label($a['account_type'])) . '</span></td>';
        echo '<td class="num">' . money($bal) . '</td>';
        echo '<td>';
        if (can_edit()) {
            echo '<a class="btn btn-sm btn-outline" href="accounts.php?action=edit&id=' . (int)$a['id'] . '">ویرایش</a> ';
            if (!$a['system_key']) {
                echo '<form method="post" class="confirm-delete" style="display:inline" data-confirm="آیا از حذف این حساب مطمئن هستید؟">';
                echo csrf_field();
                echo '<input type="hidden" name="action" value="delete">';
                echo '<input type="hidden" name="id" value="' . (int)$a['id'] . '">';
                echo '<button class="btn btn-sm btn-danger" type="submit">حذف</button></form>';
            }
        } else {
            echo '<span class="text-muted">—</span>';
        }
        echo '</td></tr>';
        render_accounts_tree($accounts, $byId, $balances, (int)$a['id'], $depth + 1);
    }
}

include __DIR__ . '/includes/layout/header.php';
?>

<?php if ($editAccount || req('action') === 'new'): ?>
<div class="card">
    <h3 class="card-title"><?= $editAccount ? 'ویرایش حساب ' . e($editAccount['code']) : 'ثبت حساب جدید' ?></h3>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int)($editAccount['id'] ?? 0) ?>">
        <div class="form-grid">
            <div class="form-group">
                <label>کد حساب *</label>
                <input class="form-control" type="text" name="code" value="<?= e($editAccount['code'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>نام حساب *</label>
                <input class="form-control" type="text" name="name" value="<?= e($editAccount['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>نوع حساب *</label>
                <select class="form-control" name="account_type">
                    <?php foreach (['asset' => 'دارایی', 'liability' => 'بدهی', 'equity' => 'سرمایه', 'income' => 'درآمد', 'expense' => 'هزینه'] as $val => $lbl): ?>
                        <option value="<?= $val ?>" <?= (($editAccount['account_type'] ?? '') === $val) ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>حساب بالادستی</label>
                <select class="form-control" name="parent_id">
                    <option value="">— بدون حساب بالادستی —</option>
                    <?php foreach ($accounts as $a): ?>
                        <option value="<?= (int)$a['id'] ?>" <?= ((int)($editAccount['parent_id'] ?? 0) === (int)$a['id']) ? 'selected' : '' ?>>
                            <?= e($a['code'] . ' - ' . $a['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>کلید ویژه سیستم (اختیاری)</label>
                <input class="form-control" type="text" name="system_key" value="<?= e($editAccount['system_key'] ?? '') ?>" placeholder="cash, bank, receivable, ...">
                <div class="hint">فقط برای حساب‌های خاص مانند صندوق، بانک، مطالبات و ... استفاده می‌شود.</div>
            </div>
        </div>
        <button class="btn btn-primary" type="submit">ذخیره</button>
        <a class="btn btn-outline" href="accounts.php">انصراف</a>
    </form>
</div>
<?php else: ?>
<div class="flex-between mb-20">
    <h3 class="mt-0">نمودار حساب‌ها</h3>
    <?php if (can_edit()): ?>
        <a class="btn btn-primary" href="accounts.php?action=new">+ ثبت حساب جدید</a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>کد</th><th>نام حساب</th><th>نوع</th><th>مانده (ریال)</th><th>عملیات</th>
            </tr>
            </thead>
            <tbody>
            <?php render_accounts_tree($accounts, $byId, $balances); ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/layout/footer.php'; ?>
