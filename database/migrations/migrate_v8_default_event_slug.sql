-- ============================================================
-- rahasiaemas.id — MIGRASI ke v8 (Root Event per Brand)
-- HANYA jalankan file ini SETELAH migrate_v7_multibrand.sql,
-- admin/migrate-legacy.php, dan migrate_v7_multibrand_finalize.sql.
--
-- Kenapa perlu: DEFAULT_EVENT_SLUG dulu adalah 1 konstanta global
-- ('default') yang mewakili event landing page root domain. Karena
-- kolom events.slug tetap unik secara GLOBAL (lihat migrate_v7), brand
-- baru TIDAK BISA punya event dengan slug 'default' lagi (sudah dipakai
-- brand rahasiaemas). Setiap brand sekarang punya kolom sendiri
-- `default_event_slug` yang menyimpan slug event root domain miliknya.
-- Brand baru akan diberi slug root '{brand_slug}-default' otomatis oleh
-- admin/setup-brand.php.
-- ============================================================

ALTER TABLE brands ADD COLUMN IF NOT EXISTS default_event_slug VARCHAR(60) NOT NULL DEFAULT 'default' AFTER admin_password_hash;

-- Brand rahasiaemas yang sudah ada TIDAK perlu diubah — event root
-- domain-nya memang sudah bernama 'default', sama seperti nilai DEFAULT
-- kolom ini. Tidak ada data yang perlu di-backfill.

-- Selesai.
