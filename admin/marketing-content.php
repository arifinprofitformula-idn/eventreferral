<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
start_secure_session();

$brand = require_admin_for_brand(get_current_brand());

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$eventSlug = clean($_GET['event'] ?? '');
$event = $eventSlug !== '' ? get_event_by_slug($eventSlug) : null;
if ($event && (int)$event['brand_id'] !== (int)$brand['id']) {
    $event = null;
}
$eventNotFound = !$event;

$flyerAsset = null;
$eventConfig = [];
if (!$eventNotFound) {
    $eventDir = EVENTS_DIR . '/' . $eventSlug;
    $assetsDir = $eventDir . '/assets';
    $realEventsDir = realpath(EVENTS_DIR);
    $realEventDir = realpath($eventDir);
    $realAssetsDir = realpath($assetsDir);
    $configPath = $eventDir . '/config.json';
    $realConfigPath = realpath($configPath);

    if ($realEventDir && $realConfigPath
        && strpos($realConfigPath, $realEventDir) === 0
        && is_file($realConfigPath)) {
        $decodedConfig = json_decode((string)file_get_contents($realConfigPath), true);
        if (is_array($decodedConfig)) {
            $eventConfig = $decodedConfig;
        }
    }

    if ($realEventsDir && $realEventDir && $realAssetsDir
        && strpos($realEventDir, $realEventsDir) === 0
        && strpos($realAssetsDir, $realEventDir) === 0
        && is_dir($realAssetsDir)) {
        $candidates = [];
        foreach (new DirectoryIterator($realAssetsDir) as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $extension = strtolower($fileInfo->getExtension());
            if (!in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
                continue;
            }

            $filename = $fileInfo->getFilename();
            $priority = preg_match('/(flyer|poster|banner|promo)/i', $filename) ? 0 : 1;
            $candidates[] = [
                'filename' => $filename,
                'priority' => $priority,
            ];
        }

        usort($candidates, static function ($a, $b) {
            if ($a['priority'] === $b['priority']) {
                return strnatcasecmp($a['filename'], $b['filename']);
            }

            return $a['priority'] <=> $b['priority'];
        });

        if (!empty($candidates)) {
            $filename = $candidates[0]['filename'];
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $flyerAsset = [
                'filename' => $filename,
                'url' => EVENTS_URL_BASE . '/' . rawurlencode($eventSlug) . '/assets/' . rawurlencode($filename),
                'download' => $eventSlug . '-flyer.' . $extension,
            ];
        }
    }
}

$getEventValue = static function (array $event, array $eventConfig, string $key): string {
    $value = trim((string)($event[$key] ?? ''));
    if ($value !== '') {
        return $value;
    }

    return trim((string)($eventConfig[$key] ?? ''));
};

$eventMeta = [];
if (!$eventNotFound) {
    $eventMeta = [
        'Tanggal' => $getEventValue($event, $eventConfig, 'event_day'),
        'Waktu' => $getEventValue($event, $eventConfig, 'event_time'),
        'Lokasi' => $getEventValue($event, $eventConfig, 'event_location'),
        'Pembicara' => $getEventValue($event, $eventConfig, 'event_speaker'),
        'Kapasitas' => $getEventValue($event, $eventConfig, 'event_capacity'),
    ];
    $eventMeta = array_filter($eventMeta, static fn($value) => trim((string)$value) !== '');
}

$logoPath = $brand['logo_path'] ? '..' . $brand['logo_path'] : '../assets/logo.png';
$landingHref = !$eventNotFound ? '../e/' . rawurlencode($eventSlug) . '/' : 'events.php';
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? $brand['domain'];
$initialInviteLink = !$eventNotFound ? "{$protocol}://{$host}/buat-link.php?event=" . urlencode($eventSlug) : '';
$challengeLink = !$eventNotFound ? "{$protocol}://{$host}/challenge" : '';
$pageTitle = $eventNotFound ? 'Event Tidak Ditemukan' : 'Konten Marketing — ' . $event['name'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
  <?= get_theme_css_vars($brand) ?>
  :root {
    --bg: #0B0B0A;
    --bg-soft: #10100F;
    --surface: #171716;
    --surface-elevated: #20201E;
    --border-gold: color-mix(in srgb, var(--gold) 18%, transparent);
    --border-soft: rgba(255,255,255,0.09);
    --gold: var(--brand-primary, #D6A536);
    --gold-soft: var(--brand-soft, #F4D27A);
    --text: #F7F3E8;
    --muted: #A8A29A;
    --success: #22C55E;
    --danger: #EF4444;
    --warning: #F59E0B;
    --shadow: 0 24px 80px rgba(0,0,0,0.34);
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html { scroll-behavior: smooth; }
  body {
    min-height: 100vh;
    background:
      radial-gradient(circle at 88% 4%, color-mix(in srgb, var(--gold) 22%, transparent), transparent 30vw),
      radial-gradient(circle at 7% 92%, color-mix(in srgb, var(--gold) 12%, transparent), transparent 34vw),
      linear-gradient(135deg, var(--bg) 0%, var(--bg-soft) 54%, #080807 100%);
    color: var(--text);
    font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  }
  body::before {
    content: "";
    position: fixed;
    inset: 0;
    pointer-events: none;
    background-image:
      linear-gradient(rgba(255,255,255,0.022) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,0.016) 1px, transparent 1px);
    background-size: 52px 52px;
    mask-image: radial-gradient(circle at 54% 22%, black, transparent 72%);
  }
  a { color: inherit; }
  .topbar {
    position: sticky;
    top: 0;
    z-index: 20;
    background: rgba(16,16,15,0.84);
    border-bottom: 1px solid color-mix(in srgb, var(--gold) 14%, transparent);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
  }
  .topbar-inner, .mkt-wrap {
    width: min(100%, 1360px);
    margin: 0 auto;
    padding-left: 32px;
    padding-right: 32px;
  }
  .topbar-inner {
    min-height: 78px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 22px;
  }
  .brand { display: inline-flex; align-items: center; text-decoration: none; }
  .brand img { width: 146px; height: auto; display: block; object-fit: contain; filter: drop-shadow(0 10px 20px rgba(0,0,0,0.32)); }
  .nav { display: flex; align-items: center; justify-content: flex-end; gap: 10px; flex-wrap: wrap; }
  .nav a {
    min-height: 42px;
    display: inline-flex;
    align-items: center;
    padding: 10px 15px;
    border-radius: 999px;
    color: var(--muted);
    font-size: 13.5px;
    font-weight: 700;
    text-decoration: none;
    border: 1px solid transparent;
    transition: background 180ms ease, border-color 180ms ease, color 180ms ease;
  }
  .nav a:hover { color: var(--text); background: rgba(255,255,255,0.04); }
  .nav a.active {
    color: var(--gold-soft);
    background: color-mix(in srgb, var(--gold) 10%, transparent);
    border-color: var(--border-gold);
    box-shadow: inset 0 -2px 0 color-mix(in srgb, var(--gold-soft) 45%, transparent);
  }
  .nav a.logout { color: var(--text); background: rgba(255,255,255,0.035); border-color: rgba(255,255,255,0.10); }
  .mkt-wrap { position: relative; z-index: 1; padding-top: 28px; padding-bottom: 56px; }
  .mkt-hero {
    position: relative;
    overflow: hidden;
    min-height: 204px;
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(240px, 420px);
    align-items: center;
    gap: 28px;
    background:
      radial-gradient(circle at 72% 34%, color-mix(in srgb, var(--gold-soft) 24%, transparent), transparent 28%),
      linear-gradient(135deg, rgba(32,32,30,0.96), rgba(23,23,22,0.93) 58%, color-mix(in srgb, var(--gold) 18%, transparent));
    border: 1px solid var(--border-gold);
    border-radius: 28px;
    box-shadow: var(--shadow);
    padding: 30px 34px;
    margin-bottom: 24px;
  }
  .mkt-hero::before {
    content: "";
    position: absolute;
    inset: -35% -8% auto auto;
    width: 620px;
    height: 310px;
    background: radial-gradient(circle, color-mix(in srgb, var(--gold-soft) 22%, transparent), transparent 62%);
    opacity: .9;
  }
  .mkt-hero-copy, .mkt-hero-visual { position: relative; z-index: 1; }
  .mkt-breadcrumb {
    display: flex;
    align-items: center;
    gap: 9px;
    color: var(--muted);
    font-size: 12px;
    margin-bottom: 16px;
  }
  .mkt-breadcrumb a { color: var(--gold-soft); text-decoration: none; }
  .mkt-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    width: fit-content;
    padding: 7px 11px;
    border: 1px solid var(--border-gold);
    border-radius: 999px;
    background: color-mix(in srgb, var(--gold) 8%, transparent);
    color: var(--gold-soft);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .06em;
    text-transform: uppercase;
  }
  .mkt-hero h1 {
    margin: 14px 0 10px;
    font-size: clamp(28px, 4vw, 42px);
    line-height: 1.05;
    letter-spacing: -0.02em;
  }
  .mkt-hero h1 span { color: var(--gold); }
  .mkt-subtitle {
    max-width: 720px;
    color: var(--muted);
    font-size: 15px;
    line-height: 1.7;
  }
  .mkt-hero-actions, .mkt-actions, .mkt-flyer-actions, .mkt-card-actions, .mkt-result-tools { display: flex; gap: 12px; flex-wrap: wrap; }
  .mkt-hero-actions { margin-top: 20px; }
  .mkt-hero-visual {
    min-height: 150px;
    display: grid;
    place-items: center;
  }
  .mkt-studio-art {
    width: min(100%, 360px);
    aspect-ratio: 16 / 8;
    border-radius: 28px;
    border: 1px solid color-mix(in srgb, var(--gold) 20%, transparent);
    background:
      radial-gradient(circle at 24% 42%, color-mix(in srgb, var(--gold-soft) 32%, transparent), transparent 18%),
      radial-gradient(circle at 72% 50%, color-mix(in srgb, var(--gold) 18%, transparent), transparent 24%),
      rgba(255,255,255,0.035);
    display: grid;
    place-items: center;
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.08), 0 28px 90px rgba(0,0,0,0.35);
  }
  .mkt-studio-art svg { width: 210px; max-width: 72%; color: var(--gold-soft); filter: drop-shadow(0 16px 24px color-mix(in srgb, var(--gold) 26%, transparent)); }
  .mkt-layout {
    display: grid;
    grid-template-columns: minmax(0, 58fr) minmax(360px, 42fr);
    gap: 24px;
    align-items: stretch;
    margin-bottom: 24px;
  }
  .mkt-panel {
    background:
      linear-gradient(145deg, rgba(255,255,255,0.055), rgba(255,255,255,0.018)),
      var(--surface);
    border: 1px solid var(--border-gold);
    border-radius: 24px;
    box-shadow: var(--shadow);
    backdrop-filter: blur(8px);
  }
  .mkt-card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 22px 24px 18px;
    border-bottom: 1px solid rgba(255,255,255,0.075);
  }
  .mkt-step {
    flex: 0 0 auto;
    width: 31px;
    height: 31px;
    display: inline-grid;
    place-items: center;
    border-radius: 999px;
    background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    color: #111;
    font-size: 13px;
    font-weight: 900;
    box-shadow: 0 10px 22px color-mix(in srgb, var(--gold) 24%, transparent);
  }
  .mkt-card-title { display: flex; gap: 12px; align-items: flex-start; }
  .mkt-card-title h2 { font-size: 19px; line-height: 1.25; margin-bottom: 4px; }
  .mkt-card-title p, .mkt-helper, .mkt-meta, .mkt-empty p, .mkt-loading p, .mkt-error p { color: var(--muted); font-size: 13px; line-height: 1.55; }
  .mkt-form-body, .mkt-flyer-body { padding: 22px 24px 24px; }
  .mkt-field { margin-bottom: 20px; }
  .mkt-field label {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    color: var(--text);
    font-size: 13px;
    font-weight: 800;
    margin-bottom: 7px;
  }
  .mkt-field .required { color: var(--gold-soft); font-weight: 800; }
  .mkt-helper { margin-bottom: 10px; }
  .mkt-counter { color: var(--muted); font-size: 12px; font-weight: 600; }
  .mkt-panel input[type=text], .mkt-panel textarea, .mkt-panel select, .mkt-readonly-link {
    width: 100%;
    border-radius: 15px;
    padding: 13px 14px;
    background: #111110;
    border: 1px solid rgba(255,255,255,0.11);
    color: var(--text);
    font-size: 14px;
    box-sizing: border-box;
    font-family: inherit;
    outline: none;
    transition: border-color 180ms ease, box-shadow 180ms ease, background 180ms ease;
  }
  .mkt-panel textarea { min-height: 122px; resize: vertical; line-height: 1.55; }
  .mkt-panel input[type=text]:focus, .mkt-panel textarea:focus, .mkt-readonly-link:focus-within {
    border-color: color-mix(in srgb, var(--gold) 58%, transparent);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--gold) 12%, transparent);
    background: #141412;
  }
  .mkt-field-error { color: #FCA5A5; font-size: 12px; margin-top: 8px; display:none; }
  .mkt-format-tabs { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
  .mkt-format-tab {
    min-height: 54px;
    border-radius: 16px;
    border: 1px solid rgba(255,255,255,0.11);
    background: rgba(255,255,255,0.035);
    color: var(--text);
    font-family: inherit;
    font-size: 12.5px;
    font-weight: 800;
    cursor: pointer;
    padding: 10px 12px;
    text-align: left;
    transition: transform 170ms ease, border-color 170ms ease, background 170ms ease, color 170ms ease;
  }
  .mkt-format-tab span { display: block; color: var(--muted); font-size: 11px; font-weight: 600; margin-top: 3px; }
  .mkt-format-tab:hover { transform: translateY(-1px); border-color: color-mix(in srgb, var(--gold) 30%, transparent); }
  .mkt-format-tab.active { background: linear-gradient(135deg, var(--gold), var(--gold-soft)); border-color: transparent; color: #111; }
  .mkt-format-tab.active span { color: rgba(17,17,17,0.72); }
  .mkt-readonly-link { display: flex; align-items: center; gap: 10px; padding: 9px 9px 9px 14px; }
  .mkt-readonly-link code { flex: 1; color: var(--text); word-break: break-all; font-size: 13px; }
  .mkt-btn {
    border: none;
    border-radius: 14px;
    min-height: 44px;
    padding: 12px 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
    text-decoration: none;
    transition: transform 170ms ease, border-color 170ms ease, background 170ms ease, color 170ms ease, box-shadow 170ms ease;
  }
  .mkt-btn svg { flex: 0 0 auto; }
  .mkt-btn-primary { background: linear-gradient(135deg, var(--gold), var(--gold-soft)); color: #111; box-shadow: 0 14px 26px color-mix(in srgb, var(--gold) 20%, transparent); }
  .mkt-btn-primary:disabled { opacity: .55; cursor: not-allowed; transform: none; }
  .mkt-btn-primary:not(:disabled):hover, .mkt-btn-secondary:hover, .mkt-copy-btn:hover { transform: translateY(-1px); }
  .mkt-btn-secondary, .mkt-btn-ghost {
    background: rgba(255,255,255,0.04);
    border: 1px solid color-mix(in srgb, var(--gold) 22%, transparent);
    color: var(--text);
  }
  .mkt-btn-ghost { background: rgba(0,0,0,0.14); }
  .mkt-output-note {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--muted);
    font-size: 13px;
    margin: 2px 0 18px;
  }
  .mkt-output-note span { color: var(--gold-soft); }
  .mkt-flyer-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(230px, .95fr); gap: 18px; }
  .mkt-flyer-preview {
    border-radius: 20px;
    overflow: hidden;
    border: 1px solid rgba(255,255,255,0.12);
    background: rgba(0,0,0,0.24);
    min-height: 260px;
    display: grid;
    place-items: center;
  }
  .mkt-flyer-preview img { display: block; width: 100%; height: 100%; max-height: 520px; object-fit: contain; background: rgba(0,0,0,0.2); }
  .mkt-info-box, .mkt-tips, .mkt-flyer-empty {
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 18px;
    background: rgba(0,0,0,0.16);
    padding: 17px;
  }
  .mkt-info-box h3, .mkt-tips h3 { color: var(--gold-soft); font-size: 13px; margin-bottom: 12px; }
  .mkt-info-row {
    display: grid;
    grid-template-columns: 86px minmax(0, 1fr);
    gap: 12px;
    padding: 7px 0;
    color: var(--muted);
    font-size: 12.5px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
  }
  .mkt-info-row:last-child { border-bottom: 0; }
  .mkt-info-row strong { color: var(--text); font-weight: 700; }
  .mkt-tips { margin-top: 16px; }
  .mkt-tips ul { list-style: none; display: grid; gap: 10px; }
  .mkt-tips li { display: flex; gap: 9px; color: var(--muted); font-size: 13px; line-height: 1.45; }
  .mkt-tips li::before { content: "✓"; color: var(--gold-soft); font-weight: 900; }
  .mkt-flyer-empty { color: var(--muted); line-height: 1.55; }
  .mkt-flyer-empty strong { display: block; color: var(--text); margin-bottom: 6px; }
  .mkt-result-panel { padding: 22px 22px 12px; }
  .mkt-results-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 18px;
  }
  .mkt-results-head h2 { font-size: 20px; margin-bottom: 5px; }
  .mkt-result-tools .mkt-chip {
    min-height: 36px;
    padding: 8px 13px;
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.12);
    background: rgba(255,255,255,0.035);
    color: var(--text);
    font-size: 12px;
    font-weight: 800;
  }
  .mkt-result-tools .mkt-chip.active { background: linear-gradient(135deg, var(--gold), var(--gold-soft)); color: #111; border-color: transparent; }
  .mkt-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
  .mkt-copy-card {
    min-height: 100%;
    display: flex;
    flex-direction: column;
    gap: 13px;
    background:
      linear-gradient(145deg, rgba(255,255,255,0.052), rgba(255,255,255,0.02)),
      var(--surface-elevated);
    border: 1px solid var(--border-gold);
    border-radius: 22px;
    padding: 20px;
    transition: transform 180ms ease, border-color 180ms ease;
  }
  .mkt-copy-card:hover { transform: translateY(-2px); border-color: color-mix(in srgb, var(--gold) 34%, transparent); }
  .mkt-card-top { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
  .mkt-card-labels { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
  .mkt-card-index {
    width: 26px;
    height: 26px;
    display: inline-grid;
    place-items: center;
    border-radius: 999px;
    border: 1px solid var(--border-gold);
    color: var(--gold-soft);
    font-size: 12px;
    font-weight: 900;
    background: color-mix(in srgb, var(--gold) 10%, transparent);
  }
  .mkt-style-badge {
    align-self: flex-start;
    font-size: 10.5px;
    text-transform: uppercase;
    letter-spacing: .05em;
    background: color-mix(in srgb, var(--gold) 11%, transparent);
    color: var(--gold-soft);
    padding: 5px 10px;
    border-radius: 999px;
    border: 1px solid var(--border-gold);
  }
  .mkt-copy-card h3 { font-size: 16px; color: var(--text); line-height: 1.38; }
  .mkt-copy-card .sub { font-size: 13px; color: var(--muted); line-height: 1.55; }
  .mkt-copy-card .desc { color: #E6E0D1; font-size: 13.5px; line-height: 1.65; white-space: pre-line; }
  .mkt-copy-card .cta {
    border-left: 3px solid var(--gold);
    padding: 10px 12px;
    border-radius: 12px;
    background: color-mix(in srgb, var(--gold) 9%, transparent);
    color: var(--gold-soft);
    font-size: 13px;
    font-weight: 800;
    line-height: 1.5;
  }
  .mkt-card-footer { margin-top: auto; padding-top: 5px; display: flex; flex-direction: column; gap: 12px; }
  .mkt-char-row { display: flex; justify-content: space-between; gap: 10px; color: var(--muted); font-size: 12px; }
  .mkt-fit-pill { color: var(--success); background: rgba(34,197,94,0.12); border-radius: 999px; padding: 3px 9px; }
  .mkt-copy-btn {
    min-height: 40px;
    padding: 9px 12px;
    border-radius: 12px;
    border: 1px solid color-mix(in srgb, var(--gold) 18%, transparent);
    background: rgba(255,255,255,0.04);
    color: var(--text);
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
  }
  .mkt-copy-btn.copied { background: rgba(34,197,94,0.16); border-color: rgba(34,197,94,0.38); color: #BBF7D0; }
  .mkt-copy-btn.primary { background: linear-gradient(135deg, var(--gold), var(--gold-soft)); color: #111; border-color: transparent; }
  .mkt-copy-btn:disabled { opacity: .55; cursor: not-allowed; transform: none; }
  .wa-preview-wrap { display: none; margin-top: 2px; }
  .wa-preview-wrap.open { display: block; }
  .wa-chat-bg {
    border-radius: 18px;
    padding: 16px 12px;
    background:
      radial-gradient(circle at 18% 20%, rgba(255,255,255,0.04) 0, transparent 35%),
      linear-gradient(135deg, #0b141a, #101d24);
    border: 1px solid rgba(255,255,255,0.08);
  }
  .wa-bubble {
    position: relative;
    max-width: 96%;
    background: #dcf8c6;
    color: #111;
    border-radius: 12px;
    border-top-left-radius: 3px;
    padding: 10px 11px 24px;
    font-size: 13px;
    line-height: 1.48;
    white-space: pre-line;
    word-break: break-word;
    box-shadow: 0 8px 18px rgba(0,0,0,0.18);
  }
  .wa-time {
    position: absolute;
    right: 10px;
    bottom: 6px;
    color: #5c6d5f;
    font-size: 10.5px;
    display: inline-flex;
    align-items: center;
    gap: 3px;
  }
  .wa-checks { color: #53bdeb; font-size: 12px; letter-spacing: -4px; padding-right: 4px; }
  .mkt-empty, .mkt-error, .mkt-loading { text-align:center; padding: 46px 24px; color: var(--muted); }
  .mkt-empty-icon {
    width: 54px;
    height: 54px;
    display: inline-grid;
    place-items: center;
    margin-bottom: 14px;
    border-radius: 18px;
    border: 1px solid var(--border-gold);
    color: var(--gold-soft);
    background: color-mix(in srgb, var(--gold) 8%, transparent);
  }
  .mkt-empty h2, .mkt-loading h2, .mkt-error h2 { color: var(--text); font-size: 20px; margin-bottom: 8px; }
  .mkt-error h2 { color: #FCA5A5; }
  .mkt-toast {
    position: fixed;
    right: 24px;
    bottom: 24px;
    z-index: 40;
    opacity: 0;
    transform: translateY(10px);
    pointer-events: none;
    border: 1px solid rgba(34,197,94,0.32);
    border-radius: 14px;
    background: rgba(12,30,20,0.92);
    color: #D1FAE5;
    padding: 12px 15px;
    font-size: 13px;
    font-weight: 800;
    box-shadow: 0 18px 55px rgba(0,0,0,0.36);
    transition: opacity 180ms ease, transform 180ms ease;
  }
  .mkt-toast.show { opacity: 1; transform: translateY(0); }
  @media (max-width: 1040px) {
    .mkt-hero, .mkt-layout, .mkt-flyer-grid { grid-template-columns: 1fr; }
    .mkt-hero-visual { display: none; }
  }
  @media (max-width: 720px) {
    .topbar-inner, .mkt-wrap { padding-left: 16px; padding-right: 16px; }
    .topbar-inner { align-items: flex-start; flex-direction: column; padding-top: 14px; padding-bottom: 14px; }
    .brand img { width: 118px; }
    .nav { width: 100%; justify-content: flex-start; }
    .nav a { min-height: 38px; padding: 8px 11px; font-size: 12.5px; }
    .mkt-wrap { padding-top: 18px; }
    .mkt-hero { padding: 24px 20px; border-radius: 22px; }
    .mkt-hero-actions, .mkt-actions, .mkt-flyer-actions, .mkt-card-actions { flex-direction: column; }
    .mkt-btn, .mkt-copy-btn { width: 100%; }
    .mkt-card-head, .mkt-form-body, .mkt-flyer-body, .mkt-result-panel { padding-left: 18px; padding-right: 18px; }
    .mkt-results-head { flex-direction: column; }
    .mkt-grid { grid-template-columns: 1fr; }
    .mkt-readonly-link { align-items: stretch; flex-direction: column; }
    .mkt-format-tabs { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="dashboard.php" aria-label="<?= htmlspecialchars($brand['name']) ?> Admin">
      <img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($brand['name']) ?>">
    </a>
    <nav class="nav" aria-label="Navigasi admin">
      <a href="dashboard.php">Dashboard</a>
      <a href="events.php">Kelola Event</a>
      <a class="active" href="marketing-content.php<?= !$eventNotFound ? '?event=' . htmlspecialchars(rawurlencode($eventSlug)) : '' ?>">Marketing Content</a>
      <a class="logout" href="logout.php">Keluar</a>
    </nav>
  </div>
</header>

<div class="mkt-wrap">

  <?php if ($eventNotFound): ?>
    <div class="mkt-panel mkt-empty">
      <span class="mkt-empty-icon" aria-hidden="true">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 9v4m0 4h.01M10.3 3.7 2.5 17.2A2 2 0 0 0 4.2 20h15.6a2 2 0 0 0 1.7-2.8L13.7 3.7a2 2 0 0 0-3.4 0Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </span>
      <h2>Event tidak ditemukan</h2>
      <p>Silakan kembali ke daftar event dan pilih event yang valid untuk membuat konten marketing.</p>
      <div class="mkt-actions" style="justify-content:center;margin-top:18px;">
        <a class="mkt-btn mkt-btn-primary" href="events.php">Kembali ke Kelola Event</a>
      </div>
    </div>
  <?php else: ?>

    <section class="mkt-hero" aria-labelledby="studio-title">
      <div class="mkt-hero-copy">
        <div class="mkt-breadcrumb">
          <a href="dashboard.php">Dashboard</a>
          <span>/</span>
          <span>Marketing Content Generator</span>
        </div>
        <span class="mkt-eyebrow">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 11v2a2 2 0 0 0 2 2h2l4 4v-4h2l8 4V5l-8 4H5a2 2 0 0 0-2 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Marketing Content Generator
        </span>
        <h1 id="studio-title">Konten Marketing — <span><?= htmlspecialchars($event['name']) ?></span></h1>
        <p class="mkt-subtitle">Generate caption promosi siap-tempel untuk WhatsApp, komunitas, dan media sosial berdasarkan flyer dan judul event.</p>
        <div class="mkt-hero-actions">
          <a class="mkt-btn mkt-btn-secondary" href="events.php">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m15 18-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Kembali ke Kelola Event
          </a>
          <a class="mkt-btn mkt-btn-secondary" href="<?= htmlspecialchars($landingHref) ?>" target="_blank" rel="noopener">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 3h6v6M10 14 21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Lihat Landing Page
          </a>
          <?php if ($flyerAsset): ?>
            <a class="mkt-btn mkt-btn-primary" href="<?= htmlspecialchars($flyerAsset['url']) ?>" target="_blank" rel="noopener">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 3h7v7M10 14 21 3M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
              Buka Flyer Event
            </a>
          <?php endif; ?>
        </div>
      </div>
      <div class="mkt-hero-visual" aria-hidden="true">
        <div class="mkt-studio-art">
          <svg viewBox="0 0 260 130" fill="none">
            <path d="M28 42c0-12 10-22 22-22h36c12 0 22 10 22 22v15c0 12-10 22-22 22H68L45 96V79h5c-12 0-22-10-22-22V42Z" stroke="currentColor" stroke-width="5"/>
            <path d="M139 61 211 34v62l-72-27V61Z" stroke="currentColor" stroke-width="5" stroke-linejoin="round"/>
            <path d="M126 58h18v15h-18a8 8 0 0 1-8-8v1a8 8 0 0 1 8-8Z" stroke="currentColor" stroke-width="5"/>
            <path d="M158 76 146 110" stroke="currentColor" stroke-width="5" stroke-linecap="round"/>
            <path d="M215 50c10 5 16 14 16 25s-6 20-16 25" stroke="currentColor" stroke-width="5" stroke-linecap="round"/>
            <circle cx="52" cy="50" r="4" fill="currentColor"/><circle cx="68" cy="50" r="4" fill="currentColor"/><circle cx="84" cy="50" r="4" fill="currentColor"/>
          </svg>
        </div>
      </div>
    </section>

    <div class="mkt-layout">
      <div class="mkt-panel">
        <div class="mkt-card-head">
          <div class="mkt-card-title">
            <span class="mkt-step">1</span>
            <div>
              <h2>Generator Caption</h2>
              <p>Tulis judul event dan konteks tambahan untuk menghasilkan 5 variasi copywriting promosi.</p>
            </div>
          </div>
        </div>

        <div class="mkt-form-body">
          <div class="mkt-field">
            <label for="mkt-event-title">
              <span>Judul Event <span class="required">*</span></span>
              <span class="mkt-counter"><span id="mkt-title-count">0</span>/150</span>
            </label>
            <p class="mkt-helper">Gunakan judul utama yang tampil di flyer atau landing page.</p>
            <input type="text" id="mkt-event-title" maxlength="150"
                   value="<?= htmlspecialchars($event['name']) ?>"
                   placeholder="Contoh: Rahasia Cuan Emas — Strategi Anti Inflasi 2026">
            <div class="mkt-field-error" id="mkt-title-error">Judul Event wajib diisi agar konten tidak keluar konteks.</div>
          </div>

          <div class="mkt-field">
            <label>Format Target</label>
            <p class="mkt-helper">Pilih format sesuai channel promosi yang akan dipakai.</p>
            <div class="mkt-format-tabs" id="mkt-format-tabs">
              <button type="button" class="mkt-format-tab active" data-format="whatsapp_broadcast">WhatsApp Broadcast<span>Caption singkat untuk grup/broadcast</span></button>
              <button type="button" class="mkt-format-tab" data-format="whatsapp_status">Status WhatsApp<span>Sangat pendek untuk story</span></button>
              <button type="button" class="mkt-format-tab" data-format="instagram_caption">Caption Instagram<span>Hook, cerita, CTA, hashtag</span></button>
              <button type="button" class="mkt-format-tab" data-format="hook_pendek">Hook Pendek<span>Untuk reels atau short video</span></button>
            </div>
          </div>

          <div class="mkt-field">
            <label>Tujuan CTA</label>
            <p class="mkt-helper">Pilih arah ajakan utama: membuat link referral atau mengikuti challenge event utama.</p>
            <div class="mkt-format-tabs" id="mkt-cta-tabs">
              <button type="button" class="mkt-format-tab active" data-cta-target="referral">Buat Link Referral<span>CTA memakai link referral event ini</span></button>
              <button type="button" class="mkt-format-tab" data-cta-target="challenge">Ikuti Challenge<span>CTA memakai halaman /challenge</span></button>
            </div>
          </div>

          <div class="mkt-field">
            <label for="mkt-context">
              <span>Konteks Tambahan <span class="mkt-meta">(opsional)</span></span>
              <span class="mkt-counter"><span id="mkt-context-count">0</span>/500</span>
            </label>
            <p class="mkt-helper">Masukkan keunggulan acara, target audiens, bonus, urgensi, atau gaya promosi yang diinginkan.</p>
            <textarea id="mkt-context" maxlength="500" placeholder="Contoh: acara ini untuk pemula, tekankan sisi edukasi dan komunitas."></textarea>
          </div>

          <div class="mkt-field">
            <label for="mkt-invite-link">Link Undangan</label>
            <p class="mkt-helper">Link ini akan otomatis dimasukkan ke CTA hasil caption.</p>
            <div class="mkt-readonly-link">
              <code id="mkt-invite-link"><?= htmlspecialchars($initialInviteLink) ?></code>
              <button type="button" class="mkt-copy-btn" id="mkt-copy-link-btn">Salin Link</button>
            </div>
          </div>

          <p class="mkt-output-note">
            <span>✓</span> Total output: 5 variasi copywriting
          </p>

          <div class="mkt-actions">
            <button type="button" class="mkt-btn mkt-btn-primary" id="mkt-generate-btn">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3v18M3 12h18M5 5l14 14M19 5 5 19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
              Generate 5 Variasi Copywriting
            </button>
            <button type="button" class="mkt-btn mkt-btn-secondary" id="mkt-reset-btn">Reset Form</button>
            <button type="button" class="mkt-btn mkt-btn-secondary" id="mkt-use-event-btn">Gunakan Data Event</button>
          </div>
        </div>
      </div>

      <aside class="mkt-panel mkt-flyer-panel">
        <div class="mkt-card-head">
          <div class="mkt-card-title">
            <span class="mkt-step">2</span>
            <div>
              <h2>Flyer Event</h2>
              <p>Gunakan preview ini sebagai referensi konten promosi.</p>
            </div>
          </div>
        </div>

        <div class="mkt-flyer-body">
          <div class="mkt-flyer-grid">
            <div>
              <?php if ($flyerAsset): ?>
                <div class="mkt-flyer-preview">
                  <img src="<?= htmlspecialchars($flyerAsset['url']) ?>" alt="Flyer promosi <?= htmlspecialchars($event['name']) ?>">
                </div>
                <div class="mkt-flyer-actions">
                  <a class="mkt-btn mkt-btn-secondary" href="<?= htmlspecialchars($flyerAsset['url']) ?>" target="_blank" rel="noopener">Buka Penuh</a>
                  <a class="mkt-btn mkt-btn-primary" href="<?= htmlspecialchars($flyerAsset['url']) ?>" download="<?= htmlspecialchars($flyerAsset['download']) ?>">Unduh Gambar</a>
                  <button type="button" class="mkt-btn mkt-btn-secondary" id="mkt-copy-title-btn">Salin Judul</button>
                </div>
              <?php else: ?>
                <div class="mkt-flyer-empty">
                  <strong>Gambar flyer tidak tersedia.</strong>
                  Tambahkan file flyer format JPG, JPEG, atau PNG ke folder assets event ini agar pengguna bisa mendownloadnya dari halaman generator.
                </div>
              <?php endif; ?>
            </div>

            <div>
              <?php if (!empty($eventMeta)): ?>
                <div class="mkt-info-box">
                  <h3>Info Event</h3>
                  <?php foreach ($eventMeta as $metaLabel => $metaValue): ?>
                    <div class="mkt-info-row">
                      <span><?= htmlspecialchars($metaLabel) ?></span>
                      <strong><?= htmlspecialchars($metaValue) ?></strong>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <div class="mkt-tips">
                <h3>Tips Penggunaan</h3>
                <ul>
                  <li>Gunakan caption pendek untuk WhatsApp status.</li>
                  <li>Gunakan caption persuasif untuk grup atau broadcast.</li>
                  <li>Pilih variasi sesuai karakter audiens.</li>
                  <li>Selalu sertakan link undangan atau link referral.</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </aside>
    </div>

    <section class="mkt-panel mkt-result-panel" id="mkt-result-area" aria-live="polite">
      <div class="mkt-empty">
        <span class="mkt-empty-icon" aria-hidden="true">
          <svg width="25" height="25" viewBox="0 0 24 24" fill="none"><path d="M8 9h8M8 13h5M21 12c0 4.4-4 8-9 8a10 10 0 0 1-4-.8L3 20l1.4-3.7A7.2 7.2 0 0 1 3 12c0-4.4 4-8 9-8s9 3.6 9 8Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
        <h2>Belum ada copywriting yang dihasilkan</h2>
        <p>Isi judul event dan konteks tambahan, lalu generate untuk mendapatkan 5 caption promosi siap pakai.</p>
        <div class="mkt-actions" style="justify-content:center;margin-top:18px;">
          <a class="mkt-btn mkt-btn-primary" href="#mkt-event-title">Mulai Generate</a>
        </div>
      </div>
    </section>

  <?php endif; ?>
</div>
<div class="mkt-toast" id="mkt-toast" role="status" aria-live="polite">Caption berhasil disalin</div>

<?php if (!$eventNotFound): ?>
<script>
(function () {
  const eventSlug   = <?= json_encode($eventSlug) ?>;
  const csrfToken   = <?= json_encode($_SESSION['csrf_token']) ?>;
  const defaultEventTitle = <?= json_encode($event['name']) ?>;
  const defaultContext = <?= json_encode(implode("\n", array_map(
      static fn($label, $value) => $label . ': ' . $value,
      array_keys($eventMeta),
      $eventMeta
  ))) ?>;
  const initialInviteLink = <?= json_encode($initialInviteLink) ?>;
  const challengeLink = <?= json_encode($challengeLink) ?>;

  const titleInput   = document.getElementById('mkt-event-title');
  const titleError   = document.getElementById('mkt-title-error');
  const contextInput = document.getElementById('mkt-context');
  const generateBtn  = document.getElementById('mkt-generate-btn');
  const resetBtn     = document.getElementById('mkt-reset-btn');
  const useEventBtn  = document.getElementById('mkt-use-event-btn');
  const resultArea   = document.getElementById('mkt-result-area');
  const inviteLinkEl = document.getElementById('mkt-invite-link');
  const copyLinkBtn  = document.getElementById('mkt-copy-link-btn');
  const copyTitleBtn = document.getElementById('mkt-copy-title-btn');
  const titleCount   = document.getElementById('mkt-title-count');
  const contextCount = document.getElementById('mkt-context-count');
  const toast        = document.getElementById('mkt-toast');
  const formatTabs   = document.querySelectorAll('[data-format]');
  const ctaTabs      = document.querySelectorAll('[data-cta-target]');

  let currentInviteLink = initialInviteLink;
  let currentFormat = 'whatsapp_broadcast';
  let currentCtaTarget = 'referral';
  let currentVariations = [];
  let toastTimer = null;

  formatTabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      formatTabs.forEach(function (item) {
        item.classList.remove('active');
      });
      tab.classList.add('active');
      currentFormat = tab.dataset.format || 'whatsapp_broadcast';
    });
  });

  function getSelectedLink() {
    return currentCtaTarget === 'challenge' ? challengeLink : initialInviteLink;
  }

  function syncInviteLinkPreview() {
    currentInviteLink = getSelectedLink();
    inviteLinkEl.textContent = currentInviteLink;
  }

  ctaTabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      ctaTabs.forEach(function (item) {
        item.classList.remove('active');
      });
      tab.classList.add('active');
      currentCtaTarget = tab.dataset.ctaTarget || 'referral';
      syncInviteLinkPreview();
    });
  });

  function showToast(message) {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () {
      toast.classList.remove('show');
    }, 1800);
  }

  function updateCounters() {
    if (titleCount) titleCount.textContent = titleInput.value.length;
    if (contextCount) contextCount.textContent = contextInput.value.length;
  }

  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text);
    }
    const el = document.createElement('textarea');
    el.value = text;
    el.style.position = 'fixed';
    el.style.opacity = '0';
    document.body.appendChild(el);
    el.select();
    document.execCommand('copy');
    el.remove();
    return Promise.resolve();
  }

  function renderLoading() {
    resultArea.textContent = '';
    resultArea.className = 'mkt-panel mkt-loading';
    const icon = document.createElement('span');
    icon.className = 'mkt-empty-icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = '...';

    const title = document.createElement('h2');
    title.textContent = 'Sedang menghasilkan caption...';

    const text = document.createElement('p');
    text.textContent = 'AI sedang menyiapkan 5 variasi copywriting berdasarkan judul, konteks, dan data event.';

    resultArea.appendChild(icon);
    resultArea.appendChild(title);
    resultArea.appendChild(text);
  }

  function renderError(message) {
    resultArea.textContent = '';
    resultArea.className = 'mkt-panel mkt-error';
    const title = document.createElement('h2');
    title.textContent = 'Generate gagal';
    const text = document.createElement('p');
    text.textContent = message;
    resultArea.appendChild(title);
    resultArea.appendChild(text);
  }

  function boldHeadline(text) {
    const value = String(text || '').trim();
    if (value === '') return '';
    if (/^\*\*[\s\S]+\*\*$/.test(value)) return value;
    return '**' + value.replace(/^\*+|\*+$/g, '').trim() + '**';
  }

  function hasHumanSymbol(text) {
    return /[✨✅💬🙌🔥⭐💡🚀😊🙂👍]/u.test(String(text || ''));
  }

  function decorateDescription(text) {
    const value = String(text || '').trim();
    if (value === '') return '';
    if (hasHumanSymbol(value)) return value;

    const symbols = ['✨', '✅', '💬', '🙌'];
    return value.split(/\n{2,}/).map(function (paragraph, index) {
      const clean = paragraph.trim();
      if (clean === '') return '';
      return /[.!?…]$/.test(clean)
        ? clean + ' ' + symbols[index % symbols.length]
        : clean + '. ' + symbols[index % symbols.length];
    }).filter(Boolean).join('\n\n');
  }

  function getFullCaption(v, inviteLink, includeLink) {
    const parts = [
      boldHeadline(v.headline),
      v.subheadline,
      decorateDescription(v.description),
      '',
      v.cta_text
    ].filter(function (part) {
      return part !== undefined && part !== null && String(part).trim() !== '';
    });

    if (includeLink) {
      parts.push('', '👉 ' + inviteLink);
    }

    return parts.join('\n\n');
  }

  function getCategory(style) {
    const value = String(style || '').toLowerCase();
    if (value.includes('edukatif') || value.includes('kredibel')) return 'edukatif';
    if (value.includes('santai') || value.includes('relatable')) return 'santai';
    return 'persuasif';
  }

  function getFitLabel(length) {
    if (length <= 280) return 'Cocok untuk status';
    if (length <= 450) return 'Cocok untuk broadcast';
    return 'Cocok untuk grup';
  }

  function createText(tag, className, text) {
    const el = document.createElement(tag);
    if (className) el.className = className;
    el.textContent = text;
    return el;
  }

  function renderResultHeader() {
    const head = document.createElement('div');
    head.className = 'mkt-results-head';

    const titleWrap = document.createElement('div');
    const titleRow = document.createElement('div');
    titleRow.className = 'mkt-card-title';
    titleRow.appendChild(createText('span', 'mkt-step', '3'));

    const copy = document.createElement('div');
    copy.appendChild(createText('h2', '', '5 Variasi Copywriting'));
    copy.appendChild(createText('p', 'mkt-meta', 'Pilih gaya promosi yang paling cocok, lalu salin untuk digunakan.'));
    titleRow.appendChild(copy);
    titleWrap.appendChild(titleRow);

    const tools = document.createElement('div');
    tools.className = 'mkt-result-tools';
    [
      ['all', 'Semua'],
      ['persuasif', 'Persuasif'],
      ['edukatif', 'Edukatif'],
      ['santai', 'Santai']
    ].forEach(function (item, index) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'mkt-chip' + (index === 0 ? ' active' : '');
      btn.dataset.filter = item[0];
      btn.textContent = item[1];
      tools.appendChild(btn);
    });

    const regenerateBtn = document.createElement('button');
    regenerateBtn.type = 'button';
    regenerateBtn.className = 'mkt-chip';
    regenerateBtn.textContent = 'Generate Ulang Semua';
    regenerateBtn.addEventListener('click', function () {
      generateBtn.click();
    });
    tools.appendChild(regenerateBtn);

    const copyAllBtn = document.createElement('button');
    copyAllBtn.type = 'button';
    copyAllBtn.className = 'mkt-chip';
    copyAllBtn.textContent = 'Salin Semua Variasi';
    copyAllBtn.addEventListener('click', function () {
      const combined = currentVariations.map(function (v, i) {
        return '=== Variasi ' + (i + 1) + ': ' + v.style + ' ===\n' + getFullCaption(v, currentInviteLink, true);
      }).join('\n\n---------------------\n\n');

      copyText(combined).then(function () {
        copyAllBtn.textContent = 'Semua Tersalin!';
        showToast('Semua variasi berhasil disalin');
        setTimeout(function () { copyAllBtn.textContent = 'Salin Semua Variasi'; }, 1800);
      });
    });
    tools.appendChild(copyAllBtn);

    head.appendChild(titleWrap);
    head.appendChild(tools);
    return head;
  }

  function renderWhatsAppPreview(captionText) {
    const wrap = document.createElement('div');
    wrap.className = 'wa-preview-wrap';

    const chat = document.createElement('div');
    chat.className = 'wa-chat-bg';

    const bubble = document.createElement('div');
    bubble.className = 'wa-bubble';

    const text = document.createElement('span');
    text.textContent = captionText;
    bubble.appendChild(text);

    const time = document.createElement('span');
    time.className = 'wa-time';
    time.appendChild(document.createTextNode('10:24 '));
    const checks = document.createElement('span');
    checks.className = 'wa-checks';
    checks.textContent = '✓✓';
    time.appendChild(checks);
    bubble.appendChild(time);

    chat.appendChild(bubble);
    wrap.appendChild(chat);
    return wrap;
  }

  function renderCopyCard(v, index, inviteLink) {
    const card = document.createElement('article');
    card.className = 'mkt-copy-card';
    card.dataset.category = getCategory(v.style);
    card.dataset.index = String(index);

    const top = document.createElement('div');
    top.className = 'mkt-card-top';

    const labels = document.createElement('div');
    labels.className = 'mkt-card-labels';
    labels.appendChild(createText('span', 'mkt-card-index', String(index + 1)));

    const badge = document.createElement('span');
    badge.className = 'mkt-style-badge';
    badge.textContent = v.style;
    labels.appendChild(badge);
    top.appendChild(labels);

    const regenBtn = document.createElement('button');
    regenBtn.type = 'button';
    regenBtn.className = 'mkt-copy-btn';
    regenBtn.textContent = '↻ Generate Ulang';
    regenBtn.addEventListener('click', function () {
      regenerateStyle(index, v.style, regenBtn, card);
    });
    top.appendChild(regenBtn);
    card.appendChild(top);

    const h3 = document.createElement('h3');
    h3.textContent = v.headline;
    card.appendChild(h3);

    if (v.subheadline) {
      const sub = document.createElement('p');
      sub.className = 'sub';
      sub.textContent = v.subheadline;
      card.appendChild(sub);
    }

    if (v.description) {
      const desc = document.createElement('p');
      desc.className = 'desc';
      desc.textContent = decorateDescription(v.description);
      card.appendChild(desc);
    }

    const cta = document.createElement('p');
    cta.className = 'cta';
    cta.textContent = v.cta_text;
    card.appendChild(cta);

    const footer = document.createElement('div');
    footer.className = 'mkt-card-footer';

    const fullCaption = getFullCaption(v, inviteLink, true);
    const shortCaption = getFullCaption(v, inviteLink, false);
    const charRow = document.createElement('div');
    charRow.className = 'mkt-char-row';
    charRow.appendChild(createText('span', '', fullCaption.length + ' karakter'));
    charRow.appendChild(createText('span', 'mkt-fit-pill', getFitLabel(fullCaption.length)));
    footer.appendChild(charRow);

    const actions = document.createElement('div');
    actions.className = 'mkt-card-actions';

    const copyCaptionBtn = document.createElement('button');
    copyCaptionBtn.type = 'button';
    copyCaptionBtn.className = 'mkt-copy-btn';
    copyCaptionBtn.textContent = 'Salin Caption';
    copyCaptionBtn.addEventListener('click', function () {
      copyText(shortCaption).then(function () {
        copyCaptionBtn.textContent = 'Tersalin!';
        copyCaptionBtn.classList.add('copied');
        showToast('Caption berhasil disalin');
        setTimeout(function () {
          copyCaptionBtn.textContent = 'Salin Caption';
          copyCaptionBtn.classList.remove('copied');
        }, 1600);
      });
    });

    const copyBtn = document.createElement('button');
    copyBtn.type = 'button';
    copyBtn.className = 'mkt-copy-btn primary';
    copyBtn.textContent = 'Salin Konten + Link';
    copyBtn.addEventListener('click', function () {
      copyText(fullCaption).then(function () {
        copyBtn.textContent = 'Tersalin!';
        copyBtn.classList.add('copied');
        showToast('Caption + link berhasil disalin');
        setTimeout(function () {
          copyBtn.textContent = 'Salin Konten + Link';
          copyBtn.classList.remove('copied');
        }, 1600);
      });
    });

    const previewBtn = document.createElement('button');
    previewBtn.type = 'button';
    previewBtn.className = 'mkt-copy-btn';
    previewBtn.textContent = 'Lihat Preview WhatsApp';

    actions.appendChild(copyCaptionBtn);
    actions.appendChild(copyBtn);
    actions.appendChild(previewBtn);
    footer.appendChild(actions);
    card.appendChild(footer);

    const waPreview = renderWhatsAppPreview(fullCaption);
    card.appendChild(waPreview);
    previewBtn.addEventListener('click', function () {
      const isOpen = waPreview.classList.toggle('open');
      previewBtn.textContent = isOpen ? 'Sembunyikan Preview' : 'Lihat Preview WhatsApp';
    });

    return card;
  }

  function renderVariations(variations, inviteLink) {
    currentInviteLink = inviteLink;
    currentVariations = variations;
    inviteLinkEl.textContent = inviteLink;

    resultArea.textContent = '';
    resultArea.className = 'mkt-panel mkt-result-panel';
    resultArea.appendChild(renderResultHeader());

    const grid = document.createElement('div');
    grid.className = 'mkt-grid';

    variations.forEach(function (v, index) {
      grid.appendChild(renderCopyCard(v, index, inviteLink));
    });

    resultArea.appendChild(grid);

    resultArea.querySelectorAll('.mkt-chip[data-filter]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const filter = btn.dataset.filter;
        resultArea.querySelectorAll('.mkt-chip[data-filter]').forEach(function (item) {
          item.classList.toggle('active', item === btn);
        });
        grid.querySelectorAll('.mkt-copy-card').forEach(function (card) {
          card.style.display = filter === 'all' || card.dataset.category === filter ? '' : 'none';
        });
      });
    });
  }

  async function regenerateStyle(index, styleName, btn, cardEl) {
    const eventTitle = titleInput.value.trim();
    if (!eventTitle) {
      titleError.style.display = 'block';
      titleInput.focus();
      return;
    }

    btn.disabled = true;
    btn.textContent = 'Memproses...';

    try {
      const res = await fetch('../api/regenerate_style_caption.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          event: eventSlug,
          event_title: eventTitle,
          context: contextInput.value.trim(),
          format: currentFormat,
          cta_target: currentCtaTarget,
          style: styleName,
          csrf_token: csrfToken,
        }),
      });
      const result = await res.json();

      if (result.success) {
        currentInviteLink = result.invite_link || currentInviteLink;
        inviteLinkEl.textContent = currentInviteLink;
        currentVariations[index] = result.variation;
        const newCard = renderCopyCard(result.variation, index, currentInviteLink);
        cardEl.replaceWith(newCard);
        showToast('Satu variasi berhasil digenerate ulang');
      } else {
        showToast(result.message || 'Gagal generate ulang. Coba lagi.');
        btn.disabled = false;
        btn.textContent = '↻ Generate Ulang';
      }
    } catch (err) {
      showToast('Gagal terhubung ke server.');
      btn.disabled = false;
      btn.textContent = '↻ Generate Ulang';
    }
  }

  generateBtn.addEventListener('click', async function () {
    const eventTitle = titleInput.value.trim();
    if (!eventTitle) {
      titleError.style.display = 'block';
      titleInput.focus();
      return;
    }
    titleError.style.display = 'none';

    generateBtn.disabled = true;
    generateBtn.textContent = 'Sedang menghasilkan caption...';
    renderLoading();

    try {
      const res = await fetch('../api/generate_marketing_content.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          event: eventSlug,
          event_title: eventTitle,
          context: contextInput.value.trim(),
          format: currentFormat,
          cta_target: currentCtaTarget,
          csrf_token: csrfToken,
        }),
      });
      const result = await res.json();

      if (result.success) {
        currentFormat = result.format || currentFormat;
        currentCtaTarget = result.cta_target || currentCtaTarget;
        renderVariations(result.variations, result.invite_link);
      } else {
        renderError(result.message || 'Gagal generate konten. Coba lagi.');
      }
    } catch (err) {
      renderError('Gagal terhubung ke server. Periksa koneksi dan coba lagi.');
    } finally {
      generateBtn.disabled = false;
      generateBtn.textContent = 'Generate 5 Variasi Copywriting';
    }
  });

  copyLinkBtn.addEventListener('click', function () {
    if (!currentInviteLink) return;
    copyText(currentInviteLink).then(function () {
      copyLinkBtn.textContent = 'Tersalin!';
      copyLinkBtn.classList.add('copied');
      showToast('Link undangan berhasil disalin');
      setTimeout(function () { copyLinkBtn.textContent = 'Salin Link'; }, 1500);
      setTimeout(function () { copyLinkBtn.classList.remove('copied'); }, 1500);
    });
  });

  if (copyTitleBtn) {
    copyTitleBtn.addEventListener('click', function () {
      copyText(titleInput.value.trim() || defaultEventTitle).then(function () {
        copyTitleBtn.textContent = 'Judul Tersalin!';
        showToast('Judul event berhasil disalin');
        setTimeout(function () { copyTitleBtn.textContent = 'Salin Judul'; }, 1500);
      });
    });
  }

  resetBtn.addEventListener('click', function () {
    titleInput.value = '';
    contextInput.value = '';
    titleError.style.display = 'none';
    updateCounters();
    titleInput.focus();
  });

  useEventBtn.addEventListener('click', function () {
    titleInput.value = defaultEventTitle;
    contextInput.value = defaultContext;
    titleError.style.display = 'none';
    updateCounters();
  });

  titleInput.addEventListener('input', updateCounters);
  contextInput.addEventListener('input', updateCounters);
  updateCounters();
  syncInviteLinkPreview();
})();
</script>
<?php endif; ?>
</body>
</html>
