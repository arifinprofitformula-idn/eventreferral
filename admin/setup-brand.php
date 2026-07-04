<?php
/**
 * admin/setup-brand.php
 * Onboarding brand baru — HANYA Coach yang tahu akses ini.
 * Dilindungi oleh MASTER_SETUP_KEY (bukan PIN/password admin brand manapun),
 * karena sistem ini sengaja TIDAK punya dashboard admin lintas-brand.
 *
 * Akses: admin/setup-brand.php?key=MASTER_SETUP_KEY
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
start_secure_session();

$providedKey = $_POST['key'] ?? $_GET['key'] ?? '';
if (!is_string($providedKey) || $providedKey === '' || !hash_equals(MASTER_SETUP_KEY, $providedKey)) {
    http_response_code(403);
    exit('Akses ditolak.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pdo = get_db();
$errors = [];
$success = null;

$formValues = [
    'slug' => '',
    'domain' => '',
    'name' => '',
    'tagline' => '',
    'whatsapp_default' => '',
    'disclaimer_text' => '',
    'theme_preset' => 'gold',
    'theme_primary' => '#C9A84C',
    'theme_charcoal' => '#1A1A1A',
    'theme_soft' => '#E8D5A3',
    'admin_username' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_brand'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Sesi tidak valid. Silakan refresh halaman lalu coba lagi.';
    } else {
        $formValues['slug'] = slugify(clean($_POST['slug'] ?? ''));
        $formValues['domain'] = strtolower(preg_replace('#^https?://#', '', trim(preg_replace('/^www\./', '', clean($_POST['domain'] ?? '')))));
        $formValues['name'] = trim(clean($_POST['name'] ?? ''));
        $formValues['tagline'] = trim(clean($_POST['tagline'] ?? ''));
        $whatsappRaw = trim((string)($_POST['whatsapp_default'] ?? ''));
        $formValues['whatsapp_default'] = $whatsappRaw !== '' ? normalize_whatsapp(clean($whatsappRaw)) : '';
        $formValues['disclaimer_text'] = trim(clean($_POST['disclaimer_text'] ?? ''));
        $formValues['theme_preset'] = in_array($_POST['theme_preset'] ?? '', ['gold', 'silver', 'bronze', 'custom'], true) ? $_POST['theme_preset'] : 'gold';
        $formValues['theme_primary'] = trim(clean($_POST['theme_primary'] ?? ''));
        $formValues['theme_charcoal'] = trim(clean($_POST['theme_charcoal'] ?? ''));
        $formValues['theme_soft'] = trim(clean($_POST['theme_soft'] ?? ''));
        $formValues['admin_username'] = trim(clean($_POST['admin_username'] ?? ''));
        $adminPassword = $_POST['admin_password'] ?? '';
        $adminPasswordConfirm = $_POST['admin_password_confirm'] ?? '';

        // ---- Validasi ----
        if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $formValues['slug']) || mb_strlen($formValues['slug']) < 2 || mb_strlen($formValues['slug']) > 60) {
            $errors[] = 'Slug brand tidak valid. Gunakan huruf kecil, angka, dan strip saja.';
        }
        if ($formValues['domain'] === '' || !preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $formValues['domain'])) {
            $errors[] = 'Domain tidak valid. Contoh: rahasiaperak.id (tanpa http:// atau www.)';
        }
        if ($formValues['name'] === '') {
            $errors[] = 'Nama brand wajib diisi.';
        }
        if ($formValues['admin_username'] === '' || mb_strlen($formValues['admin_username']) < 3) {
            $errors[] = 'Username admin minimal 3 karakter.';
        }
        if (mb_strlen($adminPassword) < 8) {
            $errors[] = 'Password admin minimal 8 karakter.';
        } elseif ($adminPassword !== $adminPasswordConfirm) {
            $errors[] = 'Konfirmasi password admin tidak cocok.';
        }
        if ($formValues['theme_preset'] === 'custom') {
            foreach (['theme_primary', 'theme_charcoal', 'theme_soft'] as $colorField) {
                if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $formValues[$colorField])) {
                    $errors[] = 'Warna custom tidak valid (format #RRGGBB): ' . $colorField;
                }
            }
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare('SELECT id FROM brands WHERE slug = ? OR domain = ?');
            $stmt->execute([$formValues['slug'], $formValues['domain']]);
            if ($stmt->fetch()) {
                $errors[] = 'Slug atau domain ini sudah dipakai brand lain.';
            }
        }

        $logoPath = null;
        if (empty($errors) && isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $logoPath = safe_upload_logo($_FILES['logo']['tmp_name'], $_FILES['logo']['name'], $formValues['slug']);
            if (!$logoPath) {
                $errors[] = 'Logo gagal diupload. Pastikan format PNG/JPG/JPEG/WEBP/SVG dan ukuran maksimal ' . (MAX_LOGO_SIZE / 1024 / 1024) . ' MB.';
            }
        }

        if (empty($errors)) {
            $defaultEventSlug = $formValues['slug'] . '-default';

            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare('
                    INSERT INTO brands
                        (slug, domain, name, tagline, logo_path, whatsapp_default, disclaimer_text,
                         theme_preset, theme_primary, theme_charcoal, theme_soft,
                         admin_username, admin_password_hash, default_event_slug, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "active")
                ');
                $stmt->execute([
                    $formValues['slug'],
                    $formValues['domain'],
                    $formValues['name'],
                    $formValues['tagline'] !== '' ? $formValues['tagline'] : null,
                    $logoPath,
                    $formValues['whatsapp_default'] !== '' ? $formValues['whatsapp_default'] : null,
                    $formValues['disclaimer_text'] !== '' ? $formValues['disclaimer_text'] : null,
                    $formValues['theme_preset'],
                    $formValues['theme_preset'] === 'custom' ? $formValues['theme_primary'] : null,
                    $formValues['theme_preset'] === 'custom' ? $formValues['theme_charcoal'] : null,
                    $formValues['theme_preset'] === 'custom' ? $formValues['theme_soft'] : null,
                    $formValues['admin_username'],
                    password_hash($adminPassword, PASSWORD_DEFAULT),
                    $defaultEventSlug,
                ]);
                $newBrandId = (int)$pdo->lastInsertId();

                // Buat event root domain otomatis untuk brand ini.
                $stmt = $pdo->prepare('
                    INSERT INTO events (brand_id, slug, name, status, whatsapp_default)
                    VALUES (?, ?, ?, "active", ?)
                ');
                $stmt->execute([
                    $newBrandId,
                    $defaultEventSlug,
                    $formValues['name'] . ' — Acara Utama',
                    $formValues['whatsapp_default'] !== '' ? $formValues['whatsapp_default'] : null,
                ]);

                $pdo->commit();

                $success = [
                    'slug' => $formValues['slug'],
                    'domain' => $formValues['domain'],
                    'admin_username' => $formValues['admin_username'],
                ];

                $formValues['slug'] = $formValues['domain'] = $formValues['name'] = $formValues['admin_username'] = '';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'Gagal membuat brand: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Setup Brand Baru — Brand Setup Console</title>
<style>
  :root {
    --bg-0: #0B0B0A;
    --bg-1: #10100F;
    --surface: #171716;
    --surface-elevated: #20201E;
    --border-gold: rgba(214,165,54,0.18);
    --gold: #D6A536;
    --gold-soft: #F4D27A;
    --text: #F7F3E8;
    --text-secondary: #A8A29A;
    --danger: #EF4444;
    --success: #22C55E;
    --warning: #F59E0B;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html, body { overflow-x: hidden; }
  body {
    background: var(--bg-0);
    color: var(--text);
    font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    min-height: 100vh;
    line-height: 1.5;
    background-image:
      radial-gradient(720px 480px at 92% -6%, rgba(214,165,54,0.10), transparent 60%),
      radial-gradient(640px 480px at 4% 108%, rgba(214,165,54,0.07), transparent 60%);
    background-attachment: fixed;
  }

  .topbar {
    position: sticky; top: 0; z-index: 20;
    height: 72px;
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 32px;
    background: rgba(16,16,15,0.78);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border-bottom: 1px solid rgba(214,165,54,0.14);
  }
  .topbar-brand { display: flex; align-items: center; gap: 12px; }
  .topbar-emblem {
    width: 38px; height: 38px; border-radius: 10px;
    background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    display: flex; align-items: center; justify-content: center;
    color: #171716; font-weight: 800; font-size: 14px; letter-spacing: 0.02em;
    flex-shrink: 0;
  }
  .topbar-label { font-size: 14.5px; font-weight: 600; color: var(--text); }
  .badge-secure {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 12px; font-weight: 600; letter-spacing: 0.03em;
    color: var(--gold-soft);
    background: rgba(214,165,54,0.10);
    border: 1px solid var(--border-gold);
    padding: 6px 12px; border-radius: 999px;
  }

  .container { max-width: 1320px; margin: 0 auto; padding: 32px; }
  @media (max-width: 640px) { .container { padding: 16px; } .topbar { padding: 0 16px; } }

  .hero {
    position: relative;
    overflow: hidden;
    border-radius: 28px;
    border: 1px solid var(--border-gold);
    background:
      radial-gradient(420px 260px at 88% 10%, rgba(214,165,54,0.16), transparent 65%),
      linear-gradient(160deg, var(--surface), var(--bg-1));
    padding: 32px 36px;
    min-height: 180px;
    display: flex; align-items: center; justify-content: space-between; gap: 24px;
    margin-bottom: 24px;
  }
  .hero-eyebrow {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 11.5px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
    color: #17170f; background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    padding: 5px 12px; border-radius: 999px; margin-bottom: 14px;
  }
  .hero h1 { font-size: clamp(22px, 3vw, 30px); font-weight: 700; margin-bottom: 8px; text-wrap: balance; }
  .hero p { color: var(--text-secondary); font-size: 14.5px; max-width: 52ch; }
  .hero-emblem {
    width: 96px; height: 96px; border-radius: 50%;
    border: 1px solid var(--border-gold);
    background: radial-gradient(circle at 35% 30%, rgba(214,165,54,0.28), rgba(214,165,54,0.02) 70%);
    display: flex; align-items: center; justify-content: center;
    color: var(--gold-soft); flex-shrink: 0;
  }
  @media (max-width: 720px) { .hero { flex-direction: column; text-align: left; padding: 24px; } .hero-emblem { display: none; } }

  .grid { display: grid; grid-template-columns: 1.85fr 1fr; gap: 24px; align-items: start; }
  @media (max-width: 980px) { .grid { grid-template-columns: 1fr; } }

  .card {
    background: var(--surface);
    border: 1px solid var(--border-gold);
    border-radius: 22px;
    padding: 26px 28px;
    margin-bottom: 20px;
    transition: border-color 180ms ease, transform 180ms ease;
  }
  .card-title { font-size: 16.5px; font-weight: 700; margin-bottom: 3px; }
  .card-subtitle { font-size: 13px; color: var(--text-secondary); margin-bottom: 22px; }
  .card-head-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 3px; }

  .field-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
  @media (max-width: 560px) { .field-grid { grid-template-columns: 1fr; } }

  .field { margin-bottom: 18px; }
  .field:last-child { margin-bottom: 0; }
  label { display: block; font-size: 13px; font-weight: 700; margin-bottom: 7px; color: var(--gold-soft); }
  input[type="text"], input[type="password"], textarea, select {
    width: 100%;
    min-height: 48px;
    background: #111110;
    border: 1px solid rgba(255,255,255,0.10);
    border-radius: 12px;
    padding: 12px 14px;
    color: var(--text);
    font-size: 14px;
    font-family: inherit;
    outline: none;
    transition: border-color 180ms ease, box-shadow 180ms ease;
  }
  input:focus, textarea:focus, select:focus {
    border-color: var(--gold);
    box-shadow: 0 0 0 4px rgba(214,165,54,0.18);
  }
  textarea { resize: vertical; min-height: 84px; }
  select { cursor: pointer; }
  .helper { font-size: 12px; color: var(--text-secondary); margin-top: 6px; }

  .section-label {
    font-size: 11.5px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase;
    color: var(--text-secondary);
    margin: 26px 0 14px;
    padding-top: 18px;
    border-top: 1px solid rgba(255,255,255,0.06);
  }
  .field-grid + .section-label,
  .card > .field:first-child + .section-label { border-top: none; padding-top: 0; }

  .color-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
  @media (max-width: 480px) { .color-row { grid-template-columns: 1fr; } }
  #customColors { display: none; }

  /* Dropzone */
  .dropzone {
    position: relative;
    border: 1.5px dashed var(--border-gold);
    border-radius: 16px;
    padding: 22px;
    display: flex; align-items: center; gap: 16px;
    background: rgba(214,165,54,0.03);
    cursor: pointer;
    transition: border-color 180ms ease, background 180ms ease;
  }
  .dropzone:hover, .dropzone.is-dragover { border-color: var(--gold); background: rgba(214,165,54,0.07); }
  .dropzone input[type="file"] {
    position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer;
  }
  .dropzone-icon {
    width: 46px; height: 46px; border-radius: 12px; flex-shrink: 0;
    background: var(--surface-elevated);
    display: flex; align-items: center; justify-content: center;
    color: var(--gold-soft);
    overflow: hidden;
  }
  .dropzone-icon img { width: 100%; height: 100%; object-fit: cover; }
  .dropzone-text strong { display: block; font-size: 14px; font-weight: 600; }
  .dropzone-text span { font-size: 12.5px; color: var(--text-secondary); }
  .dropzone-meta { margin-top: 12px; display: flex; flex-wrap: wrap; gap: 8px; }
  .format-pill {
    font-size: 11.5px; color: var(--text-secondary);
    background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
    padding: 4px 10px; border-radius: 999px;
    display: inline-flex; align-items: center; gap: 5px;
  }
  .format-pill svg { color: var(--success); flex-shrink: 0; }

  /* Alerts */
  .alert { border-radius: 14px; padding: 16px 18px; margin-bottom: 20px; }
  .alert-error { background: rgba(239,68,68,0.10); border: 1px solid rgba(239,68,68,0.28); }
  .alert-error li { color: #FCA5A5; font-size: 13.5px; margin-left: 18px; margin-bottom: 4px; }
  .alert-error li:last-child { margin-bottom: 0; }
  .alert-success { background: rgba(34,197,94,0.10); border: 1px solid rgba(34,197,94,0.28); }
  .alert-success p { color: #A7F3D0; font-size: 13.5px; line-height: 1.75; }
  .alert-success code { background: rgba(0,0,0,0.35); padding: 2px 7px; border-radius: 5px; color: var(--gold-soft); }

  /* Actions */
  .actions { display: flex; justify-content: flex-end; gap: 12px; margin-top: 26px; }
  @media (max-width: 560px) { .actions { flex-direction: column-reverse; } .actions a, .actions button { width: 100%; } }
  .btn {
    font-size: 14.5px; font-weight: 700; border-radius: 12px; padding: 13px 24px;
    cursor: pointer; border: none; text-align: center; text-decoration: none;
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    transition: transform 180ms ease, box-shadow 180ms ease;
  }
  .btn:hover { transform: translateY(-1px); }
  .btn-primary {
    background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    color: #171716;
    box-shadow: 0 8px 24px -8px rgba(214,165,54,0.55);
  }
  .btn-secondary {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.12);
    color: var(--text);
  }

  /* Preview card */
  .preview-card-body {
    border-radius: 16px;
    border: 1px solid var(--border-gold);
    background: linear-gradient(160deg, var(--surface-elevated), var(--bg-1));
    padding: 20px;
  }
  .preview-top { display: flex; align-items: center; gap: 14px; margin-bottom: 14px; }
  .preview-logo {
    width: 56px; height: 56px; border-radius: 14px; flex-shrink: 0;
    background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    display: flex; align-items: center; justify-content: center;
    color: #171716; font-weight: 800; font-size: 18px; overflow: hidden;
    border: 1px solid var(--border-gold);
  }
  .preview-logo img { width: 100%; height: 100%; object-fit: cover; }
  .preview-name { font-size: 16px; font-weight: 700; line-height: 1.3; word-break: break-word; }
  .preview-domain { font-size: 12.5px; color: var(--text-secondary); display: flex; align-items: center; gap: 5px; margin-top: 2px; }
  .preview-tagline { font-size: 13px; color: var(--text-secondary); margin-bottom: 14px; line-height: 1.6; }
  .theme-badge {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 12px; font-weight: 600;
    background: rgba(214,165,54,0.12); color: var(--gold-soft);
    border: 1px solid var(--border-gold);
    padding: 4px 11px; border-radius: 999px;
  }
  .preview-status { display: flex; flex-direction: column; gap: 8px; margin-top: 16px; }
  .status-row { display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: var(--text-secondary); }
  .status-row svg { color: var(--success); flex-shrink: 0; }

  .live-dot {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 11px; font-weight: 600; color: var(--success);
  }
  .live-dot::before {
    content: ''; width: 6px; height: 6px; border-radius: 50%; background: var(--success);
    box-shadow: 0 0 0 3px rgba(34,197,94,0.18);
  }

  .checklist { list-style: none; display: flex; flex-direction: column; gap: 12px; }
  .checklist li { display: flex; align-items: flex-start; gap: 10px; font-size: 13px; color: var(--text-secondary); line-height: 1.55; }
  .checklist svg { color: var(--gold); flex-shrink: 0; margin-top: 2px; }

  .help-card p { font-size: 13px; color: var(--text-secondary); line-height: 1.6; margin-bottom: 14px; }
  .help-link {
    font-size: 13px; font-weight: 700; color: var(--gold-soft);
    display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
    border: 1px solid var(--border-gold); padding: 8px 14px; border-radius: 10px;
  }
  .help-link:hover { border-color: var(--gold); }

  @media (prefers-reduced-motion: reduce) {
    * { transition: none !important; }
  }
</style>
</head>
<body>

<div class="topbar">
  <div class="topbar-brand">
    <div class="topbar-emblem">RE</div>
    <span class="topbar-label">Brand Setup</span>
  </div>
  <span class="badge-secure">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 2 4 5v6c0 5 3.4 8.6 8 11 4.6-2.4 8-6 8-11V5l-8-3Z"/><path d="m9 12 2 2 4-4"/></svg>
    Secure Setup
  </span>
</div>

<div class="container">

  <div class="hero">
    <div>
      <span class="hero-eyebrow">Brand Setup</span>
      <h1>Setup Brand Baru</h1>
      <p>Konfigurasi identitas brand, domain, logo, dan preferensi tampilan untuk pengalaman yang konsisten.</p>
    </div>
    <div class="hero-emblem">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 2 4 5v6c0 5 3.4 8.6 8 11 4.6-2.4 8-6 8-11V5l-8-3Z"/><path d="M9 12.5 11 14.5 15 10"/></svg>
    </div>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success">
      <p>
        Brand berhasil disimpan — <code><?= htmlspecialchars($success['slug']) ?></code>.<br>
        Domain: <code><?= htmlspecialchars($success['domain']) ?></code><br>
        Username admin: <code><?= htmlspecialchars($success['admin_username']) ?></code><br><br>
        Langkah selanjutnya (production): arahkan Addon Domain <code><?= htmlspecialchars($success['domain']) ?></code>
        ke folder public_html yang sama, lalu aktifkan SSL. Untuk tes lokal, buka
        <code>?__brand=<?= htmlspecialchars($success['slug']) ?></code> dari localhost.
      </p>
    </div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" id="brandForm">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="key" value="<?= htmlspecialchars($providedKey) ?>">
    <input type="hidden" name="create_brand" value="1">

    <div class="grid">
      <!-- Kolom kiri: form -->
      <div>
        <div class="card">
          <div class="card-title">Informasi Brand</div>
          <div class="card-subtitle">Lengkapi identitas utama brand yang akan digunakan di sistem.</div>

          <div class="field-grid">
            <div class="field">
              <label for="slug">Slug Brand</label>
              <input type="text" id="slug" name="slug" placeholder="rahasiaperak" value="<?= htmlspecialchars($formValues['slug']) ?>" required>
              <p class="helper">Huruf kecil, angka, dan strip. Digunakan untuk folder logo dan override <code>?__brand=</code> saat testing.</p>
            </div>
            <div class="field">
              <label for="domain">Domain</label>
              <input type="text" id="domain" name="domain" placeholder="rahasiaperak.id" value="<?= htmlspecialchars($formValues['domain']) ?>" required>
              <p class="helper">Tanpa http:// atau www. Pastikan domain sudah diarahkan melalui Addon Domain.</p>
            </div>
          </div>

          <div class="field">
            <label for="name">Nama Brand</label>
            <input type="text" id="name" name="name" placeholder="Rahasia Perak" value="<?= htmlspecialchars($formValues['name']) ?>" required>
            <p class="helper">Nama resmi yang tampil pada halaman publik.</p>
          </div>

          <div class="field">
            <label for="tagline">Tagline (opsional)</label>
            <input type="text" id="tagline" name="tagline" value="<?= htmlspecialchars($formValues['tagline']) ?>">
            <p class="helper">Opsional. Tampil sebagai deskripsi pendek brand.</p>
          </div>

          <div class="field">
            <label for="logo">Logo (opsional)</label>
            <div class="dropzone" id="dropzone">
              <div class="dropzone-icon" id="dropzoneIcon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 16V4M12 4 7 9M12 4l5 5"/><path d="M5 16v2a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-2"/></svg>
              </div>
              <div class="dropzone-text">
                <strong id="dropzoneTitle">Drag &amp; drop logo di sini</strong>
                <span id="dropzoneSubtitle">atau klik untuk memilih file</span>
              </div>
              <input type="file" id="logo" name="logo" accept=".png,.jpg,.jpeg,.webp,.svg">
            </div>
            <div class="dropzone-meta">
              <span class="format-pill"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m5 12 5 5L20 7"/></svg>PNG, JPG, JPEG, WEBP, SVG</span>
              <span class="format-pill"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m5 12 5 5L20 7"/></svg>Maksimal <?= (int)(MAX_LOGO_SIZE / 1024 / 1024) ?> MB</span>
              <span class="format-pill"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m5 12 5 5L20 7"/></svg>Disarankan rasio 1:1 atau 4:1</span>
            </div>
          </div>

          <div class="field-grid">
            <div class="field">
              <label for="whatsapp_default">WhatsApp Default (opsional)</label>
              <input type="text" id="whatsapp_default" name="whatsapp_default" placeholder="628111111111" value="<?= htmlspecialchars($formValues['whatsapp_default']) ?>">
              <p class="helper">Nomor utama untuk kontak pelanggan.</p>
            </div>
            <div class="field">
              <label for="disclaimer_text">Disclaimer (opsional)</label>
              <textarea id="disclaimer_text" name="disclaimer_text"><?= htmlspecialchars($formValues['disclaimer_text']) ?></textarea>
              <p class="helper">Ditampilkan di footer atau halaman tertentu sesuai kebutuhan.</p>
            </div>
          </div>

          <div class="field">
            <label for="theme_preset">Preset Tema</label>
            <select id="theme_preset" name="theme_preset">
              <?php foreach (['gold' => 'Gold', 'silver' => 'Silver', 'bronze' => 'Bronze', 'custom' => 'Custom'] as $val => $label): ?>
                <option value="<?= $val ?>" <?= $formValues['theme_preset'] === $val ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
            <p class="helper">Pilih tema utama untuk tampilan brand.</p>
          </div>

          <div class="field" id="customColors">
            <label>Warna Custom</label>
            <div class="color-row">
              <input type="text" name="theme_primary" placeholder="#C9A84C" value="<?= htmlspecialchars($formValues['theme_primary']) ?>">
              <input type="text" name="theme_charcoal" placeholder="#1A1A1A" value="<?= htmlspecialchars($formValues['theme_charcoal']) ?>">
              <input type="text" name="theme_soft" placeholder="#E8D5A3" value="<?= htmlspecialchars($formValues['theme_soft']) ?>">
            </div>
            <p class="helper">Format #RRGGBB — urutan: primary, charcoal, soft.</p>
          </div>

          <div class="section-label">Kredensial Admin Brand Ini</div>

          <div class="field">
            <label for="admin_username">Username Admin</label>
            <input type="text" id="admin_username" name="admin_username" value="<?= htmlspecialchars($formValues['admin_username']) ?>" required>
          </div>

          <div class="field-grid">
            <div class="field">
              <label for="admin_password">Password Admin</label>
              <input type="password" id="admin_password" name="admin_password" minlength="8" required>
            </div>
            <div class="field">
              <label for="admin_password_confirm">Konfirmasi Password Admin</label>
              <input type="password" id="admin_password_confirm" name="admin_password_confirm" minlength="8" required>
            </div>
          </div>

          <div class="actions">
            <a href="javascript:history.back()" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan Brand</button>
          </div>
        </div>
      </div>

      <!-- Kolom kanan: preview & checklist -->
      <div>
        <div class="card">
          <div class="card-head-row">
            <div class="card-title" style="margin-bottom:0;">Preview Brand</div>
            <span class="live-dot">Live Preview</span>
          </div>
          <div class="card-subtitle" style="margin-bottom:16px;">Tampilan ringkas berdasarkan isian di sebelah kiri.</div>

          <div class="preview-card-body">
            <div class="preview-top">
              <div class="preview-logo" id="previewLogo">RE</div>
              <div>
                <div class="preview-name" id="previewName">Nama Brand</div>
                <div class="preview-domain">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18Z"/></svg>
                  <span id="previewDomain">domain.id</span>
                </div>
              </div>
            </div>
            <div class="preview-tagline" id="previewTagline" style="display:none;"></div>
            <span class="theme-badge" id="previewTheme">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="9"/></svg>
              Tema: Gold
            </span>
            <div class="preview-status">
              <div class="status-row"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m5 12 5 5L20 7"/></svg>Identitas Brand</div>
              <div class="status-row"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m5 12 5 5L20 7"/></svg>Domain Aktif</div>
              <div class="status-row"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m5 12 5 5L20 7"/></svg>Siap Digunakan</div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-title">Panduan &amp; Checklist</div>
          <div class="card-subtitle">&nbsp;</div>
          <ul class="checklist">
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m5 12 5 5L20 7"/></svg>Slug brand hanya boleh huruf kecil, angka, dan strip (-). Contoh: <code>rahasiaperak</code>.</li>
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m5 12 5 5L20 7"/></svg>Logo yang disarankan berformat PNG atau SVG. Maksimal ukuran file <?= (int)(MAX_LOGO_SIZE / 1024 / 1024) ?> MB.</li>
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m5 12 5 5L20 7"/></svg>Pastikan domain sudah diarahkan ke server melalui Addon Domain di cPanel.</li>
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m5 12 5 5L20 7"/></svg>Setelah disimpan, brand baru akan tersedia di sistem dan dapat dikonfigurasi lebih lanjut.</li>
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m5 12 5 5L20 7"/></svg>WhatsApp default boleh dikosongkan jika belum tersedia.</li>
          </ul>
        </div>

        <div class="card help-card">
          <div class="card-title">Butuh bantuan?</div>
          <p>Pelajari dokumentasi setup brand atau hubungi tim support.</p>
          <a href="#" class="help-link">
            Lihat Dokumentasi
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M7 17 17 7M8 7h9v9"/></svg>
          </a>
        </div>
      </div>
    </div>
  </form>
</div>

<script>
  // ---- Toggle warna custom (fungsional, tidak mengubah logic backend) ----
  var presetSelect = document.getElementById('theme_preset');
  var customColors = document.getElementById('customColors');
  function toggleCustomColors() {
    customColors.style.display = presetSelect.value === 'custom' ? 'block' : 'none';
  }
  presetSelect.addEventListener('change', toggleCustomColors);
  toggleCustomColors();

  // ---- Live preview (kosmetik, tidak menyentuh validasi/submit) ----
  var nameInput = document.getElementById('name');
  var domainInput = document.getElementById('domain');
  var taglineInput = document.getElementById('tagline');
  var previewName = document.getElementById('previewName');
  var previewDomain = document.getElementById('previewDomain');
  var previewTagline = document.getElementById('previewTagline');
  var previewLogo = document.getElementById('previewLogo');
  var previewTheme = document.getElementById('previewTheme');
  var topbarEmblem = document.querySelector('.topbar-emblem');

  function initialsOf(text) {
    var words = (text || '').trim().split(/\s+/).filter(Boolean);
    if (!words.length) return 'RE';
    if (words.length === 1) return words[0].slice(0, 2).toUpperCase();
    return (words[0][0] + words[1][0]).toUpperCase();
  }

  function updatePreview() {
    var nameVal = nameInput.value.trim();
    previewName.textContent = nameVal || 'Nama Brand';
    previewDomain.textContent = domainInput.value.trim() || 'domain.id';

    var taglineVal = taglineInput.value.trim();
    if (taglineVal) {
      previewTagline.textContent = taglineVal;
      previewTagline.style.display = 'block';
    } else {
      previewTagline.style.display = 'none';
    }

    if (!previewLogo.querySelector('img')) {
      var initials = initialsOf(nameVal);
      previewLogo.textContent = initials;
      topbarEmblem.textContent = initials;
    }

    var themeLabels = { gold: 'Gold', silver: 'Silver', bronze: 'Bronze', custom: 'Custom' };
    previewTheme.lastChild.textContent = ' Tema: ' + (themeLabels[presetSelect.value] || 'Gold');
  }
  [nameInput, domainInput, taglineInput].forEach(function (el) {
    el.addEventListener('input', updatePreview);
  });
  presetSelect.addEventListener('change', updatePreview);
  updatePreview();

  // ---- Dropzone: drag & drop + preview logo (input file asli tetap dipakai) ----
  var dropzone = document.getElementById('dropzone');
  var logoInput = document.getElementById('logo');
  var dropzoneIcon = document.getElementById('dropzoneIcon');
  var dropzoneTitle = document.getElementById('dropzoneTitle');
  var dropzoneSubtitle = document.getElementById('dropzoneSubtitle');

  function showLogoPreview(file) {
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function (e) {
      dropzoneIcon.innerHTML = '<img src="' + e.target.result + '" alt="Preview logo">';
      previewLogo.innerHTML = '<img src="' + e.target.result + '" alt="Logo brand">';
      dropzoneTitle.textContent = file.name;
      dropzoneSubtitle.textContent = 'Klik atau drop file lain untuk mengganti logo';
    };
    reader.readAsDataURL(file);
  }

  logoInput.addEventListener('change', function () {
    if (logoInput.files && logoInput.files[0]) showLogoPreview(logoInput.files[0]);
  });

  ['dragenter', 'dragover'].forEach(function (evt) {
    dropzone.addEventListener(evt, function (e) {
      e.preventDefault();
      dropzone.classList.add('is-dragover');
    });
  });
  ['dragleave', 'drop'].forEach(function (evt) {
    dropzone.addEventListener(evt, function (e) {
      e.preventDefault();
      dropzone.classList.remove('is-dragover');
    });
  });
  dropzone.addEventListener('drop', function (e) {
    var files = e.dataTransfer.files;
    if (files && files[0]) {
      logoInput.files = files;
      showLogoPreview(files[0]);
    }
  });
</script>
</body>
</html>
