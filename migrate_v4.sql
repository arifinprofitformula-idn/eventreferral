-- ============================================================
-- rahasiaemas.id — MIGRASI ke v4 (Tracking Meta Pixel + Google Analytics)
-- HANYA jalankan file ini jika Anda SUDAH menjalankan migrate_v2.sql
-- dan migrate_v3.sql (sudah punya tabel `events` dengan kolom `reward_image`).
--
-- Jika ini instalasi BARU (belum pernah install sama sekali),
-- JANGAN pakai file ini — langsung import install.sql saja,
-- karena install.sql versi ini sudah termasuk semua perubahan v4.
-- ============================================================

ALTER TABLE events ADD COLUMN IF NOT EXISTS meta_pixel_id VARCHAR(50) NULL AFTER reward_image;
ALTER TABLE events ADD COLUMN IF NOT EXISTS ga_measurement_id VARCHAR(30) NULL AFTER meta_pixel_id;

-- Selesai.
