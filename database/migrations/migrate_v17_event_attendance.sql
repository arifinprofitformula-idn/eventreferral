-- Fitur "Kehadiran Event" (check-in QR / manual admin).
-- Jalankan sekali di database production via phpMyAdmin/CLI.
--
-- Tidak mengubah tabel lama (leads, referrers, events, admin_users) sama sekali.
-- Registrant lama (leads) yang belum punya baris event_attendance TETAP tampil normal
-- di leaderboard/dashboard existing — mereka hanya dianggap "qr_token belum di-generate"
-- sampai baris attendance-nya dibuat (lewat migrasi backfill terpisah jika dibutuhkan,
-- atau otomatis untuk pendaftar baru lewat hook di api/submit_lead.php).

CREATE TABLE IF NOT EXISTS event_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    registrant_id INT NOT NULL,
    referral_id INT NULL,
    session_id INT NULL COMMENT 'Reserved untuk event multi-sesi di masa depan, belum terhubung ke tabel manapun',
    attendance_status ENUM('hadir','tidak_hadir','terlambat','batal') NOT NULL DEFAULT 'tidak_hadir',
    check_in_time DATETIME NULL,
    check_in_method ENUM('qr_scan','manual_admin','self_checkin') NULL,
    qr_token VARCHAR(64) NOT NULL,
    checked_in_by_admin_id INT NULL,
    is_reward_eligible TINYINT(1) NOT NULL DEFAULT 0,
    duplicate_flag TINYINT(1) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_event_attendance_qr_token (qr_token),
    UNIQUE KEY uniq_event_attendance_registrant (event_id, registrant_id),
    INDEX idx_event_attendance_event (event_id),
    INDEX idx_event_attendance_registrant (registrant_id),
    INDEX idx_event_attendance_checkin_time (check_in_time),
    CONSTRAINT fk_event_attendance_event FOREIGN KEY (event_id) REFERENCES events(id),
    CONSTRAINT fk_event_attendance_registrant FOREIGN KEY (registrant_id) REFERENCES leads(id),
    CONSTRAINT fk_event_attendance_referral FOREIGN KEY (referral_id) REFERENCES referrers(id) ON DELETE SET NULL,
    CONSTRAINT fk_event_attendance_admin FOREIGN KEY (checked_in_by_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
