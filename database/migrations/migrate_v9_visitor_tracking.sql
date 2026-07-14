-- ============================================================
-- migrate_v9_visitor_tracking.sql
-- Menambahkan tabel visitor_events untuk tracking first-party
-- (pageview, scroll depth, form start/submit, cta click, redirect WA)
-- tanpa bergantung pada Meta Pixel / Google Analytics.
--
-- Jalankan SEKALI lewat phpMyAdmin (Import) atau mysql CLI.
-- ============================================================

CREATE TABLE IF NOT EXISTS visitor_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    brand_id INT NULL,                           -- NULL kalau resolusi brand gagal — tracking tetap jalan
    event_slug VARCHAR(60) NULL,
    session_id CHAR(36) NOT NULL,                -- UUID v4, digenerate di client, disimpan di localStorage
    event_type ENUM(
        'pageview', 'scroll_50', 'scroll_90',
        'form_start', 'form_submit', 'cta_click', 'whatsapp_redirect'
    ) NOT NULL,
    page_path VARCHAR(255) NOT NULL,
    referrer_url VARCHAR(500) NULL,
    utm_source VARCHAR(100) NULL,
    utm_medium VARCHAR(100) NULL,
    utm_campaign VARCHAR(100) NULL,
    device_type ENUM('mobile', 'tablet', 'desktop') NOT NULL DEFAULT 'desktop',
    ref_code VARCHAR(20) NULL,
    ip_hash CHAR(64) NULL,                       -- SHA-256(IP + IP_SALT) — bukan IP asli
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_brand_date (brand_id, created_at),
    INDEX idx_event_slug (event_slug),
    INDEX idx_session (session_id),
    INDEX idx_event_type (event_type),
    CONSTRAINT fk_visitor_events_brand FOREIGN KEY (brand_id) REFERENCES brands(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
