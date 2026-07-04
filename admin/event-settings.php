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
    $notice = 'Detail acara berhasil diperbarui.';
    $noticeType = 'success';
}

$formValues = $event ?: [
    'event_day' => '',
    'event_time' => '',
    'event_location' => '',
    'event_speaker' => '',
    'event_capacity' => '',
];

// ==================== HANDLE ACTIONS ====================
if (!$eventNotFound && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $formValues = array_merge($formValues, [
        'event_day' => trim($_POST['event_day'] ?? ''),
        'event_time' => trim($_POST['event_time'] ?? ''),
        'event_location' => trim($_POST['event_location'] ?? ''),
        'event_speaker' => trim($_POST['event_speaker'] ?? ''),
        'event_capacity' => trim($_POST['event_capacity'] ?? ''),
    ]);

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $notice = 'Sesi tidak valid. Silakan refresh halaman lalu coba lagi.';
        $noticeType = 'error';
    } else {
        if ($formValues['event_day'] === '') {
            $fieldErrors['event_day'] = 'Hari dan tanggal wajib diisi.';
        }
        if ($formValues['event_time'] === '') {
            $fieldErrors['event_time'] = 'Waktu acara wajib diisi.';
        }
        if ($formValues['event_location'] === '') {
            $fieldErrors['event_location'] = 'Lokasi acara wajib diisi.';
        }
        if ($formValues['event_speaker'] === '') {
            $fieldErrors['event_speaker'] = 'Pembicara wajib diisi.';
        }
        if (!ctype_digit($formValues['event_capacity']) || (int)$formValues['event_capacity'] <= 0) {
            $fieldErrors['event_capacity'] = 'Kapasitas harus berupa angka positif.';
        }

        if (!empty($fieldErrors)) {
            $notice = 'Mohon periksa kembali detail acara yang ditandai.';
            $noticeType = 'error';
        } else {
            try {
                $_POST['event_capacity'] = (string)(int)$formValues['event_capacity'];
                $updated = update_event_settings($eventSlug, $_POST);
                $event = array_merge($event, $updated);

                // ---- Hapus flyer jika diminta ----
                if (isset($_POST['remove_flyer']) && !empty($event['flyer_path'])) {
                    delete_event_flyer($event['flyer_path']);
                    $stmt = $pdo->prepare('UPDATE events SET flyer_path = NULL WHERE slug = ? AND brand_id = ?');
                    $stmt->execute([$eventSlug, (int)$brand['id']]);
                    $event['flyer_path'] = null;
                }

                // ---- Upload flyer baru jika ada ----
                if (isset($_FILES['flyer']) && $_FILES['flyer']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['flyer'];
                    if ($file['size'] > MAX_EVENT_FLYER_SIZE) {
                        $notice = 'Ukuran flyer terlalu besar. Maksimal ' . (MAX_EVENT_FLYER_SIZE / 1024 / 1024) . ' MB.';
                        $noticeType = 'error';
                    } else {
                        $flyerPath = save_event_flyer($file['tmp_name'], $file['name'], $eventSlug);
                        if (!$flyerPath) {
                            $notice = 'Gagal upload flyer. Pastikan file adalah gambar dengan format yang diizinkan.';
                            $noticeType = 'error';
                        } else {
                            $stmt = $pdo->prepare('UPDATE events SET flyer_path = ? WHERE slug = ? AND brand_id = ?');
                            $stmt->execute([$flyerPath, $eventSlug, (int)$brand['id']]);
                            $event['flyer_path'] = $flyerPath;
                        }
                    }
                }

                if (!$notice) {
                    header('Location: event-settings.php?event=' . urlencode($eventSlug) . '&saved=1');
                    exit;
                }
            } catch (Exception $e) {
                $notice = 'Detail acara belum bisa disimpan. Mohon periksa input dan coba lagi.';
                $noticeType = 'error';
            }
        }
    }
}

$pageTitle = $eventNotFound ? 'Event Tidak Ditemukan' : $event['name'];
$logoPath = $brand['logo_path'] ? '..' . $brand['logo_path'] : '../assets/logo.png';
$eventUrl = $eventNotFound ? '#' : ($eventSlug === $brand['default_event_slug'] ? '/' : EVENTS_URL_BASE . '/' . rawurlencode($eventSlug) . '/');

function event_setting_value(array $values, string $key, string $fallback = '-'): string
{
    $value = trim((string)($values[$key] ?? ''));
    return $value !== '' ? $value : $fallback;
}

function event_field_class(array $errors, string $key): string
{
    return isset($errors[$key]) ? ' is-invalid' : '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Detail Acara - <?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars($brand['name']) ?></title>
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
    --danger:#EF4444;
    --shadow:0 24px 80px rgba(0,0,0,0.34);
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html { scroll-behavior: smooth; }
  body {
    min-height: 100vh;
    background:
      radial-gradient(circle at 88% 4%, color-mix(in srgb, var(--gold) 22%, transparent), transparent 30vw),
      radial-gradient(circle at 9% 92%, color-mix(in srgb, var(--gold) 13%, transparent), transparent 34vw),
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
  .brand {
    display: inline-flex;
    align-items: center;
    text-decoration: none;
  }
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
  .nav a, .btn, .field input, .preview-item, .panel, .hero, .notice {
    transition: transform 180ms ease, border-color 180ms ease, background 180ms ease, color 180ms ease, box-shadow 180ms ease, opacity 180ms ease;
  }
  .nav a {
    color: var(--muted);
    display: inline-flex;
    align-items: center;
    gap: 9px;
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
    top:-100px;
    width:260px;
    height:260px;
    border:1px solid color-mix(in srgb, var(--gold-soft) 18%, transparent);
    border-radius:50%;
    box-shadow: 0 0 80px color-mix(in srgb, var(--gold) 16%, transparent);
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
  .icon-badge, .field-icon {
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
    max-width: 840px;
    font-family: "Playfair Display", Georgia, serif;
    font-size: clamp(36px, 5vw, 60px);
    line-height: 1.02;
    letter-spacing: 0;
  }
  h1 span { color: var(--gold); }
  .hero p {
    max-width: 650px;
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
    align-items: flex-start;
    gap: 12px;
    padding: 15px 18px;
    border-radius: 18px;
    margin: 0 0 18px;
    font-size: 14px;
    font-weight: 700;
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
    grid-template-columns: minmax(0, 1.35fr) minmax(360px, .9fr);
    gap: 22px;
    align-items: start;
  }
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
  .form-body {
    display: grid;
    gap: 18px;
    padding: 24px 26px 26px;
  }
  .field {
    display: grid;
    grid-template-columns: 210px minmax(0, 1fr);
    gap: 18px;
    align-items: start;
  }
  .field-meta {
    display: grid;
    grid-template-columns: 40px minmax(0, 1fr);
    gap: 12px;
    align-items: start;
  }
  .field label {
    display: block;
    color: var(--gold-soft);
    font-size: 14px;
    font-weight: 900;
    margin-bottom: 6px;
  }
  .helper {
    color: var(--muted);
    font-size: 12.5px;
    line-height: 1.5;
  }
  .field input[type="text"],
  .field input[type="number"] {
    width: 100%;
    min-height: 52px;
    color: var(--text);
    background: #111110;
    border: 1px solid rgba(255,255,255,0.13);
    border-radius: 14px;
    padding: 14px 16px;
    font: inherit;
    outline: none;
  }
  .field input:focus {
    border-color: var(--gold);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--gold) 14%, transparent);
  }
  .field input.is-invalid {
    border-color: rgba(239,68,68,0.74);
    box-shadow: 0 0 0 3px rgba(239,68,68,0.10);
  }
  .error-message {
    color: #FCA5A5;
    font-size: 12.5px;
    font-weight: 700;
    margin-top: 8px;
  }
  .flyer-field {
    display: grid;
    gap: 12px;
  }
  .flyer-current img {
    max-width: 220px;
    max-height: 160px;
    width: auto;
    height: auto;
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.13);
    display: block;
    margin-bottom: 8px;
  }
  .flyer-remove {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--muted);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
  }
  .flyer-field input[type="file"] {
    color: var(--muted);
    font-size: 13px;
  }
  .save-bar {
    position: sticky;
    bottom: 0;
    z-index: 5;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 26px;
    border-top: 1px solid color-mix(in srgb, var(--gold) 14%, transparent);
    background: rgba(16,16,15,0.84);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
  }
  .preview-column {
    display: grid;
    gap: 20px;
  }
  .preview-card {
    padding-bottom: 22px;
  }
  .preview-list {
    margin: 0 24px;
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 18px;
    overflow: hidden;
    background: rgba(8,8,7,0.26);
  }
  .preview-item {
    display: grid;
    grid-template-columns: 46px minmax(0, 1fr);
    gap: 14px;
    align-items: center;
    padding: 16px 18px;
    border-bottom: 1px solid rgba(255,255,255,0.07);
  }
  .preview-item:last-child { border-bottom: 0; }
  .preview-item:hover { background: color-mix(in srgb, var(--gold) 5%, transparent); }
  .preview-label {
    color: var(--gold-soft);
    font-size: 12px;
    font-weight: 900;
    margin-bottom: 4px;
  }
  .preview-value {
    color: var(--text);
    font-size: 15px;
    line-height: 1.45;
    overflow-wrap: anywhere;
  }
  .auto-box {
    position: relative;
    overflow: hidden;
    display: grid;
    grid-template-columns: 46px minmax(0, 1fr);
    gap: 14px;
    padding: 24px;
    border: 1px solid color-mix(in srgb, var(--gold-soft) 34%, transparent);
    border-radius: 22px;
    background:
      radial-gradient(circle at 88% 50%, color-mix(in srgb, var(--gold) 18%, transparent), transparent 28%),
      linear-gradient(135deg, color-mix(in srgb, var(--gold) 12%, transparent), rgba(255,255,255,0.025));
    box-shadow: 0 18px 54px color-mix(in srgb, var(--gold) 10%, transparent);
  }
  .auto-box strong {
    display: block;
    color: var(--gold-soft);
    font-size: 15px;
    margin-bottom: 6px;
  }
  .auto-box p {
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
    .hero-actions, .save-bar {
      align-items: stretch;
      flex-direction: column;
    }
    .btn {
      width: 100%;
    }
    .main-grid {
      gap: 16px;
    }
    .panel {
      border-radius: 20px;
    }
    .panel-head, .form-body {
      padding-left: 18px;
      padding-right: 18px;
    }
    .field {
      grid-template-columns: 1fr;
      gap: 10px;
    }
    .save-bar {
      padding: 16px 18px;
    }
    .preview-list {
      margin-left: 18px;
      margin-right: 18px;
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
    <span>Detail Acara</span>
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
        <span class="eyebrow"><span class="icon-badge">CAL</span> Detail Acara</span>
        <h1><?= htmlspecialchars($event['name']) ?></h1>
        <p>Atur informasi utama yang otomatis tampil di landing page event dan halaman pendaftaran.</p>
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
      <form method="POST" class="panel" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <div class="panel-head">
          <span class="icon-badge">INFO</span>
          <div>
            <h2>Informasi Acara</h2>
            <p class="desc">Data ini akan digunakan pada landing page event dan halaman pendaftaran.</p>
          </div>
        </div>

        <div class="form-body">
          <div class="field">
            <div class="field-meta">
              <span class="field-icon">TGL</span>
              <div>
                <label for="event_day">Hari &amp; Tanggal</label>
                <p class="helper">Contoh: Jumat, 3 Juli 2026</p>
              </div>
            </div>
            <div>
              <input class="<?= event_field_class($fieldErrors, 'event_day') ?>" type="text" id="event_day" name="event_day" value="<?= htmlspecialchars($formValues['event_day'] ?? '') ?>" required>
              <?php if (isset($fieldErrors['event_day'])): ?><div class="error-message"><?= htmlspecialchars($fieldErrors['event_day']) ?></div><?php endif; ?>
            </div>
          </div>

          <div class="field">
            <div class="field-meta">
              <span class="field-icon">JAM</span>
              <div>
                <label for="event_time">Waktu</label>
                <p class="helper">Contoh: 19.45 WIB</p>
              </div>
            </div>
            <div>
              <input class="<?= event_field_class($fieldErrors, 'event_time') ?>" type="text" id="event_time" name="event_time" value="<?= htmlspecialchars($formValues['event_time'] ?? '') ?>" required>
              <?php if (isset($fieldErrors['event_time'])): ?><div class="error-message"><?= htmlspecialchars($fieldErrors['event_time']) ?></div><?php endif; ?>
            </div>
          </div>

          <div class="field">
            <div class="field-meta">
              <span class="field-icon">LOC</span>
              <div>
                <label for="event_location">Lokasi</label>
                <p class="helper">Online via Zoom atau nama venue</p>
              </div>
            </div>
            <div>
              <input class="<?= event_field_class($fieldErrors, 'event_location') ?>" type="text" id="event_location" name="event_location" value="<?= htmlspecialchars($formValues['event_location'] ?? '') ?>" required>
              <?php if (isset($fieldErrors['event_location'])): ?><div class="error-message"><?= htmlspecialchars($fieldErrors['event_location']) ?></div><?php endif; ?>
            </div>
          </div>

          <div class="field">
            <div class="field-meta">
              <span class="field-icon">MIC</span>
              <div>
                <label for="event_speaker">Pembicara</label>
                <p class="helper">Nama narasumber utama</p>
              </div>
            </div>
            <div>
              <input class="<?= event_field_class($fieldErrors, 'event_speaker') ?>" type="text" id="event_speaker" name="event_speaker" value="<?= htmlspecialchars($formValues['event_speaker'] ?? '') ?>" required>
              <?php if (isset($fieldErrors['event_speaker'])): ?><div class="error-message"><?= htmlspecialchars($fieldErrors['event_speaker']) ?></div><?php endif; ?>
            </div>
          </div>

          <div class="field">
            <div class="field-meta">
              <span class="field-icon">CAP</span>
              <div>
                <label for="event_capacity">Kapasitas</label>
                <p class="helper">Angka maksimal peserta</p>
              </div>
            </div>
            <div>
              <input class="<?= event_field_class($fieldErrors, 'event_capacity') ?>" type="number" min="1" step="1" id="event_capacity" name="event_capacity" value="<?= htmlspecialchars($formValues['event_capacity'] ?? '') ?>" required>
              <?php if (isset($fieldErrors['event_capacity'])): ?><div class="error-message"><?= htmlspecialchars($fieldErrors['event_capacity']) ?></div><?php endif; ?>
            </div>
          </div>

          <div class="field">
            <div class="field-meta">
              <span class="field-icon">IMG</span>
              <div>
                <label for="flyer">Flyer Acara</label>
                <p class="helper">Ditampilkan agar pengundang bisa unduh &amp; kirim ke calon peserta</p>
              </div>
            </div>
            <div class="flyer-field">
              <?php if (!empty($event['flyer_path'])): ?>
                <div class="flyer-current">
                  <img src="<?= htmlspecialchars($event['flyer_path']) ?>" alt="Flyer acara saat ini">
                  <label class="flyer-remove">
                    <input type="checkbox" name="remove_flyer" value="1"> Hapus flyer saat ini
                  </label>
                </div>
              <?php else: ?>
                <p class="helper">Belum ada flyer. Upload gambar flyer/poster acara.</p>
              <?php endif; ?>
              <input type="file" id="flyer" name="flyer" accept=".png,.jpg,.jpeg,.webp">
              <p class="helper">PNG, JPG, JPEG, WEBP. Maksimal <?= (int)(MAX_EVENT_FLYER_SIZE / 1024 / 1024) ?> MB.</p>
            </div>
          </div>
        </div>

        <div class="save-bar">
          <button type="submit" class="btn btn-primary">Simpan Detail Acara</button>
          <a class="btn btn-secondary" href="events.php">Batal</a>
        </div>
      </form>

      <aside class="preview-column">
        <section class="panel preview-card">
          <div class="panel-head">
            <span class="icon-badge">VIEW</span>
            <div>
              <h2>Preview Detail Event</h2>
              <p class="desc">Inilah tampilan ringkas informasi event di landing page.</p>
            </div>
          </div>
          <div class="preview-list">
            <div class="preview-item">
              <span class="field-icon">TGL</span>
              <div><div class="preview-label">Hari &amp; Tanggal</div><div class="preview-value"><?= htmlspecialchars(event_setting_value($formValues, 'event_day')) ?></div></div>
            </div>
            <div class="preview-item">
              <span class="field-icon">JAM</span>
              <div><div class="preview-label">Waktu</div><div class="preview-value"><?= htmlspecialchars(event_setting_value($formValues, 'event_time')) ?></div></div>
            </div>
            <div class="preview-item">
              <span class="field-icon">LOC</span>
              <div><div class="preview-label">Lokasi</div><div class="preview-value"><?= htmlspecialchars(event_setting_value($formValues, 'event_location')) ?></div></div>
            </div>
            <div class="preview-item">
              <span class="field-icon">MIC</span>
              <div><div class="preview-label">Pembicara</div><div class="preview-value"><?= htmlspecialchars(event_setting_value($formValues, 'event_speaker')) ?></div></div>
            </div>
            <div class="preview-item">
              <span class="field-icon">CAP</span>
              <div><div class="preview-label">Kapasitas</div><div class="preview-value"><?= htmlspecialchars(event_setting_value($formValues, 'event_capacity')) ?> peserta</div></div>
            </div>
          </div>
        </section>

        <section class="auto-box">
          <span class="icon-badge">AUTO</span>
          <div>
            <strong>Perubahan akan tampil otomatis</strong>
            <p>Setelah Anda menyimpan perubahan, informasi ini akan langsung diperbarui di landing page event.</p>
          </div>
        </section>
      </aside>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
