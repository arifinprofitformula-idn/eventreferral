<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/ai_content.php';
start_secure_session();

$brand = require_superadmin_for_brand(get_current_brand());
$pdo = get_db();

if (!table_exists($pdo, 'ai_settings')) {
    http_response_code(500);
    exit('Tabel ai_settings belum tersedia. Jalankan migrate_v16_ai_settings.sql terlebih dahulu.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$notice = null;
$noticeType = 'success';
$fieldErrors = [];

$knownProviders = [
    'sumopod' => ['label' => 'SumoPod AI (kompatibel OpenAI SDK)', 'default_model' => 'gpt-4o-mini'],
    'groq' => ['label' => 'Groq', 'default_model' => 'llama-3.3-70b-versatile'],
    'gemini' => ['label' => 'Google Gemini', 'default_model' => 'gemini-2.5-flash'],
];

$current = get_ai_provider_settings();

if (isset($_GET['saved'])) {
    $notice = 'Pengaturan AI berhasil disimpan.';
    $noticeType = 'success';
}

$formValues = [
    'provider' => $current['provider'],
    'model' => $current['model'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $notice = 'Sesi tidak valid. Silakan refresh halaman lalu coba lagi.';
        $noticeType = 'error';
    } else {
        $formValues['provider'] = strtolower(trim($_POST['provider'] ?? ''));
        $formValues['model'] = trim(clean($_POST['model'] ?? ''));
        $newApiKey = trim($_POST['api_key'] ?? '');
        $clearKey = !empty($_POST['clear_api_key']);

        if (!array_key_exists($formValues['provider'], $knownProviders)) {
            $fieldErrors['provider'] = 'Provider tidak dikenal.';
        }

        if (empty($fieldErrors)) {
            $finalApiKey = $current['api_key'];
            if ($clearKey) {
                $finalApiKey = '';
            } elseif ($newApiKey !== '') {
                $finalApiKey = $newApiKey;
            }

            $finalModel = $formValues['model'] !== '' ? $formValues['model'] : $knownProviders[$formValues['provider']]['default_model'];

            save_ai_provider_settings($formValues['provider'], $finalApiKey, $finalModel);

            header('Location: ai-settings.php?saved=1');
            exit;
        }

        $notice = 'Pengaturan belum dapat disimpan. Periksa kembali data yang diisi.';
        $noticeType = 'error';
    }
}

$maskedKey = '';
if ($current['api_key'] !== '') {
    $len = strlen($current['api_key']);
    $maskedKey = substr($current['api_key'], 0, 6) . str_repeat('•', max(4, $len - 10)) . substr($current['api_key'], -4);
}

$logoPath = $brand['logo_path'] ? '..' . $brand['logo_path'] : '../assets/logo.png';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pengaturan AI — <?= htmlspecialchars($brand['name']) ?></title>
<style>
  <?= get_theme_css_vars($brand) ?>
  :root {
    --bg:#0B0B0A; --bg-soft:#10100F; --surface:#171716; --surface-elevated:#20201E;
    --gold:var(--brand-primary); --gold-soft:var(--brand-soft);
    --border-gold: color-mix(in srgb, var(--gold) 18%, transparent);
    --border-soft: rgba(255,255,255,0.09);
    --text:#F7F3E8; --muted:#A8A29A; --success:#22C55E; --danger:#EF4444; --warning:#F59E0B;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    min-height: 100vh;
    background: radial-gradient(circle at 88% 6%, color-mix(in srgb, var(--gold) 22%, transparent), transparent 30vw), linear-gradient(135deg, var(--bg) 0%, var(--bg-soft) 55%, #090908 100%);
    color: var(--text);
    font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  }
  a { color: inherit; }
  .topbar { position: sticky; top: 0; z-index: 20; background: rgba(16,16,15,0.84); border-bottom: 1px solid var(--border-gold); backdrop-filter: blur(16px); }
  .topbar-inner, .wrap { width: min(100%, 1080px); margin: 0 auto; padding-left: 32px; padding-right: 32px; }
  .topbar-inner { min-height: 78px; display: flex; align-items: center; justify-content: space-between; gap: 20px; }
  .brand-link { display: inline-flex; align-items: center; gap: 12px; text-decoration: none; }
  .brand-link img { width: 130px; height: auto; }
  .nav { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
  .nav a { color: var(--muted); display: inline-flex; align-items: center; gap: 8px; border-radius: 999px; font-size: 13px; font-weight: 700; padding: 10px 14px; text-decoration: none; }
  .nav a:hover { color: var(--text); background: rgba(255,255,255,0.04); }
  .wrap { padding-top: 28px; padding-bottom: 56px; }
  .eyebrow { display: inline-flex; color: var(--gold-soft); background: color-mix(in srgb, var(--gold) 12%, transparent); border: 1px solid var(--border-gold); border-radius: 999px; font-size: 12px; font-weight: 800; padding: 7px 11px; margin-bottom: 12px; }
  h1 { font-size: clamp(26px, 4vw, 36px); margin-bottom: 10px; }
  .subtitle { color: var(--muted); font-size: 14px; line-height: 1.65; max-width: 640px; margin-bottom: 24px; }
  .notice { border: 1px solid var(--border-soft); border-radius: 16px; font-size: 14px; line-height: 1.6; margin-bottom: 18px; padding: 14px 16px; }
  .notice.success { color: #A7F3D0; background: rgba(34,197,94,0.10); border-color: rgba(34,197,94,0.22); }
  .notice.error { color: #FECACA; background: rgba(239,68,68,0.10); border-color: rgba(239,68,68,0.24); }
  .panel { background: linear-gradient(180deg, rgba(255,255,255,0.045), rgba(255,255,255,0.02)); border: 1px solid var(--border-gold); border-radius: 22px; padding: 26px; margin-bottom: 18px; }
  .panel h2 { font-size: 18px; margin-bottom: 6px; }
  .panel p.desc { color: var(--muted); font-size: 13px; margin-bottom: 20px; }
  .field { margin-bottom: 18px; }
  .field label { display: block; color: var(--text); font-size: 13px; font-weight: 800; margin-bottom: 8px; }
  .field select, .field input[type="text"], .field input[type="password"] {
    width: 100%; min-height: 46px; color: var(--text); background: rgba(255,255,255,0.035);
    border: 1px solid rgba(255,255,255,0.12); border-radius: 12px; font: inherit; font-size: 13.5px; outline: none; padding: 0 14px;
  }
  .field select:focus, .field input:focus { border-color: color-mix(in srgb, var(--gold-soft) 42%, transparent); box-shadow: 0 0 0 4px color-mix(in srgb, var(--gold) 10%, transparent); }
  .helper { color: var(--muted); font-size: 12.5px; margin-top: 7px; line-height: 1.55; }
  .field-error { color: #FCA5A5; font-size: 12px; margin-top: 7px; }
  .current-key { display: inline-flex; align-items: center; gap: 8px; color: var(--gold-soft); background: color-mix(in srgb, var(--gold) 8%, transparent); border: 1px solid var(--border-gold); border-radius: 10px; font-size: 13px; font-family: monospace; padding: 8px 12px; margin-bottom: 10px; }
  .switch-row { display: flex; align-items: center; gap: 10px; margin-top: 6px; font-size: 13px; color: var(--muted); }
  .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 46px; border: 1px solid transparent; border-radius: 14px; cursor: pointer; font: inherit; font-size: 13.5px; font-weight: 900; padding: 12px 18px; text-decoration: none; }
  .btn-gold { color: #111; background: linear-gradient(135deg, var(--gold), var(--gold-soft)); }
  .btn-outline { color: var(--text); background: rgba(255,255,255,0.035); border-color: color-mix(in srgb, var(--gold) 22%, transparent); }
  .info-box { display: flex; gap: 12px; align-items: flex-start; color: var(--gold-soft); background: color-mix(in srgb, var(--gold) 8%, transparent); border: 1px solid var(--border-gold); border-radius: 14px; font-size: 13px; line-height: 1.6; padding: 14px; }
</style>
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand-link" href="dashboard.php"><img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($brand['name']) ?>"></a>
    <nav class="nav">
      <a href="dashboard.php">Dashboard</a>
      <a href="events.php">Kelola Event</a>
      <a href="admin-users.php">Kelola Admin</a>
      <a href="logout.php">Keluar</a>
    </nav>
  </div>
</header>

<main class="wrap">
  <span class="eyebrow">Pengaturan Global — Superadmin</span>
  <h1>Pengaturan Provider AI</h1>
  <p class="subtitle">Atur provider, API key, dan model AI yang dipakai untuk fitur generate konten marketing dan generate landing page dengan AI di seluruh brand.</p>

  <?php if ($notice): ?>
    <div class="notice <?= htmlspecialchars($noticeType) ?>"><?= htmlspecialchars($notice) ?></div>
  <?php endif; ?>

  <form method="POST" class="panel">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <h2>Konfigurasi Provider</h2>
    <p class="desc">Perubahan berlaku langsung untuk semua brand — tidak perlu ubah config.php atau deploy ulang.</p>

    <div class="field">
      <label for="provider">Provider AI Aktif</label>
      <select name="provider" id="provider">
        <?php foreach ($knownProviders as $key => $meta): ?>
          <option value="<?= htmlspecialchars($key) ?>" <?= $formValues['provider'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($meta['label']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($fieldErrors['provider'])): ?><div class="field-error"><?= htmlspecialchars($fieldErrors['provider']) ?></div><?php endif; ?>
    </div>

    <div class="field">
      <label for="model">Model</label>
      <input type="text" name="model" id="model" value="<?= htmlspecialchars($formValues['model']) ?>" placeholder="Contoh: gpt-4o-mini">
      <div class="helper">Kosongkan untuk pakai model default provider terpilih.</div>
    </div>

    <div class="field">
      <label for="api_key">API Key</label>
      <?php if ($maskedKey !== ''): ?>
        <div class="current-key">🔑 <?= htmlspecialchars($maskedKey) ?></div>
      <?php endif; ?>
      <input type="password" name="api_key" id="api_key" placeholder="<?= $maskedKey !== '' ? 'Kosongkan untuk tetap pakai key saat ini' : 'sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx' ?>" autocomplete="off">
      <div class="helper">Key disimpan di database, bukan di file kode. Kosongkan field ini jika tidak ingin mengganti key yang sudah tersimpan.</div>
      <?php if ($maskedKey !== ''): ?>
      <label class="switch-row">
        <input type="checkbox" name="clear_api_key" value="1">
        Hapus API key yang tersimpan (kosongkan)
      </label>
      <?php endif; ?>
    </div>

    <div class="info-box">
      <span aria-hidden="true">ⓘ</span>
      <span>Untuk SumoPod AI, dapatkan API key dari dashboard SumoPod (format <code>sk-...</code>). Base URL <code>https://ai.sumopod.com/v1</code> sudah otomatis dipakai sistem, tidak perlu diisi manual.</span>
    </div>

    <div style="margin-top:20px; display:flex; gap:12px; flex-wrap:wrap;">
      <button type="submit" class="btn btn-gold">Simpan Pengaturan</button>
      <a class="btn btn-outline" href="dashboard.php">Kembali ke Dashboard</a>
    </div>
  </form>
</main>
</body>
</html>
