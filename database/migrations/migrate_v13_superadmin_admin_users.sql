-- ============================================================
-- rahasiaemas.id — MIGRASI v13 (Superadmin + Multi Admin User)
-- ADDITIVE ONLY — menambah tabel admin_users tanpa menghapus login lama.
-- Aman dijalankan berkali-kali.
-- ============================================================

CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand_id INT NULL,
    username VARCHAR(60) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(150) NULL,
    email VARCHAR(150) NULL,
    role ENUM('superadmin','admin') NOT NULL DEFAULT 'admin',
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_admin_users_username (username),
    INDEX idx_admin_users_brand (brand_id),
    INDEX idx_admin_users_role_status (role, status),
    CONSTRAINT fk_admin_users_brand FOREIGN KEY (brand_id) REFERENCES brands(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bootstrap superadmin awal dari admin brand pertama yang aktif.
-- Setelah login, superadmin bisa membuat user admin lain dari /admin/admin-users.php.
INSERT INTO admin_users (brand_id, username, password_hash, name, role, status)
SELECT NULL, b.admin_username, b.admin_password_hash, CONCAT('Superadmin ', b.name), 'superadmin', 'active'
FROM brands b
WHERE b.status = 'active'
  AND b.id = (SELECT MIN(id) FROM brands WHERE status = 'active')
ON DUPLICATE KEY UPDATE username = username;

-- Selesai.
