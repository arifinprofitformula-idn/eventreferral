<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_nav.php';
require_once __DIR__ . '/../includes/lms_core.php';
require_once __DIR__ . '/../includes/lms_form_builder.php';
start_secure_session();

$brand = require_admin_for_brand(get_current_brand());
$brandId = (int)$brand['id'];
$pdo = get_db();

lms_ensure_schema($pdo);
lms_ensure_course_builder_schema($pdo);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$courseId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$course = null;
$courseTags = [];

if ($courseId) {
    $stmt = $pdo->prepare('SELECT * FROM lms_courses WHERE id = ? AND brand_id = ?');
    $stmt->execute([$courseId, $brandId]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$course) {
        header('Location: lms-courses.php');
        exit;
    }

    $stmtTags = $pdo->prepare('
        SELECT t.name FROM lms_tags t
        JOIN lms_course_tags ct ON ct.tag_id = t.id
        WHERE ct.course_id = ?
        ORDER BY t.name ASC
    ');
    $stmtTags->execute([$courseId]);
    $courseTags = $stmtTags->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $course['tags'] = implode(', ', $courseTags);

    $stmtInst = $pdo->prepare('SELECT user_id FROM lms_course_instructors WHERE course_id = ? ORDER BY sort_order ASC LIMIT 1');
    $stmtInst->execute([$courseId]);
    $course['instructor_id'] = (int)$stmtInst->fetchColumn();
}

$notice = null;
$noticeType = 'success';
$formData = $course ?: [
    'title' => '',
    'slug' => '',
    'category_id' => 0,
    'description' => '',
    'summary' => '',
    'badge_label' => '',
    'access_type' => 'free',
    'price' => 0,
    'level' => 'beginner',
    'duration_minutes' => 0,
    'certificate_enabled' => 0,
    'status' => 'active',
    'tags' => '',
    'instructor_id' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $notice = 'Sesi tidak valid. Silakan refresh halaman.';
        $noticeType = 'error';
    } else {
        $title = trim(clean($_POST['title'] ?? ''));
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $summary = trim(clean($_POST['summary'] ?? ''));
        $badgeLabel = trim(clean($_POST['badge_label'] ?? ''));
        $accessType = ($_POST['access_type'] ?? '') === 'premium' ? 'premium' : 'free';
        $price = $accessType === 'premium' ? max(0, (int)($_POST['price'] ?? 0)) : 0;
        $level = in_array($_POST['level'] ?? '', ['beginner', 'intermediate', 'advanced'], true) ? $_POST['level'] : 'beginner';
        $durationHours = max(0, (int)($_POST['duration_hours'] ?? 0));
        $durationMins = max(0, min(59, (int)($_POST['duration_minutes'] ?? 0)));
        $totalDurationMinutes = ($durationHours * 60) + $durationMins;
        $certificateEnabled = isset($_POST['certificate_enabled']) ? 1 : 0;
        $rawStatus = in_array($_POST['status'] ?? '', ['active', 'draft', 'archived'], true) ? $_POST['status'] : 'active';
        $submitMode = $_POST['form_submit_mode'] ?? 'publish';
        $status = ($submitMode === 'draft') ? 'draft' : $rawStatus;
        $rawTags = (string)($_POST['tags'] ?? '');
        $instructorId = (int)($_POST['instructor_id'] ?? 0);

        $slugInput = trim(clean($_POST['slug'] ?? ''));
        $slug = $slugInput !== '' ? slugify($slugInput) : slugify($title);

        $formData = array_merge($formData, [
            'title' => $title,
            'slug' => $slug,
            'category_id' => $categoryId,
            'description' => $description,
            'summary' => $summary,
            'badge_label' => $badgeLabel,
            'access_type' => $accessType,
            'price' => $price,
            'level' => $level,
            'duration_minutes' => $totalDurationMinutes,
            'certificate_enabled' => $certificateEnabled,
            'status' => $status,
            'tags' => $rawTags,
            'instructor_id' => $instructorId,
        ]);

        if ($title === '') {
            $notice = 'Judul course wajib diisi.';
            $noticeType = 'error';
        } elseif ($description === '') {
            $notice = 'Deskripsi course wajib diisi.';
            $noticeType = 'error';
        } elseif ($categoryId <= 0) {
            $notice = 'Kategori course wajib dipilih.';
            $noticeType = 'error';
        } elseif ($accessType === 'premium' && $price <= 0) {
            $notice = 'Course berbayar (Premium) wajib mengisi harga di atas 0.';
            $noticeType = 'error';
        } else {
            $stmtCat = $pdo->prepare('SELECT id FROM lms_categories WHERE id = ? AND brand_id = ? AND status = "active"');
            $stmtCat->execute([$categoryId, $brandId]);
            if (!$stmtCat->fetchColumn()) {
                $notice = 'Kategori tidak valid.';
                $noticeType = 'error';
            }
        }

        if (!$notice) {
            $coverPath = $course['cover_image'] ?? null;
            if (!empty($_FILES['cover_file']['name'])) {
                try {
                    $uploadRes = lms_store_course_upload($_FILES['cover_file'], 'cover');
                    if ($uploadRes) {
                        $coverPath = $uploadRes['path'];
                    }
                } catch (Throwable $e) {
                    $notice = 'Upload thumbnail gagal: ' . $e->getMessage();
                    $noticeType = 'error';
                }
            }
        }

        if (!$notice) {
            try {
                $pdo->beginTransaction();

                if ($courseId) {
                    $stmt = $pdo->prepare('
                        UPDATE lms_courses
                        SET category_id = ?, title = ?, slug = ?, summary = ?, description = ?, badge_label = ?,
                            access_type = ?, price = ?, level = ?, duration_minutes = ?, cover_image = ?,
                            certificate_enabled = ?, status = ?, published_at = IF(? = "active" AND published_at IS NULL, NOW(), published_at)
                        WHERE id = ? AND brand_id = ?
                    ');
                    $stmt->execute([
                        $categoryId, $title, $slug, $summary ?: null, $description, $badgeLabel ?: null,
                        $accessType, $price, $level, $totalDurationMinutes, $coverPath,
                        $certificateEnabled, $status, $status, $courseId, $brandId
                    ]);
                    $targetCourseId = $courseId;
                } else {
                    $stmt = $pdo->prepare('
                        INSERT INTO lms_courses (brand_id, category_id, title, slug, summary, description, badge_label,
                            access_type, price, level, duration_minutes, cover_image, certificate_enabled, status, published_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, IF(? = "active", NOW(), NULL))
                    ');
                    $stmt->execute([
                        $brandId, $categoryId, $title, $slug, $summary ?: null, $description, $badgeLabel ?: null,
                        $accessType, $price, $level, $totalDurationMinutes, $coverPath, $certificateEnabled, $status, $status
                    ]);
                    $targetCourseId = (int)$pdo->lastInsertId();
                }

                lms_sync_course_tags($pdo, $brandId, $targetCourseId, $rawTags);

                $pdo->prepare('DELETE FROM lms_course_instructors WHERE course_id = ?')->execute([$targetCourseId]);
                if ($instructorId > 0) {
                    $stmtInst = $pdo->prepare('
                        INSERT IGNORE INTO lms_course_instructors (course_id, user_id, role_label, sort_order)
                        SELECT ?, id, "Instructor", 1 FROM lms_users WHERE id = ? AND brand_id = ?
                    ');
                    $stmtInst->execute([$targetCourseId, $instructorId, $brandId]);
                }

                $quickType = in_array($_POST['quick_material_type'] ?? '', ['video', 'pdf', 'quiz'], true) ? $_POST['quick_material_type'] : '';
                $quickTitle = trim(clean($_POST['quick_material_title'] ?? ''));
                if ($quickType !== '' && $quickTitle !== '') {
                    $materialFilePath = null;
                    $materialUrl = trim(clean($_POST['quick_material_url'] ?? ''));
                    $fileSize = null;

                    if (!empty($_FILES['quick_material_file']['name'])) {
                        $upMat = lms_store_course_upload($_FILES['quick_material_file'], $quickType);
                        if ($upMat) {
                            $materialFilePath = $upMat['path'];
                            $fileSize = $upMat['size'];
                        }
                    }

                    $quizJson = null;
                    if ($quickType === 'quiz') {
                        $rawQuiz = trim($_POST['quick_quiz_json'] ?? '');
                        if ($rawQuiz !== '') {
                            $decoded = json_decode($rawQuiz, true);
                            if (is_array($decoded)) {
                                $quizJson = json_encode($decoded);
                            }
                        }
                        if (!$quizJson) {
                            $quizJson = json_encode([
                                'passing_score' => 70,
                                'questions' => [
                                    [
                                        'q' => 'Pertanyaan starter evaluasi materi...',
                                        'options' => ['Pilihan A', 'Pilihan B Benar', 'Pilihan C'],
                                        'answer' => 1,
                                    ]
                                ]
                            ]);
                        }
                    }

                    $stmtMat = $pdo->prepare('
                        INSERT INTO lms_course_materials (course_id, material_type, title, file_path, external_url, quiz_data, file_size, sort_order)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                    ');
                    $stmtMat->execute([
                        $targetCourseId, $quickType, $quickTitle, $materialFilePath, $materialUrl ?: null, $quizJson, $fileSize
                    ]);

                    $stmtMod = $pdo->prepare('SELECT id FROM lms_modules WHERE course_id = ? ORDER BY sort_order ASC LIMIT 1');
                    $stmtMod->execute([$targetCourseId]);
                    $starterModId = (int)$stmtMod->fetchColumn();
                    if (!$starterModId) {
                        $pdo->prepare('INSERT INTO lms_modules (course_id, title, sort_order) VALUES (?, "Modul 1: Kurikulum Utama", 1)')->execute([$targetCourseId]);
                        $starterModId = (int)$pdo->lastInsertId();
                    }

                    $contentUrlTarget = $materialFilePath ?: ($materialUrl ?: null);
                    $pdo->prepare('
                        INSERT INTO lms_lessons (course_id, module_id, title, content_type, content_url, body_text, quiz_data, is_premium, sort_order, duration_minutes)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 10)
                    ')->execute([
                        $targetCourseId, $starterModId, $quickTitle, $quickType, $contentUrlTarget,
                        'Materi diunggah lewat formulir pengisian course admin.', $quizJson, ($accessType === 'premium' ? 1 : 0)
                    ]);
                }

                $pdo->commit();
                header('Location: lms-courses.php?saved=1');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $notice = 'Gagal menyimpan course: ' . $e->getMessage();
                $noticeType = 'error';
            }
        }
    }
}

$stmtCats = $pdo->prepare('SELECT id, name FROM lms_categories WHERE brand_id = ? AND status = "active" ORDER BY name ASC');
$stmtCats->execute([$brandId]);
$categories = $stmtCats->fetchAll(PDO::FETCH_ASSOC);

$stmtInst = $pdo->prepare('SELECT id, name, email FROM lms_users WHERE brand_id = ? AND status = "active" ORDER BY name ASC');
$stmtInst->execute([$brandId]);
$instructors = $stmtInst->fetchAll(PDO::FETCH_ASSOC);

$builder = new LmsCourseFormBuilder($formData, [
    'categories' => $categories,
    'instructors' => $instructors,
]);

$logoPath = $brand['logo_path'] ? '..' . $brand['logo_path'] : '../assets/logo.png';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $courseId ? 'Edit eCourse' : 'Pengisian eCourse Baru' ?> - <?= htmlspecialchars($brand['name']) ?></title>
<style>
  <?= get_theme_css_vars($brand) ?>
  :root {
    --bg: #0b0b0a;
    --surface: #171716;
    --border-gold: rgba(214,165,54,0.18);
    --gold: var(--brand-primary, #d6a536);
    --gold-soft: var(--brand-soft, #f4d27a);
    --text: #f7f3e8;
    --muted: #a8a29a;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0; min-height: 100vh; color: var(--text);
    background: radial-gradient(circle at 84% 8%, color-mix(in srgb, var(--gold) 22%, transparent), transparent 30vw), linear-gradient(135deg, var(--bg), #090908);
    font-family: Inter, system-ui, -apple-system, sans-serif;
  }
  .topbar { position: sticky; top: 0; z-index: 30; background: rgba(16,16,15,0.85); border-bottom: 1px solid rgba(255,255,255,0.08); backdrop-filter: blur(16px); }
  .topbar-inner, .wrap { width: min(100%, 1280px); margin: 0 auto; padding-left: 24px; padding-right: 24px; }
  .topbar-inner { min-height: 80px; display: flex; align-items: center; justify-content: space-between; gap: 20px; }
  .brand img { width: 130px; height: auto; }
  .wrap { padding-top: 28px; padding-bottom: 80px; }
  h1 { font-family: Georgia, serif; font-size: clamp(26px, 3.5vw, 36px); margin: 0 0 8px; }
  h1 span { color: var(--gold-soft); }
  .subtitle { color: var(--muted); margin: 0 0 24px; font-size: 14px; }
  .alert { border-radius: 14px; padding: 14px 18px; margin-bottom: 20px; font-size: 14px; line-height: 1.5; }
  .alert-ok { color: #bbf7d0; background: rgba(34,197,94,0.12); border: 1px solid rgba(34,197,94,0.3); }
  .alert-error { color: #fecaca; background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.3); }
</style>
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="dashboard.php"><img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($brand['name']) ?>"></a>
    <?php render_admin_nav('lms-courses'); ?>
  </div>
</header>

<main class="wrap">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:12px;">
    <div>
      <h1><?= $courseId ? 'Edit <span>eCourse</span>' : 'Pengisian <span>Course Baru</span>' ?></h1>
      <p class="subtitle">Lengkapi formulir standar untuk menerbitkan materi pembelajaran di portal Simple LMS.</p>
    </div>
    <a href="lms-courses.php" class="lms-btn lms-btn-cancel">← Kembali ke Daftar Course</a>
  </div>

  <?php if ($notice): ?>
    <div class="alert <?= $noticeType === 'error' ? 'alert-error' : 'alert-ok' ?>">
      <?= htmlspecialchars($notice) ?>
    </div>
  <?php endif; ?>

  <?= $builder->renderForm((string)$_SESSION['csrf_token'], $courseId) ?>
</main>
</body>
</html>
