-- ============================================================
-- rahasiaemas.id — MIGRASI v14 (Dashboard Pengundang/Referrer)
-- ADDITIVE ONLY — menambah kolom login opsional ke tabel referrers.
-- Referrer lama TIDAK terpengaruh: password_hash NULL sampai mereka
-- sendiri mengaktifkan akun lewat /referrer/set-password.php.
-- Aman dijalankan berkali-kali.
-- ============================================================

ALTER TABLE referrers ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL AFTER whatsapp;
ALTER TABLE referrers ADD COLUMN IF NOT EXISTS status ENUM('pending','active') NOT NULL DEFAULT 'pending' AFTER password_hash;
ALTER TABLE referrers ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL AFTER status;

ALTER TABLE referrers ADD INDEX IF NOT EXISTS idx_referrers_brand_whatsapp (brand_id, whatsapp);

-- Selesai.
