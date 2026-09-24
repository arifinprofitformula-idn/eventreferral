-- ============================================================
-- rahasiaemas.id — Migrasi v27 LMS Payment Settings
-- Persiapan Midtrans + Transfer Bank untuk checkout eCourse.
-- ============================================================

CREATE TABLE IF NOT EXISTS lms_payment_settings (
    brand_id INT NOT NULL PRIMARY KEY,
    active_method ENUM('bank_transfer', 'midtrans') NOT NULL DEFAULT 'bank_transfer',
    bank_transfer_enabled TINYINT(1) NOT NULL DEFAULT 1,
    bank_name VARCHAR(120) NULL,
    bank_account_number VARCHAR(80) NULL,
    bank_account_name VARCHAR(150) NULL,
    bank_instructions TEXT NULL,
    midtrans_enabled TINYINT(1) NOT NULL DEFAULT 0,
    midtrans_environment ENUM('sandbox', 'production') NOT NULL DEFAULT 'sandbox',
    midtrans_server_key VARCHAR(255) NULL,
    midtrans_client_key VARCHAR(255) NULL,
    midtrans_merchant_id VARCHAR(100) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_lms_payment_settings_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

ALTER TABLE lms_orders
ADD COLUMN IF NOT EXISTS midtrans_transaction_id VARCHAR(120) NULL AFTER payment_method;

ALTER TABLE lms_orders
ADD COLUMN IF NOT EXISTS payment_reference VARCHAR(120) NULL AFTER midtrans_transaction_id;

ALTER TABLE lms_orders
ADD COLUMN IF NOT EXISTS payment_payload JSON NULL AFTER payment_reference;