<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
start_secure_session();

$brand = require_brand_or_404(get_current_brand());

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
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
        } elseif (hash_equals($brand['admin_username'], $username) && password_verify($password, $brand['admin_password_hash'])) {
            $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE ip = ?');
            $stmt->execute([$ip]);

            session_regenerate_id(true);
            $_SESSION['admin_brand_id'] = (int)$brand['id'];
            header('Location: /admin/dashboard.php');
            exit;
        } else {
            $stmt = $pdo->prepare('INSERT INTO login_attempts (ip) VALUES (?)');
            $stmt->execute([$ip]);
            $error = 'Username atau password salah.';
        }
    }
}

if (!empty($_SESSION['admin_brand_id']) && (int)$_SESSION['admin_brand_id'] === (int)$brand['id']) {
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
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@800&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  <?= get_theme_css_vars($brand) ?>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    background: #1A1A1A; color: #FAFAFA; font-family: 'Poppins', sans-serif;
    min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px;
  }
  .box { max-width: 340px; width: 100%; text-align: center; }
  .logo { width: 92px; margin: 0 auto 22px; display: block; }
  h1 { font-family: 'Playfair Display', serif; color: var(--brand-primary); font-size: 22px; margin-bottom: 24px; }
  input {
    width: 100%; background: #242424; border: 1px solid rgba(255,255,255,0.15); border-radius: 10px;
    padding: 14px; color: #FAFAFA; font-size: 15px; text-align: left;
    margin-bottom: 14px; outline: none;
  }
  input:focus { border-color: var(--brand-primary); }
  button {
    width: 100%; background: var(--brand-primary); color: #1A1A1A; font-weight: 700; font-size: 15px;
    padding: 14px; border: none; border-radius: 10px; cursor: pointer;
  }
  .error { color: #E8956B; font-size: 13.5px; margin-bottom: 14px; }
</style>
</head>
<body>
<div class="box">
  <img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($brand['name']) ?>" class="logo">
  <h1>🔒 Admin <?= htmlspecialchars($brand['name']) ?></h1>
  <?php if (!empty($error)): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <form method="POST">
    <input type="text" name="username" placeholder="Username" autofocus required autocomplete="username">
    <input type="password" name="password" placeholder="Password" required autocomplete="current-password">
    <button type="submit">Masuk</button>
  </form>
</div>
</body>
</html>
