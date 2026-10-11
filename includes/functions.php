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

function csrf_check()
{
    if (($_POST['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('درخواست نامعتبر است (CSRF). لطفاً دوباره تلاش کنید.');
    }
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
