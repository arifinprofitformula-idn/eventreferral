<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/mailketing.php';
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
$emailSettingsSchemaReady = true;
$settings = null;
$testEmail = '';

if (!$eventNotFound && isset($_GET['saved'])) {
    $notice = 'Pengaturan email berhasil disimpan.';
    $noticeType = 'success';
}

if (!$eventNotFound) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM event_email_settings WHERE brand_id = ? AND event_slug = ?');
        $stmt->execute([(int)$brand['id'], $eventSlug]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $emailSettingsSchemaReady = false;
        $notice = 'Pengaturan email belum siap. Jalankan migrasi database Mailketing terlebih dahulu.';
        $noticeType = 'error';
        error_log('[Mailketing] Schema event_email_settings belum siap: ' . $e->getMessage());
    }
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
    $formAction = $_POST['form_action'] ?? 'save';

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $notice = 'Sesi tidak valid. Silakan refresh halaman lalu coba lagi.';
        $noticeType = 'error';
    } elseif (!$emailSettingsSchemaReady) {
        $notice = 'Pengaturan belum bisa diproses karena migrasi database Mailketing belum dijalankan.';
        $noticeType = 'error';
    } else {
        $formValues['subject'] = trim(clean($_POST['subject'] ?? ''));
        $formValues['body_content'] = trim($_POST['body_content'] ?? '');
        $formValues['invitation_link'] = trim(clean($_POST['invitation_link'] ?? ''));
        $formValues['cta_text'] = trim(clean($_POST['cta_text'] ?? '')) ?: 'Gabung ke Acara Sekarang';
        $formValues['mailketing_list_id'] = trim(clean($_POST['mailketing_list_id'] ?? ''));
        $formValues['auto_send'] = isset($_POST['auto_send']) ? 1 : 0;
        $testEmail = trim(clean($_POST['test_email'] ?? ''));

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
        if ($formAction === 'test' && ($testEmail === '' || !filter_var($testEmail, FILTER_VALIDATE_EMAIL))) {
            $fieldErrors['test_email'] = 'Masukkan email tujuan test yang valid.';
        }

        if (!empty($fieldErrors)) {
            $notice = 'Mohon periksa kembali data yang ditandai.';
            $noticeType = 'error';
        } elseif ($formAction === 'test') {
            try {
                $testSettings = $formValues;
                $html = build_invitation_email_html($brand, $event, $testSettings, 'Budi Santoso');
                $senderName = !empty($brand['sender_name']) ? $brand['sender_name'] : MAILKETING_SENDER_NAME;
                $senderEmail = !empty($brand['sender_email']) ? $brand['sender_email'] : MAILKETING_SENDER_EMAIL;
                mailketing_send_email($testEmail, '[TEST] ' . $formValues['subject'], $html, $senderName, $senderEmail);
                $notice = 'Test email berhasil dikirim ke ' . htmlspecialchars($testEmail) . '.';
                $noticeType = 'success';
            } catch (Throwable $e) {
                $notice = 'Test email belum berhasil dikirim: ' . htmlspecialchars($e->getMessage());
                $noticeType = 'error';
                error_log('[Mailketing] Gagal kirim test email: ' . $e->getMessage());
            }
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

$pageTitle = $eventNotFound ? 'Event Tidak Ditemukan' : 'Email Automation Studio - ' . $event['name'];
$previewBrandName = $brand['name'] ?? $brand['slug'];
$previewLogoPath = !empty($brand['logo_path']) ? '..' . $brand['logo_path'] : '../assets/logo.png';
$previewDisclaimer = $brand['disclaimer_text'] ?? '';
$logoPath = !empty($brand['logo_path']) ? '..' . $brand['logo_path'] : '../assets/logo.png';
$eventUrl = $eventNotFound ? '#' : (($event['slug'] === ($brand['default_event_slug'] ?? '')) ? '/' : EVENTS_URL_BASE . '/' . rawurlencode($event['slug']) . '/');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle) ?></title>
<style>
  <?= get_theme_css_vars($brand) ?>
  :root {
    --bg: #0B0B0A;
    --bg-soft: #10100F;
    --surface: #171716;
    --surface-elevated: #20201E;
    --border-gold: rgba(214,165,54,0.18);
    --gold: #D6A536;
    --gold-soft: #F4D27A;
    --text: #F7F3E8;
    --muted: #A8A29A;
    --success: #22C55E;
    --danger: #EF4444;
    --warning: #F59E0B;
    --border-soft: rgba(255,255,255,0.09);
  }
  * { box-sizing: border-box; }
  html { scroll-behavior: smooth; }
  body {
    min-height: 100vh;
    margin: 0;
    background:
      radial-gradient(circle at 84% 8%, rgba(214,165,54,0.22), transparent 28vw),
      radial-gradient(circle at 8% 86%, rgba(244,210,122,0.09), transparent 34vw),
      linear-gradient(135deg, var(--bg) 0%, var(--bg-soft) 52%, #070706 100%);
    color: var(--text);
    font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  }
  body::before {
    content: "";
    position: fixed;
    inset: 0;
    pointer-events: none;
    background-image:
      linear-gradient(rgba(255,255,255,0.024) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,0.016) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: radial-gradient(circle at 50% 16%, black, transparent 74%);
  }
  a { color: inherit; }
  .topbar {
    position: sticky;
    top: 0;
    z-index: 30;
    background: rgba(16,16,15,0.84);
    border-bottom: 1px solid rgba(214,165,54,0.14);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
  }
  .topbar-inner, .studio-wrap {
    width: min(100%, 1360px);
    margin: 0 auto;
    padding-left: 32px;
    padding-right: 32px;
  }
  .topbar-inner {
    min-height: 82px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
  }
  .brand-link { display: inline-flex; align-items: center; text-decoration: none; }
  .brand-link img { width: 146px; height: auto; display: block; filter: drop-shadow(0 10px 20px rgba(0,0,0,0.32)); }
  .nav { display: flex; align-items: center; justify-content: flex-end; gap: 10px; flex-wrap: wrap; }
  .nav a {
    color: var(--muted);
    display: inline-flex;
    align-items: center;
    border: 1px solid transparent;
    border-radius: 999px;
    font-size: 13.5px;
    font-weight: 750;
    line-height: 1;
    padding: 12px 15px;
    text-decoration: none;
    transition: background 180ms ease, color 180ms ease, border-color 180ms ease, transform 180ms ease;
  }
  .nav a:hover { color: var(--text); background: rgba(255,255,255,0.04); transform: translateY(-1px); }
  .nav a.active {
    color: var(--gold-soft);
    background: rgba(214,165,54,0.10);
    border-color: var(--border-gold);
    box-shadow: inset 0 -2px 0 rgba(244,210,122,0.38);
  }
  .nav .logout { border-color: rgba(255,255,255,0.10); background: rgba(255,255,255,0.035); }
  .studio-wrap { position: relative; padding-top: 28px; padding-bottom: 120px; }
  .hero {
    position: relative;
    overflow: hidden;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 24px;
    align-items: center;
    min-height: 230px;
    margin-bottom: 22px;
    padding: 34px 36px;
    border: 1px solid var(--border-gold);
    border-radius: 28px;
    background:
      radial-gradient(circle at 84% 28%, rgba(244,210,122,0.28), transparent 24%),
      linear-gradient(135deg, rgba(32,32,30,0.96), rgba(23,23,22,0.94) 58%, rgba(92,63,10,0.30));
    box-shadow: 0 24px 70px rgba(0,0,0,0.34);
  }
  .hero::after {
    content: "@";
    position: absolute;
    right: 58px;
    bottom: 22px;
    color: rgba(244,210,122,0.12);
    font-size: 190px;
    font-weight: 900;
    line-height: 1;
    transform: rotate(-16deg);
  }
  .hero-copy, .hero-actions, .hero-visual { position: relative; z-index: 1; }
  .breadcrumb { color: var(--muted); display: flex; gap: 9px; align-items: center; font-size: 12.5px; margin-bottom: 14px; }
  .breadcrumb strong { color: var(--text); }
  .badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    width: fit-content;
    color: var(--gold-soft);
    background: rgba(214,165,54,0.11);
    border: 1px solid var(--border-gold);
    border-radius: 999px;
    font-size: 11px;
    font-weight: 900;
    letter-spacing: .08em;
    text-transform: uppercase;
    padding: 8px 10px;
    margin-bottom: 12px;
  }
  h1 { margin: 0 0 10px; color: var(--text); font-size: clamp(30px, 4vw, 48px); line-height: 1.08; letter-spacing: 0; }
  h1 span { color: var(--gold); }
  .hero p { margin: 0; color: var(--muted); font-size: 15px; line-height: 1.7; max-width: 760px; }
  .hero-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 22px; }
  .hero-visual {
    display: grid;
    place-items: center;
    width: 162px;
    height: 162px;
    color: #16130b;
    background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    border-radius: 42px;
    box-shadow: 0 18px 50px rgba(214,165,54,0.24);
    transform: rotate(-7deg);
  }
  .hero-visual svg { width: 86px; height: 86px; }
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    min-height: 46px;
    border-radius: 14px;
    border: 1px solid transparent;
    cursor: pointer;
    font: inherit;
    font-size: 13.5px;
    font-weight: 850;
    padding: 12px 18px;
    text-decoration: none;
    white-space: nowrap;
    transition: transform 180ms ease, border-color 180ms ease, background 180ms ease, opacity 180ms ease;
  }
  .btn:hover { transform: translateY(-1px); }
  .btn-primary { color: #111; background: linear-gradient(135deg, var(--gold), var(--gold-soft)); box-shadow: 0 12px 26px rgba(214,165,54,0.22); }
  .btn-secondary { color: var(--text); background: rgba(255,255,255,0.04); border-color: rgba(214,165,54,0.22); }
  .btn-compact { min-height: 40px; padding: 9px 14px; font-size: 12.5px; }
  .btn[disabled] { cursor: not-allowed; opacity: .68; transform: none; }
  .notice {
    margin-bottom: 18px;
    border-radius: 18px;
    padding: 15px 18px;
    border: 1px solid var(--border-soft);
    font-size: 14px;
    line-height: 1.6;
  }
  .notice.success { color: #A7F3D0; background: rgba(34,197,94,0.10); border-color: rgba(34,197,94,0.22); }
  .notice.error { color: #FECACA; background: rgba(239,68,68,0.10); border-color: rgba(239,68,68,0.24); }
  .studio-grid { display: grid; grid-template-columns: minmax(0, 58fr) minmax(360px, 42fr); gap: 22px; align-items: start; }
  .panel {
    background: linear-gradient(180deg, rgba(32,32,30,0.76), rgba(23,23,22,0.86));
    border: 1px solid var(--border-gold);
    border-radius: 24px;
    box-shadow: 0 18px 50px rgba(0,0,0,0.22);
    padding: 24px;
  }
  .panel + .panel { margin-top: 14px; }
  .panel-head { display: flex; align-items: flex-start; gap: 14px; margin-bottom: 22px; }
  .step {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    flex: 0 0 28px;
    color: #111;
    background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    border-radius: 999px;
    font-weight: 900;
    font-size: 13px;
  }
  .panel h2, .panel h3 { color: var(--text); margin: 0; line-height: 1.25; }
  .panel h2 { font-size: 19px; }
  .panel h3 { font-size: 15px; }
  .panel p { color: var(--muted); margin: 5px 0 0; font-size: 12.5px; line-height: 1.6; }
  .field { margin-bottom: 18px; }
  .field-row { display: grid; grid-template-columns: 1fr 0.72fr; gap: 18px; }
  .field label { display: flex; align-items: center; justify-content: space-between; gap: 10px; color: var(--text); font-size: 13px; font-weight: 750; margin-bottom: 8px; }
  .required { color: var(--danger); }
  .hint { color: var(--muted); font-size: 11.5px; line-height: 1.55; margin-top: 7px; }
  .counter { color: #817a6d; font-size: 11px; font-weight: 600; }
  input[type=text], input[type=email], textarea, select {
    width: 100%;
    color: var(--text);
    background: #111110;
    border: 1px solid rgba(255,255,255,0.11);
    border-radius: 14px;
    font: inherit;
    font-size: 14px;
    outline: none;
    padding: 12px 14px;
    transition: border-color 180ms ease, box-shadow 180ms ease, background 180ms ease;
  }
  textarea { min-height: 220px; resize: vertical; line-height: 1.65; }
  input:focus, textarea:focus, select:focus {
    border-color: rgba(244,210,122,0.54);
    box-shadow: 0 0 0 4px rgba(214,165,54,0.10);
  }
  select { appearance: none; background-image: linear-gradient(45deg, transparent 50%, var(--gold-soft) 50%), linear-gradient(135deg, var(--gold-soft) 50%, transparent 50%); background-position: calc(100% - 18px) 50%, calc(100% - 12px) 50%; background-size: 6px 6px, 6px 6px; background-repeat: no-repeat; }
  .field-error { color: #FCA5A5; font-size: 12px; margin-top: 7px; }
  .sub-card {
    background: rgba(255,255,255,0.025);
    border: 1px solid rgba(214,165,54,0.14);
    border-radius: 20px;
    padding: 20px;
    margin-top: 14px;
  }
  .chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
  .chip {
    color: var(--gold-soft);
    background: rgba(214,165,54,0.09);
    border: 1px solid rgba(214,165,54,0.30);
    border-radius: 999px;
    cursor: pointer;
    font: 700 12px/1 ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    padding: 9px 10px;
  }
  .editor-toolbar {
    display: flex;
    justify-content: flex-end;
    margin-bottom: -1px;
    border: 1px solid rgba(255,255,255,0.10);
    border-radius: 14px 14px 0 0;
    background: rgba(0,0,0,0.22);
    padding: 8px;
  }
  .editor-toolbar .btn { min-height: 34px; border-radius: 10px; padding: 7px 11px; }
  .editor-toolbar + textarea { border-radius: 0 0 14px 14px; }
  .cta-preview { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin-top: 14px; }
  .cta-preview span { color: var(--muted); font-size: 12px; }
  .cta-preview .preview-button { color: #111; background: linear-gradient(135deg, var(--gold), var(--gold-soft)); border-radius: 12px; font-size: 13px; font-weight: 850; padding: 11px 18px; }
  .mailing-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 10px; }
  .switch-card { display: flex; align-items: center; justify-content: space-between; gap: 18px; }
  .switch-copy strong { display: block; color: var(--text); font-size: 15px; margin-bottom: 4px; }
  .switch-copy span { color: var(--muted); font-size: 12.5px; line-height: 1.5; }
  .switch { position: relative; display: inline-flex; width: 56px; height: 30px; flex: 0 0 56px; }
  .switch input { position: absolute; opacity: 0; inset: 0; }
  .slider { position: absolute; inset: 0; cursor: pointer; background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.16); border-radius: 999px; transition: background 180ms ease, border-color 180ms ease; }
  .slider::before { content: ""; position: absolute; width: 22px; height: 22px; left: 4px; top: 3px; background: #bdb7aa; border-radius: 50%; transition: transform 180ms ease, background 180ms ease; }
  .switch input:checked + .slider { background: rgba(34,197,94,0.25); border-color: rgba(34,197,94,0.55); }
  .switch input:checked + .slider::before { transform: translateX(26px); background: #86EFAC; }
  .test-grid { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 10px; align-items: end; }
  .preview-stack { position: sticky; top: 106px; }
  .email-preview {
    overflow: hidden;
    background: #111110;
    border: 1px solid var(--border-gold);
    border-radius: 22px;
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.04);
  }
  .email-header {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 72px;
    padding: 20px 28px;
    background:
      radial-gradient(circle at 80% 0%, rgba(214,165,54,0.20), transparent 36%),
      #141414;
    border-bottom: 1px solid rgba(214,165,54,0.18);
  }
  .email-header img { display: block; max-height: 42px; max-width: 190px; object-fit: contain; }
  .email-brand-name { color: #fff; font-size: 20px; font-weight: 850; }
  .email-subject { padding: 14px 24px; color: #d9d1c0; font-size: 13px; font-weight: 800; border-bottom: 1px solid rgba(255,255,255,0.08); }
  .email-body { min-height: 160px; padding: 24px; color: #ece7dc; font-size: 14px; line-height: 1.7; white-space: pre-line; }
  .event-details { display: grid; gap: 8px; padding: 0 24px 20px; }
  .event-details div { color: #d9d1c0; display: flex; gap: 9px; font-size: 12.5px; line-height: 1.4; }
  .email-cta { display: flex; justify-content: center; flex-wrap: wrap; gap: 10px; padding: 0 24px 24px; }
  .email-button { display: inline-flex; align-items: center; gap: 7px; border-radius: 12px; font-size: 13px; font-weight: 850; padding: 12px 18px; text-decoration: none; }
  .email-button.primary { color: #111; background: linear-gradient(135deg, var(--gold), var(--gold-soft)); }
  .email-button.secondary { color: var(--text); background: rgba(255,255,255,0.04); border: 1px solid rgba(214,165,54,0.24); }
  .email-footer { background: #141414; padding: 16px 24px; color: #8f887a; font-size: 11px; line-height: 1.55; }
  .status-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; margin-top: 16px; }
  .status-item { display: flex; align-items: center; justify-content: space-between; gap: 10px; background: rgba(255,255,255,0.025); border: 1px solid rgba(255,255,255,0.08); border-radius: 15px; padding: 12px; }
  .status-item span:first-child { color: var(--muted); font-size: 12px; }
  .badge-status { border-radius: 999px; font-size: 11px; font-weight: 850; padding: 6px 8px; }
  .badge-status.good { color: #BBF7D0; background: rgba(34,197,94,0.14); border: 1px solid rgba(34,197,94,0.24); }
  .badge-status.warn { color: #FDE68A; background: rgba(245,158,11,0.12); border: 1px solid rgba(245,158,11,0.24); }
  .badge-status.neutral { color: var(--gold-soft); background: rgba(214,165,54,0.10); border: 1px solid rgba(214,165,54,0.20); }
  .checklist { display: grid; gap: 10px; margin-top: 14px; }
  .check { display: flex; gap: 10px; align-items: flex-start; color: #d9d1c0; font-size: 12.5px; line-height: 1.45; }
  .check::before { content: ""; width: 9px; height: 9px; flex: 0 0 9px; margin-top: 5px; border-radius: 50%; background: var(--success); box-shadow: 0 0 0 4px rgba(34,197,94,0.10); }
  .save-bar {
    position: sticky;
    bottom: 18px;
    z-index: 20;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-top: 20px;
    padding: 16px 20px;
    background: rgba(23,23,22,0.90);
    border: 1px solid var(--border-gold);
    border-radius: 20px;
    box-shadow: 0 18px 50px rgba(0,0,0,0.34);
    backdrop-filter: blur(14px);
  }
  .save-bar p { margin: 0; color: var(--muted); font-size: 12.5px; line-height: 1.5; }
  .save-actions { display: flex; gap: 12px; flex-wrap: wrap; justify-content: flex-end; }
  .toast { position: fixed; right: 24px; bottom: 24px; z-index: 60; transform: translateY(20px); opacity: 0; pointer-events: none; color: #111; background: linear-gradient(135deg, var(--gold), var(--gold-soft)); border-radius: 14px; font-size: 13px; font-weight: 850; padding: 12px 16px; transition: opacity 180ms ease, transform 180ms ease; }
  .toast.show { opacity: 1; transform: translateY(0); }
  .empty-state { display: grid; place-items: center; min-height: 360px; text-align: center; }
  @media (max-width: 1080px) {
    .studio-grid { grid-template-columns: 1fr; }
    .preview-stack { position: static; }
    .hero { grid-template-columns: 1fr; }
    .hero-visual { display: none; }
  }
  @media (max-width: 760px) {
    .topbar-inner, .studio-wrap { padding-left: 16px; padding-right: 16px; }
    .topbar-inner { display: grid; min-height: auto; padding-top: 16px; padding-bottom: 16px; }
    .brand-link img { width: 112px; }
    .nav { justify-content: flex-start; gap: 8px; }
    .nav a { font-size: 12.5px; padding: 10px 12px; }
    .studio-wrap { padding-top: 18px; padding-bottom: 96px; }
    .hero { padding: 24px; border-radius: 22px; }
    .hero-actions, .save-actions { align-items: stretch; flex-direction: column; }
    .btn, .save-actions .btn { width: 100%; }
    .panel { border-radius: 20px; padding: 18px; }
    .field-row, .test-grid, .status-grid { grid-template-columns: 1fr; }
    textarea { min-height: 180px; }
    .save-bar { align-items: stretch; flex-direction: column; bottom: 10px; }
    .toast { left: 16px; right: 16px; bottom: 16px; text-align: center; }
  }
</style>
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand-link" href="dashboard.php" aria-label="<?= htmlspecialchars($previewBrandName) ?> Admin">
      <img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($previewBrandName) ?>">
    </a>
    <nav class="nav" aria-label="Navigasi admin">
      <a href="dashboard.php">Dashboard</a>
      <a href="events.php">Kelola Event</a>
      <a class="active" href="<?= $eventNotFound ? 'events.php' : 'email-settings.php?event=' . urlencode($eventSlug) ?>">Pengaturan Email</a>
      <a class="logout" href="logout.php">Keluar</a>
    </nav>
  </div>
</header>

<main class="studio-wrap">
  <?php if ($eventNotFound): ?>
    <section class="panel empty-state">
      <div>
        <span class="badge">Email Automation</span>
        <h1>Event Tidak Ditemukan</h1>
        <p>Silakan kembali ke daftar event dan pilih event yang valid untuk mengatur email otomatis.</p>
        <div class="hero-actions" style="justify-content:center;">
          <a class="btn btn-primary" href="events.php">Kembali ke Kelola Event</a>
        </div>
      </div>
    </section>
  <?php else: ?>
    <section class="hero" aria-labelledby="page-title">
      <div class="hero-copy">
        <div class="breadcrumb"><span>Kelola Event</span><span>/</span><strong>Pengaturan Email</strong></div>
        <span class="badge">Email Automation Studio</span>
        <h1 id="page-title">Pengaturan Email — <span><?= htmlspecialchars($event['name']) ?></span></h1>
        <p>Atur email otomatis yang dikirim saat peserta baru mendaftar, lengkap dengan preview, status automation, dan integrasi mailing list.</p>
        <div class="hero-actions">
          <a class="btn btn-secondary" href="events.php">Kembali ke Event</a>
          <a class="btn btn-secondary" href="<?= htmlspecialchars($eventUrl) ?>" target="_blank" rel="noopener">Lihat Landing Page</a>
          <button class="btn btn-primary" type="submit" form="email-settings-form" name="form_action" value="test">Kirim Test Email</button>
        </div>
      </div>
      <div class="hero-visual" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm18 4-10 6L2 8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </div>
    </section>

    <?php if ($notice): ?>
      <div class="notice <?= htmlspecialchars($noticeType) ?>"><?= $notice ?></div>
    <?php endif; ?>

    <form id="email-settings-form" method="POST" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

      <div class="studio-grid">
        <div>
          <section class="panel">
            <div class="panel-head">
              <span class="step">1</span>
              <div>
                <h2>Editor Email Otomatis</h2>
                <p>Email ini akan dikirim otomatis setelah peserta berhasil mendaftar.</p>
              </div>
            </div>

            <div class="field">
              <label for="es-subject">Subjek Email <span class="required">*</span><span class="counter" id="subjectCounter">0/200</span></label>
              <input type="text" id="es-subject" name="subject" maxlength="200" value="<?= htmlspecialchars($formValues['subject']) ?>">
              <div class="hint">Gunakan judul yang jelas agar email mudah dikenali peserta.</div>
              <?php if (isset($fieldErrors['subject'])): ?><div class="field-error"><?= htmlspecialchars($fieldErrors['subject']) ?></div><?php endif; ?>
            </div>

            <div class="field">
              <label for="es-body">Isi Email <span class="required">*</span><span class="counter" id="bodyCounter">0 karakter</span></label>
              <div class="editor-toolbar">
                <button class="btn btn-secondary btn-compact" type="button" id="usePlaceholderBtn">Gunakan Placeholder</button>
              </div>
              <textarea id="es-body" name="body_content"><?= htmlspecialchars($formValues['body_content']) ?></textarea>
              <div class="hint">Gunakan placeholder untuk mengisi data peserta dan detail acara secara otomatis.</div>
              <?php if (isset($fieldErrors['body_content'])): ?><div class="field-error"><?= htmlspecialchars($fieldErrors['body_content']) ?></div><?php endif; ?>
            </div>

            <div class="sub-card">
              <h3>Placeholder Tersedia</h3>
              <p>Klik untuk menyisipkan placeholder ke posisi cursor textarea.</p>
              <div class="chips" aria-label="Placeholder tersedia">
                <button class="chip" type="button" data-placeholder="{{name}}">{{name}}</button>
                <button class="chip" type="button" data-placeholder="{{event_name}}">{{event_name}}</button>
                <button class="chip" type="button" data-placeholder="{{event_day}}">{{event_day}}</button>
                <button class="chip" type="button" data-placeholder="{{event_time}}">{{event_time}}</button>
                <button class="chip" type="button" data-placeholder="{{event_location}}">{{event_location}}</button>
                <button class="chip" type="button" data-placeholder="{{event_speaker}}">{{event_speaker}}</button>
                <button class="chip" type="button" data-placeholder="{{invitation_link}}">{{invitation_link}}</button>
              </div>
            </div>
          </section>

          <section class="sub-card">
            <div class="panel-head">
              <span class="step">2</span>
              <div>
                <h2>Tombol Akses Acara (CTA)</h2>
                <p>Atur link dan teks tombol utama di dalam email.</p>
              </div>
            </div>
            <div class="field-row">
              <div class="field">
                <label for="es-link">Link Invitation / Akses Acara <span class="required">*</span></label>
                <input type="text" id="es-link" name="invitation_link" placeholder="https://zoom.us/j/xxxxxxxxxx" value="<?= htmlspecialchars($formValues['invitation_link']) ?>">
                <div class="hint">Isi dengan link Zoom, Google Meet, grup WhatsApp, atau halaman akses acara.</div>
                <?php if (isset($fieldErrors['invitation_link'])): ?><div class="field-error"><?= htmlspecialchars($fieldErrors['invitation_link']) ?></div><?php endif; ?>
              </div>
              <div class="field">
                <label for="es-cta">Teks Tombol CTA</label>
                <input type="text" id="es-cta" name="cta_text" maxlength="100" value="<?= htmlspecialchars($formValues['cta_text']) ?>">
                <div class="hint">Teks tombol utama di dalam email.</div>
              </div>
            </div>
            <div class="cta-preview">
              <span>Preview Tombol</span>
              <span class="preview-button" id="ctaMiniPreview"><?= htmlspecialchars($formValues['cta_text']) ?></span>
            </div>
          </section>

          <section class="sub-card">
            <div class="panel-head">
              <span class="step">3</span>
              <div>
                <h2>List Mailketing <small style="color:var(--muted);font-weight:700;">(Opsional)</small></h2>
                <p>Opsional. Pilih list jika pendaftar ingin ikut disimpan ke sistem email marketing.</p>
              </div>
            </div>
            <div class="field">
              <label for="es-list">Pilih List Mailketing</label>
              <select id="es-list" name="mailketing_list_id">
                <option value="">Tidak menambahkan ke list</option>
                <?php if (!empty($formValues['mailketing_list_id'])): ?>
                  <option value="<?= htmlspecialchars($formValues['mailketing_list_id']) ?>" selected><?= htmlspecialchars($formValues['mailketing_list_id']) ?> (tersimpan)</option>
                <?php endif; ?>
              </select>
              <div class="mailing-actions">
                <button type="button" class="btn btn-secondary btn-compact" id="es-refresh-list">Muat Ulang Daftar List</button>
                <span class="hint" id="listState">Pendaftar tetap tersimpan di database meskipun tidak ditambahkan ke list.</span>
              </div>
              <?php if (isset($fieldErrors['mailketing_list_id'])): ?><div class="field-error"><?= htmlspecialchars($fieldErrors['mailketing_list_id']) ?></div><?php endif; ?>
            </div>
          </section>

          <section class="sub-card">
            <div class="panel-head">
              <span class="step">4</span>
              <div>
                <h2>Email Otomatis</h2>
                <p>Kirim email otomatis saat ada pendaftar baru.</p>
              </div>
            </div>
            <div class="switch-card">
              <div class="switch-copy">
                <strong id="autoSendTitle"><?= $formValues['auto_send'] ? 'Auto-send Aktif' : 'Auto-send Nonaktif' ?></strong>
                <span>Email undangan akan dikirim otomatis setelah submit lead berhasil.</span>
              </div>
              <label class="switch" for="es-auto">
                <input type="checkbox" id="es-auto" name="auto_send" <?= $formValues['auto_send'] ? 'checked' : '' ?>>
                <span class="slider"></span>
              </label>
            </div>
          </section>

          <section class="sub-card">
            <div class="panel-head">
              <span class="step">5</span>
              <div>
                <h2>Kirim Test Email</h2>
                <p>Kirim contoh email ke alamat internal sebelum menyimpan atau mengaktifkan automation.</p>
              </div>
            </div>
            <div class="test-grid">
              <div class="field" style="margin-bottom:0;">
                <label for="test-email">Email Tujuan Test</label>
                <input type="email" id="test-email" name="test_email" value="<?= htmlspecialchars($testEmail) ?>" placeholder="nama@<?= htmlspecialchars(preg_replace('/^www\./', '', $brand['domain'])) ?>">
                <?php if (isset($fieldErrors['test_email'])): ?><div class="field-error"><?= htmlspecialchars($fieldErrors['test_email']) ?></div><?php endif; ?>
              </div>
              <button class="btn btn-secondary" type="submit" name="form_action" value="test">Kirim Test Email</button>
            </div>
          </section>
        </div>

        <aside class="preview-stack">
          <section class="panel">
            <div class="panel-head">
              <div>
                <h2>Preview Email</h2>
                <p>Contoh tampilan email yang akan diterima peserta.</p>
              </div>
            </div>
            <div class="email-preview">
              <div class="email-header">
                <?php if ($previewLogoPath !== ''): ?>
                  <img src="<?= htmlspecialchars($previewLogoPath) ?>" alt="<?= htmlspecialchars($previewBrandName) ?>">
                <?php else: ?>
                  <span class="email-brand-name"><?= htmlspecialchars($previewBrandName) ?></span>
                <?php endif; ?>
              </div>
              <div class="email-subject" id="es-preview-subject"></div>
              <div class="email-body" id="es-preview-body"></div>
              <div class="event-details">
                <div><span>Hari/Tanggal:</span><strong><?= htmlspecialchars($event['event_day'] ?? '-') ?></strong></div>
                <div><span>Waktu:</span><strong><?= htmlspecialchars($event['event_time'] ?? '-') ?></strong></div>
                <div><span>Lokasi:</span><strong><?= htmlspecialchars($event['event_location'] ?? '-') ?></strong></div>
              </div>
              <div class="email-cta">
                <span class="email-button primary"><span aria-hidden="true">&#128279;</span><span id="es-preview-cta"></span></span>
                <span class="email-button secondary"><span aria-hidden="true">&#128197;</span><span>Set Alarm</span></span>
              </div>
              <div class="email-footer"><?= htmlspecialchars($previewDisclaimer) ?></div>
            </div>
          </section>

          <section class="panel">
            <h2>Status Email</h2>
            <div class="status-grid">
              <div class="status-item"><span>Auto-send</span><strong class="badge-status" id="statusAuto"></strong></div>
              <div class="status-item"><span>Link invitation</span><strong class="badge-status" id="statusLink"></strong></div>
              <div class="status-item"><span>CTA Button</span><strong class="badge-status" id="statusCta"></strong></div>
              <div class="status-item"><span>Mailing list</span><strong class="badge-status" id="statusList"></strong></div>
            </div>
          </section>

          <section class="panel">
            <h2>Checklist Sebelum Simpan</h2>
            <div class="checklist">
              <div class="check">Subjek email sudah jelas.</div>
              <div class="check">Isi email menggunakan placeholder yang benar.</div>
              <div class="check">Link akses acara sudah valid.</div>
              <div class="check">Tombol CTA singkat dan jelas.</div>
              <div class="check">Auto-send aktif jika email memang ingin dikirim otomatis.</div>
            </div>
          </section>
        </aside>
      </div>

      <div class="save-bar">
        <p>Tips: gunakan placeholder untuk personalisasi email agar terlihat lebih profesional dan terpercaya.</p>
        <div class="save-actions">
          <a class="btn btn-secondary" href="events.php">Batal</a>
          <button class="btn btn-primary" type="submit" name="form_action" value="save" id="saveBtn">Simpan Pengaturan Email</button>
        </div>
      </div>
    </form>
  <?php endif; ?>
</main>

<div class="toast" id="toast" role="status" aria-live="polite">Placeholder disalin</div>

<script>
(function () {
  const form = document.getElementById('email-settings-form');
  const subjectInput = document.getElementById('es-subject');
  const bodyInput = document.getElementById('es-body');
  const linkInput = document.getElementById('es-link');
  const ctaInput = document.getElementById('es-cta');
  const autoInput = document.getElementById('es-auto');
  const previewSubject = document.getElementById('es-preview-subject');
  const previewBody = document.getElementById('es-preview-body');
  const previewCta = document.getElementById('es-preview-cta');
  const listSelect = document.getElementById('es-list');
  const refreshBtn = document.getElementById('es-refresh-list');
  const subjectCounter = document.getElementById('subjectCounter');
  const bodyCounter = document.getElementById('bodyCounter');
  const ctaMiniPreview = document.getElementById('ctaMiniPreview');
  const autoSendTitle = document.getElementById('autoSendTitle');
  const statusAuto = document.getElementById('statusAuto');
  const statusLink = document.getElementById('statusLink');
  const statusCta = document.getElementById('statusCta');
  const statusList = document.getElementById('statusList');
  const toast = document.getElementById('toast');

  if (!form || !subjectInput || !bodyInput || !ctaInput || !previewSubject || !previewBody || !previewCta || !listSelect || !refreshBtn) {
    return;
  }

  const sampleData = {
    '{{nama}}': 'Budi Santoso',
    '{{name}}': 'Budi Santoso',
    '{{event_name}}': <?= json_encode($event['name'] ?? '') ?>,
    '{{event_day}}': <?= json_encode($event['event_day'] ?? 'Jumat, 3 Juli 2026') ?>,
    '{{event_time}}': <?= json_encode($event['event_time'] ?? '19.45 WIB') ?>,
    '{{event_location}}': <?= json_encode($event['event_location'] ?? 'Online via Zoom') ?>,
    '{{event_speaker}}': <?= json_encode($event['event_speaker'] ?? '') ?>,
    '{{invitation_link}}': 'https://zoom.us/j/xxxxxxxx',
  };

  function setBadge(el, text, type) {
    if (!el) return;
    el.textContent = text;
    el.className = 'badge-status ' + type;
  }

  function showToast(message) {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    window.clearTimeout(showToast.timer);
    showToast.timer = window.setTimeout(function () {
      toast.classList.remove('show');
    }, 1800);
  }

  function applyPlaceholders(text) {
    let result = text;
    Object.keys(sampleData).forEach(function (key) {
      result = result.split(key).join(sampleData[key]);
    });
    return result;
  }

  function updatePreview() {
    const subject = subjectInput.value || '(subjek kosong)';
    const body = bodyInput.value || '';
    const cta = ctaInput.value || 'Gabung ke Acara Sekarang';
    const hasLink = linkInput && linkInput.value.trim() !== '';
    const hasList = listSelect.value.trim() !== '';
    const autoOn = autoInput && autoInput.checked;

    previewSubject.textContent = subject;
    previewBody.textContent = applyPlaceholders(body);
    previewCta.textContent = cta;
    if (ctaMiniPreview) ctaMiniPreview.textContent = cta;
    if (subjectCounter) subjectCounter.textContent = subjectInput.value.length + '/200';
    if (bodyCounter) bodyCounter.textContent = bodyInput.value.length + ' karakter';
    if (autoSendTitle) autoSendTitle.textContent = autoOn ? 'Auto-send Aktif' : 'Auto-send Nonaktif';

    setBadge(statusAuto, autoOn ? 'Aktif' : 'Nonaktif', autoOn ? 'good' : 'neutral');
    setBadge(statusLink, hasLink ? 'Terisi' : 'Belum terisi', hasLink ? 'good' : 'warn');
    setBadge(statusCta, cta.trim() !== '' ? 'Siap' : 'Belum lengkap', cta.trim() !== '' ? 'good' : 'warn');
    setBadge(statusList, hasList ? 'Dipilih' : 'Tidak dipakai', hasList ? 'good' : 'neutral');
  }

  [subjectInput, bodyInput, ctaInput, linkInput, listSelect, autoInput].forEach(function (el) {
    if (el) el.addEventListener('input', updatePreview);
    if (el) el.addEventListener('change', updatePreview);
  });
  updatePreview();

  document.querySelectorAll('.chip[data-placeholder]').forEach(function (chip) {
    chip.addEventListener('click', function () {
      const placeholder = chip.dataset.placeholder;
      bodyInput.focus();
      const start = bodyInput.selectionStart || 0;
      const end = bodyInput.selectionEnd || 0;
      const value = bodyInput.value;
      bodyInput.value = value.slice(0, start) + placeholder + value.slice(end);
      const next = start + placeholder.length;
      bodyInput.setSelectionRange(next, next);
      navigator.clipboard?.writeText(placeholder).catch(function () {});
      updatePreview();
      showToast('Placeholder disisipkan');
    });
  });

  const usePlaceholderBtn = document.getElementById('usePlaceholderBtn');
  if (usePlaceholderBtn) {
    usePlaceholderBtn.addEventListener('click', function () {
      document.querySelector('.chip[data-placeholder]')?.click();
    });
  }

  refreshBtn.addEventListener('click', async function () {
    refreshBtn.disabled = true;
    refreshBtn.textContent = 'Memuat...';
    try {
      const res = await fetch('../api/mailketing_get_lists.php', {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      });
      const contentType = res.headers.get('content-type') || '';
      if (!contentType.includes('application/json')) {
        throw new Error('Respons server bukan JSON. Kemungkinan sesi admin berakhir atau endpoint diblokir.');
      }
      const result = await res.json();
      if (result.success && Array.isArray(result.lists)) {
        const currentValue = listSelect.value;
        listSelect.replaceChildren();
        const emptyOpt = document.createElement('option');
        emptyOpt.value = '';
        emptyOpt.textContent = result.lists.length ? 'Tidak menambahkan ke list' : 'Belum ada list tersedia';
        listSelect.appendChild(emptyOpt);
        result.lists.forEach(function (item) {
          const opt = document.createElement('option');
          opt.value = item.list_id ?? item.id ?? '';
          opt.textContent = (item.list_name ?? item.name ?? 'List') + ' (#' + opt.value + ')';
          if (opt.value === currentValue) opt.selected = true;
          listSelect.appendChild(opt);
        });
        showToast(result.lists.length ? 'Daftar list diperbarui' : 'Belum ada list tersedia');
        updatePreview();
      } else {
        alert(result.message || 'Gagal memuat daftar list.');
      }
    } catch (err) {
      alert(err.message || 'Gagal terhubung ke server.');
    } finally {
      refreshBtn.disabled = false;
      refreshBtn.textContent = 'Muat Ulang Daftar List';
    }
  });

  form.addEventListener('submit', function (event) {
    const submitter = event.submitter;
    if (submitter && submitter.tagName === 'BUTTON') {
      if (submitter.name && !form.querySelector('input[type="hidden"][name="' + submitter.name + '"][data-submit-proxy="1"]')) {
        const proxy = document.createElement('input');
        proxy.type = 'hidden';
        proxy.name = submitter.name;
        proxy.value = submitter.value;
        proxy.dataset.submitProxy = '1';
        form.appendChild(proxy);
      }
      submitter.disabled = true;
      submitter.textContent = submitter.value === 'test' ? 'Mengirim...' : 'Menyimpan...';
    }
  });
})();
</script>
</body>
</html>
