-- ============================================================
-- rahasiaemas.id — MIGRASI ke v5 (Login Admin: Username+Password + Rate-Limiting)
-- HANYA jalankan file ini jika Anda SUDAH menjalankan migrate_v2.sql,
-- migrate_v3.sql, dan migrate_v4.sql.
--
-- Jika ini instalasi BARU (belum pernah install sama sekali),
-- JANGAN pakai file ini — langsung import install.sql saja,
-- karena install.sql versi ini sudah termasuk semua perubahan v5.
--
-- SETELAH menjalankan file ini, Anda WAJIB juga:
-- 1. Buka admin/generate-password-hash.php untuk membuat hash password baru.
-- 2. Ganti ADMIN_PIN di config.php menjadi ADMIN_USERNAME + ADMIN_PASSWORD_HASH
--    (lihat config.example.php untuk contoh formatnya).
-- 3. Hapus admin/generate-password-hash.php dari server setelah selesai.
-- ============================================================

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip (ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Selesai.
