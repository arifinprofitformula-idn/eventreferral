<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/bootstrap.php';

$brand = get_current_brand();
if (!$brand) {
    http_response_code(404);
    exit('Event tidak ditemukan.');
}

$brandId = (int)$brand['id'];
$slug = trim((string)($_GET['event'] ?? ''));
if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
    http_response_code(404);
    exit('Event tidak ditemukan.');
}

$pdo = get_db();
$stmt = $pdo->prepare('SELECT slug, name, status FROM events WHERE slug = ? AND brand_id = ? LIMIT 1');
$stmt->execute([$slug, $brandId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event || ($event['status'] ?? '') !== 'active') {
    http_response_code(404);
    $logoPath = $brand['logo_path'] ?: '/assets/logo.png';
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Event Tidak Aktif — <?= htmlspecialchars($brand['name']) ?></title>
<style>
  <?= get_theme_css_vars($brand) ?>
  :root {
    --bg:#0B0B0A;
    --surface:#171716;
    --gold:var(--brand-primary);
    --gold-soft:var(--brand-soft);
    --text:#F7F3E8;
    --muted:#A8A29A;
  }
  * { box-sizing: border-box; }
  body {
    min-height: 100vh;
    display: grid;
    place-items: center;
    margin: 0;
    padding: 24px;
    color: var(--text);
    background:
      radial-gradient(circle at 80% 10%, color-mix(in srgb, var(--gold) 22%, transparent), transparent 32vw),
      linear-gradient(135deg, var(--bg), #10100F);
    font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  }
  .card {
    width: min(100%, 560px);
    border: 1px solid color-mix(in srgb, var(--gold) 20%, transparent);
    border-radius: 24px;
    background: linear-gradient(145deg, rgba(32,32,30,.94), rgba(23,23,22,.96));
    box-shadow: 0 24px 80px rgba(0,0,0,.34);
    padding: 34px;
    text-align: center;
  }
  img { width: 142px; height: auto; margin-bottom: 22px; }
  h1 { margin: 0 0 10px; font-size: clamp(28px, 5vw, 42px); line-height: 1.08; }
  p { color: var(--muted); line-height: 1.7; margin: 0 auto 22px; max-width: 430px; }
  a {
    display: inline-flex;
    justify-content: center;
    min-height: 46px;
    align-items: center;
    border-radius: 14px;
    color: #111;
    background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    font-weight: 800;
    padding: 12px 18px;
    text-decoration: none;
  }
</style>
</head>
<body>
  <main class="card">
    <img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($brand['name']) ?>">
    <h1>Event Tidak Aktif</h1>
    <p>Halaman event ini sedang tidak ditampilkan. Silakan kembali ke halaman utama untuk melihat event yang tersedia.</p>
    <a href="/">Kembali ke Halaman Utama</a>
  </main>
</body>
</html>
    <?php
    exit;
}

$indexPath = EVENTS_DIR . '/' . $slug . '/index.html';
$realEventsDir = realpath(EVENTS_DIR);
$realIndexPath = realpath($indexPath);
if (!$realEventsDir || !$realIndexPath || strpos($realIndexPath, $realEventsDir) !== 0 || !is_file($realIndexPath)) {
    http_response_code(404);
    exit('Event tidak ditemukan.');
}

header('Content-Type: text/html; charset=UTF-8');
readfile($realIndexPath);
