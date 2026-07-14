-- ============================================================
-- rahasiaemas.id — MIGRASI ke v7 (Multi-Brand / Multi-Domain)
-- HANYA jalankan file ini jika Anda SUDAH menjalankan migrate_v2.sql
-- s.d. migrate_v6.sql (skema `events`/`referrers`/`leads` versi terkini).
--
-- URUTAN WAJIB — jangan dibalik:
--   1. Jalankan file ini (migrate_v7_multibrand.sql) — membuat tabel
--      `brands` dan menambah kolom `brand_id` (masih boleh NULL) ke
--      events/referrers/leads.
--   2. Buka admin/migrate-legacy.php SEKALI di browser — script ini
--      membuat baris brand pertama (rahasiaemas) dari isi config.php
--      dan mengisi brand_id di semua baris lama. Setelah berhasil,
--      HAPUS file admin/migrate-legacy.php dari server.
--   3. Jalankan migrate_v7_multibrand_finalize.sql — mengunci kolom
--      brand_id jadi NOT NULL + foreign key + unique key baru.
--
-- Jika ini instalasi BARU (belum pernah install sama sekali), JANGAN
-- pakai file ini — pakai install.sql yang sudah mencakup skema brand.
-- ============================================================

CREATE TABLE IF NOT EXISTS brands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(60) NOT NULL UNIQUE,
    domain VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    tagline VARCHAR(255) NULL,
    logo_path VARCHAR(255) NULL,
    favicon_path VARCHAR(255) NULL,
    whatsapp_default VARCHAR(20) NULL,
    disclaimer_text TEXT NULL,
    theme_preset ENUM('gold','silver','bronze','custom') NOT NULL DEFAULT 'gold',
    theme_primary VARCHAR(7) NULL,
    theme_charcoal VARCHAR(7) NULL,
    theme_soft VARCHAR(7) NULL,
    admin_username VARCHAR(60) NOT NULL,
    admin_password_hash VARCHAR(255) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Kolom brand_id ditambah dulu sebagai NULL (belum FK, belum NOT NULL)
-- karena baris lama belum punya nilainya — akan diisi oleh
-- admin/migrate-legacy.php di langkah berikutnya.
ALTER TABLE events ADD COLUMN IF NOT EXISTS brand_id INT NULL AFTER id;
ALTER TABLE referrers ADD COLUMN IF NOT EXISTS brand_id INT NULL AFTER id;
ALTER TABLE leads ADD COLUMN IF NOT EXISTS brand_id INT NULL AFTER id;

-- Selesai bagian 1. Lanjutkan ke admin/migrate-legacy.php.
