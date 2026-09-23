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
lms_seed_default_courses($pdo, $brandId);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function lh($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Sesi tidak valid. Silakan refresh halaman.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create_course') {
            $title = trim(clean($_POST['title'] ?? ''));
            $slug = slugify($_POST['slug'] ?? $title);
            $summary = trim(clean($_POST['summary'] ?? ''));
            $description = trim($_POST['description'] ?? '');
            $badgeLabel = trim(clean($_POST['badge_label'] ?? ''));
            $accessType = $_POST['access_type'] === 'premium' ? 'premium' : 'free';
            $price = $accessType === 'premium' ? max(0, (int)($_POST['price'] ?? 0)) : 0;
            $certificateEnabled = isset($_POST['certificate_enabled']) ? 1 : 0;

            if ($title === '' || $slug === '') {
                $errors[] = 'Judul dan slug eCourse wajib diisi.';
            } else {
                try {
                    $stmt = $pdo->prepare('
                        INSERT INTO lms_courses (brand_id, title, slug, summary, description, badge_label, access_type, price, certificate_enabled, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "active")
                    ');
                    $stmt->execute([$brandId, $title, $slug, $summary, $description, $badgeLabel ?: null, $accessType, $price, $certificateEnabled]);
                    $messages[] = 'eCourse baru berhasil dibuat.';
                } catch (PDOException $e) {
                    $errors[] = 'Slug sudah digunakan atau data tidak valid.';
                }
            }
        } elseif ($action === 'toggle_status') {
            $courseId = (int)($_POST['course_id'] ?? 0);
            $newStatus = $_POST['status'] === 'draft' ? 'draft' : 'active';
            $stmt = $pdo->prepare('UPDATE lms_courses SET status = ? WHERE id = ? AND brand_id = ?');
            $stmt->execute([$newStatus, $courseId, $brandId]);
            $messages[] = 'Status eCourse berhasil diperbarui.';
        } elseif ($action === 'add_module') {
            $courseId = (int)($_POST['course_id'] ?? 0);
            $moduleTitle = trim(clean($_POST['module_title'] ?? ''));
            if ($moduleTitle !== '') {
                $stmtOrder = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM lms_modules WHERE course_id = ?');
                $stmtOrder->execute([$courseId]);
                $nextOrder = (int)$stmtOrder->fetchColumn();

                $stmt = $pdo->prepare('
                    SELECT id FROM lms_courses WHERE id = ? AND brand_id = ?
                ');
                $stmt->execute([$courseId, $brandId]);
                if ($stmt->fetchColumn()) {
                    $stmtIns = $pdo->prepare('INSERT INTO lms_modules (course_id, title, sort_order) VALUES (?, ?, ?)');
                    $stmtIns->execute([$courseId, $moduleTitle, $nextOrder]);
                    $messages[] = 'Modul baru berhasil ditambahkan.';
                }
            }
        } elseif ($action === 'add_lesson') {
            $moduleId = (int)($_POST['module_id'] ?? 0);
            $courseId = (int)($_POST['course_id'] ?? 0);
            $lessonTitle = trim(clean($_POST['lesson_title'] ?? ''));
            $contentType = in_array($_POST['content_type'] ?? '', ['video', 'pdf', 'quiz'], true) ? $_POST['content_type'] : 'video';
            $contentUrl = trim(clean($_POST['content_url'] ?? ''));
            $bodyText = trim($_POST['body_text'] ?? '');
            $isPremium = isset($_POST['is_premium']) ? 1 : 0;
            $duration = max(1, (int)($_POST['duration_minutes'] ?? 5));

            if ($lessonTitle === '') {
                $errors[] = 'Judul materi wajib diisi.';
            } else {
                $stmtCheck = $pdo->prepare('
                    SELECT m.id FROM lms_modules m
                    JOIN lms_courses c ON c.id = m.course_id
                    WHERE m.id = ? AND c.id = ? AND c.brand_id = ?
                ');
                $stmtCheck->execute([$moduleId, $courseId, $brandId]);
                if ($stmtCheck->fetchColumn()) {
                    $stmtOrder = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM lms_lessons WHERE module_id = ?');
                    $stmtOrder->execute([$moduleId]);
                    $nextOrder = (int)$stmtOrder->fetchColumn();

                    $stmtIns = $pdo->prepare('
                        INSERT INTO lms_lessons (course_id, module_id, title, content_type, content_url, body_text, is_premium, sort_order, duration_minutes)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ');
                    $stmtIns->execute([$courseId, $moduleId, $lessonTitle, $contentType, $contentUrl ?: null, $bodyText ?: null, $isPremium, $nextOrder, $duration]);
                    $messages[] = 'Materi pelajaran baru berhasil ditambahkan.';
                } else {
                    $errors[] = 'Modul tidak ditemukan.';
                }
            }
        } elseif ($action === 'delete_lesson') {
            $lessonId = (int)($_POST['lesson_id'] ?? 0);
            $stmt = $pdo->prepare('
                DELETE l FROM lms_lessons l
                JOIN lms_courses c ON c.id = l.course_id
                WHERE l.id = ? AND c.brand_id = ?
            ');
            $stmt->execute([$lessonId, $brandId]);
            $messages[] = 'Materi berhasil dihapus.';
        }
    }
}

$stmt = $pdo->prepare('
    SELECT c.*,
           (SELECT COUNT(*) FROM lms_enrollments e WHERE e.course_id = c.id AND e.access_status = "active") AS total_enrolled
    FROM lms_courses c
    WHERE c.brand_id = ?
    ORDER BY c.created_at DESC
');
$stmt->execute([$brandId]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil modul & lesson untuk setiap course (untuk expand panel)
$courseDetails = [];
foreach ($courses as $c) {
    $cId = (int)$c['id'];
    $stmtMods = $pdo->prepare('SELECT * FROM lms_modules WHERE course_id = ? ORDER BY sort_order ASC');
    $stmtMods->execute([$cId]);
    $mods = $stmtMods->fetchAll(PDO::FETCH_ASSOC);

    foreach ($mods as &$m) {
        $stmtLes = $pdo->prepare('SELECT * FROM lms_lessons WHERE module_id = ? ORDER BY sort_order ASC');
        $stmtLes->execute([(int)$m['id']]);
        $m['lessons'] = $stmtLes->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($m);

    $courseDetails[$cId] = $mods;
}

$logoPath = $brand['logo_path'] ? '..' . $brand['logo_path'] : '../assets/logo.png';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola eCourse LMS - <?= lh($brand['name']) ?></title>
<style>
  <?= get_theme_css_vars($brand) ?>
  :root {
    --bg: #0B0B0A; --surface: #171716; --surface-elevated: #20201E;
    --border-gold: rgba(214,165,54,0.18); --gold: var(--brand-primary); --gold-soft: var(--brand-soft);
    --text: #F7F3E8; --muted: #A8A29A; --danger: #EF4444; --success: #22C55E;
  }
  * { box-sizing: border-box; }
  body { margin:0; min-height:100vh; color:var(--text); background: radial-gradient(circle at 84% 8%, color-mix(in srgb, var(--gold) 22%, transparent), transparent 30vw), linear-gradient(135deg, var(--bg), #090908); font-family: Inter, system-ui, sans-serif; }
  a { color:inherit; }
  .topbar { position:sticky; top:0; z-index:10; background:rgba(16,16,15,0.82); border-bottom:1px solid rgba(255,255,255,0.08); backdrop-filter:blur(16px); }
  .topbar-inner, .wrap { width:min(100%, 1320px); margin:0 auto; padding-left:32px; padding-right:32px; }
  .topbar-inner { min-height:82px; display:flex; align-items:center; justify-content:space-between; gap:20px; }
  .brand img { width:132px; max-height:58px; object-fit:contain; }
  .wrap { padding-top:28px; padding-bottom:56px; }
  h1 { font-family: Georgia, serif; font-size: clamp(28px, 4vw, 40px); margin-bottom:8px; }
  h1 span { color:var(--gold-soft); }
  .subtitle, .muted { color:var(--muted); }
  .panel { border:1px solid var(--border-gold); border-radius:22px; background:linear-gradient(145deg, rgba(32,32,30,0.94), rgba(23,23,22,0.94)); box-shadow:0 22px 70px rgba(0,0,0,0.28); padding:24px; margin-top:20px; }
  .alerts { display:grid; gap:10px; margin:16px 0; }
  .alert { border-radius:14px; padding:12px 14px; font-size:14px; }
  .alert-ok { color:#bbf7d0; background:rgba(34,197,94,0.10); border:1px solid rgba(34,197,94,0.26); }
  .alert-error { color:#fecaca; background:rgba(239,68,68,0.10); border:1px solid rgba(239,68,68,0.28); }
  .grid2 { display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap:14px; }
  .field { display:grid; gap:6px; }
  label { font-size:12.5px; font-weight:800; color:var(--text); }
  input, select, textarea { width:100%; min-height:44px; color:var(--text); background:rgba(255,255,255,0.045); border:1px solid rgba(255,255,255,0.1); border-radius:12px; padding:10px 13px; font:inherit; outline:none; }
  textarea { min-height:80px; resize:vertical; }
  .btn { display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:10px 16px; border-radius:12px; border:1px solid transparent; font-size:13px; font-weight:800; cursor:pointer; }
  .btn-primary { color:#111; background:linear-gradient(135deg, var(--gold), var(--gold-soft)); }
  .btn-secondary { color:var(--text); background:rgba(255,255,255,0.05); border-color:rgba(255,255,255,0.1); }
  .btn-danger { color:#fecaca; background:rgba(239,68,68,0.1); border-color:rgba(239,68,68,0.3); }
  .course-card { border:1px solid rgba(255,255,255,0.08); border-radius:18px; padding:18px; margin-bottom:14px; background:rgba(255,255,255,0.015); }
  .pill { display:inline-flex; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:800; }
  .pill-free { background:rgba(59,130,246,0.15); color:#93c5fd; }
  .pill-premium { background:rgba(214,165,54,0.18); color:var(--gold-soft); }
  .pill-active { background:rgba(34,197,94,0.15); color:#86efac; }
  .pill-draft { background:rgba(255,255,255,0.08); color:var(--muted); }
  details.module-box { border:1px solid rgba(255,255,255,0.06); border-radius:12px; padding:10px 14px; margin-top:10px; background:rgba(255,255,255,0.01); }
  details.module-box summary { cursor:pointer; font-weight:700; font-size:13.5px; }
  .lesson-row { display:flex; justify-content:space-between; align-items:center; gap:10px; padding:8px 0; border-bottom:1px solid rgba(255,255,255,0.04); font-size:13px; }
  .inline-form { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
  @media (max-width:760px) { .grid2 { grid-template-columns:1fr; } }
</style>
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="dashboard.php"><img src="<?= lh($logoPath) ?>" alt="<?= lh($brand['name']) ?>"></a>
    <?php render_admin_nav('lms-courses'); ?>
  </div>
</header>

<main class="wrap">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:12px;">
    <div>
      <h1 style="margin:0 0 6px;">Kelola <span>eCourse & Materi</span></h1>
      <p class="subtitle" style="margin:0;">Buat, kelola, dan susun struktur kursus modular untuk platform LMS.</p>
    </div>
    <a href="lms-course-form.php" class="btn btn-primary" style="padding:12px 20px;font-size:14px;">+ Form Pengisian Course Baru</a>
  </div>

  <?php if (!empty($messages) || !empty($errors)): ?>
    <div class="alerts">
      <?php foreach ($messages as $m): ?><div class="alert alert-ok"><?= lh($m) ?></div><?php endforeach; ?>
      <?php foreach ($errors as $e): ?><div class="alert alert-error"><?= lh($e) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <section class="panel">
    <h2 style="margin-top:0;">Tambah eCourse Baru</h2>
    <form method="POST" class="grid2">
      <input type="hidden" name="csrf_token" value="<?= lh($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="action" value="create_course">
      <div class="field"><label>Judul eCourse</label><input name="title" required placeholder="Contoh: Strategi Investasi Emas Lanjutan"></div>
      <div class="field"><label>Slug URL (opsional)</label><input name="slug" placeholder="auto dari judul jika kosong"></div>
      <div class="field" style="grid-column:1/-1;"><label>Ringkasan Singkat</label><input name="summary" placeholder="Ringkasan 1 kalimat"></div>
      <div class="field" style="grid-column:1/-1;"><label>Deskripsi Lengkap</label><textarea name="description" placeholder="Deskripsi detail kurikulum"></textarea></div>
      <div class="field"><label>Badge Label (opsional)</label><input name="badge_label" placeholder="Contoh: Best Seller"></div>
      <div class="field"><label>Tipe Akses</label>
        <select name="access_type" id="accessTypeSelect">
          <option value="free">Free</option>
          <option value="premium">Premium (Berbayar)</option>
        </select>
      </div>
      <div class="field" id="priceField" style="display:none;"><label>Harga (Rp)</label><input type="number" name="price" min="0" step="1000" placeholder="299000"></div>
      <div class="field" style="display:flex;align-items:center;gap:10px;margin-top:24px;">
        <input type="checkbox" name="certificate_enabled" id="certChk" style="width:auto;min-height:auto;">
        <label for="certChk" style="margin:0;">Aktifkan Sertifikat Kelulusan</label>
      </div>
      <div style="grid-column:1/-1;"><button type="submit" class="btn btn-primary">Buat eCourse</button></div>
    </form>
  </section>

  <section class="panel">
    <h2 style="margin-top:0;">Daftar eCourse (<?= count($courses) ?>)</h2>

    <?php foreach ($courses as $c): ?>
      <?php $cId = (int)$c['id']; ?>
      <div class="course-card">
        <div style="display:flex;justify-content:space-between;align-items:start;gap:14px;flex-wrap:wrap;">
          <div>
            <div style="display:flex;gap:8px;margin-bottom:6px;flex-wrap:wrap;">
              <span class="pill <?= $c['access_type'] === 'free' ? 'pill-free' : 'pill-premium' ?>"><?= $c['access_type'] === 'free' ? 'FREE' : 'PREMIUM' ?></span>
              <span class="pill <?= $c['status'] === 'active' ? 'pill-active' : 'pill-draft' ?>"><?= strtoupper($c['status']) ?></span>
              <?php if ($c['certificate_enabled']): ?><span class="pill pill-premium">🎓 Sertifikat</span><?php endif; ?>
            </div>
            <h3 style="margin:0 0 4px;font-size:16.5px;"><?= lh($c['title']) ?></h3>
            <p class="muted" style="font-size:13px;margin:0;"><?= lh($c['summary']) ?></p>
            <p style="font-size:12px;color:var(--muted);margin-top:6px;">
              Slug: <code><?= lh($c['slug']) ?></code> &middot; Harga: <?= $c['access_type'] === 'free' ? 'Gratis' : 'Rp ' . number_format((int)$c['price'], 0, ',', '.') ?> &middot; Enrolled: <?= (int)$c['total_enrolled'] ?> user
            </p>
          </div>
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <a href="lms-course-form.php?id=<?= $cId ?>" class="btn btn-secondary">✎ Edit Course</a>
            <a href="/course/<?= urlencode($c['slug']) ?>" target="_blank" class="btn btn-secondary">Preview →</a>
            <form method="POST" class="inline-form">
              <input type="hidden" name="csrf_token" value="<?= lh($_SESSION['csrf_token']) ?>">
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="course_id" value="<?= $cId ?>">
              <input type="hidden" name="status" value="<?= $c['status'] === 'active' ? 'draft' : 'active' ?>">
              <button type="submit" class="btn <?= $c['status'] === 'active' ? 'btn-danger' : 'btn-secondary' ?>"><?= $c['status'] === 'active' ? 'Nonaktifkan' : 'Aktifkan' ?></button>
            </form>
          </div>
        </div>

        <details class="module-box" style="margin-top:14px;">
          <summary>📚 Kelola Modul & Materi (<?= count($courseDetails[$cId] ?? []) ?> modul)</summary>

          <div style="margin-top:12px;">
            <?php foreach (($courseDetails[$cId] ?? []) as $m): ?>
              <details class="module-box">
                <summary><?= lh($m['title']) ?> (<?= count($m['lessons']) ?> materi)</summary>
                <div style="margin-top:10px;">
                  <?php foreach ($m['lessons'] as $les): ?>
                    <div class="lesson-row">
                      <span><?= lh($les['title']) ?> <span class="muted">[<?= $les['content_type'] ?><?= $les['is_premium'] ? ', premium' : '' ?>]</span></span>
                      <form method="POST" onsubmit="return confirm('Hapus materi ini?');">
                        <input type="hidden" name="csrf_token" value="<?= lh($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="delete_lesson">
                        <input type="hidden" name="lesson_id" value="<?= (int)$les['id'] ?>">
                        <button type="submit" class="btn btn-danger" style="padding:5px 10px;font-size:11px;">Hapus</button>
                      </form>
                    </div>
                  <?php endforeach; ?>

                  <form method="POST" style="margin-top:12px;display:grid;gap:8px;grid-template-columns:repeat(2,1fr);background:rgba(255,255,255,0.02);padding:12px;border-radius:10px;">
                    <input type="hidden" name="csrf_token" value="<?= lh($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="add_lesson">
                    <input type="hidden" name="course_id" value="<?= $cId ?>">
                    <input type="hidden" name="module_id" value="<?= (int)$m['id'] ?>">
                    <input name="lesson_title" placeholder="Judul materi" required style="grid-column:1/-1;">
                    <select name="content_type"><option value="video">Video</option><option value="pdf">PDF</option><option value="quiz">Quiz</option></select>
                    <input name="duration_minutes" type="number" min="1" placeholder="Durasi (menit)" value="5">
                    <input name="content_url" placeholder="URL video/PDF (opsional)" style="grid-column:1/-1;">
                    <textarea name="body_text" placeholder="Catatan/instruksi materi" style="grid-column:1/-1;"></textarea>
                    <label style="display:flex;align-items:center;gap:8px;grid-column:1/-1;"><input type="checkbox" name="is_premium" style="width:auto;min-height:auto;"> Materi khusus Premium</label>
                    <button type="submit" class="btn btn-primary" style="grid-column:1/-1;">+ Tambah Materi</button>
                  </form>
                </div>
              </details>
            <?php endforeach; ?>

            <form method="POST" class="inline-form" style="margin-top:12px;">
              <input type="hidden" name="csrf_token" value="<?= lh($_SESSION['csrf_token']) ?>">
              <input type="hidden" name="action" value="add_module">
              <input type="hidden" name="course_id" value="<?= $cId ?>">
              <input name="module_title" placeholder="Nama modul baru" required style="flex:1;min-width:220px;">
              <button type="submit" class="btn btn-secondary">+ Tambah Modul</button>
            </form>
          </div>
        </details>
      </div>
    <?php endforeach; ?>
  </section>
</main>
<script>
  var accessSel = document.getElementById('accessTypeSelect');
  var priceField = document.getElementById('priceField');
  function syncPriceField() { priceField.style.display = accessSel.value === 'premium' ? 'grid' : 'none'; }
  accessSel.addEventListener('change', syncPriceField);
  syncPriceField();
</script>
</body>
</html>
