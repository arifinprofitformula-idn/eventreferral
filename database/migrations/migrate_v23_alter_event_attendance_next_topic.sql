-- Field tambahan di form Konfirmasi Kehadiran (/hadir/{slug}): "Tema Apa yang ingin Anda
-- Pelajari berikutnya?". Jalankan sekali di database production via phpMyAdmin/CLI.
-- SETELAH migrate_v22_alter_event_attendance_extra_fields.sql.
--
-- Disimpan di event_attendance (bukan leads), dengan alasan sama seperti feedback_notes:
-- nilai ini spesifik per KEHADIRAN di satu event tertentu, bukan atribut permanen pendaftar.

ALTER TABLE event_attendance
    ADD COLUMN next_topic_interest TEXT NULL AFTER feedback_notes;
