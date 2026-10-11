<?php
/**
 * راه‌اندازی اولیه برنامه — در ابتدای همه صفحات فراخوانی می‌شود
 */

if (file_exists(__DIR__ . '/../install.lock') === false && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php') {
    // اگر هنوز نصب نشده، به صفحه نصب هدایت می‌شود
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (!in_array($script, ['install.php', 'login.php'], true)) {
        header('Location: install.php');
        exit;
    }
}

$GLOBALS['APP_CONFIG'] = require dirname(__DIR__) . '/config.php';

/** خواندن مقدار از پیکربندی با نقطه: config('db.host') */
function config($key = null, $default = null)
{
    $cfg = $GLOBALS['APP_CONFIG'];
    if ($key === null) {
        return $cfg;
    }
    $parts = explode('.', $key);
    $value = $cfg;
    foreach ($parts as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
        } else {
            return $default;
        }
    }
    return $value;
}

date_default_timezone_set(config('app.timezone', 'Asia/Tehran'));
mb_internal_encoding('UTF-8');

/** تشخیص HTTPS (با در نظر گرفتن پراکسی معکوس) */
function request_is_https()
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
        return true;
    }
    if (($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on') {
        return true;
    }
    if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }
    if ((int)($_SERVER['HTTP_X_FORWARDED_PORT'] ?? 0) === 443) {
        return true;
    }
    return false;
}

/**
 * ارسال کوکی نشست به‌صورت دستی
 * (پشتیبانی از SameSite=None و Partitioned برای محیط‌های HTTPS/توکار)
 */
function session_cookie_send()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $https = request_is_https();
    $value = rawurlencode(session_name()) . '=' . rawurlencode(session_id());
    $attrs = '; Path=/; HttpOnly';
    if ($https) {
        // SameSite=None برای iframe + Partitioned (CHIPS) برای مرورگرهایی که کوکی شخص ثالث را مسدود می‌کنند
        $attrs .= '; SameSite=None; Secure; Partitioned';
    } else {
        $attrs .= '; SameSite=Lax';
    }
    header('Set-Cookie: ' . $value . $attrs, false);
}

// نشست
if (session_status() === PHP_SESSION_NONE) {
    // مسیر قابل‌نوشتن برای فایل‌های نشست
    $sessionDir = dirname(__DIR__) . '/database/sessions';
    if (!is_dir($sessionDir)) {
        @mkdir($sessionDir, 0775, true);
    }
    if (is_dir($sessionDir) && is_writable($sessionDir)) {
        @ini_set('session.save_path', $sessionDir);
    }
    session_name('ACCSESSID');
    @ini_set('session.use_cookies', '0');     // کوکی را خودمان مدیریت می‌کنیم
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.use_strict_mode', '1');

    // بازیابی شناسه نشست از کوکی (فقط قالب معتبر)
    $incomingSid = (string)($_COOKIE[session_name()] ?? '');
    if (preg_match('/^[a-f0-9]{32,128}$/', $incomingSid)) {
        session_id($incomingSid);
    }
    session_start();
    session_cookie_send();
}

require_once __DIR__ . '/jalali.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/accounting.php';

/** تنظیمات شرکت از جدول settings */
function setting($key, $default = '')
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (DB::all('SELECT setting_key, setting_value FROM settings') as $row) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable $e) {
            // قبل از نصب جدولی وجود ندارد
        }
    }
    return $cache[$key] ?? $default;
}
