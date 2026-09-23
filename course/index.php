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
lms_seed_default_courses($pdo, $brandId);

$user = lms_get_logged_user($pdo, $brandId);
$role = lms_current_role($user);

// Ambil list course aktif milik brand
$stmt = $pdo->prepare('
    SELECT c.*,
           (SELECT COUNT(l.id) FROM lms_lessons l WHERE l.course_id = c.id) AS total_lessons,
           (SELECT COUNT(m.id) FROM lms_modules m WHERE m.course_id = c.id) AS total_modules
    FROM lms_courses c
    WHERE c.brand_id = ? AND c.status = "active"
    ORDER BY (c.access_type = "free") DESC, c.created_at DESC
');
$stmt->execute([$brandId]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Map enrollments jika user sudah login
$enrolledCourseIds = [];
$courseProgressMap = [];
if ($user) {
    $stmtEnr = $pdo->prepare('
        SELECT course_id, access_status
        FROM lms_enrollments
        WHERE user_id = ? AND brand_id = ? AND access_status = "active"
    ');
    $stmtEnr->execute([(int)$user['id'], $brandId]);
    while ($row = $stmtEnr->fetch(PDO::FETCH_ASSOC)) {
        $enrolledCourseIds[] = (int)$row['course_id'];
    }

    foreach ($courses as $c) {
        $cId = (int)$c['id'];
        $courseProgressMap[$cId] = lms_get_course_progress($pdo, (int)$user['id'], $cId);
    }
}

render_lms_header($brand, $user, 'catalog');
?>
<section style="display:grid;grid-template-columns:1fr auto;gap:24px;align-items:center;padding:34px;background:radial-gradient(circle at 85% 20%, color-mix(in srgb, var(--gold-soft) 22%, transparent), transparent 30%), linear-gradient(135deg, rgba(30,30,28,0.96), rgba(20,20,18,0.95));border:1px solid var(--border-gold);border-radius:24px;margin-bottom:36px;box-shadow:0 20px 60px rgba(0,0,0,0.35);">
  <div>
    <div style="display:inline-flex;align-items:center;gap:8px;padding:6px 14px;border-radius:999px;background:var(--gold-glow);border:1px solid var(--border-gold);color:var(--gold-soft);font-size:12px;font-weight:800;letter-spacing:0.5px;margin-bottom:12px;">
      ✦ KATALOG ECOURSE RESMI
    </div>
    <h1 style="font-family:'Playfair Display', serif;font-size:clamp(28px, 4vw, 42px);line-height:1.15;margin-bottom:12px;">
      Belajar Cerdas, Bangun <span>Portofolio Emas</span> Nyata
    </h1>
    <p style="color:var(--muted);font-size:15px;line-height:1.6;max-width:680px;">
      Materi eCourse terstruktur mulai dari pemahaman dasar (Free) hingga akselerasi kepemilikan 100 gram emas murni (Premium). Terintegrasi dengan kuis evaluasi dan sertifikat kompetensi.
    </p>
  </div>
  <?php if (!$user): ?>
    <div style="display:flex;flex-direction:column;gap:10px;">
      <a href="/course/register.php" class="btn-lms btn-lms-gold" style="padding:14px 24px;font-size:14.5px;">Mulai Belajar Gratis</a>
      <a href="/course/login.php" class="btn-lms btn-lms-ghost" style="padding:12px 24px;font-size:13.5px;">Sudah Punya Akun? Login</a>
    </div>
  <?php else: ?>
    <div style="background:rgba(255,255,255,0.03);padding:18px 24px;border-radius:18px;border:1px solid var(--border-soft);text-align:right;">
      <div style="font-size:12px;color:var(--muted);">Status Akses Anda</div>
      <div style="font-size:16px;font-weight:800;color:var(--gold-soft);margin-top:2px;">
        <?= strtoupper($role) ?> USER
      </div>
      <a href="/course/my-courses.php" class="btn-lms btn-lms-gold" style="margin-top:10px;padding:9px 18px;font-size:13px;">Buka My Courses →</a>
    </div>
  <?php endif; ?>
</section>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
  <div>
    <h2 style="font-size:22px;font-weight:800;">Daftar eCourse Tersedia</h2>
    <p style="color:var(--muted);font-size:13.5px;">Pilih materi pembelajaran sesuai target portofolio Anda</p>
  </div>
  <div style="display:flex;gap:10px;">
    <span class="role-pill badge-free">Free: Dapat diakses langsung</span>
    <span class="role-pill badge-paid">Premium: Butuh Akses Paid User</span>
  </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(360px, 1fr));gap:24px;">
  <?php foreach ($courses as $c): ?>
    <?php
      $cId = (int)$c['id'];
      $isFree = $c['access_type'] === 'free';
      $isEnrolled = in_array($cId, $enrolledCourseIds, true);
      $canAccess = lms_can_access_course($user, $c, $isEnrolled);
      $progress = $courseProgressMap[$cId] ?? null;
    ?>
    <div style="background:var(--surface);border:1px solid var(--border-soft);border-radius:20px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 12px 36px rgba(0,0,0,0.28);transition:all 200ms ease;">
      <div style="padding:22px;background:linear-gradient(180deg, rgba(255,255,255,0.03), transparent);border-bottom:1px solid rgba(255,255,255,0.06);">
        <div style="display:flex;justify-content:space-between;align-items:start;gap:12px;margin-bottom:12px;">
          <span class="role-pill <?= $isFree ? 'badge-free' : 'badge-paid' ?>">
            <?= $isFree ? 'FREE ACCESS' : 'PREMIUM COURSE' ?>
          </span>
          <?php if (!empty($c['badge_label'])): ?>
            <span style="font-size:11px;font-weight:700;color:var(--muted);background:rgba(255,255,255,0.05);padding:3px 8px;border-radius:6px;">
              <?= htmlspecialchars($c['badge_label']) ?>
            </span>
          <?php endif; ?>
        </div>
        <h3 style="font-size:18px;font-weight:800;line-height:1.35;margin-bottom:8px;color:var(--text);">
          <?= htmlspecialchars($c['title']) ?>
        </h3>
        <p style="font-size:13px;color:var(--muted);line-height:1.55;min-height:42px;">
          <?= htmlspecialchars($c['summary'] ?? '') ?>
        </p>
      </div>

      <div style="padding:22px;flex:1;display:flex;flex-direction:column;justify-content:space-between;gap:18px;">
        <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:10px;text-align:center;background:rgba(255,255,255,0.02);padding:10px;border-radius:12px;border:1px solid var(--border-soft);font-size:12px;">
          <div>
            <div style="color:var(--muted);">Modul</div>
            <div style="font-weight:800;font-size:14px;color:var(--text);margin-top:2px;"><?= (int)$c['total_modules'] ?></div>
          </div>
          <div>
            <div style="color:var(--muted);">Materi</div>
            <div style="font-weight:800;font-size:14px;color:var(--text);margin-top:2px;"><?= (int)$c['total_lessons'] ?></div>
          </div>
          <div>
            <div style="color:var(--muted);">Sertifikat</div>
            <div style="font-weight:800;font-size:14px;color:<?= $c['certificate_enabled'] ? 'var(--gold-soft)' : 'var(--muted)' ?>;margin-top:2px;">
              <?= $c['certificate_enabled'] ? 'Tersedia' : 'Belum' ?>
            </div>
          </div>
        </div>

        <?php if ($user && $progress && $progress['total_lessons'] > 0): ?>
          <div>
            <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px;">
              <span style="color:var(--muted);">Progress Belajar</span>
              <span style="font-weight:800;color:var(--gold-soft);"><?= $progress['percentage'] ?>% Selesai</span>
            </div>
            <div style="width:100%;height:8px;background:rgba(255,255,255,0.08);border-radius:999px;overflow:hidden;">
              <div style="width:<?= $progress['percentage'] ?>%;height:100%;background:linear-gradient(90deg, var(--gold), var(--gold-soft));border-radius:999px;"></div>
            </div>
          </div>
        <?php endif; ?>

        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding-top:10px;border-top:1px solid rgba(255,255,255,0.05);">
          <div>
            <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;">Biaya Kursus</div>
            <div style="font-size:18px;font-weight:800;color:<?= $isFree ? '#93c5fd' : 'var(--gold-soft)' ?>;">
              <?= $isFree ? 'GRATIS' : 'Rp ' . number_format((int)$c['price'], 0, ',', '.') ?>
            </div>
          </div>

          <div>
            <?php if (!$user): ?>
              <a href="/course/<?= urlencode($c['slug']) ?>" class="btn-lms btn-lms-ghost" style="padding:9px 16px;font-size:13px;">
                Lihat Detail
              </a>
            <?php elseif ($canAccess): ?>
              <a href="/course/<?= urlencode($c['slug']) ?>" class="btn-lms btn-lms-gold" style="padding:9px 18px;font-size:13px;">
                <?= ($progress && $progress['percentage'] > 0) ? 'Continue →' : 'Mulai Belajar' ?>
              </a>
            <?php else: ?>
              <a href="/course/checkout.php?slug=<?= urlencode($c['slug']) ?>" class="btn-lms btn-lms-gold" style="padding:9px 18px;font-size:13px;">
                Upgrade Paid →
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php render_lms_footer($brand); ?>
