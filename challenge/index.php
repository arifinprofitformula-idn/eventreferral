<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';

$brand = require_brand_or_404(get_current_brand());
$brandId = (int)$brand['id'];

$eventSlug = isset($_GET['event']) ? clean($_GET['event']) : ($brand['default_event_slug'] ?? DEFAULT_EVENT_SLUG);
if ($eventSlug === '') {
    $eventSlug = $brand['default_event_slug'] ?? DEFAULT_EVENT_SLUG;
}
$eventNotFound = false;
$leaderboardPerPage = 20;
$currentPage = max(1, (int)($_GET['page'] ?? 1));

$pdo = get_db();

// Daftar semua event aktif milik brand ini, untuk dropdown pemilih
$stmt = $pdo->prepare("SELECT slug, name FROM events WHERE brand_id = ? AND status = 'active' ORDER BY created_at DESC");
$stmt->execute([$brandId]);
$allEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selectedEvent = null;
if ($eventSlug !== '') {
    $selectedEvent = get_event_by_slug($eventSlug);
    if (!$selectedEvent || (int)$selectedEvent['brand_id'] !== $brandId || $selectedEvent['status'] !== 'active') {
        $eventNotFound = true;
        $selectedEvent = null;
    }
}

// Ambil leaderboard untuk event aktif yang dipilih.
// Jika /challenge/ dibuka tanpa ?event=, otomatis mengikuti event utama brand.
// Skor challenge memakai WhatsApp unik per event berdasarkan pendaftaran pertama.
// Raw leads tetap disimpan apa adanya untuk audit dan follow-up.
if ($eventSlug !== '') {
    $stmt = $pdo->prepare('
        SELECT r.name, COUNT(fl.id) AS total
        FROM referrers r
        LEFT JOIN (
            SELECT l1.id, l1.event_slug, l1.ref_code
            FROM leads l1
            INNER JOIN (
                SELECT event_slug, whatsapp, MIN(id) AS first_id
                FROM leads
                WHERE brand_id = ? AND whatsapp IS NOT NULL AND whatsapp <> ""
                GROUP BY event_slug, whatsapp
            ) first_lead ON first_lead.first_id = l1.id
        ) fl ON fl.event_slug = r.event_slug AND fl.ref_code = r.ref_code
        WHERE r.brand_id = ? AND r.event_slug = ?
        GROUP BY r.id
        ORDER BY total DESC, r.created_at ASC
    ');
    $stmt->execute([$brandId, $brandId, $eventSlug]);
}
$leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($eventSlug !== '') {
    $totalReferrersStmt = $pdo->prepare('SELECT COUNT(*) FROM referrers WHERE brand_id = ? AND event_slug = ?');
    $totalReferrersStmt->execute([$brandId, $eventSlug]);
    $totalReferrers = (int)$totalReferrersStmt->fetchColumn();
}

$totalLeaderboardRows = count($leaderboard);
$totalPages = max(1, (int)ceil($totalLeaderboardRows / $leaderboardPerPage));
$currentPage = min($currentPage, $totalPages);
$leaderboardOffset = ($currentPage - 1) * $leaderboardPerPage;
$leaderboardPage = array_slice($leaderboard, $leaderboardOffset, $leaderboardPerPage);

$pageTitle = $eventNotFound ? 'Event Tidak Ditemukan' : ($selectedEvent ? $selectedEvent['name'] : 'Event Utama');

if ($eventNotFound) {
    http_response_code(404);
}

$rewards = $selectedEvent ? get_event_rewards($eventSlug) : [];
$rewardImage = $selectedEvent['reward_image'] ?? null;
$topThree = array_slice($leaderboard, 0, 3);
$logoPath = $brand['logo_path'] ? $brand['logo_path'] : '/assets/logo.png';
$eventUrl = $selectedEvent ? ($eventSlug === $brand['default_event_slug'] ? '/' : EVENTS_URL_BASE . '/' . rawurlencode($eventSlug) . '/') : '/';
$referralUrl = $selectedEvent ? '/buat-link.php?event=' . urlencode($eventSlug) : '/buat-link.php';

if ($eventSlug !== '') {
    $updatedStmt = $pdo->prepare('SELECT MAX(created_at) FROM leads WHERE brand_id = ? AND event_slug = ?');
    $updatedStmt->execute([$brandId, $eventSlug]);
    $lastUpdated = $updatedStmt->fetchColumn();
}

function display_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $initials = '';
    foreach ($parts as $part) {
        if ($part !== '') {
            $initials .= strtoupper(substr($part, 0, 1));
        }
        if (strlen($initials) >= 2) {
            break;
        }
    }

    return $initials !== '' ? $initials : 'RE';
}

function challenge_page_url(int $page, string $eventSlug): string
{
    $params = [];
    if ($eventSlug !== '') {
        $params['event'] = $eventSlug;
    }
    if ($page > 1) {
        $params['page'] = $page;
    }

    return '/challenge/' . (!empty($params) ? '?' . http_build_query($params) : '');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Challenge Pengundang Terbanyak — <?= htmlspecialchars($brand['name']) ?></title>
<meta name="description" content="Pantau siapa pengundang paling aktif di acara <?= htmlspecialchars($brand['name']) ?>, update secara real-time.">
<link rel="icon" href="<?= htmlspecialchars($logoPath) ?>">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style><?= get_theme_css_vars($brand) ?></style>
<style>
  :root {
    --bg:#0B0B0A;
    --surface:#171716;
    --surface-elevated:#20201E;
    --gold:var(--brand-primary);
    --gold-soft:var(--brand-soft);
    --border-gold:rgba(214,165,54,0.22);
    --text:#F7F3E8;
    --muted:#A8A29A;
    --success:#22C55E;
    --warning:#F59E0B;
    --shadow:0 24px 80px rgba(0,0,0,0.38);
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html { scroll-behavior: smooth; }
  body {
    min-height: 100vh;
    background:
      radial-gradient(circle at 88% 6%, rgba(214,165,54,0.25), transparent 30vw),
      radial-gradient(circle at 8% 88%, rgba(214,165,54,0.14), transparent 34vw),
      linear-gradient(135deg, var(--bg) 0%, #10100F 50%, #070707 100%);
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
      linear-gradient(90deg, rgba(255,255,255,0.015) 1px, transparent 1px);
    background-size: 52px 52px;
    mask-image: radial-gradient(circle at 50% 18%, black, transparent 72%);
  }
  a { color: inherit; }
  .wrap {
    position: relative;
    width: min(100%, 1120px);
    margin: 0 auto;
    padding: 24px 32px 48px;
  }
  .public-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    padding: 4px 0 22px;
  }
  .logo {
    display: inline-flex;
    align-items: center;
    text-decoration: none;
  }
  .logo img {
    display: block;
    width: 142px;
    height: auto;
    filter: drop-shadow(0 10px 20px rgba(0,0,0,0.34));
  }
  .header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
  }
  .tag, .mini-pill, .btn, .select-event, .rank-card, .prize-card {
    transition: transform 180ms ease, border-color 180ms ease, background 180ms ease, box-shadow 180ms ease;
  }
  .tag {
    display: inline-flex;
    align-items: center;
    width: fit-content;
    color: var(--gold-soft);
    background: rgba(214,165,54,0.12);
    border: 1px solid var(--border-gold);
    border-radius: 999px;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: .05em;
    text-transform: uppercase;
    padding: 8px 12px;
  }
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 46px;
    border: 1px solid transparent;
    border-radius: 14px;
    font-size: 13.5px;
    font-weight: 900;
    padding: 12px 18px;
    text-decoration: none;
    white-space: nowrap;
  }
  .btn:hover, .rank-card:hover, .prize-card:hover { transform: translateY(-2px); }
  .btn-gold {
    color: #111;
    background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    box-shadow: 0 14px 28px rgba(214,165,54,0.24);
  }
  .btn-outline {
    color: var(--text);
    background: rgba(255,255,255,0.04);
    border-color: rgba(214,165,54,0.22);
  }
  .hero {
    position: relative;
    overflow: hidden;
    display: grid;
    grid-template-columns: minmax(0, 1fr) 360px;
    align-items: center;
    gap: 24px;
    border: 1px solid var(--border-gold);
    border-radius: 28px;
    background:
      radial-gradient(circle at 80% 24%, rgba(244,210,122,0.28), transparent 24%),
      linear-gradient(135deg, rgba(32,32,30,0.96), rgba(23,23,22,0.92) 60%, rgba(76,52,12,0.32));
    box-shadow: var(--shadow);
    padding: 36px;
  }
  .hero h1 {
    color: var(--gold-soft);
    font-family: Georgia, "Times New Roman", serif;
    font-size: clamp(34px, 5vw, 56px);
    line-height: 1.04;
    letter-spacing: 0;
    margin: 14px 0 12px;
  }
  .sub {
    color: var(--muted);
    font-size: 15px;
    line-height: 1.7;
    max-width: 720px;
  }
  .hero-meta {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin: 20px 0;
  }
  .mini-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--text);
    background: rgba(255,255,255,0.045);
    border: 1px solid rgba(255,255,255,0.09);
    border-radius: 999px;
    font-size: 12.5px;
    font-weight: 800;
    padding: 9px 12px;
  }
  .mini-pill.success { color: #BBF7D0; border-color: rgba(34,197,94,0.24); background: rgba(34,197,94,0.10); }
  .hero-cta {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
  }
  .trophy {
    position: relative;
    display: grid;
    place-items: center;
    min-height: 260px;
  }
  .trophy::before {
    content:"";
    position: absolute;
    width: 320px;
    height: 320px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(244,210,122,0.30), rgba(214,165,54,0.06) 58%, transparent 72%);
    filter: blur(3px);
  }
  .trophy-img {
    position: relative;
    z-index: 1;
    display: block;
    width: min(100%, 320px);
    height: auto;
    object-fit: contain;
    filter: drop-shadow(0 28px 52px rgba(214,165,54,0.30));
  }
  .section {
    margin-top: 18px;
    border: 1px solid var(--border-gold);
    border-radius: 24px;
    background: linear-gradient(180deg, rgba(255,255,255,0.045), rgba(255,255,255,0.02));
    box-shadow: 0 18px 50px rgba(0,0,0,0.24);
    padding: 24px;
  }
  .section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 18px;
  }
  h2 {
    color: var(--gold-soft);
    font-family: Georgia, "Times New Roman", serif;
    font-size: 24px;
    line-height: 1.2;
  }
  .leaderboard-section {
    overflow: hidden;
  }
  .leaderboard-section .section-head {
    margin-bottom: 12px;
  }
  .leaderboard-title {
    display: inline-flex;
    align-items: center;
    gap: 10px;
  }
  .leaderboard-title::before {
    content: "♛";
    color: var(--gold);
    font-family: Inter, system-ui, sans-serif;
    font-size: 18px;
  }
  .selector {
    width: min(320px, 100%);
  }
  .select-event {
    width: 100%;
    min-height: 44px;
    color: var(--text);
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(214,165,54,0.24);
    border-radius: 13px;
    font: inherit;
    font-size: 13.5px;
    padding: 0 12px;
  }
  .rewards-layout {
    display: grid;
    grid-template-columns: minmax(280px, .85fr) minmax(320px, 1.15fr);
    gap: 18px;
    align-items: stretch;
  }
  .rewards-image {
    width: 100%;
    height: 100%;
    min-height: 220px;
    aspect-ratio: 16 / 9;
    object-fit: cover;
    border: 1px solid rgba(244,210,122,0.26);
    border-radius: 18px;
    display: block;
  }
  .prize-grid {
    display: grid;
    gap: 12px;
  }
  .prize-card {
    display: grid;
    grid-template-columns: 54px minmax(0, 1fr);
    gap: 14px;
    align-items: center;
    border: 1px solid rgba(214,165,54,0.20);
    border-radius: 18px;
    background: rgba(255,255,255,0.035);
    padding: 15px;
  }
  .prize-card:first-child {
    background: linear-gradient(135deg, rgba(214,165,54,0.22), rgba(255,255,255,0.04));
    border-color: rgba(244,210,122,0.42);
  }
  .medal {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 54px;
    height: 54px;
    border-radius: 50%;
    color: #17120A;
    background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    font-weight: 900;
    font-size: 18px;
    box-shadow: 0 12px 26px rgba(214,165,54,0.22);
  }
  .prize-title {
    color: var(--gold-soft);
    font-size: 12px;
    font-weight: 900;
    letter-spacing: .08em;
    text-transform: uppercase;
    margin-bottom: 5px;
  }
  .prize-text {
    color: var(--text);
    font-weight: 800;
    line-height: 1.45;
    overflow-wrap: anywhere;
  }
  .prize-note, .last-note {
    color: var(--muted);
    font-size: 12px;
    line-height: 1.6;
    margin-top: 14px;
    text-align: center;
  }
  .arena-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 310px;
    gap: 18px;
    align-items: start;
  }
  .podium {
    position: relative;
    overflow: hidden;
    display: grid;
    grid-template-columns: 1fr 1.14fr 1fr;
    align-items: end;
    gap: 8px;
    min-height: 286px;
    padding: 38px 22px 0;
    background:
      radial-gradient(circle at 50% 70%, rgba(214,165,54,0.26), transparent 34%),
      radial-gradient(circle at 50% 12%, rgba(244,210,122,0.12), transparent 34%),
      linear-gradient(180deg, rgba(11,11,10,0.42), rgba(8,8,7,0.76));
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 20px;
    margin-bottom: 0;
  }
  .podium::before {
    content: "";
    position: absolute;
    inset: 0;
    background-image:
      radial-gradient(circle at 8% 18%, rgba(214,165,54,0.55) 0 1px, transparent 2px),
      radial-gradient(circle at 28% 8%, rgba(244,210,122,0.36) 0 1px, transparent 2px),
      radial-gradient(circle at 74% 12%, rgba(214,165,54,0.46) 0 1px, transparent 2px),
      radial-gradient(circle at 88% 28%, rgba(244,210,122,0.34) 0 1px, transparent 2px);
    opacity: .72;
  }
  .podium::after {
    content: "";
    position: absolute;
    left: 50%;
    bottom: 0;
    width: 280px;
    height: 44px;
    transform: translateX(-50%);
    background: radial-gradient(ellipse at center, rgba(244,210,122,0.55), rgba(214,165,54,0.22) 45%, transparent 72%);
    filter: blur(1px);
  }
  .podium-card {
    position: relative;
    z-index: 1;
    display: grid;
    justify-items: center;
    align-content: center;
    gap: 8px;
    min-height: 160px;
    border: 1px solid rgba(214,165,54,0.18);
    border-radius: 20px 20px 0 0;
    background: linear-gradient(180deg, rgba(255,255,255,0.08), rgba(255,255,255,0.025));
    box-shadow: inset 0 1px 20px rgba(255,255,255,0.03);
    padding: 26px 12px 16px;
    text-align: center;
  }
  .podium-card.second {
    background: linear-gradient(180deg, rgba(148,163,184,0.18), rgba(255,255,255,0.025));
    transform: translateY(22px);
  }
  .podium-card.third {
    background: linear-gradient(180deg, rgba(180,83,9,0.22), rgba(255,255,255,0.025));
    transform: translateY(35px);
  }
  .podium-card.first {
    min-height: 220px;
    background: linear-gradient(180deg, rgba(214,165,54,0.50), rgba(214,165,54,0.12));
    border-color: rgba(244,210,122,0.44);
    box-shadow: 0 20px 54px rgba(214,165,54,0.20), inset 0 1px 32px rgba(244,210,122,0.08);
  }
  .podium-card.first::before {
    content: "♛";
    position: absolute;
    top: -34px;
    color: var(--gold-soft);
    font-size: 28px;
    text-shadow: 0 8px 20px rgba(214,165,54,0.42);
  }
  .podium-rank {
    position: absolute;
    top: -16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    color: #111;
    background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    font-weight: 900;
    box-shadow: 0 10px 24px rgba(214,165,54,0.25);
  }
  .podium-card.second .podium-rank {
    color: #1f2937;
    background: linear-gradient(135deg, #E5E7EB, #9CA3AF);
  }
  .podium-card.third .podium-rank {
    color: #231307;
    background: linear-gradient(135deg, #F59E0B, #92400E);
  }
  .avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 58px;
    height: 58px;
    border-radius: 50%;
    color: var(--text);
    background: #11110F;
    border: 1px solid rgba(244,210,122,0.28);
    font-weight: 900;
  }
  .podium-card.first .avatar {
    width: 70px;
    height: 70px;
    font-size: 20px;
  }
  .podium-card.first .podium-rank {
    display: none;
  }
  .podium-award {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 46px;
    min-height: 28px;
    color: #111;
    background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    border-radius: 999px;
    font-size: 13px;
    font-weight: 900;
    padding: 6px 12px;
    box-shadow: 0 10px 22px rgba(214,165,54,0.24);
  }
  .podium-card.second .podium-award,
  .podium-card.third .podium-award {
    color: var(--text);
    background: rgba(255,255,255,0.11);
    border: 1px solid rgba(255,255,255,0.12);
    box-shadow: none;
  }
  .podium-name, .rank-name {
    color: var(--text);
    font-weight: 900;
    line-height: 1.35;
    overflow-wrap: anywhere;
  }
  .podium-total, .rank-total {
    color: var(--muted);
    font-size: 12.5px;
  }
  .rank-list {
    display: grid;
    gap: 0;
    overflow: hidden;
    border: 1px solid rgba(255,255,255,0.07);
    border-top: 0;
    border-radius: 0 0 18px 18px;
    background: rgba(8,8,7,0.38);
  }
  .rank-table-head {
    display: grid;
    grid-template-columns: 52px minmax(0, 1fr) auto;
    gap: 12px;
    color: var(--muted);
    background: rgba(255,255,255,0.035);
    border-bottom: 1px solid rgba(255,255,255,0.07);
    font-size: 11px;
    font-weight: 800;
    padding: 10px 14px;
  }
  .rank-card {
    display: grid;
    grid-template-columns: 52px minmax(0, 1fr) auto;
    gap: 12px;
    align-items: center;
    border: 0;
    border-radius: 0;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    background: rgba(255,255,255,0.018);
    padding: 10px 14px;
  }
  .rank-card:last-child { border-bottom: 0; }
  .rank-card.rank-1 {
    background: linear-gradient(90deg, rgba(214,165,54,0.15), rgba(255,255,255,0.02));
  }
  .rank-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    color: var(--text);
    background: rgba(255,255,255,0.09);
    border: 1px solid rgba(255,255,255,0.14);
    font-size: 12px;
    font-weight: 900;
  }
  .rank-card.rank-1 .rank-badge {
    color: #111;
    background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    border: 0;
  }
  .rank-card.rank-2 .rank-badge {
    color: #1f2937;
    background: linear-gradient(135deg, #E5E7EB, #9CA3AF);
    border: 0;
  }
  .rank-card.rank-3 .rank-badge {
    color: #231307;
    background: linear-gradient(135deg, #F59E0B, #92400E);
    border: 0;
  }
  .count-pill {
    color: var(--text);
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 999px;
    font-size: 12.5px;
    font-weight: 900;
    padding: 7px 12px;
    white-space: nowrap;
  }
  .rank-card.rank-1 .count-pill {
    color: #111;
    background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    border: 0;
  }
  .leaderboard-pager {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    border: 1px solid rgba(255,255,255,0.07);
    border-top: 0;
    border-radius: 0 0 18px 18px;
    background: rgba(8,8,7,0.30);
    color: var(--muted);
    font-size: 12px;
    padding: 12px 14px;
  }
  .pager-actions {
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .pager-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 38px;
    min-height: 34px;
    color: var(--text);
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(214,165,54,0.18);
    border-radius: 10px;
    font-weight: 800;
    text-decoration: none;
  }
  .pager-btn.active {
    color: #111;
    background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    border-color: transparent;
  }
  .pager-btn.disabled {
    opacity: .42;
    pointer-events: none;
  }
  .join {
    position: sticky;
    top: 18px;
  }
  .steps {
    display: grid;
    gap: 16px;
    margin: 18px 0;
  }
  .join-step {
    display: grid;
    grid-template-columns: 34px minmax(0, 1fr);
    gap: 12px;
  }
  .step-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    color: #111;
    background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    font-weight: 900;
  }
  .step-title {
    color: var(--gold-soft);
    font-weight: 900;
    margin-bottom: 4px;
  }
  .step-copy {
    color: var(--muted);
    font-size: 12.5px;
    line-height: 1.6;
  }
  .empty {
    display: grid;
    place-items: center;
    gap: 12px;
    border: 1px dashed rgba(244,210,122,0.24);
    border-radius: 20px;
    background: rgba(255,255,255,0.03);
    padding: 42px 20px;
    text-align: center;
  }
  .empty h3 {
    font-size: 20px;
    font-weight: 900;
  }
  .empty p {
    color: var(--muted);
    max-width: 480px;
    line-height: 1.6;
  }
  footer {
    color: var(--gold-soft);
    font-size: 13px;
    margin-top: 24px;
    text-align: center;
  }
  footer a {
    color: inherit;
    text-decoration: none;
  }
  @media (max-width: 980px) {
    .hero, .arena-grid, .rewards-layout {
      grid-template-columns: 1fr;
    }
    .trophy {
      min-height: 200px;
    }
    .join {
      position: static;
    }
  }
  @media (max-width: 720px) {
    .wrap {
      padding: 14px 16px 32px;
    }
    .public-header {
      align-items: flex-start;
      flex-direction: column;
      padding-bottom: 16px;
    }
    .logo img {
      width: 112px;
    }
    .header-actions, .hero-cta {
      width: 100%;
    }
    .header-actions .tag {
      display: none;
    }
    .btn {
      width: 100%;
    }
    .hero {
      border-radius: 22px;
      padding: 22px;
      text-align: center;
    }
    .hero .tag, .hero-meta {
      margin-left: auto;
      margin-right: auto;
      justify-content: center;
    }
    .hero h1 {
      font-size: 34px;
    }
    .trophy {
      display: none;
    }
    .section {
      border-radius: 20px;
      padding: 16px;
    }
    .section-head {
      align-items: stretch;
      flex-direction: column;
    }
    .selector {
      width: 100%;
    }
    h2 {
      font-size: 22px;
      text-align: center;
    }
    .prize-grid {
      grid-template-columns: 1fr;
    }
    .podium {
      display: none;
    }
    .rank-card {
      grid-template-columns: 40px minmax(0, 1fr);
      padding: 12px;
    }
    .rank-table-head {
      display: none;
    }
    .count-pill {
      grid-column: 2;
      justify-self: start;
    }
    .leaderboard-pager {
      align-items: stretch;
      flex-direction: column;
    }
    .pager-actions {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
    }
  }
</style>
</head>
<body>
<div class="wrap">
  <header class="public-header">
    <a class="logo" href="/" aria-label="<?= htmlspecialchars($brand['name']) ?>">
      <img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($brand['name']) ?>">
    </a>
    <div class="header-actions">
      <span class="tag">Challenge Pengundang</span>
      <a class="btn btn-gold" href="<?= htmlspecialchars($referralUrl) ?>">Buat Link Referral</a>
    </div>
  </header>

  <section class="hero" aria-labelledby="challenge-title">
    <div>
      <span class="tag">🏆 Challenge Pengundang</span>
      <h1 id="challenge-title"><?= htmlspecialchars($pageTitle) ?></h1>
      <p class="sub">Ajak lebih banyak peserta, naikkan peringkat, dan raih hadiah challenge. Semakin banyak yang daftar dari link kamu, semakin tinggi peringkatmu.</p>
      <div class="hero-meta">
        <span class="mini-pill <?= $eventNotFound ? '' : 'success' ?>"><?= $eventNotFound ? 'Event tidak tersedia' : 'Event Aktif' ?></span>
        <span class="mini-pill"><?= (int)$totalReferrers ?> Pengundang</span>
        <span class="mini-pill">Update otomatis</span>
      </div>
      <div class="hero-cta">
        <a class="btn btn-gold" href="<?= htmlspecialchars($referralUrl) ?>">Buat Link Referral Saya</a>
        <a class="btn btn-outline" href="<?= htmlspecialchars($eventUrl) ?>">Lihat Halaman Event</a>
      </div>
    </div>
    <div class="trophy" aria-hidden="true">
      <img class="trophy-img" src="/assets/champion.png" alt="" width="512" height="512" loading="eager">
    </div>
  </section>

  <?php if (count($allEvents) > 1): ?>
    <section class="section" aria-label="Pilih event">
      <div class="section-head" style="margin-bottom:0;">
        <h2>Pilih Arena Challenge</h2>
        <div class="selector">
          <select class="select-event" onchange="if(this.value){window.location.href='/challenge/?event='+encodeURIComponent(this.value)}">
            <?php foreach ($allEvents as $ev): ?>
              <option value="<?= htmlspecialchars($ev['slug']) ?>" <?= $ev['slug'] === $eventSlug ? 'selected' : '' ?>>
                <?= htmlspecialchars($ev['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($selectedEvent && ($rewardImage || !empty($rewards))): ?>
    <section class="section">
      <div class="section-head">
        <h2>Hadiah Challenge</h2>
      </div>
      <div class="rewards-layout">
        <?php if ($rewardImage): ?>
          <img src="<?= htmlspecialchars($rewardImage) ?>" alt="Info hadiah challenge" class="rewards-image" loading="lazy" width="640" height="360">
        <?php endif; ?>
        <?php if (!empty($rewards)): ?>
          <div class="prize-grid">
            <?php foreach ($rewards as $r): ?>
              <article class="prize-card">
                <span class="medal"><?= (int)$r['rank'] ?></span>
                <div>
                  <div class="prize-title">Juara <?= (int)$r['rank'] ?></div>
                  <div class="prize-text"><?= htmlspecialchars($r['reward_text']) ?></div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      <p class="prize-note">Hadiah dapat berubah sewaktu-waktu sesuai ketentuan event.</p>
    </section>
  <?php endif; ?>

  <div class="arena-grid">
    <section class="section leaderboard-section">
      <div class="section-head">
        <h2 class="leaderboard-title">Leaderboard Pengundang</h2>
      </div>

      <?php if (empty($leaderboard)): ?>
        <div class="empty">
          <h3><?= $eventNotFound ? 'Event challenge tidak ditemukan' : 'Belum ada pengundang di leaderboard' ?></h3>
          <p><?= $eventNotFound ? 'Event yang kamu buka belum tersedia atau sudah tidak aktif.' : 'Jadilah yang pertama mengundang peserta dan ambil posisi teratas.' ?></p>
          <a class="btn btn-gold" href="<?= htmlspecialchars($eventNotFound ? '/' : $referralUrl) ?>"><?= $eventNotFound ? 'Kembali ke Beranda' : 'Buat Link Referral' ?></a>
        </div>
      <?php else: ?>
        <?php if (count($topThree) >= 3): ?>
          <?php
            $podiumOrder = [1, 0, 2];
            $podiumClasses = ['second', 'first', 'third'];
          ?>
          <div class="podium" aria-label="Top 3 leaderboard">
            <?php foreach ($podiumOrder as $pos => $leaderIndex): ?>
              <?php $leader = $topThree[$leaderIndex]; ?>
              <article class="podium-card <?= htmlspecialchars($podiumClasses[$pos]) ?>">
                <span class="podium-rank">#<?= $leaderIndex + 1 ?></span>
                <span class="avatar"><?= htmlspecialchars(display_initials($leader['name'])) ?></span>
                <div class="podium-name"><?= htmlspecialchars($leader['name']) ?></div>
                <div class="podium-total"><?= (int)$leader['total'] ?> orang</div>
                <span class="podium-award">#<?= $leaderIndex + 1 ?></span>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="rank-list">
          <div class="rank-table-head">
            <span>#</span>
            <span>Pengundang</span>
            <span>Pendaftar Unik</span>
          </div>
          <?php foreach ($leaderboardPage as $i => $row): ?>
            <?php
              $total = (int)$row['total'];
              $globalRank = $leaderboardOffset + $i + 1;
              $rankClass = $globalRank <= 3 ? 'rank-' . $globalRank : '';
            ?>
            <article class="rank-card <?= $rankClass ?>">
              <span class="rank-badge"><?= $globalRank ?></span>
              <div>
                <div class="rank-name"><?= htmlspecialchars($row['name']) ?></div>
              </div>
              <span class="count-pill"><?= $total ?> orang</span>
            </article>
          <?php endforeach; ?>
        </div>

        <?php if ($totalLeaderboardRows > 0): ?>
          <div class="leaderboard-pager">
            <span>
              Menampilkan <?= (int)($leaderboardOffset + 1) ?>-<?= (int)min($leaderboardOffset + $leaderboardPerPage, $totalLeaderboardRows) ?>
              dari <?= (int)$totalLeaderboardRows ?> pengundang
            </span>
            <?php if ($totalPages > 1): ?>
              <div class="pager-actions" aria-label="Navigasi leaderboard">
                <a class="pager-btn <?= $currentPage <= 1 ? 'disabled' : '' ?>" href="<?= htmlspecialchars(challenge_page_url(max(1, $currentPage - 1), $eventSlug)) ?>" aria-label="Halaman sebelumnya">‹</a>
                <span class="pager-btn active"><?= (int)$currentPage ?></span>
                <a class="pager-btn <?= $currentPage >= $totalPages ? 'disabled' : '' ?>" href="<?= htmlspecialchars(challenge_page_url(min($totalPages, $currentPage + 1), $eventSlug)) ?>" aria-label="Halaman berikutnya">›</a>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <p class="last-note">
        <?php if ($lastUpdated): ?>
          🔄 Leaderboard menghitung WhatsApp unik per event. Terakhir diperbarui: <?= htmlspecialchars(date('d M Y, H:i', strtotime($lastUpdated))) ?> WIB
        <?php else: ?>
          Leaderboard menghitung WhatsApp unik per event dari pendaftaran pertama.
        <?php endif; ?>
      </p>
    </section>

    <aside class="section join">
      <h2>Cara Ikut Challenge</h2>
      <div class="steps">
        <div class="join-step">
          <span class="step-num">1</span>
          <div><div class="step-title">Buat link referral</div><p class="step-copy">Dapatkan link unik kamu di halaman buat link.</p></div>
        </div>
        <div class="join-step">
          <span class="step-num">2</span>
          <div><div class="step-title">Bagikan ke banyak orang</div><p class="step-copy">Bagikan link ke teman, komunitas, atau media sosial.</p></div>
        </div>
        <div class="join-step">
          <span class="step-num">3</span>
          <div><div class="step-title">Naikkan peringkatmu</div><p class="step-copy">Setiap WhatsApp unik dari link kamu akan masuk ke leaderboard.</p></div>
        </div>
      </div>
      <a class="btn btn-gold" href="<?= htmlspecialchars($referralUrl) ?>">Buat Link Referral Sekarang</a>
    </aside>
  </div>

  <footer>
    <a href="/">Challenge Pengundang <?= htmlspecialchars($brand['name']) ?> — Ajak, Bagikan, Menangkan Hadiahnya!</a>
  </footer>
</div>
</body>
</html>
