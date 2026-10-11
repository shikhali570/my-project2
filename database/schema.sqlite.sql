-- =============================================================
-- اسکیمای پایگاه داده SQLite — سامانه حسابداری
-- (برای اجرای دمو/تست بدون نصب MySQL؛ نسخه اصلی MySQL است)
-- =============================================================

CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    full_name     TEXT NOT NULL,
    role          TEXT NOT NULL DEFAULT 'user',
    is_active     INTEGER NOT NULL DEFAULT 1,
    created_at    TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS settings (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    setting_key   TEXT NOT NULL UNIQUE,
    setting_value TEXT NULL
);

CREATE TABLE IF NOT EXISTS persons (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    code            TEXT NOT NULL UNIQUE,
    name            TEXT NOT NULL,
    person_type     TEXT NOT NULL DEFAULT 'customer',
    phone           TEXT NULL,
    address         TEXT NULL,
    notes           TEXT NULL,
    opening_balance REAL NOT NULL DEFAULT 0,
    created_at      TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS accounts (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    code         TEXT NOT NULL UNIQUE,
    name         TEXT NOT NULL,
    account_type TEXT NOT NULL,
    parent_id    INTEGER NULL,
    system_key   TEXT NULL UNIQUE,
    created_at   TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS products (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    code       TEXT NOT NULL UNIQUE,
    name       TEXT NOT NULL,
    unit       TEXT NULL,
    buy_price  REAL NOT NULL DEFAULT 0,
    sell_price REAL NOT NULL DEFAULT 0,
    stock_qty  REAL NOT NULL DEFAULT 0,
    notes      TEXT NULL,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS invoices (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_no     TEXT NOT NULL UNIQUE,
    invoice_type   TEXT NOT NULL,
    person_id      INTEGER NOT NULL,
    invoice_date   TEXT NOT NULL,
    due_date       TEXT NULL,
    items_total    REAL NOT NULL DEFAULT 0,
    discount_total REAL NOT NULL DEFAULT 0,
    tax_percent    REAL NOT NULL DEFAULT 0,
    tax_total      REAL NOT NULL DEFAULT 0,
    grand_total    REAL NOT NULL DEFAULT 0,
    paid_amount    REAL NOT NULL DEFAULT 0,
    status         TEXT NOT NULL DEFAULT 'unpaid',
    description    TEXT NULL,
    user_id        INTEGER NULL,
    created_at     TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS invoice_items (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_id  INTEGER NOT NULL,
    product_id  INTEGER NULL,
    description TEXT NULL,
    qty         REAL NOT NULL DEFAULT 1,
    unit_price  REAL NOT NULL DEFAULT 0,
    discount    REAL NOT NULL DEFAULT 0,
    total       REAL NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS transactions (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    txn_no      TEXT NOT NULL UNIQUE,
    txn_type    TEXT NOT NULL,
    txn_date    TEXT NOT NULL,
    person_id   INTEGER NULL,
    account_id  INTEGER NOT NULL,
    invoice_id  INTEGER NULL,
    amount      REAL NOT NULL DEFAULT 0,
    description TEXT NULL,
    user_id     INTEGER NULL,
    created_at  TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS expenses (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    expense_no          TEXT NOT NULL UNIQUE,
    expense_date        TEXT NOT NULL,
    category_account_id INTEGER NOT NULL,
    account_id          INTEGER NOT NULL,
    amount              REAL NOT NULL DEFAULT 0,
    description         TEXT NULL,
    user_id             INTEGER NULL,
    created_at          TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS journal_entries (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    entry_no    TEXT NOT NULL UNIQUE,
    entry_date  TEXT NOT NULL,
    description TEXT NULL,
    ref_type    TEXT NULL,
    ref_id      INTEGER NULL,
    user_id     INTEGER NULL,
    created_at  TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS journal_lines (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    entry_id    INTEGER NOT NULL,
    account_id  INTEGER NOT NULL,
    debit       REAL NOT NULL DEFAULT 0,
    credit      REAL NOT NULL DEFAULT 0,
    description TEXT NULL
);

CREATE INDEX IF NOT EXISTS idx_invoices_type_date ON invoices (invoice_type, invoice_date);
CREATE INDEX IF NOT EXISTS idx_invoices_person ON invoices (person_id);
CREATE INDEX IF NOT EXISTS idx_items_invoice ON invoice_items (invoice_id);
CREATE INDEX IF NOT EXISTS idx_transactions_type_date ON transactions (txn_type, txn_date);
CREATE INDEX IF NOT EXISTS idx_transactions_person ON transactions (person_id);
CREATE INDEX IF NOT EXISTS idx_transactions_invoice ON transactions (invoice_id);
CREATE INDEX IF NOT EXISTS idx_expenses_date ON expenses (expense_date);
CREATE INDEX IF NOT EXISTS idx_journal_date ON journal_entries (entry_date);
CREATE INDEX IF NOT EXISTS idx_journal_ref ON journal_entries (ref_type, ref_id);
CREATE INDEX IF NOT EXISTS idx_lines_entry ON journal_lines (entry_id);
CREATE INDEX IF NOT EXISTS idx_lines_account ON journal_lines (account_id);
