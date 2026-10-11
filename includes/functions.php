<?php
/**
 * توابع کمکی عمومی
 */

/** خروجی امن HTML */
function e($str)
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/** خواندن مقدار از POST/GET با مقدار پیش‌فرض */
function req($key, $default = '')
{
    return isset($_POST[$key]) ? (is_string($_POST[$key]) ? trim($_POST[$key]) : $_POST[$key])
        : (isset($_GET[$key]) ? (is_string($_GET[$key]) ? trim($_GET[$key]) : $_GET[$key]) : $default);
}

/** مقدار عددی از ورودی */
function req_num($key, $default = 0)
{
    $v = req($key, $default);
    if ($v === '' || $v === null) {
        return $default;
    }
    return (float)str_replace([',', ' '], '', (string)$v);
}

/** قالب‌بندی مبلغ */
function money($amount, $withCurrency = false)
{
    $sep = config('app.thousand_separator') ?: ',';
    $formatted = number_format((float)$amount, 0, '.', $sep);
    return $withCurrency ? $formatted . ' ریال' : $formatted;
}

/** قالب‌بندی تعداد (تا ۳ رقم اعشار در صورت نیاز) */
function qty_format($qty)
{
    $qty = (float)$qty;
    if (abs($qty - round($qty)) < 0.0001) {
        return number_format($qty, 0);
    }
    return rtrim(rtrim(number_format($qty, 3, '.', ','), '0'), '.');
}

/** تاریخ میلادی → نمای شمسی */
function jdate_fmt($gregorian)
{
    return jdate($gregorian);
}

/** وضعیت فاکتور به فارسی + کلاس رنگ */
function status_badge($status)
{
    $map = [
        'paid'    => ['پرداخت‌شده', 'success'],
        'partial' => ['پرداخت جزئی', 'warning'],
        'unpaid'  => ['پرداخت‌نشده', 'danger'],
    ];
    $info = $map[$status] ?? [$status, 'secondary'];
    return '<span class="badge badge-' . $info[1] . '">' . e($info[0]) . '</span>';
}

function person_type_label($type)
{
    $map = ['customer' => 'مشتری', 'supplier' => 'تأمین‌کننده', 'both' => 'هر دو'];
    return $map[$type] ?? $type;
}

function account_type_label($type)
{
    $map = [
        'asset'     => 'دارایی',
        'liability' => 'بدهی',
        'equity'    => 'سرمایه',
        'income'    => 'درآمد',
        'expense'   => 'هزینه',
    ];
    return $map[$type] ?? $type;
}

function invoice_type_label($type)
{
    return $type === 'sale' ? 'فروش' : 'خرید';
}

function txn_type_label($type)
{
    return $type === 'receive' ? 'دریافت' : 'پرداخت';
}

function role_label($role)
{
    $map = ['admin' => 'مدیر سیستم', 'accountant' => 'حسابدار', 'user' => 'کاربر ساده'];
    return $map[$role] ?? $role;
}

/** ساخت آدرس نسبی به ریشه برنامه (سازگار با صفحات زیرشاخه‌دار) */
function url($path = '')
{
    static $base = null;
    if ($base === null) {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $dir = preg_replace('#/reports$#', '', rtrim($dir, '/'));
        $base = $dir === '' ? '' : $dir;
    }
    return $base . '/' . ltrim((string)$path, '/');
}

/** ریدایرکت */
function redirect($path)
{
    $url = preg_match('#^(https?:)?//#', $path) ? $path : url($path);
    header('Location: ' . $url);
    exit;
}

/** پیام‌های موقت (Flash) */
function flash_set($type, $message)
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_get()
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/** توکن CSRF */
function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * بررسی توکن CSRF
 * در صورت نامعتبر بودن، به‌جای توقف خام، با پیام راهنما دوباره فرم نمایش داده می‌شود.
 */
function csrf_check()
{
    $sent = (string)($_POST['csrf_token'] ?? '');
    $known = (string)($_SESSION['csrf_token'] ?? '');
    if ($sent !== '' && $known !== '' && hash_equals($known, $sent)) {
        return;
    }

    // تشخیص اینکه کلاً کوکی به مرورگر نرسیده (مثلاً مسدودسازی کوکی در iframe)
    if (empty($_COOKIE[session_name()]) && empty($_COOKIE)) {
        // بدون نشست، پیام را مستقیم نمایش می‌دهیم (فلش کار نمی‌کند)
        http_response_code(403);
        $back = (string)($_SERVER['REQUEST_URI'] ?? '');
        if ($back === '' || $back[0] !== '/') {
            $back = url('index.php');
        }
        echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8"><title>خطای نشست</title>
<style>body{font-family:Tahoma,sans-serif;background:#f1f5f9;margin:0}
.box{max-width:520px;margin:70px auto;background:#fff;border-radius:12px;padding:32px;text-align:center;box-shadow:0 8px 30px rgba(0,0,0,.08)}
h2{color:#d97706;margin:0 0 10px}a{display:inline-block;margin-top:16px;background:#2563eb;color:#fff;padding:10px 22px;border-radius:8px;text-decoration:none}</style>
</head><body><div class="box">
<h2>نشست شما معتبر نیست</h2>
<p>کوکی‌های مرورگر در دسترس نیستند یا نشست منقضی شده است.</p>
<p>لطفاً صفحهٔ پیش‌نمایش را در <strong>تب جدید</strong> باز کنید، یا کوکی‌ها را برای این سایت مجاز کنید و دوباره تلاش کنید.</p>
<a href="' . e($back) . '">تلاش مجدد</a>
</div></body></html>';
        exit;
    }

    flash_set('warning', 'نشست شما منقضی شده یا درخواست نامعتبر است (CSRF). لطفاً فرم را دوباره ارسال کنید.');

    // بازگشت به همان صفحه (مسیر جاری؛ بدون ریسک ریدایرکت خارجی)
    $back = (string)($_SERVER['REQUEST_URI'] ?? '');
    if ($back === '' || $back[0] !== '/') {
        $back = url('index.php');
    }
    header('Location: ' . $back);
    exit;
}

/** شماره سند بعدی مثل S-00001 */
function next_doc_no($prefix, $table, $column)
{
    $like = $prefix . '-%';
    $sql = "SELECT $column FROM $table WHERE $column LIKE ? ORDER BY id DESC LIMIT 1";
    $last = DB::value($sql, [$like]);
    $num = 1;
    if ($last) {
        $parts = explode('-', $last);
        $num = (int)end($parts) + 1;
    }
    return $prefix . '-' . str_pad((string)$num, 5, '0', STR_PAD_LEFT);
}

/** سال مالی جاری (شمسی) */
function current_fiscal_label()
{
    return 'سال ' . jdate(today_gregorian(), '/');
}

/** آیا کاربر فعلی اجازه ویرایش/ثبت دارد؟ */
function can_edit()
{
    return in_array(current_user_role(), ['admin', 'accountant'], true);
}

function is_admin()
{
    return current_user_role() === 'admin';
}
