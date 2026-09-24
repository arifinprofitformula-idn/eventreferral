-- rahasiaemas.id — Migrasi v28 logo dan katalog bank checkout LMS
ALTER TABLE lms_payment_settings
ADD COLUMN IF NOT EXISTS bank_code VARCHAR(40) NULL AFTER bank_transfer_enabled;

ALTER TABLE lms_payment_settings
ADD COLUMN IF NOT EXISTS bank_logo_path VARCHAR(255) NULL AFTER bank_name;