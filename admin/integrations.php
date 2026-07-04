<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
start_secure_session();

$brand = require_admin_for_brand(get_current_brand());

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pdo = get_db();
$notice = null;
$noticeType = 'success';
$fieldErrors = [];

$formValues = [
    'sender_name'  => $brand['sender_name'] ?? '',
    'sender_email' => $brand['sender_email'] ?? '',
];

if (isset($_GET['saved'])) {
    $notice = 'Pengaturan integrasi email berhasil disimpan.';
    $noticeType = 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $notice = 'Sesi tidak valid. Silakan refresh halaman lalu coba lagi.';
        $noticeType = 'error';
    } else {
        $formValues['sender_name'] = trim(clean($_POST['sender_name'] ?? ''));
        $formValues['sender_email'] = trim(clean($_POST['sender_email'] ?? ''));

        if ($formValues['sender_name'] === '' || mb_strlen($formValues['sender_name']) < 2) {
            $fieldErrors['sender_name'] = 'Nama pengirim wajib diisi, minimal 2 karakter.';
        }
        if ($formValues['sender_email'] === '' || !filter_var($formValues['sender_email'], FILTER_VALIDATE_EMAIL)) {
            $fieldErrors['sender_email'] = 'Email pengirim tidak valid.';
        }

        $brandDomainRoot = preg_replace('/^www\./', '', strtolower($brand['domain']));
        $senderDomain = strtolower(substr(strrchr($formValues['sender_email'], '@') ?: '', 1));
        if (empty($fieldErrors['sender_email']) && $senderDomain !== $brandDomainRoot) {
            $fieldErrors['sender_email'] = 'Email pengirim harus menggunakan domain ' . htmlspecialchars($brandDomainRoot) . ' (contoh: info@' . htmlspecialchars($brandDomainRoot) . ').';
        }

        if (!empty($fieldErrors)) {
            $notice = 'Mohon periksa kembali data yang ditandai.';
            $noticeType = 'error';
        } else {
            try {
                $stmt = $pdo->prepare('UPDATE brands SET sender_name = ?, sender_email = ? WHERE id = ?');
                $stmt->execute([$formValues['sender_name'], $formValues['sender_email'], (int)$brand['id']]);

                header('Location: integrations.php?saved=1');
                exit;
            } catch (Exception $e) {
                $notice = 'Pengaturan belum bisa disimpan. Mohon periksa input dan coba lagi.';
                $noticeType = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pengaturan Integrasi - <?= htmlspecialchars($brand['name']) ?></title>
<style>
  <?= get_theme_css_vars($brand) ?>
  body { min-height: 100vh; margin: 0; background: #0B0B0A; color: #F7F3E8; font-family: Inter, Arial, sans-serif; }
  .ig-wrap { max-width: 720px; margin: 0 auto; padding: 24px 20px 60px; }
  .ig-hero { margin-bottom: 24px; }
  .ig-hero h1 { font-size: 26px; margin: 4px 0 6px; color: var(--text-strong, #F5F1E6); }
  .ig-hero p { color: var(--text-muted, #B8B2A4); margin: 0; font-size: 14px; }
  .ig-eyebrow { font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: var(--brand-primary, #C9A84C); }
  .ig-notice { border-radius: 16px; padding: 14px 18px; margin-bottom: 20px; font-size: 14px; }
  .ig-notice.success { background: rgba(76,201,130,0.12); border: 1px solid rgba(76,201,130,0.3); color: #9fe8bb; }
  .ig-notice.error { background: rgba(224,138,138,0.12); border: 1px solid rgba(224,138,138,0.3); color: #f0b3b3; }
  .ig-panel { background: rgba(255,255,255,0.03); border: 1px solid rgba(201,168,76,0.25); border-radius: 24px; padding: 24px; margin-bottom: 20px; }
  .ig-panel-title { display: flex; align-items: center; gap: 10px; margin-bottom: 18px; }
  .ig-panel-title h2 { font-size: 16px; margin: 0; color: #fff; }
  .ig-panel-title p { font-size: 12.5px; color: var(--text-muted,#B8B2A4); margin: 2px 0 0; }
  .ig-field { margin-bottom: 18px; }
  .ig-field label { font-size: 13px; color: var(--text-muted, #B8B2A4); display: block; margin-bottom: 8px; }
  .ig-field input[type=text] { width: 100%; border-radius: 14px; padding: 12px 14px; background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.12); color: #fff; font-size: 14px; box-sizing: border-box; font-family: inherit; }
  .ig-field-error { color: #e08a8a; font-size: 12px; margin-top: 6px; }
  .ig-hint { font-size: 11.5px; color: #8a8a8a; margin-top: 6px; line-height: 1.5; }
  .ig-preview-box { display: flex; align-items: center; gap: 10px; background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 12px 16px; margin-bottom: 18px; }
  .ig-preview-avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, var(--brand-primary,#C9A84C), var(--brand-soft,#E8D5A3)); display: flex; align-items: center; justify-content: center; font-weight: 700; color: #1A1A1A; font-size: 14px; flex-shrink: 0; }
  .ig-preview-text .name { color: #fff; font-size: 13.5px; font-weight: 600; }
  .ig-preview-text .email { color: #999; font-size: 12px; }
  .ig-btn { border: none; border-radius: 14px; padding: 12px 22px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
  .ig-btn-primary { background: linear-gradient(135deg, var(--brand-primary,#C9A84C), var(--brand-soft,#E8D5A3)); color: #1A1A1A; }
  .ig-btn-ghost { background: transparent; border: 1px solid rgba(255,255,255,0.2); color: #fff; margin-top: 16px; }
</style>
</head>
<body>
<div class="ig-wrap">
  <div class="ig-hero">
    <span class="ig-eyebrow">Pengaturan Integrasi</span>
    <h1>Identitas Pengirim Email - <?= htmlspecialchars($brand['name']) ?></h1>
    <p>Atur nama dan email pengirim yang tampil di inbox pendaftar untuk brand ini.</p>
    <a class="ig-btn ig-btn-ghost" href="dashboard.php">Kembali ke Dashboard</a>
  </div>

  <?php if ($notice): ?>
    <div class="ig-notice <?= htmlspecialchars($noticeType) ?>"><?= htmlspecialchars($notice) ?></div>
  <?php endif; ?>

  <form method="POST" class="ig-panel" novalidate id="ig-form">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <div class="ig-panel-title">
      <div>
        <h2>Email Sender (Mailketing)</h2>
        <p>Digunakan setiap kali sistem mengirim email otomatis ke pendaftar brand ini.</p>
      </div>
    </div>

    <div class="ig-preview-box" id="ig-preview">
      <div class="ig-preview-avatar" id="ig-preview-avatar">?</div>
      <div class="ig-preview-text">
        <div class="name" id="ig-preview-name">Nama Pengirim</div>
        <div class="email" id="ig-preview-email">email@domain.id</div>
      </div>
    </div>

    <div class="ig-field">
      <label for="ig-sender-name">Nama Pengirim</label>
      <input type="text" id="ig-sender-name" name="sender_name" maxlength="150" value="<?= htmlspecialchars($formValues['sender_name']) ?>" placeholder="Contoh: Tim RahasiaEmas.id">
      <?php if (isset($fieldErrors['sender_name'])): ?><div class="ig-field-error"><?= htmlspecialchars($fieldErrors['sender_name']) ?></div><?php endif; ?>
    </div>

    <div class="ig-field">
      <label for="ig-sender-email">Email Pengirim</label>
      <input type="text" id="ig-sender-email" name="sender_email" maxlength="150" value="<?= htmlspecialchars($formValues['sender_email']) ?>" placeholder="info@<?= htmlspecialchars(preg_replace('/^www\./', '', $brand['domain'])) ?>">
      <div class="ig-hint">Wajib menggunakan domain <?= htmlspecialchars(preg_replace('/^www\./', '', $brand['domain'])) ?>. Email dengan domain lain akan ditolak untuk mencegah salah kirim antar brand.</div>
      <?php if (isset($fieldErrors['sender_email'])): ?><div class="ig-field-error"><?= htmlspecialchars($fieldErrors['sender_email']) ?></div><?php endif; ?>
    </div>

    <button type="submit" class="ig-btn ig-btn-primary">Simpan Pengaturan</button>
  </form>
</div>

<script>
(function () {
  const nameInput = document.getElementById('ig-sender-name');
  const emailInput = document.getElementById('ig-sender-email');
  const previewName = document.getElementById('ig-preview-name');
  const previewEmail = document.getElementById('ig-preview-email');
  const previewAvatar = document.getElementById('ig-preview-avatar');

  function updatePreview() {
    const name = nameInput.value.trim();
    const email = emailInput.value.trim();
    previewName.textContent = name || 'Nama Pengirim';
    previewEmail.textContent = email || 'email@domain.id';
    previewAvatar.textContent = name ? name.charAt(0).toUpperCase() : '?';
  }

  nameInput.addEventListener('input', updatePreview);
  emailInput.addEventListener('input', updatePreview);
  updatePreview();
})();
</script>
</body>
</html>
