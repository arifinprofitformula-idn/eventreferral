<?php
/**
 * referrer/dashboard.php
 * Dashboard pribadi pengundang: hanya menampilkan leads miliknya sendiri
 * (leads dengan ref_code+event_slug yang cocok dengan link yang ia buat).
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/referrer_auth.php';
start_secure_session();

$brand = require_referrer_login(get_current_brand());
$brandId = (int)$brand['id'];

$pdo = get_db();
$myLinks = get_referrer_rows_for_session($pdo, $brand);

if (empty($myLinks)) {
    // Sesi valid tapi datanya sudah tidak ada (mis. dihapus admin) — putus sesi.
    header('Location: /referrer/login.php');
    exit;
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? $brand['domain'];

$leads = [];
$totalLeads = 0;
if (!empty($myLinks)) {
    $conditions = [];
    $params = [$brandId];
    foreach ($myLinks as $link) {
        $conditions[] = '(l.ref_code = ? AND l.event_slug = ?)';
        $params[] = $link['ref_code'];
        $params[] = $link['event_slug'];
    }
    $whereClause = implode(' OR ', $conditions);

    $stmt = $pdo->prepare("
        SELECT l.name, l.email, l.whatsapp, l.kota, l.ref_code, l.event_slug, l.created_at,
               e.name AS event_name
        FROM leads l
        LEFT JOIN events e ON e.slug = l.event_slug AND e.brand_id = l.brand_id
        WHERE l.brand_id = ? AND ($whereClause)
        ORDER BY l.created_at DESC
        LIMIT 500
    ");
    $stmt->execute($params);
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalLeads = count($leads);
}

$totalEventsLinked = count($myLinks);
$referrerName = $myLinks[0]['name'] ?? '';
$logoPath = $brand['logo_path'] ? '..' . $brand['logo_path'] : '../assets/logo.png';

function referrer_whatsapp_link(?string $number): ?string
{
    $digits = preg_replace('/\D+/', '', (string)$number);
    return $digits === '' ? null : 'https://wa.me/' . $digits;
}

function referrer_build_link(string $protocol, string $host, array $brand, string $eventSlug, string $refCode): string
{
    if ($eventSlug === $brand['default_event_slug']) {
        return "{$protocol}://{$host}/?ref={$refCode}";
    }
    return "{$protocol}://{$host}" . EVENTS_URL_BASE . "/{$eventSlug}/?ref={$refCode}";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Pengundang — <?= htmlspecialchars($brand['name']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  <?= get_theme_css_vars($brand) ?>
  :root {
    --bg: #0B0B0A; --bg-soft: #10100F; --surface: #171716; --surface-elevated: #20201E;
    --border-gold: color-mix(in srgb, var(--gold) 18%, transparent); --border-soft: rgba(255,255,255,0.08);
    --gold: var(--brand-primary); --gold-soft: var(--brand-soft); --text: #F7F3E8; --muted: #A8A29A;
    --success: #22C55E; --danger: #EF4444; --shadow: 0 22px 70px rgba(0,0,0,0.34);
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    min-height: 100vh; color: var(--text);
    background: radial-gradient(circle at 86% 8%, color-mix(in srgb, var(--gold) 22%, transparent), transparent 30vw),
      radial-gradient(circle at 8% 88%, color-mix(in srgb, var(--gold) 13%, transparent), transparent 34vw),
      linear-gradient(135deg, var(--bg) 0%, var(--bg-soft) 48%, #090908 100%);
    font-family: 'Poppins', Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  }
  a { color: inherit; }
  .topbar { position: sticky; top: 0; z-index: 20; background: rgba(16,16,15,0.78); border-bottom: 1px solid var(--border-soft); backdrop-filter: blur(16px); }
  .topbar-inner, .wrap { width: min(100%, 1200px); margin: 0 auto; padding-left: 32px; padding-right: 32px; }
  .topbar-inner { min-height: 82px; display: flex; align-items: center; justify-content: space-between; gap: 22px; }
  .brand { display: inline-flex; align-items: center; gap: 14px; text-decoration: none; }
  .brand img { width: 140px; height: auto; object-fit: contain; }
  .nav { display: flex; align-items: center; gap: 10px; }
  .nav a { color: var(--muted); display: inline-flex; align-items: center; gap: 9px; border: 1px solid rgba(255,255,255,0.10); background: rgba(255,255,255,0.035); border-radius: 999px; font-size: 13.5px; font-weight: 600; padding: 12px 16px; text-decoration: none; }
  .nav a:hover { color: var(--text); background: rgba(255,255,255,0.06); }
  .wrap { padding-top: 28px; padding-bottom: 52px; }
  .hero {
    position: relative; overflow: hidden;
    background: radial-gradient(circle at 88% 50%, color-mix(in srgb, var(--gold-soft) 22%, transparent), transparent 20%),
      linear-gradient(135deg, rgba(32,32,30,0.96), rgba(23,23,22,0.92) 55%, rgba(76,52,12,0.34));
    border: 1px solid var(--border-gold); border-radius: 24px; box-shadow: var(--shadow); padding: 32px; margin-bottom: 20px;
  }
  h1 { font-family: 'Playfair Display', Georgia, serif; font-size: clamp(26px, 4vw, 38px); line-height: 1.1; margin-bottom: 10px; }
  h1 span { color: var(--gold-soft); }
  .subtitle { color: var(--muted); font-size: 14.5px; line-height: 1.7; max-width: 640px; }
  .stats { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 16px; margin: 20px 0; }
  .stat-card {
    background: linear-gradient(145deg, rgba(32,32,30,0.95), rgba(23,23,22,0.93)); border: 1px solid var(--border-gold);
    border-radius: 20px; padding: 22px; box-shadow: 0 16px 42px rgba(0,0,0,0.24);
  }
  .stat-label { color: var(--muted); font-size: 11px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; }
  .num { color: var(--text); font-size: clamp(30px, 4.5vw, 38px); font-weight: 800; margin-top: 8px; }
  .section-card { background: linear-gradient(145deg, rgba(32,32,30,0.92), rgba(23,23,22,0.94)); border: 1px solid var(--border-gold); border-radius: 22px; box-shadow: 0 18px 50px rgba(0,0,0,0.24); margin-top: 18px; padding: 20px; }
  .section-head, .toolbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 16px; flex-wrap: wrap; }
  .section-title { color: var(--text); font-size: 17px; font-weight: 800; }
  .section-desc { color: var(--muted); font-size: 13px; margin-top: 5px; }
  .link-list { display: grid; gap: 10px; }
  .link-row { display: flex; align-items: center; gap: 10px; background: rgba(8,8,7,0.34); border: 1px solid var(--border-soft); border-radius: 14px; padding: 12px 14px; flex-wrap: wrap; }
  .link-row .event-tag { color: var(--gold-soft); font-weight: 700; font-size: 13px; min-width: 140px; }
  .link-row input { flex: 1; min-width: 180px; height: 40px; color: var(--text); background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.10); border-radius: 10px; padding: 0 12px; font-size: 12.5px; }
  .copy-btn { height: 40px; padding: 0 16px; border-radius: 10px; border: 0; color: #111; background: linear-gradient(135deg, var(--gold), var(--gold-soft)); font-weight: 700; font-size: 12.5px; cursor: pointer; }
  .table-scroll { overflow-x: auto; border: 1px solid var(--border-soft); border-radius: 16px; background: rgba(8,8,7,0.34); }
  table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13.5px; min-width: 700px; }
  th { background: rgba(32,32,30,0.98); color: var(--text); text-align: left; padding: 14px 16px; font-weight: 700; white-space: nowrap; }
  td { color: rgba(247,243,232,0.92); padding: 14px 16px; border-top: 1px solid rgba(255,255,255,0.06); white-space: nowrap; }
  tbody tr:nth-child(even) td { background: rgba(255,255,255,0.025); }
  .wa-link { color: var(--gold-soft); text-decoration: none; }
  .wa-link:hover { text-decoration: underline; }
  .search-input { width: min(260px, 100%); height: 44px; color: var(--text); background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.10); border-radius: 12px; font: inherit; font-size: 13px; padding: 0 14px; outline: none; }
  .empty { color: var(--muted); background: rgba(255,255,255,0.035); border: 1px dashed color-mix(in srgb, var(--gold-soft) 18%, transparent); border-radius: 16px; font-size: 14px; padding: 26px; text-align: center; }
  @media (max-width: 760px) {
    .topbar-inner, .wrap { padding-left: 16px; padding-right: 16px; }
    .stats { grid-template-columns: 1fr; }
    .table-scroll { display: none; }
    .mobile-cards { display: grid; gap: 12px; }
    .lead-card { background: rgba(8,8,7,0.34); border: 1px solid var(--border-soft); border-radius: 14px; padding: 14px; }
    .lead-card .lead-name { font-weight: 800; }
    .lead-card .lead-meta { color: var(--muted); font-size: 12.5px; line-height: 1.7; margin-top: 4px; }
  }
  @media (min-width: 761px) { .mobile-cards { display: none; } }
</style>
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="dashboard.php"><img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($brand['name']) ?>"></a>
    <nav class="nav">
      <a href="/buat-link.php">Buat Link Baru</a>
      <a href="logout.php">Keluar</a>
    </nav>
  </div>
</header>

<main class="wrap">
  <section class="hero">
    <h1>Halo, <span><?= htmlspecialchars($referrerName) ?></span> 👋</h1>
    <p class="subtitle">Ini daftar peserta yang mendaftar lewat link referral kamu. Klik nomor WhatsApp untuk langsung follow up.</p>
  </section>

  <section class="stats">
    <article class="stat-card">
      <div class="stat-label">Total Pendaftar</div>
      <div class="num"><?= (int)$totalLeads ?></div>
    </article>
    <article class="stat-card">
      <div class="stat-label">Link Aktif</div>
      <div class="num"><?= (int)$totalEventsLinked ?></div>
    </article>
  </section>

  <section class="section-card">
    <div class="section-head">
      <div>
        <div class="section-title">Link Referral Kamu</div>
        <p class="section-desc">Salin dan bagikan lagi kapan saja.</p>
      </div>
    </div>
    <div class="link-list">
      <?php foreach ($myLinks as $link): ?>
      <?php $shareLink = referrer_build_link($protocol, $host, $brand, $link['event_slug'], $link['ref_code']); ?>
      <div class="link-row">
        <span class="event-tag"><?= htmlspecialchars($link['event_name'] ?? $link['event_slug']) ?></span>
        <input type="text" readonly value="<?= htmlspecialchars($shareLink) ?>" onclick="this.select()">
        <button class="copy-btn" type="button" onclick="copyLink(this)" data-link="<?= htmlspecialchars($shareLink) ?>">Salin</button>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="section-card">
    <div class="toolbar">
      <div>
        <div class="section-title">Daftar Pendaftar</div>
        <p class="section-desc">Menampilkan hingga 500 pendaftar terbaru dari link kamu.</p>
      </div>
      <input class="search-input" id="leadSearch" type="search" placeholder="Cari pendaftar..." aria-label="Cari pendaftar">
    </div>
    <?php if (empty($leads)): ?>
      <p class="empty">Belum ada pendaftar dari link kamu. Yuk bagikan lagi link referralmu!</p>
    <?php else: ?>
    <div class="table-scroll">
      <table>
        <thead><tr><th>Waktu</th><th>Nama</th><th>Email</th><th>WhatsApp</th><th>Kota</th><th>Event</th></tr></thead>
        <tbody id="leadRows">
        <?php foreach ($leads as $l): ?>
        <?php $waLink = referrer_whatsapp_link($l['whatsapp'] ?? ''); ?>
        <tr>
          <td><?= date('d M Y, H:i', strtotime($l['created_at'])) ?></td>
          <td><?= htmlspecialchars($l['name']) ?></td>
          <td><?= htmlspecialchars($l['email']) ?></td>
          <td><?php if ($waLink): ?><a class="wa-link" href="<?= htmlspecialchars($waLink) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($l['whatsapp']) ?></a><?php else: ?><?= htmlspecialchars($l['whatsapp']) ?><?php endif; ?></td>
          <td><?= htmlspecialchars($l['kota']) ?></td>
          <td><?= htmlspecialchars($l['event_name'] ?? '-') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="mobile-cards" id="leadCards">
      <?php foreach ($leads as $l): ?>
      <?php $waLink = referrer_whatsapp_link($l['whatsapp'] ?? ''); ?>
      <article class="lead-card">
        <div class="lead-name"><?= htmlspecialchars($l['name']) ?></div>
        <div class="lead-meta">
          <?= htmlspecialchars($l['email']) ?><br>
          <?php if ($waLink): ?><a class="wa-link" href="<?= htmlspecialchars($waLink) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($l['whatsapp']) ?></a><?php else: ?><?= htmlspecialchars($l['whatsapp']) ?><?php endif; ?><br>
          <?= htmlspecialchars($l['kota']) ?> · <?= htmlspecialchars($l['event_name'] ?? '-') ?><br>
          <?= date('d M Y, H:i', strtotime($l['created_at'])) ?>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>
</main>

<script>
  function copyLink(btn) {
    const link = btn.getAttribute('data-link');
    navigator.clipboard.writeText(link).then(() => {
      const original = btn.textContent;
      btn.textContent = 'Tersalin!';
      setTimeout(() => btn.textContent = original, 1500);
    });
  }

  const searchInput = document.getElementById('leadSearch');
  const leadRows = Array.from(document.querySelectorAll('#leadRows tr'));
  const leadCards = Array.from(document.querySelectorAll('#leadCards .lead-card'));
  if (searchInput) {
    searchInput.addEventListener('input', () => {
      const keyword = searchInput.value.trim().toLowerCase();
      [...leadRows, ...leadCards].forEach((item) => {
        item.style.display = item.textContent.toLowerCase().includes(keyword) ? '' : 'none';
      });
    });
  }
</script>
</body>
</html>
