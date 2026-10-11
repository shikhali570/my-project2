<?php
/** مرکز گزارش‌ها */
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
$page_title = 'گزارش‌ها';

$reports = [
    ['journal.php',       '&#128220;', 'دفتر روزنامه',      'کلیه اسناد حسابداری به تفکیک تاریخ با جزئیات بدهکار و بستانکار'],
    ['ledger.php',        '&#128218;', 'دفتر کل',           'گردش و مانده یک حساب مشخص در بازه زمانی دلخواه'],
    ['trial_balance.php', '&#9878;',   'تراز آزمایشی',      'فهرست مانده همه حساب‌ها و کنترل برابری بدهکار و بستانکار'],
    ['profit_loss.php',   '&#128200;', 'صورت سود و زیان',   'درآمدها و هزینه‌های یک دوره و سود/زیان خالص'],
    ['balance_sheet.php', '&#128208;', 'ترازنامه',          'وضعیت مالی در یک تاریخ: دارایی‌ها، بدهی‌ها و سرمایه'],
    ['person_balances.php','&#128101;','مانده اشخاص',       'میزان بدهکاری/بستانکاری هر مشتری و تأمین‌کننده'],
    ['invoice_report.php','&#128179;', 'گزارش فاکتورها',    'خلاصه فاکتورهای خرید و فروش با فیلتر تاریخ، شخص و وضعیت'],
];

include __DIR__ . '/../includes/layout/header.php';
?>
<div class="report-cards">
    <?php foreach ($reports as [$href, $icon, $title, $desc]): ?>
        <a class="report-card" href="<?= e($href) ?>">
            <div class="ri"><?= $icon ?></div>
            <h3><?= e($title) ?></h3>
            <p><?= e($desc) ?></p>
        </a>
    <?php endforeach; ?>
</div>
<?php include __DIR__ . '/../includes/layout/footer.php'; ?>
