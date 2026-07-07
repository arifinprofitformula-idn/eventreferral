-- ============================================================
-- rahasiaemas.id — Skema Database Instalasi Baru (v8 Multi-Brand)
-- Jalankan file ini SEKALI lewat phpMyAdmin (Import) di hosting Anda
-- untuk INSTALASI BARU.
--
-- Jika database sudah pernah dipakai dan berisi data lama, JANGAN import
-- file ini. Jalankan migrasi berurutan: migrate_v2.sql s.d. migrate_v8.
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
    default_event_slug VARCHAR(60) NOT NULL DEFAULT 'default',
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand_id INT NOT NULL,
    slug VARCHAR(60) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    status ENUM('active','archived') NOT NULL DEFAULT 'active',
    whatsapp_default VARCHAR(20) NULL,
    event_day VARCHAR(100) NULL,
    event_date DATE NULL,
    event_time VARCHAR(50) NULL,
    event_location VARCHAR(150) NULL,
    event_speaker VARCHAR(100) NULL,
    event_capacity VARCHAR(20) NULL,
    reward_image VARCHAR(255) NULL,
    flyer_path VARCHAR(255) NULL,
    meta_pixel_id VARCHAR(50) NULL,
    ga_measurement_id VARCHAR(30) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_events_brand FOREIGN KEY (brand_id) REFERENCES brands(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS event_rewards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_slug VARCHAR(60) NOT NULL,
    rank TINYINT UNSIGNED NOT NULL,
    reward_text VARCHAR(255) NOT NULL,
    UNIQUE KEY uniq_event_rank (event_slug, rank)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS referrers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand_id INT NOT NULL,
    ref_code VARCHAR(20) NOT NULL,
    event_slug VARCHAR(60) NOT NULL DEFAULT 'default',
    name VARCHAR(100) NOT NULL,
    whatsapp VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_brand_event_ref (brand_id, event_slug, ref_code),
    INDEX idx_referrers_event (event_slug),
    CONSTRAINT fk_referrers_brand FOREIGN KEY (brand_id) REFERENCES brands(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    whatsapp VARCHAR(20) NOT NULL,
    kota VARCHAR(100) NOT NULL,
    ref_code VARCHAR(20) NULL,
    event_slug VARCHAR(60) NOT NULL DEFAULT 'default',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ref_code (ref_code),
    INDEX idx_created_at (created_at),
    INDEX idx_leads_event (event_slug),
    INDEX idx_leads_brand (brand_id),
    CONSTRAINT fk_leads_brand FOREIGN KEY (brand_id) REFERENCES brands(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip (ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Jika file ini terlanjur di-import ke database yang sudah punya tabel lama,
-- CREATE TABLE IF NOT EXISTS tidak akan mengubah struktur tabel tersebut.
-- Baris ALTER berikut membuat import ulang tetap bisa melengkapi kolom v8.
ALTER TABLE events ADD COLUMN IF NOT EXISTS brand_id INT NULL AFTER id;
ALTER TABLE events ADD COLUMN IF NOT EXISTS reward_image VARCHAR(255) NULL AFTER event_capacity;
ALTER TABLE events ADD COLUMN IF NOT EXISTS meta_pixel_id VARCHAR(50) NULL AFTER reward_image;
ALTER TABLE events ADD COLUMN IF NOT EXISTS ga_measurement_id VARCHAR(30) NULL AFTER meta_pixel_id;
ALTER TABLE events ADD COLUMN IF NOT EXISTS flyer_path VARCHAR(255) NULL AFTER reward_image;
ALTER TABLE events ADD COLUMN IF NOT EXISTS event_date DATE NULL AFTER event_day;
ALTER TABLE referrers ADD COLUMN IF NOT EXISTS brand_id INT NULL AFTER id;
ALTER TABLE referrers ADD COLUMN IF NOT EXISTS event_slug VARCHAR(60) NOT NULL DEFAULT 'default' AFTER ref_code;
ALTER TABLE leads ADD COLUMN IF NOT EXISTS brand_id INT NULL AFTER id;
ALTER TABLE leads ADD COLUMN IF NOT EXISTS event_slug VARCHAR(60) NOT NULL DEFAULT 'default' AFTER ref_code;

-- Brand awal. Untuk staging, ganti domain menjadi staging.rahasiaemas.id.
-- Hash password default sama seperti config.example.php dan WAJIB diganti
-- setelah instalasi lewat config.php / setup brand.
INSERT INTO brands (
    id, slug, domain, name, whatsapp_default, theme_preset,
    admin_username, admin_password_hash, default_event_slug, status
)
VALUES (
    1,
    'rahasiaemas',
    'rahasiaemas.id',
    'rahasiaemas.id',
    '628111111111',
    'gold',
    'admin',
    '$2y$12$iUeNUsTjuTdSG8uekn4OguWiD9GsNGxrSQQP/5PoITp/3UwRLq0Ja',
    'default',
    'active'
)
ON DUPLICATE KEY UPDATE id = id;

UPDATE events SET brand_id = 1 WHERE brand_id IS NULL;
UPDATE referrers SET brand_id = 1 WHERE brand_id IS NULL;
UPDATE leads SET brand_id = 1 WHERE brand_id IS NULL;

-- Event "default" — mewakili landing page utama (index.php) di root domain.
INSERT INTO events (brand_id, slug, name, status, whatsapp_default, event_day, event_time, event_location, event_speaker, event_capacity)
VALUES (1, 'default', 'Rahasia Emas — Acara Utama', 'active', '628111111111', 'Jumat, 25 Juli 2026', '19.30 WIB', 'Online via Zoom (link dikirim via WhatsApp)', 'Coach Arifin', '100')
ON DUPLICATE KEY UPDATE slug = slug;

-- Referrer default / "induk" — dipakai kalau seseorang membuka domain TANPA
-- kode referral. Ganti nomor WhatsApp dengan nomor WA Coach Arifin sendiri.
INSERT INTO referrers (brand_id, ref_code, event_slug, name, whatsapp)
VALUES (1, 'admin', 'default', 'Tim rahasiaemas.id', '628111111111')
ON DUPLICATE KEY UPDATE ref_code = ref_code;
