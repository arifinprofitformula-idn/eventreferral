-- Fitur "Konfirmasi Kehadiran Event" (self-service, menggantikan Google Form).
-- Jalankan sekali di database production via phpMyAdmin/CLI.
-- SETELAH migrate_v17_event_attendance.sql (tabel event_attendance harus sudah ada).
--
-- Kenapa ALTER, bukan CREATE TABLE baru: tabel event_attendance sudah dibuat di
-- migrate_v17 untuk fitur check-in admin (scan QR / manual). Kolom yang dibutuhkan
-- fitur self-service ini sebagian besar SUDAH ADA dan dipakai ulang, supaya satu
-- baris attendance = satu sumber kebenaran kehadiran, apapun cara check-in-nya:
--   - confirmed_at            -> pakai kolom check_in_time yang sudah ada
--   - metode konfirmasi diri  -> pakai nilai check_in_method = 'self_checkin' yang
--                                sudah ada di ENUM tapi belum pernah dipakai
--   - duplicate_flag, referral_id, registrant_id, event_id -> sudah ada, dipakai ulang
-- Kolom yang BENAR-BENAR baru cuma 2: attendance_source dan confirmation_code_valid.

ALTER TABLE event_attendance
    ADD COLUMN attendance_source ENUM('terdaftar','tidak_terdaftar') NULL AFTER referral_id,
    ADD COLUMN confirmation_code_valid TINYINT(1) NOT NULL DEFAULT 0 AFTER check_in_method;
