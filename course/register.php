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

if (!empty($_SESSION['lms_user_id'])) {
    header('Location: /course/my-courses.php');
    exit;
}

if (empty($_SESSION['lms_csrf_token'])) {
    $_SESSION['lms_csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$formValues = ['name' => '', 'email' => '', 'whatsapp' => ''];
$redirect = clean($_GET['redirect'] ?? '/course/my-courses.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['lms_csrf_token'], $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Sesi kedaluwarsa. Silakan refresh halaman dan coba lagi.';
    } else {
        $formValues['name'] = trim(clean($_POST['name'] ?? ''));
        $formValues['email'] = trim(strtolower(clean($_POST['email'] ?? '')));
        $formValues['whatsapp'] = trim(clean($_POST['whatsapp'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

        if (mb_strlen($formValues['name']) < 3) {
            $errors[] = 'Nama lengkap minimal 3 karakter.';
        }
        if (!filter_var($formValues['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email tidak valid.';
        }
        if (mb_strlen($formValues['whatsapp']) < 8) {
            $errors[] = 'Nomor WhatsApp tidak valid.';
        }
        if (mb_strlen($password) < 8) {
            $errors[] = 'Password minimal 8 karakter.';
        }
        if ($password !== $passwordConfirm) {
            $errors[] = 'Konfirmasi password tidak sama.';
        }

        if (empty($errors)) {
            $stmtCheck = $pdo->prepare('SELECT id FROM lms_users WHERE brand_id = ? AND email = ?');
            $stmtCheck->execute([$brandId, $formValues['email']]);
            if ($stmtCheck->fetchColumn()) {
                $errors[] = 'Email ini sudah terdaftar. Silakan login.';
            }
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare('
                    INSERT INTO lms_users (brand_id, name, email, whatsapp, password_hash, primary_role, status)
                    VALUES (?, ?, ?, ?, ?, "free", "active")
                ');
                $stmt->execute([
                    $brandId,
                    $formValues['name'],
                    $formValues['email'],
                    normalize_whatsapp($formValues['whatsapp']),
                    password_hash($password, PASSWORD_DEFAULT),
                ]);
                $newUserId = (int)$pdo->lastInsertId();

                // Assign role free secara resmi + log
                lms_assign_role($pdo, $newUserId, 'free', 'registration');
                lms_auto_enroll_free_courses($pdo, $brandId, $newUserId);

                lms_create_notification(
                    $pdo,
                    $newUserId,
                    'Selamat Datang di Simple LMS!',
                    'Akun Anda berhasil dibuat dengan akses Free. Mulai jelajahi eCourse gratis kami dan tingkatkan wawasan investasi emas Anda.'
                );

                session_regenerate_id(true);
                $_SESSION['lms_user_id'] = $newUserId;

                header('Location: ' . $redirect);
                exit;
            } catch (Throwable $e) {
                error_log('[LMS] Register error: ' . $e->getMessage());
                $errors[] = 'Pendaftaran gagal diproses. Silakan coba lagi.';
            }
        }
    }
}

render_lms_header($brand, null, 'register');
?>
<div style="max-width:460px;margin:0 auto;">
  <div style="text-align:center;margin-bottom:28px;">
    <h1 style="font-size:26px;font-weight:800;margin-bottom:8px;">Buat Akun Free Access</h1>
    <p style="color:var(--muted);font-size:14px;">Daftar gratis untuk mulai belajar eCourse dasar & lacak progress belajar Anda.</p>
  </div>

  <?php if (!empty($errors)): ?>
    <div style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);color:#fecaca;padding:14px 16px;border-radius:12px;margin-bottom:20px;font-size:13.5px;">
      <?php foreach ($errors as $err): ?><div>• <?= htmlspecialchars($err) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="POST" style="background:var(--surface);border:1px solid var(--border-soft);border-radius:20px;padding:28px;display:grid;gap:16px;box-shadow:0 16px 44px rgba(0,0,0,0.3);">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['lms_csrf_token']) ?>">

    <div>
      <label style="font-size:13px;font-weight:700;display:block;margin-bottom:6px;">Nama Lengkap</label>
      <input type="text" name="name" required value="<?= htmlspecialchars($formValues['name']) ?>" placeholder="Nama Anda" style="width:100%;height:46px;padding:0 14px;border-radius:12px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);color:var(--text);font:inherit;">
    </div>
    <div>
      <label style="font-size:13px;font-weight:700;display:block;margin-bottom:6px;">Email</label>
      <input type="email" name="email" required value="<?= htmlspecialchars($formValues['email']) ?>" placeholder="email@domain.com" style="width:100%;height:46px;padding:0 14px;border-radius:12px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);color:var(--text);font:inherit;">
    </div>
    <div>
      <label style="font-size:13px;font-weight:700;display:block;margin-bottom:6px;">Nomor WhatsApp</label>
      <input type="text" name="whatsapp" required value="<?= htmlspecialchars($formValues['whatsapp']) ?>" placeholder="08xxxxxxxxxx" style="width:100%;height:46px;padding:0 14px;border-radius:12px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);color:var(--text);font:inherit;">
    </div>
    <div>
      <label style="font-size:13px;font-weight:700;display:block;margin-bottom:6px;">Password</label>
      <input type="password" name="password" required minlength="8" placeholder="Minimal 8 karakter" style="width:100%;height:46px;padding:0 14px;border-radius:12px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);color:var(--text);font:inherit;">
    </div>
    <div>
      <label style="font-size:13px;font-weight:700;display:block;margin-bottom:6px;">Konfirmasi Password</label>
      <input type="password" name="password_confirm" required minlength="8" placeholder="Ulangi password" style="width:100%;height:46px;padding:0 14px;border-radius:12px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);color:var(--text);font:inherit;">
    </div>

    <button type="submit" class="btn-lms btn-lms-gold" style="width:100%;height:48px;margin-top:6px;">Daftar Sekarang — Gratis</button>

    <p style="text-align:center;font-size:13px;color:var(--muted);margin-top:6px;">
      Sudah punya akun? <a href="/course/login.php?redirect=<?= urlencode($redirect) ?>" style="color:var(--gold-soft);font-weight:700;">Login di sini</a>
    </p>
  </form>
</div>
<?php render_lms_footer($brand); ?>
