<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
start_secure_session();

$brand = require_admin_for_brand(get_current_brand());

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pdo = get_db();

$eventSlug = clean($_GET['event'] ?? '');
$event = ($eventSlug !== '' && is_valid_event_slug($eventSlug)) ? get_event_by_slug($eventSlug) : null;
if ($event && (int)$event['brand_id'] !== (int)$brand['id']) {
    $event = null;
}
$eventNotFound = !$event;
$notice = null;
$noticeType = 'success';
$fieldErrors = [];

if (!$eventNotFound && isset($_GET['saved'])) {
    $notice = 'Pengaturan email berhasil disimpan.';
    $noticeType = 'success';
}

$stmt = $eventNotFound ? null : $pdo->prepare('SELECT * FROM event_email_settings WHERE brand_id = ? AND event_slug = ?');
if ($stmt) {
    $stmt->execute([(int)$brand['id'], $eventSlug]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $settings = null;
}

$formValues = $settings ?: [
    'subject' => 'Info Acara: ' . ($event['name'] ?? ''),
    'body_content' => "Halo {{nama}},\n\nTerima kasih sudah mendaftar di acara {{event_name}}.\n\nHari/Tanggal: {{event_day}}\nWaktu: {{event_time}}\nLokasi: {{event_location}}\n\nJangan lupa hadir ya!",
    'invitation_link' => '',
    'cta_text' => 'Gabung ke Acara Sekarang',
    'mailketing_list_id' => '',
    'auto_send' => 1,
];

if (!$eventNotFound && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $notice = 'Sesi tidak valid. Silakan refresh halaman lalu coba lagi.';
        $noticeType = 'error';
    } else {
        $formValues['subject'] = trim(clean($_POST['subject'] ?? ''));
        $formValues['body_content'] = trim($_POST['body_content'] ?? '');
        $formValues['invitation_link'] = trim(clean($_POST['invitation_link'] ?? ''));
        $formValues['cta_text'] = trim(clean($_POST['cta_text'] ?? '')) ?: 'Gabung ke Acara Sekarang';
        $formValues['mailketing_list_id'] = trim(clean($_POST['mailketing_list_id'] ?? ''));
        $formValues['auto_send'] = isset($_POST['auto_send']) ? 1 : 0;

        if ($formValues['subject'] === '') {
            $fieldErrors['subject'] = 'Subjek email wajib diisi.';
        }
        if ($formValues['body_content'] === '') {
            $fieldErrors['body_content'] = 'Isi email wajib diisi.';
        }
        if ($formValues['invitation_link'] !== '' && !filter_var($formValues['invitation_link'], FILTER_VALIDATE_URL)) {
            $fieldErrors['invitation_link'] = 'Link invitation harus berupa URL yang valid (contoh: https://zoom.us/j/xxxx).';
        }
        if ($formValues['mailketing_list_id'] !== '' && !ctype_digit($formValues['mailketing_list_id'])) {
            $fieldErrors['mailketing_list_id'] = 'List ID tidak valid.';
        }

        if (!empty($fieldErrors)) {
            $notice = 'Mohon periksa kembali data yang ditandai.';
            $noticeType = 'error';
        } else {
            try {
                $stmt = $pdo->prepare('
                    INSERT INTO event_email_settings
                        (brand_id, event_slug, subject, body_content, invitation_link, cta_text, mailketing_list_id, auto_send)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        subject = VALUES(subject),
                        body_content = VALUES(body_content),
                        invitation_link = VALUES(invitation_link),
                        cta_text = VALUES(cta_text),
                        mailketing_list_id = VALUES(mailketing_list_id),
                        auto_send = VALUES(auto_send)
                ');
                $stmt->execute([
                    (int)$brand['id'],
                    $eventSlug,
                    $formValues['subject'],
                    $formValues['body_content'],
                    $formValues['invitation_link'] ?: null,
                    $formValues['cta_text'],
                    $formValues['mailketing_list_id'] ?: null,
                    $formValues['auto_send'],
                ]);

                header('Location: email-settings.php?event=' . urlencode($eventSlug) . '&saved=1');
                exit;
            } catch (Exception $e) {
                $notice = 'Pengaturan belum bisa disimpan. Mohon periksa input dan coba lagi.';
                $noticeType = 'error';
            }
        }
    }
}

$pageTitle = $eventNotFound ? 'Event Tidak Ditemukan' : 'Pengaturan Email - ' . $event['name'];
$previewBrandName = $brand['name'] ?? $brand['slug'];
$previewLogoPath = !empty($brand['logo_path']) ? '..' . $brand['logo_path'] : '';
$previewDisclaimer = $brand['disclaimer_text'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle) ?></title>
<style>
  <?= get_theme_css_vars($brand) ?>
  body { min-height: 100vh; margin: 0; background: #0B0B0A; color: #F7F3E8; font-family: Inter, Arial, sans-serif; }
  .es-wrap { max-width: 1050px; margin: 0 auto; padding: 24px 20px 60px; }
  .es-hero { display: flex; flex-wrap: wrap; gap: 16px; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; }
  .es-hero h1 { font-size: 26px; margin: 4px 0 6px; color: var(--text-strong, #F5F1E6); }
  .es-hero p { color: var(--text-muted, #B8B2A4); margin: 0; font-size: 14px; }
  .es-eyebrow { font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: var(--brand-primary, #C9A84C); }
  .es-notice { border-radius: 16px; padding: 14px 18px; margin-bottom: 20px; font-size: 14px; }
  .es-notice.success { background: rgba(76,201,130,0.12); border: 1px solid rgba(76,201,130,0.3); color: #9fe8bb; }
  .es-notice.error { background: rgba(224,138,138,0.12); border: 1px solid rgba(224,138,138,0.3); color: #f0b3b3; }
  .es-grid { display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 20px; align-items: start; }
  @media (max-width: 900px) { .es-grid { grid-template-columns: 1fr; } }
  .es-panel { background: rgba(255,255,255,0.03); border: 1px solid rgba(201,168,76,0.25); border-radius: 24px; padding: 24px; }
  .es-field { margin-bottom: 18px; }
  .es-field label { font-size: 13px; color: var(--text-muted, #B8B2A4); display: block; margin-bottom: 8px; }
  .es-hint { font-size: 11.5px; color: #8a8a8a; margin-top: 6px; line-height: 1.5; }
  .es-panel input[type=text], .es-panel textarea, .es-panel select { width: 100%; border-radius: 14px; padding: 12px 14px; background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.12); color: #fff; font-size: 14px; box-sizing: border-box; font-family: inherit; }
  .es-panel textarea { min-height: 160px; resize: vertical; line-height: 1.5; }
  .es-field-error { color: #e08a8a; font-size: 12px; margin-top: 6px; }
  .es-toggle-row { display: flex; align-items: center; gap: 10px; margin-bottom: 18px; }
  .es-btn { border: none; border-radius: 14px; padding: 12px 22px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
  .es-btn-primary { background: linear-gradient(135deg, var(--brand-primary,#C9A84C), var(--brand-soft,#E8D5A3)); color: #1A1A1A; }
  .es-btn-ghost { background: transparent; border: 1px solid rgba(255,255,255,0.2); color: #fff; }
  .es-btn-secondary { background: rgba(255,255,255,0.08); color: #fff; border: 1px solid rgba(255,255,255,0.15); font-size: 12px; padding: 8px 14px; border-radius: 10px; cursor: pointer; }
  .es-preview-title { font-size: 13px; color: var(--text-muted,#B8B2A4); margin-bottom: 12px; }
  .es-email-card { background: #1a1a1a; border-radius: 16px; border: 1px solid rgba(201,168,76,0.25); overflow: hidden; }
  .es-email-header { background: #141414; padding: 20px 28px; color: #fff; font-weight: 700; font-size: 15px; min-height: 40px; display: flex; align-items: center; }
  .es-email-header img { display: block; max-height: 40px; max-width: 180px; object-fit: contain; }
  .es-email-brand-name { font-size: 20px; font-weight: 700; color: #fff; }
  .es-email-subject { padding: 12px 20px; font-size: 13px; color: #999; border-bottom: 1px solid rgba(255,255,255,0.08); }
  .es-email-body { padding: 20px; color: #eaeaea; font-size: 13.5px; line-height: 1.6; white-space: pre-line; min-height: 100px; }
  .es-email-cta { display: flex; justify-content: center; flex-wrap: wrap; gap: 10px; padding: 0 20px 20px; }
  .es-email-cta .es-email-button { display: inline-flex; align-items: center; gap: 7px; background: var(--brand-primary,#C9A84C); color: #111; font-weight: 700; padding: 10px 18px; border-radius: 10px; font-size: 13px; }
  .es-email-cta .es-email-button.alarm { background: #2b2b2b; border: 1px solid var(--brand-primary,#C9A84C); color: #f5f5f5; }
  .es-email-footer { background: #141414; padding: 14px 20px; color: #777; font-size: 10.5px; line-height: 1.5; }
  .es-empty { text-align: center; padding: 40px 20px; color: var(--text-muted,#B8B2A4); }
</style>
</head>
<body>
<div class="es-wrap">
  <?php if ($eventNotFound): ?>
    <div class="es-panel es-empty">
      <p>Event tidak ditemukan. Silakan kembali ke daftar event.</p>
      <a class="es-btn es-btn-primary" href="events.php">Kembali ke Kelola Event</a>
    </div>
  <?php else: ?>
    <div class="es-hero">
      <div>
        <span class="es-eyebrow">Pengaturan Email</span>
        <h1><?= htmlspecialchars($event['name']) ?></h1>
        <p>Atur email otomatis yang dikirim saat pendaftar baru masuk.</p>
      </div>
      <a class="es-btn es-btn-ghost" href="events.php">Kembali ke Event</a>
    </div>

    <?php if ($notice): ?>
      <div class="es-notice <?= htmlspecialchars($noticeType) ?>"><?= htmlspecialchars($notice) ?></div>
    <?php endif; ?>

    <form method="POST" class="es-grid" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

      <div class="es-panel">
        <div class="es-field">
          <label for="es-subject">Subjek Email</label>
          <input type="text" id="es-subject" name="subject" maxlength="200" value="<?= htmlspecialchars($formValues['subject']) ?>">
          <?php if (isset($fieldErrors['subject'])): ?><div class="es-field-error"><?= htmlspecialchars($fieldErrors['subject']) ?></div><?php endif; ?>
        </div>

        <div class="es-field">
          <label for="es-body">Isi Email</label>
          <textarea id="es-body" name="body_content"><?= htmlspecialchars($formValues['body_content']) ?></textarea>
          <div class="es-hint">Placeholder yang bisa dipakai: <code>{{nama}}</code>, <code>{{event_name}}</code>, <code>{{event_day}}</code>, <code>{{event_time}}</code>, <code>{{event_location}}</code>, <code>{{event_speaker}}</code>, <code>{{invitation_link}}</code></div>
          <?php if (isset($fieldErrors['body_content'])): ?><div class="es-field-error"><?= htmlspecialchars($fieldErrors['body_content']) ?></div><?php endif; ?>
        </div>

        <div class="es-field">
          <label for="es-link">Link Invitation (Zoom/Meet/akses acara)</label>
          <input type="text" id="es-link" name="invitation_link" placeholder="https://zoom.us/j/xxxxxxxxxx" value="<?= htmlspecialchars($formValues['invitation_link']) ?>">
          <?php if (isset($fieldErrors['invitation_link'])): ?><div class="es-field-error"><?= htmlspecialchars($fieldErrors['invitation_link']) ?></div><?php endif; ?>
        </div>

        <div class="es-field">
          <label for="es-cta">Teks Tombol CTA</label>
          <input type="text" id="es-cta" name="cta_text" maxlength="100" value="<?= htmlspecialchars($formValues['cta_text']) ?>">
        </div>

        <div class="es-field">
          <label for="es-list">List Mailketing (opsional)</label>
          <select id="es-list" name="mailketing_list_id">
            <option value="">Tidak menambahkan ke list</option>
            <?php if (!empty($formValues['mailketing_list_id'])): ?>
              <option value="<?= htmlspecialchars($formValues['mailketing_list_id']) ?>" selected><?= htmlspecialchars($formValues['mailketing_list_id']) ?> (tersimpan)</option>
            <?php endif; ?>
          </select>
          <button type="button" class="es-btn-secondary" id="es-refresh-list" style="margin-top:8px;">Muat Ulang Daftar List</button>
          <?php if (isset($fieldErrors['mailketing_list_id'])): ?><div class="es-field-error"><?= htmlspecialchars($fieldErrors['mailketing_list_id']) ?></div><?php endif; ?>
        </div>

        <div class="es-toggle-row">
          <input type="checkbox" id="es-auto" name="auto_send" <?= $formValues['auto_send'] ? 'checked' : '' ?>>
          <label for="es-auto" style="margin:0;">Kirim email otomatis saat ada pendaftar baru</label>
        </div>

        <button type="submit" class="es-btn es-btn-primary">Simpan Pengaturan</button>
      </div>

      <div class="es-panel">
        <div class="es-preview-title">Preview Email (contoh data)</div>
        <div class="es-email-card">
          <div class="es-email-header">
            <?php if ($previewLogoPath !== ''): ?>
              <img src="<?= htmlspecialchars($previewLogoPath) ?>" alt="<?= htmlspecialchars($previewBrandName) ?>">
            <?php else: ?>
              <span class="es-email-brand-name"><?= htmlspecialchars($previewBrandName) ?></span>
            <?php endif; ?>
          </div>
          <div class="es-email-subject" id="es-preview-subject"></div>
          <div class="es-email-body" id="es-preview-body"></div>
          <div class="es-email-cta">
            <span class="es-email-button"><span aria-hidden="true">&#128279;</span><span id="es-preview-cta"></span></span>
            <span class="es-email-button alarm"><span aria-hidden="true">&#128197;</span><span>Set Alarm</span></span>
          </div>
          <div class="es-email-footer"><?= htmlspecialchars($previewDisclaimer) ?></div>
        </div>
      </div>
    </form>
  <?php endif; ?>
</div>

<script>
(function () {
  const subjectInput = document.getElementById('es-subject');
  const bodyInput = document.getElementById('es-body');
  const ctaInput = document.getElementById('es-cta');
  const previewSubject = document.getElementById('es-preview-subject');
  const previewBody = document.getElementById('es-preview-body');
  const previewCta = document.getElementById('es-preview-cta');
  const listSelect = document.getElementById('es-list');
  const refreshBtn = document.getElementById('es-refresh-list');

  if (!subjectInput || !bodyInput || !ctaInput || !previewSubject || !previewBody || !previewCta || !listSelect || !refreshBtn) {
    return;
  }

  const sampleData = {
    '{{nama}}': 'Budi Santoso',
    '{{event_name}}': <?= json_encode($event['name'] ?? '') ?>,
    '{{event_day}}': <?= json_encode($event['event_day'] ?? 'Jumat, 3 Juli 2026') ?>,
    '{{event_time}}': <?= json_encode($event['event_time'] ?? '19.45 WIB') ?>,
    '{{event_location}}': <?= json_encode($event['event_location'] ?? 'Online via Zoom') ?>,
    '{{event_speaker}}': <?= json_encode($event['event_speaker'] ?? '') ?>,
    '{{invitation_link}}': 'https://zoom.us/j/xxxxxxxx',
  };

  function applyPlaceholders(text) {
    let result = text;
    Object.keys(sampleData).forEach(function (key) {
      result = result.split(key).join(sampleData[key]);
    });
    return result;
  }

  function updatePreview() {
    previewSubject.textContent = subjectInput.value || '(subjek kosong)';
    previewBody.textContent = applyPlaceholders(bodyInput.value || '');
    previewCta.textContent = ctaInput.value || 'Gabung ke Acara Sekarang';
  }

  [subjectInput, bodyInput, ctaInput].forEach(function (el) {
    el.addEventListener('input', updatePreview);
  });
  updatePreview();

  refreshBtn.addEventListener('click', async function () {
    refreshBtn.disabled = true;
    refreshBtn.textContent = 'Memuat...';
    try {
      const res = await fetch('../api/mailketing_get_lists.php');
      const result = await res.json();
      if (result.success && Array.isArray(result.lists)) {
        const currentValue = listSelect.value;
        listSelect.replaceChildren();
        const emptyOpt = document.createElement('option');
        emptyOpt.value = '';
        emptyOpt.textContent = 'Tidak menambahkan ke list';
        listSelect.appendChild(emptyOpt);
        result.lists.forEach(function (item) {
          const opt = document.createElement('option');
          opt.value = item.list_id ?? item.id ?? '';
          opt.textContent = (item.list_name ?? item.name ?? 'List') + ' (#' + opt.value + ')';
          if (opt.value === currentValue) opt.selected = true;
          listSelect.appendChild(opt);
        });
      } else {
        alert(result.message || 'Gagal memuat daftar list.');
      }
    } catch (err) {
      alert('Gagal terhubung ke server.');
    } finally {
      refreshBtn.disabled = false;
      refreshBtn.textContent = 'Muat Ulang Daftar List';
    }
  });
})();
</script>
</body>
</html>
