-- Field tambahan di form Konfirmasi Kehadiran (/hadir/{slug}): sumber informasi, status
-- peserta, dan feedback event. Jalankan sekali di database production via phpMyAdmin/CLI.
-- SETELAH migrate_v18_alter_event_attendance_self_confirm.sql.
--
-- Disimpan di event_attendance (bukan leads), karena nilai-nilai ini spesifik per
-- KEHADIRAN di satu event tertentu, bukan atribut permanen si pendaftar (satu orang
-- bisa hadir di beberapa event dengan sumber info / status berbeda-beda tiap kali).

ALTER TABLE event_attendance
    ADD COLUMN info_source ENUM('media_sosial','landing_page','group_whatsapp','referensi') NULL AFTER confirmation_code_valid,
    ADD COLUMN participant_status ENUM('umum','epi_store','epi_channel','silverchannel') NULL AFTER info_source,
    ADD COLUMN feedback_notes TEXT NULL AFTER participant_status;
