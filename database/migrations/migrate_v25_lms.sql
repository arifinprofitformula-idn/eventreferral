-- ============================================================
-- rahasiaemas.id — Migrasi v25 Simple LMS & Role Management
-- Tabel modul: lms_users, lms_roles, lms_user_roles,
-- lms_courses, lms_modules, lms_lessons, lms_enrollments,
-- lms_lesson_progress, lms_orders, lms_role_logs, lms_notifications
-- ============================================================

CREATE TABLE IF NOT EXISTS lms_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

INSERT INTO
    lms_roles (name, label, description)
VALUES (
        'free',
        'User Free',
        'Akses gratis ke materi eCourse free'
    ),
    (
        'paid',
        'Paid User',
        'Akses penuh ke eCourse premium dan fitur lanjutan'
    ),
    (
        'instructor',
        'Instruktur',
        'Pengajar pembuat konten materi'
    ),
    (
        'moderator',
        'Moderator',
        'Moderator diskusi dan komunitas'
    ),
    (
        'admin',
        'Admin LMS',
        'Pengelola penuh modul eCourse dan user'
    )
ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    description = VALUES(description);

CREATE TABLE IF NOT EXISTS lms_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand_id INT NOT NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL,
    whatsapp VARCHAR(25) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    primary_role ENUM('free', 'paid', 'admin') NOT NULL DEFAULT 'free',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_lms_users_brand_email (brand_id, email),
    INDEX idx_lms_users_brand (brand_id),
    INDEX idx_lms_users_role (primary_role),
    CONSTRAINT fk_lms_users_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS lms_user_roles (
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_lms_ur_user FOREIGN KEY (user_id) REFERENCES lms_users (id) ON DELETE CASCADE,
    CONSTRAINT fk_lms_ur_role FOREIGN KEY (role_id) REFERENCES lms_roles (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS lms_courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    summary VARCHAR(300) NULL,
    description TEXT NULL,
    badge_label VARCHAR(60) NULL,
    access_type ENUM('free', 'premium') NOT NULL DEFAULT 'free',
    price INT UNSIGNED NOT NULL DEFAULT 0,
    cover_image VARCHAR(255) NULL,
    certificate_enabled TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active', 'draft', 'archived') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_lms_courses_brand_slug (brand_id, slug),
    INDEX idx_lms_courses_brand_status (brand_id, status),
    CONSTRAINT fk_lms_courses_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS lms_modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_lms_modules_course (course_id, sort_order),
    CONSTRAINT fk_lms_modules_course FOREIGN KEY (course_id) REFERENCES lms_courses (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS lms_lessons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    module_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    content_type ENUM('video', 'pdf', 'quiz') NOT NULL DEFAULT 'video',
    content_url TEXT NULL,
    body_text TEXT NULL,
    quiz_data JSON NULL,
    is_premium TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    duration_minutes INT NOT NULL DEFAULT 5,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_lms_lessons_course (course_id),
    INDEX idx_lms_lessons_module (module_id, sort_order),
    CONSTRAINT fk_lms_lessons_course FOREIGN KEY (course_id) REFERENCES lms_courses (id) ON DELETE CASCADE,
    CONSTRAINT fk_lms_lessons_module FOREIGN KEY (module_id) REFERENCES lms_modules (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS lms_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand_id INT NOT NULL,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    access_status ENUM('active', 'expired') NOT NULL DEFAULT 'active',
    expires_at DATETIME NULL,
    enrolled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_lms_enrollment_user_course (user_id, course_id),
    INDEX idx_lms_enrollments_brand (brand_id),
    CONSTRAINT fk_lms_enr_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE CASCADE,
    CONSTRAINT fk_lms_enr_user FOREIGN KEY (user_id) REFERENCES lms_users (id) ON DELETE CASCADE,
    CONSTRAINT fk_lms_enr_course FOREIGN KEY (course_id) REFERENCES lms_courses (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS lms_lesson_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    lesson_id INT NOT NULL,
    completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_lms_progress_user_lesson (user_id, lesson_id),
    INDEX idx_lms_progress_course (user_id, course_id),
    CONSTRAINT fk_lms_prog_user FOREIGN KEY (user_id) REFERENCES lms_users (id) ON DELETE CASCADE,
    CONSTRAINT fk_lms_prog_course FOREIGN KEY (course_id) REFERENCES lms_courses (id) ON DELETE CASCADE,
    CONSTRAINT fk_lms_prog_lesson FOREIGN KEY (lesson_id) REFERENCES lms_lessons (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS lms_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand_id INT NOT NULL,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    amount INT UNSIGNED NOT NULL DEFAULT 0,
    payment_method VARCHAR(50) NOT NULL DEFAULT 'manual_simulator',
    payment_status ENUM('pending', 'paid', 'failed') NOT NULL DEFAULT 'pending',
    paid_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_lms_orders_user (user_id),
    INDEX idx_lms_orders_brand (brand_id),
    CONSTRAINT fk_lms_orders_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE CASCADE,
    CONSTRAINT fk_lms_orders_user FOREIGN KEY (user_id) REFERENCES lms_users (id) ON DELETE CASCADE,
    CONSTRAINT fk_lms_orders_course FOREIGN KEY (course_id) REFERENCES lms_courses (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS lms_role_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    old_role VARCHAR(50) NOT NULL,
    new_role VARCHAR(50) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_lms_role_logs_user (user_id),
    CONSTRAINT fk_lms_rlog_user FOREIGN KEY (user_id) REFERENCES lms_users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS lms_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_lms_notif_user (user_id, is_read),
    CONSTRAINT fk_lms_notif_user FOREIGN KEY (user_id) REFERENCES lms_users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;