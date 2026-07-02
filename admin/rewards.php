<?php
require_once __DIR__ . '/../config.php';
start_secure_session();

if (empty($_SESSION['admin_authenticated'])) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pdo = get_db();

$eventSlug = clean($_GET['event'] ?? '');
$event = $eventSlug !== '' ? get_event_by_slug($eventSlug) : null;
$eventNotFound = !$event;
$notice = null;
$noticeType = 'success'; // success | error

if (!$eventNotFound && isset($_GET['saved'])) {
    $notice = 'Pengaturan hadiah berhasil diperbarui.';
    $noticeType = 'success';
}

// ==================== HANDLE ACTIONS ====================
if (!$eventNotFound && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $notice = 'Sesi tidak valid. Silakan refresh halaman lalu coba lagi.';
        $noticeType = 'error';
    } else {
        // ---- Simpan hadiah per peringkat (1-10, semua opsional) ----
        for ($rank = 1; $rank <= 10; $rank++) {
            $text = trim($_POST['reward_' . $rank] ?? '');
            if ($text === '') {
                $stmt = $pdo->prepare('DELETE FROM event_rewards WHERE event_slug = ? AND rank = ?');
                $stmt->execute([$eventSlug, $rank]);
            } else {
                $stmt = $pdo->prepare('
                    INSERT INTO event_rewards (event_slug, rank, reward_text)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE reward_text = VALUES(reward_text)
                ');
                $stmt->execute([$eventSlug, $rank, $text]);
            }
        }

        // ---- Hapus gambar jika diminta ----
        if (isset($_POST['remove_image']) && !empty($event['reward_image'])) {
            delete_reward_image($event['reward_image']);
            $stmt = $pdo->prepare('UPDATE events SET reward_image = NULL WHERE slug = ?');
            $stmt->execute([$eventSlug]);
            $event['reward_image'] = null;
        }

        // ---- Upload gambar baru jika ada ----
        if (isset($_FILES['reward_image']) && $_FILES['reward_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['reward_image'];
            if ($file['size'] > MAX_REWARD_IMAGE_SIZE) {
                $notice = 'Ukuran gambar terlalu besar. Maksimal ' . (MAX_REWARD_IMAGE_SIZE / 1024 / 1024) . ' MB.';
                $noticeType = 'error';
            } else {
                $imagePath = save_reward_image($file['tmp_name'], $file['name'], $eventSlug);
                if (!$imagePath) {
                    $notice = 'Gagal upload gambar. Pastikan file adalah gambar dengan format yang diizinkan.';
                    $noticeType = 'error';
                } else {
                    $stmt = $pdo->prepare('UPDATE events SET reward_image = ? WHERE slug = ?');
                    $stmt->execute([$imagePath, $eventSlug]);
                    $event['reward_image'] = $imagePath;
                }
            }
        }

        if (!$notice) {
            header('Location: rewards.php?event=' . urlencode($eventSlug) . '&saved=1');
            exit;
        }
    }
}

$rewards = $eventNotFound ? [] : get_event_rewards($eventSlug);
$rewardByRank = [];
foreach ($rewards as $r) {
    $rewardByRank[(int)$r['rank']] = $r['reward_text'];
}

$activeRanks = array_keys(array_filter($rewardByRank, static fn ($value) => trim((string)$value) !== ''));
if (empty($activeRanks)) {
    $activeRanks = [1, 2, 3];
}
$activeRankMap = array_fill_keys($activeRanks, true);
$visibleRewards = array_filter($rewardByRank, static fn ($value) => trim((string)$value) !== '');
$maxRewardImageMb = (int)(MAX_REWARD_IMAGE_SIZE / 1024 / 1024);
$logoPath = file_exists(__DIR__ . '/../assets/logo.png') ? '../assets/logo.png' : '/assets/img/logo-rahasiaemas.png';
$pageTitle = $eventNotFound ? 'Event Tidak Ditemukan' : $event['name'];
$challengeUrl = $eventNotFound ? '#' : '/challenge/?event=' . urlencode($eventSlug);

function reward_medal_label(int $rank): string
{
    return '#' . $rank;
}

function reward_preview_class(int $rank): string
{
    if ($rank === 1) return 'gold';
    if ($rank === 2) return 'silver';
    if ($rank === 3) return 'bronze';
    return 'default';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Atur Hadiah - <?= htmlspecialchars($pageTitle) ?> - RahasiaEmas.id</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
<style>
  :root {
    --bg:#0B0B0A;
    --bg-soft:#10100F;
    --surface:#171716;
    --surface-elevated:#20201E;
    --border-gold:rgba(214,165,54,0.18);
    --border-strong:rgba(214,165,54,0.34);
    --border-soft:rgba(255,255,255,0.09);
    --gold:#D6A536;
    --gold-soft:#F4D27A;
    --text:#F7F3E8;
    --muted:#A8A29A;
    --success:#22C55E;
    --danger:#EF4444;
    --warning:#F59E0B;
    --shadow:0 24px 80px rgba(0,0,0,0.34);
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html { scroll-behavior: smooth; }
  body {
    min-height: 100vh;
    background:
      radial-gradient(circle at 88% 4%, rgba(214,165,54,0.22), transparent 30vw),
      radial-gradient(circle at 8% 92%, rgba(214,165,54,0.13), transparent 34vw),
      linear-gradient(135deg, var(--bg) 0%, var(--bg-soft) 54%, #080807 100%);
    color: var(--text);
    font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  }
  body::before {
    content:"";
    position: fixed;
    inset: 0;
    pointer-events: none;
    background-image:
      linear-gradient(rgba(255,255,255,0.024) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,0.016) 1px, transparent 1px);
    background-size: 52px 52px;
    mask-image: radial-gradient(circle at 50% 16%, black, transparent 72%);
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
    width: min(100%, 1280px);
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
  .brand { display: inline-flex; align-items: center; text-decoration: none; }
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
  .nav a, .btn, .reward-row, .panel, .hero, .dropzone, .preview-reward {
    transition: transform 180ms ease, border-color 180ms ease, background 180ms ease, color 180ms ease, box-shadow 180ms ease, opacity 180ms ease;
  }
  .nav a {
    color: var(--muted);
    display: inline-flex;
    align-items: center;
    min-height: 42px;
    padding: 10px 14px;
    border-radius: 999px;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    border: 1px solid transparent;
  }
  .nav a:hover, .nav a.active {
    color: var(--gold-soft);
    background: rgba(214,165,54,0.09);
    border-color: rgba(214,165,54,0.18);
  }
  .nav a.logout {
    color: var(--text);
    background: rgba(255,255,255,0.035);
    border-color: rgba(255,255,255,0.10);
  }
  .wrap {
    position: relative;
    z-index: 1;
    padding-top: 26px;
    padding-bottom: 126px;
  }
  .breadcrumb {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--muted);
    font-size: 13px;
    margin-bottom: 18px;
  }
  .breadcrumb a {
    color: var(--gold-soft);
    text-decoration: none;
    font-weight: 700;
  }
  .hero {
    position: relative;
    overflow: hidden;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 24px;
    padding: 32px 34px;
    margin-bottom: 20px;
    border: 1px solid var(--border-gold);
    border-radius: 28px;
    background:
      radial-gradient(circle at 94% 22%, rgba(244,210,122,0.24), transparent 22%),
      linear-gradient(135deg, rgba(255,255,255,0.06), rgba(255,255,255,0.02)),
      linear-gradient(135deg, rgba(214,165,54,0.12), rgba(23,23,22,0.88));
    box-shadow: var(--shadow);
  }
  .hero::after {
    content:"R";
    position:absolute;
    right:28px;
    top:-34px;
    color: rgba(214,165,54,0.08);
    font-family: Georgia, serif;
    font-size: 220px;
    font-weight: 900;
    line-height: 1;
  }
  .eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    width: fit-content;
    color: var(--gold-soft);
    background: rgba(214,165,54,0.12);
    border: 1px solid rgba(214,165,54,0.24);
    border-radius: 999px;
    padding: 8px 12px;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: .02em;
    margin-bottom: 14px;
  }
  .icon-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    width: 40px;
    height: 40px;
    color: var(--gold-soft);
    border: 1px solid rgba(214,165,54,0.28);
    border-radius: 14px;
    background: rgba(214,165,54,0.12);
    box-shadow: inset 0 0 22px rgba(214,165,54,0.08);
    font-size: 12px;
    font-weight: 900;
  }
  h1 {
    max-width: 860px;
    font-family: "Playfair Display", Georgia, serif;
    font-size: clamp(34px, 4.5vw, 54px);
    line-height: 1.04;
    letter-spacing: 0;
  }
  h1 span { color: var(--gold); }
  .hero p {
    max-width: 680px;
    color: var(--muted);
    font-size: 16px;
    line-height: 1.7;
    margin-top: 14px;
  }
  .hero-actions {
    position: relative;
    z-index: 1;
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
    padding: 12px 17px;
    border-radius: 13px;
    border: 1px solid rgba(214,165,54,0.22);
    background: rgba(255,255,255,0.04);
    color: var(--text);
    font-family: inherit;
    font-size: 14px;
    font-weight: 900;
    text-decoration: none;
    cursor: pointer;
  }
  .btn:hover {
    transform: translateY(-1px);
    border-color: rgba(244,210,122,0.42);
  }
  .btn-primary {
    color: #111;
    background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    border-color: transparent;
    box-shadow: 0 18px 44px rgba(214,165,54,0.22);
  }
  .btn-secondary {
    background: rgba(255,255,255,0.035);
    border-color: rgba(255,255,255,0.12);
  }
  .btn-danger {
    color: #FECACA;
    border-color: rgba(239,68,68,0.35);
    background: rgba(239,68,68,0.08);
  }
  .notice {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px 18px;
    border-radius: 18px;
    margin: 0 0 18px;
    font-size: 14px;
    font-weight: 800;
    border: 1px solid rgba(255,255,255,0.10);
    background: rgba(255,255,255,0.045);
  }
  .notice.success {
    color: #B9F6CC;
    border-color: rgba(34,197,94,0.24);
    background: rgba(34,197,94,0.10);
  }
  .notice.error {
    color: #FECACA;
    border-color: rgba(239,68,68,0.24);
    background: rgba(239,68,68,0.10);
  }
  .main-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.16fr) minmax(360px, .84fr);
    gap: 22px;
    align-items: start;
  }
  .left-stack, .right-stack { display: grid; gap: 20px; }
  .panel {
    overflow: hidden;
    border: 1px solid var(--border-gold);
    border-radius: 24px;
    background: linear-gradient(180deg, rgba(255,255,255,0.045), rgba(255,255,255,0.02));
    box-shadow: 0 18px 60px rgba(0,0,0,0.24);
  }
  .panel-head {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 24px 26px 18px;
    border-bottom: 1px solid rgba(214,165,54,0.12);
  }
  h2 {
    font-family: "Playfair Display", Georgia, serif;
    color: var(--gold-soft);
    font-size: 24px;
    line-height: 1.1;
  }
  .desc {
    color: var(--muted);
    font-size: 13.5px;
    line-height: 1.6;
    margin-top: 6px;
  }
  .panel-body {
    padding: 22px 26px 26px;
  }
  .reward-list {
    display: grid;
    gap: 10px;
  }
  .reward-row {
    display: grid;
    grid-template-columns: 66px 118px minmax(0, 1fr) 46px;
    gap: 12px;
    align-items: center;
    padding: 12px;
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 18px;
    background: linear-gradient(135deg, rgba(214,165,54,0.10), rgba(255,255,255,0.025));
  }
  .reward-row.is-hidden { display: none; }
  .rank-medal {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 54px;
    height: 54px;
    border-radius: 50%;
    color: #111;
    background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    border: 2px solid rgba(255,255,255,0.20);
    font-size: 18px;
    font-weight: 1000;
    box-shadow: 0 12px 26px rgba(214,165,54,0.22);
  }
  .rank-label {
    color: var(--text);
    font-weight: 900;
    white-space: nowrap;
  }
  .reward-row input[type="text"] {
    width: 100%;
    min-height: 46px;
    color: var(--text);
    background: #111110;
    border: 1px solid rgba(255,255,255,0.13);
    border-radius: 12px;
    padding: 12px 14px;
    font: inherit;
    outline: none;
  }
  .reward-row input:focus {
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(214,165,54,0.14);
  }
  .remove-row {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    border: 1px solid rgba(239,68,68,0.28);
    background: rgba(239,68,68,0.08);
    color: #FCA5A5;
    font-weight: 1000;
    cursor: pointer;
  }
  .builder-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-top: 14px;
    color: var(--muted);
    font-size: 12.5px;
  }
  .upload-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.45fr) minmax(220px, .75fr);
    gap: 18px;
    align-items: stretch;
  }
  .current-image-wrap {
    overflow: hidden;
    border-radius: 18px;
    border: 1px solid rgba(214,165,54,0.18);
    background: rgba(8,8,7,0.32);
    aspect-ratio: 16 / 9;
  }
  .current-image {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: cover;
  }
  .image-empty {
    display: grid;
    place-items: center;
    height: 100%;
    color: var(--muted);
    text-align: center;
    padding: 22px;
  }
  .upload-actions {
    display: grid;
    gap: 10px;
    align-content: start;
  }
  .dropzone {
    position: relative;
    display: grid;
    place-items: center;
    min-height: 122px;
    padding: 18px;
    border: 1px dashed rgba(244,210,122,0.42);
    border-radius: 16px;
    background: rgba(255,255,255,0.025);
    color: var(--text);
    text-align: center;
    cursor: pointer;
  }
  .dropzone:hover {
    border-color: rgba(244,210,122,0.70);
    background: rgba(214,165,54,0.07);
  }
  .dropzone input {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
  }
  .dropzone strong {
    display: block;
    color: var(--gold-soft);
    margin-bottom: 4px;
  }
  .dropzone span {
    color: var(--muted);
    font-size: 12px;
    line-height: 1.5;
  }
  .remove-image-check {
    position: absolute;
    opacity: 0;
    pointer-events: none;
  }
  .preview-stack {
    display: grid;
    gap: 10px;
  }
  .preview-image {
    overflow: hidden;
    border: 1px solid rgba(214,165,54,0.18);
    border-radius: 18px;
    aspect-ratio: 16 / 9;
    background: rgba(8,8,7,0.30);
    margin-bottom: 14px;
  }
  .preview-image img {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: cover;
  }
  .preview-reward {
    position: relative;
    overflow: hidden;
    display: grid;
    grid-template-columns: 64px minmax(0, 1fr);
    gap: 16px;
    align-items: center;
    min-height: 104px;
    padding: 16px 18px;
    border: 1px solid rgba(255,255,255,0.10);
    border-radius: 18px;
    background: linear-gradient(135deg, rgba(255,255,255,0.055), rgba(255,255,255,0.025));
  }
  .preview-reward::after {
    content:"";
    position:absolute;
    right:-30px;
    bottom:-42px;
    width:142px;
    height:142px;
    border:1px solid rgba(244,210,122,0.12);
    border-radius:50%;
  }
  .preview-reward.gold {
    border-color: rgba(244,210,122,0.42);
    background: linear-gradient(135deg, rgba(214,165,54,0.30), rgba(214,165,54,0.08));
    box-shadow: 0 18px 48px rgba(214,165,54,0.14);
  }
  .preview-reward.silver {
    background: linear-gradient(135deg, rgba(220,220,220,0.15), rgba(255,255,255,0.025));
  }
  .preview-reward.bronze {
    background: linear-gradient(135deg, rgba(184,115,51,0.22), rgba(255,255,255,0.025));
    border-color: rgba(184,115,51,0.34);
  }
  .preview-rank {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 54px;
    height: 54px;
    border-radius: 50%;
    color: #111;
    background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    font-weight: 1000;
    font-size: 18px;
  }
  .preview-title {
    color: var(--gold-soft);
    font-size: 13px;
    font-weight: 900;
    margin-bottom: 5px;
  }
  .preview-text {
    color: var(--text);
    font-size: 18px;
    font-weight: 900;
    line-height: 1.4;
  }
  .empty-preview {
    padding: 22px;
    border: 1px dashed rgba(214,165,54,0.24);
    border-radius: 18px;
    color: var(--muted);
    background: rgba(255,255,255,0.025);
    line-height: 1.6;
  }
  .tips-list {
    display: grid;
    gap: 12px;
    color: var(--text);
    font-size: 14px;
  }
  .tips-list li {
    display: grid;
    grid-template-columns: 26px minmax(0, 1fr);
    gap: 10px;
    align-items: start;
    list-style: none;
    color: var(--muted);
    line-height: 1.6;
  }
  .tips-list li::before {
    content:"";
    width: 20px;
    height: 20px;
    margin-top: 1px;
    border-radius: 50%;
    background: radial-gradient(circle, var(--gold-soft), var(--gold));
    box-shadow: 0 0 22px rgba(214,165,54,0.18);
  }
  .save-bar {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 30;
    border-top: 1px solid rgba(214,165,54,0.22);
    background: rgba(16,16,15,0.88);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
  }
  .save-inner {
    width: min(100%, 1280px);
    min-height: 86px;
    margin: 0 auto;
    padding: 16px 32px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
  }
  .dirty-note {
    color: var(--muted);
    font-size: 14px;
    font-weight: 700;
  }
  .save-actions {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .empty-state {
    display: grid;
    place-items: center;
    min-height: 420px;
    text-align: center;
  }
  .empty-card {
    width: min(100%, 620px);
    padding: 34px;
    border: 1px solid var(--border-gold);
    border-radius: 26px;
    background: linear-gradient(180deg, rgba(255,255,255,0.055), rgba(255,255,255,0.02));
    box-shadow: var(--shadow);
  }
  .empty-card .icon-badge {
    width: 54px;
    height: 54px;
    margin: 0 auto 16px;
  }
  .empty-card h1 {
    font-size: clamp(32px, 5vw, 46px);
    margin-bottom: 10px;
  }
  .empty-card p {
    color: var(--muted);
    line-height: 1.7;
    margin-bottom: 22px;
  }
  @media (max-width: 1060px) {
    .hero, .main-grid, .upload-layout {
      grid-template-columns: 1fr;
    }
    .hero-actions {
      justify-content: flex-start;
    }
  }
  @media (max-width: 760px) {
    .topbar-inner, .wrap, .save-inner {
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
      min-height: 38px;
      padding: 9px 11px;
    }
    .wrap {
      padding-top: 18px;
      padding-bottom: 156px;
    }
    .breadcrumb {
      flex-wrap: wrap;
    }
    .hero {
      border-radius: 22px;
      padding: 24px;
    }
    .hero-actions, .builder-actions, .save-inner, .save-actions {
      align-items: stretch;
      flex-direction: column;
    }
    .btn {
      width: 100%;
    }
    .panel {
      border-radius: 20px;
    }
    .panel-head, .panel-body {
      padding-left: 18px;
      padding-right: 18px;
    }
    .reward-row {
      grid-template-columns: 58px minmax(0, 1fr) 42px;
      gap: 10px;
    }
    .rank-label {
      grid-column: 2 / 3;
    }
    .reward-row input[type="text"] {
      grid-column: 1 / -1;
    }
    .remove-row {
      grid-column: 3;
      grid-row: 1;
    }
    .preview-reward {
      grid-template-columns: 56px minmax(0, 1fr);
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
      <a class="active" href="events.php">Kelola Event</a>
      <a class="logout" href="logout.php">Keluar</a>
    </nav>
  </div>
</header>

<main class="wrap">
  <div class="breadcrumb">
    <a href="events.php">Kelola Event</a>
    <span>/</span>
    <span>Atur Hadiah</span>
  </div>

  <?php if ($eventNotFound): ?>
    <section class="empty-state">
      <div class="empty-card">
        <span class="icon-badge">EV</span>
        <h1>Event Tidak Ditemukan</h1>
        <p>Event yang Anda cari tidak tersedia atau sudah dihapus. Silakan kembali ke daftar event untuk memilih acara yang aktif.</p>
        <a class="btn btn-primary" href="events.php">Kembali ke Kelola Event</a>
      </div>
    </section>
  <?php else: ?>
    <section class="hero">
      <div>
        <span class="eyebrow"><span class="icon-badge">GIFT</span> Challenge Reward Builder</span>
        <h1>Atur Hadiah - <span><?= htmlspecialchars($event['name']) ?></span></h1>
        <p>Tentukan hadiah peringkat yang akan tampil di halaman challenge publik.</p>
      </div>
      <div class="hero-actions">
        <a class="btn btn-secondary" href="<?= htmlspecialchars($challengeUrl) ?>" target="_blank" rel="noopener">Lihat Challenge</a>
        <a class="btn btn-primary" href="events.php">Kembali ke Kelola Event</a>
      </div>
    </section>

    <?php if ($notice): ?>
      <div class="notice <?= htmlspecialchars($noticeType) ?>">
        <span class="icon-badge"><?= $noticeType === 'success' ? 'OK' : '!' ?></span>
        <span><?= htmlspecialchars($notice) ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="rewardForm">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

      <div class="main-grid">
        <div class="left-stack">
          <section class="panel">
            <div class="panel-head">
              <span class="icon-badge">RANK</span>
              <div>
                <h2>Hadiah per Peringkat</h2>
                <p class="desc">Isi hanya peringkat yang punya hadiah. Peringkat kosong tidak tampil di halaman challenge publik.</p>
              </div>
            </div>
            <div class="panel-body">
              <div class="reward-list" id="rewardList">
                <?php for ($rank = 1; $rank <= 10; $rank++): ?>
                  <div class="reward-row <?= isset($activeRankMap[$rank]) ? '' : 'is-hidden' ?>" data-rank="<?= $rank ?>">
                    <span class="rank-medal"><?= htmlspecialchars(reward_medal_label($rank)) ?></span>
                    <div class="rank-label">Juara <?= $rank ?></div>
                    <input type="text" name="reward_<?= $rank ?>" maxlength="255" placeholder="contoh: Voucher belanja emas 1 gram" value="<?= htmlspecialchars($rewardByRank[$rank] ?? '') ?>" data-reward-input>
                    <button type="button" class="remove-row" data-remove-rank="<?= $rank ?>" aria-label="Hapus hadiah peringkat <?= $rank ?>">x</button>
                  </div>
                <?php endfor; ?>
              </div>

              <div class="builder-actions">
                <button type="button" class="btn btn-secondary" id="addRewardBtn">+ Tambah Hadiah Peringkat</button>
                <span id="maxRankText">Maksimal 10 peringkat hadiah.</span>
              </div>
            </div>
          </section>

          <section class="panel">
            <div class="panel-head">
              <span class="icon-badge">IMG</span>
              <div>
                <h2>Gambar Info Hadiah</h2>
                <p class="desc">Poster atau infografis hadiah yang tampil di halaman challenge publik.</p>
              </div>
            </div>
            <div class="panel-body">
              <div class="upload-layout">
                <div class="current-image-wrap">
                  <?php if (!empty($event['reward_image'])): ?>
                    <img src="<?= htmlspecialchars($event['reward_image']) ?>" alt="Gambar hadiah saat ini" class="current-image">
                  <?php else: ?>
                    <div class="image-empty">Belum ada gambar hadiah. Upload poster 16:9 agar challenge terlihat lebih menarik.</div>
                  <?php endif; ?>
                </div>

                <div class="upload-actions">
                  <?php if (!empty($event['reward_image'])): ?>
                    <input type="checkbox" name="remove_image" id="remove_image" class="remove-image-check">
                    <label class="btn btn-danger" for="remove_image" id="removeImageBtn">Hapus Gambar</label>
                  <?php endif; ?>
                  <label class="dropzone">
                    <input type="file" name="reward_image" accept=".png,.jpg,.jpeg,.webp,.gif" id="rewardImageInput">
                    <span>
                      <strong>Ganti Gambar</strong>
                      <span id="fileNameText">Drag & drop atau klik untuk memilih file</span>
                    </span>
                  </label>
                  <p class="desc">PNG, JPG, JPEG, WEBP, GIF. Maksimal <?= $maxRewardImageMb ?> MB.</p>
                </div>
              </div>
            </div>
          </section>
        </div>

        <aside class="right-stack">
          <section class="panel">
            <div class="panel-head">
              <span class="icon-badge">VIEW</span>
              <div>
                <h2>Preview Hadiah di Halaman Challenge</h2>
                <p class="desc">Preview ini membantu memastikan hadiah tampil jelas sebelum disimpan.</p>
              </div>
            </div>
            <div class="panel-body">
              <?php if (!empty($event['reward_image'])): ?>
                <div class="preview-image">
                  <img src="<?= htmlspecialchars($event['reward_image']) ?>" alt="Preview gambar hadiah">
                </div>
              <?php endif; ?>

              <div class="preview-stack" id="previewStack">
                <?php if (!empty($visibleRewards)): ?>
                  <?php foreach ($visibleRewards as $rank => $rewardText): ?>
                    <article class="preview-reward <?= htmlspecialchars(reward_preview_class((int)$rank)) ?>" data-preview-rank="<?= (int)$rank ?>">
                      <span class="preview-rank"><?= htmlspecialchars(reward_medal_label((int)$rank)) ?></span>
                      <div>
                        <div class="preview-title">Juara <?= (int)$rank ?></div>
                        <div class="preview-text"><?= htmlspecialchars($rewardText) ?></div>
                      </div>
                    </article>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div class="empty-preview" id="emptyPreview">Belum ada hadiah yang tersimpan. Isi minimal satu peringkat untuk menampilkan preview.</div>
                <?php endif; ?>
              </div>
            </div>
          </section>

          <section class="panel">
            <div class="panel-head">
              <span class="icon-badge">TIP</span>
              <div>
                <h2>Tips Hadiah Efektif</h2>
                <p class="desc">Buat hadiah singkat, jelas, dan mudah dipahami peserta.</p>
              </div>
            </div>
            <div class="panel-body">
              <ul class="tips-list">
                <li>Gunakan hadiah yang spesifik dan mudah dipahami.</li>
                <li>Prioritaskan hadiah untuk peringkat 1-3.</li>
                <li>Kosongkan peringkat yang tidak dipakai.</li>
                <li>Gunakan gambar hadiah agar halaman challenge lebih menarik.</li>
              </ul>
            </div>
          </section>
        </aside>
      </div>

      <div class="save-bar">
        <div class="save-inner">
          <div class="dirty-note" id="dirtyNote">Siap menyimpan pengaturan hadiah.</div>
          <div class="save-actions">
            <a class="btn btn-secondary" href="events.php">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan Pengaturan Hadiah</button>
          </div>
        </div>
      </div>
    </form>
  <?php endif; ?>
</main>

<script>
  const rewardRows = Array.from(document.querySelectorAll('.reward-row'));
  const addRewardBtn = document.getElementById('addRewardBtn');
  const maxRankText = document.getElementById('maxRankText');
  const rewardForm = document.getElementById('rewardForm');
  const dirtyNote = document.getElementById('dirtyNote');
  const fileInput = document.getElementById('rewardImageInput');
  const fileNameText = document.getElementById('fileNameText');
  const removeImageBtn = document.getElementById('removeImageBtn');
  const removeImageCheck = document.getElementById('remove_image');

  function markDirty() {
    if (dirtyNote) {
      dirtyNote.textContent = 'Ada perubahan yang belum disimpan.';
      dirtyNote.style.color = 'var(--warning)';
    }
  }

  function updateAddButton() {
    if (!addRewardBtn) return;
    const hiddenRows = rewardRows.filter((row) => row.classList.contains('is-hidden'));
    addRewardBtn.disabled = hiddenRows.length === 0;
    addRewardBtn.style.opacity = hiddenRows.length === 0 ? '.54' : '1';
    if (maxRankText) {
      maxRankText.textContent = hiddenRows.length === 0 ? 'Maksimal 10 peringkat hadiah.' : 'Maksimal 10 peringkat hadiah.';
    }
  }

  addRewardBtn?.addEventListener('click', () => {
    const nextRow = rewardRows.find((row) => row.classList.contains('is-hidden'));
    if (!nextRow) return;
    nextRow.classList.remove('is-hidden');
    nextRow.querySelector('input')?.focus();
    updateAddButton();
    markDirty();
  });

  document.querySelectorAll('[data-remove-rank]').forEach((button) => {
    button.addEventListener('click', () => {
      const row = button.closest('.reward-row');
      const input = row?.querySelector('input');
      if (!row || !input) return;
      input.value = '';
      row.classList.add('is-hidden');
      updateAddButton();
      markDirty();
    });
  });

  rewardForm?.addEventListener('input', markDirty);
  rewardForm?.addEventListener('change', markDirty);

  fileInput?.addEventListener('change', () => {
    if (fileInput.files?.length && fileNameText) {
      fileNameText.textContent = fileInput.files[0].name;
    }
  });

  removeImageBtn?.addEventListener('click', () => {
    if (!removeImageCheck) return;
    setTimeout(() => {
      removeImageBtn.textContent = removeImageCheck.checked ? 'Gambar akan dihapus' : 'Hapus Gambar';
    }, 0);
  });

  updateAddButton();
</script>
</body>
</html>
