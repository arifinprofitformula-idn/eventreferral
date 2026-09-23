<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/lms_core.php';
require_once __DIR__ . '/../includes/lms_layout.php';
start_secure_session();

$brand = require_brand_or_404(get_current_brand());
$brandId = (int)$brand['id'];
$pdo = get_db();

lms_ensure_schema($pdo);
$user = lms_get_logged_user($pdo, $brandId);
$role = lms_current_role($user);

$slug = clean($_GET['slug'] ?? '');
if ($slug === '') {
    header('Location: /course/index.php');
    exit;
}

$stmt = $pdo->prepare('
    SELECT * FROM lms_courses
    WHERE brand_id = ? AND slug = ? AND status = "active"
');
$stmt->execute([$brandId, $slug]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    http_response_code(404);
    render_lms_header($brand, $user, 'catalog');
    echo '<div style="text-align:center;padding:70px 20px;"><h2>eCourse Tidak Ditemukan</h2><p style="color:var(--muted);margin:12px 0 24px;">Materi yang Anda cari tidak tersedia atau belum dipublikasikan.</p><a href="/course/index.php" class="btn-lms btn-lms-gold">Kembali ke Katalog</a></div>';
    render_lms_footer($brand);
    exit;
}

$courseId = (int)$course['id'];
$isFree = ($course['access_type'] === 'free');

// Cek enrollment
$isEnrolled = false;
if ($user) {
    $stmtEnr = $pdo->prepare('
        SELECT access_status FROM lms_enrollments
        WHERE user_id = ? AND course_id = ? AND access_status = "active"
    ');
    $stmtEnr->execute([(int)$user['id'], $courseId]);
    $isEnrolled = (bool)$stmtEnr->fetchColumn();

    // Auto enroll kalau course gratis
    if ($isFree && !$isEnrolled) {
        lms_enroll_user_in_course($pdo, $brandId, (int)$user['id'], $courseId);
        $isEnrolled = true;
    }
}

$canAccess = lms_can_access_course($user, $course, $isEnrolled);
$progress = $user ? lms_get_course_progress($pdo, (int)$user['id'], $courseId) : null;
$nextLesson = $user ? lms_get_next_lesson($pdo, (int)$user['id'], $courseId) : null;

// Ambil struktur modul dan materi
$stmtMods = $pdo->prepare('
    SELECT * FROM lms_modules
    WHERE course_id = ?
    ORDER BY sort_order ASC, id ASC
');
$stmtMods->execute([$courseId]);
$modules = $stmtMods->fetchAll(PDO::FETCH_ASSOC);

$completedLessonIds = [];
if ($user) {
    $stmtProg = $pdo->prepare('
        SELECT lesson_id FROM lms_lesson_progress
        WHERE user_id = ? AND course_id = ?
    ');
    $stmtProg->execute([(int)$user['id'], $courseId]);
    $completedLessonIds = $stmtProg->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

render_lms_header($brand, $user, 'catalog');
?>

<div style="display:grid;grid-template-columns:1fr 340px;gap:32px;align-items:start;">
  <!-- SISI KIRI: Detail Course & Silabus -->
  <div>
    <div style="margin-bottom:16px;">
      <a href="/course/index.php" style="color:var(--muted);font-size:13px;display:inline-flex;align-items:center;gap:6px;">
        ← Kembali ke Katalog eCourse
      </a>
    </div>

    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
      <span class="role-pill <?= $isFree ? 'badge-free' : 'badge-paid' ?>">
        <?= $isFree ? 'FREE COURSE' : 'PREMIUM COURSE' ?>
      </span>
      <?php if (!empty($course['badge_label'])): ?>
        <span style="font-size:12px;color:var(--gold-soft);font-weight:700;">
          <?= htmlspecialchars($course['badge_label']) ?>
        </span>
      <?php endif; ?>
    </div>

    <h1 style="font-family:'Playfair Display', serif;font-size:clamp(26px, 3.5vw, 36px);line-height:1.2;margin-bottom:16px;">
      <?= htmlspecialchars($course['title']) ?>
    </h1>

    <p style="font-size:15px;color:var(--muted);line-height:1.65;margin-bottom:28px;">
      <?= nl2br(htmlspecialchars($course['description'] ?: $course['summary'])) ?>
    </p>

    <?php if ($user && $progress && $progress['total_lessons'] > 0): ?>
      <div style="background:var(--surface);border:1px solid var(--border-soft);padding:20px;border-radius:18px;margin-bottom:32px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
          <span style="font-weight:700;font-size:14px;">Progress Penyelesaian Anda</span>
          <span style="font-weight:800;color:var(--gold-soft);"><?= $progress['percentage'] ?>% (<?= $progress['completed_lessons'] ?> / <?= $progress['total_lessons'] ?> Materi)</span>
        </div>
        <div style="width:100%;height:10px;background:rgba(255,255,255,0.06);border-radius:999px;overflow:hidden;">
          <div style="width:<?= $progress['percentage'] ?>%;height:100%;background:linear-gradient(90deg, var(--gold), var(--gold-soft));border-radius:999px;"></div>
        </div>
      </div>
    <?php endif; ?>

    <h2 style="font-size:20px;font-weight:800;margin-bottom:18px;display:flex;align-items:center;gap:10px;">
      <span>Kurikulum & Daftar Materi</span>
    </h2>

    <div style="display:grid;gap:20px;">
      <?php if (empty($modules)): ?>
        <p style="color:var(--muted);">Belum ada modul yang ditambahkan ke kursus ini.</p>
      <?php endif; ?>

      <?php foreach ($modules as $m): ?>
        <?php
          $stmtLes = $pdo->prepare('
              SELECT * FROM lms_lessons
              WHERE module_id = ?
              ORDER BY sort_order ASC, id ASC
          ');
          $stmtLes->execute([(int)$m['id']]);
          $lessons = $stmtLes->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <div style="background:var(--surface);border:1px solid var(--border-soft);border-radius:18px;overflow:hidden;">
          <div style="padding:16px 20px;background:rgba(255,255,255,0.025);border-bottom:1px solid rgba(255,255,255,0.06);font-weight:800;font-size:15px;color:var(--text);">
            <?= htmlspecialchars($m['title']) ?>
          </div>
          <div style="display:grid;">
            <?php foreach ($lessons as $les): ?>
              <?php
                $lesId = (int)$les['id'];
                $isCompleted = in_array($lesId, $completedLessonIds, true);
                $isLessonLocked = !$canAccess && ($les['is_premium'] || !$isFree);
                $icon = '📄';
                if ($les['content_type'] === 'video') $icon = '🎬';
                elseif ($les['content_type'] === 'quiz') $icon = '📝';
              ?>
              <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid rgba(255,255,255,0.04);gap:14px;background:<?= $isCompleted ? 'rgba(34,197,94,0.03)' : 'transparent' ?>;">
                <div style="display:flex;align-items:center;gap:12px;min-width:0;">
                  <span style="font-size:16px;"><?= $icon ?></span>
                  <div style="min-width:0;">
                    <div style="font-size:14px;font-weight:600;color:<?= $isLessonLocked ? 'var(--muted)' : 'var(--text)' ?>;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                      <?= htmlspecialchars($les['title']) ?>
                    </div>
                    <div style="font-size:11.5px;color:var(--muted);display:flex;gap:10px;margin-top:2px;">
                      <span><?= ucfirst($les['content_type']) ?></span>
                      <span>• <?= (int)$les['duration_minutes'] ?> menit</span>
                      <?php if ($les['is_premium']): ?>
                        <span style="color:var(--gold-soft);">★ Premium</span>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>

                <div>
                  <?php if ($isCompleted): ?>
                    <span style="font-size:12px;color:var(--success);font-weight:800;display:inline-flex;align-items:center;gap:4px;">
                      ✓ Selesai
                    </span>
                  <?php elseif ($isLessonLocked): ?>
                    <span style="font-size:12px;color:var(--muted);background:rgba(255,255,255,0.05);padding:4px 10px;border-radius:999px;">
                      🔒 Terkunci
                    </span>
                  <?php else: ?>
                    <a href="/course/lesson.php?id=<?= $lesId ?>" class="btn-lms btn-lms-ghost" style="padding:6px 14px;font-size:12px;">
                      Buka
                    </a>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- SISI KANAN: Card Action / Checkout / Access Gate -->
  <div style="position:sticky;top:96px;background:var(--surface);border:1px solid var(--border-gold);border-radius:22px;padding:26px;box-shadow:0 16px 40px rgba(0,0,0,0.35);">
    <div style="font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;">Tipe Akses eCourse</div>
    <div style="font-size:24px;font-weight:800;color:<?= $isFree ? '#93c5fd' : 'var(--gold-soft)' ?>;margin:6px 0 16px;">
      <?= $isFree ? 'FREE ACCESS' : 'Rp ' . number_format((int)$course['price'], 0, ',', '.') ?>
    </div>

    <div style="background:rgba(255,255,255,0.03);border:1px solid var(--border-soft);border-radius:14px;padding:14px;margin-bottom:20px;font-size:13px;line-height:1.6;">
      <div>✓ Materi video, panduan PDF, dan studi kasus</div>
      <div style="margin-top:6px;">✓ Progress tracking & resume otomatis</div>
      <div style="margin-top:6px;">✓ Evaluasi pemahaman kuis terstruktur</div>
      <div style="margin-top:6px;">✓ Sertifikat kompetensi: <?= $course['certificate_enabled'] ? '<strong style="color:var(--gold-soft);">Tersedia</strong>' : 'Tidak' ?></div>
    </div>

    <?php if (!$user): ?>
      <div style="display:grid;gap:10px;">
        <a href="/course/login.php?redirect=<?= urlencode('/course/' . $course['slug']) ?>" class="btn-lms btn-lms-gold" style="width:100%;">
          Login untuk Akses Materi
        </a>
        <a href="/course/register.php" class="btn-lms btn-lms-ghost" style="width:100%;">
          Daftar Akun Baru
        </a>
        <p style="font-size:11.5px;color:var(--muted);text-align:center;margin-top:4px;">
          Tamu (Guest) hanya dapat melihat daftar silabus dan ringkasan eCourse.
        </p>
      </div>
    <?php elseif ($canAccess): ?>
      <div style="display:grid;gap:10px;">
        <?php if ($nextLesson): ?>
          <a href="/course/lesson.php?id=<?= (int)$nextLesson['id'] ?>" class="btn-lms btn-lms-gold" style="width:100%;">
            Lanjutkan Belajar (Continue) →
          </a>
        <?php else: ?>
          <a href="/course/my-courses.php" class="btn-lms btn-lms-gold" style="width:100%;">
            Buka Dashboard My Courses
          </a>
        <?php endif; ?>
        <div style="font-size:12px;color:var(--success);text-align:center;font-weight:700;">
          ✓ Akses Anda Aktif (<?= strtoupper($role) ?>)
        </div>
      </div>
    <?php else: ?>
      <div style="display:grid;gap:12px;">
        <div style="background:rgba(214,165,54,0.1);border:1px solid var(--border-gold);padding:12px 14px;border-radius:12px;font-size:12.5px;color:var(--gold-soft);line-height:1.5;">
          Kursus ini merupakan materi <strong>Premium</strong>. Status akun Anda saat ini: <strong>User Free</strong>.
        </div>
        <a href="/course/checkout.php?slug=<?= urlencode($course['slug']) ?>" class="btn-lms btn-lms-gold" style="width:100%;padding:14px;">
          Beli & Upgrade ke Paid User →
        </a>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php render_lms_footer($brand); ?>
