<?php
/**
 * referrer/set-password.php
 * Aktivasi akun dashboard pengundang. Verifikasi kepemilikan lewat
 * kombinasi whatsapp + ref_code (data yang hanya diketahui pengundang
 * itu sendiri, ditampilkan ke mereka setelah submit di /buat-link.php).
 * Password yang diset berlaku untuk SEMUA link (event) milik whatsapp
 * yang sama pada brand ini.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/referrer_auth.php';
start_secure_session();

$brand = require_brand_or_404(get_current_brand());

$error = null;
$prefillRefCode = clean($_GET['ref_code'] ?? '');
$prefillWhatsapp = clean($_GET['whatsapp'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $refCode = strtolower(clean($_POST['ref_code'] ?? ''));
    $whatsapp = normalize_whatsapp($_POST['whatsapp'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $passwordConfirm = (string)($_POST['password_confirm'] ?? '');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $prefillRefCode = $refCode;
    $prefillWhatsapp = $whatsapp;

    if ($refCode === '' || $whatsapp === '') {
        $error = 'Kode link dan nomor WhatsApp wajib diisi.';
    } elseif (strlen($password) < 8) {
        $error = 'Password minimal 8 karakter.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Konfirmasi password tidak sama.';
    } else {
        $pdo = get_db(false);
        if (!$pdo) {
            $error = 'Koneksi database gagal. Coba lagi nanti.';
        } else {
            $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL ? MINUTE)');
            $stmt->execute([LOGIN_LOCKOUT_MINUTES]);

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ?');
            $stmt->execute([$ip]);
            $attemptCount = (int)$stmt->fetchColumn();

            if ($attemptCount >= LOGIN_MAX_ATTEMPTS) {
                $error = 'Terlalu banyak percobaan. Coba lagi dalam beberapa menit.';
            } else {
                $stmt = $pdo->prepare('SELECT id FROM referrers WHERE brand_id = ? AND whatsapp = ? AND ref_code = ? LIMIT 1');
                $stmt->execute([(int)$brand['id'], $whatsapp, $refCode]);
                $owns = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$owns) {
                    $stmt = $pdo->prepare('INSERT INTO login_attempts (ip) VALUES (?)');
                    $stmt->execute([$ip]);
                    $error = 'Kombinasi kode link dan nomor WhatsApp tidak cocok dengan data pendaftaran.';
                } else {
                    $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE ip = ?');
                    $stmt->execute([$ip]);

                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare('UPDATE referrers SET password_hash = ?, status = "active" WHERE brand_id = ? AND whatsapp = ?');
                    $stmt->execute([$hash, (int)$brand['id'], $whatsapp]);

                    session_regenerate_id(true);
                    $_SESSION['referrer_brand_id'] = (int)$brand['id'];
                    $_SESSION['referrer_whatsapp'] = $whatsapp;
                    header('Location: /referrer/dashboard.php');
                    exit;
                }
            }
        }
    }
}

$refCodeLocked = $prefillRefCode !== '';
$logoPath = $brand['logo_path'] ? '..' . $brand['logo_path'] : '../assets/logo.png';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Aktifkan Dashboard Pengundang — <?= htmlspecialchars($brand['name']) ?></title>
<style>
  <?= get_theme_css_vars($brand) ?>
  :root {
    --bg: #0B0B0A; --bg-soft: #10100F; --surface: rgba(23,23,22,0.78);
    --border-gold: rgba(214,165,54,0.22); --gold: var(--brand-primary, #D6A536);
    --gold-soft: var(--brand-soft, #F4D27A); --text: #F7F3E8; --muted: #A8A29A; --danger: #EF4444;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    min-height: 100vh; color: var(--text); background: linear-gradient(135deg, var(--bg) 0%, var(--bg-soft) 48%, #070706 100%);
    font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    display: grid; place-items: center; padding: 24px;
  }
  .card {
    width: min(100%, 440px); padding: clamp(24px, 4vw, 34px);
    border: 1px solid var(--border-gold); border-radius: 24px;
    background: var(--surface); box-shadow: 0 28px 80px rgba(0,0,0,.46);
    backdrop-filter: blur(18px); display: grid; gap: 16px;
  }
  .card img { width: 96px; margin: 0 auto 4px; display: block; }
  h1 { font-family: Georgia, serif; font-size: 24px; text-align: center; }
  .subtitle { color: var(--muted); font-size: 13.5px; line-height: 1.6; text-align: center; }
  form { display: grid; gap: 12px; }
  label { font-size: 12.5px; color: var(--muted); font-weight: 600; }
  .field { display: grid; gap: 6px; }
  input {
    width: 100%; height: 48px; color: var(--text); background: rgba(255,255,255,0.045);
    border: 1px solid rgba(255,255,255,0.12); border-radius: 12px; padding: 0 14px;
    font: inherit; font-size: 14.5px; outline: none;
  }
  input:focus { border-color: color-mix(in srgb, var(--gold-soft) 62%, transparent); box-shadow: 0 0 0 4px color-mix(in srgb, var(--gold) 13%, transparent); }
  input[readonly] { color: var(--muted); background: rgba(255,255,255,0.015); border-style: dashed; cursor: not-allowed; }
  input[readonly]:focus { box-shadow: none; }
  .hint { color: var(--muted); font-size: 12px; line-height: 1.5; }
  .hint.locked { color: var(--gold-soft); }
  .alert-error {
    color: #FECACA; background: rgba(239,68,68,0.10); border: 1px solid rgba(239,68,68,0.28);
    border-radius: 12px; padding: 11px 12px; font-size: 13px; line-height: 1.45;
  }
  button {
    height: 50px; color: #111; background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    border: 0; border-radius: 14px; cursor: pointer; font-size: 15px; font-weight: 800;
  }
  .footer-note { text-align: center; font-size: 12.5px; color: var(--muted); }
  .footer-note a { color: var(--gold-soft); text-decoration: none; }
</style>
</head>
<body>
  <div class="card">
    <img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($brand['name']) ?>">
    <h1>Aktifkan Dashboard Pengundang</h1>
    <p class="subtitle">Buat password untuk memantau daftar peserta yang mendaftar lewat link referral kamu.</p>

    <?php if ($error): ?>
      <div class="alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="field">
        <label for="ref_code">Kode Link Kamu</label>
        <input id="ref_code" type="text" name="ref_code" placeholder="contoh: budiemas" required value="<?= htmlspecialchars($prefillRefCode) ?>" autocomplete="off" <?= $refCodeLocked ? 'readonly' : '' ?>>
        <?php if ($refCodeLocked): ?>
          <span class="hint locked">Kode ini otomatis terisi dari link yang baru kamu buat — tidak perlu diubah.</span>
        <?php else: ?>
          <span class="hint">Kode link yang kamu buat saat mendaftar di halaman Buat Link.</span>
        <?php endif; ?>
      </div>
      <div class="field">
        <label for="whatsapp">Nomor WhatsApp Kamu</label>
        <input id="whatsapp" type="tel" name="whatsapp" placeholder="08xxxxxxxxxx" required value="<?= htmlspecialchars($prefillWhatsapp) ?>" autocomplete="tel">
      </div>
      <div class="field">
        <label for="password">Password Baru</label>
        <input id="password" type="password" name="password" placeholder="Minimal 8 karakter" required minlength="8" autocomplete="new-password">
      </div>
      <div class="field">
        <label for="password_confirm">Ulangi Password</label>
        <input id="password_confirm" type="password" name="password_confirm" placeholder="Ulangi password" required minlength="8" autocomplete="new-password">
      </div>
      <button type="submit">Aktifkan Dashboard</button>
    </form>

    <p class="footer-note">Sudah punya password? <a href="/referrer/login.php">Masuk di sini</a></p>
  </div>
</body>
</html>
