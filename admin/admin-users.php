<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
start_secure_session();

$brand = require_superadmin_for_brand(get_current_brand());
$brandId = (int)$brand['id'];
$pdo = get_db();

if (!table_exists($pdo, 'admin_users')) {
    http_response_code(500);
    exit('Tabel admin_users belum tersedia. Jalankan migrate_v13_superadmin_admin_users.sql terlebih dahulu.');
}

function admin_user_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function admin_user_valid_username(string $username): bool {
    return preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $username) === 1;
}

$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $role = $_POST['role'] === 'superadmin' ? 'superadmin' : 'admin';
        $targetBrandId = $role === 'superadmin' ? null : (int)($_POST['brand_id'] ?? 0);

        if (!admin_user_valid_username($username)) {
            $errors[] = 'Username harus 3-60 karakter dan hanya boleh berisi huruf, angka, titik, strip, atau underscore.';
        }
        if (mb_strlen($password) < 8) {
            $errors[] = 'Password minimal 8 karakter.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email tidak valid.';
        }
        if ($role === 'admin') {
            $stmt = $pdo->prepare('SELECT id FROM brands WHERE id = ? AND status = "active"');
            $stmt->execute([$targetBrandId]);
            if (!$stmt->fetchColumn()) {
                $errors[] = 'Brand untuk admin tidak valid.';
            }
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare('
                    INSERT INTO admin_users (brand_id, username, password_hash, name, email, role, status)
                    VALUES (?, ?, ?, ?, ?, ?, "active")
                ');
                $stmt->execute([
                    $targetBrandId,
                    $username,
                    password_hash($password, PASSWORD_DEFAULT),
                    $name !== '' ? $name : null,
                    $email !== '' ? $email : null,
                    $role,
                ]);
                $messages[] = 'User admin baru berhasil dibuat.';
            } catch (PDOException $e) {
                $errors[] = 'Username sudah dipakai atau data tidak bisa disimpan.';
            }
        }
    } elseif ($action === 'status') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $status = $_POST['status'] === 'inactive' ? 'inactive' : 'active';

        if (!empty($_SESSION['admin_user_id']) && (int)$_SESSION['admin_user_id'] === $userId && $status === 'inactive') {
            $errors[] = 'Superadmin tidak bisa menonaktifkan akunnya sendiri dari halaman ini.';
        } else {
            $stmt = $pdo->prepare('UPDATE admin_users SET status = ? WHERE id = ?');
            $stmt->execute([$status, $userId]);
            $messages[] = 'Status user berhasil diperbarui.';
        }
    } elseif ($action === 'reset_password') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $newPassword = (string)($_POST['new_password'] ?? '');

        if (mb_strlen($newPassword) < 8) {
            $errors[] = 'Password baru minimal 8 karakter.';
        } else {
            $stmt = $pdo->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?');
            $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
            $messages[] = 'Password user berhasil direset.';
        }
    }
}

$brands = $pdo->query('SELECT id, name, domain FROM brands WHERE status = "active" ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare('
    SELECT u.id, u.brand_id, u.username, u.name, u.email, u.role, u.status, u.created_at,
           b.name AS brand_name, b.domain AS brand_domain
    FROM admin_users u
    LEFT JOIN brands b ON b.id = u.brand_id
    ORDER BY (u.role = "superadmin") DESC, u.created_at DESC
');
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$logoPath = $brand['logo_path'] ? '..' . $brand['logo_path'] : '../assets/logo.png';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Admin - <?= admin_user_h($brand['name']) ?></title>
<style>
  <?= get_theme_css_vars($brand) ?>
  :root {
    --bg: #0B0B0A;
    --surface: #171716;
    --surface-elevated: #20201E;
    --border-gold: rgba(214,165,54,0.18);
    --gold: var(--brand-primary);
    --gold-soft: var(--brand-soft);
    --text: #F7F3E8;
    --muted: #A8A29A;
    --danger: #EF4444;
    --success: #22C55E;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    min-height: 100vh;
    color: var(--text);
    background:
      radial-gradient(circle at 84% 8%, color-mix(in srgb, var(--gold) 22%, transparent), transparent 30vw),
      radial-gradient(circle at 8% 88%, color-mix(in srgb, var(--gold) 13%, transparent), transparent 34vw),
      linear-gradient(135deg, var(--bg), #090908);
    font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  }
  a { color: inherit; }
  .topbar {
    position: sticky;
    top: 0;
    z-index: 10;
    background: rgba(16,16,15,0.82);
    border-bottom: 1px solid rgba(255,255,255,0.08);
    backdrop-filter: blur(16px);
  }
  .topbar-inner, .wrap {
    width: min(100%, 1280px);
    margin: 0 auto;
    padding-left: 32px;
    padding-right: 32px;
  }
  .topbar-inner {
    min-height: 82px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
  }
  .brand img { width: 132px; max-height: 58px; object-fit: contain; display: block; }
  .nav { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }
  .nav a, .btn {
    min-height: 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 15px;
    border-radius: 999px;
    color: var(--muted);
    border: 1px solid transparent;
    font-size: 13px;
    font-weight: 750;
    text-decoration: none;
  }
  .nav a:hover, .nav a.active { color: var(--gold-soft); background: color-mix(in srgb, var(--gold) 10%, transparent); border-color: var(--border-gold); }
  .wrap { padding-top: 28px; padding-bottom: 56px; }
  .hero, .panel {
    border: 1px solid var(--border-gold);
    border-radius: 24px;
    background: linear-gradient(145deg, rgba(32,32,30,0.94), rgba(23,23,22,0.94));
    box-shadow: 0 22px 70px rgba(0,0,0,0.28);
  }
  .hero {
    display: flex;
    justify-content: space-between;
    gap: 24px;
    padding: 32px;
    margin-bottom: 18px;
  }
  h1 { margin: 0 0 10px; font-family: Georgia, "Times New Roman", serif; font-size: clamp(30px, 4vw, 46px); line-height: 1.08; }
  h1 span { color: var(--gold-soft); }
  .subtitle, .muted { color: var(--muted); line-height: 1.6; }
  .panel { padding: 22px; margin-top: 18px; }
  .panel-head { display: flex; align-items: start; justify-content: space-between; gap: 18px; margin-bottom: 18px; }
  h2 { margin: 0 0 6px; font-size: 20px; }
  .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
  .field { display: grid; gap: 7px; }
  label { color: var(--text); font-size: 13px; font-weight: 800; }
  input, select {
    width: 100%;
    min-height: 48px;
    color: var(--text);
    background: rgba(255,255,255,0.045);
    border: 1px solid rgba(255,255,255,0.10);
    border-radius: 14px;
    outline: none;
    padding: 0 14px;
    font: inherit;
  }
  input:focus, select:focus { border-color: color-mix(in srgb, var(--gold-soft) 42%, transparent); box-shadow: 0 0 0 4px color-mix(in srgb, var(--gold) 10%, transparent); }
  .full { grid-column: 1 / -1; }
  .btn {
    border-radius: 14px;
    cursor: pointer;
    white-space: nowrap;
  }
  .btn-primary { color: #111; background: linear-gradient(135deg, var(--gold), var(--gold-soft)); }
  .btn-secondary { color: var(--text); background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.10); }
  .btn-danger { color: #fee2e2; background: rgba(239,68,68,0.10); border-color: rgba(239,68,68,0.32); }
  .alerts { display: grid; gap: 10px; margin-bottom: 16px; }
  .alert { border-radius: 14px; padding: 12px 14px; font-size: 14px; line-height: 1.5; }
  .alert-ok { color: #bbf7d0; background: rgba(34,197,94,0.10); border: 1px solid rgba(34,197,94,0.26); }
  .alert-error { color: #fecaca; background: rgba(239,68,68,0.10); border: 1px solid rgba(239,68,68,0.28); }
  .table-scroll { overflow-x: auto; border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; }
  table { width: 100%; min-width: 900px; border-collapse: collapse; font-size: 13.5px; }
  th, td { padding: 14px 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.07); vertical-align: top; }
  th { color: var(--text); background: rgba(32,32,30,0.95); }
  td { color: rgba(247,243,232,0.9); }
  tr:last-child td { border-bottom: 0; }
  .pill {
    display: inline-flex;
    align-items: center;
    min-height: 28px;
    padding: 0 10px;
    border-radius: 999px;
    color: var(--gold-soft);
    background: color-mix(in srgb, var(--gold) 10%, transparent);
    border: 1px solid var(--border-gold);
    font-size: 12px;
    font-weight: 850;
  }
  .row-actions { display: flex; gap: 8px; flex-wrap: wrap; }
  .inline-form { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
  .inline-form input { width: 180px; min-height: 40px; }
  @media (max-width: 760px) {
    .topbar-inner, .wrap { padding-left: 16px; padding-right: 16px; }
    .topbar-inner, .hero, .panel-head { align-items: stretch; flex-direction: column; }
    .nav { justify-content: flex-start; }
    .grid { grid-template-columns: 1fr; }
    .btn { width: 100%; }
    .inline-form input { width: 100%; }
  }
</style>
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="dashboard.php" aria-label="<?= admin_user_h($brand['name']) ?> Dashboard">
      <img src="<?= admin_user_h($logoPath) ?>" alt="<?= admin_user_h($brand['name']) ?>">
    </a>
    <nav class="nav" aria-label="Navigasi superadmin">
      <a href="dashboard.php">Dashboard</a>
      <a href="events.php">Kelola Event</a>
      <a class="active" href="admin-users.php">Kelola Admin</a>
      <a href="ai-settings.php">Pengaturan AI</a>
      <a href="logout.php">Keluar</a>
    </nav>
  </div>
</header>

<main class="wrap">
  <section class="hero">
    <div>
      <h1>Kelola <span>Admin User</span></h1>
      <p class="subtitle">Tambahkan user admin baru untuk mengelola website/brand ini. Superadmin dapat membuat admin biasa maupun superadmin lain.</p>
    </div>
    <a class="btn btn-secondary" href="setup-brand.php">Tambah Brand Baru</a>
  </section>

  <?php if (!empty($messages) || !empty($errors)): ?>
    <div class="alerts">
      <?php foreach ($messages as $message): ?><div class="alert alert-ok"><?= admin_user_h($message) ?></div><?php endforeach; ?>
      <?php foreach ($errors as $error): ?><div class="alert alert-error"><?= admin_user_h($error) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <section class="panel" aria-labelledby="create-admin-title">
    <div class="panel-head">
      <div>
        <h2 id="create-admin-title">Tambah User Admin</h2>
        <p class="muted">Admin biasa hanya bisa mengakses brand yang dipilih. Superadmin bisa mengakses dan mengelola semua brand.</p>
      </div>
    </div>
    <form method="POST" class="grid">
      <input type="hidden" name="action" value="create">
      <div class="field">
        <label for="username">Username</label>
        <input id="username" name="username" required autocomplete="off" placeholder="contoh: admin-event">
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" placeholder="Minimal 8 karakter">
      </div>
      <div class="field">
        <label for="name">Nama</label>
        <input id="name" name="name" placeholder="Nama admin">
      </div>
      <div class="field">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" placeholder="admin@example.com">
      </div>
      <div class="field">
        <label for="role">Role</label>
        <select id="role" name="role">
          <option value="admin">Admin Brand</option>
          <option value="superadmin">Superadmin</option>
        </select>
      </div>
      <div class="field">
        <label for="brand_id">Brand untuk Admin</label>
        <select id="brand_id" name="brand_id">
          <?php foreach ($brands as $row): ?>
            <option value="<?= (int)$row['id'] ?>" <?= (int)$row['id'] === $brandId ? 'selected' : '' ?>><?= admin_user_h($row['name']) ?> (<?= admin_user_h($row['domain']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="full">
        <button class="btn btn-primary" type="submit">Buat User Admin</button>
      </div>
    </form>
  </section>

  <section class="panel" aria-labelledby="users-title">
    <div class="panel-head">
      <div>
        <h2 id="users-title">Daftar User Admin</h2>
        <p class="muted">Nonaktifkan user jika aksesnya perlu dihentikan sementara, atau reset password bila diperlukan.</p>
      </div>
    </div>
    <?php if (empty($users)): ?>
      <p class="muted">Belum ada user di tabel admin_users.</p>
    <?php else: ?>
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>Username</th>
              <th>Nama / Email</th>
              <th>Role</th>
              <th>Brand</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $user): ?>
              <tr>
                <td><strong><?= admin_user_h($user['username']) ?></strong><br><span class="muted"><?= admin_user_h(date('d M Y, H:i', strtotime($user['created_at']))) ?></span></td>
                <td><?= admin_user_h($user['name'] ?: '-') ?><br><span class="muted"><?= admin_user_h($user['email'] ?: '-') ?></span></td>
                <td><span class="pill"><?= admin_user_h($user['role']) ?></span></td>
                <td><?= $user['role'] === 'superadmin' ? 'Semua brand' : admin_user_h(($user['brand_name'] ?? '-') . ' (' . ($user['brand_domain'] ?? '-') . ')') ?></td>
                <td><span class="pill"><?= admin_user_h($user['status']) ?></span></td>
                <td>
                  <div class="row-actions">
                    <form method="POST" class="inline-form">
                      <input type="hidden" name="action" value="status">
                      <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                      <input type="hidden" name="status" value="<?= $user['status'] === 'active' ? 'inactive' : 'active' ?>">
                      <button class="btn <?= $user['status'] === 'active' ? 'btn-danger' : 'btn-secondary' ?>" type="submit"><?= $user['status'] === 'active' ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                    </form>
                    <form method="POST" class="inline-form">
                      <input type="hidden" name="action" value="reset_password">
                      <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                      <input name="new_password" type="password" minlength="8" required placeholder="Password baru">
                      <button class="btn btn-secondary" type="submit">Reset</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</main>
<script>
  (function () {
    var role = document.getElementById('role');
    var brand = document.getElementById('brand_id');
    if (!role || !brand) return;
    function syncBrandState() {
      brand.disabled = role.value === 'superadmin';
    }
    role.addEventListener('change', syncBrandState);
    syncBrandState();
  })();
</script>
</body>
</html>
