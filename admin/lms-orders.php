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

function lo_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$stmt = $pdo->prepare('
    SELECT o.*, u.name AS user_name, u.email AS user_email, c.title AS course_title
    FROM lms_orders o
    JOIN lms_users u ON u.id = o.user_id
    JOIN lms_courses c ON c.id = o.course_id
    WHERE o.brand_id = ?
    ORDER BY o.created_at DESC
    LIMIT 300
');
$stmt->execute([$brandId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmtStats = $pdo->prepare('
    SELECT payment_status, COUNT(*) AS total, COALESCE(SUM(amount),0) AS sum_amount
    FROM lms_orders WHERE brand_id = ? GROUP BY payment_status
');
$stmtStats->execute([$brandId]);
$stats = ['pending' => ['total' => 0, 'sum' => 0], 'paid' => ['total' => 0, 'sum' => 0], 'failed' => ['total' => 0, 'sum' => 0]];
foreach ($stmtStats->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $stats[$row['payment_status']] = ['total' => (int)$row['total'], 'sum' => (int)$row['sum_amount']];
}

$logoPath = $brand['logo_path'] ? '..' . $brand['logo_path'] : '../assets/logo.png';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order & Pembayaran LMS - <?= lo_h($brand['name']) ?></title>
<style>
  <?= get_theme_css_vars($brand) ?>
  :root { --bg:#0B0B0A; --surface:#171716; --border-gold:rgba(214,165,54,0.18); --gold:var(--brand-primary); --gold-soft:var(--brand-soft); --text:#F7F3E8; --muted:#A8A29A; }
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
  .stat-card .num { font-size:22px; font-weight:800; color:var(--gold-soft); }
  .panel { border:1px solid var(--border-gold); border-radius:22px; background:linear-gradient(145deg, rgba(32,32,30,0.94), rgba(23,23,22,0.94)); padding:24px; margin-top:20px; box-shadow:0 22px 70px rgba(0,0,0,0.28); }
  .table-scroll { overflow-x:auto; border:1px solid rgba(255,255,255,0.08); border-radius:16px; }
  table { width:100%; min-width:900px; border-collapse:collapse; font-size:13px; }
  th, td { padding:12px 14px; text-align:left; border-bottom:1px solid rgba(255,255,255,0.07); }
  th { background:rgba(32,32,30,0.95); }
  .pill { display:inline-flex; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:800; }
  .pill-pending { background:rgba(245,158,11,0.15); color:#fcd34d; }
  .pill-paid { background:rgba(34,197,94,0.15); color:#86efac; }
  .pill-failed { background:rgba(239,68,68,0.15); color:#fca5a5; }
</style>
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="dashboard.php"><img src="<?= lo_h($logoPath) ?>" alt="<?= lo_h($brand['name']) ?>"></a>
    <?php render_admin_nav('lms-orders'); ?>
  </div>
</header>

<main class="wrap">
  <h1>Order & <span>Pembayaran</span> LMS</h1>
  <p class="subtitle">Riwayat transaksi checkout eCourse premium dan status pembayaran.</p>

  <div class="stat-grid">
    <div class="stat-card"><div class="muted" style="font-size:12px;">Pending</div><div class="num"><?= $stats['pending']['total'] ?> order</div></div>
    <div class="stat-card"><div class="muted" style="font-size:12px;">Berhasil (Paid)</div><div class="num">Rp <?= number_format($stats['paid']['sum'], 0, ',', '.') ?></div></div>
    <div class="stat-card"><div class="muted" style="font-size:12px;">Gagal</div><div class="num"><?= $stats['failed']['total'] ?> order</div></div>
  </div>

  <section class="panel">
    <div class="table-scroll">
      <table>
        <thead><tr><th>No. Order</th><th>User</th><th>eCourse</th><th>Jumlah</th><th>Metode</th><th>Bukti</th><th>Status</th><th>Tanggal</th></tr></thead>
        <tbody>
          <?php foreach ($orders as $o): ?>
            <tr>
              <td><code><?= lo_h($o['order_number']) ?></code><?php if (!empty($o['midtrans_transaction_id'])): ?><br><span class="muted">MID: <?= lo_h($o['midtrans_transaction_id']) ?></span><?php endif; ?></td>
              <td><?= lo_h($o['user_name']) ?><br><span class="muted"><?= lo_h($o['user_email']) ?></span></td>
              <td><?= lo_h($o['course_title']) ?></td>
              <td>Rp <?= number_format((int)$o['amount'], 0, ',', '.') ?></td>
              <td><?= lo_h(lms_payment_method_label((string)$o['payment_method'])) ?></td>
              <td><?php if (!empty($o['payment_proof_path'])): ?><a href="<?= lo_h($o['payment_proof_path']) ?>" target="_blank" rel="noopener" style="color:var(--gold-soft);font-weight:800;">Lihat Bukti</a><br><span class="muted"><?= !empty($o['payment_proof_uploaded_at']) ? date('d M H:i', strtotime($o['payment_proof_uploaded_at'])) : '' ?></span><?php else: ?><span class="muted">Belum ada</span><?php endif; ?></td>
              <td><span class="pill pill-<?= $o['payment_status'] ?>"><?= strtoupper($o['payment_status']) ?></span></td>
              <td class="muted"><?= date('d M Y, H:i', strtotime($o['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($orders)): ?>
            <tr><td colspan="8" class="muted" style="text-align:center;padding:30px;">Belum ada transaksi.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
</body>
</html>
