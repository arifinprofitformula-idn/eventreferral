-- ============================================================
-- rahasiaemas.id — Migrasi v26 LMS Course Builder
-- Metadata course, kategori relasional, tags, instructors, bundles,
-- promo code, dan material upload siap ekspansi.
-- ============================================================

CREATE TABLE IF NOT EXISTS lms_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_lms_category_brand_slug (brand_id, slug),
    CONSTRAINT fk_lms_category_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS lms_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand_id INT NOT NULL,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(80) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_lms_tag_brand_slug (brand_id, slug),
    CONSTRAINT fk_lms_tag_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS lms_course_tags (
    course_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (course_id, tag_id),
    CONSTRAINT fk_lms_ct_course FOREIGN KEY (course_id) REFERENCES lms_courses (id) ON DELETE CASCADE,
    CONSTRAINT fk_lms_ct_tag FOREIGN KEY (tag_id) REFERENCES lms_tags (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS lms_course_instructors (
    course_id INT NOT NULL,
    user_id INT NOT NULL,
    role_label VARCHAR(80) NOT NULL DEFAULT 'Instructor',
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (course_id, user_id),
    CONSTRAINT fk_lms_ci_course FOREIGN KEY (course_id) REFERENCES lms_courses (id) ON DELETE CASCADE,
    CONSTRAINT fk_lms_ci_user FOREIGN KEY (user_id) REFERENCES lms_users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS lms_bundles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand_id INT NOT NULL,
    name VARCHAR(180) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    description TEXT NULL,
    price INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'inactive',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_lms_bundle_brand_slug (brand_id, slug),
    CONSTRAINT fk_lms_bundle_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS lms_bundle_courses (
    bundle_id INT NOT NULL,
    course_id INT NOT NULL,
    PRIMARY KEY (bundle_id, course_id),
    CONSTRAINT fk_lms_bc_bundle FOREIGN KEY (bundle_id) REFERENCES lms_bundles (id) ON DELETE CASCADE,
    CONSTRAINT fk_lms_bc_course FOREIGN KEY (course_id) REFERENCES lms_courses (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS lms_promo_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand_id INT NOT NULL,
    code VARCHAR(50) NOT NULL,
    discount_type ENUM('percent', 'fixed') NOT NULL DEFAULT 'percent',
    discount_value INT UNSIGNED NOT NULL DEFAULT 0,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    usage_limit INT UNSIGNED NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'inactive',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_lms_promo_brand_code (brand_id, code),
    CONSTRAINT fk_lms_promo_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS lms_course_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    material_type ENUM('video', 'pdf', 'quiz') NOT NULL,
    title VARCHAR(200) NOT NULL,
    file_path VARCHAR(255) NULL,
    external_url TEXT NULL,
    quiz_data JSON NULL,
    file_size INT UNSIGNED NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_lms_material_course (course_id, sort_order),
    CONSTRAINT fk_lms_material_course FOREIGN KEY (course_id) REFERENCES lms_courses (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

ALTER TABLE lms_courses
ADD COLUMN IF NOT EXISTS category_id INT NULL AFTER brand_id,
ADD COLUMN IF NOT EXISTS level ENUM(
    'beginner',
    'intermediate',
    'advanced'
) NOT NULL DEFAULT 'beginner' AFTER price,
ADD COLUMN IF NOT EXISTS duration_minutes INT UNSIGNED NOT NULL DEFAULT 0 AFTER level,
ADD COLUMN IF NOT EXISTS published_at DATETIME NULL AFTER status,
ADD INDEX IF NOT EXISTS idx_lms_courses_category (category_id),
ADD CONSTRAINT fk_lms_courses_category FOREIGN KEY (category_id) REFERENCES lms_categories (id) ON DELETE SET NULL;

INSERT INTO
    lms_categories (brand_id, name, slug)
SELECT b.id, seed.name, seed.slug
FROM brands b
    CROSS JOIN (
        SELECT 'Investasi Emas' AS name, 'investasi-emas' AS slug
        UNION ALL
        SELECT 'Keuangan Pribadi', 'keuangan-pribadi'
        UNION ALL
        SELECT 'Bisnis & Referral', 'bisnis-referral'
        UNION ALL
        SELECT 'Mindset & Produktivitas', 'mindset-produktivitas'
    ) seed
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    status = 'active';