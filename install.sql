-- ============================================================
-- rahasiaemas.id — Skema Database
-- Jalankan file ini SEKALI lewat phpMyAdmin (Import) di hosting Anda
-- ============================================================

CREATE TABLE IF NOT EXISTS referrers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ref_code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    whatsapp VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ref_code (ref_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    whatsapp VARCHAR(20) NOT NULL,
    kota VARCHAR(100) NOT NULL,
    ref_code VARCHAR(20) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ref_code (ref_code),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS event_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    event_day VARCHAR(100) NOT NULL,
    event_time VARCHAR(100) NOT NULL,
    event_location VARCHAR(255) NOT NULL,
    event_speaker VARCHAR(100) NOT NULL,
    event_capacity VARCHAR(20) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Referrer default / "induk" — dipakai kalau seseorang membuka
-- rahasiaemas.id TANPA kode referral (akses langsung).
-- GANTI nomor WhatsApp di bawah ini dengan nomor WA Coach Arifin sendiri.
INSERT INTO referrers (ref_code, name, whatsapp)
VALUES ('admin', 'Tim rahasiaemas.id', '628111111111')
ON DUPLICATE KEY UPDATE ref_code = ref_code;

INSERT INTO event_settings (id, event_day, event_time, event_location, event_speaker, event_capacity)
VALUES (1, 'Jumat, 25 Juli 2026', '19.30 WIB', 'Online via Zoom (link dikirim via WhatsApp)', 'Coach Arifin', '100')
ON DUPLICATE KEY UPDATE id = id;
