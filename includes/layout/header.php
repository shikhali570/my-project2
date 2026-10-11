<?php
/** نمای کلی صفحه: تگ های بالایی HTML + نوار بالا */
$page_title = $page_title ?? 'داشبورد';
$company_name = setting('company_name', config('app.name'));
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> | <?= e($company_name) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body>
<div class="layout">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="main">
        <header class="topbar">
            <div class="topbar-title">
                <button class="sidebar-toggle" type="button" onclick="document.body.classList.toggle('sidebar-collapsed')" aria-label="منو">&#9776;</button>
                <h1><?= e($page_title) ?></h1>
            </div>
            <div class="topbar-user">
                <span class="today-date"><?= e(jdate(today_gregorian())) ?></span>
                <span class="user-chip">
                    <span class="avatar"><?= e(mb_substr(current_user_name(), 0, 1)) ?></span>
                    <?= e(current_user_name()) ?>
                    <small>[<?= e(role_label(current_user_role())) ?>]</small>
                </span>
                <a class="btn btn-sm btn-outline" href="logout.php">خروج</a>
            </div>
        </header>
        <main class="content">
        <?php foreach (flash_get() as $flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endforeach; ?>
