<?php
/**
 * referrer/login.php — Login dashboard pengundang (nomor WhatsApp + password).
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/referrer_auth.php';
start_secure_session();

$brand = require_brand_or_404(get_current_brand());
$error = null;

if (!empty($_SESSION['referrer_whatsapp']) && (int)($_SESSION['referrer_brand_id'] ?? 0) === (int)$brand['id']) {
    header('Location: /referrer/dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $whatsapp = normalize_whatsapp($_POST['whatsapp'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

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
            $error = 'Terlalu banyak percobaan gagal. Coba lagi dalam beberapa menit.';
        } else {
            $stmt = $pdo->prepare('
                SELECT password_hash FROM referrers
                WHERE brand_id = ? AND whatsapp = ? AND password_hash IS NOT NULL AND status = "active"
                LIMIT 1
            ');
            $stmt->execute([(int)$brand['id'], $whatsapp]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row && password_verify($password, $row['password_hash'])) {
                $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE ip = ?');
                $stmt->execute([$ip]);

                $stmt = $pdo->prepare('UPDATE referrers SET last_login_at = NOW() WHERE brand_id = ? AND whatsapp = ?');
                $stmt->execute([(int)$brand['id'], $whatsapp]);

                session_regenerate_id(true);
                $_SESSION['referrer_brand_id'] = (int)$brand['id'];
                $_SESSION['referrer_whatsapp'] = $whatsapp;
                header('Location: /referrer/dashboard.php');
                exit;
            }

            $stmt = $pdo->prepare('INSERT INTO login_attempts (ip) VALUES (?)');
            $stmt->execute([$ip]);
            $error = 'Nomor WhatsApp atau password salah.';
        }
    }
}

$logoPath = $brand['logo_path'] ? '..' . $brand['logo_path'] : '../assets/logo.png';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Pengundang — <?= htmlspecialchars($brand['name']) ?></title>
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
    width: min(100%, 400px); padding: clamp(24px, 4vw, 34px);
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
    <h1>Dashboard Pengundang</h1>
    <p class="subtitle">Masuk untuk melihat daftar peserta yang mendaftar lewat link referral kamu.</p>

    <?php if ($error): ?>
      <div class="alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="field">
        <label for="whatsapp">Nomor WhatsApp</label>
        <input id="whatsapp" type="tel" name="whatsapp" placeholder="08xxxxxxxxxx" required autofocus autocomplete="tel">
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" placeholder="Password" required autocomplete="current-password">
      </div>
      <button type="submit">Masuk</button>
    </form>

    <p class="footer-note">Belum punya password? <a href="/referrer/set-password.php">Aktifkan dashboard kamu</a></p>
  </div>
</body>
</html>
