<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/lms_core.php';
require_once __DIR__ . '/../includes/lms_layout.php';
start_secure_session();

$brand = require_brand_or_404(get_current_brand());
$brandId = (int)$brand['id'];
$pdo = get_db();

lms_ensure_schema($pdo);

if (!empty($_SESSION['lms_user_id'])) {
    header('Location: /course/my-courses.php');
    exit;
}

if (empty($_SESSION['lms_csrf_token'])) {
    $_SESSION['lms_csrf_token'] = bin2hex(random_bytes(32));
}

$error = null;
$redirect = clean($_GET['redirect'] ?? '/course/my-courses.php');
$emailValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (!hash_equals($_SESSION['lms_csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Sesi kedaluwarsa. Silakan refresh halaman.';
    } else {
        $emailValue = trim(strtolower(clean($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');

        $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL ? MINUTE)');
        $stmt->execute([LOGIN_LOCKOUT_MINUTES]);

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ?');
        $stmt->execute([$ip]);
        $attemptCount = (int)$stmt->fetchColumn();

        if ($attemptCount >= LOGIN_MAX_ATTEMPTS) {
            $error = 'Terlalu banyak percobaan gagal. Coba lagi dalam beberapa menit.';
        } else {
            $stmt = $pdo->prepare('
                SELECT id, password_hash, status
                FROM lms_users
                WHERE brand_id = ? AND email = ?
                LIMIT 1
            ');
            $stmt->execute([$brandId, $emailValue]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($account && $account['status'] === 'active' && password_verify($password, $account['password_hash'])) {
                $stmtDel = $pdo->prepare('DELETE FROM login_attempts WHERE ip = ?');
                $stmtDel->execute([$ip]);

                session_regenerate_id(true);
                $_SESSION['lms_user_id'] = (int)$account['id'];

                header('Location: ' . $redirect);
                exit;
            }

            $stmtIns = $pdo->prepare('INSERT INTO login_attempts (ip) VALUES (?)');
            $stmtIns->execute([$ip]);
            $error = 'Email atau password salah.';
        }
    }
}

render_lms_header($brand, null, 'login');
?>
<div style="max-width:420px;margin:0 auto;">
  <div style="text-align:center;margin-bottom:28px;">
    <h1 style="font-size:26px;font-weight:800;margin-bottom:8px;">Login Siswa LMS</h1>
    <p style="color:var(--muted);font-size:14px;">Masuk untuk melanjutkan progress belajar eCourse Anda.</p>
  </div>

  <?php if ($error): ?>
    <div style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);color:#fecaca;padding:14px 16px;border-radius:12px;margin-bottom:20px;font-size:13.5px;">
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <form method="POST" style="background:var(--surface);border:1px solid var(--border-soft);border-radius:20px;padding:28px;display:grid;gap:16px;box-shadow:0 16px 44px rgba(0,0,0,0.3);">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['lms_csrf_token']) ?>">

    <div>
      <label style="font-size:13px;font-weight:700;display:block;margin-bottom:6px;">Email</label>
      <input type="email" name="email" required value="<?= htmlspecialchars($emailValue) ?>" placeholder="email@domain.com" style="width:100%;height:46px;padding:0 14px;border-radius:12px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);color:var(--text);font:inherit;">
    </div>
    <div>
      <label style="font-size:13px;font-weight:700;display:block;margin-bottom:6px;">Password</label>
      <input type="password" name="password" required placeholder="Password Anda" style="width:100%;height:46px;padding:0 14px;border-radius:12px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);color:var(--text);font:inherit;">
    </div>

    <button type="submit" class="btn-lms btn-lms-gold" style="width:100%;height:48px;margin-top:6px;">Login</button>

    <p style="text-align:center;font-size:13px;color:var(--muted);margin-top:6px;">
      Belum punya akun? <a href="/course/register.php?redirect=<?= urlencode($redirect) ?>" style="color:var(--gold-soft);font-weight:700;">Daftar Akun Baru</a>
    </p>
  </form>
</div>
<?php render_lms_footer($brand); ?>
