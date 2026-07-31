-- Fitur "Undian Kehadiran" - hasil pemenang.
-- Status 'voided' dipakai untuk undo tanpa menghapus audit trail.

CREATE TABLE IF NOT EXISTS lucky_draw_winners (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id BIGINT UNSIGNED NOT NULL,
    event_id INT NOT NULL,
    registrant_id INT NOT NULL,
    attendance_id INT NOT NULL,
    prize_name VARCHAR(190) NOT NULL,
    drawn_at DATETIME NOT NULL,
    status ENUM('confirmed','voided') NOT NULL DEFAULT 'confirmed',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_lucky_draw_winners_event (event_id),
    INDEX idx_lucky_draw_winners_registrant (registrant_id),
    INDEX idx_lucky_draw_winners_status (status),
    INDEX idx_lucky_draw_winners_event_registrant_status (event_id, registrant_id, status),
    CONSTRAINT fk_lucky_draw_winners_session FOREIGN KEY (session_id) REFERENCES lucky_draw_sessions(id),
    CONSTRAINT fk_lucky_draw_winners_event FOREIGN KEY (event_id) REFERENCES events(id),
    CONSTRAINT fk_lucky_draw_winners_registrant FOREIGN KEY (registrant_id) REFERENCES leads(id),
    CONSTRAINT fk_lucky_draw_winners_attendance FOREIGN KEY (attendance_id) REFERENCES event_attendance(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
