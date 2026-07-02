-- ============================================================
-- rahasiaemas.id — Skema Database (v2 — Multi-Event + Challenge)
-- Jalankan file ini SEKALI lewat phpMyAdmin (Import) di hosting Anda
-- untuk INSTALASI BARU. Jika Anda sudah punya instalasi v1 yang berjalan,
-- pakai migrate_v2.sql, JANGAN file ini.
-- ============================================================

CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(60) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    status ENUM('active','archived') NOT NULL DEFAULT 'active',
    whatsapp_default VARCHAR(20) NULL,
    event_day VARCHAR(100) NULL,
    event_time VARCHAR(50) NULL,
    event_location VARCHAR(150) NULL,
    event_speaker VARCHAR(100) NULL,
    event_capacity VARCHAR(20) NULL,
    reward_image VARCHAR(255) NULL,
    meta_pixel_id VARCHAR(50) NULL,
    ga_measurement_id VARCHAR(30) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
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
    ref_code VARCHAR(20) NOT NULL,
    event_slug VARCHAR(60) NOT NULL DEFAULT 'default',
    name VARCHAR(100) NOT NULL,
    whatsapp VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_event_ref (event_slug, ref_code),
    INDEX idx_referrers_event (event_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    whatsapp VARCHAR(20) NOT NULL,
    kota VARCHAR(100) NOT NULL,
    ref_code VARCHAR(20) NULL,
    event_slug VARCHAR(60) NOT NULL DEFAULT 'default',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ref_code (ref_code),
    INDEX idx_created_at (created_at),
    INDEX idx_leads_event (event_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip (ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Event "default" — mewakili landing page utama (index.php) di root domain.
INSERT INTO events (slug, name, status, whatsapp_default, event_day, event_time, event_location, event_speaker, event_capacity)
VALUES ('default', 'Rahasia Emas — Acara Utama', 'active', '628111111111', 'Jumat, 25 Juli 2026', '19.30 WIB', 'Online via Zoom (link dikirim via WhatsApp)', 'Coach Arifin', '100')
ON DUPLICATE KEY UPDATE slug = slug;

-- Referrer default / "induk" — dipakai kalau seseorang membuka
-- rahasiaemas.id TANPA kode referral (akses langsung).
-- GANTI nomor WhatsApp di bawah ini dengan nomor WA Coach Arifin sendiri.
INSERT INTO referrers (ref_code, event_slug, name, whatsapp)
VALUES ('admin', 'default', 'Tim rahasiaemas.id', '628111111111')
ON DUPLICATE KEY UPDATE ref_code = ref_code;
