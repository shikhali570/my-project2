<?php
/** منوی کناری */
$current = basename($_SERVER['SCRIPT_NAME'] ?? '');
$currentDir = basename(dirname($_SERVER['SCRIPT_NAME'] ?? ''));

function menu_link($href, $label, $icon, $active)
{
    $cls = $active ? ' class="active"' : '';
    echo '<a href="' . e(url($href)) . '"' . $cls . '><span class="mi">' . $icon . '</span>' . e($label) . '</a>';
}
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo">&#9635;</div>
        <div>
            <strong><?= e(setting('company_name', config('app.name'))) ?></strong>
            <small>سامانه حسابداری</small>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">منوی اصلی</div>
        <?php menu_link('index.php', 'داشبورد', '&#127968;', $current === 'index.php'); ?>
        <?php menu_link('persons.php', 'اشخاص', '&#128101;', $current === 'persons.php'); ?>
        <?php menu_link('products.php', 'اقلام و کالاها', '&#128230;', $current === 'products.php'); ?>

        <div class="nav-section">عملیات</div>
        <?php menu_link('invoices.php?type=sale', 'فاکتور فروش', '&#128179;', $current === 'invoices.php' && req('type', 'sale') === 'sale' && $currentDir !== 'reports'); ?>
        <?php menu_link('invoices.php?type=purchase', 'فاکتور خرید', '&#128722;', $current === 'invoices.php' && req('type') === 'purchase'); ?>
        <?php menu_link('transactions.php', 'دریافت و پرداخت', '&#128176;', $current === 'transactions.php'); ?>
        <?php menu_link('expenses.php', 'تنخواه و هزینه ها', '&#128184;', $current === 'expenses.php'); ?>

        <div class="nav-section">حسابداری</div>
        <?php menu_link('accounts.php', 'کدینگ حساب ها', '&#128218;', $current === 'accounts.php'); ?>
        <?php menu_link('reports/index.php', 'گزارش ها', '&#128202;', $currentDir === 'reports'); ?>
        <?php if ($currentDir === 'reports'): ?>
        <div class="nav-sub">
            <?php menu_link('reports/journal.php', 'دفتر روزنامه', '', $current === 'journal.php'); ?>
            <?php menu_link('reports/ledger.php', 'دفتر کل', '', $current === 'ledger.php'); ?>
            <?php menu_link('reports/trial_balance.php', 'تراز آزمایشی', '', $current === 'trial_balance.php'); ?>
            <?php menu_link('reports/profit_loss.php', 'سود و زیان', '', $current === 'profit_loss.php'); ?>
            <?php menu_link('reports/balance_sheet.php', 'ترازنامه', '', $current === 'balance_sheet.php'); ?>
            <?php menu_link('reports/person_balances.php', 'مانده اشخاص', '', $current === 'person_balances.php'); ?>
            <?php menu_link('reports/invoice_report.php', 'گزارش فاکتورها', '', $current === 'invoice_report.php'); ?>
        </div>
        <?php endif; ?>

        <?php if (is_admin()): ?>
        <div class="nav-section">سیستم</div>
        <?php menu_link('users.php', 'کاربران', '&#128100;', $current === 'users.php'); ?>
        <?php menu_link('settings.php', 'تنظیمات', '&#9881;', $current === 'settings.php'); ?>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <small>نسخه 1.0</small>
    </div>
</aside>
