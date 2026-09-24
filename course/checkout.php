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
$paymentSettings = lms_get_payment_settings($pdo, $brand);
$selectedMethod = $paymentSettings['active_method'] === 'midtrans' && (int)$paymentSettings['midtrans_enabled'] === 1 ? 'midtrans' : 'bank_transfer';
$createdOrder = null;

$stmtPending = $pdo->prepare('
    SELECT * FROM lms_orders
    WHERE brand_id = ? AND user_id = ? AND course_id = ? AND payment_status = "pending"
    ORDER BY created_at DESC
    LIMIT 1
');
$stmtPending->execute([$brandId, (int)$user['id'], $courseId]);
$pendingOrder = $stmtPending->fetch(PDO::FETCH_ASSOC) ?: null;

// ==== CHECKOUT PAYMENT PREPARATION ====
// Midtrans Snap sudah disiapkan sebagai opsi, tetapi belum membuat transaksi riil.
// Transfer via Bank membuat order pending untuk ditindaklanjuti manual oleh admin.
// Status paid tetap hanya boleh dipicu integrasi/webhook resmi saat gateway riil aktif.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $method = $_POST['payment_method'] ?? $selectedMethod;

    if (!hash_equals($_SESSION['lms_csrf_token'], $_POST['csrf_token'] ?? '')) {
        $notice = 'Sesi kedaluwarsa. Silakan refresh halaman.';
        $noticeType = 'error';
    } elseif ($action === 'upload_proof') {
        $orderNumber = trim(clean($_POST['order_number'] ?? ''));
        try {
            $proofPath = lms_store_payment_proof($_FILES['payment_proof'] ?? []);
            if (!lms_attach_payment_proof($pdo, (int)$user['id'], $orderNumber, $proofPath)) {
                $notice = 'Order pending tidak ditemukan atau sudah diproses.';
                $noticeType = 'error';
            } else {
                $notice = 'Bukti pembayaran berhasil diupload. Silakan klik tombol WhatsApp untuk konfirmasi ke admin.';
                $noticeType = 'info';
                $stmtPending->execute([$brandId, (int)$user['id'], $courseId]);
                $pendingOrder = $stmtPending->fetch(PDO::FETCH_ASSOC) ?: null;
            }
        } catch (Throwable $e) {
            $notice = $e->getMessage();
            $noticeType = 'error';
        }
    } elseif ($action !== 'create_order') {
        $notice = 'Aksi checkout tidak valid.';
        $noticeType = 'error';
    } elseif (!in_array($method, ['bank_transfer', 'midtrans'], true)) {
        $notice = 'Metode pembayaran tidak valid.';
        $noticeType = 'error';
    } elseif ($method === 'midtrans' && (int)$paymentSettings['midtrans_enabled'] !== 1) {
        $notice = 'Midtrans belum diaktifkan. Silakan gunakan Transfer via Bank.';
        $noticeType = 'error';
    } elseif ($method === 'bank_transfer' && (int)$paymentSettings['bank_transfer_enabled'] !== 1) {
        $notice = 'Transfer via Bank belum diaktifkan admin.';
        $noticeType = 'error';
    } else {
        $orderNumber = 'LMS-' . strtoupper(bin2hex(random_bytes(5)));
        $amount = (int)$course['price'];

        $stmtOrder = $pdo->prepare('
            INSERT INTO lms_orders (brand_id, user_id, course_id, order_number, amount, payment_method, payment_status)
            VALUES (?, ?, ?, ?, ?, ?, "pending")
        ');
        $stmtOrder->execute([$brandId, (int)$user['id'], $courseId, $orderNumber, $amount, $method]);

        $createdOrder = [
            'order_number' => $orderNumber,
            'amount' => $amount,
            'payment_method' => $method,
        ];
        $pendingOrder = $createdOrder + [
            'payment_proof_path' => null,
            'payment_proof_uploaded_at' => null,
        ];
        $selectedMethod = $method;
        $notice = $method === 'midtrans'
            ? 'Order pending berhasil dibuat. Midtrans Snap masih mode persiapan dan belum mengirim transaksi riil.'
            : 'Order pending berhasil dibuat. Silakan transfer sesuai instruksi bank di bawah.';
        $noticeType = 'info';
    }
}

render_lms_header($brand, $user, 'catalog');
?>
<div style="max-width:680px;margin:0 auto;">
  <h1 style="font-size:28px;font-weight:900;margin:0 0 22px;">Checkout</h1>

  <?php if ($notice): ?>
    <div style="background:<?= $noticeType === 'error' ? 'rgba(239,68,68,.10)' : 'rgba(34,197,94,.10)' ?>;border:1px solid <?= $noticeType === 'error' ? 'rgba(239,68,68,.28)' : 'rgba(34,197,94,.28)' ?>;color:<?= $noticeType === 'error' ? '#fecaca' : '#bbf7d0' ?>;padding:13px 15px;border-radius:12px;margin-bottom:16px;font-size:13px;">
      <?= htmlspecialchars($notice) ?>
    </div>
  <?php endif; ?>

  <!-- 1. Produk -->
  <section style="display:grid;grid-template-columns:112px 1fr;gap:18px;align-items:center;background:var(--surface);border:1px solid var(--border-soft);border-radius:18px;padding:18px;margin-bottom:14px;">
    <?php if (!empty($course['cover_image'])): ?>
      <img src="<?= htmlspecialchars($course['cover_image']) ?>" alt="<?= htmlspecialchars($course['title']) ?>" style="width:112px;height:88px;object-fit:cover;border-radius:12px;">
    <?php else: ?>
      <div style="width:112px;height:88px;display:grid;place-items:center;border-radius:12px;background:linear-gradient(135deg,var(--gold),var(--gold-soft));color:#111;font-size:34px;font-weight:900;">▶</div>
    <?php endif; ?>
    <div>
      <div style="font-size:11px;font-weight:800;color:var(--gold-soft);text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px;">eCourse Premium</div>
      <h2 style="font-size:17px;line-height:1.4;margin:0 0 8px;"><?= htmlspecialchars($course['title']) ?></h2>
      <strong style="font-size:21px;color:var(--gold-soft);">Rp <?= number_format((int)$course['price'], 0, ',', '.') ?></strong>
    </div>
  </section>

  <!-- 2. Pembayaran -->
  <section style="background:var(--surface);border:1px solid var(--border-soft);border-radius:18px;padding:20px;margin-bottom:14px;">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:16px;">
      <h2 style="font-size:17px;margin:0;">Informasi Pembayaran</h2>
      <?php if (!empty($paymentSettings['bank_logo_path'])): ?>
        <img src="<?= htmlspecialchars($paymentSettings['bank_logo_path']) ?>" alt="Logo bank" style="height:34px;max-width:110px;object-fit:contain;background:#fff;border-radius:8px;padding:4px 8px;">
      <?php endif; ?>
    </div>

    <?php if ($selectedMethod === 'bank_transfer'): ?>
      <div style="background:rgba(214,165,54,.09);border-left:3px solid var(--gold);border-radius:10px;padding:12px 14px;margin-bottom:16px;font-size:13px;line-height:1.6;color:var(--text);">
        Silakan transfer ke rekening berikut. Pastikan nominal sesuai agar pembayaran mudah diverifikasi.
      </div>
      <div style="display:grid;gap:12px;font-size:13px;line-height:1.55;">
        <div>
          <div style="color:var(--muted);font-size:12px;margin-bottom:3px;">No. Order:</div>
          <code style="color:var(--text);font-size:14px;font-weight:700;"><?= htmlspecialchars($pendingOrder['order_number'] ?? 'Dibuat saat klik Buat Pesanan') ?></code>
        </div>
        <div>
          <div style="color:var(--muted);font-size:12px;margin-bottom:3px;">Total Transfer:</div>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;max-width:320px;">
            <strong style="font-size:18px;color:var(--gold-soft);">Rp <?= number_format((int)$course['price'], 0, ',', '.') ?></strong>
            <button type="button" class="copy-payment-value" data-copy="<?= (int)$course['price'] ?>" style="border:1px solid var(--border-soft);background:rgba(255,255,255,.05);color:var(--text);border-radius:8px;padding:6px 10px;font:inherit;font-size:11px;font-weight:800;cursor:pointer;">Salin</button>
          </div>
        </div>
        <div>
          <div style="color:var(--muted);font-size:12px;margin-bottom:3px;">No. Rekening:</div>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;max-width:320px;">
            <strong style="font-size:16px;color:var(--gold-soft);"><?= htmlspecialchars($paymentSettings['bank_account_number'] ?: 'Belum diatur') ?></strong>
            <button type="button" class="copy-payment-value" data-copy="<?= htmlspecialchars($paymentSettings['bank_account_number'] ?: '') ?>" style="border:1px solid var(--border-soft);background:rgba(255,255,255,.05);color:var(--text);border-radius:8px;padding:6px 10px;font:inherit;font-size:11px;font-weight:800;cursor:pointer;">Salin</button>
          </div>
        </div>
        <div>
          <div style="color:var(--muted);font-size:12px;margin-bottom:3px;">Bank:</div>
          <strong style="font-size:14px;"><?= htmlspecialchars($paymentSettings['bank_name'] ?: 'Belum diatur') ?></strong>
        </div>
        <div>
          <div style="color:var(--muted);font-size:12px;margin-bottom:3px;">Atas Nama:</div>
          <strong style="font-size:14px;"><?= htmlspecialchars($paymentSettings['bank_account_name'] ?: 'Belum diatur') ?></strong>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!$pendingOrder): ?>
      <form method="POST" style="display:grid;gap:12px;margin-top:18px;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['lms_csrf_token']) ?>">
        <?php if ((int)$paymentSettings['midtrans_enabled'] === 1): ?>
          <select name="payment_method" style="height:46px;border-radius:12px;border:1px solid var(--border-soft);background:#111;color:var(--text);padding:0 12px;">
            <?php if ((int)$paymentSettings['bank_transfer_enabled'] === 1): ?><option value="bank_transfer">Transfer via Bank</option><?php endif; ?>
            <option value="midtrans">Midtrans</option>
          </select>
        <?php else: ?>
          <input type="hidden" name="payment_method" value="bank_transfer">
        <?php endif; ?>
        <button type="submit" name="action" value="create_order" class="btn-lms btn-lms-gold" style="width:100%;height:48px;">Buat Pesanan</button>
      </form>
    <?php endif; ?>
  </section>

  <!-- 3. Bukti Pembayaran -->
  <?php if ($pendingOrder && ($pendingOrder['payment_method'] ?? '') === 'bank_transfer'): ?>
    <?php $waLink = lms_admin_whatsapp_link($paymentSettings, (string)$pendingOrder['order_number'], (string)$course['title'], (int)$pendingOrder['amount']); ?>
    <section style="background:var(--surface);border:1px solid var(--border-gold);border-radius:18px;padding:20px;">
      <h2 style="font-size:17px;margin:0 0 6px;">Upload Bukti Pembayaran</h2>
      <p style="font-size:12px;color:var(--muted);margin:0 0 16px;">Transfer selesai? Kirim bukti agar admin segera memverifikasi.</p>

      <?php if (!empty($pendingOrder['payment_proof_path'])): ?>
        <div style="background:rgba(34,197,94,.10);border:1px solid rgba(34,197,94,.25);color:#bbf7d0;border-radius:12px;padding:12px;margin-bottom:14px;font-size:13px;">✓ Bukti pembayaran sudah terkirim.</div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data" style="display:grid;gap:10px;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['lms_csrf_token']) ?>">
        <input type="hidden" name="order_number" value="<?= htmlspecialchars($pendingOrder['order_number']) ?>">
        <input type="file" name="payment_proof" accept="image/png,image/jpeg,image/webp,application/pdf" required style="width:100%;padding:12px;border-radius:12px;background:#111;border:1px dashed var(--border-gold);color:var(--text);">
        <div style="font-size:11px;color:var(--muted);">PNG, JPG, WEBP, atau PDF • Maks. 5 MB</div>
        <button type="submit" name="action" value="upload_proof" class="btn-lms btn-lms-gold" style="width:100%;height:48px;">Upload Bukti</button>
      </form>

      <?php if ($waLink): ?>
        <a href="<?= htmlspecialchars($waLink) ?>" target="_blank" rel="noopener" class="btn-lms btn-lms-ghost" style="width:100%;height:46px;margin-top:10px;">Konfirmasi via WhatsApp</a>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <p style="text-align:center;font-size:12px;color:var(--muted);margin-top:16px;">
    <a href="/course/<?= urlencode($slug) ?>" style="color:var(--muted);">← Kembali ke detail course</a>
  </p>
</div>
<script>
(function () {
  async function copyText(value) {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(value);
      return;
    }
    const input = document.createElement('textarea');
    input.value = value;
    input.style.position = 'fixed';
    input.style.opacity = '0';
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    input.remove();
  }

  document.querySelectorAll('.copy-payment-value').forEach(button => {
    button.addEventListener('click', async function () {
      const original = button.textContent;
      try {
        await copyText(button.dataset.copy || '');
        button.textContent = 'Tersalin';
        button.style.color = '#86efac';
      } catch (error) {
        button.textContent = 'Gagal';
        button.style.color = '#fca5a5';
      }
      setTimeout(() => {
        button.textContent = original;
        button.style.color = 'var(--text)';
      }, 1500);
    });
  });
})();
</script>
<?php render_lms_footer($brand); ?>
