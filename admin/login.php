<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
start_secure_session();

$brand = require_brand_or_404(get_current_brand());

$error = null;
$notice = null;

if (($_GET['reauth'] ?? '') === 'superadmin') {
    $notice = 'Silakan login ulang sebagai superadmin untuk membuka halaman Kelola Admin.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
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
            $authenticated = false;
            $sessionRole = 'admin';
            $sessionUserId = null;
            $sessionBrandId = (int)$brand['id'];

            if (table_exists($pdo, 'admin_users')) {
                $stmt = $pdo->prepare('
                    SELECT id, brand_id, username, password_hash, role, status
                    FROM admin_users
                    WHERE username = ? AND status = "active"
                    LIMIT 1
                ');
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password_hash'])) {
                    $userRole = (string)$user['role'];
                    $userBrandId = $user['brand_id'] !== null ? (int)$user['brand_id'] : null;
                    if ($userRole === 'superadmin' || $userBrandId === (int)$brand['id']) {
                        $authenticated = true;
                        $sessionRole = $userRole;
                        $sessionUserId = (int)$user['id'];
                        $sessionBrandId = $userBrandId ?: (int)$brand['id'];
                    }
                }
            }

            // Bootstrap superadmin: memakai kredensial utama config.php agar superadmin
            // tetap bisa masuk sebelum tabel admin_users dibuat/diisi.
            if (!$authenticated && hash_equals(ADMIN_USERNAME, $username) && password_verify($password, ADMIN_PASSWORD_HASH)) {
                $authenticated = true;
                $sessionRole = 'superadmin';
                $sessionBrandId = (int)$brand['id'];
            }

            // Fallback kompatibilitas untuk admin brand lama di tabel brands.
            if (!$authenticated && hash_equals($brand['admin_username'], $username) && password_verify($password, $brand['admin_password_hash'])) {
                $authenticated = true;
                $sessionRole = 'admin';
                $sessionBrandId = (int)$brand['id'];
            }

            if ($authenticated) {
                $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE ip = ?');
                $stmt->execute([$ip]);

                session_regenerate_id(true);
                $_SESSION['admin_brand_id'] = $sessionBrandId;
                $_SESSION['admin_role'] = $sessionRole;
                $_SESSION['admin_user_id'] = $sessionUserId;
                header('Location: /admin/dashboard.php');
                exit;
            }

            $stmt = $pdo->prepare('INSERT INTO login_attempts (ip) VALUES (?)');
            $stmt->execute([$ip]);
            $error = 'Username atau password salah.';
        }
    }
}

if ((!empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'superadmin') || (!empty($_SESSION['admin_brand_id']) && (int)$_SESSION['admin_brand_id'] === (int)$brand['id'])) {
    header('Location: /admin/dashboard.php');
    exit;
}

$logoPath = $brand['logo_path'] ? '..' . $brand['logo_path'] : '../assets/logo.png';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — <?= htmlspecialchars($brand['name']) ?></title>
<style>
  <?= get_theme_css_vars($brand) ?>
  :root {
    --bg: #0B0B0A;
    --bg-soft: #10100F;
    --surface: rgba(23, 23, 22, 0.78);
    --surface-solid: #171716;
    --border-gold: rgba(214,165,54,0.22);
    --gold: var(--brand-primary, #D6A536);
    --gold-soft: var(--brand-soft, #F4D27A);
    --text: #F7F3E8;
    --muted: #A8A29A;
    --danger: #EF4444;
    --success: #22C55E;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html,
  body {
    width: 100%;
    height: 100%;
    overflow: hidden;
  }
  body {
    color: var(--text);
    background: var(--bg);
    font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  }
  button,
  input { font: inherit; }
  .login-page {
    position: relative;
    isolation: isolate;
    display: grid;
    place-items: center;
    width: 100%;
    min-height: 100vh;
    min-height: 100svh;
    height: 100vh;
    height: 100svh;
    overflow: hidden;
    padding: clamp(18px, 3vw, 32px);
    background:
      radial-gradient(circle at 50% 52%, color-mix(in srgb, var(--gold) 18%, transparent), transparent 28%),
      radial-gradient(circle at 86% 12%, color-mix(in srgb, var(--gold-soft) 14%, transparent), transparent 26%),
      radial-gradient(circle at 12% 88%, color-mix(in srgb, var(--gold) 10%, transparent), transparent 28%),
      linear-gradient(135deg, var(--bg) 0%, var(--bg-soft) 48%, #070706 100%);
  }
  @supports (height: 100dvh) {
    .login-page {
      min-height: 100dvh;
      height: 100dvh;
    }
  }
  .login-page::before,
  .login-page::after {
    content: "";
    position: absolute;
    inset: auto;
    pointer-events: none;
    z-index: -1;
  }
  .login-page::before {
    width: min(56vw, 760px);
    height: min(56vw, 760px);
    right: -18vw;
    top: -22vw;
    border: 1px solid color-mix(in srgb, var(--gold-soft) 16%, transparent);
    border-radius: 44%;
    background:
      repeating-radial-gradient(circle at center, color-mix(in srgb, var(--gold) 12%, transparent) 0 1px, transparent 1px 18px);
    opacity: .55;
    transform: rotate(-18deg);
  }
  .login-page::after {
    left: -12vw;
    bottom: -18vw;
    width: min(48vw, 620px);
    height: min(48vw, 620px);
    border-radius: 50%;
    background:
      radial-gradient(circle, color-mix(in srgb, var(--gold) 16%, transparent), transparent 58%),
      repeating-linear-gradient(135deg, color-mix(in srgb, var(--gold-soft) 10%, transparent) 0 1px, transparent 1px 18px);
    opacity: .62;
  }
  .login-shell {
    position: relative;
    width: min(100%, 420px);
    max-height: calc(100svh - 48px);
    display: grid;
    gap: clamp(14px, 2.6vh, 20px);
    justify-items: center;
  }
  .login-logo {
    display: block;
    width: clamp(88px, 12vw, 110px);
    height: auto;
    max-height: 78px;
    object-fit: contain;
    filter: drop-shadow(0 14px 26px rgba(0,0,0,.36));
  }
  .login-card {
    width: 100%;
    max-height: calc(100svh - 138px);
    overflow: hidden;
    padding: clamp(24px, 4vw, 34px);
    border: 1px solid var(--border-gold);
    border-radius: 26px;
    background:
      linear-gradient(180deg, color-mix(in srgb, var(--gold-soft) 7%, transparent), transparent 34%),
      var(--surface);
    box-shadow:
      0 28px 80px rgba(0,0,0,.46),
      inset 0 1px 0 rgba(255,255,255,.06);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
  }
  .card-head {
    display: grid;
    justify-items: center;
    gap: 9px;
    text-align: center;
    margin-bottom: clamp(18px, 3vh, 24px);
  }
  .shield-mark {
    display: grid;
    place-items: center;
    width: 40px;
    height: 40px;
    color: #111;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    box-shadow: 0 12px 28px color-mix(in srgb, var(--gold) 25%, transparent);
  }
  .shield-mark svg { width: 21px; height: 21px; }
  h1 {
    color: var(--text);
    font-family: Georgia, "Times New Roman", serif;
    font-size: clamp(24px, 4vw, 30px);
    line-height: 1.1;
    letter-spacing: 0;
  }
  .subtitle {
    max-width: 300px;
    color: var(--muted);
    font-size: 13.5px;
    line-height: 1.55;
  }
  .login-form {
    display: grid;
    gap: 14px;
  }
  .field {
    display: grid;
    gap: 7px;
  }
  .sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
  }
  .input-wrap {
    position: relative;
    display: flex;
    align-items: center;
  }
  .input-icon {
    position: absolute;
    left: 15px;
    width: 18px;
    height: 18px;
    color: color-mix(in srgb, var(--gold-soft) 78%, var(--muted));
    pointer-events: none;
  }
  .input-wrap input {
    width: 100%;
    height: 50px;
    color: var(--text);
    background: rgba(255,255,255,0.045);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 14px;
    outline: none;
    padding: 0 48px 0 46px;
    font-size: 15px;
    transition: border-color 180ms ease, box-shadow 180ms ease, background 180ms ease;
  }
  .input-wrap input::placeholder { color: color-mix(in srgb, var(--muted) 78%, transparent); }
  .input-wrap input:focus {
    border-color: color-mix(in srgb, var(--gold-soft) 62%, transparent);
    background: rgba(255,255,255,0.06);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--gold) 13%, transparent);
  }
  .password-toggle {
    position: absolute;
    right: 8px;
    display: grid;
    place-items: center;
    width: 38px;
    height: 38px;
    color: var(--muted);
    border: 0;
    border-radius: 12px;
    background: transparent;
    cursor: pointer;
    transition: color 180ms ease, background 180ms ease;
  }
  .password-toggle:hover,
  .password-toggle:focus-visible {
    color: var(--gold-soft);
    background: color-mix(in srgb, var(--gold) 10%, transparent);
    outline: none;
  }
  .password-toggle svg { width: 18px; height: 18px; }
  .helper-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    color: var(--muted);
    font-size: 12.5px;
    line-height: 1.4;
    margin-top: -2px;
  }
  .helper-row svg {
    width: 15px;
    height: 15px;
    color: var(--gold-soft);
    flex: 0 0 auto;
  }
  .alert-error {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    color: #FECACA;
    background: rgba(239,68,68,0.10);
    border: 1px solid rgba(239,68,68,0.28);
    border-radius: 14px;
    padding: 11px 12px;
    font-size: 13px;
    line-height: 1.45;
  }
  .alert-notice {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    color: #FEF3C7;
    background: rgba(245,158,11,0.10);
    border: 1px solid rgba(245,158,11,0.28);
    border-radius: 14px;
    padding: 11px 12px;
    font-size: 13px;
    line-height: 1.45;
  }
  .alert-notice svg {
    width: 17px;
    height: 17px;
    flex: 0 0 auto;
    margin-top: 1px;
  }
  .alert-error svg {
    width: 17px;
    height: 17px;
    flex: 0 0 auto;
    margin-top: 1px;
  }
  .submit-btn {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    height: 52px;
    color: #111;
    background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    border: 0;
    border-radius: 14px;
    cursor: pointer;
    font-size: 15px;
    font-weight: 800;
    box-shadow: 0 16px 32px color-mix(in srgb, var(--gold) 22%, transparent);
    transition: transform 180ms ease, box-shadow 180ms ease, opacity 180ms ease;
  }
  .submit-btn:hover { transform: translateY(-1px); box-shadow: 0 20px 38px color-mix(in srgb, var(--gold) 28%, transparent); }
  .submit-btn:active { transform: scale(.98); }
  .submit-btn[disabled] {
    cursor: wait;
    opacity: .78;
    transform: none;
  }
  .spinner {
    display: none;
    width: 16px;
    height: 16px;
    border: 2px solid rgba(17,17,17,.22);
    border-top-color: #111;
    border-radius: 50%;
    animation: spin 700ms linear infinite;
  }
  .submit-btn.is-loading .spinner { display: inline-block; }
  @keyframes spin { to { transform: rotate(360deg); } }
  .security-note {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    color: var(--muted);
    font-size: 12px;
    line-height: 1.4;
    margin-top: 2px;
  }
  .security-note svg {
    width: 14px;
    height: 14px;
    color: var(--success);
  }
  @media (max-width: 520px) {
    .login-page { padding: 18px; }
    .login-shell {
      width: min(100%, 360px);
      max-height: calc(100svh - 36px);
      gap: 14px;
    }
    .login-logo {
      width: 88px;
      max-height: 62px;
    }
    .login-card {
      max-height: calc(100svh - 112px);
      padding: 24px;
      border-radius: 22px;
    }
    .login-page::before { opacity: .25; }
    .login-page::after { opacity: .35; }
    .input-wrap input {
      height: 46px;
      font-size: 14px;
    }
    .submit-btn { height: 50px; }
  }
  @media (max-height: 640px) {
    .login-page { padding: 14px; }
    .login-shell { gap: 10px; max-height: calc(100svh - 28px); }
    .login-logo { width: 82px; max-height: 54px; }
    .login-card {
      max-height: calc(100svh - 88px);
      padding: 20px;
      border-radius: 20px;
    }
    .card-head {
      gap: 6px;
      margin-bottom: 14px;
    }
    .shield-mark {
      width: 34px;
      height: 34px;
      border-radius: 12px;
    }
    .subtitle { font-size: 12.5px; }
    .login-form { gap: 10px; }
    .helper-row,
    .security-note { font-size: 11.5px; }
  }
</style>
</head>
<body>
<main class="login-page" aria-labelledby="login-title">
  <div class="login-shell">
    <img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($brand['name']) ?>" class="login-logo">

    <section class="login-card" aria-label="Login admin">
      <div class="card-head">
        <span class="shield-mark" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <path d="M12 3 19 6v5c0 4.5-2.8 8.4-7 10-4.2-1.6-7-5.5-7-10V6l7-3Z" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/>
            <path d="m9.5 12 1.7 1.7 3.6-4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </span>
        <h1 id="login-title">Admin <?= htmlspecialchars($brand['name']) ?></h1>
        <p class="subtitle">Masuk untuk mengelola event, referral, dan analytics.</p>
      </div>

      <form class="login-form" method="POST" id="loginForm">
        <?php if (!empty($notice)): ?>
          <div class="alert-notice" role="status">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M12 8v5m0 4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span><?= htmlspecialchars($notice) ?></span>
          </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
          <div class="alert-error" role="alert">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M12 9v4m0 4h.01M10.3 4.4 2.6 18a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 4.4a2 2 0 0 0-3.4 0Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span><?= htmlspecialchars($error) ?></span>
          </div>
        <?php endif; ?>

        <div class="field">
          <label class="sr-only" for="admin-username">Username</label>
          <div class="input-wrap">
            <svg class="input-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M20 21a8 8 0 0 0-16 0m12-13a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <input type="text" id="admin-username" name="username" placeholder="Username" autofocus required autocomplete="username">
          </div>
        </div>

        <div class="field">
          <label class="sr-only" for="admin-password">Password</label>
          <div class="input-wrap">
            <svg class="input-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M7 11V8a5 5 0 0 1 10 0v3m-9 0h8a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2v-6a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <input type="password" id="admin-password" name="password" placeholder="Password" required autocomplete="current-password">
            <button class="password-toggle" type="button" id="togglePassword" aria-label="Tampilkan password" aria-pressed="false">
              <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
          </div>
        </div>

        <div class="helper-row">
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M12 3 19 6v5c0 4.5-2.8 8.4-7 10-4.2-1.6-7-5.5-7-10V6l7-3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
          </svg>
          <span>Akses aman untuk tim admin</span>
        </div>

        <button class="submit-btn" type="submit" id="submitBtn">
          <span class="spinner" aria-hidden="true"></span>
          <span class="btn-text">Masuk</span>
        </button>

        <p class="security-note">
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="m5 12 4 4L19 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <span>Koneksi admin terlindungi dan dipantau.</span>
        </p>
      </form>
    </section>
  </div>
</main>

<script>
(function () {
  const form = document.getElementById('loginForm');
  const password = document.getElementById('admin-password');
  const toggle = document.getElementById('togglePassword');
  const submit = document.getElementById('submitBtn');
  const submitText = submit ? submit.querySelector('.btn-text') : null;

  if (toggle && password) {
    toggle.addEventListener('click', function () {
      const show = password.type === 'password';
      password.type = show ? 'text' : 'password';
      toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
      toggle.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
      password.focus();
    });
  }

  if (form && submit && submitText) {
    form.addEventListener('submit', function () {
      submit.disabled = true;
      submit.classList.add('is-loading');
      submitText.textContent = 'Memproses...';
    });
  }
})();
</script>
</body>
</html>
