-- ============================================================
-- rahasiaemas.id — MIGRASI ke v6 (Satukan Pengaturan Acara ke tabel events)
-- HANYA jalankan file ini jika Anda SUDAH menjalankan migrate_v2.sql
-- s.d. migrate_v5.sql.
--
-- Jika ini instalasi BARU (belum pernah install sama sekali),
-- JANGAN pakai file ini — langsung import install.sql saja,
-- karena install.sql versi ini sudah termasuk semua perubahan v6.
-- ============================================================

-- 1. Pindahkan data lama dari event_settings (kalau ada) ke event "default"
UPDATE events e
JOIN event_settings s ON s.id = 1
SET e.event_day = s.event_day,
    e.event_time = s.event_time,
    e.event_location = s.event_location,
    e.event_speaker = s.event_speaker,
    e.event_capacity = s.event_capacity
WHERE e.slug = 'default';

-- 2. Hapus tabel lama — Pengaturan Acara sekarang diatur per-event
--    lewat admin/events.php -> admin/event-settings.php, disimpan di tabel events.
DROP TABLE IF EXISTS event_settings;

-- Selesai.
