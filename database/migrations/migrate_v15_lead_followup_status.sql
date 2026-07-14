-- ============================================================
-- rahasiaemas.id — MIGRASI v15 (Status Follow-up Lead Pengundang)
-- ADDITIVE ONLY — menambah kolom status follow-up ke tabel leads.
-- Semua lead lama otomatis berstatus 'baru'. Aman dijalankan berkali-kali.
-- ============================================================

ALTER TABLE leads ADD COLUMN IF NOT EXISTS followup_status ENUM('baru','dihubungi','closing') NOT NULL DEFAULT 'baru' AFTER kota;

-- Selesai.
