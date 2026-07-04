<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
start_secure_session();

$brand = require_admin_for_brand(get_current_brand());
$brandId = (int)$brand['id'];

$pdo = get_db();

// Total pendaftar
$stmt = $pdo->prepare('SELECT COUNT(*) FROM leads WHERE brand_id = ?');
$stmt->execute([$brandId]);
$totalLeads = $stmt->fetchColumn();

// Total pengundang aktif
$stmt = $pdo->prepare('SELECT COUNT(*) FROM referrers WHERE brand_id = ?');
$stmt->execute([$brandId]);
$totalReferrers = $stmt->fetchColumn();

// Ringkasan pendaftar per event
$stmt = $pdo->prepare('
    SELECT e.slug, e.name,
        (SELECT COUNT(*) FROM leads l WHERE l.brand_id = e.brand_id AND l.event_slug = e.slug) AS total_leads,
        (SELECT COUNT(*) FROM referrers r WHERE r.brand_id = e.brand_id AND r.event_slug = e.slug) AS total_referrers
    FROM events e
    WHERE e.brand_id = ?
    ORDER BY (e.slug = "default") DESC, e.created_at DESC
');
$stmt->execute([$brandId]);
$eventSummary = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Leaderboard pengundang
// Hitungan leaderboard memakai WhatsApp unik per event berdasarkan pendaftaran pertama.
// Raw leads tetap disimpan apa adanya untuk audit dan follow-up.
$stmt = $pdo->prepare('
    SELECT r.name, r.whatsapp, r.ref_code, e.name AS event_name, COUNT(fl.id) AS total
    FROM referrers r
    LEFT JOIN (
        SELECT l1.id, l1.event_slug, l1.ref_code
        FROM leads l1
        INNER JOIN (
            SELECT event_slug, whatsapp, MIN(id) AS first_id
            FROM leads
            WHERE brand_id = ? AND whatsapp IS NOT NULL AND whatsapp <> ""
            GROUP BY event_slug, whatsapp
        ) first_lead ON first_lead.first_id = l1.id
    ) fl ON fl.event_slug = r.event_slug AND fl.ref_code = r.ref_code
    LEFT JOIN events e ON e.slug = r.event_slug AND e.brand_id = r.brand_id
    WHERE r.brand_id = ?
    GROUP BY r.id
    ORDER BY total DESC, r.created_at ASC
    LIMIT 20
');
$stmt->execute([$brandId, $brandId]);
$leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Data pendaftar terbaru
$stmt = $pdo->prepare('
    SELECT l.name, l.email, l.whatsapp, l.kota, l.ref_code, l.created_at,
           r.name AS referrer_name, e.name AS event_name
    FROM leads l
    LEFT JOIN referrers r ON r.event_slug = l.event_slug AND r.ref_code = l.ref_code AND r.brand_id = l.brand_id
    LEFT JOIN events e ON e.slug = l.event_slug AND e.brand_id = l.brand_id
    WHERE l.brand_id = ?
    ORDER BY l.created_at DESC
    LIMIT 500
');
$stmt->execute([$brandId]);
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalEvents = count($eventSummary);
$activeReferrals = count(array_filter($leaderboard, static fn ($row) => (int)$row['total'] > 0));
$logoPath = $brand['logo_path'] ? '..' . $brand['logo_path'] : '../assets/logo.png';

function whatsapp_link(?string $number): ?string
{
    $digits = preg_replace('/\D+/', '', (string)$number);
    if ($digits === '') {
        return null;
    }

    return 'https://wa.me/' . $digits;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Admin — <?= htmlspecialchars($brand['name']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  <?= get_theme_css_vars($brand) ?>
  :root {
    --bg: #0B0B0A;
    --bg-soft: #10100F;
    --surface: #171716;
    --surface-elevated: #20201E;
    --border-gold: color-mix(in srgb, var(--gold) 18%, transparent);
    --border-soft: rgba(255, 255, 255, 0.08);
    --gold: var(--brand-primary);
    --gold-soft: var(--brand-soft);
    --charcoal: var(--brand-charcoal);
    --text: #F7F3E8;
    --muted: #A8A29A;
    --success: #22C55E;
    --danger: #EF4444;
    --shadow: 0 22px 70px rgba(0, 0, 0, 0.34);
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html { scroll-behavior: smooth; }
  body {
    min-height: 100vh;
    background:
      radial-gradient(circle at 86% 8%, color-mix(in srgb, var(--gold) 22%, transparent), transparent 30vw),
      radial-gradient(circle at 8% 88%, color-mix(in srgb, var(--gold) 13%, transparent), transparent 34vw),
      linear-gradient(135deg, var(--bg) 0%, var(--bg-soft) 48%, #090908 100%);
    color: var(--text);
    font-family: 'Poppins', Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    padding: 0;
  }
  body::before {
    content: "";
    position: fixed;
    inset: 0;
    pointer-events: none;
    background-image:
      linear-gradient(rgba(255,255,255,0.025) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,0.018) 1px, transparent 1px);
    background-size: 52px 52px;
    mask-image: radial-gradient(circle at 50% 25%, black, transparent 74%);
  }
  a { color: inherit; }
  .topbar {
    position: sticky;
    top: 0;
    z-index: 20;
    background: rgba(16,16,15,0.78);
    border-bottom: 1px solid var(--border-soft);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
  }
  .topbar-inner, .wrap {
    width: min(100%, 1360px);
    margin: 0 auto;
    padding-left: 32px;
    padding-right: 32px;
  }
  .topbar-inner {
    min-height: 82px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 22px;
  }
  .brand {
    display: inline-flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
    text-decoration: none;
  }
  .brand img {
    width: 146px;
    height: auto;
    display: block;
    object-fit: contain;
    filter: drop-shadow(0 10px 20px rgba(0,0,0,0.32));
  }
  .brand-title {
    border-left: 1px solid var(--border-gold);
    color: var(--gold-soft);
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 18px;
    font-weight: 800;
    padding-left: 14px;
  }
  .nav {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
  }
  .nav a, .btn, .search-input {
    transition: transform 180ms ease, border-color 180ms ease, background 180ms ease, color 180ms ease, box-shadow 180ms ease;
  }
  .nav a {
    color: var(--muted);
    display: inline-flex;
    align-items: center;
    gap: 9px;
    border: 1px solid transparent;
    border-radius: 999px;
    font-size: 13.5px;
    font-weight: 600;
    line-height: 1;
    padding: 12px 15px;
    text-decoration: none;
  }
  .nav a:hover { color: var(--text); background: rgba(255,255,255,0.04); }
  .nav a.active {
    color: var(--gold-soft);
    background: color-mix(in srgb, var(--gold) 10%, transparent);
    border-color: var(--border-gold);
    box-shadow: inset 0 -2px 0 color-mix(in srgb, var(--gold-soft) 45%, transparent);
  }
  .nav .logout {
    border-color: rgba(255,255,255,0.10);
    background: rgba(255,255,255,0.035);
  }
  .wrap {
    position: relative;
    padding-top: 28px;
    padding-bottom: 52px;
  }
  .hero {
    position: relative;
    overflow: hidden;
    display: grid;
    grid-template-columns: 1fr auto;
    align-items: center;
    gap: 24px;
    background:
      radial-gradient(circle at 88% 50%, color-mix(in srgb, var(--gold-soft) 22%, transparent), transparent 20%),
      linear-gradient(135deg, rgba(32,32,30,0.96), rgba(23,23,22,0.92) 55%, rgba(76,52,12,0.34));
    border: 1px solid var(--border-gold);
    border-radius: 24px;
    box-shadow: var(--shadow);
    padding: 36px;
    margin-bottom: 20px;
  }
  .hero::after {
    content: "";
    position: absolute;
    right: -90px;
    bottom: -160px;
    width: 380px;
    height: 380px;
    border: 1px solid color-mix(in srgb, var(--gold-soft) 22%, transparent);
    border-radius: 50%;
    box-shadow: inset 0 0 50px color-mix(in srgb, var(--gold) 8%, transparent);
  }
  .hero-copy, .hero-actions { position: relative; z-index: 1; }
  h1 {
    color: var(--text);
    font-family: 'Playfair Display', Georgia, serif;
    font-size: clamp(30px, 4vw, 46px);
    line-height: 1.08;
    letter-spacing: 0;
    margin-bottom: 12px;
  }
  h1 span { color: var(--gold-soft); }
  .subtitle {
    color: var(--muted);
    max-width: 700px;
    font-size: 15px;
    line-height: 1.7;
  }
  .hero-actions, .toolbar-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
    flex-wrap: wrap;
  }
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    min-height: 46px;
    border-radius: 14px;
    border: 1px solid transparent;
    color: #111;
    font-size: 13.5px;
    font-weight: 800;
    padding: 12px 18px;
    text-decoration: none;
    white-space: nowrap;
  }
  .btn:hover { transform: translateY(-1px); }
  .btn-gold {
    background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    box-shadow: 0 12px 26px color-mix(in srgb, var(--gold) 24%, transparent);
  }
  .btn-secondary {
    color: var(--text);
    background: rgba(255,255,255,0.04);
    border-color: rgba(255,255,255,0.10);
  }
  .stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin: 20px 0;
  }
  .stat-card {
    position: relative;
    overflow: hidden;
    display: grid;
    gap: 18px;
    background: linear-gradient(145deg, rgba(32,32,30,0.95), rgba(23,23,22,0.93));
    border: 1px solid var(--border-gold);
    border-radius: 24px;
    box-shadow: 0 16px 42px rgba(0,0,0,0.24);
    min-height: 196px;
    padding: 24px;
    transition: transform 180ms ease, border-color 180ms ease, box-shadow 180ms ease;
  }
  .stat-card:hover {
    border-color: color-mix(in srgb, var(--gold-soft) 34%, transparent);
    box-shadow: 0 20px 50px rgba(0,0,0,0.32);
    transform: translateY(-3px);
  }
  .stat-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
  }
  .icon-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 52px;
    height: 52px;
    flex: 0 0 52px;
    border: 1px solid color-mix(in srgb, var(--gold-soft) 30%, transparent);
    border-radius: 16px;
    color: var(--gold-soft);
    background: color-mix(in srgb, var(--gold) 10%, transparent);
    box-shadow: inset 0 0 22px color-mix(in srgb, var(--gold-soft) 8%, transparent);
  }
  .stat-label {
    color: var(--muted);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .11em;
    line-height: 1.4;
    text-transform: uppercase;
  }
  .num {
    color: var(--text);
    font-size: clamp(32px, 5vw, 42px);
    font-weight: 800;
    line-height: 1;
    margin-top: 8px;
  }
  .stat-copy {
    color: var(--muted);
    font-size: 13px;
    line-height: 1.55;
  }
  .signal {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    justify-self: start;
    color: var(--gold-soft);
    background: color-mix(in srgb, var(--gold) 10%, transparent);
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    padding: 7px 10px;
  }
  .section-card {
    background: linear-gradient(145deg, rgba(32,32,30,0.92), rgba(23,23,22,0.94));
    border: 1px solid var(--border-gold);
    border-radius: 24px;
    box-shadow: 0 18px 50px rgba(0,0,0,0.24);
    margin-top: 18px;
    padding: 20px;
  }
  .section-head, .toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 16px;
  }
  .section-title {
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--text);
    font-size: 18px;
    font-weight: 800;
  }
  .section-title .icon-badge {
    width: 34px;
    height: 34px;
    flex-basis: 34px;
    border-radius: 10px;
  }
  .section-desc {
    color: var(--muted);
    font-size: 13px;
    line-height: 1.55;
    margin-top: 6px;
  }
  .table-scroll {
    overflow-x: auto;
    border: 1px solid var(--border-soft);
    border-radius: 16px;
    background: rgba(8,8,7,0.34);
  }
  table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 13.5px;
    min-width: 720px;
  }
  th {
    background: rgba(32,32,30,0.98);
    color: var(--text);
    text-align: left;
    padding: 15px 16px;
    font-weight: 700;
    white-space: nowrap;
  }
  td {
    color: rgba(247,243,232,0.92);
    padding: 15px 16px;
    border-top: 1px solid rgba(255,255,255,0.06);
    white-space: nowrap;
  }
  thead th {
    border-bottom: 1px solid rgba(255,255,255,0.06);
  }
  tbody tr:first-child td {
    border-top: 0;
  }
  .event-table {
    min-width: 520px;
  }
  .leaderboard-table {
    min-width: 860px;
  }
  .event-table th:nth-child(2),
  .event-table th:nth-child(3),
  .event-table td:nth-child(2),
  .event-table td:nth-child(3),
  .leaderboard-table th:last-child,
  .leaderboard-table td:last-child {
    text-align: center;
  }
  .leaderboard-table th:nth-child(2),
  .leaderboard-table td:nth-child(2),
  .leaderboard-table th:nth-child(5),
  .leaderboard-table td:nth-child(5) {
    white-space: normal;
  }
  .leaderboard-table td:nth-child(2) {
    min-width: 180px;
  }
  .leaderboard-table td:nth-child(5) {
    min-width: 210px;
  }
  tbody tr:nth-child(even) td { background: rgba(255,255,255,0.025); }
  tbody tr:hover td { background: color-mix(in srgb, var(--gold) 6%, transparent); }
  tbody tr.top-rank td {
    background: linear-gradient(90deg, color-mix(in srgb, var(--gold) 20%, transparent), color-mix(in srgb, var(--gold) 6%, transparent));
    border-top-color: color-mix(in srgb, var(--gold-soft) 20%, transparent);
  }
  .rank {
    color: var(--gold-soft);
    font-weight: 800;
  }
  .pill, .code-badge, .name-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    font-weight: 800;
    line-height: 1;
  }
  .pill {
    color: var(--gold-soft);
    min-width: 34px;
    background: color-mix(in srgb, var(--gold) 12%, transparent);
    border: 1px solid color-mix(in srgb, var(--gold) 22%, transparent);
    padding: 8px 10px;
  }
  .code-badge {
    color: var(--gold-soft);
    background: rgba(0,0,0,0.22);
    border: 1px solid color-mix(in srgb, var(--gold-soft) 30%, transparent);
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
    font-size: 12px;
    padding: 8px 10px;
  }
  .name-badge {
    color: var(--text);
    background: rgba(255,255,255,0.055);
    border: 1px solid rgba(255,255,255,0.08);
    padding: 8px 11px;
  }
  .wa-link {
    color: var(--gold-soft);
    text-decoration: none;
  }
  .wa-link:hover { text-decoration: underline; }
  .search-input {
    width: min(280px, 100%);
    min-height: 46px;
    color: var(--text);
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.10);
    border-radius: 14px;
    font: inherit;
    font-size: 13.5px;
    outline: none;
    padding: 0 14px;
  }
  .search-input:focus {
    border-color: color-mix(in srgb, var(--gold-soft) 42%, transparent);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--gold) 10%, transparent);
  }
  .empty {
    color: var(--muted);
    background: rgba(255,255,255,0.035);
    border: 1px dashed color-mix(in srgb, var(--gold-soft) 18%, transparent);
    border-radius: 16px;
    font-size: 14px;
    padding: 28px;
    text-align: center;
  }
  .mobile-cards { display: none; }
  .event-strip {
    display: grid;
    grid-template-columns: 56px minmax(0, 1fr) auto auto;
    align-items: center;
    gap: 18px;
    background: rgba(8,8,7,0.34);
    border: 1px solid var(--border-soft);
    border-radius: 16px;
    padding: 16px;
  }
  .event-logo {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 56px;
    height: 56px;
    border: 1px solid color-mix(in srgb, var(--gold-soft) 28%, transparent);
    border-radius: 50%;
    background: color-mix(in srgb, var(--gold) 8%, transparent);
  }
  .event-logo img { width: 42px; height: auto; }
  .event-name { font-weight: 800; line-height: 1.4; min-width: 0; overflow-wrap: anywhere; }
  .event-metric { color: var(--muted); font-size: 12px; }
  .event-metric strong {
    display: block;
    color: var(--text);
    font-size: 22px;
    line-height: 1;
    margin-bottom: 5px;
  }
  .lead-card, .leader-card {
    background: rgba(8,8,7,0.34);
    border: 1px solid var(--border-soft);
    border-radius: 16px;
    padding: 16px;
  }
  .leader-card {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    gap: 13px;
    align-items: center;
  }
  .leader-medal {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    color: #15120a;
    background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    font-weight: 900;
  }
  .leader-card:nth-child(n+2) .leader-medal {
    color: var(--text);
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.14);
  }
  .leader-name, .lead-name { font-weight: 800; }
  .leader-name { overflow-wrap: anywhere; }
  .leader-meta, .lead-meta {
    color: var(--muted);
    font-size: 12.5px;
    line-height: 1.7;
    margin-top: 3px;
    overflow-wrap: anywhere;
  }
  .leader-event {
    display: block;
    color: rgba(247,243,232,0.86);
    margin-bottom: 8px;
  }
  .leader-phone {
    display: block;
  }
  .lead-card {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 14px;
  }
  .lead-date {
    color: var(--gold-soft);
    background: color-mix(in srgb, var(--gold) 12%, transparent);
    border-radius: 10px;
    font-size: 12px;
    font-weight: 700;
    padding: 8px 10px;
    align-self: start;
  }
  .desktop-only { display: block; }
  @media (max-width: 1100px) {
    .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .hero { grid-template-columns: 1fr; }
    .hero-actions { justify-content: flex-start; }
  }
  @media (max-width: 760px) {
    .topbar-inner, .wrap {
      padding-left: 16px;
      padding-right: 16px;
    }
    .topbar-inner {
      min-height: auto;
      padding-top: 16px;
      padding-bottom: 16px;
      align-items: flex-start;
    }
    .brand img { width: 112px; }
    .brand-title { font-size: 16px; }
    .nav { gap: 8px; }
    .nav a { padding: 10px 12px; font-size: 12.5px; }
    .hero {
      border-radius: 22px;
      padding: 28px;
    }
    .hero-actions, .toolbar-actions, .section-head, .toolbar {
      align-items: stretch;
      flex-direction: column;
    }
    .btn, .search-input { width: 100%; }
    .stats { grid-template-columns: 1fr; }
    .stat-card { min-height: 166px; }
    .section-card { padding: 16px; }
    .desktop-table { display: none; }
    .mobile-cards {
      display: grid;
      gap: 12px;
    }
    .event-strip {
      grid-template-columns: 48px minmax(0, 1fr);
      gap: 14px;
    }
    .event-logo { width: 48px; height: 48px; }
    .event-logo img { width: 36px; }
    .event-metric {
      background: rgba(255,255,255,0.035);
      border: 1px solid rgba(255,255,255,0.07);
      border-radius: 14px;
      padding: 12px;
    }
    .leader-card {
      grid-template-columns: auto minmax(0, 1fr);
    }
    .leader-card > .pill {
      grid-column: 2;
      justify-self: start;
    }
    .lead-card { grid-template-columns: 1fr; }
  }
  @media (max-width: 520px) {
    .topbar-inner {
      display: grid;
      grid-template-columns: 1fr;
    }
    .brand { justify-content: space-between; }
    .nav { justify-content: flex-start; }
    h1 { font-size: 30px; }
    .hero { padding: 24px; }
  }
</style>
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="dashboard.php" aria-label="<?= htmlspecialchars($brand['name']) ?> Dashboard">
      <img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($brand['name']) ?>">
      <span class="brand-title">Dashboard</span>
    </a>
    <nav class="nav" aria-label="Navigasi admin">
      <a class="active" href="dashboard.php">Dashboard</a>
      <a href="events.php">Kelola Event</a>
      <a href="integrations.php">Pengaturan Integrasi</a>
      <a href="visitor-analytics.php">Analitik Pengunjung</a>
      <a class="logout" href="logout.php">Keluar</a>
    </nav>
  </div>
</header>

<main class="wrap">
  <section class="hero" aria-labelledby="dashboard-title">
    <div class="hero-copy">
      <h1 id="dashboard-title">Dashboard <span><?= htmlspecialchars($brand['name']) ?></span></h1>
      <p class="subtitle">Pantau performa event, pengundang, dan pendaftar referral secara real-time.</p>
    </div>
    <div class="hero-actions">
      <a class="btn btn-secondary" href="events.php">Kelola Event</a>
      <a class="btn btn-gold" href="export.php">Export CSV</a>
    </div>
  </section>

  <section class="stats" aria-label="Ringkasan performa">
    <article class="stat-card">
      <div class="stat-top">
        <span class="icon-badge" aria-hidden="true">
          <svg width="25" height="25" viewBox="0 0 24 24" fill="none"><path d="M16 11a4 4 0 1 0-3.64-5.66M12 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.87M10 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
        <span class="stat-label">Total Pendaftar</span>
      </div>
      <div>
        <div class="num"><?= (int)$totalLeads ?></div>
        <p class="stat-copy">Total pendaftar dari seluruh event</p>
      </div>
      <span class="signal">Terbaru dari semua event</span>
    </article>
    <article class="stat-card">
      <div class="stat-top">
        <span class="icon-badge" aria-hidden="true">
          <svg width="25" height="25" viewBox="0 0 24 24" fill="none"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m16-10v6m3-3h-6M10 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
        <span class="stat-label">Total Pengundang</span>
      </div>
      <div>
        <div class="num"><?= (int)$totalReferrers ?></div>
        <p class="stat-copy">Pengundang terdaftar di semua event</p>
      </div>
      <span class="signal">Link referral tersedia</span>
    </article>
    <article class="stat-card">
      <div class="stat-top">
        <span class="icon-badge" aria-hidden="true">
          <svg width="25" height="25" viewBox="0 0 24 24" fill="none"><path d="M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm3 10h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
        <span class="stat-label">Total Event</span>
      </div>
      <div>
        <div class="num"><?= (int)$totalEvents ?></div>
        <p class="stat-copy">Event aktif atau tercatat di dashboard</p>
      </div>
      <span class="signal">Dari data event</span>
    </article>
    <article class="stat-card">
      <div class="stat-top">
        <span class="icon-badge" aria-hidden="true">
          <svg width="25" height="25" viewBox="0 0 24 24" fill="none"><path d="M12 15.5 8.76 17.2l.62-3.6-2.62-2.55 3.62-.53L12 7.25l1.62 3.27 3.62.53-2.62 2.55.62 3.6L12 15.5Zm0 6.5a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
        <span class="stat-label">Referral Aktif</span>
      </div>
      <div>
        <div class="num"><?= (int)$activeReferrals ?></div>
        <p class="stat-copy">Pengundang yang sudah menghasilkan pendaftar</p>
      </div>
      <span class="signal">Conversion signal</span>
    </article>
  </section>

  <section class="section-card" aria-labelledby="event-summary-title">
    <div class="section-head">
      <div>
        <h2 class="section-title" id="event-summary-title">
          <span class="icon-badge" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M3 3v18h18M7 16v-5m5 5V8m5 8V5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
          Pendaftar per Event
        </h2>
        <p class="section-desc">Ringkasan jumlah pendaftar dan pengundang dari setiap event.</p>
      </div>
    </div>
  <?php if (empty($eventSummary)): ?>
    <p class="empty">Belum ada event terdaftar.</p>
  <?php else: ?>
  <div class="table-scroll desktop-table">
    <table class="event-table">
      <thead><tr><th>Event</th><th>Pendaftar</th><th>Pengundang</th></tr></thead>
      <tbody>
      <?php foreach ($eventSummary as $ev): ?>
      <tr>
        <td><?= htmlspecialchars($ev['name']) ?></td>
        <td><span class="pill"><?= (int)$ev['total_leads'] ?></span></td>
        <td><span class="pill"><?= (int)$ev['total_referrers'] ?></span></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="mobile-cards">
    <?php foreach ($eventSummary as $ev): ?>
      <article class="event-strip">
        <span class="event-logo"><img src="<?= htmlspecialchars($logoPath) ?>" alt=""></span>
        <div class="event-name"><?= htmlspecialchars($ev['name']) ?></div>
        <div class="event-metric"><strong><?= (int)$ev['total_leads'] ?></strong>Pendaftar</div>
        <div class="event-metric"><strong><?= (int)$ev['total_referrers'] ?></strong>Pengundang</div>
      </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  </section>

  <section class="section-card" aria-labelledby="leaderboard-title">
    <div class="section-head">
      <div>
        <h2 class="section-title" id="leaderboard-title">
          <span class="icon-badge" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M8 21h8M12 17v4M7 4h10v4a5 5 0 0 1-10 0V4Zm10 2h3a2 2 0 0 1-2 2h-1M7 6H4a2 2 0 0 0 2 2h1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
          Leaderboard Pengundang Teraktif
        </h2>
        <p class="section-desc">Pantau performa referral berdasarkan pendaftar unik dari WhatsApp pertama per event.</p>
      </div>
    </div>
  <?php if (empty($leaderboard)): ?>
    <p class="empty">Belum ada aktivitas pengundang.</p>
  <?php else: ?>
  <div class="table-scroll desktop-table">
    <table class="leaderboard-table">
      <thead><tr><th>#</th><th>Nama</th><th>WhatsApp</th><th>Kode Link</th><th>Event</th><th>Pendaftar Unik</th></tr></thead>
      <tbody>
      <?php foreach ($leaderboard as $i => $r): ?>
      <?php $waLink = whatsapp_link($r['whatsapp'] ?? ''); ?>
      <tr class="<?= $i === 0 ? 'top-rank' : '' ?>">
        <td class="rank">#<?= $i + 1 ?></td>
        <td><?= htmlspecialchars($r['name']) ?></td>
        <td>
          <?php if ($waLink): ?>
            <a class="wa-link" href="<?= htmlspecialchars($waLink) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($r['whatsapp']) ?></a>
          <?php else: ?>
            <?= htmlspecialchars($r['whatsapp']) ?>
          <?php endif; ?>
        </td>
        <td><span class="code-badge"><?= htmlspecialchars($r['ref_code']) ?></span></td>
        <td><?= htmlspecialchars($r['event_name'] ?? '-') ?></td>
        <td><span class="pill"><?= (int)$r['total'] ?></span></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="mobile-cards">
    <?php foreach ($leaderboard as $i => $r): ?>
      <article class="leader-card">
        <span class="leader-medal"><?= $i + 1 ?></span>
        <div>
          <div class="leader-name"><?= htmlspecialchars($r['name']) ?></div>
          <div class="leader-meta">
            <span class="leader-phone"><?= htmlspecialchars($r['whatsapp']) ?></span>
            <span class="leader-event"><?= htmlspecialchars($r['event_name'] ?? '-') ?></span>
            <span class="code-badge"><?= htmlspecialchars($r['ref_code']) ?></span>
          </div>
        </div>
        <span class="pill"><?= (int)$r['total'] ?></span>
      </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  </section>

  <section class="section-card" aria-labelledby="leads-title">
    <div class="toolbar">
      <div>
        <h2 class="section-title" id="leads-title">
          <span class="icon-badge" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
          Data Pendaftar Terbaru
        </h2>
        <p class="section-desc">Menampilkan 500 data pendaftar terbaru dari seluruh event.</p>
      </div>
      <div class="toolbar-actions">
        <input class="search-input" id="leadSearch" type="search" placeholder="Cari pendaftar..." aria-label="Cari pendaftar">
        <a class="btn btn-gold" href="export.php">Export CSV</a>
      </div>
    </div>
  <?php if (empty($leads)): ?>
    <p class="empty">Belum ada pendaftar.</p>
  <?php else: ?>
  <div class="table-scroll desktop-table">
    <table>
      <thead><tr><th>Waktu</th><th>Nama</th><th>Email</th><th>WhatsApp</th><th>Kota</th><th>Event</th><th>Diundang Oleh</th></tr></thead>
      <tbody id="leadRows">
      <?php foreach ($leads as $l): ?>
      <?php $waLink = whatsapp_link($l['whatsapp'] ?? ''); ?>
      <tr>
        <td><?= date('d M Y, H:i', strtotime($l['created_at'])) ?></td>
        <td><?= htmlspecialchars($l['name']) ?></td>
        <td><?= htmlspecialchars($l['email']) ?></td>
        <td>
          <?php if ($waLink): ?>
            <a class="wa-link" href="<?= htmlspecialchars($waLink) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($l['whatsapp']) ?></a>
          <?php else: ?>
            <?= htmlspecialchars($l['whatsapp']) ?>
          <?php endif; ?>
        </td>
        <td><?= htmlspecialchars($l['kota']) ?></td>
        <td><?= htmlspecialchars($l['event_name'] ?? '-') ?></td>
        <td><span class="name-badge"><?= htmlspecialchars($l['referrer_name'] ?? $l['ref_code'] ?? '-') ?></span></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="mobile-cards" id="leadCards">
    <?php foreach ($leads as $l): ?>
      <?php $waLink = whatsapp_link($l['whatsapp'] ?? ''); ?>
      <article class="lead-card">
        <div>
          <div class="lead-name"><?= htmlspecialchars($l['name']) ?></div>
          <div class="lead-meta">
            <?= htmlspecialchars($l['email']) ?><br>
            <?php if ($waLink): ?>
              <a class="wa-link" href="<?= htmlspecialchars($waLink) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($l['whatsapp']) ?></a>
            <?php else: ?>
              <?= htmlspecialchars($l['whatsapp']) ?>
            <?php endif; ?>
            <br><?= htmlspecialchars($l['kota']) ?> · <?= htmlspecialchars($l['event_name'] ?? '-') ?><br>
            Diundang oleh <?= htmlspecialchars($l['referrer_name'] ?? $l['ref_code'] ?? '-') ?>
          </div>
        </div>
        <span class="lead-date"><?= date('d M Y, H:i', strtotime($l['created_at'])) ?></span>
      </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  </section>
</main>
<script>
  const searchInput = document.getElementById('leadSearch');
  const leadRows = Array.from(document.querySelectorAll('#leadRows tr'));
  const leadCards = Array.from(document.querySelectorAll('#leadCards .lead-card'));

  if (searchInput) {
    searchInput.addEventListener('input', () => {
      const keyword = searchInput.value.trim().toLowerCase();
      [...leadRows, ...leadCards].forEach((item) => {
        item.style.display = item.textContent.toLowerCase().includes(keyword) ? '' : 'none';
      });
    });
  }
</script>
</body>
</html>
