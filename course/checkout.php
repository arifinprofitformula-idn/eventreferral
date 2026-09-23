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

$slug = clean($_GET['slug'] ?? '');
if (!$user) {
    header('Location: /course/login.php?redirect=' . urlencode('/course/checkout.php?slug=' . $slug));
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM lms_courses WHERE brand_id = ? AND slug = ? AND status = "active"');
$stmt->execute([$brandId, $slug]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    header('Location: /course/index.php');
    exit;
}

$courseId = (int)$course['id'];

// Cek jika sudah punya akses aktif
$stmtEnr = $pdo->prepare('SELECT access_status FROM lms_enrollments WHERE user_id = ? AND course_id = ? AND access_status = "active"');
$stmtEnr->execute([(int)$user['id'], $courseId]);
$alreadyEnrolled = (bool)$stmtEnr->fetchColumn();

if ($course['access_type'] === 'free' || $alreadyEnrolled) {
    header('Location: /course/' . urlencode($slug));
    exit;
}

if (empty($_SESSION['lms_csrf_token'])) {
    $_SESSION['lms_csrf_token'] = bin2hex(random_bytes(32));
}

$notice = null;
$noticeType = 'info';

// ==== SIMULATOR CHECKOUT (belum ada payment gateway riil pada codebase) ====
// Alur ini mewakili titik integrasi resmi payment gateway (Midtrans/Xendit/dsb).
// Saat gateway riil dipasang, ganti simulasi "process" di bawah dengan create-invoice
// dan pindahkan status "paid" ke handler webhook (lihat api/lms-checkout-webhook.php).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!hash_equals($_SESSION['lms_csrf_token'], $_POST['csrf_token'] ?? '')) {
        $notice = 'Sesi kedaluwarsa. Silakan refresh halaman.';
        $noticeType = 'error';
    } else {
        $orderNumber = 'LMS-' . strtoupper(bin2hex(random_bytes(5)));
        $amount = (int)$course['price'];

        $stmtOrder = $pdo->prepare('
            INSERT INTO lms_orders (brand_id, user_id, course_id, order_number, amount, payment_method, payment_status)
            VALUES (?, ?, ?, ?, ?, "manual_simulator", "pending")
        ');
        $stmtOrder->execute([$brandId, (int)$user['id'], $courseId, $orderNumber, $amount]);

        if ($action === 'pay_success') {
            $result = lms_process_checkout_success($pdo, $brand, (int)$user['id'], $courseId, $orderNumber, $amount);
            if ($result['ok']) {
                header('Location: /course/my-courses.php?activated=1');
                exit;
            }
            $notice = 'Checkout gagal diproses: ' . htmlspecialchars($result['error'] ?? 'Kesalahan sistem.');
            $noticeType = 'error';
        } else {
            lms_process_checkout_failed($pdo, (int)$user['id'], $orderNumber);
            $notice = 'Pembayaran tidak berhasil. Role akun Anda tetap User Free. Silakan coba lagi.';
            $noticeType = 'error';
        }
    }
}

render_lms_header($brand, $user, 'catalog');
?>
<div style="max-width:560px;margin:0 auto;">
  <div style="text-align:center;margin-bottom:28px;">
    <span class="role-pill badge-paid">CHECKOUT ECOURSE PREMIUM</span>
    <h1 style="font-size:26px;font-weight:800;margin:12px 0 8px;"><?= htmlspecialchars($course['title']) ?></h1>
    <p style="color:var(--muted);font-size:14px;"><?= htmlspecialchars($course['summary'] ?? '') ?></p>
  </div>

  <?php if ($notice): ?>
    <div style="background:<?= $noticeType === 'error' ? 'rgba(239,68,68,0.1)' : 'rgba(59,130,246,0.1)' ?>;border:1px solid <?= $noticeType === 'error' ? 'rgba(239,68,68,0.3)' : 'rgba(59,130,246,0.3)' ?>;color:<?= $noticeType === 'error' ? '#fecaca' : '#bfdbfe' ?>;padding:14px 16px;border-radius:12px;margin-bottom:20px;font-size:13.5px;">
      <?= $notice ?>
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['locked'])): ?>
    <div style="background:rgba(214,165,54,0.1);border:1px solid var(--border-gold);color:var(--gold-soft);padding:14px 16px;border-radius:12px;margin-bottom:20px;font-size:13.5px;">
      Materi yang Anda coba akses memerlukan status Paid User. Selesaikan checkout di bawah untuk membuka akses.
    </div>
  <?php endif; ?>

  <div style="background:var(--surface);border:1px solid var(--border-gold);border-radius:22px;padding:28px;box-shadow:0 18px 48px rgba(0,0,0,0.35);">
    <div style="display:flex;justify-content:space-between;align-items:center;padding-bottom:18px;border-bottom:1px solid rgba(255,255,255,0.08);margin-bottom:18px;">
      <span style="color:var(--muted);font-size:14px;">Total Pembayaran</span>
      <span style="font-size:26px;font-weight:800;color:var(--gold-soft);">Rp <?= number_format((int)$course['price'], 0, ',', '.') ?></span>
    </div>

    <div style="font-size:13px;color:var(--muted);line-height:1.6;margin-bottom:22px;">
      <div>✓ Akses penuh seluruh materi premium course ini</div>
      <div>✓ Role akun otomatis di-upgrade menjadi <strong style="color:var(--gold-soft);">Paid User</strong></div>
      <div>✓ Notifikasi email aktivasi dikirim otomatis setelah pembayaran sukses</div>
      <div>✓ Progress tracking, kuis, dan sertifikat langsung aktif</div>
    </div>

    <div style="background:rgba(255,255,255,0.02);border:1px dashed var(--border-soft);border-radius:12px;padding:14px;margin-bottom:22px;font-size:12px;color:var(--muted);">
      Simulator checkout demo — pilih hasil transaksi untuk menguji alur update role otomatis. Integrasi payment gateway riil (Midtrans/Xendit) dapat dipasang menggantikan tombol simulasi ini tanpa mengubah logic backend RBAC.
    </div>

    <form method="POST" style="display:grid;gap:12px;">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['lms_csrf_token']) ?>">
      <button type="submit" name="action" value="pay_success" class="btn-lms btn-lms-gold" style="width:100%;height:50px;font-size:14.5px;">
        ✓ Simulasikan Pembayaran Berhasil
      </button>
      <button type="submit" name="action" value="pay_failed" class="btn-lms btn-lms-ghost" style="width:100%;height:46px;">
        ✕ Simulasikan Pembayaran Gagal
      </button>
    </form>
  </div>

  <p style="text-align:center;font-size:12px;color:var(--muted);margin-top:18px;">
    <a href="/course/' . urlencode($slug) ?>" style="color:var(--muted);">← Batalkan dan kembali ke detail eCourse</a>
  </p>
</div>
<?php render_lms_footer($brand); ?>
