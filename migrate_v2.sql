-- ============================================================
-- rahasiaemas.id — MIGRASI ke v2 (Multi-Event + Challenge)
-- HANYA jalankan file ini jika Anda SUDAH punya instalasi v1
-- (sudah pernah import install.sql versi pertama sebelumnya).
--
-- Jika ini instalasi BARU (belum pernah install sama sekali),
-- JANGAN pakai file ini — langsung import install.sql saja,
-- karena install.sql versi ini sudah termasuk semua perubahan v2.
-- ============================================================

-- 1. Tabel events — mendata setiap landing page/acara
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
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Daftarkan event "default" — mewakili landing page utama (index.php) yang sudah ada
INSERT INTO events (slug, name, status, whatsapp_default, event_day, event_time, event_location, event_speaker, event_capacity)
SELECT 'default', 'Rahasia Emas — Acara Utama', 'active', whatsapp, '', '', '', '', ''
FROM referrers WHERE ref_code = 'admin'
ON DUPLICATE KEY UPDATE slug = slug;

-- Jika baris 'admin' di referrers tidak ditemukan (jarang terjadi), pastikan event default tetap ada:
INSERT INTO events (slug, name, status, whatsapp_default)
VALUES ('default', 'Rahasia Emas — Acara Utama', 'active', '628111111111')
ON DUPLICATE KEY UPDATE slug = slug;

-- 3. Tambah kolom event_slug ke referrers & leads (aman, tidak menghapus data lama)
ALTER TABLE referrers ADD COLUMN IF NOT EXISTS event_slug VARCHAR(60) NOT NULL DEFAULT 'default' AFTER ref_code;
ALTER TABLE leads ADD COLUMN IF NOT EXISTS event_slug VARCHAR(60) NOT NULL DEFAULT 'default' AFTER ref_code;

-- 4. Ganti unique index ref_code (dulu unik global) menjadi unik PER EVENT
--    Ini penting agar event berbeda boleh punya ref_code yang sama.
ALTER TABLE referrers DROP INDEX ref_code;
ALTER TABLE referrers ADD UNIQUE KEY uniq_event_ref (event_slug, ref_code);
ALTER TABLE referrers ADD INDEX idx_referrers_event (event_slug);
ALTER TABLE leads ADD INDEX idx_leads_event (event_slug);

-- Selesai. Semua data lama otomatis tergolong ke event_slug = 'default'.
