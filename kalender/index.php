<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';

$brand = require_brand_or_404(get_current_brand());
$brandId = (int)$brand['id'];

$pdo = get_db();

// Event aktif milik brand ini, belum lewat tanggalnya (atau belum diisi tanggal),
// terurut dari yang terdekat ke yang terjauh. Event tanpa event_date turun ke bawah.
$stmt = $pdo->prepare('
    SELECT *
    FROM events
    WHERE brand_id = ?
      AND status = "active"
      AND (event_date IS NULL OR event_date >= CURDATE())
    ORDER BY (event_date IS NULL) ASC, event_date ASC, created_at DESC
');
$stmt->execute([$brandId]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

$bulanIndo = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
$bulanPanjangIndo = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

function kalender_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function kalender_valid_slug(?string $slug): bool {
    return is_string($slug) && preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) === 1;
}

function kalender_event_url(array $brand, array $ev): ?string {
    $slug = (string)($ev['slug'] ?? '');
    if (!kalender_valid_slug($slug)) {
        return null;
    }
    if ($slug === ($brand['default_event_slug'] ?? '')) {
        return '/';
    }
    return EVENTS_URL_BASE . '/' . rawurlencode($slug) . '/';
}

function kalender_challenge_url(array $ev): ?string {
    $slug = (string)($ev['slug'] ?? '');
    if (!kalender_valid_slug($slug)) {
        return null;
    }
    return '/challenge/?event=' . rawurlencode($slug);
}

function kalender_relative_label(?string $eventDate): ?string {
    if ($eventDate === null || $eventDate === '') {
        return null;
    }
    $today = new DateTime('today');
    $target = DateTime::createFromFormat('Y-m-d', $eventDate);
    if (!$target) {
        return null;
    }
    $target->setTime(0, 0, 0);
    $diff = (int)$today->diff($target)->format('%r%a');
    if ($diff === 0) return 'Hari Ini';
    if ($diff === 1) return 'Besok';
    if ($diff > 1) return $diff . ' Hari Lagi';
    return null;
}

function kalender_date_parts(?string $eventDate, array $monthsShort, array $monthsLong): array {
    if ($eventDate === null || $eventDate === '') {
        return ['has_date' => false, 'day' => null, 'month_short' => null, 'month_long' => null, 'full' => null];
    }
    $date = DateTime::createFromFormat('Y-m-d', $eventDate);
    if (!$date || $date->format('Y-m-d') !== $eventDate) {
        return ['has_date' => false, 'day' => null, 'month_short' => null, 'month_long' => null, 'full' => null];
    }
    $month = (int)$date->format('n');
    return [
        'has_date' => true,
        'day' => $date->format('d'),
        'month_short' => $monthsShort[$month] ?? $date->format('M'),
        'month_long' => $monthsLong[$month] ?? $date->format('F'),
        'full' => $date->format('d') . ' ' . ($monthsLong[$month] ?? $date->format('F')) . ' ' . $date->format('Y'),
    ];
}

function kalender_whatsapp_url(array $brand): ?string {
    $raw = trim((string)($brand['whatsapp_default'] ?? ''));
    if ($raw === '') {
        return null;
    }
    $number = normalize_whatsapp($raw);
    if ($number === '' || !preg_match('/^62[0-9]{8,15}$/', $number)) {
        return null;
    }
    $message = 'Halo Admin ' . ($brand['name'] ?? 'RahasiaEmas.id') . ', saya ingin mendapatkan pengingat event terbaru.';
    return 'https://wa.me/' . rawurlencode($number) . '?text=' . rawurlencode($message);
}

$logoPath = $brand['logo_path'] ? '..' . $brand['logo_path'] : '../assets/logo.png';
$whatsappUrl = kalender_whatsapp_url($brand);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kalender Event - <?= kalender_h($brand['name']) ?></title>
<meta name="description" content="Jadwal lengkap webinar dan event <?= kalender_h($brand['name']) ?> yang akan datang.">
<link rel="icon" href="<?= kalender_h($logoPath) ?>">
<style><?= get_theme_css_vars($brand) ?></style>
<style>
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
  }

  * { box-sizing: border-box; }
  html { min-width: 0; overflow-x: hidden; scroll-behavior: smooth; }
  body {
    min-width: 0;
    min-height: 100svh;
    margin: 0;
    overflow-x: hidden;
    background:
      radial-gradient(circle at 82% 4%, rgba(214,165,54,0.28), transparent 34rem),
      radial-gradient(circle at 7% 86%, rgba(214,165,54,0.16), transparent 30rem),
      linear-gradient(180deg, var(--bg), #080807 58%, var(--bg));
    color: var(--text);
    font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  }
  body::before {
    content: "";
    position: fixed;
    inset: 0;
    pointer-events: none;
    background:
      linear-gradient(90deg, transparent 0, rgba(214,165,54,0.05) 1px, transparent 1px),
      linear-gradient(180deg, transparent 0, rgba(255,255,255,0.025) 1px, transparent 1px);
    background-size: 96px 96px;
    mask-image: radial-gradient(circle at center, rgba(0,0,0,0.8), transparent 72%);
  }
  a { color: inherit; }
  img { max-width: 100%; }
  .page {
    position: relative;
    width: min(100%, 1280px);
    margin: 0 auto;
    padding: 0 32px 40px;
  }
  .page::before {
    content: "";
    position: absolute;
    top: 70px;
    right: 18px;
    width: min(56vw, 680px);
    height: 1px;
    transform: rotate(-14deg);
    transform-origin: right center;
    background: linear-gradient(90deg, transparent, rgba(244,210,122,0.6), transparent);
    pointer-events: none;
  }

  .site-nav {
    position: sticky;
    top: 0;
    z-index: 20;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    min-height: 88px;
    margin: 0 -32px;
    padding: 16px 32px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    background: rgba(11,11,10,0.82);
    backdrop-filter: blur(18px);
  }
  .brand-logo {
    display: inline-flex;
    align-items: center;
    min-width: 120px;
    text-decoration: none;
  }
  .brand-logo img { width: 112px; height: auto; max-height: 54px; object-fit: contain; display: block; }
  .nav-menu { display: flex; align-items: center; justify-content: center; gap: 8px; }
  .nav-menu a {
    position: relative;
    min-height: 42px;
    display: inline-flex;
    align-items: center;
    padding: 0 14px;
    border-radius: 999px;
    color: rgba(247,243,232,0.86);
    font-size: 14px;
    font-weight: 650;
    text-decoration: none;
    transition: color 180ms ease, background 180ms ease;
  }
  .nav-menu a:hover { color: var(--text); background: rgba(255,255,255,0.045); }
  .nav-menu a.active { color: var(--gold-soft); background: rgba(214,165,54,0.1); }
  .nav-menu a.active::after {
    content: "";
    position: absolute;
    left: 18px;
    right: 18px;
    bottom: -18px;
    height: 2px;
    background: linear-gradient(90deg, transparent, var(--gold), transparent);
  }
  .nav-cta {
    min-height: 46px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 0 18px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--gold-soft), var(--gold));
    color: #16130A;
    font-size: 14px;
    font-weight: 850;
    text-decoration: none;
    box-shadow: 0 16px 34px rgba(214,165,54,0.18);
    white-space: nowrap;
  }
  .menu-toggle {
    display: none;
    width: 48px;
    height: 48px;
    border: 1px solid var(--border-gold);
    border-radius: 14px;
    background: rgba(255,255,255,0.04);
    color: var(--text);
    cursor: pointer;
  }
  .menu-toggle span,
  .menu-toggle::before,
  .menu-toggle::after {
    content: "";
    display: block;
    width: 20px;
    height: 2px;
    margin: 5px auto;
    border-radius: 999px;
    background: var(--gold-soft);
  }

  .hero {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(360px, 0.78fr);
    gap: 44px;
    align-items: center;
    min-height: 560px;
    padding: 70px 0 58px;
  }
  .hero::after {
    content: "";
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(244,210,122,0.34), transparent);
  }
  .badge {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    width: fit-content;
    min-height: 34px;
    padding: 0 13px;
    border: 1px solid var(--border-gold);
    border-radius: 999px;
    background: rgba(214,165,54,0.1);
    color: var(--gold-soft);
    font-size: 12px;
    font-weight: 850;
    letter-spacing: .08em;
    text-transform: uppercase;
  }
  .badge svg { width: 16px; height: 16px; }
  h1 {
    max-width: 760px;
    margin: 20px 0 18px;
    font-family: Georgia, "Times New Roman", serif;
    font-size: clamp(42px, 7vw, 82px);
    line-height: 0.98;
    letter-spacing: 0;
  }
  h1 span { color: var(--gold-soft); }
  .hero-subtitle {
    max-width: 680px;
    margin: 0;
    color: rgba(247,243,232,0.72);
    font-size: clamp(16px, 2vw, 20px);
    line-height: 1.7;
  }
  .benefits {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    max-width: 780px;
    margin-top: 36px;
  }
  .benefit {
    display: grid;
    grid-template-columns: 46px minmax(0, 1fr);
    gap: 12px;
    align-items: center;
    min-width: 0;
  }
  .icon-bubble {
    width: 46px;
    height: 46px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(214,165,54,0.25);
    border-radius: 50%;
    background: radial-gradient(circle at 35% 28%, rgba(244,210,122,0.24), rgba(214,165,54,0.08));
    color: var(--gold-soft);
  }
  .icon-bubble svg { width: 22px; height: 22px; }
  .benefit > span:not(.icon-bubble) { display: block; color: var(--muted); font-size: 13px; line-height: 1.45; }
  .benefit strong { display: block; margin-bottom: 4px; color: var(--text); font-size: 14px; }
  .benefit > span:not(.icon-bubble) span { display: block; }

  .hero-visual {
    position: relative;
    min-height: 440px;
    border: 1px solid var(--border-gold);
    border-radius: 28px;
    overflow: hidden;
    background:
      radial-gradient(circle at 72% 24%, rgba(244,210,122,0.28), transparent 10rem),
      linear-gradient(135deg, rgba(255,255,255,0.05), rgba(255,255,255,0.012));
    box-shadow: 0 28px 80px rgba(0,0,0,0.36);
  }
  .hero-visual::before {
    content: "";
    position: absolute;
    inset: -12%;
    border: 1px solid rgba(214,165,54,0.52);
    border-radius: 50%;
    transform: rotate(-18deg);
  }
  .hero-visual::after {
    content: "";
    position: absolute;
    inset: 0;
    background:
      linear-gradient(90deg, rgba(11,11,10,0.82), rgba(11,11,10,0.18)),
      radial-gradient(circle at 66% 36%, rgba(244,210,122,0.17), transparent 14rem);
  }
  .stage-card {
    position: absolute;
    z-index: 2;
    inset: auto 28px 28px 28px;
    display: grid;
    grid-template-columns: 72px minmax(0, 1fr);
    gap: 16px;
    align-items: center;
    padding: 18px;
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 22px;
    background: rgba(11,11,10,0.72);
    backdrop-filter: blur(14px);
  }
  .stage-icon {
    width: 72px;
    height: 72px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 20px;
    background: linear-gradient(135deg, var(--gold-soft), var(--gold));
    color: #111;
  }
  .stage-icon svg { width: 36px; height: 36px; }
  .stage-card strong { display: block; margin-bottom: 5px; font-size: 18px; }
  .stage-card span { color: var(--muted); font-size: 14px; line-height: 1.45; }
  .stage-lights {
    position: absolute;
    z-index: 1;
    inset: 0;
    background:
      linear-gradient(118deg, transparent 0 38%, rgba(214,165,54,0.18) 39%, transparent 40%),
      linear-gradient(64deg, transparent 0 54%, rgba(244,210,122,0.22) 55%, transparent 56%);
  }
  .audience {
    position: absolute;
    z-index: 1;
    left: 8%;
    right: 8%;
    bottom: 128px;
    display: grid;
    grid-template-columns: repeat(8, 1fr);
    gap: 12px;
    opacity: .6;
  }
  .audience i {
    display: block;
    aspect-ratio: 1;
    border-radius: 50%;
    background: linear-gradient(180deg, rgba(247,243,232,0.18), rgba(247,243,232,0.04));
  }

  .section-head {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 20px;
    margin: 44px 0 24px;
  }
  .section-kicker { margin: 0 0 8px; color: var(--gold-soft); font-size: 13px; font-weight: 850; text-transform: uppercase; letter-spacing: .06em; }
  .section-head h2 { margin: 0; font-size: clamp(26px, 4vw, 38px); letter-spacing: 0; }
  .section-head p { margin: 8px 0 0; color: var(--muted); line-height: 1.6; }
  .section-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--gold-soft);
    font-size: 14px;
    font-weight: 800;
    text-decoration: none;
    white-space: nowrap;
  }

  .timeline {
    position: relative;
    padding-left: 34px;
  }
  .timeline::before {
    content: "";
    position: absolute;
    left: 10px;
    top: 18px;
    bottom: 18px;
    width: 1px;
    background: linear-gradient(180deg, var(--gold-soft), rgba(214,165,54,0.16), rgba(255,255,255,0.05));
  }
  .tl-node { position: relative; margin-bottom: 30px; }
  .tl-node:last-child { margin-bottom: 0; }
  .tl-dot {
    position: absolute;
    left: -34px;
    top: 34px;
    width: 22px;
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid var(--gold);
    border-radius: 50%;
    background: var(--bg);
    box-shadow: 0 0 0 5px rgba(214,165,54,0.08);
  }
  .tl-dot::after {
    content: "";
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--gold-soft);
  }
  .tl-node.featured .tl-dot { width: 28px; height: 28px; left: -37px; box-shadow: 0 0 0 7px rgba(214,165,54,0.16); }
  .event-card {
    position: relative;
    display: grid;
    grid-template-columns: minmax(190px, 300px) minmax(0, 1fr);
    gap: 28px;
    align-items: stretch;
    min-width: 0;
    padding: 20px;
    border: 1px solid var(--border-gold);
    border-radius: 26px;
    background: linear-gradient(180deg, rgba(255,255,255,0.045), rgba(255,255,255,0.02));
    box-shadow: 0 22px 60px rgba(0,0,0,0.22);
    transition: transform 180ms ease, border-color 180ms ease, box-shadow 180ms ease;
  }
  .event-card:hover {
    transform: translateY(-2px);
    border-color: rgba(244,210,122,0.36);
    box-shadow: 0 28px 70px rgba(0,0,0,0.32);
  }
  .event-card.featured {
    border-color: rgba(244,210,122,0.58);
    background:
      radial-gradient(circle at 92% 14%, rgba(214,165,54,0.16), transparent 18rem),
      linear-gradient(180deg, rgba(255,255,255,0.06), rgba(255,255,255,0.024));
  }
  .featured-label {
    position: absolute;
    top: -1px;
    left: 50%;
    transform: translateX(-50%);
    min-height: 34px;
    display: inline-flex;
    align-items: center;
    padding: 0 18px;
    border-radius: 0 0 12px 12px;
    background: linear-gradient(135deg, var(--gold-soft), var(--gold));
    color: #16130A;
    font-size: 12px;
    font-weight: 900;
    text-transform: uppercase;
    white-space: nowrap;
  }
  .poster {
    position: relative;
    display: block;
    min-height: 100%;
    aspect-ratio: 4 / 5;
    overflow: hidden;
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 18px;
    background:
      radial-gradient(circle at 70% 14%, rgba(244,210,122,0.22), transparent 8rem),
      linear-gradient(145deg, rgba(214,165,54,0.18), rgba(255,255,255,0.035));
    text-decoration: none;
  }
  .poster img { width: 100%; height: 100%; display: block; object-fit: cover; }
  .poster-fallback {
    height: 100%;
    min-height: 320px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 22px;
  }
  .poster-fallback[hidden] { display: none; }
  .poster-fallback .mark { width: 76px; height: auto; opacity: .9; }
  .poster-fallback strong { display: block; font-family: Georgia, "Times New Roman", serif; font-size: clamp(28px, 4vw, 42px); line-height: 1; }
  .poster-fallback span { color: var(--gold-soft); font-size: 13px; font-weight: 850; text-transform: uppercase; letter-spacing: .08em; }
  .event-body { min-width: 0; display: flex; flex-direction: column; justify-content: center; padding: 16px 6px; }
  .event-top { display: grid; grid-template-columns: 74px minmax(0, 1fr); gap: 18px; align-items: start; margin-bottom: 16px; }
  .date-badge {
    width: 74px;
    min-height: 78px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(214,165,54,0.36);
    border-radius: 14px;
    background: rgba(214,165,54,0.07);
    text-align: center;
  }
  .date-badge strong { color: var(--gold-soft); font-size: 30px; line-height: .95; }
  .date-badge span { margin-top: 7px; color: var(--text); font-size: 12px; font-weight: 750; text-transform: uppercase; }
  .date-badge.tbd { color: var(--muted); font-size: 12px; font-weight: 850; line-height: 1.25; text-transform: uppercase; }
  .countdown {
    display: inline-flex;
    margin-bottom: 8px;
    color: var(--gold-soft);
    font-size: 12px;
    font-weight: 900;
    letter-spacing: .08em;
    text-transform: uppercase;
  }
  .event-name {
    margin: 0;
    color: var(--text);
    font-size: clamp(22px, 3vw, 32px);
    line-height: 1.24;
    letter-spacing: 0;
  }
  .event-details {
    display: grid;
    gap: 11px;
    margin: 18px 0 24px;
    color: rgba(247,243,232,0.76);
  }
  .detail {
    display: grid;
    grid-template-columns: 22px minmax(0, 1fr);
    gap: 10px;
    align-items: start;
    min-width: 0;
    font-size: 15px;
    line-height: 1.45;
  }
  .detail svg { width: 18px; height: 18px; margin-top: 2px; color: var(--gold-soft); }
  .actions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
  .btn {
    min-height: 50px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 0 18px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 900;
    text-align: center;
    text-decoration: none;
    transition: transform 180ms ease, border-color 180ms ease, background 180ms ease;
  }
  .btn:hover { transform: translateY(-1px); }
  .btn-primary { background: linear-gradient(135deg, var(--gold-soft), var(--gold)); color: #16130A; }
  .btn-secondary { border: 1px solid rgba(247,243,232,0.2); background: rgba(255,255,255,0.018); color: var(--text); }
  .btn-secondary:hover { border-color: rgba(244,210,122,0.36); background: rgba(214,165,54,0.06); }
  .btn svg { width: 18px; height: 18px; }

  .empty-state,
  .reminder,
  .feature-grid {
    border: 1px solid var(--border-gold);
    border-radius: 26px;
    background: linear-gradient(180deg, rgba(255,255,255,0.045), rgba(255,255,255,0.02));
    box-shadow: 0 22px 60px rgba(0,0,0,0.2);
  }
  .empty-state {
    display: grid;
    place-items: center;
    min-height: 300px;
    padding: 42px 24px;
    text-align: center;
  }
  .empty-icon {
    width: 78px;
    height: 78px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
    border: 1px solid rgba(214,165,54,0.26);
    border-radius: 22px;
    color: var(--gold-soft);
    background: rgba(214,165,54,0.09);
  }
  .empty-icon svg { width: 38px; height: 38px; }
  .empty-state h2 { margin: 0 0 10px; font-size: clamp(24px, 4vw, 34px); }
  .empty-state p { max-width: 520px; margin: 0 auto 24px; color: var(--muted); line-height: 1.6; }
  .empty-actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 12px; }

  .reminder {
    display: grid;
    grid-template-columns: 80px minmax(0, 1fr) auto;
    gap: 22px;
    align-items: center;
    margin: 34px 0 18px;
    padding: 26px;
  }
  .reminder h2 { margin: 0 0 6px; font-size: clamp(22px, 3vw, 30px); }
  .reminder p { margin: 0; color: var(--muted); line-height: 1.55; }
  .feature-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0;
    margin-top: 18px;
    overflow: hidden;
  }
  .feature {
    min-width: 0;
    padding: 28px 22px;
    text-align: center;
    border-right: 1px solid rgba(214,165,54,0.13);
  }
  .feature:last-child { border-right: 0; }
  .feature svg { width: 34px; height: 34px; color: var(--gold-soft); margin-bottom: 14px; }
  .feature strong { display: block; margin-bottom: 8px; font-size: 18px; }
  .feature span { display: block; color: var(--muted); font-size: 14px; line-height: 1.55; }

  .site-footer {
    display: grid;
    grid-template-columns: 160px minmax(0, 1fr) auto;
    gap: 22px;
    align-items: center;
    padding: 34px 0 0;
    color: rgba(247,243,232,0.58);
    font-size: 13px;
  }
  .site-footer img { width: 100px; height: auto; max-height: 48px; object-fit: contain; }
  .footer-links { display: flex; flex-wrap: wrap; justify-content: end; gap: 16px; }
  .footer-links a { color: rgba(247,243,232,0.72); text-decoration: none; }
  .footer-links a:hover { color: var(--gold-soft); }

  @media (max-width: 1040px) {
    .page { padding-inline: 24px; }
    .site-nav { margin-inline: -24px; padding-inline: 24px; }
    .hero { grid-template-columns: 1fr; min-height: auto; padding-top: 54px; }
    .hero-visual { min-height: 340px; }
    .event-card { grid-template-columns: minmax(170px, 240px) minmax(0, 1fr); gap: 22px; }
    .feature-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .feature:nth-child(2) { border-right: 0; }
    .feature:nth-child(-n+2) { border-bottom: 1px solid rgba(214,165,54,0.13); }
  }

  @media (max-width: 860px) {
    .site-nav { min-height: 76px; }
    .menu-toggle { display: block; flex: 0 0 auto; }
    .nav-menu {
      position: absolute;
      top: calc(100% + 1px);
      left: 16px;
      right: 16px;
      display: none;
      flex-direction: column;
      align-items: stretch;
      padding: 12px;
      border: 1px solid var(--border-gold);
      border-radius: 18px;
      background: rgba(16,16,15,0.98);
      box-shadow: 0 24px 60px rgba(0,0,0,0.42);
    }
    .nav-menu.is-open { display: flex; }
    .nav-menu a { justify-content: center; min-height: 46px; }
    .nav-menu a.active::after { display: none; }
    .nav-cta { display: none; }
    .benefits { grid-template-columns: 1fr; }
    .section-head { align-items: start; flex-direction: column; }
    .event-card { grid-template-columns: 1fr; }
    .featured-label {
      position: static;
      transform: none;
      justify-self: start;
      width: fit-content;
      min-height: 32px;
      margin-bottom: -4px;
      border-radius: 999px;
    }
    .poster { width: 100%; max-width: 420px; min-height: 0; margin: 0 auto; }
    .poster-fallback { min-height: 300px; }
    .event-body { padding: 0; }
    .reminder { grid-template-columns: 64px minmax(0, 1fr); }
    .reminder .btn { grid-column: 1 / -1; }
    .site-footer { grid-template-columns: 1fr; text-align: center; }
    .site-footer img { margin: 0 auto; }
    .footer-links { justify-content: center; }
  }

  @media (max-width: 560px) {
    .page { padding: 0 16px 28px; }
    .page::before { display: none; }
    .site-nav { margin-inline: -16px; padding: 13px 16px; }
    .brand-logo { min-width: 96px; }
    .brand-logo img { width: 96px; max-height: 46px; }
    .hero { padding: 42px 0 42px; text-align: left; }
    h1 { font-size: clamp(38px, 13vw, 58px); }
    .hero-subtitle { font-size: 16px; }
    .hero-visual { min-height: 260px; border-radius: 22px; }
    .audience { display: none; }
    .stage-card { inset: auto 16px 16px; grid-template-columns: 56px minmax(0, 1fr); padding: 14px; }
    .stage-icon { width: 56px; height: 56px; border-radius: 16px; }
    .stage-icon svg { width: 28px; height: 28px; }
    .stage-card strong { font-size: 15px; }
    .stage-card span { font-size: 12px; }
    .timeline { padding-left: 18px; }
    .timeline::before { left: 6px; }
    .tl-dot { left: -18px; top: 26px; width: 14px; height: 14px; box-shadow: 0 0 0 4px rgba(214,165,54,0.08); }
    .tl-dot::after { width: 5px; height: 5px; }
    .tl-node.featured .tl-dot { left: -20px; width: 18px; height: 18px; }
    .event-card { padding: 14px; border-radius: 22px; }
    .featured-label { font-size: 11px; margin-bottom: -2px; }
    .poster { max-width: none; aspect-ratio: 4 / 5; border-radius: 16px; }
    .event-top { grid-template-columns: 62px minmax(0, 1fr); gap: 13px; }
    .date-badge { width: 62px; min-height: 66px; }
    .date-badge strong { font-size: 24px; }
    .event-details { margin-bottom: 18px; }
    .actions { grid-template-columns: 1fr; }
    .btn { width: 100%; min-height: 50px; }
    .empty-actions { flex-direction: column; }
    .reminder { grid-template-columns: 1fr; text-align: center; padding: 22px; }
    .reminder .icon-bubble { margin: 0 auto; }
    .feature-grid { grid-template-columns: 1fr; }
    .feature { border-right: 0; border-bottom: 1px solid rgba(214,165,54,0.13); }
    .feature:last-child { border-bottom: 0; }
  }
</style>
</head>
<body>
<div class="page">
  <nav class="site-nav" aria-label="Navigasi utama">
    <a class="brand-logo" href="/" aria-label="<?= kalender_h($brand['name']) ?>">
      <img src="<?= kalender_h($logoPath) ?>" alt="<?= kalender_h($brand['name']) ?>">
    </a>
    <button class="menu-toggle" type="button" aria-label="Buka menu" aria-expanded="false" aria-controls="navMenu"><span></span></button>
    <div class="nav-menu" id="navMenu">
      <a href="/">Beranda</a>
      <a class="active" href="/kalender/" aria-current="page">Kalender Event</a>
      <a href="/challenge/">Challenge</a>
      <a href="/#tentang">Tentang Kami</a>
      <a href="/#kontak">Kontak</a>
    </div>
    <a class="nav-cta" href="/buat-link.php">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm7 8a7 7 0 0 0-14 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      Buat Link Referral
    </a>
  </nav>

  <header class="hero">
    <div>
      <div class="badge">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3v3m10-3v3M4 9h16M6 5h12a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        Kalender Event
      </div>
      <h1>Jadwal Webinar &amp; <span>Event Mendatang</span></h1>
      <p class="hero-subtitle">Temukan acara <?= kalender_h($brand['name']) ?> yang akan datang, mulai dari webinar edukasi, sesi inspirasi, hingga event komunitas.</p>
      <div class="benefits" aria-label="Keunggulan event">
        <div class="benefit">
          <span class="icon-bubble"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3v3m10-3v3M4 9h16m-9 5h6m-6 4h3M6 5h12a2 2 0 0 1 2 2v12H4V7a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
          <span><strong>Selalu Update</strong><span>Jadwal terbaru tampil otomatis.</span></span>
        </div>
        <div class="benefit">
          <span class="icon-bubble"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 11a4 4 0 1 0-8 0m12 9a8 8 0 0 0-16 0m15-10a3 3 0 0 1 2 5.2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
          <span><strong>Event Berkualitas</strong><span>Webinar dan sesi pilihan bersama praktisi.</span></span>
        </div>
        <div class="benefit">
          <span class="icon-bubble"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M8 21h8M12 17v4M7 4h10v4a5 5 0 0 1-10 0V4Zm-3 2h3v2a4 4 0 0 1-3-2Zm16 0h-3v2a4 4 0 0 0 3-2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
          <span><strong>Tingkatkan Diri</strong><span>Dapatkan insight dan strategi yang relevan.</span></span>
        </div>
      </div>
    </div>
    <div class="hero-visual" aria-hidden="true">
      <div class="stage-lights"></div>
      <div class="audience">
        <i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i>
        <i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i>
      </div>
      <div class="stage-card">
        <span class="stage-icon"><svg viewBox="0 0 24 24" fill="none"><path d="M7 3v3m10-3v3M4 9h16M6 5h12a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
        <span><strong>RahasiaEmas.id Event Calendar</strong><span>Semua jadwal publik dalam satu halaman yang mudah dibuka dari WhatsApp.</span></span>
      </div>
    </div>
  </header>

  <main>
    <section id="events" aria-labelledby="events-title">
      <div class="section-head">
        <div>
          <p class="section-kicker">Kalender Publik</p>
          <h2 id="events-title">Event Terdekat</h2>
          <p>Daftar event yang paling dekat waktunya. Jadwal yang belum punya tanggal tetap berada di bawah.</p>
        </div>
        <?php if (!empty($events)): ?>
          <a class="section-link" href="#events">Lihat semua event <span aria-hidden="true">-&gt;</span></a>
        <?php endif; ?>
      </div>

      <?php if (empty($events)): ?>
        <div class="empty-state">
          <div>
            <span class="empty-icon"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3v3m10-3v3M4 9h16M6 5h12a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
            <h2>Belum ada event mendatang</h2>
            <p>Jadwal baru akan tampil di sini setelah tersedia. Kamu tetap bisa melihat challenge yang sedang aktif atau menghubungi admin.</p>
            <div class="empty-actions">
              <a class="btn btn-primary" href="/challenge/">Lihat Challenge</a>
              <?php if ($whatsappUrl): ?><a class="btn btn-secondary" href="<?= kalender_h($whatsappUrl) ?>" target="_blank" rel="noopener">Hubungi Admin</a><?php endif; ?>
            </div>
          </div>
        </div>
      <?php else: ?>
        <div class="timeline">
          <?php foreach ($events as $i => $ev):
            $dateParts = kalender_date_parts($ev['event_date'] ?? null, $bulanIndo, $bulanPanjangIndo);
            $relative = kalender_relative_label($ev['event_date'] ?? null);
            $featured = ($i === 0 && $dateParts['has_date']);
            $eventUrl = kalender_event_url($brand, $ev);
            $challengeUrl = kalender_challenge_url($ev);
            $posterLinkTag = $eventUrl ? 'a' : 'div';
            $loadingMode = $i === 0 ? 'eager' : 'lazy';
          ?>
            <article class="tl-node<?= $featured ? ' featured' : '' ?>">
              <span class="tl-dot" aria-hidden="true"></span>
              <div class="event-card<?= $featured ? ' featured' : '' ?>">
                <?php if ($featured): ?><span class="featured-label">Event Terdekat</span><?php endif; ?>
                <<?= $posterLinkTag ?> class="poster"<?php if ($eventUrl): ?> href="<?= kalender_h($eventUrl) ?>" aria-label="Lihat detail <?= kalender_h($ev['name']) ?>"<?php endif; ?>>
                  <?php if (!empty($ev['flyer_path'])): ?>
                    <img src="<?= kalender_h($ev['flyer_path']) ?>" alt="Flyer <?= kalender_h($ev['name']) ?>" loading="<?= kalender_h($loadingMode) ?>" width="640" height="800" onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
                    <span class="poster-fallback" hidden>
                      <img class="mark" src="<?= kalender_h($logoPath) ?>" alt="">
                      <strong><?= kalender_h($ev['name']) ?></strong>
                      <span><?= $dateParts['has_date'] ? kalender_h($dateParts['full']) : 'Jadwal Menyusul' ?></span>
                    </span>
                  <?php else: ?>
                    <span class="poster-fallback">
                      <img class="mark" src="<?= kalender_h($logoPath) ?>" alt="">
                      <strong><?= kalender_h($ev['name']) ?></strong>
                      <span><?= $dateParts['has_date'] ? kalender_h($dateParts['full']) : 'Jadwal Menyusul' ?></span>
                    </span>
                  <?php endif; ?>
                </<?= $posterLinkTag ?>>

                <div class="event-body">
                  <div class="event-top">
                    <?php if ($dateParts['has_date']): ?>
                      <div class="date-badge">
                        <strong><?= kalender_h($dateParts['day']) ?></strong>
                        <span><?= kalender_h($dateParts['month_short']) ?></span>
                      </div>
                    <?php else: ?>
                      <div class="date-badge tbd">Jadwal<br>Menyusul</div>
                    <?php endif; ?>
                    <div>
                      <?php if ($relative): ?><span class="countdown"><?= kalender_h($relative) ?></span><?php endif; ?>
                      <h3 class="event-name"><?= kalender_h($ev['name']) ?></h3>
                    </div>
                  </div>

                  <div class="event-details">
                    <?php if (!empty($ev['event_day']) || !empty($ev['event_time'])): ?>
                      <div class="detail">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3v3m10-3v3M4 9h16M6 5h12a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        <span><?= !empty($ev['event_day']) ? kalender_h($ev['event_day']) : kalender_h($dateParts['full'] ?? 'Jadwal Menyusul') ?><?= !empty($ev['event_time']) ? ' &middot; ' . kalender_h($ev['event_time']) : '' ?></span>
                      </div>
                    <?php endif; ?>
                    <?php if (!empty($ev['event_location'])): ?>
                      <div class="detail">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 21s7-5.2 7-11a7 7 0 1 0-14 0c0 5.8 7 11 7 11Zm0-8a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <span><?= kalender_h($ev['event_location']) ?></span>
                      </div>
                    <?php endif; ?>
                    <?php if (!empty($ev['event_speaker'])): ?>
                      <div class="detail">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm7 8a7 7 0 0 0-14 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        <span><?= kalender_h($ev['event_speaker']) ?></span>
                      </div>
                    <?php endif; ?>
                  </div>

                  <div class="actions">
                    <?php if ($eventUrl): ?>
                      <a class="btn btn-primary" href="<?= kalender_h($eventUrl) ?>">Lihat Selengkapnya <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
                    <?php endif; ?>
                    <?php if ($challengeUrl): ?>
                      <a class="btn btn-secondary" href="<?= kalender_h($challengeUrl) ?>">Lihat Challenge</a>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="reminder" aria-labelledby="reminder-title">
      <span class="icon-bubble" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M7 3v3m10-3v3M4 9h16M6 5h12a2 2 0 0 1 2 2v11H4V7a2 2 0 0 1 2-2Zm5 10 2 2 4-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
      <div>
        <h2 id="reminder-title">Jangan lewatkan event terbaru!</h2>
        <p>Dapatkan pengingat event langsung ke WhatsApp agar tidak ketinggalan informasi penting.</p>
      </div>
      <?php if ($whatsappUrl): ?>
        <a class="btn btn-primary" href="<?= kalender_h($whatsappUrl) ?>" target="_blank" rel="noopener">Aktifkan Pengingat</a>
      <?php endif; ?>
    </section>

    <section class="feature-grid" aria-label="Kenapa ikut event kami">
      <div class="feature">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20M4 4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <strong>Materi praktis</strong>
        <span>Topik dirancang agar mudah dipahami dan bisa langsung diterapkan.</span>
      </div>
      <div class="feature">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm7 8a7 7 0 0 0-14 0m14-13 2 2 3-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <strong>Narasumber berpengalaman</strong>
        <span>Sesi dipandu oleh coach atau praktisi sesuai detail event.</span>
      </div>
      <div class="feature">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 11a4 4 0 1 0-8 0m12 9a8 8 0 0 0-16 0m15-10a3 3 0 0 1 2 5.2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <strong>Komunitas bertumbuh</strong>
        <span>Cocok untuk peserta dari komunitas, WhatsApp, referral, dan iklan.</span>
      </div>
      <div class="feature">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <strong>Akses online mudah</strong>
        <span>Setiap event mengarah ke halaman detail atau challenge yang sudah tersedia.</span>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <img src="<?= kalender_h($logoPath) ?>" alt="<?= kalender_h($brand['name']) ?>">
    <span>&copy; <?= kalender_h(date('Y')) ?> <?= kalender_h($brand['name']) ?>. All rights reserved.</span>
    <div class="footer-links">
      <a href="/#privacy">Kebijakan Privasi</a>
      <a href="/#terms">Syarat &amp; Ketentuan</a>
      <a href="/#kontak">Kontak</a>
    </div>
  </footer>
</div>
<script>
  (function () {
    var toggle = document.querySelector('.menu-toggle');
    var menu = document.getElementById('navMenu');
    if (!toggle || !menu) return;
    toggle.addEventListener('click', function () {
      var isOpen = menu.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
  })();
</script>
</body>
</html>
