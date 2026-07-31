-- Fitur "Undian Kehadiran" - sesi undian.
-- Jalankan setelah migrate_v17/v18 event_attendance.

CREATE TABLE IF NOT EXISTS lucky_draw_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    prize_name VARCHAR(190) NOT NULL,
    winners_count INT NOT NULL DEFAULT 1,
    duration_seconds INT NOT NULL,
    status ENUM('drawing','revealed','confirmed','voided') NOT NULL DEFAULT 'drawing',
    draw_started_at DATETIME NOT NULL,
    reveal_at DATETIME NOT NULL,
    created_by_admin_id BIGINT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_lucky_draw_sessions_event (event_id),
    INDEX idx_lucky_draw_sessions_status (status),
    INDEX idx_lucky_draw_sessions_event_status (event_id, status),
    CONSTRAINT fk_lucky_draw_sessions_event FOREIGN KEY (event_id) REFERENCES events(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
