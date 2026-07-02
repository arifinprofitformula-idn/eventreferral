<?php
/**
 * admin/generate-password-hash.php
 * Alat bantu SEKALI PAKAI untuk membuat ADMIN_PASSWORD_HASH.
 * Tidak butuh login (memang belum bisa login sebelum hash-nya ada di config.php),
 * tidak menyimpan apapun ke database/sesi — cuma kalkulator hash satu arah.
 *
 * PENTING: HAPUS FILE INI DARI SERVER setelah selesai dipakai.
 */

$hash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if ($password !== '') {
        $hash = password_hash($password, PASSWORD_DEFAULT);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Generate Password Hash — rahasiaemas.id</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    background: #1A1A1A; color: #FAFAFA; font-family: Poppins, Arial, sans-serif;
    min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px;
  }
  .box { max-width: 480px; width: 100%; }
  h1 { color: #C9A84C; font-size: 20px; margin-bottom: 16px; }
  .warning {
    background: rgba(217,116,58,0.15); color: #E8956B; border: 1px solid rgba(217,116,58,0.4);
    border-radius: 10px; padding: 14px 16px; font-size: 13.5px; margin-bottom: 20px; font-weight: 700;
  }
  input {
    width: 100%; background: #242424; border: 1px solid rgba(255,255,255,0.15); border-radius: 10px;
    padding: 13px 14px; color: #FAFAFA; font-size: 15px; margin-bottom: 14px; outline: none;
  }
  input:focus { border-color: #C9A84C; }
  button {
    width: 100%; background: #C9A84C; color: #1A1A1A; font-weight: 700; font-size: 15px;
    padding: 13px; border: none; border-radius: 10px; cursor: pointer;
  }
  .result { margin-top: 20px; background: #242424; border: 1px solid rgba(201,168,76,0.3); border-radius: 10px; padding: 16px; }
  .result label { display: block; font-size: 12.5px; color: #E8D5A3; margin-bottom: 8px; font-weight: 600; }
  .result textarea {
    width: 100%; background: #1A1A1A; border: 1px solid rgba(255,255,255,0.15); border-radius: 8px;
    color: #E8D5A3; font-size: 13px; padding: 10px; font-family: monospace; resize: none;
  }
</style>
</head>
<body>
<div class="box">
  <h1>🔑 Generate Password Hash Admin</h1>
  <div class="warning">⚠️ Hapus file ini dari server setelah selesai dipakai.</div>

  <form method="POST">
    <input type="password" name="password" placeholder="Ketik password admin baru di sini" autofocus required minlength="8">
    <button type="submit">Buat Hash</button>
  </form>

  <?php if ($hash): ?>
  <div class="result">
    <label>Salin hasil ini ke ADMIN_PASSWORD_HASH di config.php:</label>
    <textarea rows="3" readonly onclick="this.select()"><?= htmlspecialchars($hash) ?></textarea>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
