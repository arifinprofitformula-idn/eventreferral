<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_nav.php';
start_secure_session();

$brand = require_admin_for_brand(get_current_brand());
$brandId = (int)$brand['id'];
$pdo = get_db();

$selectedMonth = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 12],
]);
$selectedYear = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 2000, 'max_range' => 2100],
]);
$selectedMonth = $selectedMonth ?: null;
$selectedYear = $selectedYear ?: null;

$monthNames = [
    1 => 'Januari',
    2 => 'Februari',
    3 => 'Maret',
    4 => 'April',
    5 => 'Mei',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'Agustus',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember',
];

// Fitur ini butuh migrate_v18 (attendance_source di event_attendance). Cek dulu supaya
// halaman ini tidak error mentah di server yang belum menjalankan migrasi tsb.
$reportReady = false;
try {
    $columnStmt = $pdo->query("SHOW COLUMNS FROM event_attendance LIKE 'attendance_source'");
    $reportReady = (bool)$columnStmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $reportReady = false;
}

$rows = [];
$availableYears = [];
if ($reportReady) {
    $yearStmt = $pdo->prepare('
        SELECT DISTINCT YEAR(event_date) AS event_year
        FROM events
        WHERE brand_id = ? AND event_date IS NOT NULL
        ORDER BY event_year DESC
    ');
    $yearStmt->execute([$brandId]);
    $availableYears = array_map('intval', array_filter($yearStmt->fetchAll(PDO::FETCH_COLUMN)));
    if ($selectedYear !== null && !in_array($selectedYear, $availableYears, true)) {
        $availableYears[] = $selectedYear;
        rsort($availableYears);
    }
    if (empty($availableYears)) {
        $availableYears[] = (int)date('Y');
    }

    $where = ['e.brand_id = ?'];
    $params = [$brandId];
    if ($selectedMonth !== null) {
        $where[] = 'MONTH(e.event_date) = ?';
        $params[] = $selectedMonth;
    }
    if ($selectedYear !== null) {
        $where[] = 'YEAR(e.event_date) = ?';
        $params[] = $selectedYear;
    }
    $whereSql = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT e.slug, e.name,
            (SELECT COUNT(*) FROM leads l WHERE l.brand_id = e.brand_id AND l.event_slug = e.slug) AS total_leads,
            (SELECT COUNT(*) FROM event_attendance ea WHERE ea.event_id = e.id AND ea.attendance_status = \"hadir\") AS total_hadir,
            (SELECT COUNT(*) FROM event_attendance ea WHERE ea.event_id = e.id AND ea.attendance_status = \"hadir\" AND ea.attendance_source = \"terdaftar\") AS hadir_terdaftar,
            (SELECT COUNT(*) FROM event_attendance ea WHERE ea.event_id = e.id AND ea.attendance_status = \"hadir\" AND ea.attendance_source = \"tidak_terdaftar\") AS hadir_walkin
        FROM events e
        WHERE {$whereSql}
        ORDER BY (e.slug = ?) DESC, (e.event_date IS NULL) ASC, e.event_date DESC, e.created_at DESC
    ");
    $params[] = $brand['default_event_slug'];
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$grandLeads = array_sum(array_column($rows, 'total_leads'));
$grandHadir = array_sum(array_column($rows, 'total_hadir'));
$grandConversion = $grandLeads > 0 ? round($grandHadir / $grandLeads * 100, 1) : 0;
$filterActive = $selectedMonth !== null || $selectedYear !== null;
$periodParts = [];
if ($selectedMonth !== null) {
    $periodParts[] = $monthNames[$selectedMonth] ?? '';
}
if ($selectedYear !== null) {
    $periodParts[] = (string)$selectedYear;
}
$periodLabel = $periodParts ? implode(' ', array_filter($periodParts)) : 'Semua periode';
$logoPath = $brand['logo_path'] ? '..' . $brand['logo_path'] : '../assets/logo.png';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rekap Kehadiran — <?= htmlspecialchars($brand['name']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
<style>
  <?= get_theme_css_vars($brand) ?>
  :root {
    --bg:#0B0B0A; --bg-soft:#10100F;
    --border-gold:color-mix(in srgb, var(--gold) 18%, transparent);
    --border-soft:rgba(255,255,255,0.09);
    --gold:var(--brand-primary); --gold-soft:var(--brand-soft);
    --text:#F7F3E8; --muted:#A8A29A; --success:#22C55E; --warning:#F59E0B;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    min-height: 100vh;
    background: linear-gradient(135deg, var(--bg) 0%, var(--bg-soft) 55%, #090908 100%);
    color: var(--text);
    font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  }
  a { color: inherit; }
  .topbar { position: sticky; top: 0; z-index: 20; background: rgba(16,16,15,0.85); border-bottom: 1px solid var(--border-soft); backdrop-filter: blur(14px); }
  .topbar-inner { width: min(100%, 1280px); margin: 0 auto; min-height: 76px; display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 12px 24px; }
  .brand { display: inline-flex; align-items: center; gap: 12px; text-decoration: none; }
  .brand img { width: 130px; height: auto; }
  .nav { display: flex; gap: 8px; flex-wrap: wrap; }
  .nav a { color: var(--muted); border: 1px solid transparent; border-radius: 999px; font-size: 13px; font-weight: 700; padding: 10px 14px; text-decoration: none; }
  .nav a.active, .nav a:hover { color: var(--gold-soft); background: color-mix(in srgb, var(--gold) 10%, transparent); border-color: var(--border-gold); }
  .wrap { width: min(100%, 1280px); margin: 0 auto; padding: 26px 24px 60px; }
  h1 { font-family: "Playfair Display", Georgia, serif; font-size: clamp(26px, 4vw, 36px); margin-bottom: 6px; }
  .subtitle { color: var(--muted); font-size: 14px; margin-bottom: 22px; }
  .stats { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 16px; margin-bottom: 22px; }
  .stat-card {
    background: linear-gradient(145deg, rgba(32,32,30,0.95), rgba(23,23,22,0.93));
    border: 1px solid var(--border-gold); border-radius: 20px; padding: 20px;
  }
  .stat-label { color: var(--muted); font-size: 11px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
  .stat-num { color: var(--text); font-size: 32px; font-weight: 900; line-height: 1; margin-top: 8px; }
  .section-card {
    background: linear-gradient(180deg, rgba(255,255,255,0.045), rgba(255,255,255,0.02));
    border: 1px solid var(--border-gold); border-radius: 22px; padding: 22px; margin-bottom: 18px;
  }
  .filter-card {
    display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap;
  }
  .filter-title { color: var(--muted); font-size: 12px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; margin-bottom: 6px; }
  .filter-period { font-size: 18px; font-weight: 900; }
  .filter-form { display: flex; align-items: flex-end; gap: 10px; flex-wrap: wrap; }
  .filter-field { display: grid; gap: 6px; min-width: 150px; }
  .filter-field label { color: var(--muted); font-size: 11px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
  .filter-field select {
    appearance: none; border: 1px solid var(--border-soft); border-radius: 12px; background: rgba(8,8,7,0.72);
    color: var(--text); font: inherit; font-size: 13px; font-weight: 700; min-height: 42px; padding: 0 36px 0 12px;
    background-image: linear-gradient(45deg, transparent 50%, var(--muted) 50%), linear-gradient(135deg, var(--muted) 50%, transparent 50%);
    background-position: calc(100% - 18px) 18px, calc(100% - 13px) 18px; background-size: 5px 5px, 5px 5px; background-repeat: no-repeat;
  }
  .filter-actions { display: flex; gap: 8px; align-items: center; }
  .btn {
    min-height: 42px; border-radius: 12px; border: 1px solid var(--border-gold); padding: 0 14px;
    color: var(--gold-soft); background: color-mix(in srgb, var(--gold) 12%, transparent); font-size: 13px; font-weight: 900;
    text-decoration: none; cursor: pointer;
  }
  .btn.secondary { display: inline-flex; align-items: center; color: var(--muted); border-color: var(--border-soft); background: rgba(255,255,255,0.04); }
  h2 { font-size: 18px; font-weight: 900; margin-bottom: 4px; }
  .desc { color: var(--muted); font-size: 13px; margin-bottom: 18px; }
  .table-scroll { overflow-x: auto; border: 1px solid var(--border-soft); border-radius: 16px; }
  table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13.5px; min-width: 720px; }
  th { background: rgba(32,32,30,0.98); text-align: left; padding: 14px 16px; font-weight: 800; white-space: nowrap; }
  td { padding: 14px 16px; border-top: 1px solid rgba(255,255,255,0.06); white-space: nowrap; }
  tbody tr:nth-child(even) td { background: rgba(255,255,255,0.02); }
  .pill { display: inline-flex; align-items: center; justify-content: center; min-width: 34px; border-radius: 999px; font-weight: 800; padding: 6px 10px; }
  .pill.gold { color: var(--gold-soft); background: color-mix(in srgb, var(--gold) 12%, transparent); border: 1px solid color-mix(in srgb, var(--gold) 22%, transparent); }
  .pill.success { color: #BBF7D0; background: rgba(34,197,94,0.14); border: 1px solid rgba(34,197,94,0.3); }
  .pill.muted { color: var(--muted); background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); }
  .conv-bar-track { width: 90px; height: 8px; background: rgba(255,255,255,0.08); border-radius: 999px; overflow: hidden; display: inline-block; vertical-align: middle; margin-right: 8px; }
  .conv-bar-fill { height: 100%; background: linear-gradient(90deg, var(--gold), var(--gold-soft)); }
  .empty { color: var(--muted); text-align: center; padding: 40px 20px; }
  .mobile-cards { display: none; }
  .event-card { background: rgba(8,8,7,0.34); border: 1px solid var(--border-soft); border-radius: 16px; padding: 16px; margin-bottom: 12px; }
  .event-card h3 { font-size: 15px; margin-bottom: 10px; }
  .event-card .row { display: flex; justify-content: space-between; font-size: 13px; color: var(--muted); padding: 4px 0; }
  .event-card .row strong { color: var(--text); }
  @media (max-width: 900px) { .stats { grid-template-columns: repeat(2, minmax(0,1fr)); } }
  @media (max-width: 760px) {
    .topbar-inner, .wrap { padding-left: 16px; padding-right: 16px; }
    .filter-card, .filter-form, .filter-field, .filter-actions { width: 100%; }
    .filter-actions .btn { flex: 1; }
    .desktop-table { display: none; }
    .mobile-cards { display: block; }
    .stats { grid-template-columns: 1fr 1fr; gap: 10px; }
    .stat-card { padding: 14px; border-radius: 16px; }
    .stat-num { font-size: 24px; }
  }
</style>
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="dashboard.php"><img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($brand['name']) ?>"></a>
    <?php render_admin_nav('event-attendance-report'); ?>
  </div>
</header>

<main class="wrap">
  <h1>Rekap Kehadiran</h1>
  <p class="subtitle">Perbandingan pendaftar vs kehadiran aktual, per event.</p>

  <?php if (!$reportReady): ?>
    <div class="section-card">
      <p class="empty">Fitur rekap kehadiran belum aktif di server ini. Jalankan migrasi <code>migrate_v18_alter_event_attendance_self_confirm.sql</code> terlebih dahulu.</p>
    </div>
  <?php else: ?>

  <section class="section-card filter-card">
    <div>
      <div class="filter-title">Periode Event</div>
      <div class="filter-period"><?= htmlspecialchars($periodLabel) ?></div>
    </div>
    <form class="filter-form" method="GET">
      <div class="filter-field">
        <label for="month">Bulan</label>
        <select id="month" name="month">
          <option value="">Semua Bulan</option>
          <?php foreach ($monthNames as $monthNumber => $monthName): ?>
            <option value="<?= (int)$monthNumber ?>" <?= $selectedMonth === (int)$monthNumber ? 'selected' : '' ?>><?= htmlspecialchars($monthName) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-field">
        <label for="year">Tahun</label>
        <select id="year" name="year">
          <option value="">Semua Tahun</option>
          <?php foreach ($availableYears as $year): ?>
            <option value="<?= (int)$year ?>" <?= $selectedYear === (int)$year ? 'selected' : '' ?>><?= (int)$year ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-actions">
        <button class="btn" type="submit">Terapkan</button>
        <?php if ($filterActive): ?>
          <a class="btn secondary" href="event-attendance-report.php">Reset</a>
        <?php endif; ?>
      </div>
    </form>
  </section>

  <?php if (empty($rows)): ?>
    <div class="section-card">
      <p class="empty"><?= $filterActive ? 'Tidak ada event pada periode ini.' : 'Belum ada event untuk brand ini.' ?></p>
    </div>
  <?php else: ?>

  <section class="stats">
    <article class="stat-card">
      <div class="stat-label">Total Pendaftar</div>
      <div class="stat-num"><?= (int)$grandLeads ?></div>
    </article>
    <article class="stat-card">
      <div class="stat-label">Total Konfirmasi Hadir</div>
      <div class="stat-num"><?= (int)$grandHadir ?></div>
    </article>
    <article class="stat-card">
      <div class="stat-label">Tingkat Konversi</div>
      <div class="stat-num"><?= $grandConversion ?>%</div>
    </article>
    <article class="stat-card">
      <div class="stat-label">Total Event</div>
      <div class="stat-num"><?= count($rows) ?></div>
    </article>
  </section>

  <section class="section-card">
    <h2>Breakdown per Event</h2>
    <p class="desc">Terdaftar = sudah daftar sebelumnya lalu konfirmasi hadir. Tidak Terdaftar = konfirmasi langsung di lokasi tanpa pernah daftar (walk-in).</p>

    <div class="table-scroll desktop-table">
      <table>
        <thead>
          <tr><th>Event</th><th>Pendaftar</th><th>Hadir</th><th>Terdaftar</th><th>Tidak Terdaftar</th><th>Konversi</th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <?php $conv = (int)$r['total_leads'] > 0 ? round((int)$r['total_hadir'] / (int)$r['total_leads'] * 100) : 0; ?>
          <tr>
            <td><?= htmlspecialchars($r['name']) ?></td>
            <td><span class="pill gold"><?= (int)$r['total_leads'] ?></span></td>
            <td><span class="pill success"><?= (int)$r['total_hadir'] ?></span></td>
            <td><span class="pill muted"><?= (int)$r['hadir_terdaftar'] ?></span></td>
            <td><span class="pill muted"><?= (int)$r['hadir_walkin'] ?></span></td>
            <td>
              <span class="conv-bar-track"><span class="conv-bar-fill" style="width:<?= $conv ?>%"></span></span>
              <?= $conv ?>%
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="mobile-cards">
      <?php foreach ($rows as $r): ?>
        <?php $conv = (int)$r['total_leads'] > 0 ? round((int)$r['total_hadir'] / (int)$r['total_leads'] * 100) : 0; ?>
        <div class="event-card">
          <h3><?= htmlspecialchars($r['name']) ?></h3>
          <div class="row"><span>Pendaftar</span><strong><?= (int)$r['total_leads'] ?></strong></div>
          <div class="row"><span>Hadir</span><strong><?= (int)$r['total_hadir'] ?></strong></div>
          <div class="row"><span>Terdaftar</span><strong><?= (int)$r['hadir_terdaftar'] ?></strong></div>
          <div class="row"><span>Tidak Terdaftar</span><strong><?= (int)$r['hadir_walkin'] ?></strong></div>
          <div class="row"><span>Konversi</span><strong><?= $conv ?>%</strong></div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <?php endif; ?>
  <?php endif; ?>
</main>
</body>
</html>
