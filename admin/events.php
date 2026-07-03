<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
start_secure_session();

$brand = require_admin_for_brand(get_current_brand());
$brandId = (int)$brand['id'];
$defaultEventSlug = $brand['default_event_slug'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pdo = get_db();
$notice = null;
$noticeType = 'success'; // success | error

// ==================== HANDLE ACTIONS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    $notice = 'Sesi tidak valid. Silakan refresh halaman lalu coba lagi.';
    $noticeType = 'error';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---- Upload / Update event via ZIP ----
    if (isset($_FILES['event_zip'])) {
        $file = $_FILES['event_zip'];
        $slugOverride = slugify(clean($_POST['slug_override'] ?? ''));
        $allowOverwrite = isset($_POST['allow_overwrite']);

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $notice = 'Upload gagal (kode error: ' . $file['error'] . '). Coba lagi.';
            $noticeType = 'error';
        } elseif ($file['size'] > MAX_ZIP_SIZE) {
            $notice = 'Ukuran ZIP terlalu besar. Maksimal ' . (MAX_ZIP_SIZE / 1024 / 1024) . ' MB.';
            $noticeType = 'error';
        } else {
            // Baca config.json dari dalam ZIP tanpa extract dulu, untuk ambil slug/nama
            $zip = new ZipArchive();
            if ($zip->open($file['tmp_name']) !== true) {
                $notice = 'File ZIP tidak valid atau rusak.';
                $noticeType = 'error';
            } else {
                $configRaw = $zip->getFromName('config.json');
                $hasIndex = $zip->locateName('index.html') !== false;
                $zip->close();

                if (!$hasIndex) {
                    $notice = 'ZIP harus berisi file index.html di posisi root (bukan di dalam subfolder).';
                    $noticeType = 'error';
                } elseif (!$configRaw) {
                    $notice = 'ZIP harus berisi file config.json di posisi root. Lihat README-EVENTS.md untuk formatnya.';
                    $noticeType = 'error';
                } else {
                    $cfg = json_decode($configRaw, true);
                    if (!$cfg || empty($cfg['name'])) {
                        $notice = 'config.json tidak valid atau field "name" kosong.';
                        $noticeType = 'error';
                    } else {
                        $slug = $slugOverride !== '' ? $slugOverride : slugify($cfg['slug'] ?? $cfg['name']);

                        $existingEvent = get_event_by_slug($slug);

                        if (!is_valid_event_slug($slug)) {
                            $notice = 'Slug "' . htmlspecialchars($slug) . '" tidak valid. Gunakan huruf kecil, angka, dan strip saja (contoh: funtactic-selling), dan bukan kata yang dicadangkan sistem.';
                            $noticeType = 'error';
                        } elseif ($existingEvent && (int)$existingEvent['brand_id'] !== $brandId) {
                            // Slug event unik secara GLOBAL (folder /e/ dibagi semua brand) —
                            // jangan biarkan brand ini menimpa event milik brand lain.
                            $notice = 'Slug "' . htmlspecialchars($slug) . '" sudah dipakai oleh brand lain. Gunakan slug lain.';
                            $noticeType = 'error';
                        } else {
                            $targetDir = EVENTS_DIR . '/' . $slug;
                            $existsAlready = is_dir($targetDir) && count(glob($targetDir . '/*')) > 0;

                            if ($existsAlready && !$allowOverwrite) {
                                $notice = 'Event dengan slug "' . htmlspecialchars($slug) . '" sudah ada. Centang "Timpa event yang sudah ada" jika Anda ingin memperbarui landing page-nya.';
                                $noticeType = 'error';
                            } else {
                                $extractResult = safe_extract_zip($file['tmp_name'], $targetDir);

                                if (!$extractResult['ok']) {
                                    $notice = 'Gagal mengekstrak ZIP. Pastikan struktur file valid dan coba upload kembali.';
                                    $noticeType = 'error';
                                } else {
                                    inject_sdk_script($targetDir . '/index.html');

                                    // Upsert ke tabel events (brand_id sudah divalidasi di atas milik brand ini)
                                    $stmt = $pdo->prepare('
                                        INSERT INTO events (brand_id, slug, name, status, whatsapp_default, event_day, event_time, event_location, event_speaker, event_capacity)
                                        VALUES (?, ?, ?, "active", ?, ?, ?, ?, ?, ?)
                                        ON DUPLICATE KEY UPDATE
                                            name = VALUES(name), status = "active", whatsapp_default = VALUES(whatsapp_default),
                                            event_day = VALUES(event_day), event_time = VALUES(event_time),
                                            event_location = VALUES(event_location), event_speaker = VALUES(event_speaker),
                                            event_capacity = VALUES(event_capacity)
                                    ');
                                    $stmt->execute([
                                        $brandId,
                                        $slug,
                                        clean($cfg['name']),
                                        normalize_whatsapp(clean($cfg['whatsapp'] ?? '')),
                                        clean($cfg['event_day'] ?? ''),
                                        clean($cfg['event_time'] ?? ''),
                                        clean($cfg['event_location'] ?? ''),
                                        clean($cfg['event_speaker'] ?? ''),
                                        clean($cfg['event_capacity'] ?? ''),
                                    ]);

                                    $skippedCount = count($extractResult['skipped']);
                                    $notice = 'Event "' . htmlspecialchars($cfg['name']) . '" berhasil ' . ($existsAlready ? 'diperbarui' : 'dipublikasikan') .
                                        '! (' . $extractResult['extracted'] . ' file diekstrak' .
                                        ($skippedCount > 0 ? ", {$skippedCount} file dilewati karena tidak diizinkan" : '') . ')';
                                    $noticeType = 'success';
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    // ---- Arsipkan / aktifkan kembali event ----
    if (isset($_POST['toggle_status']) && isset($_POST['slug'])) {
        $slug = clean($_POST['slug']);
        if ($slug !== $defaultEventSlug) {
            $ev = get_event_by_slug($slug);
            if ($ev && (int)$ev['brand_id'] === $brandId) {
                $newStatus = $ev['status'] === 'active' ? 'archived' : 'active';
                $stmt = $pdo->prepare('UPDATE events SET status = ? WHERE slug = ? AND brand_id = ?');
                $stmt->execute([$newStatus, $slug, $brandId]);
                $notice = 'Status event "' . htmlspecialchars($ev['name']) . '" diubah menjadi ' . ($newStatus === 'active' ? 'AKTIF' : 'DIARSIPKAN') . '.';
                $noticeType = 'success';
            }
        } else {
            $notice = 'Event utama (default) tidak bisa diarsipkan.';
            $noticeType = 'error';
        }
    }
}

// ==================== DATA UNTUK TAMPILAN ====================
$stmt = $pdo->prepare('
    SELECT e.*,
        (SELECT COUNT(*) FROM leads l WHERE l.brand_id = e.brand_id AND l.event_slug = e.slug) AS total_leads,
        (SELECT COUNT(*) FROM referrers r WHERE r.brand_id = e.brand_id AND r.event_slug = e.slug) AS total_referrers
    FROM events e
    WHERE e.brand_id = ?
    ORDER BY (e.slug = ?) DESC, e.created_at DESC
');
$stmt->execute([$brandId, $defaultEventSlug]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalEvents = count($events);
$activeEvents = count(array_filter($events, static fn ($event) => ($event['status'] ?? '') === 'active'));
$totalLeads = array_sum(array_map(static fn ($event) => (int)$event['total_leads'], $events));
$totalReferrers = array_sum(array_map(static fn ($event) => (int)$event['total_referrers'], $events));
$maxZipMb = (int)(MAX_ZIP_SIZE / 1024 / 1024);
$logoPath = $brand['logo_path'] ? '..' . $brand['logo_path'] : '../assets/logo.png';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Event — rahasiaemas.id</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
<style>
  :root {
    --bg:#0B0B0A;
    --bg-soft:#10100F;
    --surface:#171716;
    --surface-elevated:#20201E;
    --border-gold:rgba(214,165,54,0.18);
    --border-strong:rgba(214,165,54,0.30);
    --border-soft:rgba(255,255,255,0.09);
    --gold:#D6A536;
    --gold-soft:#F4D27A;
    --text:#F7F3E8;
    --muted:#A8A29A;
    --success:#22C55E;
    --warning:#F59E0B;
    --danger:#EF4444;
    --shadow:0 22px 70px rgba(0,0,0,0.34);
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html { scroll-behavior: smooth; }
  body {
    min-height: 100vh;
    background:
      radial-gradient(circle at 88% 6%, rgba(214,165,54,0.24), transparent 28vw),
      radial-gradient(circle at 8% 88%, rgba(214,165,54,0.13), transparent 34vw),
      linear-gradient(135deg, var(--bg) 0%, var(--bg-soft) 52%, #090908 100%);
    color: var(--text);
    font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  }
  body::before {
    content:"";
    position: fixed;
    inset: 0;
    pointer-events: none;
    background-image:
      linear-gradient(rgba(255,255,255,0.022) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,0.016) 1px, transparent 1px);
    background-size: 52px 52px;
    mask-image: radial-gradient(circle at 50% 18%, black, transparent 72%);
  }
  a { color: inherit; }
  .topbar {
    position: sticky;
    top: 0;
    z-index: 20;
    background: rgba(16,16,15,0.78);
    border-bottom: 1px solid rgba(214,165,54,0.14);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
  }
  .topbar-inner, .wrap {
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
    gap: 22px;
  }
  .brand {
    display: inline-flex;
    align-items: center;
    gap: 14px;
    text-decoration: none;
  }
  .brand img {
    width: 146px;
    height: auto;
    display: block;
    filter: drop-shadow(0 10px 20px rgba(0,0,0,0.32));
  }
  .nav {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
  }
  .nav a, .btn, .chip, .event-action, .field input, .file-drop, .switch-slider {
    transition: transform 180ms ease, border-color 180ms ease, background 180ms ease, color 180ms ease, box-shadow 180ms ease, opacity 180ms ease;
  }
  .nav a {
    color: var(--muted);
    display: inline-flex;
    align-items: center;
    border: 1px solid transparent;
    border-radius: 999px;
    font-size: 13.5px;
    font-weight: 700;
    line-height: 1;
    padding: 12px 15px;
    text-decoration: none;
  }
  .nav a:hover { color: var(--text); background: rgba(255,255,255,0.04); }
  .nav a.active {
    color: var(--gold-soft);
    background: rgba(214,165,54,0.10);
    border-color: var(--border-gold);
    box-shadow: inset 0 -2px 0 rgba(244,210,122,0.45);
  }
  .nav .logout {
    border-color: rgba(255,255,255,0.10);
    background: rgba(255,255,255,0.035);
  }
  .wrap {
    position: relative;
    padding-top: 28px;
    padding-bottom: 48px;
  }
  .hero {
    position: relative;
    overflow: hidden;
    display: grid;
    grid-template-columns: 1fr auto;
    align-items: center;
    gap: 24px;
    background:
      radial-gradient(circle at 90% 20%, rgba(244,210,122,0.28), transparent 22%),
      linear-gradient(135deg, rgba(32,32,30,0.96), rgba(23,23,22,0.92) 58%, rgba(76,52,12,0.34));
    border: 1px solid var(--border-gold);
    border-radius: 28px;
    box-shadow: var(--shadow);
    padding: 36px;
    margin-bottom: 18px;
  }
  .hero::after {
    content:"";
    position: absolute;
    right: -130px;
    bottom: -190px;
    width: 420px;
    height: 420px;
    border: 1px solid rgba(244,210,122,0.22);
    border-radius: 50%;
    box-shadow: inset 0 0 60px rgba(214,165,54,0.08);
  }
  .hero-copy, .hero-actions { position: relative; z-index: 1; }
  .eyebrow {
    display: inline-flex;
    align-items: center;
    width: fit-content;
    color: var(--gold-soft);
    background: rgba(214,165,54,0.12);
    border: 1px solid var(--border-gold);
    border-radius: 999px;
    font-size: 12px;
    font-weight: 800;
    margin-bottom: 12px;
    padding: 7px 10px;
  }
  h1 {
    color: var(--text);
    font-family: "Playfair Display", Georgia, serif;
    font-size: clamp(34px, 4.6vw, 54px);
    line-height: 1.04;
    letter-spacing: 0;
    margin-bottom: 12px;
  }
  .subtitle {
    color: var(--muted);
    max-width: 720px;
    font-size: 15px;
    line-height: 1.7;
  }
  .hero-actions, .section-actions, .action-row {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
    flex-wrap: wrap;
  }
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    min-height: 46px;
    border: 1px solid transparent;
    border-radius: 14px;
    cursor: pointer;
    font: inherit;
    font-size: 13.5px;
    font-weight: 900;
    padding: 12px 18px;
    text-decoration: none;
    white-space: nowrap;
  }
  .btn:hover, .event-action:hover, .chip:hover { transform: translateY(-1px); }
  .btn-gold {
    color: #111;
    background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    box-shadow: 0 12px 26px rgba(214,165,54,0.24);
  }
  .btn-outline, .event-action {
    color: var(--text);
    background: rgba(255,255,255,0.035);
    border-color: rgba(214,165,54,0.22);
  }
  .btn-danger {
    color: #fff;
    background: rgba(239,68,68,0.11);
    border-color: rgba(239,68,68,0.28);
  }
  .notice {
    border: 1px solid var(--border-soft);
    border-radius: 18px;
    box-shadow: 0 16px 36px rgba(0,0,0,0.20);
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 18px;
    padding: 16px 18px;
  }
  .notice.success {
    color: #A7F3D0;
    background: rgba(34,197,94,0.10);
    border-color: rgba(34,197,94,0.22);
  }
  .notice.error {
    color: #FECACA;
    background: rgba(239,68,68,0.10);
    border-color: rgba(239,68,68,0.24);
  }
  .summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 18px;
  }
  .summary-card, .panel, .event-card {
    background: linear-gradient(180deg, rgba(255,255,255,0.045), rgba(255,255,255,0.02));
    border: 1px solid var(--border-gold);
    box-shadow: 0 18px 50px rgba(0,0,0,0.24);
  }
  .summary-card {
    display: flex;
    align-items: center;
    gap: 16px;
    min-height: 118px;
    border-radius: 22px;
    padding: 20px;
  }
  .summary-card:hover, .event-card:hover { transform: translateY(-2px); border-color: rgba(244,210,122,0.34); }
  .icon-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    flex: 0 0 48px;
    color: var(--gold-soft);
    background: rgba(214,165,54,0.12);
    border: 1px solid rgba(244,210,122,0.28);
    border-radius: 16px;
  }
  .summary-label {
    color: var(--gold-soft);
    font-size: 11px;
    font-weight: 900;
    letter-spacing: .08em;
    text-transform: uppercase;
  }
  .summary-num {
    color: var(--text);
    font-size: 32px;
    font-weight: 900;
    line-height: 1;
    margin: 6px 0;
  }
  .summary-copy {
    color: var(--muted);
    font-size: 12px;
  }
  .panel {
    border-radius: 24px;
    margin-bottom: 18px;
    padding: 26px;
  }
  .panel-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 20px;
  }
  h2 {
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--text);
    font-size: 21px;
    font-weight: 900;
    line-height: 1.25;
  }
  h2 .icon-badge {
    width: 36px;
    height: 36px;
    flex-basis: 36px;
    border-radius: 12px;
  }
  .desc {
    color: var(--muted);
    font-size: 13px;
    line-height: 1.65;
    margin-top: 7px;
    max-width: 760px;
  }
  .upload-layout {
    display: grid;
    grid-template-columns: minmax(280px, .95fr) minmax(320px, 1.05fr);
    gap: 24px;
  }
  .steps {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--muted);
    font-size: 12.5px;
    font-weight: 700;
    flex-wrap: wrap;
  }
  .step {
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }
  .step-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    color: #111;
    background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    border-radius: 50%;
    font-weight: 900;
  }
  .step:nth-child(n+2) .step-num {
    color: var(--text);
    background: rgba(255,255,255,0.10);
    border: 1px solid rgba(255,255,255,0.13);
  }
  .step-arrow { color: rgba(255,255,255,0.22); }
  .file-drop {
    display: grid;
    place-items: center;
    min-height: 206px;
    border: 1px dashed rgba(244,210,122,0.62);
    border-radius: 18px;
    background: rgba(11,11,10,0.42);
    cursor: pointer;
    padding: 26px;
    text-align: center;
  }
  .file-drop:hover {
    background: rgba(214,165,54,0.06);
    border-color: var(--gold-soft);
  }
  .file-drop input {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
    pointer-events: none;
  }
  .upload-icon {
    display: inline-flex;
    color: var(--gold-soft);
    margin-bottom: 12px;
  }
  .file-title {
    color: var(--text);
    display: block;
    font-size: 16px;
    font-weight: 900;
    margin-bottom: 7px;
  }
  .file-subtitle, .file-name, .helper {
    color: var(--muted);
    display: block;
    font-size: 12.5px;
    line-height: 1.6;
  }
  .file-name {
    color: var(--gold-soft);
    font-weight: 800;
    margin-top: 12px;
  }
  .field {
    margin-bottom: 18px;
  }
  .field label, .switch-label {
    color: var(--text);
    display: block;
    font-size: 13px;
    font-weight: 800;
    margin-bottom: 8px;
  }
  .field input[type="text"], .event-search {
    width: 100%;
    min-height: 46px;
    color: var(--text);
    background: rgba(255,255,255,0.035);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 12px;
    font: inherit;
    font-size: 13.5px;
    outline: none;
    padding: 0 14px;
  }
  .field input[type="text"]:focus, .event-search:focus {
    border-color: rgba(244,210,122,0.42);
    box-shadow: 0 0 0 4px rgba(214,165,54,0.10);
  }
  .helper strong { color: var(--gold-soft); }
  .switch-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin: 18px 0;
  }
  .switch {
    position: relative;
    display: inline-flex;
    width: 54px;
    height: 30px;
    flex: 0 0 54px;
  }
  .switch input {
    opacity: 0;
    width: 0;
    height: 0;
  }
  .switch-slider {
    position: absolute;
    inset: 0;
    background: rgba(255,255,255,0.16);
    border: 1px solid rgba(255,255,255,0.14);
    border-radius: 999px;
    cursor: pointer;
  }
  .switch-slider::before {
    content:"";
    position: absolute;
    width: 22px;
    height: 22px;
    left: 3px;
    top: 3px;
    background: var(--text);
    border-radius: 50%;
    transition: transform 180ms ease, background 180ms ease;
  }
  .switch input:checked + .switch-slider {
    background: rgba(214,165,54,0.34);
    border-color: rgba(244,210,122,0.48);
  }
  .switch input:checked + .switch-slider::before {
    background: var(--gold-soft);
    transform: translateX(24px);
  }
  .info-box {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    color: var(--gold-soft);
    background: rgba(214,165,54,0.08);
    border: 1px solid rgba(244,210,122,0.28);
    border-radius: 14px;
    font-size: 13px;
    line-height: 1.6;
    margin: 18px 0 0;
    padding: 14px;
  }
  .events-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 18px;
  }
  .search-wrap {
    width: min(320px, 100%);
  }
  .chips {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }
  .chip {
    color: var(--text);
    background: rgba(255,255,255,0.045);
    border: 1px solid rgba(255,255,255,0.09);
    border-radius: 999px;
    cursor: pointer;
    font: inherit;
    font-size: 12.5px;
    font-weight: 800;
    padding: 10px 14px;
  }
  .chip.active {
    color: #111;
    background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    border-color: transparent;
  }
  .event-list {
    display: grid;
    gap: 14px;
  }
  .event-card {
    border-radius: 20px;
    display: grid;
    grid-template-columns: minmax(260px, 1fr) auto minmax(360px, auto);
    gap: 22px;
    align-items: center;
    padding: 20px;
    transition: transform 180ms ease, border-color 180ms ease, opacity 180ms ease;
  }
  .event-card.archived {
    opacity: .72;
  }
  .event-main {
    display: grid;
    grid-template-columns: 64px minmax(0, 1fr);
    gap: 16px;
    align-items: center;
    min-width: 0;
  }
  .event-logo {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 64px;
    height: 64px;
    border: 1px solid rgba(244,210,122,0.26);
    border-radius: 18px;
    background: rgba(214,165,54,0.08);
    overflow: hidden;
  }
  .event-logo img {
    width: 54px;
    height: auto;
  }
  .event-name-row {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
  }
  .event-name {
    color: var(--text);
    font-size: 18px;
    font-weight: 900;
    line-height: 1.35;
    overflow-wrap: anywhere;
  }
  .status-badge {
    border-radius: 999px;
    font-size: 10.5px;
    font-weight: 900;
    letter-spacing: .06em;
    padding: 6px 9px;
    text-transform: uppercase;
  }
  .status-badge.active {
    color: #BBF7D0;
    background: rgba(34,197,94,0.14);
    border: 1px solid rgba(34,197,94,0.28);
  }
  .status-badge.archived {
    color: var(--muted);
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.10);
  }
  .event-path, .event-created {
    color: var(--muted);
    font-size: 12.5px;
    line-height: 1.6;
  }
  .event-path {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
    margin-top: 5px;
  }
  .event-created {
    margin-top: 2px;
  }
  .event-metrics {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .metric {
    min-width: 104px;
    background: rgba(255,255,255,0.035);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 16px;
    padding: 13px;
    text-align: center;
  }
  .metric strong {
    color: var(--text);
    display: block;
    font-size: 24px;
    line-height: 1;
    margin-bottom: 6px;
  }
  .metric span {
    color: var(--muted);
    font-size: 12px;
  }
  .event-actions {
    display: grid;
    gap: 10px;
  }
  .action-row {
    justify-content: flex-end;
  }
  .event-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 38px;
    border-radius: 11px;
    cursor: pointer;
    font: inherit;
    font-size: 12.5px;
    font-weight: 800;
    padding: 9px 12px;
    text-decoration: none;
    white-space: nowrap;
  }
  .event-action.primary {
    color: #111;
    background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    border-color: transparent;
  }
  .event-action.warning {
    color: #FED7AA;
    background: rgba(245,158,11,0.10);
    border-color: rgba(245,158,11,0.28);
  }
  .event-action.danger {
    color: #FECACA;
    background: rgba(239,68,68,0.10);
    border-color: rgba(239,68,68,0.28);
  }
  .inline-form {
    display: inline-flex;
  }
  .empty-state {
    display: grid;
    place-items: center;
    gap: 12px;
    border: 1px dashed rgba(244,210,122,0.24);
    border-radius: 20px;
    background: rgba(255,255,255,0.03);
    padding: 42px 20px;
    text-align: center;
  }
  .empty-state h3 {
    font-size: 20px;
    font-weight: 900;
  }
  .empty-state p {
    color: var(--muted);
    max-width: 440px;
    line-height: 1.6;
  }
  .footer {
    color: var(--muted);
    font-size: 12px;
    padding-top: 12px;
    text-align: center;
  }
  @media (max-width: 1180px) {
    .event-card {
      grid-template-columns: 1fr;
      align-items: stretch;
    }
    .event-metrics, .action-row {
      justify-content: flex-start;
    }
  }
  @media (max-width: 980px) {
    .hero, .upload-layout {
      grid-template-columns: 1fr;
    }
    .hero-actions {
      justify-content: flex-start;
    }
    .summary-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }
  @media (max-width: 760px) {
    .topbar-inner, .wrap {
      padding-left: 16px;
      padding-right: 16px;
    }
    .topbar-inner {
      display: grid;
      min-height: auto;
      padding-top: 16px;
      padding-bottom: 16px;
    }
    .brand img { width: 112px; }
    .nav {
      justify-content: flex-start;
      gap: 8px;
    }
    .nav a {
      font-size: 12.5px;
      padding: 10px 12px;
    }
    .wrap {
      padding-top: 18px;
    }
    .hero {
      border-radius: 22px;
      padding: 24px;
    }
    .hero-actions, .section-actions, .events-toolbar, .panel-head {
      align-items: stretch;
      flex-direction: column;
    }
    .btn, .search-wrap, .event-search {
      width: 100%;
    }
    .summary-grid {
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }
    .summary-card {
      align-items: flex-start;
      flex-direction: column;
      min-height: 142px;
      padding: 16px;
    }
    .panel {
      border-radius: 20px;
      padding: 16px;
    }
    .steps {
      display: none;
    }
    .file-drop {
      min-height: 176px;
    }
    .switch-row {
      align-items: flex-start;
      flex-direction: column;
    }
    .event-main {
      grid-template-columns: 58px minmax(0, 1fr);
      gap: 12px;
    }
    .event-logo {
      width: 58px;
      height: 58px;
      border-radius: 16px;
    }
    .event-logo img {
      width: 48px;
    }
    .event-name {
      font-size: 15px;
    }
    .event-metrics {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
    }
    .metric {
      min-width: 0;
      padding: 11px;
    }
    .action-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 8px;
    }
    .event-action {
      width: 100%;
      white-space: normal;
    }
  }
  @media (max-width: 460px) {
    h1 {
      font-size: 34px;
    }
    .summary-grid {
      grid-template-columns: 1fr;
    }
    .action-row {
      grid-template-columns: 1fr;
    }
  }
</style>
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="dashboard.php" aria-label="RahasiaEmas.id Admin">
      <img src="<?= htmlspecialchars($logoPath) ?>" alt="RahasiaEmas.id">
    </a>
    <nav class="nav" aria-label="Navigasi admin">
      <a href="dashboard.php">Dashboard</a>
      <a href="events.php" class="active">Kelola Event</a>
      <a href="logout.php" class="logout">Keluar</a>
    </nav>
  </div>
</header>

<main class="wrap">
  <section class="hero" aria-labelledby="page-title">
    <div class="hero-copy">
      <span class="eyebrow">Event Control Center</span>
      <h1 id="page-title">Kelola Event</h1>
      <p class="subtitle">Upload, publikasikan, dan kontrol seluruh event referral dari satu tempat.</p>
    </div>
    <div class="hero-actions">
      <a class="btn btn-gold" href="#upload-event">Upload Event Baru</a>
      <a class="btn btn-outline" href="dashboard.php">Kembali ke Dashboard</a>
    </div>
  </section>

  <?php if ($notice): ?>
    <div class="notice <?= htmlspecialchars($noticeType) ?>"><?= $notice /* sudah di-escape di titik penyusunan pesan */ ?></div>
  <?php endif; ?>

  <section class="summary-grid" aria-label="Ringkasan event">
    <article class="summary-card">
      <span class="icon-badge" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </span>
      <div>
        <div class="summary-label">Total Event</div>
        <div class="summary-num"><?= (int)$totalEvents ?></div>
        <p class="summary-copy">Semua event terdaftar</p>
      </div>
    </article>
    <article class="summary-card">
      <span class="icon-badge" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="m9 12 2 2 4-5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </span>
      <div>
        <div class="summary-label">Event Aktif</div>
        <div class="summary-num"><?= (int)$activeEvents ?></div>
        <p class="summary-copy">Sedang berlangsung</p>
      </div>
    </article>
    <article class="summary-card">
      <span class="icon-badge" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.87M10 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </span>
      <div>
        <div class="summary-label">Total Pendaftar</div>
        <div class="summary-num"><?= (int)$totalLeads ?></div>
        <p class="summary-copy">Dari semua event</p>
      </div>
    </article>
    <article class="summary-card">
      <span class="icon-badge" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m16-10v6m3-3h-6M10 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </span>
      <div>
        <div class="summary-label">Total Pengundang</div>
        <div class="summary-num"><?= (int)$totalReferrers ?></div>
        <p class="summary-copy">Dari semua event</p>
      </div>
    </article>
  </section>

  <section class="panel" id="upload-event">
    <div class="panel-head">
      <div>
        <h2>
          <span class="icon-badge" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
          Upload Event Baru
        </h2>
        <p class="desc">Upload file ZIP berisi <strong>index.html</strong>, <strong>config.json</strong>, dan folder <strong>assets</strong> sesuai struktur yang ditentukan.</p>
      </div>
      <div class="steps" aria-label="Langkah upload">
        <span class="step"><span class="step-num">1</span>Pilih ZIP</span>
        <span class="step-arrow">→</span>
        <span class="step"><span class="step-num">2</span>Atur Slug</span>
        <span class="step-arrow">→</span>
        <span class="step"><span class="step-num">3</span>Publikasikan</span>
      </div>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <div class="upload-layout">
        <div>
          <label class="file-drop">
            <input id="eventZip" type="file" name="event_zip" accept=".zip" required>
            <span>
              <span class="upload-icon" aria-hidden="true">
                <svg width="42" height="42" viewBox="0 0 24 24" fill="none"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4m14-7-5-5-5 5m5-5v12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </span>
              <span class="file-title">Pilih file ZIP event</span>
              <span class="file-subtitle">atau seret dan lepas file di sini</span>
              <span class="file-subtitle">Format: .zip · Maks. <?= htmlspecialchars((string)$maxZipMb) ?>MB</span>
              <span class="file-name" id="fileName"></span>
            </span>
          </label>
          <button type="submit" class="btn btn-gold" style="margin-top:16px;">Upload & Publikasikan</button>
        </div>
        <div>
          <div class="field">
            <label for="slug_override">Slug URL</label>
            <input id="slug_override" type="text" name="slug_override" placeholder="contoh: rahasia-investasi-emas">
            <div class="helper">Hasil akhir: <strong>rahasiaemas.id/e/contoh-slug</strong></div>
          </div>
          <div class="switch-row">
            <div>
              <span class="switch-label">Timpa event yang sudah ada</span>
              <span class="helper">Aktifkan jika slug sudah dipakai dan ingin memperbarui landing page.</span>
            </div>
            <label class="switch" for="allow_overwrite">
              <input type="checkbox" name="allow_overwrite" id="allow_overwrite">
              <span class="switch-slider"></span>
            </label>
          </div>
          <div class="info-box">
            <span aria-hidden="true">ⓘ</span>
            <span>Pastikan ZIP memiliki file <strong>index.html</strong> dan <strong>config.json</strong> di root folder.</span>
          </div>
        </div>
      </div>
    </form>
  </section>

  <section class="panel" id="event-list">
    <div class="events-toolbar">
      <div>
        <h2>
          <span class="icon-badge" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
          Semua Event
        </h2>
        <p class="desc">Kelola halaman, referral link, challenge, hadiah, dan tracking setiap event.</p>
      </div>
      <div class="section-actions">
        <div class="search-wrap">
          <input class="event-search" id="eventSearch" type="search" placeholder="Cari event..." aria-label="Cari event">
        </div>
        <div class="chips" aria-label="Filter event">
          <button class="chip active" type="button" data-filter="all">Semua</button>
          <button class="chip" type="button" data-filter="active">Aktif</button>
          <button class="chip" type="button" data-filter="archived">Nonaktif</button>
        </div>
      </div>
    </div>

    <?php if (empty($events)): ?>
      <div class="empty-state">
        <span class="icon-badge" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm7 10h4m-2-2v4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
        <h3>Belum ada event</h3>
        <p>Upload ZIP event pertama untuk mulai menjalankan campaign referral.</p>
        <a class="btn btn-gold" href="#upload-event">Upload Event Baru</a>
      </div>
    <?php else: ?>
      <div class="event-list" id="eventsList">
      <?php foreach ($events as $ev): ?>
        <?php
          $eventStatus = $ev['status'] === 'active' ? 'active' : 'archived';
          $eventPath = $ev['slug'] === $defaultEventSlug ? '/ (root domain)' : '/e/' . $ev['slug'] . '/';
          $eventUrl = $ev['slug'] === $defaultEventSlug ? '/' : EVENTS_URL_BASE . '/' . rawurlencode($ev['slug']) . '/';
        ?>
        <article class="event-card <?= $eventStatus === 'archived' ? 'archived' : '' ?>" data-status="<?= htmlspecialchars($eventStatus) ?>" data-search="<?= htmlspecialchars(strtolower($ev['name'] . ' ' . $ev['slug'] . ' ' . $eventStatus)) ?>">
          <div class="event-main">
            <span class="event-logo"><img src="<?= htmlspecialchars($logoPath) ?>" alt=""></span>
            <div>
              <div class="event-name-row">
                <h3 class="event-name"><?= htmlspecialchars($ev['name']) ?></h3>
                <span class="status-badge <?= htmlspecialchars($eventStatus) ?>"><?= $eventStatus === 'active' ? 'Aktif' : 'Nonaktif' ?></span>
              </div>
              <div class="event-path"><?= htmlspecialchars($eventPath) ?></div>
              <?php if (!empty($ev['created_at'])): ?>
                <div class="event-created">Dibuat: <?= htmlspecialchars(date('d M Y, H:i', strtotime($ev['created_at']))) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <div class="event-metrics">
            <div class="metric"><strong><?= (int)$ev['total_leads'] ?></strong><span>Pendaftar</span></div>
            <div class="metric"><strong><?= (int)$ev['total_referrers'] ?></strong><span>Pengundang</span></div>
          </div>

          <div class="event-actions">
            <div class="action-row">
              <a class="event-action primary" href="<?= htmlspecialchars($eventUrl) ?>" target="_blank" rel="noopener">Lihat Halaman</a>
              <a class="event-action primary" href="/buat-link.php?event=<?= urlencode($ev['slug']) ?>" target="_blank" rel="noopener">Buat Link</a>
            </div>
            <div class="action-row">
              <a class="event-action" href="event-settings.php?event=<?= urlencode($ev['slug']) ?>">Detail Acara</a>
              <a class="event-action" href="/challenge/?event=<?= urlencode($ev['slug']) ?>" target="_blank" rel="noopener">Challenge</a>
              <a class="event-action" href="rewards.php?event=<?= urlencode($ev['slug']) ?>">Atur Hadiah</a>
              <a class="event-action" href="tracking.php?event=<?= urlencode($ev['slug']) ?>">Tracking</a>
              <?php if ($ev['slug'] !== $defaultEventSlug): ?>
                <form class="inline-form" method="POST">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                  <input type="hidden" name="toggle_status" value="1">
                  <input type="hidden" name="slug" value="<?= htmlspecialchars($ev['slug']) ?>">
                  <button class="event-action <?= $eventStatus === 'active' ? 'danger' : 'warning' ?>" type="submit" onclick="return confirm('Yakin ingin <?= $eventStatus === 'active' ? 'mengarsipkan' : 'mengaktifkan kembali' ?> event ini?')">
                    <?= $eventStatus === 'active' ? 'Arsipkan' : 'Aktifkan' ?>
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <div class="footer">© <?= date('Y') ?> RahasiaEmas.id — All rights reserved.</div>
</main>
<script>
  const zipInput = document.getElementById('eventZip');
  const fileName = document.getElementById('fileName');
  if (zipInput && fileName) {
    zipInput.addEventListener('change', () => {
      fileName.textContent = zipInput.files.length ? zipInput.files[0].name : '';
    });
  }

  const eventSearch = document.getElementById('eventSearch');
  const eventCards = Array.from(document.querySelectorAll('.event-card'));
  const chips = Array.from(document.querySelectorAll('.chip[data-filter]'));
  let activeFilter = 'all';

  function filterEvents() {
    const keyword = eventSearch ? eventSearch.value.trim().toLowerCase() : '';
    eventCards.forEach((card) => {
      const matchesKeyword = card.dataset.search.includes(keyword);
      const matchesFilter = activeFilter === 'all' || card.dataset.status === activeFilter;
      card.style.display = matchesKeyword && matchesFilter ? '' : 'none';
    });
  }

  chips.forEach((chip) => {
    chip.addEventListener('click', () => {
      activeFilter = chip.dataset.filter;
      chips.forEach((item) => item.classList.toggle('active', item === chip));
      filterEvents();
    });
  });

  if (eventSearch) {
    eventSearch.addEventListener('input', filterEvents);
  }
</script>
</body>
</html>
