<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
start_secure_session();

$brand = require_admin_for_brand(get_current_brand());

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pdo = get_db();

$eventSlug = clean($_GET['event'] ?? '');
$event = $eventSlug !== '' ? get_event_by_slug($eventSlug) : null;
if ($event && (int)$event['brand_id'] !== (int)$brand['id']) {
    $event = null; // event milik brand lain — perlakukan seperti tidak ditemukan
}
$eventNotFound = !$event;
$notice = null;
$noticeType = 'success'; // success | error
$fieldErrors = [];

if (!$eventNotFound && isset($_GET['saved'])) {
    $notice = 'Pengaturan tracking berhasil diperbarui.';
    $noticeType = 'success';
}

$formValues = $event ?: [
    'meta_pixel_id' => '',
    'ga_measurement_id' => '',
];

// ==================== HANDLE ACTIONS ====================
if (!$eventNotFound && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $notice = 'Sesi tidak valid. Silakan refresh halaman lalu coba lagi.';
        $noticeType = 'error';
    } else {
        $metaPixelId = trim($_POST['meta_pixel_id'] ?? '');
        $gaMeasurementId = strtoupper(trim($_POST['ga_measurement_id'] ?? ''));

        $formValues['meta_pixel_id'] = $metaPixelId;
        $formValues['ga_measurement_id'] = $gaMeasurementId;

        if ($metaPixelId !== '' && !preg_match('/^\d{6,20}$/', $metaPixelId)) {
            $fieldErrors['meta_pixel_id'] = 'Meta Pixel ID harus berupa angka saja, 6-20 digit.';
        }
        if ($gaMeasurementId !== '' && !preg_match('/^G-[A-Z0-9]+$/', $gaMeasurementId)) {
            $fieldErrors['ga_measurement_id'] = 'GA4 Measurement ID harus berformat G-XXXXXXXXXX.';
        }

        if (!empty($fieldErrors)) {
            $notice = 'Mohon periksa kembali ID tracking yang ditandai.';
            $noticeType = 'error';
        } else {
            try {
                $stmt = $pdo->prepare('UPDATE events SET meta_pixel_id = ?, ga_measurement_id = ? WHERE slug = ? AND brand_id = ?');
                $stmt->execute([
                    $metaPixelId !== '' ? $metaPixelId : null,
                    $gaMeasurementId !== '' ? $gaMeasurementId : null,
                    $eventSlug,
                    (int)$brand['id'],
                ]);

                header('Location: tracking.php?event=' . urlencode($eventSlug) . '&saved=1');
                exit;
            } catch (Exception $e) {
                $notice = 'Pengaturan tracking belum bisa disimpan. Mohon periksa input dan coba lagi.';
                $noticeType = 'error';
            }
        }
    }
}

$pageTitle = $eventNotFound ? 'Event Tidak Ditemukan' : $event['name'];
$logoPath = $brand['logo_path'] ? '..' . $brand['logo_path'] : '../assets/logo.png';
$eventUrl = $eventNotFound ? '#' : ($eventSlug === $brand['default_event_slug'] ? '/' : EVENTS_URL_BASE . '/' . rawurlencode($eventSlug) . '/');
$metaActive = trim((string)($formValues['meta_pixel_id'] ?? '')) !== '';
$gaActive = trim((string)($formValues['ga_measurement_id'] ?? '')) !== '';
$trackingActive = $metaActive || $gaActive;

function tracking_field_class(array $errors, string $key): string
{
    return isset($errors[$key]) ? ' is-invalid' : '';
}

function tracking_status_badge(bool $active): string
{
    return $active ? 'Aktif' : 'Belum diatur';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tracking - <?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars($brand['name']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
<style>
  <?= get_theme_css_vars($brand) ?>
  :root {
    --bg:#0B0B0A;
    --bg-soft:#10100F;
    --surface:#171716;
    --surface-elevated:#20201E;
    --border-gold:color-mix(in srgb, var(--gold) 18%, transparent);
    --border-strong:color-mix(in srgb, var(--gold) 34%, transparent);
    --border-soft:rgba(255,255,255,0.09);
    --gold:var(--brand-primary);
    --gold-soft:var(--brand-soft);
    --charcoal:var(--brand-charcoal);
    --text:#F7F3E8;
    --muted:#A8A29A;
    --success:#22C55E;
    --warning:#F59E0B;
    --danger:#EF4444;
    --shadow:0 24px 80px rgba(0,0,0,0.34);
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html { scroll-behavior: smooth; }
  body {
    min-height: 100vh;
    background:
      radial-gradient(circle at 88% 4%, color-mix(in srgb, var(--gold) 22%, transparent), transparent 30vw),
      radial-gradient(circle at 8% 92%, color-mix(in srgb, var(--gold) 13%, transparent), transparent 34vw),
      linear-gradient(135deg, var(--bg) 0%, var(--bg-soft) 54%, #080807 100%);
    color: var(--text);
    font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  }
  body::before {
    content:"";
    position: fixed;
    inset: 0;
    pointer-events: none;
    background-image:
      linear-gradient(rgba(255,255,255,0.024) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,0.016) 1px, transparent 1px);
    background-size: 52px 52px;
    mask-image: radial-gradient(circle at 50% 16%, black, transparent 72%);
  }
  a { color: inherit; }
  .topbar {
    position: sticky;
    top: 0;
    z-index: 20;
    background: rgba(16,16,15,0.78);
    border-bottom: 1px solid color-mix(in srgb, var(--gold) 14%, transparent);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
  }
  .topbar-inner, .wrap {
    width: min(100%, 1280px);
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
  .brand { display: inline-flex; align-items: center; text-decoration: none; }
  .brand img {
    width: 146px;
    height: auto;
    display: block;
    filter: drop-shadow(0 10px 20px rgba(0,0,0,0.32));
  }
  .nav {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
  }
  .nav a, .btn, .panel, .hero, .field input, .status-row, .notice {
    transition: transform 180ms ease, border-color 180ms ease, background 180ms ease, color 180ms ease, box-shadow 180ms ease, opacity 180ms ease;
  }
  .nav a {
    color: var(--muted);
    display: inline-flex;
    align-items: center;
    min-height: 42px;
    padding: 10px 14px;
    border-radius: 999px;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    border: 1px solid transparent;
  }
  .nav a:hover, .nav a.active {
    color: var(--gold-soft);
    background: color-mix(in srgb, var(--gold) 9%, transparent);
    border-color: color-mix(in srgb, var(--gold) 18%, transparent);
  }
  .nav a.logout {
    color: var(--text);
    background: rgba(255,255,255,0.035);
    border-color: rgba(255,255,255,0.10);
  }
  .wrap {
    position: relative;
    z-index: 1;
    padding-top: 26px;
    padding-bottom: 48px;
  }
  .breadcrumb {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--muted);
    font-size: 13px;
    margin-bottom: 18px;
  }
  .breadcrumb a {
    color: var(--gold-soft);
    text-decoration: none;
    font-weight: 700;
  }
  .hero {
    position: relative;
    overflow: hidden;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 24px;
    padding: 32px 34px;
    margin-bottom: 20px;
    border: 1px solid var(--border-gold);
    border-radius: 28px;
    background:
      radial-gradient(circle at 94% 22%, color-mix(in srgb, var(--gold-soft) 24%, transparent), transparent 22%),
      linear-gradient(135deg, rgba(255,255,255,0.06), rgba(255,255,255,0.02)),
      linear-gradient(135deg, color-mix(in srgb, var(--gold) 12%, transparent), rgba(23,23,22,0.88));
    box-shadow: var(--shadow);
  }
  .hero::after {
    content:"";
    position:absolute;
    right:-80px;
    top:-105px;
    width:280px;
    height:280px;
    border-radius:50%;
    border:1px solid color-mix(in srgb, var(--gold-soft) 16%, transparent);
    box-shadow: inset 0 0 90px color-mix(in srgb, var(--gold) 14%, transparent), 0 0 70px color-mix(in srgb, var(--gold) 12%, transparent);
  }
  .eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    width: fit-content;
    color: var(--gold-soft);
    background: color-mix(in srgb, var(--gold) 12%, transparent);
    border: 1px solid color-mix(in srgb, var(--gold) 24%, transparent);
    border-radius: 999px;
    padding: 8px 12px;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: .02em;
    margin-bottom: 14px;
  }
  .icon-badge, .input-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    width: 40px;
    height: 40px;
    color: var(--gold-soft);
    border: 1px solid color-mix(in srgb, var(--gold) 28%, transparent);
    border-radius: 14px;
    background: color-mix(in srgb, var(--gold) 12%, transparent);
    box-shadow: inset 0 0 22px color-mix(in srgb, var(--gold) 8%, transparent);
    font-size: 12px;
    font-weight: 900;
  }
  h1 {
    max-width: 880px;
    font-family: "Playfair Display", Georgia, serif;
    font-size: clamp(34px, 4.5vw, 54px);
    line-height: 1.04;
    letter-spacing: 0;
  }
  h1 span { color: var(--gold); }
  .hero p {
    max-width: 700px;
    color: var(--muted);
    font-size: 16px;
    line-height: 1.7;
    margin-top: 14px;
  }
  .hero-actions {
    position: relative;
    z-index: 1;
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
    min-height: 48px;
    padding: 13px 18px;
    border-radius: 13px;
    border: 1px solid color-mix(in srgb, var(--gold) 22%, transparent);
    background: rgba(255,255,255,0.04);
    color: var(--text);
    font-family: inherit;
    font-size: 14px;
    font-weight: 900;
    text-decoration: none;
    cursor: pointer;
  }
  .btn:hover {
    transform: translateY(-1px);
    border-color: color-mix(in srgb, var(--gold-soft) 42%, transparent);
  }
  .btn-primary {
    color: #111;
    background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    border-color: transparent;
    box-shadow: 0 18px 44px color-mix(in srgb, var(--gold) 22%, transparent);
  }
  .btn-secondary {
    background: rgba(255,255,255,0.035);
    border-color: rgba(255,255,255,0.12);
  }
  .notice {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px 18px;
    border-radius: 18px;
    margin: 0 0 18px;
    font-size: 14px;
    font-weight: 800;
    border: 1px solid rgba(255,255,255,0.10);
    background: rgba(255,255,255,0.045);
  }
  .notice.success {
    color: #B9F6CC;
    border-color: rgba(34,197,94,0.24);
    background: rgba(34,197,94,0.10);
  }
  .notice.error {
    color: #FECACA;
    border-color: rgba(239,68,68,0.24);
    background: rgba(239,68,68,0.10);
  }
  .main-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.2fr) minmax(350px, .8fr);
    gap: 22px;
    align-items: start;
  }
  .right-stack { display: grid; gap: 18px; }
  .panel {
    overflow: hidden;
    border: 1px solid var(--border-gold);
    border-radius: 24px;
    background: linear-gradient(180deg, rgba(255,255,255,0.045), rgba(255,255,255,0.02));
    box-shadow: 0 18px 60px rgba(0,0,0,0.24);
  }
  .panel-head {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 24px 26px 18px;
    border-bottom: 1px solid color-mix(in srgb, var(--gold) 12%, transparent);
  }
  h2 {
    font-family: "Playfair Display", Georgia, serif;
    color: var(--gold-soft);
    font-size: 24px;
    line-height: 1.1;
  }
  .desc {
    color: var(--muted);
    font-size: 13.5px;
    line-height: 1.6;
    margin-top: 6px;
  }
  .panel-body {
    padding: 24px 26px 26px;
  }
  .tracking-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 22px;
    padding: 16px;
    border: 1px solid color-mix(in srgb, var(--gold) 16%, transparent);
    border-radius: 18px;
    background: color-mix(in srgb, var(--gold) 6%, transparent);
  }
  .tracking-toggle strong {
    display: block;
    color: var(--text);
    margin-bottom: 4px;
  }
  .tracking-toggle span {
    color: var(--muted);
    font-size: 12.5px;
  }
  .switch {
    position: relative;
    width: 54px;
    height: 30px;
    flex: 0 0 auto;
    border-radius: 999px;
    border: 1px solid rgba(255,255,255,0.12);
    background: rgba(255,255,255,0.08);
  }
  .switch::after {
    content:"";
    position: absolute;
    top: 4px;
    left: 4px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: var(--muted);
    transition: transform 180ms ease, background 180ms ease;
  }
  .switch.is-on {
    background: rgba(34,197,94,0.18);
    border-color: rgba(34,197,94,0.32);
  }
  .switch.is-on::after {
    transform: translateX(24px);
    background: var(--success);
  }
  .field {
    display: grid;
    gap: 9px;
    margin-bottom: 22px;
  }
  .field label {
    color: var(--gold-soft);
    font-size: 14px;
    font-weight: 900;
  }
  .input-group {
    display: grid;
    grid-template-columns: 54px minmax(0, 1fr);
    overflow: hidden;
    border: 1px solid color-mix(in srgb, var(--gold) 14%, transparent);
    border-radius: 15px;
    background: #111110;
  }
  .input-group:focus-within {
    border-color: var(--gold);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--gold) 14%, transparent);
  }
  .input-group .input-icon {
    width: 54px;
    height: 52px;
    border-width: 0 1px 0 0;
    border-color: color-mix(in srgb, var(--gold) 14%, transparent);
    border-radius: 0;
    background: color-mix(in srgb, var(--gold) 8%, transparent);
  }
  .field input[type="text"] {
    width: 100%;
    min-height: 52px;
    color: var(--text);
    background: transparent;
    border: 0;
    padding: 14px 16px;
    font: inherit;
    outline: none;
  }
  .input-group.is-invalid {
    border-color: rgba(239,68,68,0.74);
    box-shadow: 0 0 0 3px rgba(239,68,68,0.10);
  }
  .hint {
    color: var(--muted);
    font-size: 12.5px;
    line-height: 1.55;
  }
  .error-message {
    color: #FCA5A5;
    font-size: 12.5px;
    font-weight: 800;
  }
  .save-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding-top: 22px;
    border-top: 1px solid color-mix(in srgb, var(--gold) 12%, transparent);
  }
  .dirty-note {
    color: var(--muted);
    font-size: 13px;
    font-weight: 700;
  }
  .save-actions {
    display: flex;
    gap: 12px;
  }
  .status-list {
    display: grid;
    gap: 10px;
  }
  .status-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 14px 15px;
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 16px;
    background: rgba(255,255,255,0.025);
  }
  .status-row strong {
    color: var(--text);
    font-size: 14px;
  }
  .status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 30px;
    padding: 6px 10px;
    border-radius: 999px;
    color: var(--gold-soft);
    background: color-mix(in srgb, var(--gold) 10%, transparent);
    border: 1px solid color-mix(in srgb, var(--gold) 20%, transparent);
    font-size: 12px;
    font-weight: 900;
    white-space: nowrap;
  }
  .status-badge.active {
    color: #BBF7D0;
    background: rgba(34,197,94,0.12);
    border-color: rgba(34,197,94,0.28);
  }
  .event-list, .guide-list, .insight-list {
    display: grid;
    gap: 12px;
    margin-top: 18px;
  }
  .event-list li, .guide-list li, .insight-item {
    display: grid;
    grid-template-columns: 30px minmax(0, 1fr);
    gap: 12px;
    align-items: start;
    color: var(--muted);
    font-size: 13.5px;
    line-height: 1.6;
    list-style: none;
  }
  .num, .dot {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    color: #111;
    background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    font-size: 12px;
    font-weight: 1000;
  }
  .dot {
    color: var(--gold-soft);
    background: color-mix(in srgb, var(--gold) 14%, transparent);
    border: 1px solid color-mix(in srgb, var(--gold) 24%, transparent);
  }
  .info-box {
    display: grid;
    grid-template-columns: 40px minmax(0, 1fr);
    gap: 12px;
    margin-top: 18px;
    padding: 16px;
    border: 1px solid color-mix(in srgb, var(--gold-soft) 26%, transparent);
    border-radius: 18px;
    background: color-mix(in srgb, var(--gold) 8%, transparent);
    color: var(--muted);
    font-size: 13px;
    line-height: 1.65;
  }
  .empty-state {
    display: grid;
    place-items: center;
    min-height: 420px;
    text-align: center;
  }
  .empty-card {
    width: min(100%, 620px);
    padding: 34px;
    border: 1px solid var(--border-gold);
    border-radius: 26px;
    background: linear-gradient(180deg, rgba(255,255,255,0.055), rgba(255,255,255,0.02));
    box-shadow: var(--shadow);
  }
  .empty-card .icon-badge {
    width: 54px;
    height: 54px;
    margin: 0 auto 16px;
  }
  .empty-card h1 {
    font-size: clamp(32px, 5vw, 46px);
    margin-bottom: 10px;
  }
  .empty-card p {
    color: var(--muted);
    line-height: 1.7;
    margin-bottom: 22px;
  }
  @media (max-width: 1040px) {
    .hero, .main-grid {
      grid-template-columns: 1fr;
    }
    .hero-actions {
      justify-content: flex-start;
    }
  }
  @media (max-width: 760px) {
    .topbar-inner, .wrap {
      padding-left: 16px;
      padding-right: 16px;
    }
    .topbar-inner {
      display: grid;
      min-height: auto;
      padding-top: 16px;
      padding-bottom: 16px;
    }
    .brand img { width: 112px; }
    .nav {
      justify-content: flex-start;
      gap: 8px;
    }
    .nav a {
      font-size: 12.5px;
      min-height: 38px;
      padding: 9px 11px;
    }
    .wrap {
      padding-top: 18px;
    }
    .breadcrumb {
      flex-wrap: wrap;
    }
    .hero {
      border-radius: 22px;
      padding: 24px;
    }
    .hero-actions, .save-row, .save-actions {
      align-items: stretch;
      flex-direction: column;
    }
    .btn {
      width: 100%;
    }
    .panel {
      border-radius: 20px;
    }
    .panel-head, .panel-body {
      padding-left: 18px;
      padding-right: 18px;
    }
    .tracking-toggle, .status-row {
      align-items: flex-start;
      flex-direction: column;
    }
  }
</style>
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="dashboard.php" aria-label="<?= htmlspecialchars($brand['name']) ?> Admin">
      <img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($brand['name']) ?>">
    </a>
    <nav class="nav" aria-label="Navigasi admin">
      <a href="dashboard.php">Dashboard</a>
      <a class="active" href="events.php">Kelola Event</a>
      <a href="visitor-analytics.php">Analitik Pengunjung</a>
      <a class="logout" href="logout.php">Keluar</a>
    </nav>
  </div>
</header>

<main class="wrap">
  <div class="breadcrumb">
    <a href="events.php">Kelola Event</a>
    <span>/</span>
    <span>Tracking</span>
  </div>

  <?php if ($eventNotFound): ?>
    <section class="empty-state">
      <div class="empty-card">
        <span class="icon-badge">EV</span>
        <h1>Event Tidak Ditemukan</h1>
        <p>Event yang Anda cari tidak tersedia atau sudah dihapus. Silakan kembali ke daftar event untuk memilih acara yang aktif.</p>
        <a class="btn btn-primary" href="events.php">Kembali ke Kelola Event</a>
      </div>
    </section>
  <?php else: ?>
    <section class="hero">
      <div>
        <span class="eyebrow"><span class="icon-badge">TRK</span> Tracking &amp; Analytics Setup</span>
        <h1>Tracking - <span><?= htmlspecialchars($event['name']) ?></span></h1>
        <p>Hubungkan Meta Pixel dan Google Analytics untuk melacak PageView dan lead dari landing page event.</p>
      </div>
      <div class="hero-actions">
        <a class="btn btn-primary" href="<?= htmlspecialchars($eventUrl) ?>" target="_blank" rel="noopener">Lihat Landing Page</a>
        <a class="btn btn-secondary" href="events.php">Kembali ke Kelola Event</a>
      </div>
    </section>

    <?php if ($notice): ?>
      <div class="notice <?= htmlspecialchars($noticeType) ?>">
        <span class="icon-badge"><?= $noticeType === 'success' ? 'OK' : '!' ?></span>
        <span><?= htmlspecialchars($notice) ?></span>
      </div>
    <?php endif; ?>

    <div class="main-grid">
      <form method="POST" class="panel" id="trackingForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <div class="panel-head">
          <span class="icon-badge">DATA</span>
          <div>
            <h2>Meta Pixel &amp; Google Analytics</h2>
            <p class="desc">ID ini digunakan untuk melacak performa landing page event dan pendaftaran peserta.</p>
          </div>
        </div>

        <div class="panel-body">
          <div class="tracking-toggle">
            <div>
              <strong>Enable Tracking</strong>
              <span><?= $trackingActive ? 'Tracking aktif karena minimal satu ID sudah diisi.' : 'Tracking nonaktif. Isi salah satu ID untuk mengaktifkan.' ?></span>
            </div>
            <span class="switch <?= $trackingActive ? 'is-on' : '' ?>" aria-hidden="true"></span>
          </div>

          <div class="field">
            <label for="meta_pixel_id">Meta Pixel ID</label>
            <div class="input-group<?= tracking_field_class($fieldErrors, 'meta_pixel_id') ?>">
              <span class="input-icon">META</span>
              <input type="text" inputmode="numeric" pattern="[0-9]*" id="meta_pixel_id" name="meta_pixel_id" placeholder="contoh: 123456789012345" value="<?= htmlspecialchars($formValues['meta_pixel_id'] ?? '') ?>">
            </div>
            <p class="hint">Angka saja. Dapat ditemukan di Meta Events Manager.</p>
            <?php if (isset($fieldErrors['meta_pixel_id'])): ?><p class="error-message"><?= htmlspecialchars($fieldErrors['meta_pixel_id']) ?></p><?php endif; ?>
          </div>

          <div class="field">
            <label for="ga_measurement_id">GA4 Measurement ID</label>
            <div class="input-group<?= tracking_field_class($fieldErrors, 'ga_measurement_id') ?>">
              <span class="input-icon">GA4</span>
              <input type="text" id="ga_measurement_id" name="ga_measurement_id" placeholder="contoh: G-XXXXXXXXXX" value="<?= htmlspecialchars($formValues['ga_measurement_id'] ?? '') ?>">
            </div>
            <p class="hint">Dapat ditemukan di Google Analytics, Admin, Data Streams.</p>
            <?php if (isset($fieldErrors['ga_measurement_id'])): ?><p class="error-message"><?= htmlspecialchars($fieldErrors['ga_measurement_id']) ?></p><?php endif; ?>
          </div>

          <div class="save-row">
            <span class="dirty-note" id="dirtyNote">Kosongkan field untuk menonaktifkan tracking terkait.</span>
            <div class="save-actions">
              <button type="submit" class="btn btn-primary">Simpan Pengaturan Tracking</button>
              <a class="btn btn-secondary" href="events.php">Batal</a>
            </div>
          </div>
        </div>
      </form>

      <aside class="right-stack">
        <section class="panel">
          <div class="panel-head">
            <span class="icon-badge">STAT</span>
            <div>
              <h2>Status Tracking</h2>
              <p class="desc">Ringkasan konfigurasi tracking untuk event ini.</p>
            </div>
          </div>
          <div class="panel-body">
            <div class="status-list">
              <div class="status-row">
                <strong>Meta Pixel</strong>
                <span class="status-badge <?= $metaActive ? 'active' : '' ?>"><?= htmlspecialchars(tracking_status_badge($metaActive)) ?></span>
              </div>
              <div class="status-row">
                <strong>Google Analytics</strong>
                <span class="status-badge <?= $gaActive ? 'active' : '' ?>"><?= htmlspecialchars(tracking_status_badge($gaActive)) ?></span>
              </div>
            </div>

            <ul class="event-list">
              <li><span class="dot">PV</span><span>PageView, kunjungan halaman event.</span></li>
              <li><span class="dot">LD</span><span>Lead atau pendaftaran peserta dari form event.</span></li>
            </ul>

            <div class="info-box">
              <span class="icon-badge">INFO</span>
              <span>Jika salah satu ID diisi, tracking yang terkait akan aktif di landing page event.</span>
            </div>
          </div>
        </section>

        <section class="panel">
          <div class="panel-head">
            <span class="icon-badge">GUIDE</span>
            <div>
              <h2>Cara Mengatur Tracking</h2>
              <p class="desc">Langkah singkat agar tracking siap dipakai.</p>
            </div>
          </div>
          <div class="panel-body">
            <ol class="guide-list">
              <li><span class="num">1</span><span>Salin Meta Pixel ID dari Meta Events Manager.</span></li>
              <li><span class="num">2</span><span>Salin GA4 Measurement ID dari Google Analytics Data Streams.</span></li>
              <li><span class="num">3</span><span>Tempel ID ke form, pastikan tidak ada spasi tambahan.</span></li>
              <li><span class="num">4</span><span>Simpan, lalu uji event di landing page.</span></li>
            </ol>
          </div>
        </section>

        <section class="panel">
          <div class="panel-head">
            <span class="icon-badge">VIEW</span>
            <div>
              <h2>Yang Akan Dilacak</h2>
              <p class="desc">Data dasar untuk membaca performa campaign.</p>
            </div>
          </div>
          <div class="panel-body">
            <div class="insight-list">
              <div class="insight-item"><span class="dot">1</span><span>Kunjungan halaman event melalui PageView.</span></div>
              <div class="insight-item"><span class="dot">2</span><span>Pendaftaran peserta sebagai Lead.</span></div>
              <div class="insight-item"><span class="dot">3</span><span>Aktivitas marketing untuk optimasi campaign.</span></div>
            </div>
          </div>
        </section>
      </aside>
    </div>
  <?php endif; ?>
</main>

<script>
  const trackingForm = document.getElementById('trackingForm');
  const dirtyNote = document.getElementById('dirtyNote');
  trackingForm?.addEventListener('input', () => {
    if (!dirtyNote) return;
    dirtyNote.textContent = 'Ada perubahan yang belum disimpan.';
    dirtyNote.style.color = 'var(--warning)';
  });
</script>
</body>
</html>
