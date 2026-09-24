-- rahasiaemas.id — Migrasi v29 bukti transfer dan whatsapp admin payment
ALTER TABLE lms_payment_settings
ADD COLUMN IF NOT EXISTS admin_whatsapp VARCHAR(25) NULL AFTER bank_instructions;

ALTER TABLE lms_orders
ADD COLUMN IF NOT EXISTS payment_proof_path VARCHAR(255) NULL AFTER payment_payload;

ALTER TABLE lms_orders
ADD COLUMN IF NOT EXISTS payment_proof_uploaded_at DATETIME NULL AFTER payment_proof_path;