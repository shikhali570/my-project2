<?php
/** ایجاد / ویرایش فاکتور خرید و فروش */
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
require_edit_access();

$id = (int)req('id');
$invoice = $id ? DB::one('SELECT * FROM invoices WHERE id = ?', [$id]) : null;

$type = $invoice ? $invoice['invoice_type'] : (req('type', 'sale') === 'purchase' ? 'purchase' : 'sale');
$page_title = ($invoice ? 'ویرایش ' : 'ثبت ') . 'فاکتور ' . invoice_type_label($type);

/** اعمال تغییرات موجودی کالا */
function apply_stock(array $items, $type, $sign = 1)
{
    foreach ($items as $item) {
        if (empty($item['product_id'])) {
            continue;
        }
        $delta = (float)$item['qty'] * ($type === 'sale' ? -1 : 1) * $sign;
        DB::run('UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?', [$delta, (int)$item['product_id']]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // جمع‌آوری اقلام
    $items = [];
    $rawItems = $_POST['items'] ?? [];
    if (is_array($rawItems)) {
        foreach ($rawItems as $row) {
            $qty = (float)($row['qty'] ?? 0);
            $price = (float)($row['unit_price'] ?? 0);
            $discount = (float)($row['discount'] ?? 0);
            $desc = trim((string)($row['description'] ?? ''));
            $productId = (int)($row['product_id'] ?? 0) ?: null;
            if ($qty <= 0 && $price == 0 && $desc === '') {
                continue;
            }
            $items[] = [
                'product_id'  => $productId,
                'description' => $desc,
                'qty'         => $qty,
                'unit_price'  => $price,
                'discount'    => $discount,
                'total'       => $qty * $price - $discount,
            ];
        }
    }

    $personId = (int)req('person_id');
    $invoiceDate = jdate_to_gregorian(req('invoice_date'));
    $dueDate = jdate_to_gregorian(req('due_date'));
    $taxPercent = max(0, req_num('tax_percent'));
    $invoiceDiscount = max(0, req_num('invoice_discount'));

    if (!$personId || !$invoiceDate || !$items) {
        flash_set('danger', 'شخص، تاریخ و حداقل یک قلم فاکتور الزامی است.');
        redirect($invoice ? 'invoice_edit.php?id=' . $id : 'invoice_edit.php?type=' . $type);
    }

    $itemsTotal = 0;
    $itemDiscounts = 0;
    foreach ($items as $item) {
        $itemsTotal += $item['qty'] * $item['unit_price'];
        $itemDiscounts += $item['discount'];
    }
    $discountTotal = $itemDiscounts + $invoiceDiscount;
    $net = max(0, $itemsTotal - $discountTotal);
    $taxTotal = round($net * $taxPercent / 100);
    $grandTotal = $net + $taxTotal;

    try {
        DB::begin();

        if ($invoice) {
            // بازگردانی موجودی قبلی و سند قبلی
            $oldItems = DB::all('SELECT * FROM invoice_items WHERE invoice_id = ?', [$id]);
            apply_stock($oldItems, $invoice['invoice_type'], -1);
            delete_journal_for('invoice', $id);
            DB::delete('invoice_items', 'invoice_id = ?', [$id]);

            $invoiceNo = $invoice['invoice_no'];
            DB::update('invoices', [
                'invoice_type'   => $type,
                'person_id'      => $personId,
                'invoice_date'   => $invoiceDate,
                'due_date'       => $dueDate ?: null,
                'items_total'    => $itemsTotal,
                'discount_total' => $discountTotal,
                'tax_percent'    => $taxPercent,
                'tax_total'      => $taxTotal,
                'grand_total'    => $grandTotal,
                'description'    => req('description'),
            ], 'id = ?', [$id]);
        } else {
            $invoiceNo = next_doc_no($type === 'sale' ? 'S' : 'B', 'invoices', 'invoice_no');
            $id = DB::insert('invoices', [
                'invoice_no'    => $invoiceNo,
                'invoice_type'  => $type,
                'person_id'     => $personId,
                'invoice_date'  => $invoiceDate,
                'due_date'      => $dueDate ?: null,
                'items_total'   => $itemsTotal,
                'discount_total'=> $discountTotal,
                'tax_percent'   => $taxPercent,
                'tax_total'     => $taxTotal,
                'grand_total'   => $grandTotal,
                'paid_amount'   => 0,
                'status'        => 'unpaid',
                'description'   => req('description'),
                'user_id'       => current_user_id(),
                'created_at'    => now_datetime(),
            ]);
        }

        foreach ($items as $item) {
            $item['invoice_id'] = $id;
            DB::insert('invoice_items', $item);
        }
        apply_stock($items, $type, 1);

        $invoiceRow = DB::one('SELECT * FROM invoices WHERE id = ?', [$id]);
        $personRow = DB::one('SELECT * FROM persons WHERE id = ?', [$personId]);
        post_invoice_journal($invoiceRow, $personRow, current_user_id());
        refresh_invoice_status($id);

        DB::commit();
        flash_set('success', 'فاکتور ' . $invoiceNo . ' با موفقیت ذخیره شد.');
        redirect('invoice_view.php?id=' . $id);
    } catch (Throwable $e) {
        DB::rollBack();
        flash_set('danger', 'خطا در ذخیره فاکتور: ' . $e->getMessage());
        redirect('invoice_edit.php?type=' . $type);
    }
}

// آماده‌سازی داده فرم
$personTypeFilter = $type === 'sale' ? ['customer', 'both'] : ['supplier', 'both'];
$placeholders = implode(',', array_fill(0, count($personTypeFilter), '?'));
$persons = DB::all(
    "SELECT * FROM persons WHERE person_type IN ($placeholders) ORDER BY name",
    $personTypeFilter
);
$products = DB::all('SELECT * FROM products ORDER BY name');
$productsJson = [];
foreach ($products as $p) {
    $productsJson[(string)$p['id']] = [
        'name'       => $p['name'],
        'buy_price'  => (float)$p['buy_price'],
        'sell_price' => (float)$p['sell_price'],
    ];
}

$formItems = [['product_id' => '', 'description' => '', 'qty' => 1, 'unit_price' => 0, 'discount' => 0]];
$invoiceDiscountVal = 0;
if ($invoice) {
    $formItems = DB::all('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id', [$id]);
    $itemDiscountsSum = 0;
    foreach ($formItems as $fi) {
        $itemDiscountsSum += (float)$fi['discount'];
    }
    $invoiceDiscountVal = max(0, (float)$invoice['discount_total'] - $itemDiscountsSum);
}

$defaultTax = $invoice ? (float)$invoice['tax_percent'] : (float)setting('default_tax_percent', 0);

include __DIR__ . '/includes/layout/header.php';
?>
<div class="card">
    <h3 class="card-title">
        <?= e($page_title) ?>
        <span>
            <a class="btn btn-outline btn-sm" href="invoices.php?type=<?= e($type) ?>">بازگشت به لیست</a>
        </span>
    </h3>

    <form method="post" id="invoice-form">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <input type="hidden" name="invoice-type-hidden" id="invoice-type" value="<?= e($type) ?>">

        <div class="form-grid">
            <div class="form-group">
                <label><?= $type === 'sale' ? 'مشتری' : 'تأمین‌کننده' ?> *</label>
                <select class="form-control" name="person_id" required>
                    <option value="">— انتخاب کنید —</option>
                    <?php foreach ($persons as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= ((int)($invoice['person_id'] ?? 0) === (int)$p['id']) ? 'selected' : '' ?>>
                            <?= e($p['code'] . ' — ' . $p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>تاریخ فاکتور (شمسی) *</label>
                <input class="form-control" type="text" name="invoice_date"
                       value="<?= e($invoice ? jdate($invoice['invoice_date']) : jdate(today_gregorian())) ?>"
                       placeholder="1404/07/20" required>
            </div>
            <div class="form-group">
                <label>تاریخ سررسید (شمسی)</label>
                <input class="form-control" type="text" name="due_date"
                       value="<?= $invoice && $invoice['due_date'] ? e(jdate($invoice['due_date'])) : '' ?>"
                       placeholder="اختیاری">
            </div>
            <div class="form-group">
                <label>درصد مالیات بر ارزش افزوده</label>
                <input class="form-control" type="text" name="tax_percent" id="invoice-tax" value="<?= e($defaultTax) ?>">
            </div>
            <div class="form-group">
                <label>تخفیف کل فاکتور (ریال)</label>
                <input class="form-control" type="text" name="invoice_discount" id="invoice-discount" value="<?= e($invoiceDiscountVal) ?>">
            </div>
            <div class="form-group">
                <label>توضیحات</label>
                <input class="form-control" type="text" name="description" value="<?= e($invoice['description'] ?? '') ?>">
            </div>
        </div>

        <h3 class="card-title" style="margin-top:22px">
            اقلام فاکتور
            <button class="btn btn-sm btn-outline" type="button" id="add-item-row">+ افزودن قلم</button>
        </h3>

        <div class="table-wrap">
            <table class="table items-table">
                <thead>
                <tr>
                    <th style="width:180px">کالا</th>
                    <th>شرح</th>
                    <th style="width:90px">تعداد</th>
                    <th style="width:130px">قیمت واحد</th>
                    <th style="width:110px">تخفیف</th>
                    <th style="width:120px">جمع</th>
                    <th style="width:50px"></th>
                </tr>
                </thead>
                <tbody id="items-body">
                <?php foreach ($formItems as $idx => $item): ?>
                    <tr class="item-row">
                        <td>
                            <select class="form-control i-product" name="items[<?= (int)$idx ?>][product_id]">
                                <option value="">— آزاد —</option>
                                <?php foreach ($products as $p): ?>
                                    <option value="<?= (int)$p['id'] ?>" <?= ((int)($item['product_id'] ?? 0) === (int)$p['id']) ? 'selected' : '' ?>>
                                        <?= e($p['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input class="form-control i-desc" type="text" name="items[<?= (int)$idx ?>][description]" value="<?= e($item['description'] ?? '') ?>"></td>
                        <td><input class="form-control i-qty" type="text" name="items[<?= (int)$idx ?>][qty]" value="<?= e($item['qty'] ?? 1) ?>"></td>
                        <td><input class="form-control i-price" type="text" name="items[<?= (int)$idx ?>][unit_price]" value="<?= e($item['unit_price'] ?? 0) ?>"></td>
                        <td><input class="form-control i-discount" type="text" name="items[<?= (int)$idx ?>][discount]" value="<?= e($item['discount'] ?? 0) ?>"></td>
                        <td class="num i-total">0</td>
                        <td><button class="btn btn-sm btn-danger remove-row" type="button">✕</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="total-box">
            <div class="total-row"><span>جمع اقلام</span><span id="sum-items">0</span></div>
            <div class="total-row"><span>جمع تخفیف‌ها</span><span id="sum-discount">0</span></div>
            <div class="total-row"><span>مالیات</span><span id="sum-tax">0</span></div>
            <div class="total-row grand"><span>مبلغ قابل پرداخت</span><span><span id="sum-grand">0</span> ریال</span></div>
        </div>

        <div style="margin-top:20px">
            <button class="btn btn-primary" type="submit">ذخیره فاکتور</button>
            <a class="btn btn-outline" href="invoices.php?type=<?= e($type) ?>">انصراف</a>
        </div>
    </form>
</div>

<script type="application/json" id="products-data"><?= json_encode($productsJson, JSON_UNESCAPED_UNICODE) ?></script>
<?php include __DIR__ . '/includes/layout/footer.php'; ?>
