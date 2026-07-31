<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/bootstrap.php';

$brand = require_brand_or_404(get_current_brand());
$pdo = get_db();
$eventText = trim((string)($_GET['event'] ?? ($_GET['event_id'] ?? '')));

if ($eventText === '') {
    http_response_code(422);
    exit('Event wajib dipilih.');
}

if (ctype_digit($eventText)) {
    $stmt = $pdo->prepare('SELECT id, slug, name, event_day, event_time FROM events WHERE id = ? AND brand_id = ? LIMIT 1');
    $stmt->execute([(int)$eventText, (int)$brand['id']]);
} else {
    $stmt = $pdo->prepare('SELECT id, slug, name, event_day, event_time FROM events WHERE slug = ? AND brand_id = ? LIMIT 1');
    $stmt->execute([clean($eventText), (int)$brand['id']]);
}
$event = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$event) {
    http_response_code(404);
    exit('Event tidak ditemukan.');
}

$logoPath = $brand['logo_path'] ?: '/assets/logo.png';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Undian Kehadiran - <?= htmlspecialchars($event['name'], ENT_QUOTES, 'UTF-8') ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
<style>
<?= get_theme_css_vars($brand) ?>
:root{--accent:var(--brand-primary);--accent-soft:var(--brand-soft);--charcoal:var(--brand-charcoal);--text:#fff9ea;--muted:#cfc7b7;--panel:rgba(20,20,20,.68)}
*{box-sizing:border-box}html,body{width:100%;height:100%;overflow:hidden}body{margin:0;background:#090909;color:var(--text);font-family:Inter,system-ui,-apple-system,sans-serif}.stage{position:relative;min-height:100vh;display:grid;place-items:center;padding:28px;isolation:isolate;background:radial-gradient(circle at 50% 18%,color-mix(in srgb,var(--accent) 24%,transparent),transparent 28%),linear-gradient(140deg,#050505 0%,var(--charcoal) 48%,#101010 100%)}.stage::before{content:"";position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,.035) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.03) 1px,transparent 1px);background-size:54px 54px;mask-image:linear-gradient(to bottom,rgba(0,0,0,.72),rgba(0,0,0,.08));z-index:-2}.stage::after{content:"";position:absolute;inset:auto -10% -25% -10%;height:52vh;background:linear-gradient(180deg,transparent,color-mix(in srgb,var(--accent) 16%,transparent));filter:blur(28px);z-index:-1}.shell{width:min(1180px,100%);text-align:center}.brand{display:inline-flex;align-items:center;gap:14px;margin-bottom:26px;color:var(--accent-soft);font-weight:900;letter-spacing:.08em;text-transform:uppercase;font-size:13px}.brand img{width:52px;height:52px;object-fit:contain}.event-name{font-family:"Playfair Display",serif;font-size:clamp(31px,5.4vw,74px);line-height:.98;margin:0 0 12px;text-wrap:balance}.meta{color:var(--muted);font-weight:700;font-size:clamp(14px,1.6vw,20px);margin-bottom:34px}.draw-box{position:relative;min-height:330px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:18px;border-block:1px solid color-mix(in srgb,var(--accent) 28%,transparent);padding:36px 18px}.state-label{display:inline-flex;align-items:center;justify-content:center;border:1px solid color-mix(in srgb,var(--accent) 34%,transparent);background:color-mix(in srgb,var(--accent) 10%,rgba(255,255,255,.04));border-radius:8px;padding:9px 13px;color:var(--accent-soft);font-size:12px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.slot{width:min(920px,100%);min-height:118px;display:flex;align-items:center;justify-content:center;font-size:clamp(38px,7vw,94px);font-weight:900;line-height:1.05;text-shadow:0 20px 60px color-mix(in srgb,var(--accent) 35%,transparent);word-break:break-word}.slot.spinning{animation:bounceName .34s ease-in-out infinite}.countdown{height:76px;font-size:clamp(46px,7vw,88px);font-weight:900;color:var(--accent);line-height:1}.winners{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;width:min(980px,100%);margin:8px auto 0}.winner{border:1px solid color-mix(in srgb,var(--accent) 46%,transparent);background:linear-gradient(135deg,color-mix(in srgb,var(--accent) 18%,rgba(255,255,255,.05)),rgba(255,255,255,.06));border-radius:8px;padding:20px 18px;box-shadow:0 20px 60px rgba(0,0,0,.28);animation:winnerPop .64s cubic-bezier(.2,1.2,.2,1) both}.winner .name{font-size:clamp(26px,4.8vw,58px);font-weight:900;line-height:1.04}.winner .prize{margin-top:8px;color:var(--accent-soft);font-weight:900;font-size:clamp(14px,1.6vw,20px)}.idle-note{font-size:clamp(19px,2.2vw,28px);color:var(--muted);font-weight:700}.confetti{position:fixed;inset:0;pointer-events:none;overflow:hidden}.piece{position:absolute;top:-24px;width:10px;height:18px;background:var(--accent);animation:fall linear forwards}.piece:nth-child(3n){background:var(--accent-soft)}.piece:nth-child(4n){background:#fff7d6}.piece:nth-child(5n){border-radius:50%}@keyframes bounceName{0%,100%{transform:translateY(0) scale(1)}50%{transform:translateY(-10px) scale(1.025)}}@keyframes winnerPop{from{opacity:0;transform:translateY(24px) scale(.94)}to{opacity:1;transform:translateY(0) scale(1)}}@keyframes fall{to{transform:translateY(110vh) rotate(720deg)}}@media(max-width:620px){.stage{padding:18px}.brand{font-size:11px;margin-bottom:18px}.brand img{width:42px;height:42px}.draw-box{min-height:300px;padding-inline:4px}.slot{min-height:96px}.winners{grid-template-columns:1fr}}
</style>
</head>
<body>
<main class="stage">
  <section class="shell">
    <div class="brand">
      <img src="<?= htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') ?>" alt="">
      <span><?= htmlspecialchars($brand['name'], ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <h1 class="event-name"><?= htmlspecialchars($event['name'], ENT_QUOTES, 'UTF-8') ?></h1>
    <div class="meta"><?= htmlspecialchars(trim(($event['event_day'] ?? '') . ' ' . ($event['event_time'] ?? '')), ENT_QUOTES, 'UTF-8') ?></div>
    <div class="draw-box">
      <div class="state-label" id="stateLabel">Undian Kehadiran</div>
      <div class="slot" id="slotName">Menunggu undian dimulai...</div>
      <div class="countdown" id="countdown"></div>
      <div class="winners" id="winners"></div>
      <div class="idle-note" id="note">Layar ini akan bergerak otomatis saat admin memulai undian.</div>
    </div>
  </section>
</main>
<div class="confetti" id="confetti"></div>
<script>
const EVENT_SLUG = <?= json_encode($event['slug']) ?>;
const slot = document.getElementById('slotName');
const countdown = document.getElementById('countdown');
const winnersEl = document.getElementById('winners');
const note = document.getElementById('note');
const stateLabel = document.getElementById('stateLabel');
const confetti = document.getElementById('confetti');
let publicNames = ['Peserta RahasiaEmas'];
let spinTimer = null;
let lastStatus = 'boot';
let lastSession = null;
let drawingSince = 0;

function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
}

async function loadNames() {
  try {
    const res = await fetch('/api/lucky-draw-names.php?event=' + encodeURIComponent(EVENT_SLUG), {cache: 'no-store'});
    const data = await res.json();
    if (data.success && Array.isArray(data.names) && data.names.length) {
      publicNames = data.names;
    }
  } catch (err) {}
}

function startSpin() {
  if (spinTimer) return;
  slot.classList.add('spinning');
  winnersEl.innerHTML = '';
  note.textContent = 'Nama sedang diputar...';
  spinTimer = setInterval(() => {
    slot.textContent = publicNames[Math.floor(Math.random() * publicNames.length)];
    const hue = Math.floor(Math.random() * 28);
    slot.style.color = `hsl(${42 + hue}, 88%, ${66 + (hue % 12)}%)`;
  }, 72);
}

function stopSpin() {
  if (spinTimer) {
    clearInterval(spinTimer);
    spinTimer = null;
  }
  slot.classList.remove('spinning');
  slot.style.color = '';
}

function burstConfetti() {
  confetti.innerHTML = '';
  for (let i = 0; i < 140; i++) {
    const piece = document.createElement('i');
    piece.className = 'piece';
    piece.style.left = Math.random() * 100 + 'vw';
    piece.style.animationDuration = (2.2 + Math.random() * 2.6) + 's';
    piece.style.animationDelay = (Math.random() * .7) + 's';
    piece.style.transform = 'rotate(' + Math.floor(Math.random() * 180) + 'deg)';
    confetti.appendChild(piece);
  }
  setTimeout(() => confetti.innerHTML = '', 5200);
}

async function pollStatus() {
  try {
    const res = await fetch('/api/lucky-draw-status.php?event=' + encodeURIComponent(EVENT_SLUG), {cache: 'no-store'});
    const data = await res.json();
    if (data.success === false) return;

    if (data.status === 'idle') {
      stopSpin();
      lastSession = null;
      lastStatus = 'idle';
      stateLabel.textContent = 'Undian Kehadiran';
      slot.textContent = 'Menunggu undian dimulai...';
      countdown.textContent = '';
      winnersEl.innerHTML = '';
      note.textContent = 'Layar ini akan bergerak otomatis saat admin memulai undian.';
      await loadNames();
      return;
    }

    if (data.status === 'drawing') {
      if (lastStatus !== 'drawing') {
        await loadNames();
        startSpin();
        drawingSince = Date.now();
      }
      lastStatus = 'drawing';
      stateLabel.textContent = 'Sedang Mengundi';
      const elapsed = Math.floor((Date.now() - drawingSince) / 1000);
      const pulse = 3 - (elapsed % 4);
      countdown.textContent = elapsed >= 4 && pulse > 0 ? pulse : '';
      return;
    }

    if (data.status === 'revealed') {
      const shouldCelebrate = lastStatus !== 'revealed' || lastSession !== data.session_id;
      stopSpin();
      lastStatus = 'revealed';
      lastSession = data.session_id;
      stateLabel.textContent = 'Selamat Kepada Pemenang';
      slot.textContent = data.prize_name || 'Hadiah';
      countdown.textContent = '';
      note.textContent = 'Pemenang undian kehadiran';
      winnersEl.innerHTML = (data.winners || []).map(w => (
        '<div class="winner"><div class="name">' + escapeHtml(w.name) + '</div><div class="prize">' + escapeHtml(w.prize_name) + '</div></div>'
      )).join('');
      if (shouldCelebrate) burstConfetti();
    }
  } catch (err) {}
}

loadNames();
pollStatus();
setInterval(pollStatus, 1000);
</script>
</body>
</html>
