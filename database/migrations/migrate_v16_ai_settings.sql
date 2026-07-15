-- AI provider settings (global, dikelola superadmin lewat admin/ai-settings.php)
-- Jalankan sekali di database production via phpMyAdmin/CLI.

CREATE TABLE IF NOT EXISTS ai_settings (
    id INT PRIMARY KEY,
    provider VARCHAR(30) NOT NULL DEFAULT 'groq',
    api_key VARCHAR(255) NOT NULL DEFAULT '',
    model VARCHAR(100) NOT NULL DEFAULT '',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO ai_settings (id, provider, api_key, model) VALUES (1, 'sumopod', '', 'gpt-4o-mini');
