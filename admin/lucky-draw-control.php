<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
start_secure_session();

$brand = require_admin_for_brand(get_current_brand());
$brandId = (int)$brand['id'];
$pdo = get_db();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$stmt = $pdo->prepare('SELECT id, slug, name FROM events WHERE brand_id = ? ORDER BY (slug = ?) DESC, created_at DESC');
$stmt->execute([$brandId, $brand['default_event_slug']]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

$requestedEvent = trim((string)($_GET['event'] ?? ''));
$event = null;
foreach ($events as $candidate) {
    if ($requestedEvent !== '' && ($candidate['slug'] === $requestedEvent || (string)$candidate['id'] === $requestedEvent)) {
        $event = $candidate;
        break;
    }
}
if (!$event && !empty($events)) {
    $event = $events[0];
}

$eligibleCount = 0;
if ($event) {
    $stmt = $pdo->prepare('
        SELECT COUNT(*)
        FROM event_attendance ea
        JOIN leads l ON l.id = ea.registrant_id
        LEFT JOIN lucky_draw_winners ldw
            ON ldw.event_id = ea.event_id
            AND ldw.registrant_id = ea.registrant_id
            AND ldw.status = "confirmed"
        WHERE ea.event_id = ?
            AND ea.attendance_status = "hadir"
            AND l.brand_id = ?
            AND ldw.id IS NULL
    ');
    $stmt->execute([(int)$event['id'], $brandId]);
    $eligibleCount = (int)$stmt->fetchColumn();
}

$logoPath = $brand['logo_path'] ? '..' . $brand['logo_path'] : '../assets/logo.png';
$displayUrl = $event ? '/lucky-draw-display.php?event=' . urlencode($event['slug']) : '#';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kontrol Undian Kehadiran - <?= htmlspecialchars($brand['name'], ENT_QUOTES, 'UTF-8') ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
<style>
<?= get_theme_css_vars($brand) ?>
:root{--gold:var(--brand-primary);--gold-soft:var(--brand-soft);--charcoal:var(--brand-charcoal);--bg:#111;--surface:#1b1b1b;--surface-2:#232323;--line:rgba(255,255,255,.11);--text:#f8f4e8;--muted:#b9b1a1;--danger:#ff7777;--ok:#69d391}
*{box-sizing:border-box}body{margin:0;background:linear-gradient(135deg,#0b0b0c,var(--charcoal) 48%,#101010);color:var(--text);font-family:Inter,system-ui,-apple-system,sans-serif;min-height:100vh}.topbar{border-bottom:1px solid var(--line);background:rgba(12,12,12,.82);backdrop-filter:blur(14px);position:sticky;top:0;z-index:10}.top-inner{max-width:1180px;margin:0 auto;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:16px}.brand{display:flex;align-items:center;gap:12px;min-width:0}.brand img{width:38px;height:38px;object-fit:contain}.brand strong{font-size:15px}.brand span{display:block;color:var(--muted);font-size:12px;margin-top:2px}.nav{display:flex;gap:10px;flex-wrap:wrap}.nav a{color:var(--text);text-decoration:none;border:1px solid var(--line);border-radius:8px;padding:9px 12px;font-size:13px;font-weight:800;background:rgba(255,255,255,.04)}main{max-width:1180px;margin:0 auto;padding:28px 20px 44px}.hero{display:grid;grid-template-columns:1.05fr .95fr;gap:22px;align-items:stretch}.panel{background:rgba(28,28,28,.78);border:1px solid var(--line);border-radius:8px;padding:22px;box-shadow:0 22px 70px rgba(0,0,0,.32)}.eyebrow{color:var(--gold-soft);font-size:12px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;margin-bottom:10px}h1{font-family:"Playfair Display",serif;font-size:clamp(32px,4vw,56px);line-height:1;margin:0 0 12px}p{color:var(--muted);line-height:1.6;margin:0}.stat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:20px}.stat{border:1px solid var(--line);border-radius:8px;background:rgba(255,255,255,.04);padding:16px}.stat b{display:block;font-size:30px;color:var(--gold);line-height:1}.stat span{display:block;color:var(--muted);font-size:12px;font-weight:800;margin-top:8px}.form-grid{display:grid;gap:16px}.field label{display:block;font-size:12px;font-weight:900;color:var(--gold-soft);margin-bottom:8px;text-transform:uppercase;letter-spacing:.05em}.field input,.field select{width:100%;border:1px solid var(--line);border-radius:8px;background:#101010;color:var(--text);padding:13px 14px;font:inherit;font-weight:700}.field input:focus,.field select:focus{outline:none;border-color:var(--gold);box-shadow:0 0 0 4px color-mix(in srgb,var(--gold) 16%,transparent)}.duration{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}.choice{position:relative}.choice input{position:absolute;opacity:0}.choice span{display:flex;align-items:center;justify-content:center;min-height:42px;border:1px solid var(--line);border-radius:8px;background:rgba(255,255,255,.04);font-weight:900;cursor:pointer}.choice input:checked+span{background:linear-gradient(135deg,var(--gold),var(--gold-soft));color:#111;border-color:transparent}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:6px}.btn{border:none;border-radius:8px;padding:13px 16px;font-weight:900;cursor:pointer;font-family:inherit;min-height:46px}.btn:disabled{opacity:.45;cursor:not-allowed}.primary{background:linear-gradient(135deg,var(--gold),var(--gold-soft));color:#111}.ghost{background:rgba(255,255,255,.06);color:var(--text);border:1px solid var(--line);text-decoration:none;display:inline-flex;align-items:center}.danger{background:rgba(255,119,119,.13);color:#ffd1d1;border:1px solid rgba(255,119,119,.34)}.success{background:rgba(105,211,145,.14);color:#d9ffe7;border:1px solid rgba(105,211,145,.34)}.status-box{min-height:278px;display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center;gap:14px}.status-pill{display:inline-flex;border:1px solid var(--line);border-radius:999px;padding:8px 12px;color:var(--gold-soft);font-weight:900;font-size:12px;text-transform:uppercase;letter-spacing:.08em}.big-number{font-size:72px;line-height:.95;font-weight:900;color:var(--gold)}.winner-list{display:grid;gap:10px;width:100%;margin-top:8px}.winner{border:1px solid color-mix(in srgb,var(--gold) 38%,transparent);background:color-mix(in srgb,var(--gold) 10%,rgba(255,255,255,.04));border-radius:8px;padding:13px 14px;font-size:18px;font-weight:900}.message{margin-top:16px;border:1px solid var(--line);border-radius:8px;padding:12px 14px;color:var(--muted);display:none}.message.show{display:block}.message.ok{color:#d9ffe7;border-color:rgba(105,211,145,.34);background:rgba(105,211,145,.1)}.message.err{color:#ffd1d1;border-color:rgba(255,119,119,.34);background:rgba(255,119,119,.1)}@media(max-width:860px){.hero{grid-template-columns:1fr}.top-inner{align-items:flex-start;flex-direction:column}.duration,.stat-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:520px){main{padding:20px 14px 34px}.panel{padding:18px}.stat-grid,.duration{grid-template-columns:1fr}.actions .btn,.actions .ghost{width:100%;justify-content:center}.big-number{font-size:54px}}
</style>
</head>
<body>
<header class="topbar">
  <div class="top-inner">
    <div class="brand">
      <img src="<?= htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') ?>" alt="">
      <div><strong><?= htmlspecialchars($brand['name'], ENT_QUOTES, 'UTF-8') ?></strong><span>Kontrol Undian Kehadiran</span></div>
    </div>
    <nav class="nav">
      <a href="/admin/dashboard.php">Dashboard</a>
      <a href="/admin/event-attendance.php<?= $event ? '?event=' . urlencode($event['slug']) : '' ?>">Kehadiran</a>
      <a href="<?= htmlspecialchars($displayUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Buka Display</a>
    </nav>
  </div>
</header>
<main>
<?php if (!$event): ?>
  <section class="panel"><h1>Belum ada event</h1><p>Buat event dulu sebelum menjalankan undian kehadiran.</p></section>
<?php else: ?>
  <section class="hero">
    <div class="panel">
      <div class="eyebrow">Event aktif</div>
      <h1><?= htmlspecialchars($event['name'], ENT_QUOTES, 'UTF-8') ?></h1>
      <p>Pool undian memakai peserta yang sudah hadir, lalu otomatis mengecualikan peserta yang pernah menang dan sudah confirmed pada event yang sama.</p>
      <div class="stat-grid">
        <div class="stat"><b id="eligibleCount"><?= (int)$eligibleCount ?></b><span>Eligible sekarang</span></div>
        <div class="stat"><b id="sessionState">Idle</b><span>Status sesi</span></div>
        <div class="stat"><b id="timer">-</b><span>Sisa waktu</span></div>
      </div>
      <div id="message" class="message"></div>
    </div>

    <div class="panel">
      <form id="drawForm" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="event" value="<?= htmlspecialchars($event['slug'], ENT_QUOTES, 'UTF-8') ?>">
        <div class="field">
          <label for="prizeName">Nama Hadiah</label>
          <input id="prizeName" name="prize_name" maxlength="190" placeholder="Contoh: Logam Mulia 1 gram" required>
        </div>
        <div class="field">
          <label for="winnersCount">Jumlah Pemenang</label>
          <input id="winnersCount" name="winners_count" type="number" min="1" max="50" value="1" required>
        </div>
        <div class="field">
          <label>Durasi Animasi</label>
          <div class="duration">
            <label class="choice"><input type="radio" name="duration_preset" value="5"><span>5 detik</span></label>
            <label class="choice"><input type="radio" name="duration_preset" value="10" checked><span>10 detik</span></label>
            <label class="choice"><input type="radio" name="duration_preset" value="15"><span>15 detik</span></label>
            <label class="choice"><input type="radio" name="duration_preset" value="custom"><span>Custom</span></label>
          </div>
        </div>
        <div class="field" id="customDurationWrap" style="display:none">
          <label for="customDuration">Custom Durasi Detik</label>
          <input id="customDuration" name="custom_duration" type="number" min="3" max="120" value="20">
        </div>
        <div class="actions">
          <button class="btn primary" type="submit" id="startBtn">Mulai Undian</button>
          <a class="btn ghost" href="<?= htmlspecialchars($displayUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Buka Layar Publik</a>
        </div>
      </form>
    </div>
  </section>

  <section class="panel" style="margin-top:22px">
    <div class="status-box">
      <div class="status-pill" id="statusPill">Menunggu sesi</div>
      <div class="big-number" id="statusHeadline"><?= (int)$eligibleCount ?></div>
      <p id="statusText">Peserta eligible siap diundi.</p>
      <div class="winner-list" id="winnerList"></div>
      <div class="actions" id="decisionActions" style="display:none">
        <button class="btn success" type="button" id="confirmBtn">Konfirmasi</button>
        <button class="btn danger" type="button" id="voidBtn">Batalkan / Undo</button>
      </div>
    </div>
  </section>
<?php endif; ?>
</main>
<?php if ($event): ?>
<script>
const EVENT_SLUG = <?= json_encode($event['slug']) ?>;
const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token']) ?>;
let activeSessionId = null;

const els = {
  form: document.getElementById('drawForm'),
  eligible: document.getElementById('eligibleCount'),
  state: document.getElementById('sessionState'),
  timer: document.getElementById('timer'),
  msg: document.getElementById('message'),
  start: document.getElementById('startBtn'),
  statusPill: document.getElementById('statusPill'),
  headline: document.getElementById('statusHeadline'),
  text: document.getElementById('statusText'),
  winners: document.getElementById('winnerList'),
  actions: document.getElementById('decisionActions'),
  confirm: document.getElementById('confirmBtn'),
  void: document.getElementById('voidBtn'),
  customWrap: document.getElementById('customDurationWrap')
};

function showMessage(text, type) {
  els.msg.textContent = text;
  els.msg.className = 'message show ' + (type || '');
}

function durationValue() {
  const selected = document.querySelector('input[name="duration_preset"]:checked').value;
  return selected === 'custom' ? Number(document.getElementById('customDuration').value || 10) : Number(selected);
}

document.querySelectorAll('input[name="duration_preset"]').forEach(input => {
  input.addEventListener('change', () => {
    els.customWrap.style.display = input.value === 'custom' && input.checked ? 'block' : 'none';
  });
});

async function apiPost(url, payload) {
  const res = await fetch(url, {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(payload)
  });
  return res.json();
}

async function pollStatus() {
  try {
    const res = await fetch('/api/lucky-draw-status.php?event=' + encodeURIComponent(EVENT_SLUG), {cache: 'no-store'});
    const data = await res.json();
    if (data.success === false) {
      showMessage(data.message || 'Gagal membaca status.', 'err');
      return;
    }

    els.state.textContent = data.status;
    els.actions.style.display = 'none';

    if (data.status === 'idle') {
      activeSessionId = null;
      els.eligible.textContent = data.eligible_count;
      els.timer.textContent = '-';
      els.statusPill.textContent = 'Menunggu sesi';
      els.headline.textContent = data.eligible_count;
      els.text.textContent = 'Peserta eligible siap diundi.';
      els.winners.innerHTML = '';
      els.start.disabled = data.eligible_count < 1;
      return;
    }

    activeSessionId = data.session_id;
    if (data.status === 'drawing') {
      els.timer.textContent = '...';
      els.statusPill.textContent = 'Undian berjalan';
      els.headline.textContent = 'Live';
      els.text.textContent = 'Pemenang sudah ditentukan di server dan akan terbuka saat waktunya.';
      els.winners.innerHTML = '';
      els.start.disabled = true;
      return;
    }

    if (data.status === 'revealed') {
      els.timer.textContent = '0s';
      els.statusPill.textContent = 'Pemenang tampil';
      els.headline.textContent = data.prize_name || 'Hadiah';
      els.text.textContent = 'Silakan konfirmasi untuk mengunci, atau undo agar peserta kembali eligible.';
      els.winners.innerHTML = (data.winners || []).map(w => '<div class="winner">' + escapeHtml(w.name) + '</div>').join('');
      els.actions.style.display = 'flex';
      els.start.disabled = true;
    }
  } catch (err) {
    showMessage('Koneksi status terputus sebentar.', 'err');
  }
}

function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
}

els.form.addEventListener('submit', async (event) => {
  event.preventDefault();
  els.start.disabled = true;
  const payload = {
    csrf_token: CSRF_TOKEN,
    event: EVENT_SLUG,
    prize_name: document.getElementById('prizeName').value,
    winners_count: Number(document.getElementById('winnersCount').value || 1),
    duration_seconds: durationValue()
  };
  const data = await apiPost('/api/lucky-draw-start.php', payload);
  if (!data.success) {
    showMessage(data.message || 'Gagal memulai undian.', 'err');
    els.start.disabled = false;
    pollStatus();
    return;
  }
  showMessage('Undian dimulai. Layar publik akan sinkron otomatis.', 'ok');
  activeSessionId = data.session_id;
  pollStatus();
});

els.confirm.addEventListener('click', async () => {
  if (!activeSessionId) return;
  const data = await apiPost('/api/lucky-draw-confirm.php', {csrf_token: CSRF_TOKEN, session_id: activeSessionId});
  showMessage(data.message || (data.success ? 'Berhasil.' : 'Gagal.'), data.success ? 'ok' : 'err');
  pollStatus();
});

els.void.addEventListener('click', async () => {
  if (!activeSessionId) return;
  const data = await apiPost('/api/lucky-draw-void.php', {csrf_token: CSRF_TOKEN, session_id: activeSessionId});
  showMessage(data.message || (data.success ? 'Berhasil.' : 'Gagal.'), data.success ? 'ok' : 'err');
  pollStatus();
});

pollStatus();
setInterval(pollStatus, 1000);
</script>
<?php endif; ?>
</body>
</html>
