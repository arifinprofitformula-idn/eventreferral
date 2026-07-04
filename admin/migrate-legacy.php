<?php
/**
 * admin/migrate-legacy.php — Migrasi SATU KALI PAKAI
 *
 * Membuat baris brand pertama ("rahasiaemas") dari kredensial yang
 * sudah ada di config.php, lalu mengisi brand_id di semua baris lama
 * pada tabel events/referrers/leads.
 *
 * Jalankan file ini SEKALI lewat browser SETELAH migrate_v7_multibrand.sql
 * dan SEBELUM migrate_v7_multibrand_finalize.sql. Setelah berhasil,
 * HAPUS FILE INI dari server — jangan biarkan tertinggal.
 */

require_once __DIR__ . '/../config.php';
start_secure_session();

if (empty($_SESSION['admin_authenticated']) && empty($_SESSION['legacy_migration_authenticated'])) {
    $loginError = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if (hash_equals(ADMIN_USERNAME, $username) && password_verify($password, ADMIN_PASSWORD_HASH)) {
            session_regenerate_id(true);
            $_SESSION['legacy_migration_authenticated'] = true;
            header('Location: migrate-legacy.php');
            exit;
        }

        $loginError = 'Username atau password salah.';
    }
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Login Migrasi Legacy</title>
<style>
  body { font-family: Arial, sans-serif; background: #1A1A1A; color: #FAFAFA; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
  .box { width: 100%; max-width: 360px; background: #242424; border-radius: 12px; padding: 28px; }
  h1 { color: #C9A84C; font-size: 20px; margin: 0 0 18px; }
  input, button { width: 100%; box-sizing: border-box; padding: 12px; margin-bottom: 12px; border-radius: 8px; font-size: 15px; }
  input { background: #1A1A1A; border: 1px solid rgba(255,255,255,0.18); color: #FAFAFA; }
  button { background: #C9A84C; color: #1A1A1A; border: 0; font-weight: 700; cursor: pointer; }
  .error { color: #E8956B; font-size: 14px; }
  .note { color: #B8B4AA; font-size: 13px; line-height: 1.5; }
</style>
</head>
<body>
<div class="box">
  <h1>Login Migrasi Legacy</h1>
  <p class="note">Gunakan username/password admin lama dari <code>config.php</code>. Login ini tidak bergantung pada tabel brand.</p>
  <?php if ($loginError): ?><p class="error"><?= htmlspecialchars($loginError) ?></p><?php endif; ?>
  <form method="POST">
    <input type="text" name="username" placeholder="Username admin" required autofocus>
    <input type="password" name="password" placeholder="Password admin" required>
    <button type="submit">Masuk & Jalankan Migrasi</button>
  </form>
</div>
</body>
</html>
    <?php
    exit;
}

$pdo = get_db();
$messages = [];
$error = null;

try {
    $currentHost = preg_replace('/^www\./', '', strtolower($_SERVER['HTTP_HOST'] ?? 'rahasiaemas.id'));
    $currentHost = explode(':', $currentHost)[0];
    $existing = $pdo->query("SELECT id, domain FROM brands WHERE slug = 'rahasiaemas'")->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $brandId = (int)$existing['id'];
        $messages[] = 'Brand "rahasiaemas" sudah ada (id=' . $brandId . '). Melanjutkan recovery/backfill baris lama yang masih kosong.';
        $stmt = $pdo->prepare('UPDATE brands SET admin_username = ?, admin_password_hash = ? WHERE id = ?');
        $stmt->execute([ADMIN_USERNAME, ADMIN_PASSWORD_HASH, $brandId]);
        $messages[] = 'Username/password admin brand disamakan ulang dengan ADMIN_USERNAME dan ADMIN_PASSWORD_HASH dari config.php.';
        if (($existing['domain'] ?? '') !== $currentHost) {
            $messages[] = 'PERHATIAN — domain brand saat ini "' . ($existing['domain'] ?? '') . '", sedangkan domain yang sedang dibuka "' . $currentHost . '". Jika ini database staging, jalankan: UPDATE brands SET domain = "' . $currentHost . '" WHERE slug = "rahasiaemas";';
        }
    } else {
        $pdo->beginTransaction();

        // Tidak ada konstanta WA global di config.php — ambil whatsapp_default
        // dari event "default" yang sudah ada (mewakili landing page utama).
        $defaultWhatsapp = $pdo->query("SELECT whatsapp_default FROM events WHERE slug = 'default'")->fetchColumn();

        $stmt = $pdo->prepare('
            INSERT INTO brands
                (slug, domain, name, whatsapp_default, theme_preset, admin_username, admin_password_hash, status)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            'rahasiaemas',
            $currentHost,
            SITE_NAME,
            $defaultWhatsapp !== false ? $defaultWhatsapp : null,
            'gold',
            ADMIN_USERNAME,
            ADMIN_PASSWORD_HASH,
            'active',
        ]);
        $brandId = (int)$pdo->lastInsertId();

        $pdo->commit();

        $messages[] = 'Brand "rahasiaemas" berhasil dibuat (id=' . $brandId . ').';
    }

    $pdo->beginTransaction();
    $updatedEvents = $pdo->prepare('UPDATE events SET brand_id = ? WHERE brand_id IS NULL');
    $updatedEvents->execute([$brandId]);
    $updatedReferrers = $pdo->prepare('UPDATE referrers SET brand_id = ? WHERE brand_id IS NULL');
    $updatedReferrers->execute([$brandId]);
    $updatedLeads = $pdo->prepare('UPDATE leads SET brand_id = ? WHERE brand_id IS NULL');
    $updatedLeads->execute([$brandId]);
    $pdo->commit();

    $messages[] = 'Backfill brand_id=' . $brandId . ' selesai: events=' . $updatedEvents->rowCount() . ', referrers=' . $updatedReferrers->rowCount() . ', leads=' . $updatedLeads->rowCount() . '.';

    $checkEvents = (int)$pdo->query('SELECT COUNT(*) FROM events WHERE brand_id IS NULL')->fetchColumn();
    $checkReferrers = (int)$pdo->query('SELECT COUNT(*) FROM referrers WHERE brand_id IS NULL')->fetchColumn();
    $checkLeads = (int)$pdo->query('SELECT COUNT(*) FROM leads WHERE brand_id IS NULL')->fetchColumn();

    $messages[] = "Verifikasi — baris tanpa brand_id: events={$checkEvents}, referrers={$checkReferrers}, leads={$checkLeads} (harus 0 semua).";

    if ($checkEvents === 0 && $checkReferrers === 0 && $checkLeads === 0) {
        $messages[] = 'SIAP — Anda sekarang boleh menjalankan migrate_v7_multibrand_finalize.sql, lalu HAPUS file admin/migrate-legacy.php ini dari server.';
    } else {
        $messages[] = 'BELUM SIAP — masih ada baris tanpa brand_id. Jangan jalankan file finalize dulu. Periksa data secara manual.';
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error = 'Migrasi gagal: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Migrasi Multi-Brand — SEKALI PAKAI</title>
<style>
  body { font-family: 'Poppins', Arial, sans-serif; background: #1A1A1A; color: #FAFAFA; padding: 40px; line-height: 1.6; }
  .box { max-width: 640px; margin: 0 auto; background: #242424; border-radius: 12px; padding: 28px; }
  h1 { color: #C9A84C; font-size: 20px; }
  .error { color: #E8956B; }
  li { margin-bottom: 8px; }
</style>
</head>
<body>
<div class="box">
  <h1>Migrasi Legacy → Multi-Brand</h1>
  <?php if ($error): ?>
    <p class="error"><?= htmlspecialchars($error) ?></p>
  <?php else: ?>
    <ul>
      <?php foreach ($messages as $m): ?>
        <li><?= htmlspecialchars($m) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
</body>
</html>
