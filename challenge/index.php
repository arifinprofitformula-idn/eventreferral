<?php
require_once __DIR__ . '/../config.php';

$eventSlug = isset($_GET['event']) ? clean($_GET['event']) : '';

$pdo = get_db();

// Daftar semua event aktif, untuk dropdown pemilih
$allEvents = $pdo->query("SELECT slug, name FROM events WHERE status = 'active' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$selectedEvent = null;
if ($eventSlug !== '') {
    $selectedEvent = get_event_by_slug($eventSlug);
    if (!$selectedEvent || $selectedEvent['status'] !== 'active') {
        $eventSlug = '';
        $selectedEvent = null;
    }
}

// Ambil leaderboard: jika event dipilih -> hanya event itu. Jika tidak -> semua event digabung.
if ($eventSlug !== '') {
    $stmt = $pdo->prepare('
        SELECT r.name, COUNT(l.id) AS total
        FROM referrers r
        LEFT JOIN leads l ON l.event_slug = r.event_slug AND l.ref_code = r.ref_code
        WHERE r.event_slug = ?
        GROUP BY r.id
        ORDER BY total DESC, r.created_at ASC
        LIMIT 50
    ');
    $stmt->execute([$eventSlug]);
} else {
    $stmt = $pdo->query('
        SELECT r.name, SUM(cnt.total) AS total
        FROM referrers r
        LEFT JOIN (
            SELECT event_slug, ref_code, COUNT(*) AS total
            FROM leads
            GROUP BY event_slug, ref_code
        ) cnt ON cnt.event_slug = r.event_slug AND cnt.ref_code = r.ref_code
        GROUP BY r.name
        ORDER BY total DESC
        LIMIT 50
    ');
}
$leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = $selectedEvent ? $selectedEvent['name'] : 'Semua Acara';

$rewards = $selectedEvent ? get_event_rewards($eventSlug) : [];
$rewardImage = $selectedEvent['reward_image'] ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Challenge Pengundang Terbanyak — rahasiaemas.id</title>
<meta name="description" content="Pantau siapa pengundang paling aktif di acara rahasiaemas.id, update secara real-time.">
<link rel="icon" href="/assets/logo.png">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root { --charcoal:#1A1A1A; --charcoal-soft:#242424; --gold:#C9A84C; --gold-soft:#E8D5A3; --white:#FAFAFA; --muted:#9C9992; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: var(--charcoal); color: var(--white); font-family: 'Poppins', sans-serif; padding: 40px 20px 80px; }
  .wrap { max-width: 680px; margin: 0 auto; }
  .top { text-align: center; margin-bottom: 36px; }
  .top img { width: 64px; margin: 0 auto 20px; }
  .badge {
    display: inline-block; border: 1px solid var(--gold); color: var(--gold-soft);
    font-size: 12.5px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase;
    padding: 6px 16px; border-radius: 999px; margin-bottom: 18px;
  }
  h1 { font-family: 'Playfair Display', serif; color: var(--gold); font-size: clamp(24px, 6vw, 34px); margin-bottom: 10px; }
  p.sub { color: var(--muted); font-size: 14.5px; max-width: 420px; margin: 0 auto; }

  .selector { text-align: center; margin: 28px 0 8px; }
  .selector select {
    background: var(--charcoal-soft); color: var(--white); border: 1px solid rgba(201,168,76,0.3);
    border-radius: 10px; padding: 10px 16px; font-size: 14px; font-family: inherit; cursor: pointer;
  }

  .list { margin-top: 32px; }
  .row {
    display: flex; align-items: center; gap: 16px;
    background: var(--charcoal-soft); border: 1px solid rgba(255,255,255,0.06);
    border-radius: 14px; padding: 16px 20px; margin-bottom: 10px;
  }
  .row.rank-1 { border-color: var(--gold); background: rgba(201,168,76,0.08); }
  .rank-num {
    font-family: 'Playfair Display', serif; font-weight: 800; font-size: 20px;
    color: var(--gold); width: 34px; text-align: center; flex-shrink: 0;
  }
  .row.rank-1 .rank-num { font-size: 26px; }
  .medal { font-size: 20px; width: 34px; text-align: center; flex-shrink: 0; }
  .r-name { flex: 1; font-weight: 600; font-size: 15px; }
  .r-total { color: var(--gold-soft); font-weight: 700; font-size: 15px; white-space: nowrap; }
  .empty { text-align: center; color: var(--muted); padding: 60px 20px; font-size: 14.5px; }
  footer { text-align: center; margin-top: 48px; color: var(--muted); font-size: 12.5px; }
  footer a { color: var(--gold-soft); text-decoration: none; }
  .refresh-note { text-align: center; color: var(--muted); font-size: 12px; margin-top: 24px; }

  .rewards-box {
    background: var(--charcoal-soft); border: 1px solid rgba(201,168,76,0.25); border-radius: 16px;
    padding: 24px; margin: 28px 0;
  }
  .rewards-box h2 { font-family: 'Playfair Display', serif; color: var(--gold); font-size: 18px; text-align: center; margin-bottom: 18px; }
  .rewards-image { width: 100%; border-radius: 12px; border: 1px solid rgba(201,168,76,0.3); margin-bottom: 18px; display: block; }
  .reward-list { list-style: none; }
  .reward-row {
    display: flex; align-items: center; gap: 12px; padding: 10px 0;
    border-bottom: 1px solid rgba(255,255,255,0.06); font-size: 14.5px;
  }
  .reward-row:last-child { border-bottom: none; }
  .reward-rank { color: var(--gold); font-weight: 700; width: 34px; text-align: center; flex-shrink: 0; }
  .reward-text { flex: 1; }
</style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <img src="/assets/logo.png" alt="rahasiaemas.id">
    <span class="badge">🏆 Challenge Pengundang</span>
    <h1><?= htmlspecialchars($pageTitle) ?></h1>
    <p class="sub">Papan peringkat siapa yang paling banyak mengundang orang untuk hadir. Update setiap ada pendaftar baru.</p>
  </div>

  <?php if ($selectedEvent && ($rewardImage || !empty($rewards))): ?>
  <div class="rewards-box">
    <h2>🏆 Hadiah Challenge</h2>
    <?php if ($rewardImage): ?>
      <img src="<?= htmlspecialchars($rewardImage) ?>" alt="Info hadiah challenge" class="rewards-image">
    <?php endif; ?>
    <?php if (!empty($rewards)): ?>
      <?php $rewardMedals = ['🥇', '🥈', '🥉']; ?>
      <ul class="reward-list">
        <?php foreach ($rewards as $r): ?>
          <li class="reward-row">
            <span class="reward-rank"><?= $rewardMedals[$r['rank'] - 1] ?? '#' . (int)$r['rank'] ?></span>
            <span class="reward-text"><?= htmlspecialchars($r['reward_text']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if (count($allEvents) > 1): ?>
  <div class="selector">
    <select onchange="if(this.value){window.location.href='/challenge/?event='+this.value}else{window.location.href='/challenge/'}">
      <option value="">Semua Acara (Gabungan)</option>
      <?php foreach ($allEvents as $ev): ?>
        <option value="<?= htmlspecialchars($ev['slug']) ?>" <?= $ev['slug'] === $eventSlug ? 'selected' : '' ?>>
          <?= htmlspecialchars($ev['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>

  <div class="list">
    <?php if (empty($leaderboard)): ?>
      <p class="empty">Belum ada pengundang untuk ditampilkan di sini. Jadilah yang pertama! 🚀</p>
    <?php else: ?>
      <?php $medals = ['🥇', '🥈', '🥉']; ?>
      <?php foreach ($leaderboard as $i => $row): ?>
        <?php if ((int)$row['total'] === 0) continue; ?>
        <div class="row <?= $i === 0 ? 'rank-1' : '' ?>">
          <?php if ($i < 3): ?>
            <div class="medal"><?= $medals[$i] ?></div>
          <?php else: ?>
            <div class="rank-num">#<?= $i + 1 ?></div>
          <?php endif; ?>
          <div class="r-name"><?= htmlspecialchars($row['name']) ?></div>
          <div class="r-total"><?= (int)$row['total'] ?> orang</div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <p class="refresh-note">🔄 Muat ulang halaman untuk melihat update terbaru.</p>

  <footer>
    <a href="/">← Kembali ke rahasiaemas.id</a>
  </footer>
</div>
</body>
</html>
