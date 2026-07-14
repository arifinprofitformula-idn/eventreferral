-- Mailketing integration schema
-- Jalankan sekali di database production via phpMyAdmin/CLI.

CREATE TABLE IF NOT EXISTS event_email_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand_id INT NOT NULL,
    event_slug VARCHAR(60) NOT NULL,
    subject VARCHAR(200) NOT NULL DEFAULT '',
    body_content TEXT NOT NULL,
    invitation_link VARCHAR(500) NULL,
    cta_text VARCHAR(100) NOT NULL DEFAULT 'Gabung ke Acara Sekarang',
    mailketing_list_id VARCHAR(20) NULL,
    auto_send TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_event_email (brand_id, event_slug),
    CONSTRAINT fk_email_settings_brand FOREIGN KEY (brand_id) REFERENCES brands(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE brands ADD COLUMN IF NOT EXISTS sender_name VARCHAR(150) NULL AFTER disclaimer_text;
ALTER TABLE brands ADD COLUMN IF NOT EXISTS sender_email VARCHAR(150) NULL AFTER sender_name;
