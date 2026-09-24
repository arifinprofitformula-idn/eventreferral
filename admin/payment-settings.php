<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_nav.php';
require_once __DIR__ . '/../includes/lms_core.php';
start_secure_session();

$brand = require_admin_for_brand(get_current_brand());
$brandId = (int)$brand['id'];
$pdo = get_db();
lms_ensure_schema($pdo);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function ps_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$notice = null;
$noticeType = 'success';
$fieldErrors = [];
$settings = lms_get_payment_settings($pdo, $brand);
$bankCatalog = lms_bank_catalog();

if (isset($_GET['saved'])) {
    $notice = 'Pengaturan pembayaran berhasil disimpan.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $notice = 'Sesi tidak valid. Silakan refresh halaman lalu coba lagi.';
        $noticeType = 'error';
    } else {
        $settings['active_method'] = in_array($_POST['active_method'] ?? '', ['bank_transfer', 'midtrans'], true) ? $_POST['active_method'] : 'bank_transfer';
        $settings['bank_transfer_enabled'] = isset($_POST['bank_transfer_enabled']) ? 1 : 0;
        $settings['bank_code'] = array_key_exists($_POST['bank_code'] ?? '', $bankCatalog) ? $_POST['bank_code'] : '';
        $settings['bank_name'] = $settings['bank_code'] !== '' ? $bankCatalog[$settings['bank_code']] : '';
        $settings['bank_account_number'] = trim(clean($_POST['bank_account_number'] ?? ''));
        $settings['bank_account_name'] = trim(clean($_POST['bank_account_name'] ?? ''));
        $settings['bank_instructions'] = trim((string)($_POST['bank_instructions'] ?? ''));
        $settings['admin_whatsapp'] = trim(clean($_POST['admin_whatsapp'] ?? ''));
        $settings['midtrans_enabled'] = isset($_POST['midtrans_enabled']) ? 1 : 0;
        $settings['midtrans_environment'] = ($_POST['midtrans_environment'] ?? 'sandbox') === 'production' ? 'production' : 'sandbox';
        $settings['midtrans_merchant_id'] = trim(clean($_POST['midtrans_merchant_id'] ?? ''));

        try {
            if (!empty($_POST['remove_bank_logo'])) {
                $oldLogo = (string)($settings['bank_logo_path'] ?? '');
                if ($oldLogo !== '' && str_starts_with($oldLogo, '/uploads/lms/banks/')) {
                    $oldFile = dirname(__DIR__) . $oldLogo;
                    if (is_file($oldFile)) @unlink($oldFile);
                }
                $settings['bank_logo_path'] = '';
            } else {
                $settings['bank_logo_path'] = lms_store_bank_logo($_FILES['bank_logo'] ?? [], $settings['bank_logo_path'] ?? null);
            }
        } catch (Throwable $e) {
            $fieldErrors['bank_logo'] = $e->getMessage();
        }

        $newServerKey = trim((string)($_POST['midtrans_server_key'] ?? ''));
        $newClientKey = trim((string)($_POST['midtrans_client_key'] ?? ''));
        if ($newServerKey !== '') $settings['midtrans_server_key'] = $newServerKey;
        if ($newClientKey !== '') $settings['midtrans_client_key'] = $newClientKey;
        if (!empty($_POST['clear_midtrans_keys'])) {
            $settings['midtrans_server_key'] = '';
            $settings['midtrans_client_key'] = '';
        }

        if (!$settings['bank_transfer_enabled'] && !$settings['midtrans_enabled']) {
            $fieldErrors['method'] = 'Aktifkan minimal satu metode pembayaran.';
        }
        if ($settings['active_method'] === 'bank_transfer' && !$settings['bank_transfer_enabled']) {
            $fieldErrors['active_method'] = 'Metode aktif tidak boleh Transfer Bank jika Transfer Bank nonaktif.';
        }
        if ($settings['active_method'] === 'midtrans' && !$settings['midtrans_enabled']) {
            $fieldErrors['active_method'] = 'Metode aktif tidak boleh Midtrans jika Midtrans nonaktif.';
        }
        if ($settings['bank_transfer_enabled'] && ($settings['bank_name'] === '' || $settings['bank_account_number'] === '' || $settings['bank_account_name'] === '')) {
            $fieldErrors['bank'] = 'Data bank wajib lengkap jika Transfer via Bank aktif.';
        }
        if ($settings['bank_transfer_enabled'] && $settings['admin_whatsapp'] === '') {
            $fieldErrors['admin_whatsapp'] = 'Nomor WhatsApp admin wajib diisi agar user bisa konfirmasi pembayaran.';
        }
        if ($settings['midtrans_enabled'] && ($settings['midtrans_server_key'] === '' || $settings['midtrans_client_key'] === '')) {
            $fieldErrors['midtrans'] = 'Server Key dan Client Key Midtrans wajib diisi jika Midtrans aktif.';
        }

        if ($fieldErrors) {
            $notice = 'Pengaturan belum dapat disimpan. Periksa data yang ditandai.';
            $noticeType = 'error';
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO lms_payment_settings
                    (brand_id, active_method, bank_transfer_enabled, bank_code, bank_name, bank_logo_path, bank_account_number, bank_account_name, bank_instructions, admin_whatsapp, midtrans_enabled, midtrans_environment, midtrans_server_key, midtrans_client_key, midtrans_merchant_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    active_method = VALUES(active_method),
                    bank_transfer_enabled = VALUES(bank_transfer_enabled),
                    bank_code = VALUES(bank_code),
                    bank_name = VALUES(bank_name),
                    bank_logo_path = VALUES(bank_logo_path),
                    bank_account_number = VALUES(bank_account_number),
                    bank_account_name = VALUES(bank_account_name),
                    bank_instructions = VALUES(bank_instructions),
                    admin_whatsapp = VALUES(admin_whatsapp),
                    midtrans_enabled = VALUES(midtrans_enabled),
                    midtrans_environment = VALUES(midtrans_environment),
                    midtrans_server_key = VALUES(midtrans_server_key),
                    midtrans_client_key = VALUES(midtrans_client_key),
                    midtrans_merchant_id = VALUES(midtrans_merchant_id)
            ');
            $stmt->execute([
                $brandId,
                $settings['active_method'],
                (int)$settings['bank_transfer_enabled'],
                $settings['bank_code'],
                $settings['bank_name'],
                $settings['bank_logo_path'],
                $settings['bank_account_number'],
                $settings['bank_account_name'],
                $settings['bank_instructions'],
                $settings['admin_whatsapp'],
                (int)$settings['midtrans_enabled'],
                $settings['midtrans_environment'],
                $settings['midtrans_server_key'],
                $settings['midtrans_client_key'],
                $settings['midtrans_merchant_id'],
            ]);
            header('Location: payment-settings.php?saved=1');
            exit;
        }
    }
}

$brandName = $brand['name'] ?? $brand['slug'];
$logoPath = !empty($brand['logo_path']) ? '..' . $brand['logo_path'] : '../assets/logo.png';
$maskedServerKey = lms_mask_secret($settings['midtrans_server_key'] ?? '');
$maskedClientKey = lms_mask_secret($settings['midtrans_client_key'] ?? '');

$bankAccentPalette = ['#2563EB', '#DB2777', '#059669', '#D97706', '#7C3AED', '#0891B2', '#DC2626', '#4F46E5'];
$bankAccent = static function (string $code) use ($bankAccentPalette): string {
    $index = crc32($code) % count($bankAccentPalette);
    return $bankAccentPalette[$index];
};
$bankInitials = static function (string $name): string {
    $clean = preg_replace('/\(.*?\)/', '', $name);
    $words = array_values(array_filter(explode(' ', trim($clean))));
    $words = array_slice($words, 0, 2);
    $letters = array_map(fn($w) => mb_strtoupper(mb_substr($w, 0, 1)), $words);
    return implode('', $letters) ?: '?';
};
$selectedBankName = $settings['bank_name'] ?: '';
$selectedBankCode = $settings['bank_code'] ?: '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pengaturan Pembayaran - <?= ps_h($brandName) ?></title>
<style>
  <?= get_theme_css_vars($brand) ?>
  :root { --bg:#0B0B0A; --surface:#171716; --border-gold:rgba(214,165,54,0.18); --gold:var(--brand-primary); --gold-soft:var(--brand-soft); --text:#F7F3E8; --muted:#A8A29A; }
  * { box-sizing:border-box; }
  body { margin:0; min-height:100vh; color:var(--text); background:linear-gradient(135deg,var(--bg),#090908); font-family:Inter, system-ui, sans-serif; }
  .topbar { position:sticky; top:0; z-index:10; background:rgba(16,16,15,0.84); border-bottom:1px solid rgba(255,255,255,0.08); backdrop-filter:blur(16px); }
  .topbar-inner,.wrap { width:min(100%,1120px); margin:0 auto; padding-left:32px; padding-right:32px; }
  .topbar-inner { min-height:82px; display:flex; align-items:center; justify-content:space-between; gap:20px; }
  .brand img { width:132px; max-height:58px; object-fit:contain; }
  .wrap { padding-top:28px; padding-bottom:56px; }
  h1 { font-family:Georgia,serif; font-size:clamp(28px,4vw,42px); margin:0 0 8px; }
  h1 span { color:var(--gold-soft); }
  .subtitle,.muted { color:var(--muted); }
  .notice { margin:18px 0; border-radius:14px; padding:14px 16px; font-size:14px; }
  .notice.success { background:rgba(34,197,94,0.10); border:1px solid rgba(34,197,94,0.24); color:#bbf7d0; }
  .notice.error { background:rgba(239,68,68,0.10); border:1px solid rgba(239,68,68,0.24); color:#fecaca; }
  .grid { display:grid; grid-template-columns:1fr 1fr; gap:18px; align-items:start; margin-top:22px; }
  .panel { border:1px solid var(--border-gold); border-radius:22px; background:linear-gradient(145deg,rgba(32,32,30,0.94),rgba(23,23,22,0.94)); padding:24px; box-shadow:0 22px 70px rgba(0,0,0,0.28); }
  .panel.full { grid-column:1/-1; }
  .panel h2 { margin:0 0 6px; font-size:20px; }
  .panel p { margin:0 0 18px; color:var(--muted); font-size:13px; line-height:1.6; }
  .field { margin:14px 0; }
  label { display:block; font-size:13px; font-weight:800; margin-bottom:8px; }
  input[type=text], input[type=password], input[type=search], input[type=file], textarea, select { width:100%; min-height:46px; color:var(--text); background:#111110; border:1px solid rgba(255,255,255,0.11); border-radius:12px; padding:12px; font:inherit; }
  textarea { min-height:110px; resize:vertical; }
  .check { display:flex; gap:10px; align-items:center; color:var(--text); font-size:13px; margin:12px 0; }
  .hint { color:var(--muted); font-size:11.5px; line-height:1.5; margin-top:6px; }
  .error { color:#fca5a5; font-size:12px; margin-top:7px; }
  .btn { display:inline-flex; align-items:center; justify-content:center; min-height:46px; border-radius:14px; border:1px solid transparent; cursor:pointer; font:inherit; font-weight:850; padding:12px 18px; text-decoration:none; }
  .btn-primary { color:#111; background:linear-gradient(135deg,var(--gold),var(--gold-soft)); }
  .btn-secondary { color:var(--text); background:rgba(255,255,255,0.04); border-color:rgba(255,255,255,0.12); }
  .actions { display:flex; gap:12px; flex-wrap:wrap; margin-top:20px; }
  code { color:var(--gold-soft); }

  /* ==== Bank Picker: compact searchable dropdown ==== */
  .bank-picker { position:relative; z-index:6; }
  .bank-search-shell { position:relative; }
  .bank-search-shell svg { position:absolute; left:14px; top:50%; transform:translateY(-50%); width:18px; height:18px; color:var(--muted); pointer-events:none; }
  .bank-search-shell input[type=search] { padding-left:42px; font-size:14.5px; font-weight:600; border-color:rgba(255,255,255,0.14); }
  .bank-search-shell input[type=search]:focus { border-color:var(--gold); box-shadow:0 0 0 4px color-mix(in srgb, var(--gold) 18%, transparent); outline:none; }
  .bank-selected-chip { display:none; align-items:center; gap:10px; margin-top:10px; padding:9px 12px; border-radius:999px; width:max-content; max-width:100%; background:linear-gradient(135deg, color-mix(in srgb, var(--gold) 16%, transparent), rgba(255,255,255,0.03)); border:1px solid var(--border-gold); }
  .bank-selected-chip.show { display:flex; }
  .bank-selected-chip .bank-avatar { width:28px; height:28px; font-size:11px; }
  .bank-selected-chip strong { font-size:13px; color:var(--text); }
  .bank-selected-chip span { display:none; }
  .bank-options {
    display:none;
    position:absolute; left:0; right:0; top:76px; z-index:40;
    max-height:260px; overflow-y:auto;
    margin-top:6px; padding:8px;
    border:1px solid rgba(255,255,255,0.14); border-radius:14px;
    background:rgba(13,13,12,0.98);
    box-shadow:0 18px 50px rgba(0,0,0,0.55);
    backdrop-filter:blur(14px);
  }
  .bank-options.open { display:block; }
  .bank-option {
    display:flex; align-items:center; gap:10px;
    width:100%; text-align:left; cursor:pointer;
    color:var(--text); background:transparent;
    border:0; border-radius:10px;
    padding:10px 12px; font:inherit; font-size:13px; font-weight:700;
    transition:background 120ms ease, color 120ms ease;
  }
  .bank-option:hover, .bank-option.active { background:color-mix(in srgb, var(--gold) 14%, rgba(255,255,255,0.04)); color:var(--gold-soft); }
  .bank-option.hidden-item { display:none; }
  .bank-option-name { line-height:1.25; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .bank-avatar {
    flex:0 0 auto; width:26px; height:26px; border-radius:8px;
    display:flex; align-items:center; justify-content:center;
    font-weight:900; font-size:10.5px; color:#fff; letter-spacing:.02em;
    box-shadow:inset 0 0 0 1px rgba(255,255,255,0.18);
  }
  .bank-empty-state { text-align:center; color:var(--muted); font-size:12.5px; padding:14px 6px; }

  .bank-logo-preview {
    display:flex; align-items:center; gap:16px;
    margin:12px 0 4px; padding:16px;
    border:1px solid var(--border-gold); border-radius:16px;
    background:linear-gradient(135deg, rgba(255,255,255,0.06), rgba(255,255,255,0.015));
  }
  .bank-logo-preview img { width:120px; height:64px; padding:10px; object-fit:contain; background:#fff; border-radius:12px; box-shadow:0 8px 20px rgba(0,0,0,0.3); }
  .bank-logo-preview .bank-logo-meta { display:flex; flex-direction:column; gap:8px; }
  .bank-logo-preview .bank-logo-meta strong { font-size:13px; color:var(--text); }

  .upload-dropzone {
    position:relative; display:flex; align-items:center; gap:14px;
    border:1.5px dashed rgba(255,255,255,0.18); border-radius:16px;
    padding:18px; background:rgba(255,255,255,0.02);
    transition:border-color 160ms ease, background 160ms ease;
  }
  .upload-dropzone:hover { border-color:var(--gold); background:color-mix(in srgb, var(--gold) 6%, transparent); }
  .upload-dropzone .upload-icon {
    flex:0 0 auto; width:44px; height:44px; border-radius:12px;
    display:flex; align-items:center; justify-content:center;
    background:linear-gradient(135deg, var(--gold), var(--gold-soft)); color:#111;
  }
  .upload-dropzone .upload-icon svg { width:22px; height:22px; }
  .upload-dropzone-text strong { display:block; font-size:13.5px; color:var(--text); }
  .upload-dropzone-text span { display:block; font-size:11.5px; color:var(--muted); margin-top:2px; }
  .upload-dropzone input[type=file] { position:absolute; inset:0; opacity:0; cursor:pointer; min-height:unset; }

  .method-toggle { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:6px; }
  .method-card {
    position:relative; cursor:pointer; border-radius:16px; padding:16px;
    border:1.5px solid rgba(255,255,255,0.10); background:rgba(255,255,255,0.02);
    transition:border-color 140ms ease, box-shadow 140ms ease, background 140ms ease;
  }
  .method-card input { position:absolute; opacity:0; pointer-events:none; }
  .method-card .method-icon { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; margin-bottom:10px; background:rgba(255,255,255,0.06); color:var(--gold-soft); }
  .method-card strong { display:block; font-size:14px; color:var(--text); }
  .method-card span { display:block; font-size:11.5px; color:var(--muted); margin-top:4px; }
  .method-card.checked { border-color:var(--gold); background:color-mix(in srgb, var(--gold) 10%, transparent); box-shadow:0 0 0 3px color-mix(in srgb, var(--gold) 16%, transparent); }
  .method-card.checked .method-icon { background:linear-gradient(135deg, var(--gold), var(--gold-soft)); color:#111; }

  .status-chip { display:inline-flex; align-items:center; gap:6px; font-size:10.5px; font-weight:900; text-transform:uppercase; letter-spacing:.05em; padding:4px 10px; border-radius:999px; }
  .status-chip.on { color:#bbf7d0; background:rgba(34,197,94,0.14); border:1px solid rgba(34,197,94,0.3); }
  .status-chip.off { color:#fca5a5; background:rgba(239,68,68,0.12); border:1px solid rgba(239,68,68,0.28); }
  .panel-head-row { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:6px; }

  @media (max-width: 860px) { .grid { grid-template-columns:1fr; } .topbar-inner,.wrap { padding-left:16px; padding-right:16px; } .bank-options { grid-template-columns:1fr; } .method-toggle { grid-template-columns:1fr; } }
</style>
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="dashboard.php"><img src="<?= ps_h($logoPath) ?>" alt="<?= ps_h($brandName) ?>"></a>
    <?php render_admin_nav('payment-settings'); ?>
  </div>
</header>

<main class="wrap">
  <h1>Pengaturan <span>Pembayaran</span></h1>
  <p class="subtitle">Pilih metode checkout eCourse: Midtrans Snap atau Transfer via Bank. Midtrans masih mode persiapan, belum membuat transaksi riil.</p>

  <?php if ($notice): ?><div class="notice <?= ps_h($noticeType) ?>"><?= ps_h($notice) ?></div><?php endif; ?>
  <?php foreach ($fieldErrors as $error): ?><div class="notice error"><?= ps_h($error) ?></div><?php endforeach; ?>

  <form method="POST" enctype="multipart/form-data" class="grid">
    <input type="hidden" name="csrf_token" value="<?= ps_h($_SESSION['csrf_token']) ?>">

    <section class="panel full">
      <h2>Metode Aktif</h2>
      <p>Metode aktif menjadi pilihan default di halaman checkout.</p>
      <div class="field">
        <label for="active_method">Metode Default</label>
        <select id="active_method" name="active_method">
          <option value="bank_transfer" <?= $settings['active_method'] === 'bank_transfer' ? 'selected' : '' ?>>Transfer via Bank</option>
          <option value="midtrans" <?= $settings['active_method'] === 'midtrans' ? 'selected' : '' ?>>Midtrans Snap</option>
        </select>
      </div>
    </section>

    <section class="panel">
      <div class="panel-head-row">
        <h2>🏦 Transfer via Bank</h2>
        <span class="status-chip <?= (int)$settings['bank_transfer_enabled'] === 1 ? 'on' : 'off' ?>"><?= (int)$settings['bank_transfer_enabled'] === 1 ? 'Aktif' : 'Nonaktif' ?></span>
      </div>
      <p>Metode ini aktif sekarang untuk membuat order pending tanpa payment gateway.</p>
      <label class="check"><input type="checkbox" name="bank_transfer_enabled" value="1" <?= (int)$settings['bank_transfer_enabled'] === 1 ? 'checked' : '' ?>> Aktifkan Transfer via Bank</label>

      <div class="field bank-picker">
        <label for="bank-search">Pilih Bank</label>
        <div class="bank-search-shell">
          <svg viewBox="0 0 24 24" fill="none"><path d="m21 21-4.3-4.3M19 11a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <input type="search" id="bank-search" value="<?= ps_h($selectedBankName) ?>" placeholder="Cari BCA, BRI, Mandiri, BNI..." autocomplete="off">
        </div>
        <input type="hidden" id="bank-code" name="bank_code" value="<?= ps_h($selectedBankCode) ?>">

        <div class="bank-selected-chip <?= $selectedBankCode !== '' ? 'show' : '' ?>" id="bank-selected-chip">
          <div class="bank-avatar" id="bank-selected-avatar" style="background:<?= ps_h($selectedBankCode !== '' ? $bankAccent($selectedBankCode) : '#333') ?>;"><?= ps_h($selectedBankCode !== '' ? $bankInitials($selectedBankName) : '') ?></div>
          <div><strong id="bank-selected-name"><?= ps_h($selectedBankName) ?></strong><br><span>Bank Terpilih</span></div>
        </div>

        <div class="bank-options" id="bank-options">
          <?php foreach ($bankCatalog as $code => $name): ?>
            <button type="button" class="bank-option <?= $selectedBankCode === $code ? 'active' : '' ?>" data-code="<?= ps_h($code) ?>" data-name="<?= ps_h($name) ?>">
              <span class="bank-avatar" style="background:<?= ps_h($bankAccent($code)) ?>;"><?= ps_h($bankInitials($name)) ?></span>
              <span class="bank-option-name"><?= ps_h($name) ?></span>
            </button>
          <?php endforeach; ?>
          <div class="bank-empty-state" id="bank-empty-state" style="display:none;">Bank tidak ditemukan.</div>
        </div>
        <div class="hint">Ketik nama bank untuk memfilter, lalu klik kartu bank untuk memilih.</div>
      </div>

      <div class="field">
        <label>Logo Bank</label>
        <?php if (!empty($settings['bank_logo_path'])): ?>
          <div class="bank-logo-preview">
            <img src="<?= ps_h($settings['bank_logo_path']) ?>" alt="Logo <?= ps_h($settings['bank_name']) ?>">
            <div class="bank-logo-meta">
              <strong>Logo tersimpan</strong>
              <label class="check" style="margin:0;"><input type="checkbox" name="remove_bank_logo" value="1"> Hapus logo ini</label>
            </div>
          </div>
        <?php endif; ?>
        <label class="upload-dropzone" for="bank-logo">
          <span class="upload-icon"><svg viewBox="0 0 24 24" fill="none"><path d="M12 16V4m0 0L7 9m5-5 5 5M5 20h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
          <span class="upload-dropzone-text">
            <strong id="upload-filename">Klik atau tarik file logo ke sini</strong>
            <span>PNG, JPG, atau WEBP • Maksimal 2 MB • Tampil di tujuan transfer checkout</span>
          </span>
          <input type="file" id="bank-logo" name="bank_logo" accept="image/png,image/jpeg,image/webp">
        </label>
      </div>

      <div class="field"><label>No. Rekening</label><input type="text" name="bank_account_number" value="<?= ps_h($settings['bank_account_number']) ?>" placeholder="Contoh: 1234567890"></div>
      <div class="field"><label>Atas Nama</label><input type="text" name="bank_account_name" value="<?= ps_h($settings['bank_account_name']) ?>" placeholder="Contoh: PT Rahasia Emas Indonesia"></div>
      <div class="field"><label>WhatsApp Admin Konfirmasi</label><input type="text" name="admin_whatsapp" value="<?= ps_h($settings['admin_whatsapp'] ?? '') ?>" placeholder="Contoh: 081234567890"><div class="hint">Nomor ini tampil sebagai tombol konfirmasi setelah user upload bukti transfer.</div></div>
      <div class="field"><label>Instruksi Transfer</label><textarea name="bank_instructions" placeholder="Instruksi untuk user setelah order dibuat."><?= ps_h($settings['bank_instructions']) ?></textarea></div>
    </section>

    <section class="panel">
      <div class="panel-head-row">
        <h2>💳 Midtrans Snap</h2>
        <span class="status-chip <?= (int)$settings['midtrans_enabled'] === 1 ? 'on' : 'off' ?>"><?= (int)$settings['midtrans_enabled'] === 1 ? 'Aktif' : 'Nonaktif' ?></span>
      </div>
      <p>Key disimpan untuk persiapan. Integrasi create token dan webhook belum diaktifkan.</p>
      <label class="check"><input type="checkbox" name="midtrans_enabled" value="1" <?= (int)$settings['midtrans_enabled'] === 1 ? 'checked' : '' ?>> Tampilkan opsi Midtrans di checkout</label>

      <label class="hint" style="display:block;margin-top:14px;">Environment</label>
      <div class="method-toggle">
        <label class="method-card <?= $settings['midtrans_environment'] === 'sandbox' ? 'checked' : '' ?>">
          <input type="radio" name="midtrans_environment" value="sandbox" <?= $settings['midtrans_environment'] === 'sandbox' ? 'checked' : '' ?>>
          <span class="method-icon"><svg viewBox="0 0 24 24" fill="none" width="18" height="18"><path d="M12 2v4m0 12v4m10-10h-4M6 12H2m15.5-6.5-2.8 2.8M8.3 15.7l-2.8 2.8m0-13 2.8 2.8m8.4 8.4 2.8 2.8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
          <strong>Sandbox</strong>
          <span>Testing, tanpa uang riil</span>
        </label>
        <label class="method-card <?= $settings['midtrans_environment'] === 'production' ? 'checked' : '' ?>">
          <input type="radio" name="midtrans_environment" value="production" <?= $settings['midtrans_environment'] === 'production' ? 'checked' : '' ?>>
          <span class="method-icon"><svg viewBox="0 0 24 24" fill="none" width="18" height="18"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
          <strong>Production</strong>
          <span>Live, transaksi asli</span>
        </label>
      </div>

      <div class="field" style="margin-top:18px;"><label>Merchant ID</label><input type="text" name="midtrans_merchant_id" value="<?= ps_h($settings['midtrans_merchant_id']) ?>" placeholder="Opsional"></div>
      <div class="field"><label>Server Key</label><input type="password" name="midtrans_server_key" value="" placeholder="<?= ps_h($maskedServerKey ?: 'Masukkan Server Key') ?>" autocomplete="new-password"><div class="hint">Kosongkan untuk mempertahankan key tersimpan.</div></div>
      <div class="field"><label>Client Key</label><input type="password" name="midtrans_client_key" value="" placeholder="<?= ps_h($maskedClientKey ?: 'Masukkan Client Key') ?>" autocomplete="new-password"><div class="hint">Kosongkan untuk mempertahankan key tersimpan.</div></div>
      <?php if ($maskedServerKey || $maskedClientKey): ?><label class="check"><input type="checkbox" name="clear_midtrans_keys" value="1"> Hapus key Midtrans tersimpan</label><?php endif; ?>
      <div class="hint">Webhook target nanti: <code>/api/lms-checkout-webhook.php</code>. File itu belum dibuat karena integrasi riil belum dihubungkan.</div>
    </section>

    <section class="panel full">
      <div class="actions">
        <button type="submit" class="btn btn-primary">Simpan Pengaturan Pembayaran</button>
        <a class="btn btn-secondary" href="lms-orders.php">Lihat Order LMS</a>
      </div>
    </section>
  </form>
</main>
<script>
(function () {
  const search = document.getElementById('bank-search');
  const code = document.getElementById('bank-code');
  const options = document.getElementById('bank-options');
  const picker = document.querySelector('.bank-picker');
  const items = Array.from(document.querySelectorAll('.bank-option'));
  const empty = document.getElementById('bank-empty-state');
  const chip = document.getElementById('bank-selected-chip');
  const chipName = document.getElementById('bank-selected-name');
  const chipAvatar = document.getElementById('bank-selected-avatar');
  const logoInput = document.getElementById('bank-logo');
  const uploadFilename = document.getElementById('upload-filename');

  function initials(name) {
    return name.replace(/\(.*?\)/g, '').trim().split(/\s+/).slice(0, 2).map(word => word.charAt(0).toUpperCase()).join('') || '?';
  }

  function accent(value) {
    const palette = ['#2563EB', '#DB2777', '#059669', '#D97706', '#7C3AED', '#0891B2', '#DC2626', '#4F46E5'];
    let hash = 0;
    for (let i = 0; i < value.length; i++) hash = ((hash << 5) - hash) + value.charCodeAt(i);
    return palette[Math.abs(hash) % palette.length];
  }

  function filterBanks() {
    if (!search) return;
    const query = search.value.trim().toLowerCase();
    let visible = 0;
    items.forEach(item => {
      const match = item.dataset.name.toLowerCase().includes(query);
      item.classList.toggle('hidden-item', !match);
      if (match) visible++;
    });
    if (empty) empty.style.display = visible ? 'none' : 'block';
  }

  if (search && code && options) {
    search.addEventListener('focus', function () {
      options.classList.add('open');
      filterBanks();
    });
    search.addEventListener('input', function () {
      code.value = '';
      options.classList.add('open');
      filterBanks();
      if (chip) chip.classList.remove('show');
      items.forEach(item => item.classList.remove('active'));
    });
    items.forEach(item => item.addEventListener('click', function () {
      const selectedName = item.dataset.name;
      const selectedCode = item.dataset.code;
      search.value = selectedName;
      code.value = selectedCode;
      items.forEach(candidate => candidate.classList.toggle('active', candidate === item));
      if (chip && chipName && chipAvatar) {
        chipName.textContent = selectedName;
        chipAvatar.textContent = initials(selectedName);
        chipAvatar.style.background = accent(selectedCode);
        chip.classList.add('show');
      }
      options.classList.remove('open');
    }));
    document.addEventListener('click', function (event) {
      if (picker && !picker.contains(event.target)) options.classList.remove('open');
    });
    filterBanks();
  }

  if (logoInput && uploadFilename) {
    logoInput.addEventListener('change', function () {
      uploadFilename.textContent = logoInput.files && logoInput.files[0]
        ? logoInput.files[0].name
        : 'Klik atau tarik file logo ke sini';
    });
  }

  document.querySelectorAll('.method-card input[type=radio]').forEach(input => {
    input.addEventListener('change', function () {
      document.querySelectorAll('.method-card').forEach(card => card.classList.remove('checked'));
      input.closest('.method-card')?.classList.add('checked');
    });
  });
})();
</script>
</body>
</html>
