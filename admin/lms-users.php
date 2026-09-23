<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_nav.php';
require_once __DIR__ . '/../includes/lms_core.php';
start_secure_session();

$brand = require_admin_for_brand(get_current_brand());
$brandId = (int)$brand['id'];
$pdo = get_db();

lms_ensure_schema($pdo);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function lu_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Sesi tidak valid. Silakan refresh halaman.';
    } else {
        $action = $_POST['action'] ?? '';
        $userId = (int)($_POST['user_id'] ?? 0);

        // Pastikan user milik brand ini
        $stmtCheck = $pdo->prepare('SELECT id FROM lms_users WHERE id = ? AND brand_id = ?');
        $stmtCheck->execute([$userId, $brandId]);
        if (!$stmtCheck->fetchColumn()) {
            $errors[] = 'User tidak ditemukan.';
        } elseif ($action === 'change_role') {
            $newRole = $_POST['new_role'] ?? '';
            if (lms_assign_role($pdo, $userId, $newRole, 'admin_manual_change')) {
                $messages[] = 'Role user berhasil diperbarui ke ' . strtoupper($newRole) . '.';
            } else {
                $errors[] = 'Role tidak valid.';
            }
        } elseif ($action === 'toggle_status') {
            $newStatus = $_POST['status'] === 'inactive' ? 'inactive' : 'active';
            $stmt = $pdo->prepare('UPDATE lms_users SET status = ? WHERE id = ?');
            $stmt->execute([$newStatus, $userId]);
            $messages[] = 'Status user berhasil diperbarui.';
        } elseif ($action === 'reset_password') {
            $newPassword = (string)($_POST['new_password'] ?? '');
            if (mb_strlen($newPassword) < 8) {
                $errors[] = 'Password baru minimal 8 karakter.';
            } else {
                $stmt = $pdo->prepare('UPDATE lms_users SET password_hash = ? WHERE id = ?');
                $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
                $messages[] = 'Password user berhasil direset.';
            }
        }
    }
}

$search = trim(clean($_GET['q'] ?? ''));
$roleFilter = $_GET['role'] ?? '';

$sql = 'SELECT * FROM lms_users WHERE brand_id = ?';
$params = [$brandId];

if ($search !== '') {
    $sql .= ' AND (name LIKE ? OR email LIKE ? OR whatsapp LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if (in_array($roleFilter, ['free', 'paid', 'admin'], true)) {
    $sql .= ' AND primary_role = ?';
    $params[] = $roleFilter;
}
$sql .= ' ORDER BY created_at DESC LIMIT 300';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistik ringkas
$stmtStats = $pdo->prepare('
    SELECT primary_role, COUNT(*) AS total
    FROM lms_users WHERE brand_id = ?
    GROUP BY primary_role
');
$stmtStats->execute([$brandId]);
$roleStats = ['free' => 0, 'paid' => 0, 'admin' => 0];
foreach ($stmtStats->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $roleStats[$row['primary_role']] = (int)$row['total'];
}

$logoPath = $brand['logo_path'] ? '..' . $brand['logo_path'] : '../assets/logo.png';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola User & Role LMS - <?= lu_h($brand['name']) ?></title>
<style>
  <?= get_theme_css_vars($brand) ?>
  :root { --bg:#0B0B0A; --surface:#171716; --border-gold:rgba(214,165,54,0.18); --gold:var(--brand-primary); --gold-soft:var(--brand-soft); --text:#F7F3E8; --muted:#A8A29A; --danger:#EF4444; --success:#22C55E; }
  * { box-sizing:border-box; }
  body { margin:0; min-height:100vh; color:var(--text); background:radial-gradient(circle at 84% 8%, color-mix(in srgb, var(--gold) 22%, transparent), transparent 30vw), linear-gradient(135deg, var(--bg), #090908); font-family:Inter, system-ui, sans-serif; }
  a { color:inherit; }
  .topbar { position:sticky; top:0; z-index:10; background:rgba(16,16,15,0.82); border-bottom:1px solid rgba(255,255,255,0.08); backdrop-filter:blur(16px); }
  .topbar-inner, .wrap { width:min(100%, 1320px); margin:0 auto; padding-left:32px; padding-right:32px; }
  .topbar-inner { min-height:82px; display:flex; align-items:center; justify-content:space-between; gap:20px; }
  .brand img { width:132px; max-height:58px; object-fit:contain; }
  .wrap { padding-top:28px; padding-bottom:56px; }
  h1 { font-family:Georgia, serif; font-size:clamp(28px,4vw,40px); margin-bottom:8px; }
  h1 span { color:var(--gold-soft); }
  .subtitle, .muted { color:var(--muted); }
  .stat-grid { display:grid; grid-template-columns:repeat(3, 1fr); gap:14px; margin:20px 0; }
  .stat-card { border:1px solid var(--border-gold); border-radius:16px; padding:18px; background:linear-gradient(145deg, rgba(32,32,30,0.94), rgba(23,23,22,0.94)); }
  .stat-card .num { font-size:26px; font-weight:800; color:var(--gold-soft); }
  .panel { border:1px solid var(--border-gold); border-radius:22px; background:linear-gradient(145deg, rgba(32,32,30,0.94), rgba(23,23,22,0.94)); padding:24px; margin-top:20px; box-shadow:0 22px 70px rgba(0,0,0,0.28); }
  .alerts { display:grid; gap:10px; margin:16px 0; }
  .alert { border-radius:14px; padding:12px 14px; font-size:14px; }
  .alert-ok { color:#bbf7d0; background:rgba(34,197,94,0.10); border:1px solid rgba(34,197,94,0.26); }
  .alert-error { color:#fecaca; background:rgba(239,68,68,0.10); border:1px solid rgba(239,68,68,0.28); }
  .filters { display:flex; gap:10px; margin-bottom:16px; flex-wrap:wrap; }
  input, select { min-height:42px; color:var(--text); background:rgba(255,255,255,0.045); border:1px solid rgba(255,255,255,0.1); border-radius:12px; padding:0 12px; font:inherit; }
  .table-scroll { overflow-x:auto; border:1px solid rgba(255,255,255,0.08); border-radius:16px; }
  table { width:100%; min-width:960px; border-collapse:collapse; font-size:13px; }
  th, td { padding:12px 14px; text-align:left; border-bottom:1px solid rgba(255,255,255,0.07); vertical-align:top; }
  th { background:rgba(32,32,30,0.95); }
  .pill { display:inline-flex; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:800; }
  .pill-free { background:rgba(59,130,246,0.15); color:#93c5fd; }
  .pill-paid { background:rgba(214,165,54,0.18); color:var(--gold-soft); }
  .pill-admin { background:rgba(168,85,247,0.18); color:#d8b4fe; }
  .btn { display:inline-flex; align-items:center; gap:6px; padding:7px 12px; border-radius:10px; border:1px solid transparent; font-size:11.5px; font-weight:800; cursor:pointer; }
  .btn-primary { color:#111; background:linear-gradient(135deg, var(--gold), var(--gold-soft)); }
  .btn-secondary { color:var(--text); background:rgba(255,255,255,0.05); border-color:rgba(255,255,255,0.1); }
  .btn-danger { color:#fecaca; background:rgba(239,68,68,0.1); border-color:rgba(239,68,68,0.3); }
  .row-actions { display:flex; gap:6px; flex-wrap:wrap; }
</style>
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="dashboard.php"><img src="<?= lu_h($logoPath) ?>" alt="<?= lu_h($brand['name']) ?>"></a>
    <?php render_admin_nav('lms-users'); ?>
  </div>
</header>

<main class="wrap">
  <h1>Kelola <span>User & Role</span> LMS</h1>
  <p class="subtitle">Monitor role akses (Free/Paid/Admin) dan kelola akun siswa platform eCourse.</p>

  <div class="stat-grid">
    <div class="stat-card"><div class="muted" style="font-size:12px;">Free User</div><div class="num"><?= $roleStats['free'] ?></div></div>
    <div class="stat-card"><div class="muted" style="font-size:12px;">Paid User</div><div class="num"><?= $roleStats['paid'] ?></div></div>
    <div class="stat-card"><div class="muted" style="font-size:12px;">Admin LMS</div><div class="num"><?= $roleStats['admin'] ?></div></div>
  </div>

  <?php if (!empty($messages) || !empty($errors)): ?>
    <div class="alerts">
      <?php foreach ($messages as $m): ?><div class="alert alert-ok"><?= lu_h($m) ?></div><?php endforeach; ?>
      <?php foreach ($errors as $e): ?><div class="alert alert-error"><?= lu_h($e) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <section class="panel">
    <form method="GET" class="filters">
      <input type="text" name="q" placeholder="Cari nama / email / WA..." value="<?= lu_h($search) ?>" style="flex:1;min-width:220px;">
      <select name="role">
        <option value="">Semua Role</option>
        <option value="free" <?= $roleFilter === 'free' ? 'selected' : '' ?>>Free</option>
        <option value="paid" <?= $roleFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
        <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
      </select>
      <button type="submit" class="btn btn-secondary" style="min-height:42px;">Filter</button>
    </form>

    <div class="table-scroll">
      <table>
        <thead>
          <tr><th>User</th><th>Kontak</th><th>Role</th><th>Status</th><th>Terdaftar</th><th>Aksi</th></tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td><strong><?= lu_h($u['name']) ?></strong></td>
              <td><?= lu_h($u['email']) ?><br><span class="muted"><?= lu_h($u['whatsapp']) ?></span></td>
              <td>
                <span class="pill pill-<?= $u['primary_role'] ?>"><?= strtoupper($u['primary_role']) ?></span>
              </td>
              <td><span class="pill <?= $u['status'] === 'active' ? 'pill-free' : '' ?>" style="<?= $u['status'] !== 'active' ? 'background:rgba(239,68,68,0.15);color:#fca5a5;' : '' ?>"><?= strtoupper($u['status']) ?></span></td>
              <td class="muted"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
              <td>
                <div class="row-actions">
                  <form method="POST" class="row-actions">
                    <input type="hidden" name="csrf_token" value="<?= lu_h($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="change_role">
                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                    <select name="new_role" style="min-height:34px;font-size:11.5px;">
                      <option value="free" <?= $u['primary_role'] === 'free' ? 'selected' : '' ?>>Free</option>
                      <option value="paid" <?= $u['primary_role'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                      <option value="admin" <?= $u['primary_role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                      <option value="instructor">Instructor</option>
                      <option value="moderator">Moderator</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Ubah</button>
                  </form>
                  <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= lu_h($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                    <input type="hidden" name="status" value="<?= $u['status'] === 'active' ? 'inactive' : 'active' ?>">
                    <button type="submit" class="btn <?= $u['status'] === 'active' ? 'btn-danger' : 'btn-secondary' ?>"><?= $u['status'] === 'active' ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                  </form>
                  <form method="POST" class="row-actions">
                    <input type="hidden" name="csrf_token" value="<?= lu_h($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                    <input name="new_password" type="password" minlength="8" placeholder="Password baru" style="min-height:34px;width:130px;font-size:11.5px;">
                    <button type="submit" class="btn btn-secondary">Reset</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($users)): ?>
            <tr><td colspan="6" class="muted" style="text-align:center;padding:30px;">Belum ada user terdaftar.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
</body>
</html>
