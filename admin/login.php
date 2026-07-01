<?php
require_once __DIR__ . '/../config.php';
start_secure_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = $_POST['pin'] ?? '';
    if (hash_equals(ADMIN_PIN, $pin)) {
        session_regenerate_id(true);
        $_SESSION['admin_authenticated'] = true;
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'PIN salah. Coba lagi.';
    }
}

if (!empty($_SESSION['admin_authenticated'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — rahasiaemas.id</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@800&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    background: #1A1A1A; color: #FAFAFA; font-family: 'Poppins', sans-serif;
    min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px;
  }
  .box { max-width: 340px; width: 100%; text-align: center; }
  .logo { width: 92px; margin: 0 auto 22px; display: block; }
  h1 { font-family: 'Playfair Display', serif; color: #C9A84C; font-size: 22px; margin-bottom: 24px; }
  input {
    width: 100%; background: #242424; border: 1px solid rgba(255,255,255,0.15); border-radius: 10px;
    padding: 15px; color: #FAFAFA; font-size: 20px; text-align: center; letter-spacing: 6px;
    margin-bottom: 16px; outline: none;
  }
  input:focus { border-color: #C9A84C; }
  button {
    width: 100%; background: #C9A84C; color: #1A1A1A; font-weight: 700; font-size: 15px;
    padding: 14px; border: none; border-radius: 10px; cursor: pointer;
  }
  .error { color: #E8956B; font-size: 13.5px; margin-bottom: 14px; }
</style>
</head>
<body>
<div class="box">
  <img src="../assets/logo.png" alt="rahasiaemas.id" class="logo">
  <h1>🔒 Admin rahasiaemas.id</h1>
  <?php if (!empty($error)): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <form method="POST">
    <input type="password" name="pin" placeholder="••••••" inputmode="numeric" maxlength="10" autofocus required>
    <button type="submit">Masuk</button>
  </form>
</div>
</body>
</html>
