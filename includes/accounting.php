<?php
/**
 * عملیات حسابداری دوطرفه: ثبت اسناد روزنامه، مانده حساب‌ها و اشخاص
 */

/** دریافت حساب بر اساس کلید سیستمی (cash, bank, receivable, ...) */
function account_by_key($key)
{
    return DB::one('SELECT * FROM accounts WHERE system_key = ? LIMIT 1', [$key]);
}

function account_by_id($id)
{
    return DB::one('SELECT * FROM accounts WHERE id = ? LIMIT 1', [(int)$id]);
}

/** شناسه حساب‌های نقدی/بانکی (دارایی جاری) */
function cash_account_ids()
{
    $rows = DB::all("SELECT id FROM accounts WHERE system_key IN ('cash','bank') ORDER BY code");
    return array_map('intval', array_column($rows, 'id'));
}

/**
 * ثبت سند روزنامه دوطرفه
 * $lines: [ ['account_id'=>..,'debit'=>..,'credit'=>..,'description'=>..], ... ]
 */
function post_journal($entry_date, $description, $ref_type, $ref_id, array $lines, $user_id = null)
{
    $totalDebit = 0;
    $totalCredit = 0;
    foreach ($lines as $l) {
        $totalDebit += (float)($l['debit'] ?? 0);
        $totalCredit += (float)($l['credit'] ?? 0);
    }
    if (abs($totalDebit - $totalCredit) > 0.01) {
        throw new RuntimeException('بدهکار و بستانکار سند برابر نیست.');
    }
    if ($totalDebit <= 0 && $totalCredit <= 0) {
        return 0;
    }

    $entryId = DB::insert('journal_entries', [
        'entry_no'    => next_doc_no('J', 'journal_entries', 'entry_no'),
        'entry_date'  => $entry_date,
        'description' => $description,
        'ref_type'    => $ref_type,
        'ref_id'      => $ref_id ? (int)$ref_id : null,
        'user_id'     => $user_id ? (int)$user_id : null,
        'created_at'  => now_datetime(),
    ]);
    foreach ($lines as $l) {
        $debit = (float)($l['debit'] ?? 0);
        $credit = (float)($l['credit'] ?? 0);
        if ($debit == 0 && $credit == 0) {
            continue;
        }
        DB::insert('journal_lines', [
            'entry_id'    => $entryId,
            'account_id'  => (int)$l['account_id'],
            'debit'       => $debit,
            'credit'      => $credit,
            'description' => $l['description'] ?? $description,
        ]);
    }
    return $entryId;
}

/** حذف اسناد روزنامه مرتبط با یک مرجع (فاکتور/سند دریافت و پرداخت/هزینه) */
function delete_journal_for($ref_type, $ref_id)
{
    $entries = DB::all('SELECT id FROM journal_entries WHERE ref_type = ? AND ref_id = ?', [$ref_type, (int)$ref_id]);
    foreach ($entries as $entry) {
        DB::delete('journal_lines', 'entry_id = ?', [(int)$entry['id']]);
    }
    DB::delete('journal_entries', 'ref_type = ? AND ref_id = ?', [$ref_type, (int)$ref_id]);
}

/**
 * سند حسابداری فاکتور
 * فروش:   بدهکار مطالبات / بستانکار درآمد فروش (+ مالیات پرداختنی)
 * خرید:   بدهکار خرید / بستانکار حساب‌های پرداختنی
 */
function post_invoice_journal($invoice, $person, $user_id = null)
{
    $desc = 'فاکتور ' . invoice_type_label($invoice['invoice_type']) . ' شماره ' . $invoice['invoice_no']
          . ' - ' . ($person['name'] ?? '');
    $net = (float)$invoice['items_total'] - (float)$invoice['discount_total'];
    $tax = (float)$invoice['tax_total'];
    $grand = (float)$invoice['grand_total'];

    if ($invoice['invoice_type'] === 'sale') {
        $lines = [
            ['account_id' => account_by_key('receivable')['id'], 'debit' => $grand, 'credit' => 0, 'description' => $desc],
            ['account_id' => account_by_key('sales')['id'],      'debit' => 0,      'credit' => $net, 'description' => $desc],
        ];
        if ($tax > 0) {
            $lines[] = ['account_id' => account_by_key('tax_payable')['id'], 'debit' => 0, 'credit' => $tax, 'description' => $desc];
        }
    } else {
        // خرید: بدهکار خرید (خالص اقلام) و مالیات، بستانکار پرداختنی‌ها
        $lines = [
            ['account_id' => account_by_key('purchases')['id'], 'debit' => $grand, 'credit' => 0, 'description' => $desc],
            ['account_id' => account_by_key('payable')['id'],   'debit' => 0,      'credit' => $grand, 'description' => $desc],
        ];
    }
    return post_journal($invoice['invoice_date'], $desc, 'invoice', $invoice['id'], $lines, $user_id);
}

/** سند حسابداری دریافت/پرداخت */
function post_transaction_journal($txn, $user_id = null)
{
    $cashAccount = account_by_id($txn['account_id']);
    $desc = txn_type_label($txn['txn_type']) . ' وجه ' . $txn['txn_no']
          . (!empty($txn['description']) ? ' - ' . $txn['description'] : '');
    if ($txn['txn_type'] === 'receive') {
        // دریافت وجه: بدهکار صندوق/بانک — بستانکار مطالبات
        $lines = [
            ['account_id' => $cashAccount['id'], 'debit' => (float)$txn['amount'], 'credit' => 0, 'description' => $desc],
            ['account_id' => account_by_key('receivable')['id'], 'debit' => 0, 'credit' => (float)$txn['amount'], 'description' => $desc],
        ];
    } else {
        // پرداخت وجه: بدهکار پرداختنی‌ها — بستانکار صندوق/بانک
        $lines = [
            ['account_id' => account_by_key('payable')['id'], 'debit' => (float)$txn['amount'], 'credit' => 0, 'description' => $desc],
            ['account_id' => $cashAccount['id'], 'debit' => 0, 'credit' => (float)$txn['amount'], 'description' => $desc],
        ];
    }
    return post_journal($txn['txn_date'], $desc, 'transaction', $txn['id'], $lines, $user_id);
}

/** سند حسابداری هزینه (تنخواه) */
function post_expense_journal($expense, $user_id = null)
{
    $desc = 'سند هزینه ' . $expense['expense_no']
          . (!empty($expense['description']) ? ' - ' . $expense['description'] : '');
    $lines = [
        ['account_id' => (int)$expense['category_account_id'], 'debit' => (float)$expense['amount'], 'credit' => 0, 'description' => $desc],
        ['account_id' => (int)$expense['account_id'], 'debit' => 0, 'credit' => (float)$expense['amount'], 'description' => $desc],
    ];
    return post_journal($expense['expense_date'], $desc, 'expense', $expense['id'], $lines, $user_id);
}

/** مانده حساب از روی اسناد روزنامه تا یک تاریخ (بدهکار مثبت) */
function account_balance($account_id, $until = null)
{
    $sql = 'SELECT COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) FROM journal_lines jl
            JOIN journal_entries je ON je.id = jl.entry_id
            WHERE jl.account_id = ?';
    $params = [(int)$account_id];
    if ($until) {
        $sql .= ' AND je.entry_date <= ?';
        $params[] = $until;
    }
    return (float)DB::value($sql, $params, 0);
}

/**
 * ثبت سند افتتاحیه مانده اول دوره شخص
 * (مثبت: طلب از شخص → بدهکار مطالبات / بستانکار سرمایه)
 */
function post_person_opening($person)
{
    delete_journal_for('person_opening', (int)$person['id']);
    $opening = (float)$person['opening_balance'];
    if (abs($opening) < 0.01) {
        return 0;
    }
    $desc = 'مانده اول دوره - ' . $person['name'];
    if ($opening > 0) {
        $lines = [
            ['account_id' => account_by_key('receivable')['id'], 'debit' => $opening, 'credit' => 0, 'description' => $desc],
            ['account_id' => account_by_key('capital')['id'],    'debit' => 0, 'credit' => $opening, 'description' => $desc],
        ];
    } else {
        $lines = [
            ['account_id' => account_by_key('capital')['id'],  'debit' => abs($opening), 'credit' => 0, 'description' => $desc],
            ['account_id' => account_by_key('payable')['id'],  'debit' => 0, 'credit' => abs($opening), 'description' => $desc],
        ];
    }
    return post_journal(today_gregorian(), $desc, 'person_opening', (int)$person['id'], $lines, current_user_id() ?: null);
}

/** مانده اشخاص: مثبت = طلبکار از ما (بدهکار)، منفی = بدهکار به ما (بستانکار) */
function person_balance($person_id)
{
    $row = DB::one('SELECT opening_balance FROM persons WHERE id = ?', [(int)$person_id]);
    $opening = (float)($row['opening_balance'] ?? 0);

    $sales = (float)DB::value(
        "SELECT COALESCE(SUM(grand_total),0) FROM invoices WHERE person_id = ? AND invoice_type = 'sale'",
        [(int)$person_id], 0
    );
    $purchases = (float)DB::value(
        "SELECT COALESCE(SUM(grand_total),0) FROM invoices WHERE person_id = ? AND invoice_type = 'purchase'",
        [(int)$person_id], 0
    );
    $received = (float)DB::value(
        "SELECT COALESCE(SUM(amount),0) FROM transactions WHERE person_id = ? AND txn_type = 'receive'",
        [(int)$person_id], 0
    );
    $paid = (float)DB::value(
        "SELECT COALESCE(SUM(amount),0) FROM transactions WHERE person_id = ? AND txn_type = 'payment'",
        [(int)$person_id], 0
    );
    return $opening + $sales - $received - $purchases + $paid;
}

/** مانده نقدی (صندوق + بانک) */
function cash_balance_total()
{
    $ids = cash_account_ids();
    if (!$ids) {
        return 0;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    return (float)DB::value(
        "SELECT COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) FROM journal_lines WHERE account_id IN ($placeholders)",
        $ids, 0
    );
}

/** بروزرسانی وضعیت پرداخت فاکتور */
function refresh_invoice_status($invoice_id)
{
    $inv = DB::one('SELECT id, grand_total, paid_amount FROM invoices WHERE id = ?', [(int)$invoice_id]);
    if (!$inv) {
        return;
    }
    $paid = (float)DB::value(
        'SELECT COALESCE(SUM(amount),0) FROM transactions WHERE invoice_id = ?',
        [(int)$invoice_id], 0
    );
    $grand = (float)$inv['grand_total'];
    if ($paid >= $grand - 0.01 && $grand > 0) {
        $status = 'paid';
    } elseif ($paid > 0) {
        $status = 'partial';
    } else {
        $status = 'unpaid';
    }
    DB::update('invoices', ['paid_amount' => $paid, 'status' => $status], 'id = ?', [(int)$invoice_id]);
}
