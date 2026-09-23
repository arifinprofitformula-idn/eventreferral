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

if (!$user) {
    header('Location: /course/login.php?redirect=' . urlencode('/course/my-courses.php'));
    exit;
}

$role = lms_current_role($user);

// Selalu auto enroll course free untuk user terdaftar
lms_auto_enroll_free_courses($pdo, $brandId, (int)$user['id']);

// Dashboard query sesuai role:
// Free User -> hanya course free (atau yang dienroll)
// Paid User -> semua course yang dibeli / aktif
if ($role === 'admin' || $role === 'paid') {
    $stmt = $pdo->prepare('
        SELECT c.*, e.access_status, e.enrolled_at,
               (SELECT COUNT(l.id) FROM lms_lessons l WHERE l.course_id = c.id) AS total_lessons
        FROM lms_courses c
        LEFT JOIN lms_enrollments e ON e.course_id = c.id AND e.user_id = ?
        WHERE c.brand_id = ? AND c.status = "active"
        ORDER BY (e.access_status = "active") DESC, c.created_at DESC
    ');
    $stmt->execute([(int)$user['id'], $brandId]);
} else {
    // Free User
    $stmt = $pdo->prepare('
        SELECT c.*, e.access_status, e.enrolled_at,
               (SELECT COUNT(l.id) FROM lms_lessons l WHERE l.course_id = c.id) AS total_lessons
        FROM lms_courses c
        JOIN lms_enrollments e ON e.course_id = c.id AND e.user_id = ?
        WHERE c.brand_id = ? AND c.status = "active" AND (c.access_type = "free" OR e.access_status = "active")
        ORDER BY c.created_at DESC
    ');
    $stmt->execute([(int)$user['id'], $brandId]);
}
$myCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung rekap progress per course
$progressData = [];
foreach ($myCourses as $mc) {
    $cId = (int)$mc['id'];
    $progressData[$cId] = lms_get_course_progress($pdo, (int)$user['id'], $cId);
}

// Rekomendasi course premium jika masih free
$upsellCourses = [];
if ($role === 'free') {
    $stmtUp = $pdo->prepare('
        SELECT * FROM lms_courses
        WHERE brand_id = ? AND access_type = "premium" AND status = "active"
        LIMIT 2
    ');
    $stmtUp->execute([$brandId]);
    $upsellCourses = $stmtUp->fetchAll(PDO::FETCH_ASSOC);
}

render_lms_header($brand, $user, 'my_courses');
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:28px;flex-wrap:wrap;gap:16px;">
  <div>
    <h1 style="font-size:28px;font-weight:800;letter-spacing:-0.5px;">Dashboard My Courses</h1>
    <p style="color:var(--muted);font-size:14px;margin-top:4px;">
      Selamat datang kembali, <strong style="color:var(--text);"><?= htmlspecialchars($user['name']) ?></strong>! Lanjutkan materi pembelajaran Anda.
    </p>
  </div>
  <div style="display:flex;gap:12px;align-items:center;">
    <a href="/course/index.php" class="btn-lms btn-lms-ghost" style="padding:10px 18px;font-size:13.5px;">+ Eksplor eCourse Baru</a>
    <?php if ($role === 'free'): ?>
      <a href="/course/index.php" class="btn-lms btn-lms-gold" style="padding:10px 18px;font-size:13.5px;">Upgrade ke Paid User ★</a>
    <?php endif; ?>
  </div>
</div>

<?php if (isset($_GET['activated'])): ?>
  <div style="background:rgba(34,197,94,0.12);border:1px solid rgba(34,197,94,0.3);color:#bbf7d0;padding:16px 20px;border-radius:16px;margin-bottom:28px;display:flex;align-items:center;gap:12px;">
    <span style="font-size:22px;">🎉</span>
    <div>
      <strong style="color:#fff;font-size:15px;">Aktivasi Berhasil!</strong>
      <div style="font-size:13.5px;color:#dcfce7;margin-top:2px;">Role akun Anda resmi di-upgrade ke Paid User. Seluruh eCourse premium dan sertifikat kini aktif.</div>
    </div>
  </div>
<?php endif; ?>

<?php if (empty($myCourses)): ?>
  <div style="background:var(--surface);border:1px solid var(--border-soft);border-radius:20px;padding:48px 24px;text-align:center;">
    <div style="font-size:42px;margin-bottom:12px;">📚</div>
    <h3 style="font-size:20px;font-weight:800;margin-bottom:8px;">Belum Ada eCourse yang Terdaftar</h3>
    <p style="color:var(--muted);font-size:14px;max-width:480px;margin:0 auto 20px;">
      Anda dapat mulai belajar dengan memilih eCourse starter gratis di katalog resmi kami.
    </p>
    <a href="/course/index.php" class="btn-lms btn-lms-gold">Buka Katalog eCourse</a>
  </div>
<?php else: ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(360px, 1fr));gap:24px;">
    <?php foreach ($myCourses as $c): ?>
      <?php
        $cId = (int)$c['id'];
        $prog = $progressData[$cId] ?? ['percentage' => 0, 'completed_lessons' => 0, 'total_lessons' => 0, 'is_completed' => false];
        $nextLesson = lms_get_next_lesson($pdo, (int)$user['id'], $cId);
        $isFree = ($c['access_type'] === 'free');
      ?>
      <div style="background:var(--surface);border:1px solid var(--border-soft);border-radius:20px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 14px 40px rgba(0,0,0,0.3);">
        <div style="padding:22px;border-bottom:1px solid rgba(255,255,255,0.06);">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:12px;">
            <span class="role-pill <?= $isFree ? 'badge-free' : 'badge-paid' ?>">
              <?= $isFree ? 'FREE COURSE' : 'PAID ACCESS' ?>
            </span>
            <span style="font-size:12px;color:<?= $prog['is_completed'] ? 'var(--success)' : 'var(--gold-soft)' ?>;font-weight:800;">
              <?= $prog['is_completed'] ? '✓ Selesai 100%' : $prog['percentage'] . '% Berjalan' ?>
            </span>
          </div>

          <h3 style="font-size:18px;font-weight:800;line-height:1.35;margin-bottom:6px;">
            <a href="/course/<?= urlencode($c['slug']) ?>" style="color:var(--text);">
              <?= htmlspecialchars($c['title']) ?>
            </a>
          </h3>
          <p style="font-size:13px;color:var(--muted);line-height:1.5;">
            <?= htmlspecialchars($c['summary'] ?? '') ?>
          </p>
        </div>

        <div style="padding:22px;flex:1;display:flex;flex-direction:column;justify-content:space-between;gap:18px;">
          <div>
            <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--muted);margin-bottom:6px;">
              <span>Materi Selesai: <strong><?= $prog['completed_lessons'] ?></strong> / <?= $prog['total_lessons'] ?></span>
              <span style="font-weight:800;color:var(--gold-soft);"><?= $prog['percentage'] ?>%</span>
            </div>
            <div style="width:100%;height:8px;background:rgba(255,255,255,0.08);border-radius:999px;overflow:hidden;">
              <div style="width:<?= $prog['percentage'] ?>%;height:100%;background:linear-gradient(90deg, var(--gold), var(--gold-soft));border-radius:999px;"></div>
            </div>
          </div>

          <?php if ($nextLesson): ?>
            <div style="background:rgba(255,255,255,0.02);border:1px solid var(--border-soft);padding:12px 14px;border-radius:12px;font-size:12.5px;">
              <div style="color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:0.5px;">Materi Selanjutnya:</div>
              <div style="font-weight:700;color:var(--text);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                <?= htmlspecialchars($nextLesson['title']) ?>
              </div>
            </div>
          <?php endif; ?>

          <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding-top:10px;border-top:1px solid rgba(255,255,255,0.05);">
            <a href="/course/<?= urlencode($c['slug']) ?>" class="btn-lms btn-lms-ghost" style="padding:9px 16px;font-size:13px;">
              Daftar Isi
            </a>

            <?php if ($nextLesson): ?>
              <a href="/course/lesson.php?id=<?= (int)$nextLesson['id'] ?>" class="btn-lms btn-lms-gold" style="padding:9px 20px;font-size:13px;">
                Continue →
              </a>
            <?php else: ?>
              <a href="/course/<?= urlencode($c['slug']) ?>" class="btn-lms btn-lms-gold" style="padding:9px 20px;font-size:13px;">
                Review Materi
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if (!empty($upsellCourses)): ?>
  <div style="margin-top:48px;padding:32px;background:linear-gradient(135deg, rgba(30,30,28,0.95), rgba(20,20,18,0.95));border:1px solid var(--border-gold);border-radius:24px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
      <div>
        <span class="role-pill badge-paid">REKOMENDASI UPGRADE</span>
        <h2 style="font-size:22px;font-weight:800;margin-top:6px;">Buka Akses eCourse Premium</h2>
      </div>
      <a href="/course/index.php" style="color:var(--gold-soft);font-size:13.5px;font-weight:700;">Lihat Semua di Katalog →</a>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:20px;">
      <?php foreach ($upsellCourses as $up): ?>
        <div style="background:var(--surface);border:1px solid var(--border-soft);border-radius:18px;padding:20px;display:flex;flex-direction:column;justify-content:space-between;gap:16px;">
          <div>
            <h4 style="font-size:16.5px;font-weight:800;margin-bottom:6px;"><?= htmlspecialchars($up['title']) ?></h4>
            <p style="font-size:13px;color:var(--muted);line-height:1.5;"><?= htmlspecialchars($up['summary'] ?? '') ?></p>
          </div>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
            <div style="font-size:17px;font-weight:800;color:var(--gold-soft);">
              Rp <?= number_format((int)$up['price'], 0, ',', '.') ?>
            </div>
            <a href="/course/checkout.php?slug=<?= urlencode($up['slug']) ?>" class="btn-lms btn-lms-gold" style="padding:8px 16px;font-size:12.5px;">
              Ambil eCourse Ini
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<?php render_lms_footer($brand); ?>
