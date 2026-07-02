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
        try {
            $updated = update_event_settings($eventSlug, $_POST);
            $event = array_merge($event, $updated);
            $notice = 'Detail acara berhasil diperbarui.';
            $noticeType = 'success';
        } catch (Exception $e) {
            $notice = $e->getMessage();
            $noticeType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Detail Acara — <?= htmlspecialchars($event['name']) ?> — rahasiaemas.id</title>
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

  .btn { background: var(--gold); color: var(--charcoal); font-weight: 700; font-size: 14px; padding: 12px 22px; border: none; border-radius: 10px; cursor: pointer; }
</style>
</head>
<body>
<div class="wrap">
  <header>
    <h1>📅 Detail Acara — <?= htmlspecialchars($event['name']) ?></h1>
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
      <h2>🗓️ Detail Acara</h2>
      <p class="desc">Nilai ini otomatis tampil di landing page event ini — tidak perlu edit file apapun.</p>

      <div class="field">
        <label>Hari &amp; Tanggal</label>
        <input type="text" name="event_day" value="<?= htmlspecialchars($event['event_day'] ?? '') ?>" required>
      </div>
      <div class="field">
        <label>Waktu</label>
        <input type="text" name="event_time" value="<?= htmlspecialchars($event['event_time'] ?? '') ?>" required>
      </div>
      <div class="field">
        <label>Lokasi</label>
        <input type="text" name="event_location" value="<?= htmlspecialchars($event['event_location'] ?? '') ?>" required>
      </div>
      <div class="field">
        <label>Pembicara</label>
        <input type="text" name="event_speaker" value="<?= htmlspecialchars($event['event_speaker'] ?? '') ?>" required>
      </div>
      <div class="field">
        <label>Kapasitas</label>
        <input type="text" name="event_capacity" value="<?= htmlspecialchars($event['event_capacity'] ?? '') ?>" required>
      </div>
    </div>

    <button type="submit" class="btn">Simpan Detail Acara</button>
  </form>
</div>
</body>
</html>
