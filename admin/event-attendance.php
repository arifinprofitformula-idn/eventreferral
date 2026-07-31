<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
start_secure_session();

$brand = require_admin_for_brand(get_current_brand());
$brandId = (int)$brand['id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pdo = get_db();

// ==================== EVENT AKTIF ====================
$stmt = $pdo->prepare('SELECT slug, name FROM events WHERE brand_id = ? ORDER BY (slug = ?) DESC, created_at DESC');
$stmt->execute([$brandId, $brand['default_event_slug']]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

$requestedSlug = clean($_GET['event'] ?? '');
$eventSlug = '';
foreach ($events as $ev) {
    if ($ev['slug'] === $requestedSlug) {
        $eventSlug = $ev['slug'];
        break;
    }
}
if ($eventSlug === '' && !empty($events)) {
    $eventSlug = $events[0]['slug'];
}

$registrants = [];
$totalRegistrants = 0;
$totalHadir = 0;

if ($eventSlug !== '') {
    $event = get_event_by_slug($eventSlug);

    if ($event && (int)$event['brand_id'] === $brandId) {
        $eventId = (int)$event['id'];

        // LEFT JOIN ke event_attendance — pendaftar lama tanpa qr_token (sebelum fitur ini ada)
        // TETAP tampil, ditandai attendance_status NULL ("belum di-generate"), bukan error.
        $stmt = $pdo->prepare('
            SELECT l.id AS registrant_id, l.name, l.whatsapp, l.kota,
                r.name AS referrer_name, r.ref_code,
                ea.attendance_status, ea.check_in_time, ea.check_in_method, ea.duplicate_flag,
                ea.is_reward_eligible, ea.qr_token IS NOT NULL AS has_qr
            FROM leads l
            LEFT JOIN referrers r ON r.event_slug = l.event_slug AND r.ref_code = l.ref_code AND r.brand_id = l.brand_id
            LEFT JOIN event_attendance ea ON ea.registrant_id = l.id AND ea.event_id = ?
            WHERE l.brand_id = ? AND l.event_slug = ?
            ORDER BY l.name ASC
            LIMIT 1000
        ');
        $stmt->execute([$eventId, $brandId, $eventSlug]);
        $registrants = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalRegistrants = count($registrants);
        foreach ($registrants as $row) {
            if ($row['attendance_status'] === 'hadir') {
                $totalHadir++;
            }
        }
    } else {
        $eventSlug = '';
    }
}

function whatsapp_link_att(?string $number): ?string
{
    $digits = preg_replace('/\D+/', '', (string)$number);
    return $digits === '' ? null : 'https://wa.me/' . $digits;
}

$statusLabel = [
    'hadir' => 'Hadir',
    'tidak_hadir' => 'Belum Hadir',
    'terlambat' => 'Terlambat',
    'batal' => 'Batal',
];
$logoPath = $brand['logo_path'] ? '..' . $brand['logo_path'] : '../assets/logo.png';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Kehadiran Event — <?= htmlspecialchars($brand['name']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
<style>
  <?= get_theme_css_vars($brand) ?>
  :root {
    --bg:#0B0B0A;
    --bg-soft:#10100F;
    --surface:#171716;
    --border-gold:color-mix(in srgb, var(--gold) 18%, transparent);
    --border-soft:rgba(255,255,255,0.09);
    --gold:var(--brand-primary);
    --gold-soft:var(--brand-soft);
    --text:#F7F3E8;
    --muted:#A8A29A;
    --success:#22C55E;
    --warning:#F59E0B;
    --danger:#EF4444;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    min-height: 100vh;
    background: linear-gradient(135deg, var(--bg) 0%, var(--bg-soft) 55%, #090908 100%);
    color: var(--text);
    font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  }
  a { color: inherit; }
  .topbar {
    position: sticky;
    top: 0;
    z-index: 30;
    background: rgba(16,16,15,0.85);
    border-bottom: 1px solid var(--border-soft);
    backdrop-filter: blur(14px);
  }
  .topbar-inner {
    width: min(100%, 1200px);
    margin: 0 auto;
    min-height: 68px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 12px 20px;
  }
  .brand { display: inline-flex; align-items: center; gap: 12px; text-decoration: none; }
  .brand img { width: 120px; height: auto; }
  .back-link {
    color: var(--muted);
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
  }
  .back-link:hover { color: var(--text); }
  .wrap {
    width: min(100%, 1200px);
    margin: 0 auto;
    padding: 20px 20px 60px;
  }
  h1 {
    font-family: "Playfair Display", Georgia, serif;
    font-size: clamp(24px, 4vw, 32px);
    margin-bottom: 6px;
  }
  .subtitle { color: var(--muted); font-size: 13.5px; margin-bottom: 18px; }
  .event-picker {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 18px;
  }
  select, input[type="search"] {
    color: var(--text);
    background: rgba(255,255,255,0.045);
    border: 1px solid rgba(255,255,255,0.14);
    border-radius: 12px;
    font: inherit;
    font-size: 14px;
    min-height: 46px;
    padding: 0 14px;
    outline: none;
  }
  select:focus, input:focus {
    border-color: color-mix(in srgb, var(--gold-soft) 42%, transparent);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--gold) 10%, transparent);
  }
  .counter-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    background: linear-gradient(135deg, rgba(32,32,30,0.95), rgba(23,23,22,0.92));
    border: 1px solid var(--border-gold);
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 18px;
  }
  .counter-num { font-size: 34px; font-weight: 900; color: var(--gold-soft); line-height: 1; }
  .counter-label { color: var(--muted); font-size: 12.5px; margin-top: 4px; }
  .counter-bar-track {
    flex: 1;
    min-width: 160px;
    height: 10px;
    background: rgba(255,255,255,0.08);
    border-radius: 999px;
    overflow: hidden;
  }
  .counter-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--gold), var(--gold-soft));
    border-radius: 999px;
    transition: width 240ms ease;
  }
  .actions-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; }
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 46px;
    border: 1px solid transparent;
    border-radius: 12px;
    cursor: pointer;
    font: inherit;
    font-size: 13.5px;
    font-weight: 800;
    padding: 12px 18px;
    text-decoration: none;
    white-space: nowrap;
  }
  .btn-gold { color: #111; background: linear-gradient(135deg, var(--gold), var(--gold-soft)); }
  .btn-outline { color: var(--text); background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.14); }
  .btn:disabled { opacity: .5; cursor: not-allowed; }
  #scannerPanel {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 50;
    background: #000;
  }
  #scannerPanel.open { display: flex; flex-direction: column; }
  .scanner-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 18px;
    color: var(--text);
    background: rgba(0,0,0,0.6);
  }
  #qrReader { flex: 1; width: 100%; }
  #qrReader video { object-fit: cover; }
  .scanner-result {
    padding: 14px 18px 22px;
    background: rgba(0,0,0,0.75);
    color: var(--text);
    font-size: 14px;
    min-height: 60px;
    text-align: center;
  }
  .scanner-result.ok { color: #A7F3D0; }
  .scanner-result.err { color: #FECACA; }
  .toolbar { display: flex; gap: 10px; margin-bottom: 14px; flex-wrap: wrap; }
  .toolbar input { flex: 1; min-width: 200px; }
  .list-card {
    background: linear-gradient(180deg, rgba(255,255,255,0.04), rgba(255,255,255,0.015));
    border: 1px solid var(--border-gold);
    border-radius: 18px;
    overflow: hidden;
  }
  .row-item {
    display: grid;
    grid-template-columns: minmax(0,1fr) auto auto;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    border-top: 1px solid rgba(255,255,255,0.06);
  }
  .row-item:first-child { border-top: 0; }
  .row-item:nth-child(even) { background: rgba(255,255,255,0.02); }
  .row-name { font-weight: 800; font-size: 14px; overflow-wrap: anywhere; }
  .row-meta { color: var(--muted); font-size: 12px; margin-top: 3px; overflow-wrap: anywhere; }
  .status-badge {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .04em;
    padding: 6px 10px;
    text-transform: uppercase;
    white-space: nowrap;
  }
  .status-badge.hadir { color: #BBF7D0; background: rgba(34,197,94,0.14); border: 1px solid rgba(34,197,94,0.3); }
  .status-badge.tidak_hadir { color: var(--muted); background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); }
  .status-badge.terlambat { color: #FED7AA; background: rgba(245,158,11,0.14); border: 1px solid rgba(245,158,11,0.3); }
  .status-badge.batal { color: #FECACA; background: rgba(239,68,68,0.14); border: 1px solid rgba(239,68,68,0.3); }
  .checkin-btn {
    min-height: 38px;
    border-radius: 10px;
    border: 1px solid color-mix(in srgb, var(--gold) 30%, transparent);
    background: color-mix(in srgb, var(--gold) 12%, transparent);
    color: var(--gold-soft);
    cursor: pointer;
    font: inherit;
    font-size: 12px;
    font-weight: 800;
    padding: 8px 12px;
    white-space: nowrap;
  }
  .checkin-btn:disabled { opacity: .45; cursor: default; }
  .empty { color: var(--muted); text-align: center; padding: 40px 20px; }
  .toast {
    position: fixed;
    left: 50%;
    bottom: 24px;
    transform: translateX(-50%);
    max-width: min(92vw, 420px);
    background: rgba(20,20,19,0.96);
    border: 1px solid var(--border-gold);
    border-radius: 14px;
    color: var(--text);
    font-size: 13.5px;
    padding: 14px 18px;
    box-shadow: 0 18px 50px rgba(0,0,0,0.4);
    z-index: 60;
    display: none;
  }
  .toast.ok { border-color: rgba(34,197,94,0.4); }
  .toast.err { border-color: rgba(239,68,68,0.4); }
  @media (max-width: 640px) {
    .row-item { grid-template-columns: minmax(0,1fr); }
    .row-item > div:nth-child(2) { justify-self: start; }
    .row-item > div:nth-child(3) { justify-self: stretch; }
    .checkin-btn { width: 100%; }
    .actions-row .btn { flex: 1 1 auto; }
  }
</style>
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="dashboard.php"><img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($brand['name']) ?>"></a>
    <span style="display:flex;gap:14px;align-items:center;">
      <a class="back-link" href="event-attendance-report.php">Rekap Kehadiran →</a>
      <a class="back-link" href="dashboard.php">← Dashboard</a>
    </span>
  </div>
</header>

<main class="wrap">
  <h1>Kehadiran Event</h1>
  <p class="subtitle">Scan QR atau cari manual untuk mencatat kehadiran peserta.</p>

  <form class="event-picker" method="GET">
    <select name="event" onchange="this.form.submit()" aria-label="Pilih event">
      <?php if (empty($events)): ?>
        <option value="">Belum ada event</option>
      <?php else: ?>
        <?php foreach ($events as $ev): ?>
          <option value="<?= htmlspecialchars($ev['slug']) ?>" <?= $ev['slug'] === $eventSlug ? 'selected' : '' ?>><?= htmlspecialchars($ev['name']) ?></option>
        <?php endforeach; ?>
      <?php endif; ?>
    </select>
  </form>

  <?php if ($eventSlug === ''): ?>
    <p class="empty">Belum ada event untuk brand ini. Buat event terlebih dahulu di halaman Kelola Event.</p>
  <?php else: ?>

  <section class="counter-bar" aria-live="polite">
    <div>
      <div class="counter-num"><span id="counterHadir"><?= (int)$totalHadir ?></span> dari <span id="counterTotal"><?= (int)$totalRegistrants ?></span> hadir</div>
      <div class="counter-label">Update real-time saat check-in dilakukan</div>
    </div>
    <div class="counter-bar-track">
      <div class="counter-bar-fill" id="counterBarFill" style="width: <?= $totalRegistrants > 0 ? round($totalHadir / $totalRegistrants * 100) : 0 ?>%"></div>
    </div>
  </section>

  <div class="actions-row">
    <button type="button" class="btn btn-gold" id="openScannerBtn">📷 Scan QR Full-Screen</button>
    <button type="button" class="btn btn-outline" id="finalizeBtn">✅ Finalisasi Kehadiran</button>
  </div>

  <div class="toolbar">
    <input type="search" id="searchInput" placeholder="Cari nama atau nomor HP..." aria-label="Cari pendaftar">
  </div>

  <div class="list-card" id="registrantList">
    <?php if (empty($registrants)): ?>
      <p class="empty">Belum ada pendaftar untuk event ini.</p>
    <?php else: ?>
      <?php foreach ($registrants as $r): ?>
        <?php
          $status = $r['attendance_status'] ?: 'tidak_hadir';
          $waLink = whatsapp_link_att($r['whatsapp'] ?? '');
          $searchBlob = strtolower($r['name'] . ' ' . $r['whatsapp']);
        ?>
        <div class="row-item" data-registrant-id="<?= (int)$r['registrant_id'] ?>" data-search="<?= htmlspecialchars($searchBlob) ?>">
          <div>
            <div class="row-name"><?= htmlspecialchars($r['name']) ?></div>
            <div class="row-meta">
              <?php if ($waLink): ?>
                <a href="<?= htmlspecialchars($waLink) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($r['whatsapp']) ?></a>
              <?php else: ?>
                <?= htmlspecialchars($r['whatsapp']) ?>
              <?php endif; ?>
              <?= $r['referrer_name'] ? ' · Diundang oleh ' . htmlspecialchars($r['referrer_name']) : '' ?>
            </div>
          </div>
          <div>
            <span class="status-badge <?= htmlspecialchars($status) ?>" data-status-badge><?= htmlspecialchars($statusLabel[$status] ?? $status) ?></span>
          </div>
          <div>
            <button type="button" class="checkin-btn" data-checkin-btn <?= $status === 'hadir' ? 'disabled' : '' ?>>
              <?= $status === 'hadir' ? 'Sudah Hadir' : 'Check-in' ?>
            </button>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <?php endif; ?>
</main>

<div id="scannerPanel">
  <div class="scanner-head">
    <strong>Scan QR Kehadiran</strong>
    <button type="button" class="btn btn-outline" id="closeScannerBtn">✕ Tutup</button>
  </div>
  <div id="qrReader"></div>
  <div class="scanner-result" id="scannerResult">Arahkan kamera ke QR peserta.</div>
</div>

<div class="toast" id="toast"></div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(function () {
  var EVENT_SLUG = <?= json_encode($eventSlug) ?>;
  var CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token']) ?>;
  var STATUS_LABEL = <?= json_encode($statusLabel) ?>;

  var toastEl = document.getElementById('toast');
  function showToast(message, ok) {
    toastEl.textContent = message;
    toastEl.className = 'toast ' + (ok ? 'ok' : 'err');
    toastEl.style.display = 'block';
    clearTimeout(showToast._t);
    showToast._t = setTimeout(function () { toastEl.style.display = 'none'; }, 3200);
  }

  function updateCounters(hadir, total) {
    document.getElementById('counterHadir').textContent = hadir;
    document.getElementById('counterTotal').textContent = total;
    var pct = total > 0 ? Math.round((hadir / total) * 100) : 0;
    document.getElementById('counterBarFill').style.width = pct + '%';
  }

  function updateRow(registrantId, status) {
    var row = document.querySelector('.row-item[data-registrant-id="' + registrantId + '"]');
    if (!row) return;
    var badge = row.querySelector('[data-status-badge]');
    badge.className = 'status-badge ' + status;
    badge.textContent = STATUS_LABEL[status] || status;
    var btn = row.querySelector('[data-checkin-btn]');
    if (status === 'hadir') {
      btn.disabled = true;
      btn.textContent = 'Sudah Hadir';
    }
  }

  function submitCheckin(payload, onDone) {
    payload.event = EVENT_SLUG;
    payload.csrf_token = CSRF_TOKEN;
    fetch('/api/attendance-checkin.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data.success) {
          showToast(data.message, true);
          updateRow(data.registrant_id, 'hadir');
          updateCounters(data.total_hadir, data.total_registrants);
        } else {
          showToast(data.message || 'Gagal memproses check-in.', false);
          if (data.duplicate && data.registrant_id) {
            updateRow(data.registrant_id, 'hadir');
          }
        }
        if (onDone) onDone(data);
      })
      .catch(function () {
        showToast('Gagal terhubung ke server. Coba lagi.', false);
        if (onDone) onDone(null);
      });
  }

  // ---- Manual check-in buttons ----
  document.querySelectorAll('[data-checkin-btn]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var row = btn.closest('.row-item');
      var registrantId = parseInt(row.getAttribute('data-registrant-id'), 10);
      btn.disabled = true;
      submitCheckin({ registrant_id: registrantId, check_in_method: 'manual_admin' }, function () {
        btn.disabled = (btn.textContent.trim() === 'Sudah Hadir');
      });
    });
  });

  // ---- Live search ----
  var searchInput = document.getElementById('searchInput');
  if (searchInput) {
    searchInput.addEventListener('input', function () {
      var keyword = searchInput.value.trim().toLowerCase();
      document.querySelectorAll('.row-item').forEach(function (row) {
        var haystack = row.getAttribute('data-search') || '';
        row.style.display = haystack.indexOf(keyword) !== -1 ? '' : 'none';
      });
    });
  }

  // ---- QR scanner (full-screen) ----
  var scannerPanel = document.getElementById('scannerPanel');
  var scannerResult = document.getElementById('scannerResult');
  var html5QrCode = null;
  var scanLocked = false;

  document.getElementById('openScannerBtn').addEventListener('click', function () {
    if (typeof Html5Qrcode === 'undefined') {
      showToast('Library scanner gagal dimuat. Periksa koneksi internet.', false);
      return;
    }
    scannerPanel.classList.add('open');
    html5QrCode = new Html5Qrcode('qrReader');
    html5QrCode.start(
      { facingMode: 'environment' },
      { fps: 10, qrbox: { width: 260, height: 260 } },
      function onScanSuccess(decodedText) {
        if (scanLocked) return;
        scanLocked = true;
        scannerResult.textContent = 'Memproses...';
        scannerResult.className = 'scanner-result';
        submitCheckin({ qr_token: decodedText, check_in_method: 'qr_scan' }, function (data) {
          if (data && data.success) {
            scannerResult.textContent = data.message;
            scannerResult.className = 'scanner-result ok';
          } else {
            scannerResult.textContent = (data && data.message) || 'Gagal memproses QR.';
            scannerResult.className = 'scanner-result err';
          }
          setTimeout(function () {
            scanLocked = false;
            scannerResult.textContent = 'Arahkan kamera ke QR peserta.';
            scannerResult.className = 'scanner-result';
          }, 2200);
        });
      },
      function onScanFailure() { /* diabaikan — dipanggil terus-menerus saat tidak ada QR di frame */ }
    ).catch(function () {
      scannerResult.textContent = 'Gagal mengakses kamera. Periksa izin kamera browser.';
      scannerResult.className = 'scanner-result err';
    });
  });

  function closeScanner() {
    scannerPanel.classList.remove('open');
    if (html5QrCode) {
      html5QrCode.stop().then(function () {
        html5QrCode.clear();
        html5QrCode = null;
      }).catch(function () { html5QrCode = null; });
    }
  }
  document.getElementById('closeScannerBtn').addEventListener('click', closeScanner);

  // ---- Finalisasi kehadiran ----
  document.getElementById('finalizeBtn').addEventListener('click', function () {
    if (!confirm('Finalisasi kehadiran untuk event ini sekarang? Ini akan menghitung ulang status reward-eligible pengundang berdasarkan data hadir saat ini.')) {
      return;
    }
    var btn = this;
    btn.disabled = true;
    fetch('/api/attendance-finalize.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ event: EVENT_SLUG, csrf_token: CSRF_TOKEN, min_hadir: 1 })
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        btn.disabled = false;
        showToast(data.message || (data.success ? 'Finalisasi selesai.' : 'Gagal finalisasi.'), !!data.success);
      })
      .catch(function () {
        btn.disabled = false;
        showToast('Gagal terhubung ke server. Coba lagi.', false);
      });
  });
})();
</script>
</body>
</html>
