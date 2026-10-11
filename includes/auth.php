<?php
/**
 * احراز هویت و سطح دسترسی کاربران
 */

function auth_user()
{
    return $_SESSION['user'] ?? null;
}

function auth_check()
{
    return !empty($_SESSION['user']);
}

function current_user_id()
{
    return (int)($_SESSION['user']['id'] ?? 0);
}

function current_user_role()
{
    return $_SESSION['user']['role'] ?? 'guest';
}

function current_user_name()
{
    return $_SESSION['user']['full_name'] ?? '';
}

/** ورود کاربر */
function auth_login($username, $password)
{
    $user = DB::one('SELECT * FROM users WHERE username = ? LIMIT 1', [$username]);
    if (!$user || !(int)$user['is_active']) {
        return false;
    }
    if (!password_verify($password, $user['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    session_cookie_send();
    $_SESSION['user'] = [
        'id'        => (int)$user['id'],
        'username'  => $user['username'],
        'full_name' => $user['full_name'],
        'role'      => $user['role'],
    ];
    return true;
}

function auth_logout()
{
    // پاک‌سازی داده‌های نشست و بازتولید شناسه (نشست برای پیام‌های بعدی باقی می‌ماند)
    $_SESSION = [];
    session_regenerate_id(true);
    session_cookie_send();
}

/** اجبار ورود */
function require_login()
{
    if (!auth_check()) {
        flash_set('warning', 'لطفاً ابتدا وارد شوید.');
        redirect('login.php');
    }
}

/** اجبار نقش خاص */
function require_role($roles)
{
    require_login();
    if (!in_array(current_user_role(), (array)$roles, true)) {
        http_response_code(403);
        include __DIR__ . '/layout/403.php';
        exit;
    }
}

/** اجبار دسترسی ثبت/ویرایش */
function require_edit_access()
{
    require_login();
    if (!can_edit()) {
        http_response_code(403);
        include __DIR__ . '/layout/403.php';
        exit;
    }
}
