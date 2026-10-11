<?php
/**
 * پیکربندی سامانه حسابداری
 * ---------------------------------------------------------------
 * این فایل را متناسب با هاست خود ویرایش کنید.
 * برای تغییرات محلی (بدین دست زدن به این فایل) می‌توانید فایل
 * config.local.php بسازید؛ مقادیر آن بر مقادیر این فایل اولویت دارند.
 */
$config = [
    'db' => [
        // درایور پایگاه داده: 'mysql' (پیشنهادی روی هاست) یا 'sqlite'
        'driver' => 'mysql',

        // تنظیمات MySQL / MariaDB
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'dbname'   => 'accounting',
        'username' => 'root',
        'password' => '',
        'charset'  => 'utf8mb4',

        // مسیر فایل دیتابیس SQLite (فقط وقتی درایور sqlite است)
        'sqlite_path' => __DIR__ . '/database/accounting.sqlite',

        // اگر اتصال MySQL ممکن نبود، به‌صورت خودکار از SQLite استفاده شود؟
        // (برای اجرای دمو و تست بدون نصب MySQL)
        'allow_sqlite_fallback' => true,
    ],

    'app' => [
        'name'     => 'سامانه حسابداری',
        'timezone' => 'Asia/Tehran',
        // آدرس پایه سایت؛ اگر در زیرشاخه نصب شده مثل: /accounting
        'base_url' => '',
        // نمایش اعداد با جداکننده هزارگان
        'thousand_separator' => ',',
    ],
];

// ادغام تنظیمات محلی (اختیاری)
$localConfigFile = __DIR__ . '/config.local.php';
if (file_exists($localConfigFile)) {
    $local = require $localConfigFile;
    if (is_array($local)) {
        foreach ($local as $key => $value) {
            if (is_array($value) && isset($config[$key]) && is_array($config[$key])) {
                $config[$key] = array_merge($config[$key], $value);
            } else {
                $config[$key] = $value;
            }
        }
    }
}

return $config;
