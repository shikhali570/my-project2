<?php
/**
 * نصب‌کننده سامانه حسابداری
 * ساخت جداول، داده‌های اولیه و کاربر مدیر
 */

require_once __DIR__ . '/includes/bootstrap.php';

$installed = file_exists(__DIR__ . '/install.lock');
$messages = [];
$errors = [];

function run_schema_file($path)
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException('فایل اسکیما یافت نشد: ' . $path);
    }
    // حذف توضیحات و اجرای تک‌تک دستورات
    $lines = [];
    foreach (explode("\n", $sql) as $line) {
        if (preg_match('/^\s*--/', $line)) {
            continue;
        }
        $lines[] = $line;
    }
    $statements = array_filter(array_map('trim', explode(';', implode("\n", $lines))));
    foreach ($statements as $stmt) {
        DB::pdo()->exec($stmt);
    }
}

function seed_base_data()
{
    $now = now_datetime();

    // تنظیمات پیش‌فرض
    $defaults = [
        'company_name'         => 'شرکت نمونه',
        'company_phone'        => '021-00000000',
        'company_address'      => 'تهران',
        'company_economic_code'=> '',
        'default_tax_percent'  => '10',
    ];
    foreach ($defaults as $key => $value) {
        $exists = DB::value('SELECT id FROM settings WHERE setting_key = ?', [$key]);
        if (!$exists) {
            DB::insert('settings', ['setting_key' => $key, 'setting_value' => $value]);
        }
    }

    // کدینگ حساب‌ها (استاندارد حسابداری)
    $accounts = [
        // [code, name, type, parent_code, system_key]
        ['1', 'دارایی‌ها', 'asset', null, null],
        ['1100', 'صندوق', 'asset', '1', 'cash'],
        ['1200', 'بانک', 'asset', '1', 'bank'],
        ['1300', 'حساب‌های دریافتنی (مطالبات)', 'asset', '1', 'receivable'],
        ['1400', 'موجودی کالا', 'asset', '1', null],
        ['2', 'بدهی‌ها', 'liability', null, null],
        ['2100', 'حساب‌های پرداختنی', 'liability', '2', 'payable'],
        ['2200', 'مالیات پرداختنی', 'liability', '2', 'tax_payable'],
        ['3', 'سرمایه', 'equity', null, null],
        ['3100', 'سرمایه آورده', 'equity', '3', 'capital'],
        ['4', 'درآمدها', 'income', null, null],
        ['4100', 'درآمد فروش', 'income', '4', 'sales'],
        ['4200', 'درآمد خدمات', 'income', '4', null],
        ['5', 'هزینه‌ها', 'expense', null, null],
        ['5100', 'بهای تمام‌شده کالای فروش‌رفته / خرید', 'expense', '5', 'purchases'],
        ['5200', 'حقوق و دستمزد', 'expense', '5', null],
        ['5300', 'اجاره', 'expense', '5', null],
        ['5400', 'آب و برق و تلفن', 'expense', '5', null],
        ['5500', 'حمل و نقل', 'expense', '5', null],
        ['5600', 'تعمیرات و نگهداری', 'expense', '5', null],
        ['5700', 'اداری و دفتری', 'expense', '5', null],
        ['5800', 'سایر هزینه‌ها', 'expense', '5', null],
    ];
    $idsByCode = [];
    foreach ($accounts as [$code, $name, $type, $parentCode, $sysKey]) {
        $exists = DB::value('SELECT id FROM accounts WHERE code = ?', [$code]);
        if ($exists) {
            $idsByCode[$code] = (int)$exists;
            continue;
        }
        $idsByCode[$code] = DB::insert('accounts', [
            'code'        => $code,
            'name'        => $name,
            'account_type'=> $type,
            'parent_id'   => $parentCode ? ($idsByCode[$parentCode] ?? null) : null,
            'system_key'  => $sysKey,
            'created_at'  => $now,
        ]);
    }

    // کاربر مدیر پیش‌فرض
    $adminExists = DB::value('SELECT id FROM users WHERE username = ?', ['admin']);
    if (!$adminExists) {
        DB::insert('users', [
            'username'      => 'admin',
            'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
            'full_name'     => 'مدیر سیستم',
            'role'          => 'admin',
            'is_active'     => 1,
            'created_at'    => $now,
        ]);
    }
    return $idsByCode;
}

function seed_demo_data()
{
    $now = now_datetime();
    if (DB::value('SELECT COUNT(*) FROM persons', [], 0) > 0) {
        return; // قبلاً داده نمونه ثبت شده
    }

    $persons = [
        ['P-1001', 'فروشگاه ایرانیان', 'customer', '09121112233', 'تهران، خیابان آزادی', 0],
        ['P-1002', 'شرکت پخش البرز', 'supplier', '02133445566', 'کرج، بلوار چمران', 0],
        ['P-1003', 'محمد رضایی', 'customer', '09355554433', 'اصفهان، خیابان چهارباغ', 1500000],
        ['P-1004', 'بازرگانی کاوه', 'both', '02177889900', 'تهران، جاده خاوران', 0],
    ];
    $personIds = [];
    foreach ($persons as [$code, $name, $type, $phone, $address, $opening]) {
        $personIds[$code] = DB::insert('persons', [
            'code' => $code, 'name' => $name, 'person_type' => $type,
            'phone' => $phone, 'address' => $address,
            'opening_balance' => $opening, 'created_at' => $now,
        ]);
        // سند افتتاحیه مانده اول دوره
        post_person_opening(DB::one('SELECT * FROM persons WHERE id = ?', [$personIds[$code]]));
    }

    $products = [
        ['K-100', 'کیف اداری چرم', 'عدد', 850000, 1250000, 40],
        ['K-101', 'کفش رسمی مردانه', 'جفت', 1200000, 1850000, 25],
        ['K-102', 'کمربند چرم', 'عدد', 250000, 420000, 60],
        ['K-103', 'کیف پول', 'عدد', 180000, 320000, 80],
        ['K-104', 'جاکلیدی فلزی', 'عدد', 45000, 95000, 150],
        ['K-105', 'ست هدیه مدیریتی', 'ست', 2100000, 3400000, 15],
    ];
    $productIds = [];
    foreach ($products as [$code, $name, $unit, $buy, $sell, $stock]) {
        $productIds[$code] = DB::insert('products', [
            'code' => $code, 'name' => $name, 'unit' => $unit,
            'buy_price' => $buy, 'sell_price' => $sell,
            'stock_qty' => $stock, 'created_at' => $now,
        ]);
    }

    // چند فاکتور، دریافت/پرداخت و هزینه نمونه
    $date = fn($daysAgo) => date('Y-m-d', strtotime("-{$daysAgo} days"));

    $makeInvoice = function ($type, $personId, $daysAgo, array $items, $taxPercent, $paidAmount) use ($date) {
        $itemsTotal = 0;
        $rows = [];
        foreach ($items as [$productId, $desc, $qty, $price, $discount]) {
            $total = $qty * $price - $discount;
            $itemsTotal += $qty * $price;
            $rows[] = ['product_id' => $productId, 'description' => $desc, 'qty' => $qty,
                       'unit_price' => $price, 'discount' => $discount, 'total' => $total];
        }
        $discountTotal = array_sum(array_column($rows, 'discount'));
        $net = $itemsTotal - $discountTotal;
        $taxTotal = round($net * $taxPercent / 100);
        $grand = $net + $taxTotal;
        $invoiceId = DB::insert('invoices', [
            'invoice_no'    => next_doc_no($type === 'sale' ? 'S' : 'B', 'invoices', 'invoice_no'),
            'invoice_type'  => $type,
            'person_id'     => $personId,
            'invoice_date'  => $date($daysAgo),
            'items_total'   => $itemsTotal,
            'discount_total'=> $discountTotal,
            'tax_percent'   => $taxPercent,
            'tax_total'     => $taxTotal,
            'grand_total'   => $grand,
            'paid_amount'   => 0,
            'status'        => 'unpaid',
            'description'   => 'فاکتور نمونه',
            'created_at'    => now_datetime(),
        ]);
        foreach ($rows as $row) {
            $row['invoice_id'] = $invoiceId;
            DB::insert('invoice_items', $row);
        }
        $invoice = DB::one('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
        $person = DB::one('SELECT * FROM persons WHERE id = ?', [$personId]);
        post_invoice_journal($invoice, $person, 1);
        return $invoiceId;
    };

    $inv1 = $makeInvoice('sale', $personIds['P-1001'], 45, [
        [$productIds['K-100'], 'کیف اداری چرم', 5, 1250000, 0],
        [$productIds['K-103'], 'کیف پول', 10, 320000, 200000],
    ], 10, 0);
    $inv2 = $makeInvoice('sale', $personIds['P-1003'], 20, [
        [$productIds['K-101'], 'کفش رسمی مردانه', 3, 1850000, 0],
    ], 10, 0);
    $inv3 = $makeInvoice('sale', $personIds['P-1004'], 5, [
        [$productIds['K-105'], 'ست هدیه مدیریتی', 2, 3400000, 150000],
        [$productIds['K-102'], 'کمربند چرم', 6, 420000, 0],
    ], 0, 0);
    $makeInvoice('purchase', $personIds['P-1002'], 30, [
        [$productIds['K-100'], 'کیف اداری چرم', 20, 850000, 0],
        [$productIds['K-102'], 'کمربند چرم', 30, 250000, 0],
    ], 10, 0);
    $makeInvoice('purchase', $personIds['P-1004'], 12, [
        [$productIds['K-104'], 'جاکلیدی فلزی', 100, 45000, 50000],
    ], 0, 0);

    $cashId = (int)account_by_key('cash')['id'];
    $bankId = (int)account_by_key('bank')['id'];

    // سند سرمایه اولیه (واریز به بانک)
    $capitalId = (int)account_by_key('capital')['id'];
    post_journal(date('Y-m-d', strtotime('-60 days')), 'سرمایه‌گذاری اولیه واریز به بانک', 'manual', null, [
        ['account_id' => $bankId, 'debit' => 50000000, 'credit' => 0, 'description' => 'سرمایه اولیه'],
        ['account_id' => $capitalId, 'debit' => 0, 'credit' => 50000000, 'description' => 'سرمایه اولیه'],
    ], 1);

    $makeTxn = function ($type, $personId, $accountId, $invoiceId, $amount, $daysAgo, $desc) {
        $txnId = DB::insert('transactions', [
            'txn_no'     => next_doc_no($type === 'receive' ? 'R' : 'P', 'transactions', 'txn_no'),
            'txn_type'   => $type,
            'txn_date'   => date('Y-m-d', strtotime("-{$daysAgo} days")),
            'person_id'  => $personId,
            'account_id' => $accountId,
            'invoice_id' => $invoiceId,
            'amount'     => $amount,
            'description'=> $desc,
            'created_at' => now_datetime(),
        ]);
        $txn = DB::one('SELECT * FROM transactions WHERE id = ?', [$txnId]);
        post_transaction_journal($txn, 1);
        if ($invoiceId) {
            refresh_invoice_status($invoiceId);
        }
        return $txnId;
    };

    $makeTxn('receive', $personIds['P-1001'], $bankId, $inv1, 4000000, 30, 'دریافت بابت فاکتور فروش');
    $makeTxn('receive', $personIds['P-1003'], $cashId, $inv2, 2500000, 10, 'دریافت نقدی');
    $makeTxn('receive', $personIds['P-1004'], $bankId, $inv3, 3000000, 2, 'دریافت علی‌الحساب');
    $makeTxn('payment', $personIds['P-1002'], $bankId, null, 15000000, 25, 'پرداخت بابت خرید');

    $makeExpense = function ($categoryId, $accountId, $amount, $daysAgo, $desc) {
        $id = DB::insert('expenses', [
            'expense_no'          => next_doc_no('E', 'expenses', 'expense_no'),
            'expense_date'        => date('Y-m-d', strtotime("-{$daysAgo} days")),
            'category_account_id' => $categoryId,
            'account_id'          => $accountId,
            'amount'              => $amount,
            'description'         => $desc,
            'created_at'          => now_datetime(),
        ]);
        $expense = DB::one('SELECT * FROM expenses WHERE id = ?', [$id]);
        post_expense_journal($expense, 1);
        return $id;
    };

    $acc5300 = (int)DB::value("SELECT id FROM accounts WHERE code = '5300'");
    $acc5400 = (int)DB::value("SELECT id FROM accounts WHERE code = '5400'");
    $acc5500 = (int)DB::value("SELECT id FROM accounts WHERE code = '5500'");
    $makeExpense($acc5300, $cashId, 8000000, 15, 'اجاره ماهانه دفتر');
    $makeExpense($acc5400, $cashId, 1250000, 8, 'قبض برق و آب');
    $makeExpense($acc5500, $bankId, 2200000, 3, 'هزینه حمل و نقل سفارشات');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $driver = DB::driver(); // اتصال (با fallback در صورت نیاز)
        run_schema_file(__DIR__ . '/database/' . ($driver === 'sqlite' ? 'schema.sqlite.sql' : 'schema.sql'));
        seed_base_data();
        if (req('demo_data') === '1') {
            DB::begin();
            try {
                seed_demo_data();
                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        }
        file_put_contents(__DIR__ . '/install.lock', now_datetime());
        $installed = true;
        $messages[] = 'نصب با موفقیت انجام شد! درایور پایگاه داده: ' . $driver;
        if (DB::fallbackNote()) {
            $messages[] = DB::fallbackNote();
        }
        $messages[] = 'نام کاربری: admin — رمز عبور: admin123 (لطفاً پس از ورود رمز را تغییر دهید)';
    } catch (Throwable $e) {
        $errors[] = 'خطا در نصب: ' . $e->getMessage();
    }
}

$phpVersion = PHP_VERSION;
$pdoDrivers = class_exists('PDO') ? PDO::getAvailableDrivers() : [];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نصب سامانه حسابداری</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="standalone">
<div class="login-card" style="max-width:560px">
    <div class="login-logo">&#9635;</div>
    <h2>نصب سامانه حسابداری</h2>
    <p class="sub">ساخت پایگاه داده، جداول و داده‌های اولیه</p>

    <?php foreach ($messages as $m): ?>
        <div class="alert alert-success"><?= e($m) ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $m): ?>
        <div class="alert alert-danger"><?= e($m) ?></div>
    <?php endforeach; ?>

    <div class="card" style="margin-bottom:16px">
        <strong>وضعیت محیط</strong>
        <ul style="line-height:2; padding-right:18px; margin-bottom:0">
            <li>نسخه PHP: <?= e($phpVersion) ?> <?= version_compare($phpVersion, '8.0', '>=') ? '✓' : '✗ (حداقل 8.0)' ?></li>
            <li>افزونه PDO: <?= $pdoDrivers ? e(implode('، ', $pdoDrivers)) : '✗ نصب نیست' ?></li>
            <li>درایور پیکربندی‌شده: <?= e(config('db.driver')) ?></li>
        </ul>
    </div>

    <?php if ($installed && !$messages): ?>
        <div class="alert alert-info">سامانه قبلاً نصب شده است. اگر می‌خواهید دوباره نصب کنید، ابتدا فایل <code>install.lock</code> را حذف کنید.</div>
        <a class="btn btn-primary btn-block" href="login.php">ورود به سامانه</a>
    <?php elseif ($installed): ?>
        <a class="btn btn-primary btn-block" href="login.php">ورود به سامانه</a>
    <?php else: ?>
        <form method="post">
            <?= csrf_field() ?>
            <label style="display:flex; gap:8px; align-items:center; margin-bottom:16px">
                <input type="checkbox" name="demo_data" value="1" checked>
                ایجاد داده‌های نمونه (اشخاص، کالا، فاکتور و اسناد نمونه برای شروع سریع)
            </label>
            <button class="btn btn-primary btn-block" type="submit">شروع نصب</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
