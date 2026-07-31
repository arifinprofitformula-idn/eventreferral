-- Rate limiting untuk endpoint publik /api/attendance-confirm.php (cegah spam submit).
-- Jalankan sekali di database production via phpMyAdmin/CLI.
--
-- Tabel baru, tidak diminta eksplisit di daftar file task, tapi dibutuhkan untuk memenuhi
-- ATURAN KEAMANAN "Rate limit endpoint attendance-confirm.php per nomor HP/IP".
-- IP disimpan dalam bentuk HASH (ip_hash), memakai IP_SALT yang sama dengan
-- visitor_events.ip_hash (migrate_v9) — konsisten dengan konvensi yang sudah ada,
-- bukan menyimpan IP mentah.

CREATE TABLE IF NOT EXISTS attendance_rate_limit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_hash VARCHAR(64) NOT NULL,
    whatsapp VARCHAR(20) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_attendance_rate_limit_ip (ip_hash, created_at),
    INDEX idx_attendance_rate_limit_wa (whatsapp, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
