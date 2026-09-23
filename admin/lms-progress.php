<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_nav.php';
require_once __DIR__ . '/../includes/lms_core.php';
start_secure_session();

$brand = require_admin_for_brand(get_current_brand());
$brandId = (int)$brand['id'];
$pdo = get_db();

lms_ensure_schema($pdo);

function lp_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$stmt = $pdo->prepare('
    SELECT c.id, c.title, c.access_type,
           (SELECT COUNT(*) FROM lms_lessons l WHERE l.course_id = c.id) AS total_lessons,
           (SELECT COUNT(*) FROM lms_enrollments e WHERE e.course_id = c.id AND e.access_status = "active") AS total_enrolled
    FROM lms_courses c
    WHERE c.brand_id = ? AND c.status = "active"
    ORDER BY c.created_at DESC
');
$stmt->execute([$brandId]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selectedCourseId = (int)($_GET['course_id'] ?? ($courses[0]['id'] ?? 0));

$learners = [];
if ($selectedCourseId > 0) {
    $stmt = $pdo->prepare('
        SELECT u.id, u.name, u.email, u.primary_role, e.enrolled_at,
               (SELECT COUNT(*) FROM lms_lessons l WHERE l.course_id = ?) AS total_lessons,
               (SELECT COUNT(DISTINCT p.lesson_id) FROM lms_lesson_progress p WHERE p.user_id = u.id AND p.course_id = ?) AS completed_lessons
        FROM lms_enrollments e
        JOIN lms_users u ON u.id = e.user_id
        WHERE e.course_id = ? AND e.access_status = "active"
        ORDER BY e.enrolled_at DESC
    ');
    $stmt->execute([$selectedCourseId, $selectedCourseId, $selectedCourseId]);
    $learners = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$logoPath = $brand['logo_path'] ? '..' . $brand['logo_path'] : '../assets/logo.png';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Monitoring Progress LMS - <?= lp_h($brand['name']) ?></title>
<style>
  <?= get_theme_css_vars($brand) ?>
  :root { --bg:#0B0B0A; --surface:#171716; --border-gold:rgba(214,165,54,0.18); --gold:var(--brand-primary); --gold-soft:var(--brand-soft); --text:#F7F3E8; --muted:#A8A29A; }
  * { box-sizing:border-box; }
  body { margin:0; min-height:100vh; color:var(--text); background:radial-gradient(circle at 84% 8%, color-mix(in srgb, var(--gold) 22%, transparent), transparent 30vw), linear-gradient(135deg, var(--bg), #090908); font-family:Inter, system-ui, sans-serif; }
  a { color:inherit; }
  .topbar { position:sticky; top:0; z-index:10; background:rgba(16,16,15,0.82); border-bottom:1px solid rgba(255,255,255,0.08); backdrop-filter:blur(16px); }
  .topbar-inner, .wrap { width:min(100%, 1320px); margin:0 auto; padding-left:32px; padding-right:32px; }
  .topbar-inner { min-height:82px; display:flex; align-items:center; justify-content:space-between; gap:20px; }
  .brand img { width:132px; max-height:58px; object-fit:contain; }
  .wrap { padding-top:28px; padding-bottom:56px; }
  h1 { font-family:Georgia, serif; font-size:clamp(28px,4vw,40px); margin-bottom:8px; }
  h1 span { color:var(--gold-soft); }
  .subtitle, .muted { color:var(--muted); }
  .panel { border:1px solid var(--border-gold); border-radius:22px; background:linear-gradient(145deg, rgba(32,32,30,0.94), rgba(23,23,22,0.94)); padding:24px; margin-top:20px; box-shadow:0 22px 70px rgba(0,0,0,0.28); }
  select { min-height:44px; color:var(--text); background:rgba(255,255,255,0.045); border:1px solid rgba(255,255,255,0.1); border-radius:12px; padding:0 14px; font:inherit; margin-bottom:20px; }
  .table-scroll { overflow-x:auto; border:1px solid rgba(255,255,255,0.08); border-radius:16px; }
  table { width:100%; min-width:800px; border-collapse:collapse; font-size:13px; }
  th, td { padding:12px 14px; text-align:left; border-bottom:1px solid rgba(255,255,255,0.07); }
  th { background:rgba(32,32,30,0.95); }
  .progress-bar { width:140px; height:8px; background:rgba(255,255,255,0.08); border-radius:999px; overflow:hidden; }
  .progress-fill { height:100%; background:linear-gradient(90deg, var(--gold), var(--gold-soft)); }
  .pill { display:inline-flex; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:800; }
  .pill-free { background:rgba(59,130,246,0.15); color:#93c5fd; }
  .pill-paid { background:rgba(214,165,54,0.18); color:var(--gold-soft); }
</style>
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="dashboard.php"><img src="<?= lp_h($logoPath) ?>" alt="<?= lp_h($brand['name']) ?>"></a>
    <?php render_admin_nav('lms-progress'); ?>
  </div>
</header>

<main class="wrap">
  <h1>Monitoring <span>Progress Belajar</span></h1>
  <p class="subtitle">Pantau kemajuan penyelesaian materi setiap peserta per eCourse.</p>

  <section class="panel">
    <form method="GET">
      <label style="display:block;font-size:12.5px;font-weight:800;margin-bottom:6px;">Pilih eCourse</label>
      <select name="course_id" onchange="this.form.submit()">
        <?php foreach ($courses as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === $selectedCourseId ? 'selected' : '' ?>>
            <?= lp_h($c['title']) ?> (<?= (int)$c['total_enrolled'] ?> peserta)
          </option>
        <?php endforeach; ?>
      </select>
    </form>

    <div class="table-scroll">
      <table>
        <thead><tr><th>Peserta</th><th>Role</th><th>Terdaftar</th><th>Progress</th></tr></thead>
        <tbody>
          <?php foreach ($learners as $l): ?>
            <?php
              $total = max(1, (int)$l['total_lessons']);
              $done = (int)$l['completed_lessons'];
              $pct = min(100, (int)round(($done / $total) * 100));
            ?>
            <tr>
              <td><strong><?= lp_h($l['name']) ?></strong><br><span class="muted"><?= lp_h($l['email']) ?></span></td>
              <td><span class="pill pill-<?= $l['primary_role'] ?>"><?= strtoupper($l['primary_role']) ?></span></td>
              <td class="muted"><?= date('d M Y', strtotime($l['enrolled_at'])) ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:10px;">
                  <div class="progress-bar"><div class="progress-fill" style="width:<?= $pct ?>%;"></div></div>
                  <span style="font-weight:800;color:var(--gold-soft);font-size:12px;"><?= $pct ?>% (<?= $done ?>/<?= $total ?>)</span>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($learners)): ?>
            <tr><td colspan="4" class="muted" style="text-align:center;padding:30px;">Belum ada peserta terdaftar pada eCourse ini.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
</body>
</html>
