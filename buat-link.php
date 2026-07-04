<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/bootstrap.php';

$brand = require_brand_or_404(get_current_brand());
$brandId = (int)$brand['id'];

$eventSlug = clean($_GET['event'] ?? $brand['default_event_slug']);
$event = get_event_by_slug($eventSlug);
if (!$event || (int)$event['brand_id'] !== $brandId || $event['status'] !== 'active') {
    $eventSlug = $brand['default_event_slug'];
    $event = get_event_by_slug($eventSlug);
}
$eventDisplayName = $event['name'] ?? $brand['name'];
$logoPath = $brand['logo_path'] ? $brand['logo_path'] : 'assets/logo.png';
$eventUrl = $eventSlug === $brand['default_event_slug'] ? '/' : EVENTS_URL_BASE . '/' . rawurlencode($eventSlug) . '/';
$challengeUrl = '/challenge/?event=' . urlencode($eventSlug);
$previewPath = $eventSlug === $brand['default_event_slug'] ? '/?ref=' : EVENTS_URL_BASE . '/' . rawurlencode($eventSlug) . '/?ref=';

/** Ikon SVG monokrom (feather-style) — mengikuti warna teks/box induknya lewat currentColor. */
function ui_icon(string $name): string
{
    $paths = [
        'link' => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'check' => '<polyline points="20 6 9 17 4 12"/>',
        'trophy' => '<path d="M8 21h8"/><path d="M12 17v4"/><path d="M7 4h10v3a5 5 0 0 1-10 0V4z"/><path d="M7 5H4.5a2 2 0 0 0 0 4H7"/><path d="M17 5h2.5a2 2 0 0 1 0 4H17"/>',
        'arrow-up-right' => '<line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/>',
        'image' => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
        'message' => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
        'copy' => '<rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
        'send' => '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
        'eye' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
    ];
    $path = $paths[$name] ?? '';
    return '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Buat Link Undanganmu — <?= htmlspecialchars($brand['name']) ?></title>
<link rel="icon" href="<?= htmlspecialchars($logoPath) ?>">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
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
    --shadow: 0 24px 80px rgba(0,0,0,0.38);
  }

  * { box-sizing: border-box; margin: 0; padding: 0; }
  html { scroll-behavior: smooth; }
  body {
    min-height: 100vh;
    overflow-x: hidden;
    background:
      radial-gradient(circle at 18% 0%, rgba(214,165,54,0.20), transparent 34vw),
      radial-gradient(circle at 88% 92%, rgba(244,210,122,0.12), transparent 36vw),
      linear-gradient(135deg, var(--bg) 0%, var(--bg-soft) 48%, #070707 100%);
    color: var(--text);
    font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  }
  body::before {
    content: "";
    position: fixed;
    inset: 0;
    pointer-events: none;
    background-image:
      radial-gradient(circle at 12% 22%, rgba(244,210,122,0.34) 0 1px, transparent 2px),
      radial-gradient(circle at 72% 12%, rgba(214,165,54,0.26) 0 1px, transparent 2px),
      radial-gradient(circle at 88% 54%, rgba(244,210,122,0.22) 0 1px, transparent 2px),
      linear-gradient(rgba(255,255,255,0.018) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,0.014) 1px, transparent 1px);
    background-size: auto, auto, auto, 56px 56px, 56px 56px;
    mask-image: radial-gradient(circle at 50% 20%, black, transparent 76%);
    opacity: .75;
  }
  a { color: inherit; }
  button, input { font: inherit; }

  .page {
    position: relative;
    width: min(100%, 1120px);
    margin: 0 auto;
    padding: 22px 18px 42px;
  }
  .site-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding-bottom: 22px;
  }
  .logo {
    display: inline-flex;
    align-items: center;
    text-decoration: none;
  }
  .logo img {
    display: block;
    width: clamp(96px, 28vw, 148px);
    height: auto;
    filter: drop-shadow(0 12px 24px rgba(0,0,0,0.35));
  }
  .event-chip {
    display: none;
    max-width: 48%;
    color: var(--gold-soft);
    background: rgba(214,165,54,0.10);
    border: 1px solid var(--border-gold);
    border-radius: 999px;
    font-size: 12px;
    font-weight: 800;
    padding: 9px 12px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 18px;
  }
  .hero {
    text-align: center;
    padding: 6px 2px 2px;
  }
  .badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: fit-content;
    color: var(--gold-soft);
    background: rgba(214,165,54,0.12);
    border: 1px solid var(--border-gold);
    border-radius: 999px;
    font-size: 11.5px;
    font-weight: 900;
    letter-spacing: .06em;
    text-transform: uppercase;
    padding: 8px 12px;
  }
  h1 {
    color: var(--gold-soft);
    font-size: clamp(32px, 10vw, 58px);
    line-height: 1.02;
    letter-spacing: 0;
    margin: 16px auto 12px;
    max-width: 720px;
  }
  .subtitle {
    color: var(--muted);
    font-size: 15px;
    line-height: 1.65;
    max-width: 620px;
    margin: 0 auto;
  }
  .event-line {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--text);
    background: rgba(255,255,255,0.045);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 999px;
    font-size: 13px;
    font-weight: 800;
    margin-top: 16px;
    padding: 9px 12px;
  }
  .hero-actions {
    display: flex;
    gap: 10px;
    justify-content: center;
    margin-top: 20px;
  }
  .panel {
    border: 1px solid var(--border-gold);
    border-radius: 24px;
    background: linear-gradient(180deg, rgba(32,32,30,0.94), rgba(23,23,22,0.96));
    box-shadow: 0 18px 54px rgba(0,0,0,0.30);
  }
  .info-stack {
    display: grid;
    gap: 14px;
  }
  .benefits {
    display: grid;
    gap: 10px;
  }
  .benefit-card {
    display: grid;
    grid-template-columns: 42px minmax(0, 1fr);
    gap: 12px;
    align-items: start;
    border: 1px solid rgba(214,165,54,0.16);
    border-radius: 18px;
    background: rgba(255,255,255,0.035);
    padding: 14px;
  }
  .icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    width: 42px;
    height: 42px;
    color: #111;
    background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    border-radius: 14px;
    font-weight: 900;
    font-size: 16px;
    box-shadow: 0 12px 26px rgba(214,165,54,0.16);
  }
  .icon .ico { width: 20px; height: 20px; }
  .benefit-card strong {
    display: block;
    color: var(--text);
    font-size: 14px;
    margin-bottom: 4px;
  }
  .benefit-card div span {
    display: block;
    color: var(--muted);
    font-size: 12.5px;
    line-height: 1.5;
  }
  .form-card {
    position: relative;
    overflow: hidden;
    padding: 20px;
    border-color: rgba(244,210,122,0.34);
    background:
      linear-gradient(var(--surface), var(--surface)) padding-box,
      linear-gradient(145deg, rgba(244,210,122,0.78), rgba(214,165,54,0.14) 42%, rgba(255,255,255,0.10)) border-box;
    box-shadow:
      0 28px 90px rgba(0,0,0,0.46),
      0 0 0 1px rgba(255,255,255,0.035),
      0 0 48px rgba(214,165,54,0.13);
    isolation: isolate;
  }
  .form-card::before {
    content: "";
    position: absolute;
    inset: -120px -90px auto auto;
    width: 250px;
    height: 250px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(244,210,122,0.24), rgba(214,165,54,0.08) 46%, transparent 68%);
    pointer-events: none;
    z-index: -1;
  }
  .form-card::after {
    content: "";
    position: absolute;
    inset: 0;
    background:
      linear-gradient(90deg, transparent, rgba(244,210,122,0.18), transparent) 0 0 / 100% 1px no-repeat,
      radial-gradient(circle at 18% 10%, rgba(255,255,255,0.055), transparent 28%);
    pointer-events: none;
    z-index: -1;
  }
  .form-head {
    position: relative;
    display: grid;
    grid-template-columns: 52px minmax(0, 1fr);
    gap: 14px;
    align-items: start;
    margin-bottom: 22px;
    padding-bottom: 18px;
    border-bottom: 1px solid rgba(214,165,54,0.16);
  }
  .form-head-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 52px;
    height: 52px;
    color: #111;
    background:
      radial-gradient(circle at 34% 22%, #FFF4BF, transparent 34%),
      linear-gradient(135deg, var(--gold), var(--gold-soft));
    border-radius: 18px;
    box-shadow:
      0 16px 34px rgba(214,165,54,0.24),
      inset 0 1px 0 rgba(255,255,255,0.45);
  }
  .form-head-icon .ico {
    width: 25px;
    height: 25px;
  }
  .form-kicker {
    display: inline-flex;
    width: fit-content;
    color: var(--gold-soft);
    background: rgba(214,165,54,0.10);
    border: 1px solid rgba(214,165,54,0.22);
    border-radius: 999px;
    font-size: 10.5px;
    font-weight: 900;
    letter-spacing: .08em;
    text-transform: uppercase;
    padding: 6px 9px;
    margin-bottom: 10px;
  }
  .form-head h2 {
    color: var(--gold-soft);
    font-size: 26px;
    line-height: 1.2;
    margin-bottom: 7px;
  }
  .form-head p {
    color: var(--muted);
    font-size: 14px;
    line-height: 1.55;
  }
  .field {
    position: relative;
    margin-bottom: 17px;
  }
  .field label {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text);
    font-size: 13px;
    font-weight: 800;
    margin-bottom: 8px;
  }
  .field label::before {
    content: "";
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--gold);
    box-shadow: 0 0 12px rgba(214,165,54,0.58);
  }
  .field input {
    width: 100%;
    min-height: 58px;
    color: var(--text);
    background:
      linear-gradient(180deg, rgba(255,255,255,0.045), rgba(255,255,255,0.015)),
      #111110;
    border: 1px solid rgba(255,255,255,0.13);
    border-radius: 16px;
    font-size: 15px;
    outline: none;
    padding: 0 16px;
    box-shadow:
      inset 0 1px 0 rgba(255,255,255,0.035),
      0 10px 24px rgba(0,0,0,0.18);
    transition: border-color 180ms ease, box-shadow 180ms ease, background 180ms ease;
  }
  .field input::placeholder { color: rgba(168,162,154,0.70); }
  .field input:focus {
    border-color: rgba(244,210,122,0.82);
    box-shadow:
      0 0 0 4px rgba(214,165,54,0.13),
      0 14px 30px rgba(0,0,0,0.24),
      inset 0 1px 0 rgba(255,255,255,0.06);
    background:
      linear-gradient(180deg, rgba(244,210,122,0.055), rgba(255,255,255,0.018)),
      #141413;
  }
  .hint {
    display: block;
    color: var(--muted);
    font-size: 12px;
    line-height: 1.5;
    margin-top: 7px;
  }
  .live-preview {
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(214,165,54,0.28);
    border-radius: 20px;
    background:
      radial-gradient(circle at 100% 0%, rgba(244,210,122,0.12), transparent 34%),
      rgba(11,11,10,0.66);
    margin: 6px 0 18px;
    padding: 15px;
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.04);
  }
  .live-preview::before {
    content: "";
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: linear-gradient(180deg, var(--gold-soft), var(--gold));
  }
  .live-preview span {
    display: block;
    color: var(--gold-soft);
    font-size: 12px;
    font-weight: 900;
    letter-spacing: .04em;
    text-transform: uppercase;
    margin-bottom: 8px;
  }
  .preview-link {
    color: var(--text);
    background: rgba(255,255,255,0.045);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 12px;
    font-size: 13.5px;
    font-weight: 700;
    line-height: 1.45;
    overflow-wrap: anywhere;
    padding: 10px 11px;
  }
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    width: 100%;
    min-height: 54px;
    border: 1px solid transparent;
    border-radius: 16px;
    cursor: pointer;
    font-weight: 900;
    text-decoration: none;
    transition: transform 180ms ease, box-shadow 180ms ease, border-color 180ms ease, background 180ms ease;
  }
  .btn:hover { transform: translateY(-1px); }
  .btn:disabled {
    cursor: not-allowed;
    opacity: .68;
    transform: none;
  }
  .btn .ico { width: 18px; height: 18px; flex: 0 0 auto; }
  .btn-primary {
    color: #111;
    background:
      linear-gradient(135deg, rgba(255,255,255,0.32), transparent 34%),
      linear-gradient(135deg, var(--gold), var(--gold-soft));
    box-shadow:
      0 18px 38px rgba(214,165,54,0.28),
      inset 0 1px 0 rgba(255,255,255,0.55);
  }
  #genBtn {
    min-height: 60px;
    border-radius: 18px;
    font-size: 15.5px;
    letter-spacing: .01em;
    position: relative;
    overflow: hidden;
  }
  #genBtn::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.28), transparent);
    transform: translateX(-120%);
    transition: transform 520ms ease;
  }
  #genBtn:hover::after {
    transform: translateX(120%);
  }
  .btn-secondary {
    color: var(--text);
    background: rgba(255,255,255,0.04);
    border-color: rgba(214,165,54,0.22);
  }
  .trust-note {
    color: rgba(247,243,232,0.72);
    background: rgba(255,255,255,0.035);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 14px;
    font-size: 12px;
    line-height: 1.55;
    margin-top: 14px;
    padding: 10px 12px;
    text-align: center;
  }
  .msg {
    display: none;
    border-radius: 16px;
    font-size: 13px;
    line-height: 1.55;
    margin-top: 14px;
    padding: 13px 14px;
  }
  .msg.error {
    display: block;
    color: #FECACA;
    background: rgba(239,68,68,0.11);
    border: 1px solid rgba(239,68,68,0.28);
  }
  .copy-existing-link {
    display: block;
    width: 100%;
    color: var(--gold-soft);
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(214,165,54,0.24);
    border-radius: 12px;
    cursor: pointer;
    font: inherit;
    font-size: 12.5px;
    line-height: 1.45;
    margin: 10px 0;
    overflow-wrap: anywhere;
    padding: 10px 12px;
    text-align: center;
  }
  .copy-hint {
    display: block;
    color: var(--muted);
    font-size: 12px;
  }

  .result {
    display: none;
    margin-top: 16px;
    padding: 18px;
  }
  .success-card {
    border-color: rgba(34,197,94,0.28);
    background:
      radial-gradient(circle at 85% 0%, rgba(34,197,94,0.10), transparent 36%),
      linear-gradient(180deg, rgba(32,32,30,0.96), rgba(23,23,22,0.96));
  }
  .card-title {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--gold-soft);
    font-size: 18px;
    font-weight: 900;
    margin-bottom: 6px;
  }
  .status-icon, .card-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    font-weight: 900;
  }
  .status-icon { color: #051B0C; background: var(--success); }
  .card-icon { color: #111; background: linear-gradient(135deg, var(--gold), var(--gold-soft)); }
  .status-icon .ico, .card-icon .ico { width: 17px; height: 17px; }
  .card-desc {
    color: var(--muted);
    font-size: 13px;
    line-height: 1.6;
    margin-bottom: 14px;
  }
  .event-detail {
    display: none;
    color: var(--muted);
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 14px;
    font-size: 13px;
    line-height: 1.75;
    margin-bottom: 14px;
    padding: 13px;
  }
  .event-detail strong { color: var(--gold-soft); }
  .link-box {
    display: grid;
    gap: 10px;
    margin-bottom: 12px;
  }
  .link-box input {
    width: 100%;
    min-height: 50px;
    color: var(--gold-soft);
    background: #111110;
    border: 1px solid rgba(214,165,54,0.20);
    border-radius: 14px;
    outline: none;
    padding: 0 13px;
  }
  .result-actions {
    display: grid;
    gap: 10px;
  }
  .flyer-preview {
    display: block;
    width: 100%;
    max-height: 360px;
    object-fit: contain;
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 16px;
    background: #111110;
    margin-bottom: 12px;
  }
  .templates {
    text-align: left;
  }
  .template-card {
    background: rgba(11,11,10,0.62);
    border: 1px solid rgba(255,255,255,0.09);
    border-radius: 16px;
    padding: 13px;
    margin-top: 10px;
  }
  .template-card .t-head {
    color: var(--gold-soft);
    font-size: 12.5px;
    font-weight: 900;
    margin-bottom: 8px;
  }
  .template-card pre {
    white-space: pre-wrap;
    word-break: break-word;
    color: var(--text);
    font-family: inherit;
    font-size: 12.5px;
    line-height: 1.55;
    max-height: 150px;
    overflow-y: auto;
    margin-bottom: 10px;
  }

  @media (min-width: 760px) {
    .page { padding: 28px 32px 56px; }
    .event-chip { display: inline-block; }
    .layout {
      grid-template-columns: minmax(0, 1fr) minmax(360px, 430px);
      align-items: start;
      gap: 32px;
    }
    .hero {
      text-align: left;
      padding-top: 28px;
    }
    h1 { margin-left: 0; }
    .subtitle { margin-left: 0; }
    .hero-actions { justify-content: flex-start; }
    .hero-actions .btn { width: auto; min-width: 166px; }
    .benefits {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .info-stack {
      margin-top: 18px;
    }
    .form-card {
      position: sticky;
      top: 22px;
      border-radius: 28px;
      padding: 24px;
    }
    .result-actions {
      grid-template-columns: 1fr 1fr;
    }
    .result-actions .wide {
      grid-column: span 2;
    }
  }

  @media (max-width: 420px) {
    .page { padding: 16px 14px 34px; }
    .site-header { padding-bottom: 16px; }
    .subtitle { font-size: 14px; }
    .hero-actions { flex-direction: column; }
    .benefit-card {
      grid-template-columns: 38px minmax(0, 1fr);
      border-radius: 16px;
      padding: 12px;
    }
    .icon {
      width: 38px;
      height: 38px;
      border-radius: 13px;
    }
    .form-card { padding: 18px; }
    .form-head {
      grid-template-columns: 44px minmax(0, 1fr);
      gap: 12px;
    }
    .form-head-icon {
      width: 44px;
      height: 44px;
      border-radius: 15px;
    }
    .form-head h2 {
      font-size: 23px;
    }
  }
</style>
</head>
<body>
<div class="page">
  <header class="site-header">
    <a class="logo" href="/" aria-label="<?= htmlspecialchars($brand['name']) ?>">
      <img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($brand['name']) ?>">
    </a>
    <span class="event-chip">Event: <?= htmlspecialchars($eventDisplayName) ?></span>
  </header>

  <main class="layout">
    <section class="info-stack" aria-labelledby="page-title">
      <div class="hero">
        <span class="badge">Link Referral Pengundang</span>
        <h1 id="page-title">Buat Link Undanganmu</h1>
        <p class="subtitle">Dapatkan link pribadi untuk mengundang peserta. Setiap orang yang daftar lewat link kamu akan tercatat atas namamu.</p>
        <div class="event-line">Event: <?= htmlspecialchars($eventDisplayName) ?></div>
        <div class="hero-actions">
          <a class="btn btn-primary" href="#builder"><?= ui_icon('link') ?><span>Mulai Buat Link</span></a>
          <a class="btn btn-secondary" href="<?= htmlspecialchars($eventUrl) ?>"><?= ui_icon('eye') ?><span>Lihat Event</span></a>
        </div>
      </div>

      <div class="benefits" aria-label="Manfaat link referral">
        <article class="benefit-card">
          <span class="icon"><?= ui_icon('link') ?></span>
          <div><strong>Link pribadi siap dibagikan</strong><span>Satu link khusus untuk semua promosi event kamu.</span></div>
        </article>
        <article class="benefit-card">
          <span class="icon"><?= ui_icon('check') ?></span>
          <div><strong>Pendaftar tercatat otomatis</strong><span>Setiap peserta dari link kamu masuk atas namamu.</span></div>
        </article>
        <article class="benefit-card">
          <span class="icon"><?= ui_icon('trophy') ?></span>
          <div><strong>Bisa naik leaderboard</strong><span>Ajak lebih banyak peserta dan pantau posisi challenge.</span></div>
        </article>
        <article class="benefit-card">
          <span class="icon"><?= ui_icon('arrow-up-right') ?></span>
          <div><strong>Lead Bertambah</strong><span>Anda mendapatkan lebih banyak calon konsumen.</span></div>
        </article>
      </div>

    </section>

    <section class="panel form-card" id="builder" aria-labelledby="form-title">
      <form id="genForm">
        <input type="hidden" name="event" value="<?= htmlspecialchars($eventSlug) ?>">
        <div class="form-head">
          <span class="form-head-icon"><?= ui_icon('link') ?></span>
          <div>
            <span class="form-kicker">Referral Link Builder</span>
            <h2 id="form-title">Buat Link Referral</h2>
            <p>Isi data berikut untuk membuat link undangan pribadi.</p>
          </div>
        </div>

        <div class="field">
          <label for="name">Nama Kamu</label>
          <input id="name" type="text" name="name" placeholder="Nama lengkap kamu" required minlength="3" autocomplete="name">
          <small class="hint">Gunakan nama yang mudah dikenali peserta.</small>
        </div>

        <div class="field">
          <label for="whatsapp">Nomor WhatsApp Kamu</label>
          <input id="whatsapp" type="tel" name="whatsapp" placeholder="08xxxxxxxxxx" required minlength="9" inputmode="tel" autocomplete="tel">
          <small class="hint">Gunakan nomor aktif karena pendaftar bisa terhubung ke WhatsApp kamu.</small>
        </div>

        <div class="field">
          <label for="ref_code">Kode Link yang Diinginkan</label>
          <input id="ref_code" type="text" name="ref_code" placeholder="contoh: budiemas" required minlength="3" maxlength="20" pattern="[a-zA-Z0-9_-]+" autocomplete="off">
          <small class="hint">Gunakan 3-20 karakter: huruf, angka, strip (-), atau underscore (_).</small>
        </div>

        <div class="live-preview" aria-live="polite">
          <span>Preview Link Kamu</span>
          <div class="preview-link" id="livePreview"></div>
        </div>

        <button type="submit" class="btn btn-primary" id="genBtn"><?= ui_icon('link') ?> <span class="btn-label">Buat Link Undangan Saya</span></button>
        <p class="trust-note">Data kamu hanya digunakan untuk mencatat link referral dan menghubungkan pendaftar dari undanganmu.</p>
      </form>

      <div class="msg" id="errMsg"></div>

      <div class="panel result success-card" id="resultBox">
        <div class="card-title"><span class="status-icon"><?= ui_icon('check') ?></span> Link Undangan Berhasil Dibuat</div>
        <div class="card-desc">Bagikan link ini agar peserta yang mendaftar otomatis tercatat sebagai undanganmu.</div>
        <div class="event-detail" id="eventDetail"></div>
        <div class="link-box">
          <input type="text" id="linkOutput" readonly aria-label="Link referral kamu">
          <button class="btn btn-primary" id="copyBtn" type="button"><?= ui_icon('copy') ?> <span class="btn-label">Salin Link</span></button>
        </div>
        <div class="result-actions">
          <a href="#" id="waShareBtn" class="btn btn-primary" target="_blank" rel="noopener"><?= ui_icon('send') ?><span>Bagikan ke WhatsApp</span></a>
          <a href="<?= htmlspecialchars($eventUrl) ?>" class="btn btn-secondary"><?= ui_icon('eye') ?><span>Lihat Halaman Event</span></a>
          <a href="<?= htmlspecialchars($challengeUrl) ?>" class="btn btn-secondary wide"><?= ui_icon('trophy') ?><span>Lihat Challenge</span></a>
        </div>
      </div>

      <div class="panel result" id="flyerBox">
        <div class="card-title"><span class="card-icon"><?= ui_icon('image') ?></span> Flyer Acara</div>
        <div class="card-desc">Unduh flyer ini dan kirimkan bersama link referralmu supaya calon peserta lebih yakin untuk mendaftar.</div>
        <img id="flyerImage" class="flyer-preview" alt="Flyer acara">
        <a href="#" id="flyerDownloadBtn" class="btn btn-secondary" download><?= ui_icon('download') ?><span>Unduh Flyer</span></a>
      </div>

      <div class="panel result templates" id="templateBox">
        <div class="card-title"><span class="card-icon"><?= ui_icon('message') ?></span> Template Balasan untuk Peserta</div>
        <div class="card-desc">Setelah ada yang mendaftar lewat link kamu, salin salah satu template ini, ganti [Nama Peserta], lalu kirim ke mereka via WhatsApp.</div>
        <div id="templateList"></div>
      </div>
    </section>
  </main>
</div>

<script>
const eventName = <?= json_encode($eventDisplayName) ?>;
const previewPath = <?= json_encode($previewPath) ?>;
const previewOrigin = window.location.origin;
const form = document.getElementById('genForm');
const genBtn = document.getElementById('genBtn');
const errMsg = document.getElementById('errMsg');
const resultBox = document.getElementById('resultBox');
const templateBox = document.getElementById('templateBox');
const flyerBox = document.getElementById('flyerBox');
const flyerImage = document.getElementById('flyerImage');
const flyerDownloadBtn = document.getElementById('flyerDownloadBtn');
const linkOutput = document.getElementById('linkOutput');
const copyBtn = document.getElementById('copyBtn');
const waShareBtn = document.getElementById('waShareBtn');
const eventDetail = document.getElementById('eventDetail');
const templateList = document.getElementById('templateList');
const refCodeInput = document.getElementById('ref_code');
const livePreview = document.getElementById('livePreview');
const genBtnLabel = genBtn.querySelector('.btn-label');
const copyBtnLabel = copyBtn.querySelector('.btn-label');
const ICON_COPY = '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';

function sanitizeRefPreview(value) {
  return value
    .toLowerCase()
    .trim()
    .replace(/\s+/g, '-')
    .replace(/[^a-z0-9_-]/g, '')
    .slice(0, 20);
}

function updatePreview() {
  const code = sanitizeRefPreview(refCodeInput.value) || 'kodekamu';
  livePreview.textContent = previewOrigin + previewPath + code;
}

refCodeInput.addEventListener('input', function () {
  const caret = refCodeInput.selectionStart;
  const cleaned = sanitizeRefPreview(refCodeInput.value);
  if (refCodeInput.value !== cleaned) {
    refCodeInput.value = cleaned;
    refCodeInput.setSelectionRange(Math.min(caret, cleaned.length), Math.min(caret, cleaned.length));
  }
  updatePreview();
});
updatePreview();

function renderFlyer(event) {
  const flyerPath = event && event.flyer_path;
  if (!flyerPath) { flyerBox.style.display = 'none'; return; }
  flyerImage.src = flyerPath;
  flyerDownloadBtn.href = flyerPath;
  flyerBox.style.display = 'block';
}

function renderEventDetail(event) {
  if (!event) { eventDetail.style.display = 'none'; return; }
  const rows = [
    ['Acara', event.name],
    ['Hari/Tanggal', event.event_day],
    ['Waktu', event.event_time],
    ['Lokasi', event.event_location],
    ['Pembicara', event.event_speaker],
  ].filter(([, value]) => value);

  if (!rows.length) { eventDetail.style.display = 'none'; return; }

  eventDetail.innerHTML = rows.map(([label, value]) =>
    `<div><strong>${label}:</strong> ${escapeHtml(value)}</div>`
  ).join('');
  eventDetail.style.display = 'block';
}

function renderTemplates(templates) {
  templateList.innerHTML = '';
  if (!templates || !templates.length) { templateBox.style.display = 'none'; return; }

  templates.forEach((tpl) => {
    const card = document.createElement('div');
    card.className = 'template-card';

    const head = document.createElement('div');
    head.className = 't-head';
    head.textContent = tpl.label;
    card.appendChild(head);

    const pre = document.createElement('pre');
    pre.textContent = tpl.text;
    card.appendChild(pre);

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-secondary';
    btn.innerHTML = ICON_COPY + ' <span class="btn-label">Salin Template</span>';
    const btnLabel = btn.querySelector('.btn-label');
    btn.addEventListener('click', function () {
      copyText(tpl.text).then(() => {
        btnLabel.textContent = 'Tersalin!';
        setTimeout(() => btnLabel.textContent = 'Salin Template', 1500);
      });
    });
    card.appendChild(btn);

    templateList.appendChild(card);
  });
  templateBox.style.display = 'block';
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

function showFriendlyError(message) {
  errMsg.textContent = message || 'Terjadi kesalahan. Coba lagi.';
  errMsg.className = 'msg error';
  errMsg.style.display = 'block';
}

function showResult(link, event, templates) {
  linkOutput.value = link;
  const shareText = `Aku mengundang kamu ikut event ${eventName}. Daftar lewat link ini: ${link}`;
  waShareBtn.href = `https://wa.me/?text=${encodeURIComponent(shareText)}`;
  renderEventDetail(event);
  renderFlyer(event);
  renderTemplates(templates);
  resultBox.style.display = 'block';
  form.style.display = 'none';
  resultBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

form.addEventListener('submit', async function (e) {
  e.preventDefault();
  errMsg.style.display = 'none';
  genBtn.disabled = true;
  genBtnLabel.textContent = 'Membuat link...';

  const data = {
    name: form.name.value.trim(),
    whatsapp: form.whatsapp.value.trim(),
    ref_code: sanitizeRefPreview(form.ref_code.value),
    event: form.event.value,
  };

  try {
    const res = await fetch('api/create_referrer.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
    });
    const result = await res.json();

    if (result.success) {
      showResult(result.link, result.event, result.templates);
    } else if (result.existing_link) {
      showExistingLinkMessage(result.message, result.existing_link);
      showResult(result.existing_link, result.event, result.templates);
    } else {
      showFriendlyError(result.message);
    }
  } catch (err) {
    showFriendlyError('Gagal terhubung ke server. Coba lagi beberapa saat.');
  } finally {
    genBtn.disabled = false;
    genBtnLabel.textContent = 'Buat Link Undangan Saya';
  }
});

copyBtn.addEventListener('click', function () {
  linkOutput.select();
  copyText(linkOutput.value).then(() => {
    copyBtnLabel.textContent = 'Tersalin!';
    setTimeout(() => copyBtnLabel.textContent = 'Salin Link', 1500);
  });
});

function showExistingLinkMessage(message, link) {
  errMsg.textContent = '';
  errMsg.className = 'msg error';

  const prefix = document.createElement('span');
  prefix.textContent = message || 'Nomor WhatsApp ini sudah punya kode link.';

  const linkButton = document.createElement('button');
  linkButton.type = 'button';
  linkButton.className = 'copy-existing-link';
  linkButton.textContent = link;
  linkButton.setAttribute('aria-label', 'Salin link undangan yang sudah terdaftar');

  const hint = document.createElement('span');
  hint.className = 'copy-hint';
  hint.textContent = 'Tap link di atas untuk menyalin.';

  linkButton.addEventListener('click', function () {
    copyText(link).then(() => {
      const originalText = linkButton.textContent;
      linkButton.textContent = 'Tersalin: ' + link;
      setTimeout(() => {
        linkButton.textContent = originalText;
      }, 1600);
    });
  });

  errMsg.append(prefix, linkButton, hint);
  errMsg.style.display = 'block';
}

function copyText(text) {
  if (navigator.clipboard && window.isSecureContext) {
    return navigator.clipboard.writeText(text);
  }

  const tempInput = document.createElement('input');
  tempInput.value = text;
  tempInput.setAttribute('readonly', '');
  tempInput.style.position = 'fixed';
  tempInput.style.opacity = '0';
  document.body.appendChild(tempInput);
  tempInput.select();
  document.execCommand('copy');
  tempInput.remove();
  return Promise.resolve();
}
</script>
</body>
</html>
