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
        // ---- Simpan hadiah per peringkat (1-10, semua opsional) ----
        for ($rank = 1; $rank <= 10; $rank++) {
            $text = trim($_POST['reward_' . $rank] ?? '');
            if ($text === '') {
                $stmt = $pdo->prepare('DELETE FROM event_rewards WHERE event_slug = ? AND rank = ?');
                $stmt->execute([$eventSlug, $rank]);
            } else {
                $stmt = $pdo->prepare('
                    INSERT INTO event_rewards (event_slug, rank, reward_text)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE reward_text = VALUES(reward_text)
                ');
                $stmt->execute([$eventSlug, $rank, $text]);
            }
        }

        // ---- Hapus gambar jika diminta ----
        if (isset($_POST['remove_image']) && !empty($event['reward_image'])) {
            delete_reward_image($event['reward_image']);
            $stmt = $pdo->prepare('UPDATE events SET reward_image = NULL WHERE slug = ?');
            $stmt->execute([$eventSlug]);
            $event['reward_image'] = null;
        }

        // ---- Upload gambar baru jika ada ----
        if (isset($_FILES['reward_image']) && $_FILES['reward_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['reward_image'];
            if ($file['size'] > MAX_REWARD_IMAGE_SIZE) {
                $notice = 'Ukuran gambar terlalu besar. Maksimal ' . (MAX_REWARD_IMAGE_SIZE / 1024 / 1024) . ' MB.';
                $noticeType = 'error';
            } else {
                $imagePath = save_reward_image($file['tmp_name'], $file['name'], $eventSlug);
                if (!$imagePath) {
                    $notice = 'Gagal upload gambar. Pastikan file adalah gambar (' . implode(', ', ALLOWED_REWARD_IMAGE_EXT) . ').';
                    $noticeType = 'error';
                } else {
                    $stmt = $pdo->prepare('UPDATE events SET reward_image = ? WHERE slug = ?');
                    $stmt->execute([$imagePath, $eventSlug]);
                    $event['reward_image'] = $imagePath;
                }
            }
        }

        if (!$notice) {
            $notice = 'Pengaturan hadiah berhasil disimpan.';
            $noticeType = 'success';
        }
    }
}

$rewards = get_event_rewards($eventSlug);
$rewardByRank = [];
foreach ($rewards as $r) {
    $rewardByRank[(int)$r['rank']] = $r['reward_text'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Atur Hadiah — <?= htmlspecialchars($event['name']) ?> — rahasiaemas.id</title>
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

  .field { margin-bottom: 14px; }
  .field label { display: block; font-size: 13px; color: var(--gold-soft); margin-bottom: 6px; font-weight: 600; }
  .field input[type="text"], .field input[type="file"] {
    width: 100%; background: var(--charcoal); border: 1px solid rgba(255,255,255,0.15); border-radius: 10px;
    padding: 12px 14px; color: var(--white); font-size: 14px; font-family: inherit; outline: none;
  }
  .field input:focus { border-color: var(--gold); }
  .field .hint { font-size: 12px; color: var(--muted); margin-top: 6px; }
  .checkbox-row { display: flex; align-items: center; gap: 8px; margin: 10px 0 18px; font-size: 13.5px; color: var(--muted); }

  .btn { background: var(--gold); color: var(--charcoal); font-weight: 700; font-size: 14px; padding: 12px 22px; border: none; border-radius: 10px; cursor: pointer; }

  .current-image { max-width: 100%; border-radius: 12px; border: 1px solid rgba(201,168,76,0.3); margin-bottom: 14px; display: block; }
</style>
</head>
<body>
<div class="wrap">
  <header>
    <h1>🏆 Atur Hadiah — <?= htmlspecialchars($event['name']) ?></h1>
    <nav>
      <a href="events.php">← Kelola Event</a>
    </nav>
  </header>

  <?php if ($notice): ?>
    <div class="notice <?= $noticeType ?>"><?= htmlspecialchars($notice) ?></div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <div class="panel">
      <h2>🎁 Hadiah per Peringkat</h2>
      <p class="desc">Isi hadiah untuk peringkat yang punya hadiah saja. Kosongkan yang tidak dipakai — tidak akan ditampilkan di halaman challenge publik.</p>
      <?php for ($rank = 1; $rank <= 10; $rank++): ?>
      <div class="field">
        <label>Peringkat <?= $rank ?></label>
        <input type="text" name="reward_<?= $rank ?>" maxlength="255" placeholder="contoh: Voucher belanja emas 1 gram" value="<?= htmlspecialchars($rewardByRank[$rank] ?? '') ?>">
      </div>
      <?php endfor; ?>
    </div>

    <div class="panel">
      <h2>🖼️ Gambar Info Hadiah</h2>
      <p class="desc">Poster/infografis hadiah yang akan tampil di halaman challenge publik. Format: <?= implode(', ', ALLOWED_REWARD_IMAGE_EXT) ?>, maksimal <?= (int)(MAX_REWARD_IMAGE_SIZE / 1024 / 1024) ?> MB.</p>

      <?php if (!empty($event['reward_image'])): ?>
        <img src="<?= htmlspecialchars($event['reward_image']) ?>" alt="Gambar hadiah saat ini" class="current-image">
        <div class="checkbox-row">
          <input type="checkbox" name="remove_image" id="remove_image">
          <label for="remove_image">Hapus gambar ini</label>
        </div>
      <?php endif; ?>

      <div class="field">
        <label>Upload Gambar Baru</label>
        <input type="file" name="reward_image" accept=".png,.jpg,.jpeg,.webp,.gif">
      </div>
    </div>

    <button type="submit" class="btn">Simpan Pengaturan Hadiah</button>
  </form>
</div>
</body>
</html>
