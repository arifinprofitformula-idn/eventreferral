<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
start_secure_session();

$brand = require_admin_for_brand(get_current_brand());

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pdo = get_db();
$notice = null;
$noticeType = 'success';
$fieldErrors = [];

$formValues = [
    'sender_name'  => $brand['sender_name'] ?? '',
    'sender_email' => $brand['sender_email'] ?? '',
];

if (isset($_GET['saved'])) {
    $notice = 'Identitas pengirim email berhasil diperbarui.';
    $noticeType = 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $notice = 'Sesi tidak valid. Silakan refresh halaman lalu coba lagi.';
        $noticeType = 'error';
    } else {
        $formValues['sender_name'] = trim(clean($_POST['sender_name'] ?? ''));
        $formValues['sender_email'] = trim(clean($_POST['sender_email'] ?? ''));

        if ($formValues['sender_name'] === '' || mb_strlen($formValues['sender_name']) < 2) {
            $fieldErrors['sender_name'] = 'Nama pengirim wajib diisi, minimal 2 karakter.';
        }
        if ($formValues['sender_email'] === '' || !filter_var($formValues['sender_email'], FILTER_VALIDATE_EMAIL)) {
            $fieldErrors['sender_email'] = 'Email pengirim tidak valid.';
        }

        $brandDomainRoot = preg_replace('/^www\./', '', strtolower($brand['domain']));
        $senderDomain = strtolower(substr(strrchr($formValues['sender_email'], '@') ?: '', 1));
        if (empty($fieldErrors['sender_email']) && $senderDomain !== $brandDomainRoot) {
            $fieldErrors['sender_email'] = 'Email pengirim harus menggunakan domain ' . htmlspecialchars($brandDomainRoot) . ' (contoh: info@' . htmlspecialchars($brandDomainRoot) . ').';
        }

        if (!empty($fieldErrors)) {
            $notice = 'Pengaturan belum dapat disimpan. Periksa kembali data yang diisi.';
            $noticeType = 'error';
        } else {
            try {
                $stmt = $pdo->prepare('UPDATE brands SET sender_name = ?, sender_email = ? WHERE id = ?');
                $stmt->execute([$formValues['sender_name'], $formValues['sender_email'], (int)$brand['id']]);

                header('Location: integrations.php?saved=1');
                exit;
            } catch (Exception $e) {
                $notice = 'Pengaturan belum dapat disimpan. Periksa kembali data yang diisi.';
                $noticeType = 'error';
            }
        }
    }
}

$brandName = $brand['name'] ?? $brand['slug'];
$brandDomainRoot = preg_replace('/^www\./', '', strtolower($brand['domain']));
$logoPath = !empty($brand['logo_path']) ? '..' . $brand['logo_path'] : '../assets/logo.png';
$brandInitial = strtoupper(mb_substr(trim($brandName), 0, 1));
$isSilverBrand = ($brand['theme_preset'] ?? '') === 'silver' || stripos($brand['slug'] ?? '', 'perak') !== false || stripos($brand['domain'] ?? '', 'perak') !== false;
$defaultEventSlug = $brand['default_event_slug'] ?? '';
$emailSettingsUrl = 'events.php';
if ($defaultEventSlug !== '' && is_valid_event_slug($defaultEventSlug)) {
    $defaultEvent = get_event_by_slug($defaultEventSlug);
    if ($defaultEvent && (int)$defaultEvent['brand_id'] === (int)$brand['id']) {
        $emailSettingsUrl = 'email-settings.php?event=' . urlencode($defaultEventSlug);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Email Sender Identity Center - <?= htmlspecialchars($brandName) ?></title>
<style>
  <?= get_theme_css_vars($brand) ?>
  :root {
    --bg: #0B0B0A;
    --bg-soft: #10100F;
    --surface: #171716;
    --surface-elevated: #20201E;
    --border-accent: <?= $isSilverBrand ? 'rgba(199,204,209,0.20)' : 'rgba(214,165,54,0.18)' ?>;
    --accent: <?= $isSilverBrand ? '#C7CCD1' : '#D6A536' ?>;
    --accent-soft: <?= $isSilverBrand ? '#F2F4F5' : '#F4D27A' ?>;
    --text: #F7F3E8;
    --muted: #A8A29A;
    --success: #22C55E;
    --danger: #EF4444;
    --warning: #F59E0B;
    --border-soft: rgba(255,255,255,0.09);
  }
  * { box-sizing: border-box; }
  html { scroll-behavior: smooth; }
  body {
    min-height: 100vh;
    margin: 0;
    background:
      radial-gradient(circle at 84% 8%, color-mix(in srgb, var(--accent) 23%, transparent), transparent 28vw),
      radial-gradient(circle at 8% 86%, color-mix(in srgb, var(--accent-soft) 8%, transparent), transparent 34vw),
      linear-gradient(135deg, var(--bg) 0%, var(--bg-soft) 52%, #070706 100%);
    color: var(--text);
    font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  }
  body::before {
    content: "";
    position: fixed;
    inset: 0;
    pointer-events: none;
    background-image:
      linear-gradient(rgba(255,255,255,0.024) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,0.016) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: radial-gradient(circle at 50% 16%, black, transparent 74%);
  }
  a { color: inherit; }
  .topbar {
    position: sticky;
    top: 0;
    z-index: 30;
    background: rgba(16,16,15,0.84);
    border-bottom: 1px solid var(--border-accent);
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
    gap: 24px;
  }
  .brand-link { display: inline-flex; align-items: center; gap: 14px; min-width: 0; text-decoration: none; }
  .brand-link img { width: 146px; height: auto; display: block; filter: drop-shadow(0 10px 20px rgba(0,0,0,0.32)); }
  .nav { display: flex; align-items: center; justify-content: flex-end; gap: 10px; flex-wrap: wrap; }
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
    transition: background 180ms ease, color 180ms ease, border-color 180ms ease, transform 180ms ease;
  }
  .nav a:hover { color: var(--text); background: rgba(255,255,255,0.04); transform: translateY(-1px); }
  .nav a svg {
    width: 16px;
    height: 16px;
    flex: 0 0 16px;
  }
  .nav a.active {
    color: var(--accent-soft);
    background: color-mix(in srgb, var(--accent) 10%, transparent);
    border-color: var(--border-accent);
    box-shadow: inset 0 -2px 0 color-mix(in srgb, var(--accent-soft) 45%, transparent);
  }
  .nav .logout { border-color: rgba(255,255,255,0.10); background: rgba(255,255,255,0.035); }
  .nav .logout.icon-only {
    justify-content: center;
    width: 42px;
    height: 42px;
    padding: 0;
  }
  .wrap { position: relative; padding-top: 28px; padding-bottom: 72px; }
  .hero {
    position: relative;
    overflow: hidden;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 24px;
    align-items: center;
    min-height: 230px;
    margin-bottom: 22px;
    padding: 34px 36px;
    border: 1px solid var(--border-accent);
    border-radius: 28px;
    background:
      radial-gradient(circle at 84% 28%, color-mix(in srgb, var(--accent-soft) 24%, transparent), transparent 24%),
      linear-gradient(135deg, rgba(32,32,30,0.96), rgba(23,23,22,0.94) 58%, color-mix(in srgb, var(--accent) 15%, transparent));
    box-shadow: 0 24px 70px rgba(0,0,0,0.34);
  }
  .hero-copy, .hero-actions, .hero-visual { position: relative; z-index: 1; }
  .breadcrumb { color: var(--muted); display: flex; gap: 9px; align-items: center; font-size: 12.5px; margin-bottom: 14px; }
  .breadcrumb strong { color: var(--text); }
  .badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    width: fit-content;
    color: var(--accent-soft);
    background: color-mix(in srgb, var(--accent) 11%, transparent);
    border: 1px solid var(--border-accent);
    border-radius: 999px;
    font-size: 11px;
    font-weight: 900;
    letter-spacing: .08em;
    text-transform: uppercase;
    padding: 8px 10px;
    margin-bottom: 12px;
  }
  h1 { margin: 0 0 10px; color: var(--text); font-size: clamp(30px, 4vw, 48px); line-height: 1.08; letter-spacing: 0; }
  h1 span { color: var(--accent); }
  .hero p { margin: 0; color: var(--muted); font-size: 15px; line-height: 1.7; max-width: 780px; }
  .hero-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 22px; }
  .hero-visual {
    display: grid;
    place-items: center;
    width: 162px;
    height: 162px;
    color: #16130b;
    background: linear-gradient(135deg, var(--accent), var(--accent-soft));
    border-radius: 42px;
    box-shadow: 0 18px 50px color-mix(in srgb, var(--accent) 24%, transparent);
    transform: rotate(-7deg);
  }
  .hero-visual svg { width: 86px; height: 86px; }
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    min-height: 46px;
    border-radius: 14px;
    border: 1px solid transparent;
    cursor: pointer;
    font: inherit;
    font-size: 13.5px;
    font-weight: 850;
    padding: 12px 18px;
    text-decoration: none;
    white-space: nowrap;
    transition: transform 180ms ease, border-color 180ms ease, background 180ms ease, opacity 180ms ease;
  }
  .btn:hover { transform: translateY(-1px); }
  .btn-primary { color: #111; background: linear-gradient(135deg, var(--accent), var(--accent-soft)); box-shadow: 0 12px 26px color-mix(in srgb, var(--accent) 22%, transparent); }
  .btn-secondary { color: var(--text); background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.12); }
  .notice { margin-bottom: 18px; border-radius: 18px; padding: 15px 18px; border: 1px solid var(--border-soft); font-size: 14px; line-height: 1.6; }
  .notice.success { color: #A7F3D0; background: rgba(34,197,94,0.10); border-color: rgba(34,197,94,0.22); }
  .notice.error { color: #FECACA; background: rgba(239,68,68,0.10); border-color: rgba(239,68,68,0.24); }
  .grid { display: grid; grid-template-columns: minmax(0, 58fr) minmax(360px, 42fr); gap: 22px; align-items: start; }
  .stack { position: sticky; top: 106px; }
  .panel {
    background: linear-gradient(180deg, rgba(32,32,30,0.76), rgba(23,23,22,0.86));
    border: 1px solid var(--border-accent);
    border-radius: 24px;
    box-shadow: 0 18px 50px rgba(0,0,0,0.22);
    padding: 24px;
  }
  .panel + .panel { margin-top: 14px; }
  .panel-head { display: flex; align-items: flex-start; gap: 14px; margin-bottom: 22px; }
  .icon-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    flex: 0 0 38px;
    color: #111;
    background: linear-gradient(135deg, var(--accent), var(--accent-soft));
    border-radius: 14px;
  }
  .icon-badge svg { width: 20px; height: 20px; }
  .panel h2, .panel h3 { color: var(--text); margin: 0; line-height: 1.25; }
  .panel h2 { font-size: 20px; }
  .panel h3 { font-size: 15px; }
  .panel p { color: var(--muted); margin: 5px 0 0; font-size: 12.5px; line-height: 1.6; }
  .sender-profile {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 20px;
    padding: 18px;
    border: 1px solid var(--border-accent);
    border-radius: 20px;
    background: color-mix(in srgb, var(--accent) 9%, rgba(0,0,0,0.20));
  }
  .avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 54px;
    height: 54px;
    flex: 0 0 54px;
    color: #111;
    background: linear-gradient(135deg, var(--accent), var(--accent-soft));
    border-radius: 50%;
    font-weight: 900;
    font-size: 20px;
  }
  .sender-profile strong { display: block; color: var(--text); font-size: 16px; margin-bottom: 4px; overflow-wrap: anywhere; }
  .sender-profile span { color: var(--muted); display: block; font-size: 13px; overflow-wrap: anywhere; }
  .sender-profile .profile-badge { color: var(--accent-soft); display: inline-flex; margin-top: 8px; border: 1px solid var(--border-accent); border-radius: 999px; padding: 6px 9px; font-size: 11px; font-weight: 850; }
  .field { margin-bottom: 18px; }
  .field label { display: flex; align-items: center; justify-content: space-between; gap: 10px; color: var(--text); font-size: 13px; font-weight: 750; margin-bottom: 8px; }
  input[type=text] {
    width: 100%;
    min-height: 52px;
    color: var(--text);
    background: #111110;
    border: 1px solid rgba(255,255,255,0.11);
    border-radius: 14px;
    font: inherit;
    font-size: 14px;
    outline: none;
    padding: 13px 14px;
    transition: border-color 180ms ease, box-shadow 180ms ease, background 180ms ease;
  }
  input:focus { border-color: color-mix(in srgb, var(--accent-soft) 54%, transparent); box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 10%, transparent); }
  .field.has-error input { border-color: rgba(239,68,68,0.48); box-shadow: 0 0 0 4px rgba(239,68,68,0.08); }
  .hint { color: var(--muted); font-size: 11.5px; line-height: 1.55; margin-top: 7px; }
  .field-error { color: #FCA5A5; font-size: 12px; margin-top: 7px; }
  .policy {
    display: flex;
    gap: 14px;
    margin: 20px 0;
    padding: 18px;
    border: 1px solid var(--border-accent);
    border-radius: 18px;
    background: color-mix(in srgb, var(--accent) 8%, rgba(0,0,0,0.16));
  }
  .policy strong { color: var(--text); display: block; margin-bottom: 5px; }
  .policy p { margin: 0; }
  .form-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 20px; }
  .inbox-card {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    gap: 14px;
    align-items: start;
    margin-top: 16px;
    padding: 18px;
    border: 1px solid rgba(255,255,255,0.10);
    border-radius: 18px;
    background: rgba(0,0,0,0.24);
  }
  .inbox-main { min-width: 0; }
  .inbox-from { color: var(--text); font-size: 14px; font-weight: 850; overflow-wrap: anywhere; }
  .inbox-email { color: var(--muted); font-size: 12px; margin-top: 2px; overflow-wrap: anywhere; }
  .inbox-subject { color: var(--text); font-size: 13px; font-weight: 800; margin-top: 10px; }
  .inbox-snippet { color: var(--muted); font-size: 12.5px; line-height: 1.55; margin-top: 5px; }
  .inbox-time { color: var(--muted); font-size: 12px; }
  .status-list { display: grid; gap: 0; margin-top: 14px; overflow: hidden; border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; }
  .status-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 13px 16px; background: rgba(255,255,255,0.018); border-bottom: 1px solid rgba(255,255,255,0.07); }
  .status-row:last-child { border-bottom: 0; }
  .status-row span:first-child { color: #d9d1c0; font-size: 13px; }
  .status-badge { border-radius: 999px; font-size: 11px; font-weight: 850; padding: 6px 9px; white-space: nowrap; }
  .status-badge.good { color: #BBF7D0; background: rgba(34,197,94,0.14); border: 1px solid rgba(34,197,94,0.24); }
  .status-badge.warn { color: #FDE68A; background: rgba(245,158,11,0.12); border: 1px solid rgba(245,158,11,0.24); }
  .status-badge.bad { color: #FECACA; background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.24); }
  .checklist { display: grid; gap: 10px; margin-top: 14px; }
  .check { display: flex; gap: 10px; align-items: flex-start; color: #d9d1c0; font-size: 12.5px; line-height: 1.45; }
  .check::before { content: ""; width: 9px; height: 9px; flex: 0 0 9px; margin-top: 5px; border-radius: 50%; background: var(--success); box-shadow: 0 0 0 4px rgba(34,197,94,0.10); }
  .tips { display: grid; gap: 9px; margin: 14px 0 0; padding-left: 0; list-style: none; }
  .tips li { color: #d9d1c0; font-size: 12.5px; line-height: 1.45; }
  .tips li::before { content: "- "; color: var(--accent-soft); }
  @media (max-width: 1080px) {
    .grid { grid-template-columns: 1fr; }
    .stack { position: static; }
    .hero { grid-template-columns: 1fr; }
    .hero-visual { display: none; }
  }
  @media (max-width: 760px) {
    .topbar-inner, .wrap { padding-left: 16px; padding-right: 16px; }
    .topbar-inner { display: grid; min-height: auto; padding-top: 16px; padding-bottom: 16px; }
    .brand-link img { width: 112px; }
    .nav { justify-content: flex-start; gap: 8px; }
    .nav a { font-size: 12.5px; padding: 10px 12px; }
    .wrap { padding-top: 18px; padding-bottom: 44px; }
    .hero { padding: 24px; border-radius: 22px; }
    .hero-actions, .form-actions { align-items: stretch; flex-direction: column; }
    .btn, .form-actions .btn { width: 100%; }
    .panel { border-radius: 20px; padding: 18px; }
    .sender-profile, .policy { align-items: flex-start; }
    .inbox-card { grid-template-columns: auto minmax(0, 1fr); }
    .inbox-time { display: none; }
  }
</style>
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand-link" href="dashboard.php" aria-label="<?= htmlspecialchars($brandName) ?> Admin">
      <img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($brandName) ?>">
    </a>
    <nav class="nav" aria-label="Navigasi admin">
      <a href="dashboard.php">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5V21a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1V10.5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Dashboard
      </a>
      <a href="events.php">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Kelola Event
      </a>
      <a class="active" href="integrations.php">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.07 0l2.83-2.83a5 5 0 0 0-7.07-7.07L11.5 4.43M14 11a5 5 0 0 0-7.07 0L4.1 13.83a5 5 0 1 0 7.07 7.07l1.33-1.33" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Pengaturan Integrasi
      </a>
      <a href="visitor-analytics.php">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 3v18h18M7 16v-5m5 5V8m5 8V5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Analitik Pengunjung
      </a>
      <a class="logout icon-only" href="logout.php" title="Keluar" aria-label="Keluar">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M10 17 15 12l-5-5M15 12H3m8-9h8a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </a>
    </nav>
  </div>
</header>

<main class="wrap">
  <section class="hero" aria-labelledby="page-title">
    <div class="hero-copy">
      <div class="breadcrumb"><span>Dashboard</span><span>/</span><strong>Integrasi</strong></div>
      <span class="badge">Pengaturan Integrasi</span>
      <h1 id="page-title">Identitas Pengirim Email — <span><?= htmlspecialchars($brandName) ?></span></h1>
      <p>Atur nama dan email pengirim yang tampil di inbox peserta saat sistem mengirim email otomatis untuk brand ini.</p>
      <div class="hero-actions">
        <a class="btn btn-secondary" href="dashboard.php">Kembali ke Dashboard</a>
        <a class="btn btn-secondary" href="<?= htmlspecialchars($emailSettingsUrl) ?>">Lihat Pengaturan Email</a>
      </div>
    </div>
    <div class="hero-visual" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm18 4-10 6L2 8m8 8 2 2 4-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </div>
  </section>

  <?php if ($notice): ?>
    <div class="notice <?= htmlspecialchars($noticeType) ?>"><?= htmlspecialchars($notice) ?></div>
  <?php endif; ?>

  <div class="grid">
    <form method="POST" class="panel" novalidate id="ig-form">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

      <div class="panel-head">
        <span class="icon-badge" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M4 4h16v16H4V4Zm16 4-8 5-8-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
        <div>
          <h2>Email Sender</h2>
          <p>Identitas ini digunakan setiap kali sistem mengirim email otomatis ke pendaftar brand ini.</p>
        </div>
      </div>

      <div class="sender-profile">
        <div class="avatar" id="ig-preview-avatar"><?= htmlspecialchars($brandInitial) ?></div>
        <div>
          <strong id="ig-preview-name"><?= htmlspecialchars($formValues['sender_name'] ?: 'Nama Pengirim') ?></strong>
          <span id="ig-preview-email"><?= htmlspecialchars($formValues['sender_email'] ?: 'email@' . $brandDomainRoot) ?></span>
          <span class="profile-badge">Brand Sender</span>
        </div>
      </div>

      <div class="field <?= isset($fieldErrors['sender_name']) ? 'has-error' : '' ?>">
        <label for="ig-sender-name">Nama Pengirim</label>
        <input type="text" id="ig-sender-name" name="sender_name" maxlength="150" value="<?= htmlspecialchars($formValues['sender_name']) ?>" placeholder="Contoh: Tim <?= htmlspecialchars($brandName) ?>">
        <div class="hint">Contoh: Tim Rahasia Emas atau Minra dari RahasiaEmas.ID.</div>
        <?php if (isset($fieldErrors['sender_name'])): ?><div class="field-error"><?= htmlspecialchars($fieldErrors['sender_name']) ?></div><?php endif; ?>
      </div>

      <div class="field <?= isset($fieldErrors['sender_email']) ? 'has-error' : '' ?>">
        <label for="ig-sender-email">Email Pengirim</label>
        <input type="text" id="ig-sender-email" name="sender_email" maxlength="150" value="<?= htmlspecialchars($formValues['sender_email']) ?>" placeholder="info@<?= htmlspecialchars($brandDomainRoot) ?>">
        <div class="hint">Gunakan email dengan domain brand agar terlihat profesional dan mengurangi risiko masuk spam.</div>
        <?php if (isset($fieldErrors['sender_email'])): ?><div class="field-error"><?= htmlspecialchars($fieldErrors['sender_email']) ?></div><?php endif; ?>
      </div>

      <div class="policy">
        <span class="icon-badge" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
        <div>
          <strong>Kebijakan Domain Email</strong>
          <p>Wajib menggunakan domain: <strong><?= htmlspecialchars($brandDomainRoot) ?></strong>. Email dengan domain lain akan ditolak untuk mencegah salah kirim antar brand.</p>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary" id="saveBtn">Simpan Pengaturan</button>
        <a class="btn btn-secondary" href="dashboard.php">Kembali ke Dashboard</a>
      </div>
    </form>

    <aside class="stack">
      <section class="panel">
        <div class="panel-head">
          <span class="icon-badge" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Zm10 3a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
          <div>
            <h2>Preview Tampilan Inbox</h2>
            <p>Simulasi tampilan sender di inbox peserta.</p>
          </div>
        </div>
        <div class="inbox-card">
          <div class="avatar" id="inbox-avatar"><?= htmlspecialchars($brandInitial) ?></div>
          <div class="inbox-main">
            <div class="inbox-from" id="inbox-name"><?= htmlspecialchars($formValues['sender_name'] ?: 'Nama Pengirim') ?></div>
            <div class="inbox-email" id="inbox-email"><?= htmlspecialchars($formValues['sender_email'] ?: 'email@' . $brandDomainRoot) ?></div>
            <div class="inbox-subject">Info Acara dari <?= htmlspecialchars($brandName) ?></div>
            <div class="inbox-snippet">Terima kasih sudah mendaftar. Detail acara Anda ada di email ini.</div>
          </div>
          <div class="inbox-time">10:30</div>
        </div>
        <p><span class="status-badge good">Akan tampil di inbox peserta</span></p>
      </section>

      <section class="panel">
        <div class="panel-head">
          <span class="icon-badge" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Zm-3-10 2 2 4-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
          <div>
            <h2>Status Sender</h2>
            <p>DNS belum diverifikasi oleh sistem.</p>
          </div>
        </div>
        <div class="status-list">
          <div class="status-row"><span>Nama Pengirim</span><strong class="status-badge" id="status-name"></strong></div>
          <div class="status-row"><span>Email Pengirim</span><strong class="status-badge" id="status-email"></strong></div>
          <div class="status-row"><span>Domain Email</span><strong class="status-badge" id="status-domain"></strong></div>
          <div class="status-row"><span>Siap Digunakan</span><strong class="status-badge" id="status-ready"></strong></div>
        </div>
      </section>

      <section class="panel">
        <div class="panel-head">
          <span class="icon-badge" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M9 11 12 14 22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
          <div>
            <h2>Checklist Pengirim Email</h2>
            <p>Pastikan sender mudah dikenali dan sesuai brand.</p>
          </div>
        </div>
        <div class="checklist">
          <div class="check">Gunakan nama pengirim yang mudah dikenali peserta.</div>
          <div class="check">Gunakan email dengan domain brand.</div>
          <div class="check">Hindari email gratisan seperti Gmail/Yahoo untuk pengiriman sistem.</div>
          <div class="check">Pastikan inbox tujuan dapat membalas jika diperlukan.</div>
          <div class="check">Uji email otomatis sebelum campaign berjalan.</div>
        </div>
      </section>

      <section class="panel">
        <div class="panel-head">
          <span class="icon-badge" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M9 18h6M10 22h4M12 2a7 7 0 0 0-4 12c.7.5 1 1.2 1 2h6c0-.8.3-1.5 1-2a7 7 0 0 0-4-12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
          <div>
            <h2>Tips Agar Email Lebih Dipercaya</h2>
          </div>
        </div>
        <ul class="tips">
          <li>Gunakan nama pengirim yang konsisten.</li>
          <li>Gunakan subject yang jelas.</li>
          <li>Hindari terlalu banyak huruf kapital.</li>
          <li>Pastikan link di email sesuai domain brand.</li>
          <li>Jika memakai SMTP/domain sendiri, pastikan SPF, DKIM, dan DMARC sudah disiapkan.</li>
        </ul>
      </section>
    </aside>
  </div>
</main>

<script>
(function () {
  const nameInput = document.getElementById('ig-sender-name');
  const emailInput = document.getElementById('ig-sender-email');
  const profileName = document.getElementById('ig-preview-name');
  const profileEmail = document.getElementById('ig-preview-email');
  const profileAvatar = document.getElementById('ig-preview-avatar');
  const inboxName = document.getElementById('inbox-name');
  const inboxEmail = document.getElementById('inbox-email');
  const inboxAvatar = document.getElementById('inbox-avatar');
  const statusName = document.getElementById('status-name');
  const statusEmail = document.getElementById('status-email');
  const statusDomain = document.getElementById('status-domain');
  const statusReady = document.getElementById('status-ready');
  const form = document.getElementById('ig-form');
  const saveBtn = document.getElementById('saveBtn');
  const brandDomain = <?= json_encode($brandDomainRoot) ?>;

  function badge(el, text, type) {
    if (!el) return;
    el.textContent = text;
    el.className = 'status-badge ' + type;
  }

  function initialFrom(name) {
    return name ? name.trim().charAt(0).toUpperCase() : '?';
  }

  function emailDomain(email) {
    const parts = email.split('@');
    return parts.length === 2 ? parts[1].toLowerCase() : '';
  }

  function updatePreview() {
    const name = nameInput.value.trim();
    const email = emailInput.value.trim();
    const displayName = name || 'Nama Pengirim';
    const displayEmail = email || 'email@' + brandDomain;
    const hasName = name.length >= 2;
    const hasEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    const domainOk = hasEmail && emailDomain(email) === brandDomain;
    const ready = hasName && hasEmail && domainOk;

    profileName.textContent = displayName;
    profileEmail.textContent = displayEmail;
    inboxName.textContent = displayName;
    inboxEmail.textContent = displayEmail;
    profileAvatar.textContent = initialFrom(displayName);
    inboxAvatar.textContent = initialFrom(displayName);

    badge(statusName, hasName ? 'Terisi' : 'Belum terisi', hasName ? 'good' : 'bad');
    badge(statusEmail, hasEmail ? 'Terisi' : 'Belum terisi', hasEmail ? 'good' : 'bad');
    badge(statusDomain, domainOk ? 'Sesuai Brand' : 'Perlu Dicek', domainOk ? 'good' : 'warn');
    badge(statusReady, ready ? 'Ya' : 'Belum', ready ? 'good' : 'warn');
  }

  nameInput.addEventListener('input', updatePreview);
  emailInput.addEventListener('input', updatePreview);
  form.addEventListener('submit', function () {
    saveBtn.disabled = true;
    saveBtn.textContent = 'Menyimpan...';
  });
  updatePreview();
})();
</script>
</body>
</html>
