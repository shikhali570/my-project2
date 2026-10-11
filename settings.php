<?php
/** تنظیمات سامانه (فقط مدیر سیستم) */
require_once __DIR__ . '/includes/bootstrap.php';
require_role(['admin']);
$page_title = 'تنظیمات';

$keys = [
    'company_name'          => 'نام شرکت / مجموعه',
    'company_phone'         => 'تلفن',
    'company_address'       => 'آدرس',
    'company_economic_code' => 'کد اقتصادی',
    'default_tax_percent'   => 'درصد پیش‌فرض مالیات بر ارزش افزوده',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($keys as $key => $label) {
        $value = req($key);
        $exists = DB::value('SELECT id FROM settings WHERE setting_key = ?', [$key]);
        if ($exists) {
            DB::update('settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
        } else {
            DB::insert('settings', ['setting_key' => $key, 'setting_value' => $value]);
        }
    }
    flash_set('success', 'تنظیمات ذخیره شد.');
    redirect('settings.php');
}

include __DIR__ . '/includes/layout/header.php';
?>
<div class="card">
    <h3 class="card-title">اطلاعات پایه</h3>
    <form method="post">
        <?= csrf_field() ?>
        <div class="form-grid">
            <?php foreach ($keys as $key => $label): ?>
                <div class="form-group">
                    <label><?= e($label) ?></label>
                    <input class="form-control" type="text" name="<?= e($key) ?>" value="<?= e(setting($key)) ?>">
                </div>
            <?php endforeach; ?>
        </div>
        <button class="btn btn-primary" type="submit">ذخیره تنظیمات</button>
    </form>
</div>

<div class="card">
    <h3 class="card-title">اطلاعات سیستم</h3>
    <div class="table-wrap">
        <table class="table">
            <tr><td>نسخه PHP</td><td><?= e(PHP_VERSION) ?></td></tr>
            <tr><td>درایور پایگاه داده</td><td><?= e(DB::driver()) ?></td></tr>
            <tr><td>منطقه زمانی</td><td><?= e(config('app.timezone')) ?></td></tr>
        </table>
    </div>
</div>
<?php include __DIR__ . '/includes/layout/footer.php'; ?>
