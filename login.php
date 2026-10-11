<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!file_exists(__DIR__ . '/install.lock')) {
    redirect('install.php');
}
if (auth_check()) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = req('username');
    $password = req('password');
    if ($username === '' || $password === '') {
        $error = 'نام کاربری و رمز عبور را وارد کنید.';
    } elseif (auth_login($username, $password)) {
        flash_set('success', 'خوش آمدید، ' . current_user_name() . '!');
        redirect('index.php');
    } else {
        $error = 'نام کاربری یا رمز عبور اشتباه است.';
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود | <?= e(setting('company_name', config('app.name'))) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="standalone">
<div class="login-card">
    <div class="login-logo">&#9635;</div>
    <h2><?= e(setting('company_name', config('app.name'))) ?></h2>
    <p class="sub">سامانه حسابداری تحت وب — ورود به حساب کاربری</p>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <div class="form-group">
            <label>نام کاربری</label>
            <input class="form-control" type="text" name="username" value="<?= e(req('username')) ?>" required autofocus>
        </div>
        <div class="form-group">
            <label>رمز عبور</label>
            <input class="form-control" type="password" name="password" required>
        </div>
        <button class="btn btn-primary btn-block" type="submit">ورود</button>
    </form>
</div>
</body>
</html>
