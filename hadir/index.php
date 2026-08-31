<?php
/**
 * hadir/index.php
 * Halaman PUBLIK konfirmasi kehadiran self-service — pengganti Google Form.
 * Diakses lewat /hadir/{slug-event} (di-rewrite ke sini dengan ?slug=... oleh .htaccess).
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/attendance.php';
start_secure_session();

$brand = require_brand_or_404(get_current_brand());
$brandId = (int)$brand['id'];

$eventSlug = clean($_GET['slug'] ?? '');
$event = $eventSlug !== '' ? get_event_by_slug($eventSlug) : null;
if ($event && (int)$event['brand_id'] !== $brandId) {
    $event = null;
}

if (!$event) {
    http_response_code(404);
}

$windowState = $event ? attendance_window_state($event) : 'not_configured';
$justConfirmed = isset($_GET['confirmed']);

if (empty($_SESSION['attendance_csrf_token'])) {
    $_SESSION['attendance_csrf_token'] = bin2hex(random_bytes(32));
}

$logoPath = $brand['logo_path'] ? '/' . ltrim($brand['logo_path'], '/') : '/assets/logo.png';

// Kode kehadiran boleh disisipkan admin di link yang dibagikan (?code=...) supaya field
// terisi otomatis — tetap ditampilkan sebagai input biasa (bukan hidden/readonly) supaya
// peserta yang pakai link generik tanpa kode tetap bisa mengetiknya manual. Kode aslinya
// (events.attendance_code) tetap divalidasi di server, jadi ini murni kenyamanan UI.
$prefilledCode = strtoupper(clean($_GET['code'] ?? ''));

$windowMessage = [
    'not_configured' => ['title' => 'Belum Tersedia', 'body' => 'Konfirmasi kehadiran belum diaktifkan untuk event ini. Silakan hubungi panitia.'],
    'not_open' => ['title' => 'Belum Dibuka', 'body' => 'Konfirmasi kehadiran untuk event ini belum dibuka. Silakan kembali lagi pada hari acara berlangsung.'],
    'closed' => ['title' => 'Sudah Berakhir', 'body' => 'Periode konfirmasi kehadiran untuk event ini sudah berakhir. Terima kasih atas partisipasinya!'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Konfirmasi Kehadiran<?= $event ? ' — ' . htmlspecialchars($event['name']) : '' ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
<style>
  <?= get_theme_css_vars($brand) ?>
  :root {
    --bg:#0B0B0A;
    --bg-soft:#10100F;
    --border-gold:color-mix(in srgb, var(--gold) 18%, transparent);
    --gold:var(--brand-primary);
    --gold-soft:var(--brand-soft);
    --text:#F7F3E8;
    --muted:#A8A29A;
    --success:#22C55E;
    --danger:#EF4444;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    min-height: 100vh;
    background: linear-gradient(160deg, var(--bg) 0%, var(--bg-soft) 60%, #090908 100%);
    color: var(--text);
    font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px 16px;
  }
  .card {
    width: min(100%, 460px);
    background: linear-gradient(180deg, rgba(255,255,255,0.05), rgba(255,255,255,0.02));
    border: 1px solid var(--border-gold);
    border-radius: 24px;
    box-shadow: 0 24px 70px rgba(0,0,0,0.4);
    padding: 28px 24px;
  }
  .logo { display: block; width: 130px; height: auto; margin: 0 auto 18px; }
  h1 {
    font-family: "Playfair Display", Georgia, serif;
    font-size: 24px;
    text-align: center;
    margin-bottom: 6px;
  }
  .event-name { color: var(--gold-soft); text-align: center; font-weight: 800; font-size: 14px; margin-bottom: 20px; }
  .subtitle { color: var(--muted); text-align: center; font-size: 13.5px; line-height: 1.6; margin-bottom: 22px; }
  .field { margin-bottom: 16px; }
  .field label { display: block; font-size: 13px; font-weight: 800; margin-bottom: 7px; color: var(--text); }
  .field input, .field select, .field textarea {
    width: 100%;
    min-height: 50px;
    color: var(--text);
    background: rgba(255,255,255,0.045);
    border: 1px solid rgba(255,255,255,0.14);
    border-radius: 12px;
    font: inherit;
    font-size: 15px;
    outline: none;
    padding: 0 14px;
  }
  .field select {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    color-scheme: dark;
    background-color: #15150f;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23A8A29A' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 14px center;
    background-size: 16px;
    padding-right: 40px;
  }
  .field select option {
    color: var(--text);
    background-color: #15150f;
  }
  .field textarea { min-height: 80px; padding: 12px 14px; resize: vertical; }
  .field input:focus, .field select:focus, .field textarea:focus {
    border-color: color-mix(in srgb, var(--gold-soft) 42%, transparent);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--gold) 10%, transparent);
  }
  .field input[readonly] {
    color: var(--muted);
    background: rgba(255,255,255,0.02);
    border-style: dashed;
  }
  .field .helper { color: var(--muted); font-size: 12px; margin-top: 6px; }
  .section-label {
    color: var(--gold-soft);
    font-size: 12px;
    font-weight: 900;
    letter-spacing: .04em;
    text-transform: uppercase;
    margin: 18px 0 10px;
  }
  .btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-height: 50px;
    border: none;
    border-radius: 13px;
    cursor: pointer;
    font: inherit;
    font-size: 14.5px;
    font-weight: 900;
    color: #111;
    background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    margin-top: 6px;
  }
  .btn:disabled { opacity: .55; cursor: not-allowed; }
  .status-box {
    text-align: center;
    padding: 30px 10px;
  }
  .status-box .icon { font-size: 42px; margin-bottom: 14px; }
  .status-box h1 { margin-bottom: 10px; }
  .status-box p { color: var(--muted); font-size: 14px; line-height: 1.7; }
  .alert {
    border-radius: 12px;
    font-size: 13px;
    line-height: 1.6;
    padding: 12px 14px;
    margin-bottom: 16px;
    display: none;
  }
  .alert.show { display: block; }
  .alert.err { color: #FECACA; background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.28); }
  .alert.ok { color: #A7F3D0; background: rgba(34,197,94,0.12); border: 1px solid rgba(34,197,94,0.28); }
  #stepExtra, #stepFinal { display: none; }
</style>
</head>
<body>
<div class="card">
  <img class="logo" src="<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($brand['name']) ?>">

  <?php if (!$event): ?>
    <div class="status-box">
      <div class="icon">🔍</div>
      <h1>Event Tidak Ditemukan</h1>
      <p>Periksa kembali link yang kamu terima, atau hubungi panitia acara.</p>
    </div>

  <?php elseif ($justConfirmed): ?>
    <div class="status-box">
      <div class="icon">✅</div>
      <h1>Kehadiran Tercatat</h1>
      <p>Terima kasih sudah konfirmasi kehadiran di <strong><?= htmlspecialchars($event['name']) ?></strong>!</p>
    </div>

  <?php elseif ($windowState !== 'open'): ?>
    <div class="status-box">
      <div class="icon">🕒</div>
      <h1><?= htmlspecialchars($windowMessage[$windowState]['title']) ?></h1>
      <p><?= htmlspecialchars($windowMessage[$windowState]['body']) ?></p>
    </div>

  <?php else: ?>
    <h1>Konfirmasi Kehadiran</h1>
    <div class="event-name"><?= htmlspecialchars($event['name']) ?></div>
    <p class="subtitle">Isi nomor WhatsApp dan kode kehadiran untuk mulai konfirmasi.</p>

    <div class="alert err" id="alertBox"></div>

    <form id="attendanceForm" autocomplete="off">
      <div class="field" id="stepPhone">
        <label for="whatsapp">Nomor WhatsApp</label>
        <input type="tel" id="whatsapp" inputmode="numeric" placeholder="08xxxxxxxxxx" required>
        <div class="helper">Format: diawali 08, tanpa spasi/strip.</div>
      </div>

      <div class="field" id="stepCode">
        <label for="attendanceCode">Kode Kehadiran</label>
        <input type="text" id="attendanceCode" placeholder="Diberikan panitia saat acara" style="text-transform:uppercase;" value="<?= htmlspecialchars($prefilledCode) ?>">
      </div>

      <button type="button" class="btn" id="lookupBtn">Lanjutkan</button>

      <div id="stepExtra">
        <p class="subtitle" id="foundMessage" style="display:none;">Data kamu sudah kami temukan dari pendaftaran sebelumnya — tinggal lengkapi sisanya ya.</p>

        <div id="lockedFields" style="display:none;">
          <div class="field">
            <label for="lockedName">Nama Lengkap</label>
            <input type="text" id="lockedName" readonly tabindex="-1">
          </div>
          <div class="field">
            <label for="lockedEmail">Email</label>
            <input type="text" id="lockedEmail" readonly tabindex="-1">
          </div>
          <div class="field">
            <label for="lockedKota">Kota Domisili</label>
            <input type="text" id="lockedKota" readonly tabindex="-1">
          </div>
        </div>

        <div id="newFields">
          <div class="field">
            <label for="name">Nama Lengkap</label>
            <input type="text" id="name" placeholder="Nama lengkap kamu">
          </div>
          <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" placeholder="nama@email.com">
          </div>
          <div class="field">
            <label for="kota">Kota Domisili</label>
            <input type="text" id="kota" list="cityList" placeholder="Contoh: Jakarta" autocomplete="off">
          </div>
          <div class="field">
            <label for="refCodeManual">Kode Referral Pengundang (jika ada)</label>
            <input type="text" id="refCodeManual" placeholder="Opsional">
          </div>
        </div>

        <div class="section-label">Data Kehadiran</div>

        <div class="field">
          <label for="infoSource">Sumber Informasi</label>
          <select id="infoSource">
            <option value="">Pilih salah satu</option>
            <option value="media_sosial">Media Sosial</option>
            <option value="landing_page">Landing Page</option>
            <option value="group_whatsapp">Group WhatsApp</option>
            <option value="referensi">Referensi</option>
          </select>
        </div>
        <div class="field">
          <label for="participantStatus">Status Peserta</label>
          <select id="participantStatus">
            <option value="">Pilih salah satu</option>
            <option value="umum">Umum</option>
            <option value="epi_store">EPI Store</option>
            <option value="epi_channel">EPI Channel</option>
            <option value="silverchannel">Silverchannel</option>
          </select>
        </div>
        <div class="field">
          <label for="feedback">Feedback Event (opsional)</label>
          <textarea id="feedback" placeholder="Kesan, saran, atau masukan kamu untuk acara ini"></textarea>
        </div>
        <div class="field">
          <label for="nextTopicInterest">Tema Apa yang ingin Anda Pelajari berikutnya? (opsional)</label>
          <textarea id="nextTopicInterest" placeholder="Ceritakan tema yang kamu minati untuk event berikutnya"></textarea>
        </div>

        <button type="button" class="btn" id="confirmBtn">Konfirmasi Kehadiran</button>
      </div>

      <datalist id="cityList"><?= render_city_datalist_options() ?></datalist>
    </form>
  <?php endif; ?>
</div>

<?php if ($event && !$justConfirmed && $windowState === 'open'): ?>
<script>
(function () {
  var EVENT_SLUG = <?= json_encode($eventSlug) ?>;
  var CSRF_TOKEN = <?= json_encode($_SESSION['attendance_csrf_token']) ?>;

  var alertBox = document.getElementById('alertBox');
  var whatsappInput = document.getElementById('whatsapp');
  var lookupBtn = document.getElementById('lookupBtn');
  var stepExtra = document.getElementById('stepExtra');
  var foundMessage = document.getElementById('foundMessage');
  var lockedFields = document.getElementById('lockedFields');
  var newFields = document.getElementById('newFields');
  var confirmBtn = document.getElementById('confirmBtn');
  var isRegistered = false;

  function showAlert(message, ok) {
    alertBox.textContent = message;
    alertBox.className = 'alert show ' + (ok ? 'ok' : 'err');
  }
  function hideAlert() {
    alertBox.className = 'alert';
  }

  function normalizeDisplay(v) {
    return v.replace(/[^0-9]/g, '');
  }
  whatsappInput.addEventListener('input', function () {
    whatsappInput.value = normalizeDisplay(whatsappInput.value);
  });

  function postJson(payload) {
    return fetch('/api/attendance-confirm.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(function (res) { return res.json(); });
  }

  var attendanceCodeInput = document.getElementById('attendanceCode');

  lookupBtn.addEventListener('click', function () {
    hideAlert();
    var wa = whatsappInput.value.trim();
    var code = attendanceCodeInput.value.trim();

    if (wa.length < 10 || wa.length > 15 || wa.indexOf('0') !== 0) {
      showAlert('Nomor WhatsApp tidak valid. Pastikan diawali 08.', false);
      return;
    }
    if (code === '') {
      showAlert('Kode kehadiran wajib diisi.', false);
      return;
    }

    lookupBtn.disabled = true;
    postJson({ action: 'lookup', slug: EVENT_SLUG, whatsapp: wa, attendance_code: code, csrf_token: CSRF_TOKEN })
      .then(function (data) {
        lookupBtn.disabled = false;
        if (!data.success) {
          showAlert(data.message || 'Gagal memproses. Coba lagi.', false);
          return;
        }
        isRegistered = !!data.found;
        if (isRegistered) {
          foundMessage.style.display = 'block';
          lockedFields.style.display = 'block';
          newFields.style.display = 'none';
          var profile = data.profile || {};
          document.getElementById('lockedName').value = profile.name || '';
          document.getElementById('lockedEmail').value = profile.email || '';
          document.getElementById('lockedKota').value = profile.kota || '';
        } else {
          foundMessage.style.display = 'none';
          lockedFields.style.display = 'none';
          newFields.style.display = 'block';
        }
        stepExtra.style.display = 'block';
        lookupBtn.style.display = 'none';
        whatsappInput.disabled = true;
        attendanceCodeInput.disabled = true;
      })
      .catch(function () {
        lookupBtn.disabled = false;
        showAlert('Gagal terhubung ke server. Coba lagi.', false);
      });
  });

  confirmBtn.addEventListener('click', function () {
    hideAlert();
    var wa = whatsappInput.value.trim();
    var code = document.getElementById('attendanceCode').value.trim();
    var infoSource = document.getElementById('infoSource').value;
    var participantStatus = document.getElementById('participantStatus').value;
    var feedback = document.getElementById('feedback').value.trim();
    var nextTopicInterest = document.getElementById('nextTopicInterest').value.trim();

    if (code === '') {
      showAlert('Kode kehadiran wajib diisi.', false);
      return;
    }
    if (infoSource === '' || participantStatus === '') {
      showAlert('Sumber informasi dan status peserta wajib dipilih.', false);
      return;
    }

    var payload = {
      action: 'confirm', slug: EVENT_SLUG, whatsapp: wa, attendance_code: code, csrf_token: CSRF_TOKEN,
      info_source: infoSource, participant_status: participantStatus, feedback: feedback,
      next_topic_interest: nextTopicInterest
    };

    if (!isRegistered) {
      var name = document.getElementById('name').value.trim();
      var email = document.getElementById('email').value.trim();
      var kota = document.getElementById('kota').value.trim();
      if (name === '' || email === '' || kota === '') {
        showAlert('Nama lengkap, email, dan kota wajib diisi.', false);
        return;
      }
      payload.name = name;
      payload.email = email;
      payload.kota = kota;
      payload.ref_code_manual = document.getElementById('refCodeManual').value.trim();
    }

    confirmBtn.disabled = true;
    postJson(payload)
      .then(function (data) {
        if (data.success) {
          // Redirect (bukan reload form) supaya reload/tombol back tidak memicu submit ulang.
          window.location.replace(window.location.pathname + '?confirmed=1');
          return;
        }
        confirmBtn.disabled = false;
        showAlert(data.message || 'Gagal memproses. Coba lagi.', false);
      })
      .catch(function () {
        confirmBtn.disabled = false;
        showAlert('Gagal terhubung ke server. Coba lagi.', false);
      });
  });
})();
</script>
<?php endif; ?>
</body>
</html>
