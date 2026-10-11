-- =============================================================
-- اسکیمای پایگاه داده MySQL / MariaDB — سامانه حسابداری
-- اجرا:  php install.php   (توصیه‌شده)  یا  وارد کردن این فایل در phpMyAdmin
-- =============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username      VARCHAR(50)  NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name     VARCHAR(100) NOT NULL,
    role          VARCHAR(20)  NOT NULL DEFAULT 'user',   -- admin | accountant | user
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key   VARCHAR(50)  NOT NULL,
    setting_value TEXT         NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS persons (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code           VARCHAR(20)  NOT NULL,
    name           VARCHAR(100) NOT NULL,
    person_type    VARCHAR(20)  NOT NULL DEFAULT 'customer', -- customer | supplier | both
    phone          VARCHAR(30)  NULL,
    address        VARCHAR(255) NULL,
    notes          TEXT         NULL,
    opening_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
    created_at     DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_persons_code (code),
    KEY idx_persons_type (person_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounts (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code         VARCHAR(20)  NOT NULL,
    name         VARCHAR(100) NOT NULL,
    account_type VARCHAR(20)  NOT NULL,                    -- asset | liability | equity | income | expense
    parent_id    INT UNSIGNED NULL,
    system_key   VARCHAR(30)  NULL,                        -- cash|bank|receivable|payable|sales|purchases|tax_payable|capital
    created_at   DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_accounts_code (code),
    UNIQUE KEY uq_accounts_system_key (system_key),
    KEY idx_accounts_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code       VARCHAR(20)  NOT NULL,
    name       VARCHAR(100) NOT NULL,
    unit       VARCHAR(20)  NULL,
    buy_price  DECIMAL(18,2) NOT NULL DEFAULT 0,
    sell_price DECIMAL(18,2) NOT NULL DEFAULT 0,
    stock_qty  DECIMAL(18,3) NOT NULL DEFAULT 0,
    notes      TEXT          NULL,
    created_at DATETIME      NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_no     VARCHAR(20)  NOT NULL,
    invoice_type   VARCHAR(10)  NOT NULL,                  -- sale | purchase
    person_id      INT UNSIGNED NOT NULL,
    invoice_date   DATE         NOT NULL,
    due_date       DATE         NULL,
    items_total    DECIMAL(18,2) NOT NULL DEFAULT 0,
    discount_total DECIMAL(18,2) NOT NULL DEFAULT 0,
    tax_percent    DECIMAL(5,2)  NOT NULL DEFAULT 0,
    tax_total      DECIMAL(18,2) NOT NULL DEFAULT 0,
    grand_total    DECIMAL(18,2) NOT NULL DEFAULT 0,
    paid_amount    DECIMAL(18,2) NOT NULL DEFAULT 0,
    status         VARCHAR(10)  NOT NULL DEFAULT 'unpaid', -- unpaid | partial | paid
    description    VARCHAR(255) NULL,
    user_id        INT UNSIGNED NULL,
    created_at     DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_invoices_no (invoice_no),
    KEY idx_invoices_type_date (invoice_type, invoice_date),
    KEY idx_invoices_person (person_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_items (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id  INT UNSIGNED NOT NULL,
    product_id  INT UNSIGNED NULL,
    description VARCHAR(255) NULL,
    qty         DECIMAL(18,3) NOT NULL DEFAULT 1,
    unit_price  DECIMAL(18,2) NOT NULL DEFAULT 0,
    discount    DECIMAL(18,2) NOT NULL DEFAULT 0,
    total       DECIMAL(18,2) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_items_invoice (invoice_id),
    KEY idx_items_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transactions (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    txn_no      VARCHAR(20)  NOT NULL,
    txn_type    VARCHAR(10)  NOT NULL,                     -- receive | payment
    txn_date    DATE         NOT NULL,
    person_id   INT UNSIGNED NULL,
    account_id  INT UNSIGNED NOT NULL,                     -- حساب صندوق/بانک
    invoice_id  INT UNSIGNED NULL,
    amount      DECIMAL(18,2) NOT NULL DEFAULT 0,
    description VARCHAR(255) NULL,
    user_id     INT UNSIGNED NULL,
    created_at  DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_transactions_no (txn_no),
    KEY idx_transactions_type_date (txn_type, txn_date),
    KEY idx_transactions_person (person_id),
    KEY idx_transactions_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS expenses (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    expense_no         VARCHAR(20)  NOT NULL,
    expense_date       DATE         NOT NULL,
    category_account_id INT UNSIGNED NOT NULL,             -- حساب هزینه
    account_id         INT UNSIGNED NOT NULL,              -- حساب صندوق/بانک
    amount             DECIMAL(18,2) NOT NULL DEFAULT 0,
    description        VARCHAR(255) NULL,
    user_id            INT UNSIGNED NULL,
    created_at         DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_expenses_no (expense_no),
    KEY idx_expenses_date (expense_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS journal_entries (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    entry_no    VARCHAR(20)  NOT NULL,
    entry_date  DATE         NOT NULL,
    description VARCHAR(255) NULL,
    ref_type    VARCHAR(20)  NULL,                         -- invoice|transaction|expense|manual
    ref_id      INT UNSIGNED NULL,
    user_id     INT UNSIGNED NULL,
    created_at  DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_journal_no (entry_no),
    KEY idx_journal_date (entry_date),
    KEY idx_journal_ref (ref_type, ref_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS journal_lines (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    entry_id    INT UNSIGNED NOT NULL,
    account_id  INT UNSIGNED NOT NULL,
    debit       DECIMAL(18,2) NOT NULL DEFAULT 0,
    credit      DECIMAL(18,2) NOT NULL DEFAULT 0,
    description VARCHAR(255) NULL,
    PRIMARY KEY (id),
    KEY idx_lines_entry (entry_id),
    KEY idx_lines_account (account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
