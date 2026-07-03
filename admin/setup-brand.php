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
<title>Setup Brand Baru</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    background: #1A1A1A; color: #FAFAFA; font-family: Arial, sans-serif;
    min-height: 100vh; padding: 32px 16px;
  }
  .box { max-width: 640px; margin: 0 auto; background: #242424; border-radius: 14px; padding: 28px; }
  h1 { color: #C9A84C; font-size: 22px; margin-bottom: 20px; }
  .field { margin-bottom: 16px; }
  label { display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: #E8D5A3; }
  input[type="text"], input[type="password"], input[type="file"], textarea, select {
    width: 100%; background: #1A1A1A; border: 1px solid rgba(255,255,255,0.15); border-radius: 8px;
    padding: 12px; color: #FAFAFA; font-size: 14px; outline: none;
  }
  input:focus, textarea:focus, select:focus { border-color: #C9A84C; }
  textarea { resize: vertical; min-height: 70px; }
  .helper { font-size: 12px; color: #A8A29A; margin-top: 5px; }
  .color-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
  button {
    background: #C9A84C; color: #1A1A1A; font-weight: 700; font-size: 15px;
    padding: 13px 20px; border: none; border-radius: 10px; cursor: pointer;
  }
  .errors { background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.3); border-radius: 8px; padding: 14px 16px; margin-bottom: 18px; }
  .errors li { color: #FECACA; font-size: 13.5px; margin-left: 18px; }
  .success { background: rgba(34,197,94,0.12); border: 1px solid rgba(34,197,94,0.3); border-radius: 8px; padding: 16px 18px; margin-bottom: 18px; }
  .success p { color: #A7F3D0; font-size: 13.5px; line-height: 1.7; }
  .success code { background: rgba(0,0,0,0.3); padding: 2px 6px; border-radius: 4px; }
  #customColors { display: none; }
</style>
</head>
<body>
<div class="box">
  <h1>Setup Brand Baru</h1>

  <?php if (!empty($errors)): ?>
    <div class="errors"><ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="success">
      <p>
        Brand <code><?= htmlspecialchars($success['slug']) ?></code> berhasil dibuat.<br>
        Domain: <code><?= htmlspecialchars($success['domain']) ?></code><br>
        Username admin: <code><?= htmlspecialchars($success['admin_username']) ?></code><br><br>
        Langkah selanjutnya (production): arahkan Addon Domain <code><?= htmlspecialchars($success['domain']) ?></code>
        ke folder public_html yang sama, lalu aktifkan SSL. Untuk tes lokal, buka
        <code>?__brand=<?= htmlspecialchars($success['slug']) ?></code> dari localhost.
      </p>
    </div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="key" value="<?= htmlspecialchars($providedKey) ?>">
    <input type="hidden" name="create_brand" value="1">

    <div class="field">
      <label for="slug">Slug Brand</label>
      <input type="text" id="slug" name="slug" placeholder="rahasiaperak" value="<?= htmlspecialchars($formValues['slug']) ?>" required>
      <p class="helper">Huruf kecil, angka, strip. Dipakai untuk folder logo & override ?__brand= saat testing.</p>
    </div>

    <div class="field">
      <label for="domain">Domain</label>
      <input type="text" id="domain" name="domain" placeholder="rahasiaperak.id" value="<?= htmlspecialchars($formValues['domain']) ?>" required>
      <p class="helper">Tanpa http:// atau www. — harus sudah/akan diarahkan lewat Addon Domain di cPanel.</p>
    </div>

    <div class="field">
      <label for="name">Nama Brand</label>
      <input type="text" id="name" name="name" placeholder="Rahasia Perak" value="<?= htmlspecialchars($formValues['name']) ?>" required>
    </div>

    <div class="field">
      <label for="tagline">Tagline (opsional)</label>
      <input type="text" id="tagline" name="tagline" value="<?= htmlspecialchars($formValues['tagline']) ?>">
    </div>

    <div class="field">
      <label for="logo">Logo (opsional)</label>
      <input type="file" id="logo" name="logo" accept=".png,.jpg,.jpeg,.webp,.svg">
      <p class="helper">PNG/JPG/JPEG/WEBP/SVG, maksimal <?= (int)(MAX_LOGO_SIZE / 1024 / 1024) ?> MB.</p>
    </div>

    <div class="field">
      <label for="whatsapp_default">WhatsApp Default (opsional)</label>
      <input type="text" id="whatsapp_default" name="whatsapp_default" placeholder="628111111111" value="<?= htmlspecialchars($formValues['whatsapp_default']) ?>">
    </div>

    <div class="field">
      <label for="disclaimer_text">Disclaimer (opsional)</label>
      <textarea id="disclaimer_text" name="disclaimer_text"><?= htmlspecialchars($formValues['disclaimer_text']) ?></textarea>
    </div>

    <div class="field">
      <label for="theme_preset">Preset Tema</label>
      <select id="theme_preset" name="theme_preset">
        <?php foreach (['gold' => 'Gold', 'silver' => 'Silver', 'bronze' => 'Bronze', 'custom' => 'Custom'] as $val => $label): ?>
          <option value="<?= $val ?>" <?= $formValues['theme_preset'] === $val ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
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

    <div class="field">
      <label for="admin_username">Username Admin Brand Ini</label>
      <input type="text" id="admin_username" name="admin_username" value="<?= htmlspecialchars($formValues['admin_username']) ?>" required>
    </div>

    <div class="field">
      <label for="admin_password">Password Admin</label>
      <input type="password" id="admin_password" name="admin_password" minlength="8" required>
    </div>

    <div class="field">
      <label for="admin_password_confirm">Konfirmasi Password Admin</label>
      <input type="password" id="admin_password_confirm" name="admin_password_confirm" minlength="8" required>
    </div>

    <button type="submit">Buat Brand</button>
  </form>
</div>
<script>
  const presetSelect = document.getElementById('theme_preset');
  const customColors = document.getElementById('customColors');
  function toggleCustomColors() {
    customColors.style.display = presetSelect.value === 'custom' ? 'block' : 'none';
  }
  presetSelect.addEventListener('change', toggleCustomColors);
  toggleCustomColors();
</script>
</body>
</html>
