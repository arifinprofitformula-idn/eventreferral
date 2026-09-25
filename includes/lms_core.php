<?php
/**
 * includes/lms_core.php
 * Core modular LMS: RBAC, auto-migration, courses, progress, checkout sync, notifications.
 */

if (!function_exists('lms_add_column_if_missing')) {
    function lms_add_column_if_missing(PDO $pdo, string $table, string $column, string $alterSql): void {
        try {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
            $stmt->execute([$column]);
            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                $pdo->exec($alterSql);
            }
        } catch (Throwable $e) {
            error_log('[LMS] Gagal ensure column ' . $table . '.' . $column . ': ' . $e->getMessage());
        }
    }
}

if (!function_exists('lms_ensure_schema')) {
    function lms_ensure_schema(PDO $pdo): void {
        static $checked = false;
        if ($checked) {
            return;
        }

        $tables = [
            'lms_roles',
            'lms_users',
            'lms_user_roles',
            'lms_courses',
            'lms_modules',
            'lms_lessons',
            'lms_enrollments',
            'lms_lesson_progress',
            'lms_orders',
            'lms_role_logs',
            'lms_notifications',
        ];

        $missing = false;
        foreach ($tables as $tbl) {
            if (!table_exists($pdo, $tbl)) {
                $missing = true;
                break;
            }
        }

        if ($missing) {
            $sqlFile = __DIR__ . '/../database/migrations/migrate_v25_lms.sql';
            if (is_file($sqlFile)) {
                $sql = file_get_contents($sqlFile);
                if ($sql !== false) {
                    $pdo->exec($sql);
                }
            }
        }

        if (!table_exists($pdo, 'lms_payment_settings')) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS lms_payment_settings (
                    brand_id INT NOT NULL PRIMARY KEY,
                    active_method ENUM('bank_transfer', 'midtrans') NOT NULL DEFAULT 'bank_transfer',
                    bank_transfer_enabled TINYINT(1) NOT NULL DEFAULT 1,
                    bank_code VARCHAR(40) NULL,
                    bank_name VARCHAR(120) NULL,
                    bank_logo_path VARCHAR(255) NULL,
                    bank_account_number VARCHAR(80) NULL,
                    bank_account_name VARCHAR(150) NULL,
                    bank_instructions TEXT NULL,
                    admin_whatsapp VARCHAR(25) NULL,
                    midtrans_enabled TINYINT(1) NOT NULL DEFAULT 0,
                    midtrans_environment ENUM('sandbox', 'production') NOT NULL DEFAULT 'sandbox',
                    midtrans_server_key VARCHAR(255) NULL,
                    midtrans_client_key VARCHAR(255) NULL,
                    midtrans_merchant_id VARCHAR(100) NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    CONSTRAINT fk_lms_payment_settings_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE CASCADE
                ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4
            ");
        }

        lms_add_column_if_missing($pdo, 'lms_payment_settings', 'bank_code', "ALTER TABLE lms_payment_settings ADD COLUMN bank_code VARCHAR(40) NULL AFTER bank_transfer_enabled");
        lms_add_column_if_missing($pdo, 'lms_payment_settings', 'bank_logo_path', "ALTER TABLE lms_payment_settings ADD COLUMN bank_logo_path VARCHAR(255) NULL AFTER bank_name");
        lms_add_column_if_missing($pdo, 'lms_payment_settings', 'admin_whatsapp', "ALTER TABLE lms_payment_settings ADD COLUMN admin_whatsapp VARCHAR(25) NULL AFTER bank_instructions");
        if (table_exists($pdo, 'lms_categories')) {
            lms_add_column_if_missing($pdo, 'lms_categories', 'description', "ALTER TABLE lms_categories ADD COLUMN description TEXT NULL AFTER slug");
        }
        lms_add_column_if_missing($pdo, 'lms_orders', 'midtrans_transaction_id', "ALTER TABLE lms_orders ADD COLUMN midtrans_transaction_id VARCHAR(120) NULL AFTER payment_method");
        lms_add_column_if_missing($pdo, 'lms_orders', 'payment_reference', "ALTER TABLE lms_orders ADD COLUMN payment_reference VARCHAR(120) NULL AFTER midtrans_transaction_id");
        lms_add_column_if_missing($pdo, 'lms_orders', 'payment_payload', "ALTER TABLE lms_orders ADD COLUMN payment_payload JSON NULL AFTER payment_reference");
        lms_add_column_if_missing($pdo, 'lms_orders', 'payment_proof_path', "ALTER TABLE lms_orders ADD COLUMN payment_proof_path VARCHAR(255) NULL AFTER payment_payload");
        lms_add_column_if_missing($pdo, 'lms_orders', 'payment_proof_uploaded_at', "ALTER TABLE lms_orders ADD COLUMN payment_proof_uploaded_at DATETIME NULL AFTER payment_proof_path");

        $checked = true;
    }
}

if (!function_exists('lms_ensure_course_builder_schema')) {
    function lms_ensure_course_builder_schema(PDO $pdo): void {
        static $checked = false;
        if ($checked) return;

        $hasCategories = table_exists($pdo, 'lms_categories');
        $hasMaterials = table_exists($pdo, 'lms_course_materials');
        $hasMetadata = false;
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM lms_courses LIKE 'category_id'");
            $hasMetadata = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $hasMetadata = false;
        }

        if (!$hasCategories || !$hasMaterials || !$hasMetadata) {
            $sqlFile = __DIR__ . '/../database/migrations/migrate_v26_lms_course_builder.sql';
            $sql = is_file($sqlFile) ? file_get_contents($sqlFile) : false;
            if ($sql === false) {
                throw new RuntimeException('Migrasi LMS Course Builder tidak ditemukan.');
            }
            $pdo->exec($sql);
        }
        $checked = true;
    }
}

if (!function_exists('lms_store_course_upload')) {
    function lms_store_course_upload(array $file, string $type): ?array {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
            throw new RuntimeException('Upload file gagal.');
        }

        $rules = [
            'cover' => ['max' => 5 * 1024 * 1024, 'ext' => ['jpg', 'jpeg', 'png', 'webp'], 'mime' => ['image/jpeg', 'image/png', 'image/webp']],
            'video' => ['max' => 100 * 1024 * 1024, 'ext' => ['mp4', 'webm'], 'mime' => ['video/mp4', 'video/webm']],
            'pdf' => ['max' => 20 * 1024 * 1024, 'ext' => ['pdf'], 'mime' => ['application/pdf']],
        ];
        if (!isset($rules[$type])) throw new InvalidArgumentException('Tipe upload tidak didukung.');

        $rule = $rules[$type];
        if ((int)$file['size'] <= 0 || (int)$file['size'] > $rule['max']) {
            throw new RuntimeException('Ukuran file melampaui batas upload.');
        }
        $extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($file['tmp_name']);
        if (!in_array($extension, $rule['ext'], true) || !in_array($mime, $rule['mime'], true)) {
            throw new RuntimeException('Format file tidak valid.');
        }

        $relativeDir = '/uploads/lms/' . $type;
        $absoluteDir = dirname(__DIR__) . $relativeDir;
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException('Folder upload tidak dapat dibuat.');
        }
        $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], $absoluteDir . '/' . $fileName)) {
            throw new RuntimeException('File gagal disimpan.');
        }
        return ['path' => $relativeDir . '/' . $fileName, 'size' => (int)$file['size']];
    }
}

if (!function_exists('lms_sync_course_tags')) {
    function lms_sync_course_tags(PDO $pdo, int $brandId, int $courseId, string $rawTags): void {
        $pdo->prepare('DELETE FROM lms_course_tags WHERE course_id = ?')->execute([$courseId]);
        $names = array_values(array_unique(array_filter(array_map('trim', explode(',', $rawTags)))));
        foreach (array_slice($names, 0, 20) as $name) {
            if (mb_strlen($name) > 80) continue;
            $slug = slugify($name);
            if ($slug === '') continue;
            $stmt = $pdo->prepare('INSERT INTO lms_tags (brand_id, name, slug) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), id = LAST_INSERT_ID(id)');
            $stmt->execute([$brandId, $name, $slug]);
            $tagId = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT IGNORE INTO lms_course_tags (course_id, tag_id) VALUES (?, ?)')->execute([$courseId, $tagId]);
        }
    }
}

if (!function_exists('lms_seed_default_courses')) {
    function lms_seed_default_courses(PDO $pdo, int $brandId): void {
        lms_ensure_schema($pdo);

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM lms_courses WHERE brand_id = ?');
        $stmt->execute([$brandId]);
        if ((int)$stmt->fetchColumn() > 0) {
            return;
        }

        $courses = [
            [
                'title' => 'Dasar Investasi Emas Fisik & Keamanan Aset',
                'slug' => 'dasar-investasi-emas-fisik',
                'summary' => 'Fondasi aman nabung emas, kenali kadar, simpan anti-risiko, dan kelola portofolio keluarga.',
                'description' => "Panduan praktis langkah demi langkah untuk pemula:\n- Cara hitung spread & waktu terbaik beli\n- Bedakan emas batangan asli vs palsu\n- Skema simpan mandiri vs brankas resmi\n- Mindset aset lindung nilai jangka panjang",
                'badge_label' => 'Free Starter',
                'access_type' => 'free',
                'price' => 0,
                'certificate_enabled' => 0,
                'modules' => [
                    [
                        'title' => 'Modul 1: Mindset & Prinsip Emas Fisik',
                        'lessons' => [
                            [
                                'title' => 'Pelajaran 1: Mengapa Emas Menjaga Daya Beli Anda',
                                'content_type' => 'video',
                                'content_url' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
                                'body_text' => "Simak video singkat tentang inflasi, daya beli mata uang fiat, dan sejarah stabilitas emas fisik lintas dekade.\n\nPoin Kunci:\n1. Emas bukan instrumen cepat kaya, melainkan penjaga nilai.\n2. Alokasikan 10-20% dana menganggur ke aset fisik.\n3. Fokus akumulasi gramasi, bukan spekulasi harian.",
                                'is_premium' => 0,
                                'duration_minutes' => 7,
                            ],
                            [
                                'title' => 'Pelajaran 2: Checklist Keaslian & Sertifikasi',
                                'content_type' => 'pdf',
                                'content_url' => 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf',
                                'body_text' => "Unduh checklist resmi verifikasi cetakan, berat timbangan digital miligram, uji magnet, dan kode barcode certicard pabrikan terpercaya.",
                                'is_premium' => 0,
                                'duration_minutes' => 10,
                            ],
                        ],
                    ],
                    [
                        'title' => 'Modul 2: Evaluasi Pemahaman Dasar',
                        'lessons' => [
                            [
                                'title' => 'Kuis 1: Uji Pemahaman Fondasi Emas',
                                'content_type' => 'quiz',
                                'quiz_data' => [
                                    'passing_score' => 70,
                                    'questions' => [
                                        [
                                            'q' => 'Fungsi utama emas fisik dalam portofolio keuangan adalah...',
                                            'options' => ['Trading kilat harian', 'Lindung nilai (hedging) & penjaga daya beli', 'Menggantikan dana darurat tunai', 'Mengejar dividen bulanan'],
                                            'answer' => 1,
                                            'explanation' => 'Emas fisik berfungsi utama sebagai proteksi kekayaan dari penurunan nilai tukar/inflasi.',
                                        ],
                                        [
                                            'q' => 'Pemeriksaan awal fisik emas yang benar mencakup...',
                                            'options' => ['Hanya warna mengkilap', 'Kesesuaian berat presisi, certicard, dan dimensi resmi', 'Membakar di atas lilin', 'Memotong batangan menjadi dua'],
                                            'answer' => 1,
                                            'explanation' => 'Pemeriksaan standar mengutamakan timbangan miligram presisi, segel certicard utuh, dan sertifikasi resmi.',
                                        ],
                                    ],
                                ],
                                'body_text' => "Kerjakan kuis kilat ini untuk menuntaskan modul starter gratis Anda.",
                                'is_premium' => 0,
                                'duration_minutes' => 6,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Blueprint Cuan Emas 100 Gram Pertama: Strategi Cashflow & Proteksi',
                'slug' => 'blueprint-cuan-emas-100-gram',
                'summary' => 'Akselerasi kepemilikan 100 gram emas murni lewat optimasi cashflow bulanan dan teknik alokasi tanpa mengganggu kebutuhan primer.',
                'description' => "Kurikulum intensif tingkat lanjut untuk Paid User:\n- Framework 5 kantong finansial keluarga\n- Strategi dollar-cost averaging bertingkat saat koreksi pasar\n- Monetisasi jaringan referral & edukasi komisi legal\n- Akses kuis evaluasi komprehensif dan sertifikat kelulusan resmi",
                'badge_label' => 'Premium Accelerator',
                'access_type' => 'premium',
                'price' => 299000,
                'certificate_enabled' => 1,
                'modules' => [
                    [
                        'title' => 'Modul 1: Blueprint Perencanaan & Eksekusi',
                        'lessons' => [
                            [
                                'title' => 'Pelajaran 1: Masterplan 100 Gram dalam 18 Bulan',
                                'content_type' => 'video',
                                'content_url' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
                                'body_text' => "Bedah strategi matematis pemecahan target 100 gram menjadi milestone bulanan, simulasi DCA dinamis, serta manajemen risiko likuiditas darurat.",
                                'is_premium' => 1,
                                'duration_minutes' => 18,
                            ],
                            [
                                'title' => 'Pelajaran 2: Template Spreadsheet Finansial & Kalkulator DCA',
                                'content_type' => 'pdf',
                                'content_url' => 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf',
                                'body_text' => "Panduan rumus spreadsheet alokasi tabungan otomatis untuk disiplin akumulasi emas setiap tanggal gajian.",
                                'is_premium' => 1,
                                'duration_minutes' => 12,
                            ],
                        ],
                    ],
                    [
                        'title' => 'Modul 2: Proteksi Portofolio & Ujian Sertifikasi',
                        'lessons' => [
                            [
                                'title' => 'Ujian Sertifikasi: Akumulasi Aset Emas Berkelanjutan',
                                'content_type' => 'quiz',
                                'quiz_data' => [
                                    'passing_score' => 80,
                                    'questions' => [
                                        [
                                            'q' => 'Metode akumulasi paling minim stres saat harga emas volatil adalah...',
                                            'options' => ['All-in di satu tanggal', 'Dollar-cost averaging bertahap dan konsisten', 'Menunggu harga terendah mutlak', 'Pinjam dana berbunga tinggi'],
                                            'answer' => 1,
                                            'explanation' => 'Dollar-cost averaging menghaluskan harga perolehan rata-rata tanpa perlu menebak titik dasar pasar.',
                                        ],
                                        [
                                            'q' => 'Kapan portofolio emas fisik sebaiknya direalisasikan/dijual?',
                                            'options' => ['Setiap kali naik 1%', 'Hanya saat tujuan keuangan tercapai atau kondisi sangat darurat', 'Setiap akhir pekan', 'Saat ada tren aset gorengan baru'],
                                            'answer' => 1,
                                            'explanation' => 'Emas fisik ditujukan untuk tujuan finansial strategis, bukan transaksi spekulatif jangka pendek.',
                                        ],
                                    ],
                                ],
                                'body_text' => "Selesaikan ujian sertifikasi akhir ini untuk membuka lencana kompetensi dan klaim sertifikat kelulusan digital Anda.",
                                'is_premium' => 1,
                                'duration_minutes' => 15,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($courses as $c) {
            $stmt = $pdo->prepare('
                INSERT INTO lms_courses (brand_id, title, slug, summary, description, badge_label, access_type, price, certificate_enabled, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "active")
            ');
            $stmt->execute([
                $brandId,
                $c['title'],
                $c['slug'],
                $c['summary'],
                $c['description'],
                $c['badge_label'],
                $c['access_type'],
                $c['price'],
                $c['certificate_enabled'],
            ]);
            $courseId = (int)$pdo->lastInsertId();

            $modOrder = 1;
            foreach ($c['modules'] as $m) {
                $stmtMod = $pdo->prepare('INSERT INTO lms_modules (course_id, title, sort_order) VALUES (?, ?, ?)');
                $stmtMod->execute([$courseId, $m['title'], $modOrder++]);
                $moduleId = (int)$pdo->lastInsertId();

                $lesOrder = 1;
                foreach ($m['lessons'] as $les) {
                    $stmtLes = $pdo->prepare('
                        INSERT INTO lms_lessons (course_id, module_id, title, content_type, content_url, body_text, quiz_data, is_premium, sort_order, duration_minutes)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ');
                    $stmtLes->execute([
                        $courseId,
                        $moduleId,
                        $les['title'],
                        $les['content_type'],
                        $les['content_url'] ?? null,
                        $les['body_text'] ?? null,
                        isset($les['quiz_data']) ? json_encode($les['quiz_data']) : null,
                        $les['is_premium'],
                        $lesOrder++,
                        $les['duration_minutes'] ?? 5,
                    ]);
                }
            }
        }
    }
}

// ==== RBAC & SESSION MANAGEMENT ====

if (!function_exists('lms_get_logged_user')) {
    function lms_get_logged_user(PDO $pdo, int $brandId): ?array {
        $userId = (int)($_SESSION['lms_user_id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }

        $stmt = $pdo->prepare('
            SELECT id, brand_id, name, email, whatsapp, primary_role, status, created_at
            FROM lms_users
            WHERE id = ? AND brand_id = ? AND status = "active"
            LIMIT 1
        ');
        $stmt->execute([$userId, $brandId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            unset($_SESSION['lms_user_id'], $_SESSION['lms_user_role'], $_SESSION['lms_user_name'], $_SESSION['lms_user_email'], $_SESSION['lms_roles']);
            return null;
        }

        // Ambil multi-role bila ada di tabel lms_user_roles
        $stmtRoles = $pdo->prepare('
            SELECT r.name
            FROM lms_user_roles ur
            JOIN lms_roles r ON r.id = ur.role_id
            WHERE ur.user_id = ?
        ');
        $stmtRoles->execute([$userId]);
        $roles = $stmtRoles->fetchAll(PDO::FETCH_COLUMN) ?: [];

        if (!in_array($user['primary_role'], $roles, true)) {
            $roles[] = $user['primary_role'];
        }

        // Sinkronkan session agar selalu fresh dengan DB
        $_SESSION['lms_user_id'] = (int)$user['id'];
        $_SESSION['lms_user_role'] = $user['primary_role'];
        $_SESSION['lms_user_name'] = $user['name'];
        $_SESSION['lms_user_email'] = $user['email'];
        $_SESSION['lms_roles'] = array_values(array_unique($roles));

        $user['roles'] = $_SESSION['lms_roles'];
        return $user;
    }
}

if (!function_exists('lms_current_role')) {
    function lms_current_role(?array $user): string {
        if (!$user) {
            return 'guest';
        }
        return $user['primary_role'] ?? 'free';
    }
}

if (!function_exists('lms_has_role')) {
    function lms_has_role(?array $user, array $allowedRoles): bool {
        $role = lms_current_role($user);
        if (in_array($role, $allowedRoles, true)) {
            return true;
        }
        if (!empty($user['roles']) && is_array($user['roles'])) {
            foreach ($allowedRoles as $allowed) {
                if (in_array($allowed, $user['roles'], true)) {
                    return true;
                }
            }
        }
        return false;
    }
}

if (!function_exists('lms_assign_role')) {
    function lms_assign_role(PDO $pdo, int $userId, string $newRole, string $reason = 'manual_update'): bool {
        $validRoles = ['free', 'paid', 'admin', 'instructor', 'moderator'];
        if (!in_array($newRole, $validRoles, true)) {
            return false;
        }

        $stmt = $pdo->prepare('SELECT primary_role, email FROM lms_users WHERE id = ? FOR UPDATE');
        $stmt->execute([$userId]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$current) {
            return false;
        }

        $oldRole = $current['primary_role'];

        $primaryTarget = in_array($newRole, ['free', 'paid', 'admin'], true) ? $newRole : $oldRole;
        $stmtUp = $pdo->prepare('UPDATE lms_users SET primary_role = ? WHERE id = ?');
        $stmtUp->execute([$primaryTarget, $userId]);

        // Pastikan role tercatat di lms_roles & lms_user_roles
        $stmtRole = $pdo->prepare('SELECT id FROM lms_roles WHERE name = ?');
        $stmtRole->execute([$newRole]);
        $roleId = (int)$stmtRole->fetchColumn();

        if ($roleId > 0) {
            $stmtUr = $pdo->prepare('
                INSERT INTO lms_user_roles (user_id, role_id)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE assigned_at = CURRENT_TIMESTAMP
            ');
            $stmtUr->execute([$userId, $roleId]);
        }

        // Catat riwayat perubahan role
        $stmtLog = $pdo->prepare('
            INSERT INTO lms_role_logs (user_id, old_role, new_role, reason)
            VALUES (?, ?, ?, ?)
        ');
        $stmtLog->execute([$userId, $oldRole, $newRole, $reason]);

        // Kirim notifikasi in-app
        if ($newRole === 'paid') {
            lms_create_notification(
                $pdo,
                $userId,
                'Akses Paid User Telah Aktif!',
                'Selamat! Akun Anda berhasil di-upgrade ke Paid User. Seluruh eCourse premium, kuis lengkap, dan sertifikat kini dapat Anda akses penuh.'
            );
        }

        return true;
    }
}

if (!function_exists('lms_create_notification')) {
    function lms_create_notification(PDO $pdo, int $userId, string $title, string $message): void {
        try {
            $stmt = $pdo->prepare('
                INSERT INTO lms_notifications (user_id, title, message, is_read)
                VALUES (?, ?, ?, 0)
            ');
            $stmt->execute([$userId, $title, $message]);
        } catch (Throwable $e) {
            error_log('[LMS] Gagal simpan notifikasi: ' . $e->getMessage());
        }
    }
}

if (!function_exists('lms_get_unread_notifications_count')) {
    function lms_get_unread_notifications_count(PDO $pdo, int $userId): int {
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM lms_notifications WHERE user_id = ? AND is_read = 0');
            $stmt->execute([$userId]);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

// ==== COURSE ACCESS & ENROLLMENT LOGIC ====

if (!function_exists('lms_can_access_course')) {
    function lms_can_access_course(?array $user, array $course, bool $hasActiveEnrollment = false): bool {
        $role = lms_current_role($user);
        if ($role === 'admin') {
            return true;
        }

        if (($course['access_type'] ?? 'free') === 'free') {
            return in_array($role, ['free', 'paid'], true);
        }

        if (($course['access_type'] ?? 'free') === 'premium') {
            if ($role === 'paid') {
                return true;
            }
            return $hasActiveEnrollment;
        }

        return false;
    }
}

if (!function_exists('lms_auto_enroll_free_courses')) {
    function lms_auto_enroll_free_courses(PDO $pdo, int $brandId, int $userId): void {
        try {
            $stmt = $pdo->prepare('
                INSERT IGNORE INTO lms_enrollments (brand_id, user_id, course_id, access_status)
                SELECT brand_id, ?, id, "active"
                FROM lms_courses
                WHERE brand_id = ? AND access_type = "free" AND status = "active"
            ');
            $stmt->execute([$userId, $brandId]);
        } catch (Throwable $e) {
            error_log('[LMS] Auto enroll free course error: ' . $e->getMessage());
        }
    }
}

if (!function_exists('lms_enroll_user_in_course')) {
    function lms_enroll_user_in_course(PDO $pdo, int $brandId, int $userId, int $courseId, ?string $expiresAt = null): void {
        $stmt = $pdo->prepare('
            INSERT INTO lms_enrollments (brand_id, user_id, course_id, access_status, expires_at)
            VALUES (?, ?, ?, "active", ?)
            ON DUPLICATE KEY UPDATE access_status = "active", expires_at = VALUES(expires_at)
        ');
        $stmt->execute([$brandId, $userId, $courseId, $expiresAt]);
    }
}

if (!function_exists('lms_get_course_progress')) {
    function lms_get_course_progress(PDO $pdo, int $userId, int $courseId): array {
        $stmtTotal = $pdo->prepare('
            SELECT COUNT(l.id) AS total_lessons
            FROM lms_lessons l
            JOIN lms_modules m ON m.id = l.module_id
            WHERE l.course_id = ?
        ');
        $stmtTotal->execute([$courseId]);
        $totalLessons = (int)$stmtTotal->fetchColumn();

        if ($totalLessons <= 0) {
            return [
                'total_lessons' => 0,
                'completed_lessons' => 0,
                'percentage' => 0,
                'is_completed' => false,
            ];
        }

        $stmtComp = $pdo->prepare('
            SELECT COUNT(DISTINCT p.lesson_id)
            FROM lms_lesson_progress p
            WHERE p.user_id = ? AND p.course_id = ?
        ');
        $stmtComp->execute([$userId, $courseId]);
        $completedLessons = (int)$stmtComp->fetchColumn();

        $percentage = (int)round(($completedLessons / $totalLessons) * 100);
        $percentage = min(100, max(0, $percentage));

        return [
            'total_lessons' => $totalLessons,
            'completed_lessons' => $completedLessons,
            'percentage' => $percentage,
            'is_completed' => ($percentage >= 100),
        ];
    }
}

if (!function_exists('lms_get_next_lesson')) {
    function lms_get_next_lesson(PDO $pdo, int $userId, int $courseId): ?array {
        $stmt = $pdo->prepare('
            SELECT l.*, m.title AS module_title
            FROM lms_lessons l
            JOIN lms_modules m ON m.id = l.module_id
            LEFT JOIN lms_lesson_progress p ON p.lesson_id = l.id AND p.user_id = ?
            WHERE l.course_id = ? AND p.id IS NULL
            ORDER BY m.sort_order ASC, l.sort_order ASC, l.id ASC
            LIMIT 1
        ');
        $stmt->execute([$userId, $courseId]);
        $next = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($next) {
            return $next;
        }

        // Kalau semua sudah selesai, return pelajaran pertama
        $stmtFirst = $pdo->prepare('
            SELECT l.*, m.title AS module_title
            FROM lms_lessons l
            JOIN lms_modules m ON m.id = l.module_id
            WHERE l.course_id = ?
            ORDER BY m.sort_order ASC, l.sort_order ASC, l.id ASC
            LIMIT 1
        ');
        $stmtFirst->execute([$courseId]);
        return $stmtFirst->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

if (!function_exists('lms_mark_lesson_completed')) {
    function lms_mark_lesson_completed(PDO $pdo, int $userId, int $lessonId): array {
        $stmt = $pdo->prepare('
            SELECT l.id, l.course_id, l.is_premium, c.brand_id, c.access_type
            FROM lms_lessons l
            JOIN lms_courses c ON c.id = l.course_id
            WHERE l.id = ?
        ');
        $stmt->execute([$lessonId]);
        $lesson = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lesson) {
            return ['ok' => false, 'error' => 'Pelajaran tidak ditemukan.'];
        }

        $courseId = (int)$lesson['course_id'];
        $stmtIns = $pdo->prepare('
            INSERT INTO lms_lesson_progress (user_id, course_id, lesson_id, completed_at)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE completed_at = CURRENT_TIMESTAMP
        ');
        $stmtIns->execute([$userId, $courseId, $lessonId]);

        $progress = lms_get_course_progress($pdo, $userId, $courseId);

        if ($progress['is_completed']) {
            lms_create_notification(
                $pdo,
                $userId,
                'Selamat! eCourse Selesai',
                'Anda telah menuntaskan seluruh materi eCourse. Sertifikat digital Anda kini dapat diunduh/dilihat.'
            );
        }

        return [
            'ok' => true,
            'course_id' => $courseId,
            'progress' => $progress,
        ];
    }
}

// ==== CHECKOUT & SIMULATOR PAYMENT ====

if (!function_exists('lms_process_checkout_success')) {
    function lms_process_checkout_success(PDO $pdo, array $brand, int $userId, int $courseId, string $orderNumber, int $amount): array {
        $pdo->beginTransaction();
        try {
            // Update order status
            $stmtOrder = $pdo->prepare('
                UPDATE lms_orders
                SET payment_status = "paid", paid_at = CURRENT_TIMESTAMP
                WHERE order_number = ? AND user_id = ?
            ');
            $stmtOrder->execute([$orderNumber, $userId]);

            // Upgrade user role ke paid
            lms_assign_role($pdo, $userId, 'paid', 'checkout_success: ' . $orderNumber);

            // Enroll user ke course
            lms_enroll_user_in_course($pdo, (int)$brand['id'], $userId, $courseId);

            // Auto enroll semua free course juga
            lms_auto_enroll_free_courses($pdo, (int)$brand['id'], $userId);

            $pdo->commit();

            // Kirim email notifikasi
            $stmtUser = $pdo->prepare('SELECT name, email FROM lms_users WHERE id = ?');
            $stmtUser->execute([$userId]);
            $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

            if ($user && !empty($user['email'])) {
                lms_send_paid_activation_email($brand, $user['email'], $user['name']);
            }

            return ['ok' => true, 'role' => 'paid'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[LMS] Gagal checkout success: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

if (!function_exists('lms_process_checkout_failed')) {
    function lms_process_checkout_failed(PDO $pdo, int $userId, string $orderNumber): void {
        try {
            $stmtOrder = $pdo->prepare('
                UPDATE lms_orders
                SET payment_status = "failed"
                WHERE order_number = ? AND user_id = ?
            ');
            $stmtOrder->execute([$orderNumber, $userId]);

            lms_create_notification(
                $pdo,
                $userId,
                'Pembayaran Belum Berhasil',
                'Transaksi checkout eCourse premium Anda belum berhasil. Role akun Anda tetap User Free. Anda dapat mencoba kembali kapan saja.'
            );
        } catch (Throwable $e) {
            error_log('[LMS] Gagal update order failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('lms_send_paid_activation_email')) {
    function lms_send_paid_activation_email(array $brand, string $email, string $name): void {
        $subject = 'Selamat! Akses eCourse Premium Anda Sudah Aktif di ' . ($brand['name'] ?? 'RahasiaEmas.id');
        $accent = ($brand['theme_preset'] ?? 'gold') === 'silver' ? '#B7BCC4' : '#C9A84C';
        $brandName = htmlspecialchars($brand['name'] ?? 'RahasiaEmas.id', ENT_QUOTES, 'UTF-8');
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $dashboardUrl = 'https://' . ($brand['domain'] ?? 'rahasiaemas.id') . '/course/my-courses.php';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="id">
<body style="margin:0;padding:0;background:#0d0d0c;font-family:Arial,Helvetica,sans-serif;color:#f3efe6;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#0d0d0c;padding:30px 15px;">
    <tr><td align="center">
      <table width="520" cellpadding="0" cellspacing="0" style="background:#171716;border-radius:18px;overflow:hidden;border:1px solid {$accent}40;box-shadow:0 15px 40px rgba(0,0,0,0.5);">
        <tr><td style="background:#1f1f1d;padding:24px 30px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.06);">
          <h2 style="margin:0;color:{$accent};font-size:22px;letter-spacing:0.5px;">{$brandName} LMS</h2>
        </td></tr>
        <tr><td style="padding:32px 30px;font-size:14px;line-height:1.65;color:#e8e4da;">
          <p style="font-size:16px;font-weight:bold;margin-top:0;color:#ffffff;">Halo {$safeName},</p>
          <p>Selamat! Pembayaran eCourse Anda telah berhasil diverifikasi. Role akun Anda kini telah ditingkatkan menjadi <strong style="color:{$accent};">Paid User</strong>.</p>
          <div style="background:#222220;border-left:4px solid {$accent};padding:14px 18px;border-radius:8px;margin:20px 0;">
            <p style="margin:0;font-size:13.5px;color:#f4d27a;"><strong>Fasilitas Baru Anda:</strong><br>
            • Akses tak terbatas ke seluruh materi eCourse premium<br>
            • Progress tracking otomatis & materi video/PDF<br>
            • Kuis evaluasi pemahaman & sertifikat digital resmi</p>
          </div>
          <p style="text-align:center;margin:28px 0 10px;">
            <a href="{$dashboardUrl}" style="display:inline-block;background:linear-gradient(135deg, {$accent}, #f4d27a);color:#111110;text-decoration:none;font-weight:800;padding:14px 28px;border-radius:12px;font-size:14px;">Buka Dashboard My Courses</a>
          </p>
        </td></tr>
        <tr><td style="background:#141413;padding:16px 30px;text-align:center;font-size:11.5px;color:#8f8a81;border-top:1px solid rgba(255,255,255,0.05);">
          Email otomatis dari platform edukasi {$brandName}. Simpan email ini sebagai bukti aktivasi akun.
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

        // Prioritas Mailketing bila token tersedia, fallback ke mail() standar
        if (function_exists('mailketing_send_email') && function_exists('mailketing_get_api_token') && mailketing_get_api_token($brand) !== '') {
            try {
                $senderName = !empty($brand['sender_name']) ? $brand['sender_name'] : ($brand['name'] ?? 'RahasiaEmas.id');
                $senderEmail = !empty($brand['sender_email']) ? $brand['sender_email'] : 'info@' . ($brand['domain'] ?? 'rahasiaemas.id');
                mailketing_send_email($email, $subject, $html, $senderName, $senderEmail, $brand);
                return;
            } catch (Throwable $e) {
                error_log('[LMS] Kirim email Mailketing gagal, fallback mail(): ' . $e->getMessage());
            }
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . ($brand['name'] ?? 'RahasiaEmas.id') . ' <noreply@' . ($brand['domain'] ?? 'rahasiaemas.id') . '>',
        ];
        @mail($email, $subject, $html, implode("\r\n", $headers));
    }
}

if (!function_exists('lms_bank_catalog')) {
    function lms_bank_catalog(): array {
        return [
            'bca' => 'Bank Central Asia (BCA)',
            'bri' => 'Bank Rakyat Indonesia (BRI)',
            'mandiri' => 'Bank Mandiri',
            'bni' => 'Bank Negara Indonesia (BNI)',
            'bsi' => 'Bank Syariah Indonesia (BSI)',
            'cimb' => 'CIMB Niaga',
            'permata' => 'PermataBank',
            'danamon' => 'Bank Danamon',
            'ocbc' => 'OCBC Indonesia',
            'panin' => 'PaninBank',
            'maybank' => 'Maybank Indonesia',
            'btn' => 'Bank Tabungan Negara (BTN)',
            'mega' => 'Bank Mega',
            'jago' => 'Bank Jago',
            'seabank' => 'SeaBank Indonesia',
            'blu' => 'blu by BCA Digital',
            'bank_dki' => 'Bank DKI',
            'bank_jabar' => 'Bank BJB',
            'bank_jateng' => 'Bank Jateng',
            'bank_jatim' => 'Bank Jatim',
            'other' => 'Bank Lainnya',
        ];
    }
}

if (!function_exists('lms_store_bank_logo')) {
    function lms_store_bank_logo(array $file, ?string $oldPath = null): string {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return (string)$oldPath;
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
            throw new RuntimeException('Upload logo bank gagal.');
        }
        if ((int)($file['size'] ?? 0) <= 0 || (int)$file['size'] > 2 * 1024 * 1024) {
            throw new RuntimeException('Logo bank maksimal 2 MB.');
        }
        $extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        $allowed = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset($allowed[$extension]) || $mime !== $allowed[$extension]) {
            throw new RuntimeException('Logo bank harus PNG, JPG, atau WEBP.');
        }
        $relativeDir = '/uploads/lms/banks';
        $absoluteDir = dirname(__DIR__) . $relativeDir;
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException('Folder logo bank tidak dapat dibuat.');
        }
        $path = $relativeDir . '/' . bin2hex(random_bytes(16)) . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], dirname(__DIR__) . $path)) {
            throw new RuntimeException('Logo bank gagal disimpan.');
        }
        if ($oldPath && str_starts_with($oldPath, $relativeDir . '/')) {
            $oldFile = dirname(__DIR__) . $oldPath;
            if (is_file($oldFile)) @unlink($oldFile);
        }
        return $path;
    }
}

if (!function_exists('lms_default_payment_settings')) {
    function lms_default_payment_settings(array $brand): array {
        return [
            'brand_id' => (int)($brand['id'] ?? 0),
            'active_method' => 'bank_transfer',
            'bank_transfer_enabled' => 1,
            'bank_code' => '',
            'bank_name' => '',
            'bank_account_number' => '',
            'bank_account_name' => $brand['name'] ?? '',
            'bank_instructions' => "Transfer sesuai nominal checkout. Setelah transfer, simpan bukti pembayaran dan hubungi admin untuk verifikasi manual.",
            'admin_whatsapp' => $brand['whatsapp_default'] ?? '',
            'midtrans_enabled' => 0,
            'midtrans_environment' => 'sandbox',
            'midtrans_server_key' => '',
            'midtrans_client_key' => '',
            'midtrans_merchant_id' => '',
            'bank_logo_path' => '',
        ];
    }
}

if (!function_exists('lms_get_payment_settings')) {
    function lms_get_payment_settings(PDO $pdo, array $brand): array {
        lms_ensure_schema($pdo);
        $brandId = (int)$brand['id'];
        $defaults = lms_default_payment_settings($brand);

        $stmt = $pdo->prepare('SELECT * FROM lms_payment_settings WHERE brand_id = ? LIMIT 1');
        $stmt->execute([$brandId]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$settings) {
            $stmt = $pdo->prepare('
                INSERT INTO lms_payment_settings (brand_id, active_method, bank_transfer_enabled, bank_account_name, bank_instructions)
                VALUES (?, ?, ?, ?, ?)
            ');
            $stmt->execute([$brandId, $defaults['active_method'], 1, $defaults['bank_account_name'], $defaults['bank_instructions']]);
            return $defaults;
        }

        return array_merge($defaults, $settings);
    }
}

if (!function_exists('lms_payment_method_label')) {
    function lms_payment_method_label(string $method): string {
        return match ($method) {
            'midtrans' => 'Midtrans Snap',
            'bank_transfer' => 'Transfer via Bank',
            'manual_simulator' => 'Simulator Manual',
            default => $method,
        };
    }
}

if (!function_exists('lms_mask_secret')) {
    function lms_mask_secret(?string $value): string {
        $value = trim((string)$value);
        if ($value === '') return '';
        return substr($value, 0, 6) . str_repeat('*', max(4, strlen($value) - 10)) . substr($value, -4);
    }
}

if (!function_exists('lms_store_payment_proof')) {
    function lms_store_payment_proof(array $file): string {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('Silakan pilih file bukti pembayaran terlebih dahulu.');
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
            throw new RuntimeException('Upload bukti pembayaran gagal. Coba lagi.');
        }
        if ((int)($file['size'] ?? 0) <= 0 || (int)$file['size'] > 5 * 1024 * 1024) {
            throw new RuntimeException('Ukuran bukti pembayaran maksimal 5 MB.');
        }
        $extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        $allowed = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'pdf' => 'application/pdf'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset($allowed[$extension]) || $mime !== $allowed[$extension]) {
            throw new RuntimeException('Bukti pembayaran harus berupa gambar (PNG/JPG/WEBP) atau PDF.');
        }
        $relativeDir = '/uploads/lms/payment-proofs';
        $absoluteDir = dirname(__DIR__) . $relativeDir;
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException('Folder bukti pembayaran tidak dapat dibuat.');
        }
        $path = $relativeDir . '/' . bin2hex(random_bytes(16)) . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], dirname(__DIR__) . $path)) {
            throw new RuntimeException('Bukti pembayaran gagal disimpan.');
        }
        return $path;
    }
}

if (!function_exists('lms_attach_payment_proof')) {
    function lms_attach_payment_proof(PDO $pdo, int $userId, string $orderNumber, string $proofPath): bool {
        $stmt = $pdo->prepare('
            UPDATE lms_orders
            SET payment_proof_path = ?, payment_proof_uploaded_at = CURRENT_TIMESTAMP
            WHERE order_number = ? AND user_id = ? AND payment_status = "pending"
        ');
        $stmt->execute([$proofPath, $orderNumber, $userId]);
        return $stmt->rowCount() > 0;
    }
}

if (!function_exists('lms_admin_whatsapp_link')) {
    function lms_admin_whatsapp_link(array $paymentSettings, string $orderNumber, string $courseTitle, int $amount): ?string {
        $raw = trim((string)($paymentSettings['admin_whatsapp'] ?? ''));
        if ($raw === '') return null;
        $number = function_exists('normalize_whatsapp') ? normalize_whatsapp($raw) : preg_replace('/[^0-9]/', '', $raw);
        if ($number === '') return null;
        $message = "Halo Admin, saya sudah transfer untuk order {$orderNumber} ({$courseTitle}) sebesar Rp " . number_format($amount, 0, ',', '.') . ". Mohon dikonfirmasi. Terima kasih.";
        return 'https://wa.me/' . $number . '?text=' . rawurlencode($message);
    }
}
