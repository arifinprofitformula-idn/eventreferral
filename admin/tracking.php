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

$eventSlug = clean($_GET['event'] ?? '');
$event = $eventSlug !== '' ? get_event_by_slug($eventSlug) : null;
if (!$event) {
    header('Location: events.php');
    exit;
}

$notice = null;
$noticeType = 'success'; // success | error

// ==================== HANDLE ACTIONS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $notice = 'Sesi tidak valid. Silakan refresh halaman lalu coba lagi.';
        $noticeType = 'error';
    } else {
        $metaPixelId = trim($_POST['meta_pixel_id'] ?? '');
        $gaMeasurementId = trim($_POST['ga_measurement_id'] ?? '');

        $errors = [];
        if ($metaPixelId !== '' && !preg_match('/^\d{6,20}$/', $metaPixelId)) {
            $errors[] = 'Meta Pixel ID harus berupa angka saja (6-20 digit).';
        }
        if ($gaMeasurementId !== '' && !preg_match('/^G-[A-Za-z0-9]+$/', $gaMeasurementId)) {
            $errors[] = 'GA4 Measurement ID harus berformat G-XXXXXXXXXX.';
        }

        if (!empty($errors)) {
            $notice = implode(' ', $errors);
            $noticeType = 'error';
        } else {
            $stmt = $pdo->prepare('UPDATE events SET meta_pixel_id = ?, ga_measurement_id = ? WHERE slug = ?');
            $stmt->execute([
                $metaPixelId !== '' ? $metaPixelId : null,
                $gaMeasurementId !== '' ? $gaMeasurementId : null,
                $eventSlug,
            ]);
            $event['meta_pixel_id'] = $metaPixelId !== '' ? $metaPixelId : null;
            $event['ga_measurement_id'] = $gaMeasurementId !== '' ? $gaMeasurementId : null;
            $notice = 'Pengaturan tracking berhasil disimpan.';
            $noticeType = 'success';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tracking — <?= htmlspecialchars($event['name']) ?> — rahasiaemas.id</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root { --charcoal:#1A1A1A; --charcoal-soft:#242424; --gold:#C9A84C; --gold-soft:#E8D5A3; --white:#FAFAFA; --muted:#9C9992; --danger:#D9743A; --success:#4CC978; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: var(--charcoal); color: var(--white); font-family: 'Poppins', sans-serif; padding: 24px; }
  .wrap { max-width: 700px; margin: 0 auto; }
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
  .field input[type="text"] {
    width: 100%; background: var(--charcoal); border: 1px solid rgba(255,255,255,0.15); border-radius: 10px;
    padding: 12px 14px; color: var(--white); font-size: 14px; font-family: inherit; outline: none;
  }
  .field input:focus { border-color: var(--gold); }
  .field .hint { font-size: 12px; color: var(--muted); margin-top: 6px; }

  .btn { background: var(--gold); color: var(--charcoal); font-weight: 700; font-size: 14px; padding: 12px 22px; border: none; border-radius: 10px; cursor: pointer; }
</style>
</head>
<body>
<div class="wrap">
  <header>
    <h1>📊 Tracking — <?= htmlspecialchars($event['name']) ?></h1>
    <nav>
      <a href="events.php">← Kelola Event</a>
    </nav>
  </header>

  <?php if ($notice): ?>
    <div class="notice <?= $noticeType ?>"><?= htmlspecialchars($notice) ?></div>
  <?php endif; ?>

  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <div class="panel">
      <h2>🎯 Meta Pixel &amp; Google Analytics</h2>
      <p class="desc">ID ini dipakai untuk melacak PageView dan pendaftaran (Lead) di landing page event ini. Kosongkan untuk menonaktifkan.</p>

      <div class="field">
        <label>Meta Pixel ID</label>
        <input type="text" name="meta_pixel_id" placeholder="contoh: 1234567890123456" value="<?= htmlspecialchars($event['meta_pixel_id'] ?? '') ?>">
        <div class="hint">Angka saja, bisa dilihat di Meta Events Manager.</div>
      </div>

      <div class="field">
        <label>GA4 Measurement ID</label>
        <input type="text" name="ga_measurement_id" placeholder="contoh: G-XXXXXXXXXX" value="<?= htmlspecialchars($event['ga_measurement_id'] ?? '') ?>">
        <div class="hint">Bisa dilihat di Google Analytics &rarr; Admin &rarr; Data Streams.</div>
      </div>
    </div>

    <button type="submit" class="btn">Simpan Pengaturan Tracking</button>
  </form>
</div>
</body>
</html>
