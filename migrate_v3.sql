-- ============================================================
-- rahasiaemas.id — MIGRASI ke v3 (Hadiah Challenge per Event)
-- HANYA jalankan file ini jika Anda SUDAH menjalankan migrate_v2.sql
-- (sudah punya tabel `events`, kolom `event_slug` di referrers/leads).
--
-- Jika ini instalasi BARU (belum pernah install sama sekali),
-- JANGAN pakai file ini — langsung import install.sql saja,
-- karena install.sql versi ini sudah termasuk semua perubahan v3.
-- ============================================================

-- 1. Kolom untuk gambar poster/infografis hadiah per event
ALTER TABLE events ADD COLUMN IF NOT EXISTS reward_image VARCHAR(255) NULL AFTER event_capacity;

-- 2. Tabel hadiah per peringkat (1-10), opsional per event
CREATE TABLE IF NOT EXISTS event_rewards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_slug VARCHAR(60) NOT NULL,
    rank TINYINT UNSIGNED NOT NULL,
    reward_text VARCHAR(255) NOT NULL,
    UNIQUE KEY uniq_event_rank (event_slug, rank)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Selesai.
