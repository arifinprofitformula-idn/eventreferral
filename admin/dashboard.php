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
$settingsMessage = $_SESSION['settings_message'] ?? null;
$settingsError = $_SESSION['settings_error'] ?? null;
unset($_SESSION['settings_message'], $_SESSION['settings_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_event_settings') {
    try {
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Sesi tidak valid. Silakan refresh halaman lalu coba lagi.');
        }
        save_event_settings($_POST);
        $_SESSION['settings_message'] = 'Detail acara berhasil diperbarui.';
    } catch (Exception $e) {
        $_SESSION['settings_error'] = $e->getMessage();
    }
    header('Location: dashboard.php');
    exit;
}

$eventSettings = get_event_settings();

// Total pendaftar
$totalLeads = $pdo->query('SELECT COUNT(*) FROM leads')->fetchColumn();

// Total pengundang aktif
$totalReferrers = $pdo->query('SELECT COUNT(*) FROM referrers')->fetchColumn();

// Leaderboard pengundang
$leaderboard = $pdo->query('
    SELECT r.name, r.whatsapp, r.ref_code, COUNT(l.id) AS total
    FROM referrers r
    LEFT JOIN leads l ON l.ref_code = r.ref_code
    GROUP BY r.id
    ORDER BY total DESC, r.created_at ASC
    LIMIT 20
')->fetchAll(PDO::FETCH_ASSOC);

// Data pendaftar terbaru
$leads = $pdo->query('
    SELECT l.name, l.email, l.whatsapp, l.kota, l.ref_code, l.created_at,
           r.name AS referrer_name
    FROM leads l
    LEFT JOIN referrers r ON r.ref_code = l.ref_code
    ORDER BY l.created_at DESC
    LIMIT 500
')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Admin — rahasiaemas.id</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root { --charcoal:#1A1A1A; --charcoal-soft:#242424; --gold:#C9A84C; --gold-soft:#E8D5A3; --white:#FAFAFA; --muted:#9C9992; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: var(--charcoal); color: var(--white); font-family: 'Poppins', sans-serif; padding: 24px; }
  .wrap { max-width: 1100px; margin: 0 auto; }
  header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 12px; }
  h1 { font-family: 'Playfair Display', serif; color: var(--gold); font-size: 24px; }
  .logout { color: var(--muted); font-size: 13.5px; text-decoration: none; }
  .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 40px; }
  .stat-card { background: var(--charcoal-soft); border: 1px solid rgba(201,168,76,0.2); border-radius: 14px; padding: 20px; }
  .stat-card .num { font-family: 'Playfair Display', serif; color: var(--gold); font-size: 32px; font-weight: 800; }
  .stat-card .label { color: var(--muted); font-size: 13px; margin-top: 4px; }
  h2 { font-family: 'Playfair Display', serif; color: var(--gold-soft); font-size: 18px; margin: 36px 0 16px; }
  .settings-card {
    background: var(--charcoal-soft);
    border: 1px solid rgba(201,168,76,0.2);
    border-radius: 14px;
    padding: 22px;
    margin-bottom: 40px;
  }
  .settings-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
  .field.full { grid-column: 1 / -1; }
  label { display: block; color: var(--gold-soft); font-size: 13px; font-weight: 600; margin-bottom: 7px; }
  input {
    width: 100%;
    background: var(--charcoal);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 10px;
    color: var(--white);
    font-family: inherit;
    font-size: 14px;
    padding: 12px 14px;
    outline: none;
  }
  input:focus { border-color: var(--gold); }
  .btn-save {
    background: var(--gold);
    color: var(--charcoal);
    font-weight: 700;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-size: 14px;
    padding: 12px 18px;
    margin-top: 18px;
  }
  .alert {
    border-radius: 10px;
    font-size: 13.5px;
    margin-bottom: 16px;
    padding: 12px 14px;
  }
  .alert.success { background: rgba(76,201,120,0.12); color: #7CD79A; }
  .alert.error { background: rgba(217,116,58,0.12); color: #E8956B; }
  @media (max-width: 720px) { .settings-grid { grid-template-columns: 1fr; } }
  .table-scroll { overflow-x: auto; border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; }
  table { width: 100%; border-collapse: collapse; font-size: 13.5px; min-width: 600px; }
  th { background: var(--charcoal-soft); color: var(--gold-soft); text-align: left; padding: 12px 14px; font-weight: 600; white-space: nowrap; }
  td { padding: 11px 14px; border-top: 1px solid rgba(255,255,255,0.06); white-space: nowrap; }
  tr:hover td { background: rgba(255,255,255,0.02); }
  .rank { color: var(--gold); font-weight: 700; }
  .btn-export {
    display: inline-block; background: var(--gold); color: var(--charcoal); font-weight: 700;
    font-size: 13.5px; padding: 10px 18px; border-radius: 10px; text-decoration: none; margin-bottom: 16px;
  }
  .empty { color: var(--muted); font-size: 14px; padding: 24px; text-align: center; }
</style>
</head>
<body>
<div class="wrap">
  <header>
    <h1>📊 Dashboard rahasiaemas.id</h1>
    <a href="logout.php" class="logout">Keluar →</a>
  </header>

  <div class="stats">
    <div class="stat-card"><div class="num"><?= (int)$totalLeads ?></div><div class="label">Total Pendaftar</div></div>
    <div class="stat-card"><div class="num"><?= (int)$totalReferrers ?></div><div class="label">Total Pengundang</div></div>
  </div>

  <h2>⚙️ Pengaturan Acara</h2>
  <div class="settings-card">
    <?php if ($settingsMessage): ?><div class="alert success"><?= htmlspecialchars($settingsMessage) ?></div><?php endif; ?>
    <?php if ($settingsError): ?><div class="alert error"><?= htmlspecialchars($settingsError) ?></div><?php endif; ?>
    <form method="POST">
      <input type="hidden" name="action" value="save_event_settings">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <div class="settings-grid">
        <div class="field">
          <label for="event_day">Hari & Tanggal</label>
          <input type="text" id="event_day" name="event_day" value="<?= htmlspecialchars($eventSettings['event_day']) ?>" required>
        </div>
        <div class="field">
          <label for="event_time">Waktu</label>
          <input type="text" id="event_time" name="event_time" value="<?= htmlspecialchars($eventSettings['event_time']) ?>" required>
        </div>
        <div class="field full">
          <label for="event_location">Lokasi</label>
          <input type="text" id="event_location" name="event_location" value="<?= htmlspecialchars($eventSettings['event_location']) ?>" required>
        </div>
        <div class="field">
          <label for="event_speaker">Pembicara</label>
          <input type="text" id="event_speaker" name="event_speaker" value="<?= htmlspecialchars($eventSettings['event_speaker']) ?>" required>
        </div>
        <div class="field">
          <label for="event_capacity">Kapasitas</label>
          <input type="text" id="event_capacity" name="event_capacity" value="<?= htmlspecialchars($eventSettings['event_capacity']) ?>" required>
        </div>
      </div>
      <button type="submit" class="btn-save">Simpan Detail Acara</button>
    </form>
  </div>

  <h2>🏆 Leaderboard Pengundang Teraktif</h2>
  <?php if (empty($leaderboard)): ?>
    <p class="empty">Belum ada pengundang.</p>
  <?php else: ?>
  <div class="table-scroll">
    <table>
      <tr><th>#</th><th>Nama</th><th>WhatsApp</th><th>Kode Link</th><th>Jumlah Pendaftar</th></tr>
      <?php foreach ($leaderboard as $i => $r): ?>
      <tr>
        <td class="rank">#<?= $i + 1 ?></td>
        <td><?= htmlspecialchars($r['name']) ?></td>
        <td><?= htmlspecialchars($r['whatsapp']) ?></td>
        <td><?= htmlspecialchars($r['ref_code']) ?></td>
        <td><?= (int)$r['total'] ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>

  <h2>📋 Data Pendaftar (500 Terbaru)</h2>
  <a href="export.php" class="btn-export">⬇ Export ke CSV</a>
  <?php if (empty($leads)): ?>
    <p class="empty">Belum ada pendaftar.</p>
  <?php else: ?>
  <div class="table-scroll">
    <table>
      <tr><th>Waktu</th><th>Nama</th><th>Email</th><th>WhatsApp</th><th>Kota</th><th>Diundang Oleh</th></tr>
      <?php foreach ($leads as $l): ?>
      <tr>
        <td><?= date('d M Y, H:i', strtotime($l['created_at'])) ?></td>
        <td><?= htmlspecialchars($l['name']) ?></td>
        <td><?= htmlspecialchars($l['email']) ?></td>
        <td><?= htmlspecialchars($l['whatsapp']) ?></td>
        <td><?= htmlspecialchars($l['kota']) ?></td>
        <td><?= htmlspecialchars($l['referrer_name'] ?? $l['ref_code'] ?? '-') ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
