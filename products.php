<?php
/** مدیریت اقلام و کالاها */
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
$page_title = 'اقلام و کالاها';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = req('action');

    if ($action === 'save') {
        require_edit_access();
        $name = req('name');
        if ($name === '') {
            flash_set('danger', 'نام کالا الزامی است.');
        } else {
            $data = [
                'code'       => req('code') ?: next_doc_no('K', 'products', 'code'),
                'name'       => $name,
                'unit'       => req('unit'),
                'buy_price'  => req_num('buy_price'),
                'sell_price' => req_num('sell_price'),
                'stock_qty'  => req_num('stock_qty'),
                'notes'      => req('notes'),
            ];
            $id = (int)req('id');
            if ($id > 0) {
                DB::update('products', $data, 'id = ?', [$id]);
                flash_set('success', 'کالا بروزرسانی شد.');
            } else {
                $data['created_at'] = now_datetime();
                DB::insert('products', $data);
                flash_set('success', 'کالای جدید ثبت شد.');
            }
        }
        redirect('products.php');
    }

    if ($action === 'delete') {
        require_edit_access();
        $id = (int)req('id');
        $used = DB::value('SELECT COUNT(*) FROM invoice_items WHERE product_id = ?', [$id], 0);
        if ($used > 0) {
            flash_set('danger', 'این کالا در فاکتورها استفاده شده و قابل حذف نیست.');
        } else {
            DB::delete('products', 'id = ?', [$id]);
            flash_set('success', 'کالا حذف شد.');
        }
        redirect('products.php');
    }
}

$editProduct = null;
if (req('action') === 'edit' && req('id')) {
    $editProduct = DB::one('SELECT * FROM products WHERE id = ?', [(int)req('id')]);
}

$search = req('search');
$sql = 'SELECT * FROM products';
$params = [];
if ($search !== '') {
    $sql .= ' WHERE name LIKE ? OR code LIKE ?';
    $like = '%' . $search . '%';
    $params = [$like, $like];
}
$sql .= ' ORDER BY id DESC';
$products = DB::all($sql, $params);

include __DIR__ . '/includes/layout/header.php';
?>

<?php if ($editProduct || req('action') === 'new'): ?>
<div class="card">
    <h3 class="card-title"><?= $editProduct ? 'ویرایش کالا: ' . e($editProduct['name']) : 'ثبت کالای جدید' ?></h3>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int)($editProduct['id'] ?? 0) ?>">
        <div class="form-grid">
            <div class="form-group">
                <label>نام کالا *</label>
                <input class="form-control" type="text" name="name" value="<?= e($editProduct['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>کد کالا</label>
                <input class="form-control" type="text" name="code" value="<?= e($editProduct['code'] ?? '') ?>" placeholder="خودکار">
            </div>
            <div class="form-group">
                <label>واحد شمارش</label>
                <input class="form-control" type="text" name="unit" value="<?= e($editProduct['unit'] ?? '') ?>" placeholder="عدد، کیلوگرم، ...">
            </div>
            <div class="form-group">
                <label>قیمت خرید (ریال)</label>
                <input class="form-control" type="text" name="buy_price" value="<?= e($editProduct['buy_price'] ?? '0') ?>">
            </div>
            <div class="form-group">
                <label>قیمت فروش (ریال)</label>
                <input class="form-control" type="text" name="sell_price" value="<?= e($editProduct['sell_price'] ?? '0') ?>">
            </div>
            <div class="form-group">
                <label>موجودی</label>
                <input class="form-control" type="text" name="stock_qty" value="<?= e($editProduct['stock_qty'] ?? '0') ?>">
            </div>
            <div class="form-group" style="grid-column:1/-1">
                <label>توضیحات</label>
                <textarea class="form-control" name="notes"><?= e($editProduct['notes'] ?? '') ?></textarea>
            </div>
        </div>
        <button class="btn btn-primary" type="submit">ذخیره</button>
        <a class="btn btn-outline" href="products.php">انصراف</a>
    </form>
</div>
<?php else: ?>
<div class="flex-between mb-20">
    <form method="get" class="form-inline">
        <input class="form-control" type="text" name="search" value="<?= e($search) ?>" placeholder="جستجوی کالا...">
        <button class="btn btn-outline" type="submit">جستجو</button>
    </form>
    <?php if (can_edit()): ?>
        <a class="btn btn-primary" href="products.php?action=new">+ ثبت کالای جدید</a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>کد</th><th>نام کالا</th><th>واحد</th>
                <th>قیمت خرید</th><th>قیمت فروش</th><th>موجودی</th><th>عملیات</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$products): ?>
                <tr><td colspan="7" class="empty-state">کالایی یافت نشد.</td></tr>
            <?php endif; ?>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td><?= e($p['code']) ?></td>
                    <td><?= e($p['name']) ?></td>
                    <td><?= e($p['unit'] ?? '—') ?></td>
                    <td class="num"><?= money($p['buy_price']) ?></td>
                    <td class="num"><?= money($p['sell_price']) ?></td>
                    <td class="num"><?= qty_format($p['stock_qty']) ?></td>
                    <td>
                        <?php if (can_edit()): ?>
                            <a class="btn btn-sm btn-outline" href="products.php?action=edit&id=<?= (int)$p['id'] ?>">ویرایش</a>
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
