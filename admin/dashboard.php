<?php
require_once __DIR__ . '/../config.php';
session_start();

if (empty($_SESSION['admin_authenticated'])) {
    header('Location: login.php');
    exit;
}

$pdo = get_db();

// Total pendaftar
$totalLeads = $pdo->query('SELECT COUNT(*) FROM leads')->fetchColumn();

// Total pengundang aktif
$totalReferrers = $pdo->query('SELECT COUNT(*) FROM referrers')->fetchColumn();

// Ringkasan per event
$perEvent = $pdo->query('
    SELECT e.slug, e.name, e.status, COUNT(l.id) AS total_leads
    FROM events e
    LEFT JOIN leads l ON l.event_slug = e.slug
    GROUP BY e.id
    ORDER BY total_leads DESC
')->fetchAll(PDO::FETCH_ASSOC);

// Leaderboard pengundang (gabungan semua event — cocokkan event_slug agar tidak salah hitung)
$leaderboard = $pdo->query('
    SELECT r.name, r.whatsapp, r.ref_code, r.event_slug, COUNT(l.id) AS total
    FROM referrers r
    LEFT JOIN leads l ON l.event_slug = r.event_slug AND l.ref_code = r.ref_code
    GROUP BY r.id
    ORDER BY total DESC, r.created_at ASC
    LIMIT 20
')->fetchAll(PDO::FETCH_ASSOC);

// Data pendaftar terbaru
$leads = $pdo->query('
    SELECT l.name, l.email, l.whatsapp, l.kota, l.ref_code, l.event_slug, l.extra_fields, l.created_at,
           r.name AS referrer_name
    FROM leads l
    LEFT JOIN referrers r ON r.event_slug = l.event_slug AND r.ref_code = l.ref_code
    ORDER BY l.created_at DESC
    LIMIT 500
')->fetchAll(PDO::FETCH_ASSOC);

/** Ubah JSON extra_fields jadi teks ringkas yang enak dibaca di tabel admin */
function format_extra_fields(?string $json): string {
    if (!$json) return '—';
    $data = json_decode($json, true);
    if (!is_array($data) || empty($data)) return '—';
    $parts = [];
    foreach ($data as $key => $val) {
        $label = ucwords(str_replace('_', ' ', $key));
        $value = ucwords(str_replace('_', ' ', (string) $val));
        $parts[] = "{$label}: {$value}";
    }
    return implode(' · ', $parts);
}
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
    <nav style="display:flex; align-items:center; gap:18px;">
      <a href="dashboard.php" style="color:var(--gold); font-size:13.5px; text-decoration:none;">Dashboard</a>
      <a href="events.php" style="color:var(--muted); font-size:13.5px; text-decoration:none;">Kelola Event</a>
      <a href="logout.php" class="logout">Keluar →</a>
    </nav>
  </header>

  <div class="stats">
    <div class="stat-card"><div class="num"><?= (int)$totalLeads ?></div><div class="label">Total Pendaftar</div></div>
    <div class="stat-card"><div class="num"><?= (int)$totalReferrers ?></div><div class="label">Total Pengundang</div></div>
  </div>

  <h2>🗂️ Pendaftar per Event</h2>
  <?php if (empty($perEvent)): ?>
    <p class="empty">Belum ada event.</p>
  <?php else: ?>
  <div class="table-scroll">
    <table>
      <tr><th>Event</th><th>Status</th><th>Total Pendaftar</th></tr>
      <?php foreach ($perEvent as $pe): ?>
      <tr>
        <td><?= htmlspecialchars($pe['name']) ?></td>
        <td><?= $pe['status'] === 'active' ? '🟢 Aktif' : '⚪ Diarsipkan' ?></td>
        <td><?= (int)$pe['total_leads'] ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>

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
      <tr><th>Waktu</th><th>Nama</th><th>Email</th><th>WhatsApp</th><th>Kota</th><th>Event</th><th>Info Tambahan</th><th>Diundang Oleh</th></tr>
      <?php foreach ($leads as $l): ?>
      <tr>
        <td><?= date('d M Y, H:i', strtotime($l['created_at'])) ?></td>
        <td><?= htmlspecialchars($l['name']) ?></td>
        <td><?= htmlspecialchars($l['email']) ?></td>
        <td><?= htmlspecialchars($l['whatsapp']) ?></td>
        <td><?= htmlspecialchars($l['kota']) ?></td>
        <td><?= htmlspecialchars($l['event_slug'] ?? 'default') ?></td>
        <td><?= htmlspecialchars(format_extra_fields($l['extra_fields'] ?? null)) ?></td>
        <td><?= htmlspecialchars($l['referrer_name'] ?? $l['ref_code'] ?? '-') ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
