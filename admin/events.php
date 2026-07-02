<?php
require_once __DIR__ . '/../config.php';
start_secure_session();

if (empty($_SESSION['admin_authenticated'])) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pdo = get_db();
$notice = null;
$noticeType = 'success'; // success | error

// ==================== HANDLE ACTIONS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    $notice = 'Sesi tidak valid. Silakan refresh halaman lalu coba lagi.';
    $noticeType = 'error';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---- Upload / Update event via ZIP ----
    if (isset($_FILES['event_zip'])) {
        $file = $_FILES['event_zip'];
        $slugOverride = slugify(clean($_POST['slug_override'] ?? ''));
        $allowOverwrite = isset($_POST['allow_overwrite']);

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $notice = 'Upload gagal (kode error: ' . $file['error'] . '). Coba lagi.';
            $noticeType = 'error';
        } elseif ($file['size'] > MAX_ZIP_SIZE) {
            $notice = 'Ukuran ZIP terlalu besar. Maksimal ' . (MAX_ZIP_SIZE / 1024 / 1024) . ' MB.';
            $noticeType = 'error';
        } else {
            // Baca config.json dari dalam ZIP tanpa extract dulu, untuk ambil slug/nama
            $zip = new ZipArchive();
            if ($zip->open($file['tmp_name']) !== true) {
                $notice = 'File ZIP tidak valid atau rusak.';
                $noticeType = 'error';
            } else {
                $configRaw = $zip->getFromName('config.json');
                $hasIndex = $zip->locateName('index.html') !== false;
                $zip->close();

                if (!$hasIndex) {
                    $notice = 'ZIP harus berisi file index.html di posisi root (bukan di dalam subfolder).';
                    $noticeType = 'error';
                } elseif (!$configRaw) {
                    $notice = 'ZIP harus berisi file config.json di posisi root. Lihat README-EVENTS.md untuk formatnya.';
                    $noticeType = 'error';
                } else {
                    $cfg = json_decode($configRaw, true);
                    if (!$cfg || empty($cfg['name'])) {
                        $notice = 'config.json tidak valid atau field "name" kosong.';
                        $noticeType = 'error';
                    } else {
                        $slug = $slugOverride !== '' ? $slugOverride : slugify($cfg['slug'] ?? $cfg['name']);

                        if (!is_valid_event_slug($slug)) {
                            $notice = 'Slug "' . htmlspecialchars($slug) . '" tidak valid. Gunakan huruf kecil, angka, dan strip saja (contoh: funtactic-selling), dan bukan kata yang dicadangkan sistem.';
                            $noticeType = 'error';
                        } else {
                            $targetDir = EVENTS_DIR . '/' . $slug;
                            $existsAlready = is_dir($targetDir) && count(glob($targetDir . '/*')) > 0;

                            if ($existsAlready && !$allowOverwrite) {
                                $notice = 'Event dengan slug "' . htmlspecialchars($slug) . '" sudah ada. Centang "Timpa event yang sudah ada" jika Anda ingin memperbarui landing page-nya.';
                                $noticeType = 'error';
                            } else {
                                $extractResult = safe_extract_zip($file['tmp_name'], $targetDir);

                                if (!$extractResult['ok']) {
                                    $notice = 'Gagal mengekstrak ZIP: ' . htmlspecialchars($extractResult['error']);
                                    $noticeType = 'error';
                                } else {
                                    inject_sdk_script($targetDir . '/index.html');

                                    // Upsert ke tabel events
                                    $stmt = $pdo->prepare('
                                        INSERT INTO events (slug, name, status, whatsapp_default, event_day, event_time, event_location, event_speaker, event_capacity)
                                        VALUES (?, ?, "active", ?, ?, ?, ?, ?, ?)
                                        ON DUPLICATE KEY UPDATE
                                            name = VALUES(name), status = "active", whatsapp_default = VALUES(whatsapp_default),
                                            event_day = VALUES(event_day), event_time = VALUES(event_time),
                                            event_location = VALUES(event_location), event_speaker = VALUES(event_speaker),
                                            event_capacity = VALUES(event_capacity)
                                    ');
                                    $stmt->execute([
                                        $slug,
                                        clean($cfg['name']),
                                        normalize_whatsapp(clean($cfg['whatsapp'] ?? '')),
                                        clean($cfg['event_day'] ?? ''),
                                        clean($cfg['event_time'] ?? ''),
                                        clean($cfg['event_location'] ?? ''),
                                        clean($cfg['event_speaker'] ?? ''),
                                        clean($cfg['event_capacity'] ?? ''),
                                    ]);

                                    $skippedCount = count($extractResult['skipped']);
                                    $notice = 'Event "' . htmlspecialchars($cfg['name']) . '" berhasil ' . ($existsAlready ? 'diperbarui' : 'dipublikasikan') .
                                        '! (' . $extractResult['extracted'] . ' file diekstrak' .
                                        ($skippedCount > 0 ? ", {$skippedCount} file dilewati karena tidak diizinkan" : '') . ')';
                                    $noticeType = 'success';
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    // ---- Arsipkan / aktifkan kembali event ----
    if (isset($_POST['toggle_status']) && isset($_POST['slug'])) {
        $slug = clean($_POST['slug']);
        if ($slug !== DEFAULT_EVENT_SLUG) {
            $ev = get_event_by_slug($slug);
            if ($ev) {
                $newStatus = $ev['status'] === 'active' ? 'archived' : 'active';
                $stmt = $pdo->prepare('UPDATE events SET status = ? WHERE slug = ?');
                $stmt->execute([$newStatus, $slug]);
                $notice = 'Status event "' . htmlspecialchars($ev['name']) . '" diubah menjadi ' . ($newStatus === 'active' ? 'AKTIF' : 'DIARSIPKAN') . '.';
                $noticeType = 'success';
            }
        } else {
            $notice = 'Event utama (default) tidak bisa diarsipkan.';
            $noticeType = 'error';
        }
    }
}

// ==================== DATA UNTUK TAMPILAN ====================
$events = $pdo->query('
    SELECT e.*,
        (SELECT COUNT(*) FROM leads l WHERE l.event_slug = e.slug) AS total_leads,
        (SELECT COUNT(*) FROM referrers r WHERE r.event_slug = e.slug) AS total_referrers
    FROM events e
    ORDER BY (e.slug = "default") DESC, e.created_at DESC
')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Event — rahasiaemas.id</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root { --charcoal:#1A1A1A; --charcoal-soft:#242424; --gold:#C9A84C; --gold-soft:#E8D5A3; --white:#FAFAFA; --muted:#9C9992; --danger:#D9743A; --success:#4CC978; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: var(--charcoal); color: var(--white); font-family: 'Poppins', sans-serif; padding: 24px; }
  .wrap { max-width: 1000px; margin: 0 auto; }
  header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; flex-wrap: wrap; gap: 12px; }
  h1 { font-family: 'Playfair Display', serif; color: var(--gold); font-size: 22px; }
  nav a { color: var(--muted); font-size: 13.5px; text-decoration: none; margin-left: 18px; }
  nav a.active { color: var(--gold); }

  .notice { padding: 14px 18px; border-radius: 10px; margin-bottom: 24px; font-size: 14px; }
  .notice.success { background: rgba(76,201,120,0.12); color: #7CD79A; }
  .notice.error { background: rgba(217,116,58,0.12); color: #E8956B; }

  .panel { background: var(--charcoal-soft); border: 1px solid rgba(201,168,76,0.15); border-radius: 16px; padding: 26px; margin-bottom: 28px; }
  .panel h2 { font-family: 'Playfair Display', serif; color: var(--gold-soft); font-size: 17px; margin-bottom: 6px; }
  .panel .desc { color: var(--muted); font-size: 13px; margin-bottom: 20px; }

  .field { margin-bottom: 16px; }
  .field label { display: block; font-size: 13px; color: var(--gold-soft); margin-bottom: 6px; font-weight: 600; }
  .field input[type="text"], .field input[type="file"] {
    width: 100%; background: var(--charcoal); border: 1px solid rgba(255,255,255,0.15); border-radius: 10px;
    padding: 12px 14px; color: var(--white); font-size: 14px; font-family: inherit; outline: none;
  }
  .field input:focus { border-color: var(--gold); }
  .field .hint { font-size: 12px; color: var(--muted); margin-top: 6px; }
  .checkbox-row { display: flex; align-items: center; gap: 8px; margin-bottom: 18px; font-size: 13.5px; color: var(--muted); }

  .btn { background: var(--gold); color: var(--charcoal); font-weight: 700; font-size: 14px; padding: 12px 22px; border: none; border-radius: 10px; cursor: pointer; }
  .btn-sm { padding: 7px 14px; font-size: 12.5px; border-radius: 8px; }
  .btn-outline { background: transparent; border: 1px solid var(--muted); color: var(--muted); }

  .event-card {
    background: var(--charcoal-soft); border: 1px solid rgba(255,255,255,0.08); border-radius: 14px;
    padding: 18px 20px; margin-bottom: 12px; display: flex; flex-wrap: wrap; align-items: center; gap: 14px;
  }
  .event-card.archived { opacity: 0.55; }
  .ev-main { flex: 1; min-width: 200px; }
  .ev-name { font-weight: 700; font-size: 15px; margin-bottom: 4px; }
  .ev-slug { color: var(--muted); font-size: 12.5px; font-family: monospace; }
  .ev-stats { display: flex; gap: 18px; font-size: 12.5px; color: var(--gold-soft); }
  .ev-status { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 999px; text-transform: uppercase; }
  .ev-status.active { background: rgba(76,201,120,0.15); color: #7CD79A; }
  .ev-status.archived { background: rgba(156,153,146,0.15); color: var(--muted); }
  .ev-links { display: flex; gap: 8px; flex-wrap: wrap; }
  .ev-links a, .ev-links button {
    font-size: 12px; color: var(--gold-soft); text-decoration: none; border: 1px solid rgba(201,168,76,0.3);
    padding: 6px 12px; border-radius: 8px; background: transparent; cursor: pointer; font-family: inherit;
  }
</style>
</head>
<body>
<div class="wrap">
  <header>
    <h1>🗂️ Kelola Event</h1>
    <nav>
      <a href="dashboard.php">Dashboard</a>
      <a href="events.php" class="active">Kelola Event</a>
      <a href="logout.php">Keluar →</a>
    </nav>
  </header>

  <?php if ($notice): ?>
    <div class="notice <?= $noticeType ?>"><?= $notice /* sudah di-escape di titik penyusunan pesan */ ?></div>
  <?php endif; ?>

  <div class="panel">
    <h2>📦 Upload Event Baru (ZIP)</h2>
    <p class="desc">ZIP harus berisi <code>index.html</code> + <code>config.json</code> di posisi root, dan folder <code>assets/</code> jika ada gambar/CSS/JS tambahan. Lihat <strong>README-EVENTS.md</strong> untuk contoh lengkap & template siap pakai.</p>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <div class="field">
        <label>File ZIP</label>
        <input type="file" name="event_zip" accept=".zip" required>
      </div>
      <div class="field">
        <label>Slug URL (opsional)</label>
        <input type="text" name="slug_override" placeholder="Kosongkan untuk pakai slug dari config.json, contoh: funtactic-selling">
        <div class="hint">Hasil akhir: rahasiaemas.id/e/<strong>slug-ini</strong>/</div>
      </div>
      <div class="checkbox-row">
        <input type="checkbox" name="allow_overwrite" id="allow_overwrite">
        <label for="allow_overwrite">Timpa event yang sudah ada (jika slug sudah dipakai sebelumnya)</label>
      </div>
      <button type="submit" class="btn">Upload & Publikasikan</button>
    </form>
  </div>

  <div class="panel">
    <h2>📋 Semua Event</h2>
    <p class="desc"><?= count($events) ?> event terdaftar.</p>

    <?php foreach ($events as $ev): ?>
      <div class="event-card <?= $ev['status'] === 'archived' ? 'archived' : '' ?>">
        <div class="ev-main">
          <div class="ev-name">
            <?= htmlspecialchars($ev['name']) ?>
            <span class="ev-status <?= $ev['status'] ?>"><?= $ev['status'] === 'active' ? 'Aktif' : 'Diarsipkan' ?></span>
          </div>
          <div class="ev-slug"><?= $ev['slug'] === DEFAULT_EVENT_SLUG ? '/ (root domain)' : '/e/' . htmlspecialchars($ev['slug']) . '/' ?></div>
        </div>
        <div class="ev-stats">
          <span>👥 <?= (int)$ev['total_leads'] ?> pendaftar</span>
          <span>🔗 <?= (int)$ev['total_referrers'] ?> pengundang</span>
        </div>
        <div class="ev-links">
          <a href="<?= $ev['slug'] === DEFAULT_EVENT_SLUG ? '/' : EVENTS_URL_BASE . '/' . htmlspecialchars($ev['slug']) . '/' ?>" target="_blank">Lihat Halaman</a>
          <a href="/buat-link.php?event=<?= urlencode($ev['slug']) ?>" target="_blank">Buat Link</a>
          <a href="/challenge/?event=<?= urlencode($ev['slug']) ?>" target="_blank">Challenge</a>
          <a href="event-settings.php?event=<?= urlencode($ev['slug']) ?>">📅 Detail Acara</a>
          <a href="rewards.php?event=<?= urlencode($ev['slug']) ?>">🏆 Atur Hadiah</a>
          <a href="tracking.php?event=<?= urlencode($ev['slug']) ?>">📊 Tracking</a>
          <?php if ($ev['slug'] !== DEFAULT_EVENT_SLUG): ?>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
              <input type="hidden" name="toggle_status" value="1">
              <input type="hidden" name="slug" value="<?= htmlspecialchars($ev['slug']) ?>">
              <button type="submit" onclick="return confirm('Yakin ingin <?= $ev['status'] === 'active' ? 'mengarsipkan' : 'mengaktifkan kembali' ?> event ini?')">
                <?= $ev['status'] === 'active' ? 'Arsipkan' : 'Aktifkan' ?>
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
</body>
</html>
