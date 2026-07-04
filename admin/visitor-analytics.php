<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
start_secure_session();

$brand = require_admin_for_brand(get_current_brand());
$brandId = (int)$brand['id'];

$pdo = get_db();

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function traffic_source_label(?string $utmSource, ?string $referrerUrl): string
{
    $utmSource = trim((string)$utmSource);
    if ($utmSource !== '') {
        return $utmSource;
    }
    $referrerUrl = trim((string)$referrerUrl);
    if ($referrerUrl === '') {
        return 'Direct/Unknown';
    }
    $host = parse_url($referrerUrl, PHP_URL_HOST);
    if (!$host) {
        return 'Direct/Unknown';
    }
    return preg_replace('/^www\./', '', $host);
}

function pct(float $part, float $total, int $decimals = 1): float
{
    return $total > 0 ? round(($part / $total) * 100, $decimals) : 0;
}

function format_pct(float $value, int $decimals = 1): string
{
    return number_format($value, $decimals, ',', '.') . '%';
}

$allowedRanges = [7 => '7 Hari Terakhir', 14 => '14 Hari Terakhir', 30 => '30 Hari Terakhir', 90 => '90 Hari Terakhir'];
$rangeDays = (int)($_GET['range'] ?? 30);
if (!isset($allowedRanges[$rangeDays])) {
    $rangeDays = 30;
}

$stmt = $pdo->prepare('SELECT slug, name FROM events WHERE brand_id = ? ORDER BY (slug = ?) DESC, created_at DESC');
$stmt->execute([$brandId, $brand['default_event_slug'] ?? '']);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

$eventSlugs = array_column($events, 'slug');
$selectedEvent = trim((string)($_GET['event'] ?? ''));
if ($selectedEvent !== '' && !in_array($selectedEvent, $eventSlugs, true)) {
    $selectedEvent = '';
}

$rangeSql = (string)$rangeDays;
$where = "brand_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL {$rangeSql} DAY)";
$whereVe = "ve.brand_id = ? AND ve.created_at >= DATE_SUB(NOW(), INTERVAL {$rangeSql} DAY)";
$params = [$brandId];
if ($selectedEvent !== '') {
    $where .= ' AND event_slug = ?';
    $whereVe .= ' AND ve.event_slug = ?';
    $params[] = $selectedEvent;
}

$filterQuery = http_build_query(array_filter([
    'range' => $rangeDays,
    'event' => $selectedEvent !== '' ? $selectedEvent : null,
], static fn ($value) => $value !== null && $value !== ''));

// ==================== KPI ====================
$stmt = $pdo->prepare("
    SELECT
        SUM(event_type = 'pageview') AS total_pageview,
        COUNT(DISTINCT session_id) AS unique_sessions,
        SUM(event_type = 'form_start') AS form_starts,
        SUM(event_type = 'form_submit') AS form_submits
    FROM visitor_events
    WHERE {$where}
");
$stmt->execute($params);
$kpi = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$totalPageview = (int)($kpi['total_pageview'] ?? 0);
$uniqueSessions = (int)($kpi['unique_sessions'] ?? 0);
$formStarts = (int)($kpi['form_starts'] ?? 0);
$formSubmits = (int)($kpi['form_submits'] ?? 0);
$conversionRate = $totalPageview > 0 ? ($formSubmits / $totalPageview) * 100 : 0;

// ==================== FUNNEL ====================
$stmt = $pdo->prepare("
    SELECT event_type, COUNT(*) AS total
    FROM visitor_events
    WHERE {$where} AND event_type IN ('pageview','scroll_50','form_start','form_submit')
    GROUP BY event_type
");
$stmt->execute($params);
$funnelRaw = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $funnelRaw[$row['event_type']] = (int)$row['total'];
}
$funnelStages = [
    ['key' => 'pageview', 'label' => 'Pageview'],
    ['key' => 'scroll_50', 'label' => 'Scroll 50%'],
    ['key' => 'form_start', 'label' => 'Form Dimulai'],
    ['key' => 'form_submit', 'label' => 'Form Submit'],
];
$funnelMax = max(1, $funnelRaw['pageview'] ?? 0);
$largestDrop = null;
foreach ($funnelStages as $i => &$stage) {
    $stage['total'] = $funnelRaw[$stage['key']] ?? 0;
    $stage['pct'] = pct($stage['total'], $funnelMax, 2);
    $prevTotal = $i > 0 ? $funnelStages[$i - 1]['total'] : null;
    $stage['prev_conversion'] = $prevTotal !== null ? pct($stage['total'], $prevTotal, 1) : 100;
    $stage['drop'] = $prevTotal !== null ? max(0, $prevTotal - $stage['total']) : null;
    $stage['drop_pct'] = $prevTotal !== null ? pct($stage['drop'], $prevTotal, 1) : null;
    if ($stage['drop'] !== null && $stage['drop'] > 0 && ($largestDrop === null || $stage['drop_pct'] > $largestDrop['drop_pct'])) {
        $largestDrop = $stage;
    }
}
unset($stage);

// ==================== SUMBER TRAFIK ====================
$stmt = $pdo->prepare("
    SELECT utm_source, referrer_url, COUNT(*) AS total
    FROM visitor_events
    WHERE {$where} AND event_type = 'pageview'
    GROUP BY utm_source, referrer_url
");
$stmt->execute($params);
$sources = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $label = traffic_source_label($row['utm_source'], $row['referrer_url']);
    $sources[$label] = ($sources[$label] ?? 0) + (int)$row['total'];
}
arsort($sources);
$sources = array_slice($sources, 0, 10, true);
$sourcesTotal = array_sum($sources);
$sourcesMax = max(1, $sourcesTotal);

// ==================== BREAKDOWN DEVICE ====================
$stmt = $pdo->prepare("
    SELECT device_type, COUNT(*) AS total
    FROM visitor_events
    WHERE {$where} AND event_type = 'pageview'
    GROUP BY device_type
");
$stmt->execute($params);
$deviceRaw = ['mobile' => 0, 'tablet' => 0, 'desktop' => 0];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (isset($deviceRaw[$row['device_type']])) {
        $deviceRaw[$row['device_type']] = (int)$row['total'];
    }
}
$deviceTotal = array_sum($deviceRaw);
$deviceLabels = ['mobile' => 'Mobile', 'tablet' => 'Tablet', 'desktop' => 'Desktop'];

// ==================== TOP HALAMAN MASUK (BARU) ====================
$stmt = $pdo->prepare("
    SELECT page_path, COUNT(DISTINCT session_id) AS total
    FROM visitor_events
    WHERE {$where} AND event_type = 'pageview'
    GROUP BY page_path ORDER BY total DESC LIMIT 8
");
$stmt->execute($params);
$topPages = $stmt->fetchAll(PDO::FETCH_ASSOC);
$topPagesTotal = max(1, array_sum(array_column($topPages, 'total')));

// ==================== PERFORMA PER PENGUNDANG / REFERRAL (BARU) ====================
$stmt = $pdo->prepare("
    SELECT
        ve.ref_code,
        COALESCE(r.name, ve.ref_code) AS referrer_name,
        COUNT(DISTINCT CASE WHEN ve.event_type = 'pageview' THEN ve.session_id END) AS visits,
        SUM(ve.event_type = 'form_submit') AS submits
    FROM visitor_events ve
    LEFT JOIN referrers r ON r.brand_id = ve.brand_id AND r.event_slug = ve.event_slug AND r.ref_code = ve.ref_code
    WHERE {$whereVe} AND ve.ref_code IS NOT NULL AND ve.ref_code != ''
    GROUP BY ve.ref_code, referrer_name
    ORDER BY visits DESC LIMIT 12
");
$stmt->execute($params);
$referrerPerf = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($referrerPerf as &$rp) {
    $rp['visits'] = (int)$rp['visits'];
    $rp['submits'] = (int)$rp['submits'];
    $rp['conv_rate'] = $rp['visits'] > 0 ? pct($rp['submits'], $rp['visits'], 1) : 0;
}
unset($rp);

// ==================== GRAFIK HARIAN (ikut filter periode yang dipilih) ====================
$chartWhere = 'brand_id = ? AND event_type = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ' . $rangeSql . ' DAY)';
$chartParams = [$brandId, 'pageview'];
if ($selectedEvent !== '') {
    $chartWhere .= ' AND event_slug = ?';
    $chartParams[] = $selectedEvent;
}
$stmt = $pdo->prepare("
    SELECT DATE(created_at) AS tanggal, COUNT(*) AS total
    FROM visitor_events
    WHERE {$chartWhere}
    GROUP BY tanggal ORDER BY tanggal ASC
");
$stmt->execute($chartParams);
$dailyRaw = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $dailyRaw[$row['tanggal']] = (int)$row['total'];
}
$dailySeries = [];
for ($i = $rangeDays - 1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $dailySeries[] = ['date' => $date, 'total' => $dailyRaw[$date] ?? 0];
}
$dailyTotal = array_sum(array_map(static fn ($d) => $d['total'], $dailySeries));
$dailyMax = max(1, ...array_map(static fn ($d) => $d['total'], $dailySeries));

$chartW = 700;
$chartH = 260;
$chartPadX = 26;
$chartPadY = 24;
$plotH = $chartH - (2 * $chartPadY);
$points = [];
$areaPoints = [];
$count = count($dailySeries);
foreach ($dailySeries as $i => $d) {
    $x = $count > 1 ? ($chartPadX + ($i / ($count - 1)) * ($chartW - 2 * $chartPadX)) : $chartW / 2;
    $y = $chartH - $chartPadY - (($d['total'] / $dailyMax) * $plotH);
    $points[] = round($x, 1) . ',' . round($y, 1);
    $areaPoints[] = [round($x, 1), round($y, 1)];
}
$polylinePoints = implode(' ', $points);
$areaPath = '';
if (!empty($areaPoints)) {
    $first = $areaPoints[0];
    $last = $areaPoints[count($areaPoints) - 1];
    $areaPath = 'M ' . $first[0] . ' ' . ($chartH - $chartPadY) . ' L ' . implode(' L ', array_map(static fn ($p) => $p[0] . ' ' . $p[1], $areaPoints)) . ' L ' . $last[0] . ' ' . ($chartH - $chartPadY) . ' Z';
}

$allAnalyticsEmpty = $totalPageview === 0 && $uniqueSessions === 0 && $formStarts === 0 && $formSubmits === 0;

$insights = [];
if ($totalPageview === 0) {
    $insights[] = ['tone' => 'warning', 'title' => 'Belum ada data', 'text' => 'Mulai sebarkan link event untuk mengisi analytics.'];
} elseif ($conversionRate <= 0) {
    $insights[] = ['tone' => 'danger', 'title' => 'Belum ada submit', 'text' => 'Periksa CTA, form, dan kecepatan halaman.'];
} else {
    $insights[] = ['tone' => 'success', 'title' => 'Conversion rate', 'text' => format_pct($conversionRate) . ' dari pageview menjadi submit form.'];
}
if ($deviceTotal > 0 && $deviceRaw['mobile'] > $deviceRaw['tablet'] && $deviceRaw['mobile'] > $deviceRaw['desktop']) {
    $insights[] = ['tone' => 'gold', 'title' => 'Mobile dominan', 'text' => 'Mayoritas pengunjung dari mobile. Pastikan landing page optimal.'];
}
if (empty($sources)) {
    $insights[] = ['tone' => 'warning', 'title' => 'UTM belum terbaca', 'text' => 'Gunakan UTM agar sumber kampanye terbaca lebih jelas.'];
}
if ($largestDrop !== null && $funnelRaw['pageview'] > 0) {
    $insights[] = ['tone' => 'danger', 'title' => 'Drop-off terbesar', 'text' => 'Terjadi di tahap ' . $largestDrop['label'] . ' sebesar ' . format_pct($largestDrop['drop_pct']) . '.'];
}
if (!empty($referrerPerf) && $referrerPerf[0]['visits'] >= 10) {
    $insights[] = ['tone' => 'gold', 'title' => 'Pengundang teraktif', 'text' => $referrerPerf[0]['referrer_name'] . ' membawa ' . $referrerPerf[0]['visits'] . ' kunjungan dengan conversion ' . format_pct($referrerPerf[0]['conv_rate']) . '.'];
}

$logoPath = $brand['logo_path'] ? '..' . $brand['logo_path'] : '../assets/logo.png';
$landingHref = $selectedEvent !== '' ? '../e/' . rawurlencode($selectedEvent) . '/' : '../';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Analitik Pengunjung — <?= h($brand['name']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
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
      radial-gradient(circle at 88% 4%, color-mix(in srgb, var(--gold) 23%, transparent), transparent 29vw),
      radial-gradient(circle at 8% 92%, color-mix(in srgb, var(--gold) 12%, transparent), transparent 34vw),
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
  .topbar-inner, .wrap {
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
  .nav a, .btn, .select-field, .panel, .kpi-card {
    transition: transform 180ms ease, border-color 180ms ease, background 180ms ease, color 180ms ease, box-shadow 180ms ease;
  }
  .nav a {
    color: var(--muted);
    display: inline-flex;
    align-items: center;
    min-height: 42px;
    padding: 10px 15px;
    border-radius: 999px;
    font-size: 13.5px;
    font-weight: 700;
    text-decoration: none;
    border: 1px solid transparent;
  }
  .nav a:hover { color: var(--text); background: rgba(255,255,255,0.04); }
  .nav a.active {
    color: var(--gold-soft);
    background: color-mix(in srgb, var(--gold) 10%, transparent);
    border-color: var(--border-gold);
    box-shadow: inset 0 -2px 0 color-mix(in srgb, var(--gold-soft) 45%, transparent);
  }
  .nav a.logout { color: var(--text); background: rgba(255,255,255,0.035); border-color: rgba(255,255,255,0.10); }
  .wrap { position: relative; z-index: 1; padding-top: 28px; padding-bottom: 48px; }
  .hero {
    position: relative;
    overflow: hidden;
    min-height: 180px;
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(320px, 392px);
    align-items: center;
    gap: 28px;
    background:
      radial-gradient(circle at 78% 28%, color-mix(in srgb, var(--gold-soft) 22%, transparent), transparent 24%),
      linear-gradient(135deg, rgba(32,32,30,0.96), rgba(23,23,22,0.93) 58%, color-mix(in srgb, var(--gold) 18%, transparent));
    border: 1px solid var(--border-gold);
    border-radius: 28px;
    box-shadow: var(--shadow);
    margin-bottom: 18px;
    padding: 30px 34px;
  }
  .hero::after {
    content: "";
    position: absolute;
    right: 33%;
    top: 28px;
    width: 220px;
    height: 128px;
    opacity: .52;
    background:
      linear-gradient(135deg, transparent 46%, color-mix(in srgb, var(--gold-soft) 65%, transparent) 47%, transparent 49%),
      linear-gradient(90deg, transparent 14%, color-mix(in srgb, var(--gold) 52%, transparent) 15% 16%, transparent 17% 34%, color-mix(in srgb, var(--gold) 46%, transparent) 35% 36%, transparent 37% 55%, color-mix(in srgb, var(--gold) 52%, transparent) 56% 57%, transparent 58%);
    mask-image: linear-gradient(90deg, transparent, black 20%, black 80%, transparent);
  }
  .hero-copy, .hero-actions { position: relative; z-index: 1; }
  .breadcrumb { display: flex; align-items: center; gap: 9px; color: var(--muted); font-size: 13px; margin-bottom: 14px; }
  .breadcrumb a { color: var(--gold-soft); text-decoration: none; font-weight: 800; }
  .eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    width: fit-content;
    color: var(--gold-soft);
    background: color-mix(in srgb, var(--gold) 12%, transparent);
    border: 1px solid var(--border-gold);
    border-radius: 999px;
    font-size: 11px;
    font-weight: 900;
    letter-spacing: .05em;
    margin-bottom: 10px;
    padding: 7px 10px;
    text-transform: uppercase;
  }
  h1 {
    color: var(--text);
    font-family: "Playfair Display", Georgia, serif;
    font-size: clamp(34px, 4.6vw, 54px);
    line-height: 1.04;
    letter-spacing: 0;
    margin-bottom: 10px;
  }
  h1 span { color: var(--gold); }
  .subtitle { color: var(--muted); max-width: 730px; font-size: 15px; line-height: 1.7; }
  .filter-form { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
  .field { display: grid; gap: 7px; }
  .field label { color: var(--muted); font-size: 12px; font-weight: 800; }
  .select-field {
    width: 100%;
    min-height: 46px;
    color: var(--text);
    background: rgba(255,255,255,0.045);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 12px;
    font: inherit;
    font-size: 13.5px;
    outline: none;
    padding: 0 13px;
  }
  .select-field option {
    color: #111;
    background: #fff;
  }
  .select-field:focus { border-color: color-mix(in srgb, var(--gold-soft) 42%, transparent); box-shadow: 0 0 0 4px color-mix(in srgb, var(--gold) 10%, transparent); }
  .filter-buttons { display: grid; grid-column: 1 / -1; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 4px; }
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
    font-weight: 900;
    padding: 12px 16px;
    text-decoration: none;
    white-space: nowrap;
  }
  .btn:hover { transform: translateY(-1px); }
  .btn-primary { color: #111; background: linear-gradient(135deg, var(--gold), var(--gold-soft)); box-shadow: 0 12px 26px color-mix(in srgb, var(--gold) 24%, transparent); }
  .btn-secondary { color: var(--text); background: rgba(255,255,255,0.04); border-color: color-mix(in srgb, var(--gold) 22%, transparent); }
  .filter-date { grid-column: 1 / -1; color: var(--muted); display: flex; justify-content: flex-end; gap: 8px; font-size: 12px; margin-top: 4px; }
  .kpi-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 14px; margin-bottom: 18px; }
  .kpi-card {
    position: relative;
    overflow: hidden;
    display: grid;
    gap: 13px;
    min-height: 132px;
    padding: 18px;
    border: 1px solid var(--border-gold);
    border-radius: 22px;
    background: linear-gradient(145deg, rgba(32,32,30,0.94), rgba(23,23,22,0.94));
    box-shadow: 0 18px 50px rgba(0,0,0,0.22);
  }
  .kpi-card::after {
    content: "";
    position: absolute;
    right: -30px;
    top: -52px;
    width: 110px;
    height: 110px;
    border-radius: 50%;
    background: color-mix(in srgb, var(--gold) 12%, transparent);
  }
  .kpi-card:hover { border-color: color-mix(in srgb, var(--gold-soft) 34%, transparent); transform: translateY(-2px); }
  .kpi-top { position: relative; z-index: 1; display: flex; align-items: center; gap: 12px; }
  .icon-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 42px;
    width: 42px;
    height: 42px;
    color: var(--gold-soft);
    background: color-mix(in srgb, var(--gold) 12%, transparent);
    border: 1px solid color-mix(in srgb, var(--gold-soft) 28%, transparent);
    border-radius: 14px;
    box-shadow: inset 0 0 22px color-mix(in srgb, var(--gold-soft) 8%, transparent);
  }
  .kpi-label { color: var(--muted); font-size: 11px; font-weight: 900; letter-spacing: .08em; text-transform: uppercase; }
  .kpi-value { position: relative; z-index: 1; color: var(--text); font-size: clamp(28px, 3.2vw, 36px); font-weight: 900; line-height: 1; }
  .kpi-copy { position: relative; z-index: 1; color: var(--muted); font-size: 12px; line-height: 1.45; }
  .kpi-copy.empty { color: var(--warning); }
  .global-empty {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 18px;
    border: 1px dashed color-mix(in srgb, var(--gold-soft) 28%, transparent);
    border-radius: 20px;
    background: color-mix(in srgb, var(--gold) 7%, transparent);
    margin-bottom: 18px;
    padding: 18px;
  }
  .global-empty h2 { color: var(--text); font-size: 17px; font-weight: 900; margin-bottom: 5px; }
  .global-empty p { color: var(--muted); font-size: 13px; line-height: 1.6; }
  .global-empty-actions { display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }
  .main-grid { display: grid; grid-template-columns: minmax(0, 1.85fr) minmax(320px, 1fr); gap: 18px; align-items: start; }
  .left-stack, .right-stack { display: grid; gap: 18px; }
  .panel {
    overflow: hidden;
    border: 1px solid var(--border-gold);
    border-radius: 24px;
    background: linear-gradient(145deg, rgba(32,32,30,0.93), rgba(23,23,22,0.94));
    box-shadow: 0 18px 60px rgba(0,0,0,0.24);
  }
  .panel:hover { border-color: color-mix(in srgb, var(--gold-soft) 26%, transparent); }
  .panel-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 22px 24px 12px;
  }
  .panel-title { display: flex; align-items: flex-start; gap: 12px; min-width: 0; }
  .panel-title .icon-badge { width: 36px; height: 36px; flex-basis: 36px; border-radius: 12px; }
  h2 { color: var(--text); font-size: 18px; font-weight: 900; line-height: 1.25; }
  .desc { color: var(--muted); font-size: 13px; line-height: 1.55; margin-top: 5px; }
  .panel-metric { color: var(--muted); font-size: 12px; white-space: nowrap; }
  .panel-metric strong { color: var(--gold-soft); font-size: 18px; }
  .panel-body { padding: 14px 24px 24px; }
  .empty-state {
    display: grid;
    place-items: center;
    gap: 8px;
    min-height: 156px;
    border: 1px dashed color-mix(in srgb, var(--gold-soft) 24%, transparent);
    border-radius: 18px;
    background: rgba(255,255,255,0.03);
    color: var(--muted);
    padding: 26px 18px;
    text-align: center;
  }
  .empty-state strong { color: var(--text); font-size: 15px; }
  .empty-state span { max-width: 360px; font-size: 13px; line-height: 1.6; }
  .chart-wrap { position: relative; width: 100%; min-height: 260px; }
  .chart-wrap svg { width: 100%; height: 260px; display: block; overflow: visible; }
  .chart-grid { stroke: rgba(255,255,255,0.075); stroke-width: 1; }
  .chart-area { fill: url(#chartGradient); }
  .chart-line { fill: none; stroke: var(--gold); stroke-width: 3; stroke-linejoin: round; stroke-linecap: round; filter: drop-shadow(0 0 8px color-mix(in srgb, var(--gold) 38%, transparent)); }
  .chart-dot { fill: var(--gold-soft); stroke: var(--surface); stroke-width: 3; }
  .chart-tooltip {
    position: absolute; display: none; background: var(--surface-elevated);
    border: 1px solid var(--border-gold); border-radius: 10px; padding: 8px 12px;
    font-size: 12px; color: var(--text); pointer-events: none; z-index: 6;
    box-shadow: 0 10px 26px rgba(0,0,0,0.35); white-space: nowrap;
  }
  .chart-tooltip .tt-date { color: var(--muted); margin-bottom: 3px; }
  .chart-tooltip .tt-val { color: var(--gold-soft); font-weight: 800; }
  .chart-axis-labels { display: flex; justify-content: space-between; color: var(--muted); font-size: 11.5px; margin-top: 8px; }
  .legend { display: flex; align-items: center; gap: 8px; color: var(--muted); font-size: 12px; margin-top: 10px; }
  .legend-dot { width: 9px; height: 9px; border-radius: 50%; background: linear-gradient(135deg, var(--gold), var(--gold-soft)); }
  .funnel-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
  .funnel-step {
    position: relative;
    overflow: hidden;
    min-height: 152px;
    border: 1px solid rgba(255,255,255,0.09);
    border-radius: 18px;
    background: rgba(8,8,7,0.32);
    padding: 17px;
  }
  .funnel-step::after {
    content: "";
    position: absolute;
    inset: auto -30px -42px auto;
    width: 110px;
    height: 110px;
    border-radius: 50%;
    background: color-mix(in srgb, var(--gold) 9%, transparent);
  }
  .funnel-name { position: relative; z-index: 1; color: var(--muted); font-size: 12px; font-weight: 800; margin-bottom: 9px; }
  .funnel-total { position: relative; z-index: 1; color: var(--text); font-size: 28px; font-weight: 900; line-height: 1; margin-bottom: 13px; }
  .bar-track { position: relative; z-index: 1; height: 9px; border-radius: 999px; background: rgba(255,255,255,0.08); overflow: hidden; }
  .bar-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, var(--gold), var(--gold-soft)); }
  .funnel-meta { position: relative; z-index: 1; display: grid; gap: 4px; color: var(--muted); font-size: 11.5px; line-height: 1.45; margin-top: 12px; }
  .funnel-drop { color: #FCA5A5; }
  .funnel-insight {
    display: flex;
    gap: 11px;
    align-items: flex-start;
    color: var(--gold-soft);
    background: color-mix(in srgb, var(--gold) 7%, transparent);
    border: 1px solid color-mix(in srgb, var(--gold-soft) 22%, transparent);
    border-radius: 16px;
    font-size: 13px;
    line-height: 1.6;
    margin-top: 14px;
    padding: 14px;
  }
  .source-list, .device-list { display: grid; gap: 14px; }
  .source-row, .device-row { display: grid; gap: 8px; }
  .source-top, .device-top { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; align-items: center; gap: 12px; }
  .source-top strong, .device-top strong { color: var(--text); font-size: 13.5px; font-weight: 800; overflow-wrap: anywhere; }
  .source-top span, .device-top span { color: var(--gold-soft); font-size: 13px; font-weight: 900; }
  .source-top em, .device-top em { color: var(--muted); font-size: 12px; font-style: normal; min-width: 44px; text-align: right; }
  .device-name { display: inline-flex; align-items: center; gap: 8px; }
  .device-icon { color: var(--gold-soft); display: inline-flex; }
  .insight-grid { display: grid; gap: 12px; }
  .insight-item {
    display: grid;
    grid-template-columns: 38px minmax(0, 1fr);
    gap: 12px;
    align-items: start;
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 16px;
    background: rgba(255,255,255,0.032);
    padding: 14px;
  }
  .insight-item .icon-badge { width: 38px; height: 38px; flex-basis: 38px; border-radius: 12px; }
  .insight-item.success .icon-badge { color: #BBF7D0; background: rgba(34,197,94,0.12); border-color: rgba(34,197,94,0.24); }
  .insight-item.danger .icon-badge { color: #FECACA; background: rgba(239,68,68,0.12); border-color: rgba(239,68,68,0.24); }
  .insight-item.warning .icon-badge { color: #FED7AA; background: rgba(245,158,11,0.12); border-color: rgba(245,158,11,0.24); }
  .insight-item strong { color: var(--text); display: block; font-size: 13.5px; margin-bottom: 4px; }
  .insight-item p { color: var(--muted); font-size: 12.5px; line-height: 1.55; }
  .trust-note {
    display: flex;
    gap: 11px;
    align-items: center;
    color: var(--muted);
    border: 1px solid var(--border-gold);
    border-radius: 18px;
    background: rgba(255,255,255,0.028);
    font-size: 13px;
    line-height: 1.6;
    padding: 15px 17px;
  }
  @media (max-width: 1180px) {
    .hero, .main-grid { grid-template-columns: 1fr; }
    .hero::after { display: none; }
    .hero-actions { max-width: none; }
    .kpi-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  }
  @media (max-width: 860px) {
    .topbar-inner, .wrap { padding-left: 16px; padding-right: 16px; }
    .topbar-inner { display: grid; min-height: auto; padding-top: 16px; padding-bottom: 16px; }
    .brand img { width: 112px; }
    .nav { justify-content: flex-start; gap: 8px; }
    .nav a { font-size: 12.5px; padding: 10px 12px; }
    .wrap { padding-top: 18px; }
    .hero { border-radius: 22px; padding: 24px; }
    .filter-form { grid-template-columns: 1fr; }
    .filter-buttons { grid-template-columns: 1fr; }
    .kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    .global-empty { grid-template-columns: 1fr; }
    .global-empty-actions { justify-content: flex-start; }
    .funnel-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .panel-head { flex-direction: column; }
    .panel-metric { white-space: normal; }
  }
  @media (max-width: 560px) {
    h1 { font-size: 34px; }
    .kpi-grid, .funnel-grid { grid-template-columns: 1fr; }
    .btn { width: 100%; }
    .panel-head, .panel-body { padding-left: 16px; padding-right: 16px; }
    .chart-wrap svg { height: 220px; }
    .source-top, .device-top { grid-template-columns: minmax(0, 1fr) auto; }
    .source-top em, .device-top em { grid-column: 2; }
    .trust-note { align-items: flex-start; }
  }
</style>
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="dashboard.php" aria-label="<?= h($brand['name']) ?> Admin">
      <img src="<?= h($logoPath) ?>" alt="<?= h($brand['name']) ?>">
    </a>
    <nav class="nav" aria-label="Navigasi admin">
      <a href="dashboard.php">Dashboard</a>
      <a href="events.php">Kelola Event</a>
      <a class="active" href="visitor-analytics.php">Analitik Pengunjung</a>
      <a class="logout" href="logout.php">Keluar</a>
    </nav>
  </div>
</header>

<main class="wrap">
  <section class="hero" aria-labelledby="analytics-title">
    <div class="hero-copy">
      <div class="breadcrumb">
        <a href="dashboard.php">Dashboard</a>
        <span>/</span>
        <span>Analitik Pengunjung</span>
      </div>
      <span class="eyebrow">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="m9 12 2 2 4-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        First-party Tracking
      </span>
      <h1 id="analytics-title">Analitik <span>Pengunjung</span></h1>
      <p class="subtitle">Pantau kunjungan, perilaku scroll, sumber trafik, perangkat, dan konversi form dari landing page event.</p>
    </div>
    <div class="hero-actions">
      <form class="filter-form" method="get" action="visitor-analytics.php">
        <div class="field">
          <label for="range">Periode</label>
          <select class="select-field" id="range" name="range">
            <?php foreach ($allowedRanges as $days => $label): ?>
              <option value="<?= (int)$days ?>" <?= $rangeDays === (int)$days ? 'selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="event">Event</label>
          <select class="select-field" id="event" name="event">
            <option value="">Semua Event</option>
            <?php foreach ($events as $event): ?>
              <option value="<?= h($event['slug']) ?>" <?= $selectedEvent === $event['slug'] ? 'selected' : '' ?>><?= h($event['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-buttons">
          <a class="btn btn-secondary" href="visitor-analytics.php<?= $filterQuery ? '?' . h($filterQuery) : '' ?>">Refresh</a>
          <button class="btn btn-primary" type="submit">Terapkan Filter</button>
        </div>
        <div class="filter-buttons">
          <a class="btn btn-secondary" href="export-analytics.php<?= $filterQuery ? '?' . h($filterQuery) : '' ?>" style="grid-column: 1 / -1;">⬇ Export CSV</a>
        </div>
        <div class="filter-date">
          <span><?= h(date('d M Y', strtotime("-{$rangeDays} days"))) ?></span>
          <span>-</span>
          <span><?= h(date('d M Y')) ?></span>
        </div>
      </form>
    </div>
  </section>

  <section class="kpi-grid" aria-label="Ringkasan analytics">
    <?php
      $kpis = [
        ['label' => 'Total Pageview', 'value' => number_format($totalPageview, 0, ',', '.'), 'raw' => $totalPageview, 'icon' => '<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>'],
        ['label' => 'Sesi Unik', 'value' => number_format($uniqueSessions, 0, ',', '.'), 'raw' => $uniqueSessions, 'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m14-10a4 4 0 1 0 0-8m6 18v-2a4 4 0 0 0-3-3.87M10 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'],
        ['label' => 'Form Dimulai', 'value' => number_format($formStarts, 0, ',', '.'), 'raw' => $formStarts, 'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" stroke="currentColor" stroke-width="2"/><path d="M14 2v6h6M8 13h8M8 17h5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>'],
        ['label' => 'Form Submit', 'value' => number_format($formSubmits, 0, ',', '.'), 'raw' => $formSubmits, 'icon' => '<path d="m22 2-7 20-4-9-9-4 20-7Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M22 2 11 13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>'],
        ['label' => 'Conversion Rate', 'value' => format_pct($conversionRate), 'raw' => $conversionRate, 'icon' => '<path d="M12 22a10 10 0 1 0-10-10 10 10 0 0 0 10 10Z" stroke="currentColor" stroke-width="2"/><path d="M12 16v-4l3-3M8 12h8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>'],
      ];
    ?>
    <?php foreach ($kpis as $item): ?>
      <article class="kpi-card">
        <div class="kpi-top">
          <span class="icon-badge" aria-hidden="true"><svg width="21" height="21" viewBox="0 0 24 24" fill="none"><?= $item['icon'] ?></svg></span>
          <span class="kpi-label"><?= h($item['label']) ?></span>
        </div>
        <div class="kpi-value"><?= h($item['value']) ?></div>
        <p class="kpi-copy <?= (float)$item['raw'] <= 0 ? 'empty' : '' ?>"><?= (float)$item['raw'] <= 0 ? 'Belum ada data masuk' : h($allowedRanges[$rangeDays]) ?></p>
      </article>
    <?php endforeach; ?>
  </section>

  <?php if ($allAnalyticsEmpty): ?>
    <section class="global-empty" aria-label="Status data analytics">
      <div>
        <h2>Data belum masuk</h2>
        <p>Analytics akan mulai terisi setelah landing page event dikunjungi.</p>
      </div>
      <div class="global-empty-actions">
        <a class="btn btn-secondary" href="<?= h($landingHref) ?>">Lihat Landing Page</a>
        <a class="btn btn-primary" href="events.php">Kelola Event</a>
      </div>
    </section>
  <?php endif; ?>

  <div class="main-grid">
    <div class="left-stack">
      <section class="panel">
        <div class="panel-head">
          <div class="panel-title">
            <span class="icon-badge" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M3 3v18h18M7 15l4-4 3 3 6-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
            <div>
              <h2>Tren Pageview <?= h($allowedRanges[$rangeDays]) ?></h2>
              <p class="desc">Grafik kunjungan harian dari landing page event.</p>
            </div>
          </div>
          <div class="panel-metric">Total <strong><?= h(number_format($dailyTotal, 0, ',', '.')) ?></strong> Pageview</div>
        </div>
        <div class="panel-body">
          <?php if ($dailyTotal === 0): ?>
            <div class="empty-state">
              <strong>Belum ada pageview dalam periode ini.</strong>
              <span>Grafik akan muncul otomatis saat kunjungan pertama tercatat.</span>
            </div>
          <?php else: ?>
            <div class="chart-wrap">
              <svg viewBox="0 0 <?= (int)$chartW ?> <?= (int)$chartH ?>" preserveAspectRatio="none" role="img" aria-label="Tren pageview 14 hari">
                <defs>
                  <linearGradient id="chartGradient" x1="0" x2="0" y1="0" y2="1">
                    <stop offset="0%" stop-color="var(--gold)" stop-opacity="0.36"/>
                    <stop offset="100%" stop-color="var(--gold)" stop-opacity="0"/>
                  </linearGradient>
                </defs>
                <?php for ($line = 0; $line <= 4; $line++): ?>
                  <?php $y = $chartPadY + ($line / 4) * $plotH; ?>
                  <line class="chart-grid" x1="<?= (int)$chartPadX ?>" y1="<?= h(number_format($y, 1, '.', '')) ?>" x2="<?= (int)($chartW - $chartPadX) ?>" y2="<?= h(number_format($y, 1, '.', '')) ?>"></line>
                <?php endfor; ?>
                <path class="chart-area" d="<?= h($areaPath) ?>"></path>
                <polyline class="chart-line" points="<?= h($polylinePoints) ?>"></polyline>
                <?php foreach ($areaPoints as $i => $point): ?>
                  <circle class="chart-dot" cx="<?= h($point[0]) ?>" cy="<?= h($point[1]) ?>" r="4"></circle>
                  <circle class="chart-hit" data-date="<?= h(date('d M Y', strtotime($dailySeries[$i]['date']))) ?>" data-total="<?= (int)$dailySeries[$i]['total'] ?>" cx="<?= h($point[0]) ?>" cy="<?= h($point[1]) ?>" r="10" fill="transparent" style="cursor:pointer;"></circle>
                <?php endforeach; ?>
              </svg>
              <div id="chartTooltip" class="chart-tooltip"></div>
              <div class="chart-axis-labels">
                <span><?= h(date('d M', strtotime($dailySeries[0]['date']))) ?></span>
                <span><?= h(date('d M', strtotime($dailySeries[(int)floor((count($dailySeries) - 1) / 2)]['date']))) ?></span>
                <span><?= h(date('d M', strtotime($dailySeries[count($dailySeries) - 1]['date']))) ?></span>
              </div>
              <div class="legend"><span class="legend-dot"></span><span>Pageview harian</span></div>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <section class="panel">
        <div class="panel-head">
          <div class="panel-title">
            <span class="icon-badge" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M4 4v16h16M8 16l3-4 3 2 4-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
            <div>
              <h2>Performa Per Pengundang / Referral Link</h2>
              <p class="desc">Kunjungan dan konversi dari setiap link referral yang dibagikan.</p>
            </div>
          </div>
        </div>
        <div class="panel-body">
          <?php if (empty($referrerPerf)): ?>
            <div class="empty-state">
              <strong>Belum ada kunjungan lewat link referral.</strong>
              <span>Data muncul begitu ada pengunjung yang datang lewat link ?ref= milik pengundang.</span>
            </div>
          <?php else: ?>
            <div style="overflow-x:auto;">
              <table style="width:100%; border-collapse:collapse; font-size:13px;">
                <tr style="color:var(--muted); text-align:left; border-bottom:1px solid var(--border-soft);">
                  <th style="padding:9px 8px; font-size:11px; text-transform:uppercase; letter-spacing:.04em;">Pengundang</th>
                  <th style="padding:9px 8px; font-size:11px; text-transform:uppercase; letter-spacing:.04em;">Kode</th>
                  <th style="padding:9px 8px; text-align:right; font-size:11px; text-transform:uppercase; letter-spacing:.04em;">Kunjungan</th>
                  <th style="padding:9px 8px; text-align:right; font-size:11px; text-transform:uppercase; letter-spacing:.04em;">Submit</th>
                  <th style="padding:9px 8px; text-align:right; font-size:11px; text-transform:uppercase; letter-spacing:.04em;">Conversion</th>
                </tr>
                <?php foreach ($referrerPerf as $rp): ?>
                  <tr style="border-bottom:1px solid rgba(255,255,255,0.04);">
                    <td style="padding:10px 8px; color:var(--text); font-weight:700;"><?= h($rp['referrer_name']) ?></td>
                    <td style="padding:10px 8px; color:var(--muted); font-family:monospace; font-size:12px;"><?= h($rp['ref_code']) ?></td>
                    <td style="padding:10px 8px; text-align:right; color:var(--gold-soft); font-weight:800;"><?= h(number_format($rp['visits'], 0, ',', '.')) ?></td>
                    <td style="padding:10px 8px; text-align:right;"><?= h(number_format($rp['submits'], 0, ',', '.')) ?></td>
                    <td style="padding:10px 8px; text-align:right;"><?= h(format_pct($rp['conv_rate'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <section class="panel">
        <div class="panel-head">
          <div class="panel-title">
            <span class="icon-badge" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M4 4h16v4H4Zm0 6h10v4H4Zm0 6h7v4H4Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg></span>
            <div>
              <h2>Funnel Kunjungan</h2>
              <p class="desc">Tahapan dari pageview sampai submit form, termasuk konversi dan drop-off antar tahap.</p>
            </div>
          </div>
        </div>
        <div class="panel-body">
          <?php if (($funnelRaw['pageview'] ?? 0) === 0): ?>
            <div class="empty-state">
              <strong>Funnel belum terbentuk karena belum ada kunjungan.</strong>
              <span>Data akan tersusun setelah event pageview, scroll, dan form mulai tercatat.</span>
            </div>
          <?php else: ?>
            <div class="funnel-grid">
              <?php foreach ($funnelStages as $index => $stage): ?>
                <article class="funnel-step">
                  <div class="funnel-name"><?= h(($index + 1) . '. ' . $stage['label']) ?></div>
                  <div class="funnel-total"><?= h(number_format($stage['total'], 0, ',', '.')) ?></div>
                  <div class="bar-track"><div class="bar-fill" style="width: <?= h(number_format($stage['pct'], 2, '.', '')) ?>%"></div></div>
                  <div class="funnel-meta">
                    <span><?= h(format_pct($stage['prev_conversion'])) ?> dari tahap sebelumnya</span>
                    <?php if ($stage['drop'] !== null): ?>
                      <span class="funnel-drop">Drop-off <?= h(number_format($stage['drop'], 0, ',', '.')) ?> (<?= h(format_pct($stage['drop_pct'])) ?>)</span>
                    <?php endif; ?>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
            <?php if ($largestDrop !== null): ?>
              <div class="funnel-insight">
                <span class="icon-badge" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M9 18h6M10 22h4M12 2a7 7 0 0 0-4 12.74V16h8v-1.26A7 7 0 0 0 12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
                <span>Insight: Drop-off terbesar terjadi di tahap <strong><?= h($largestDrop['label']) ?></strong>. Pertimbangkan optimasi konten atau CTA sebelum tahap ini.</span>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </section>

      <div class="trust-note">
        <span class="icon-badge" aria-hidden="true"><svg width="19" height="19" viewBox="0 0 24 24" fill="none"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z" stroke="currentColor" stroke-width="2"/></svg></span>
        <span>Data ini dikumpulkan secara first-party dan tidak menampilkan IP mentah pengunjung.</span>
      </div>
    </div>

    <aside class="right-stack">
      <section class="panel">
        <div class="panel-head">
          <div class="panel-title">
            <span class="icon-badge" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20ZM2 12h20M12 2c3 3 4.5 6.3 4.5 10S15 19 12 22M12 2C9 5 7.5 8.3 7.5 12S9 19 12 22" stroke="currentColor" stroke-width="2"/></svg></span>
            <div>
              <h2>Sumber Trafik</h2>
              <p class="desc">Berdasarkan UTM atau domain referrer.</p>
            </div>
          </div>
        </div>
        <div class="panel-body">
          <?php if (empty($sources)): ?>
            <div class="empty-state">
              <strong>Belum ada data trafik.</strong>
              <span>Pastikan landing page sudah dikunjungi atau UTM digunakan.</span>
            </div>
          <?php else: ?>
            <div class="source-list">
              <?php foreach ($sources as $label => $total): ?>
                <?php $sourcePct = pct($total, $sourcesTotal, 1); ?>
                <div class="source-row">
                  <div class="source-top">
                    <strong><?= h($label) ?></strong>
                    <span><?= h(number_format($total, 0, ',', '.')) ?></span>
                    <em><?= h(format_pct($sourcePct)) ?></em>
                  </div>
                  <div class="bar-track"><div class="bar-fill" style="width: <?= h(number_format(($total / $sourcesMax) * 100, 2, '.', '')) ?>%"></div></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <section class="panel">
        <div class="panel-head">
          <div class="panel-title">
            <span class="icon-badge" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M14 2v6h6" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg></span>
            <div>
              <h2>Top Halaman Masuk</h2>
              <p class="desc">Halaman yang paling banyak dikunjungi.</p>
            </div>
          </div>
        </div>
        <div class="panel-body">
          <?php if (empty($topPages)): ?>
            <div class="empty-state">
              <strong>Belum ada data halaman.</strong>
              <span>Muncul setelah ada pageview tercatat.</span>
            </div>
          <?php else: ?>
            <div class="source-list">
              <?php foreach ($topPages as $tp): ?>
                <?php $tpPct = pct($tp['total'], $topPagesTotal, 1); ?>
                <div class="source-row">
                  <div class="source-top">
                    <strong title="<?= h($tp['page_path']) ?>" style="overflow:hidden; text-overflow:ellipsis;"><?= h($tp['page_path']) ?></strong>
                    <span><?= h(number_format($tp['total'], 0, ',', '.')) ?></span>
                    <em><?= h(format_pct($tpPct)) ?></em>
                  </div>
                  <div class="bar-track"><div class="bar-fill" style="width: <?= h(number_format($tpPct, 2, '.', '')) ?>%"></div></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <section class="panel">
        <div class="panel-head">
          <div class="panel-title">
            <span class="icon-badge" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M7 2h10a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Zm5 17h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
            <div>
              <h2>Breakdown Device</h2>
              <p class="desc">Distribusi pageview berdasarkan jenis perangkat.</p>
            </div>
          </div>
        </div>
        <div class="panel-body">
          <?php if ($deviceTotal === 0): ?>
            <div class="empty-state">
              <strong>Belum ada data perangkat.</strong>
              <span>Breakdown akan muncul saat pageview pertama masuk.</span>
            </div>
          <?php else: ?>
            <div class="device-list">
              <?php foreach ($deviceRaw as $device => $total): ?>
                <?php $devicePct = pct($total, $deviceTotal, 1); ?>
                <div class="device-row">
                  <div class="device-top">
                    <strong class="device-name"><span class="device-icon" aria-hidden="true">●</span><?= h($deviceLabels[$device]) ?></strong>
                    <span><?= h(number_format($total, 0, ',', '.')) ?></span>
                    <em><?= h(format_pct($devicePct)) ?></em>
                  </div>
                  <div class="bar-track"><div class="bar-fill" style="width: <?= h(number_format($devicePct, 2, '.', '')) ?>%"></div></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <section class="panel">
        <div class="panel-head">
          <div class="panel-title">
            <span class="icon-badge" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="m12 2 2.8 6 6.2.8-4.6 4.4 1.2 6.2L12 16.3 6.4 19.4l1.2-6.2L3 8.8 9.2 8Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg></span>
            <div>
              <h2>Insight Cepat</h2>
              <p class="desc">Rekomendasi otomatis dari data yang sudah tersedia.</p>
            </div>
          </div>
        </div>
        <div class="panel-body">
          <div class="insight-grid">
            <?php foreach ($insights as $insight): ?>
              <article class="insight-item <?= h($insight['tone']) ?>">
                <span class="icon-badge" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M4 14h4v6H4Zm6-5h4v11h-4Zm6-5h4v16h-4Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg></span>
                <div>
                  <strong><?= h($insight['title']) ?></strong>
                  <p><?= h($insight['text']) ?></p>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
    </aside>
  </div>
</main>
<script>
  // Tooltip interaktif untuk chart tren pageview — vanilla JS, tanpa library
  (function () {
    var tooltip = document.getElementById('chartTooltip');
    var wrap = document.querySelector('.chart-wrap');
    if (!tooltip || !wrap) return;
    var svg = wrap.querySelector('svg');
    document.querySelectorAll('.chart-hit').forEach(function (dot) {
      dot.addEventListener('mouseenter', function () {
        var rect = svg.getBoundingClientRect();
        var wrapRect = wrap.getBoundingClientRect();
        var scaleX = rect.width / svg.viewBox.baseVal.width;
        var scaleY = rect.height / svg.viewBox.baseVal.height;
        var cx = parseFloat(dot.getAttribute('cx')) * scaleX;
        var cy = parseFloat(dot.getAttribute('cy')) * scaleY;
        tooltip.innerHTML = '<div class="tt-date">' + dot.dataset.date + '</div><div class="tt-val">' + dot.dataset.total + ' Pageview</div>';
        tooltip.style.left = Math.max(0, cx - 55) + 'px';
        tooltip.style.top = Math.max(0, cy - 58) + 'px';
        tooltip.style.display = 'block';
      });
      dot.addEventListener('mouseleave', function () { tooltip.style.display = 'none'; });
    });
  })();
</script>
</body>
</html>
