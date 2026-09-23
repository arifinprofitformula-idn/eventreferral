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
$user = lms_get_logged_user($pdo, $brandId);

if (!$user) {
    header('Location: /course/login.php?redirect=' . urlencode('/course/notifications.php'));
    exit;
}

// Tandai semua sudah dibaca saat halaman dibuka
$stmtMark = $pdo->prepare('UPDATE lms_notifications SET is_read = 1 WHERE user_id = ?');
$stmtMark->execute([(int)$user['id']]);

$stmt = $pdo->prepare('
    SELECT * FROM lms_notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 50
');
$stmt->execute([(int)$user['id']]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

render_lms_header($brand, $user, 'notifications');
?>
<div style="max-width:640px;margin:0 auto;">
  <h1 style="font-size:24px;font-weight:800;margin-bottom:20px;">Notifikasi Akun</h1>

  <?php if (empty($notifications)): ?>
    <div style="text-align:center;padding:48px 20px;background:var(--surface);border:1px solid var(--border-soft);border-radius:18px;">
      <p style="color:var(--muted);">Belum ada notifikasi.</p>
    </div>
  <?php else: ?>
    <div style="display:grid;gap:12px;">
      <?php foreach ($notifications as $n): ?>
        <div style="background:var(--surface);border:1px solid var(--border-soft);border-radius:16px;padding:18px;">
          <div style="display:flex;justify-content:space-between;align-items:start;gap:12px;margin-bottom:6px;">
            <strong style="font-size:14.5px;color:var(--text);"><?= htmlspecialchars($n['title']) ?></strong>
            <span style="font-size:11px;color:var(--muted);white-space:nowrap;"><?= date('d M Y, H:i', strtotime($n['created_at'])) ?></span>
          </div>
          <p style="font-size:13.5px;color:var(--muted);line-height:1.55;"><?= htmlspecialchars($n['message']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php render_lms_footer($brand); ?>
