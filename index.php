<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/bootstrap.php';

$brand = require_brand_or_404(get_current_brand());
$brandId = (int)$brand['id'];

$refCode = isset($_GET['ref']) ? clean($_GET['ref']) : DEFAULT_REF_CODE;
$referrerName = null;
$pdo = null;

try {
    $pdo = get_db(false);
    if ($pdo) {
        $stmt = $pdo->prepare('SELECT name FROM referrers WHERE brand_id = ? AND ref_code = ?');
        $stmt->execute([$brandId, $refCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $refCode !== DEFAULT_REF_CODE) {
            $referrerName = $row['name'];
        }
        if (!$row) {
            $refCode = DEFAULT_REF_CODE;
        }
    } else {
        $refCode = DEFAULT_REF_CODE;
    }
} catch (Exception $e) {
    // Jika DB belum siap, halaman tetap tampil (form akan error saat submit, bukan saat load)
    $refCode = DEFAULT_REF_CODE;
}

$eventSettings = default_event_settings();

$trackingEvent = null;
if ($pdo) {
    try {
        $stmt = $pdo->prepare('SELECT event_day, event_time, event_location, event_speaker, event_capacity, meta_pixel_id, ga_measurement_id FROM events WHERE brand_id = ? AND slug = ?');
        $stmt->execute([$brandId, $brand['default_event_slug']]);
        $trackingEvent = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($trackingEvent) {
            $eventSettings = array_merge($eventSettings, array_filter([
                'event_day' => $trackingEvent['event_day'],
                'event_time' => $trackingEvent['event_time'],
                'event_location' => $trackingEvent['event_location'],
                'event_speaker' => $trackingEvent['event_speaker'],
                'event_capacity' => $trackingEvent['event_capacity'],
            ]));
        }
    } catch (Exception $e) {
        $trackingEvent = null;
    }
}

$logoPath = $brand['logo_path'] ? $brand['logo_path'] : 'assets/logo.png';
$disclaimerText = $brand['disclaimer_text'] ?: 'Acara ini bersifat edukatif. Kepemilikan emas dan perak adalah strategi diversifikasi aset jangka panjang. Bukan ajakan spekulasi, bukan jaminan keuntungan.';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($brand['name']) ?> — Kerja Keras Tiap Hari, Tapi Uangnya Kemana?</title>
<meta name="description" content="Jumat Malam — Webinar Gratis Edukasi Logam Mulia. Pahami cara kerja emas dan perak sebagai penyimpan nilai.">
<link rel="icon" href="<?= htmlspecialchars($logoPath) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style><?= get_theme_css_vars($brand) ?></style>
<style>
  :root {
    --charcoal: var(--brand-charcoal);
    --charcoal-soft: #242424;
    --gold: var(--brand-primary);
    --gold-soft: var(--brand-soft);
    --white: #FAFAFA;
    --muted: #9C9992;
    --danger: #D9743A;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html { scroll-behavior: smooth; }
  body {
    background: var(--charcoal);
    color: var(--white);
    font-family: 'Poppins', sans-serif;
    line-height: 1.6;
    -webkit-font-smoothing: antialiased;
    padding-bottom: 92px;
  }
  h1, h2, h3 { font-family: 'Playfair Display', serif; font-weight: 800; line-height: 1.15; }
  .wrap { max-width: 720px; margin: 0 auto; padding: 0 24px; }
  img { max-width: 100%; display: block; }

  /* ---------- HERO ---------- */
  .hero {
    min-height: 100svh;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    padding: 48px 24px;
    position: relative;
    background:
      radial-gradient(ellipse 80% 60% at 50% 0%, rgba(201,168,76,0.10), transparent),
      var(--charcoal);
  }
  .hero-logo { width: 96px; margin-bottom: 28px; opacity: 0.95; }
  .badge {
    display: inline-block;
    border: 1px solid var(--gold);
    color: var(--gold-soft);
    font-size: 13px;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    padding: 7px 18px;
    border-radius: 999px;
    margin-bottom: 24px;
  }
  .invited-by {
    color: var(--gold-soft);
    font-size: 14px;
    margin-bottom: 14px;
    font-weight: 500;
  }
  .invited-by strong { color: var(--gold); }
  .hero h1 {
    color: var(--gold);
    font-size: clamp(30px, 7vw, 48px);
    max-width: 620px;
    margin-bottom: 20px;
  }
  .hero p.sub {
    color: var(--white);
    opacity: 0.85;
    font-size: clamp(15px, 3.5vw, 18px);
    max-width: 480px;
    margin: 0 auto 24px;
  }
  .event-flyer {
    width: min(100%, 520px);
    margin: 0 auto 30px;
    border-radius: 8px;
    border: 1px solid rgba(201,168,76,0.28);
    box-shadow: 0 18px 44px rgba(0,0,0,0.32);
  }
  .btn-gold {
    display: inline-block;
    background: var(--gold);
    color: var(--charcoal);
    font-weight: 700;
    font-size: 16px;
    padding: 17px 34px;
    border-radius: 12px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
    box-shadow: 0 8px 24px rgba(201,168,76,0.25);
  }
  .btn-gold:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(201,168,76,0.35); }
  .btn-gold:active { transform: translateY(0); }
  .floating-cta {
    position: fixed;
    left: 50%;
    bottom: 18px;
    z-index: 50;
    width: min(calc(100% - 32px), 420px);
    text-align: center;
    transform: translateX(-50%);
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
    box-shadow: 0 14px 36px rgba(201,168,76,0.38), 0 8px 28px rgba(0,0,0,0.35);
    transition: opacity 0.2s ease, visibility 0.2s ease, transform 0.15s ease, box-shadow 0.15s ease;
  }
  .floating-cta:hover {
    transform: translateX(-50%) translateY(-2px);
    box-shadow: 0 18px 42px rgba(201,168,76,0.45), 0 10px 30px rgba(0,0,0,0.38);
  }
  .floating-cta:active { transform: translateX(-50%); }
  .floating-cta.is-hidden {
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transform: translateX(-50%) translateY(16px);
  }
  .scroll-hint { margin-top: 44px; color: var(--muted); font-size: 13px; }

  /* ---------- SECTIONS ---------- */
  section { padding: 72px 0; }
  .section-alt { background: var(--charcoal-soft); }
  h2.section-title {
    color: var(--gold);
    font-size: clamp(24px, 5vw, 32px);
    text-align: center;
    margin-bottom: 40px;
  }

  /* Pain point */
  .checklist { list-style: none; max-width: 540px; margin: 0 auto; }
  .checklist li {
    display: flex;
    gap: 14px;
    padding: 16px 0;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    font-size: 16px;
  }
  .checklist li:last-child { border-bottom: none; }
  .checkmark { color: var(--gold); font-size: 18px; flex-shrink: 0; }
  .section-closer {
    text-align: center;
    font-style: italic;
    color: var(--gold-soft);
    margin-top: 36px;
    font-size: 16px;
  }

  /* Cards */
  .cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
  @media (max-width: 720px) { .cards { grid-template-columns: 1fr; } }
  .card {
    background: rgba(201,168,76,0.05);
    border: 1px solid rgba(201,168,76,0.25);
    border-radius: 16px;
    padding: 28px 24px;
  }
  .card .icon {
    width: 40px; height: 40px;
    border-radius: 10px;
    background: var(--gold);
    color: var(--charcoal);
    display: flex; align-items: center; justify-content: center;
    font-weight: 800;
    margin-bottom: 18px;
  }
  .card h3 { color: var(--gold-soft); font-size: 18px; margin-bottom: 10px; }
  .card p { font-size: 14.5px; opacity: 0.85; }

  /* Event details */
  .event-card {
    border: 1px solid var(--gold);
    border-radius: 18px;
    padding: 32px;
    max-width: 520px;
    margin: 0 auto;
  }
  .event-row {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    padding: 14px 0;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    font-size: 15px;
  }
  .event-row:last-child { border-bottom: none; }
  .event-row .label { color: var(--muted); }
  .event-row .value { color: var(--white); font-weight: 600; text-align: right; }
  .urgency {
    text-align: center;
    color: var(--gold);
    font-weight: 700;
    margin-top: 28px;
    font-size: 15px;
  }

  /* Form */
  .form-card {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(201,168,76,0.2);
    border-radius: 20px;
    padding: 32px 26px;
    max-width: 480px;
    margin: 0 auto;
  }
  .field { margin-bottom: 18px; }
  .field label {
    display: block;
    font-size: 13px;
    color: var(--gold-soft);
    margin-bottom: 7px;
    font-weight: 600;
  }
  .field input {
    width: 100%;
    background: var(--charcoal);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 10px;
    padding: 14px 16px;
    color: var(--white);
    font-size: 15px;
    font-family: inherit;
    outline: none;
    transition: border-color 0.15s ease;
  }
  .field input:focus { border-color: var(--gold); }
  .field .req { color: var(--gold); }
  .field-error {
    color: var(--danger);
    font-size: 12.5px;
    margin-top: 6px;
    display: none;
  }
  .submit-btn {
    width: 100%;
    background: var(--gold);
    color: var(--charcoal);
    font-weight: 700;
    font-size: 16px;
    padding: 16px;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    margin-top: 6px;
    transition: opacity 0.15s ease;
  }
  .submit-btn:disabled { opacity: 0.6; cursor: not-allowed; }
  .microcopy {
    text-align: center;
    font-size: 12.5px;
    color: var(--muted);
    margin-top: 14px;
  }
  .form-msg {
    display: none;
    text-align: center;
    padding: 16px;
    border-radius: 12px;
    margin-bottom: 18px;
    font-size: 14.5px;
  }
  .form-msg.success { background: rgba(76,201,120,0.12); color: #7CD79A; display: block; }
  .form-msg.error { background: rgba(217,116,58,0.12); color: #E8956B; display: block; }

  /* Footer */
  footer { padding: 48px 0 60px; text-align: center; }
  footer .brand { font-family: 'Playfair Display', serif; font-weight: 800; color: var(--gold); font-size: 18px; margin-bottom: 14px; }
  footer .disclaimer { font-size: 12.5px; color: var(--muted); max-width: 480px; margin: 0 auto 18px; }
  footer .copyright { font-size: 12px; color: var(--muted); opacity: 0.7; }
</style>
<?php if (!empty($trackingEvent['meta_pixel_id'])): ?>
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '<?= htmlspecialchars($trackingEvent['meta_pixel_id']) ?>');
fbq('track', 'PageView');
</script>
<?php endif; ?>
<?php if (!empty($trackingEvent['ga_measurement_id'])): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= urlencode($trackingEvent['ga_measurement_id']) ?>"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', '<?= htmlspecialchars($trackingEvent['ga_measurement_id']) ?>');
</script>
<?php endif; ?>
</head>
<body>

<!-- ================= HERO ================= -->
<section class="hero">
  <img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($brand['name']) ?>" class="hero-logo">
  <?php if ($referrerName): ?>
    <div class="invited-by">Kamu diundang oleh <strong><?= htmlspecialchars($referrerName) ?></strong></div>
  <?php endif; ?>
  <span class="badge">Jumat Malam · Webinar Gratis</span>
  <h1>"Kerja Keras Tiap Hari, Tapi Uangnya Kemana?"</h1>
  <p class="sub">Kamu bukan salah kelola. Kamu hanya belum tahu cara menyimpan nilai uangmu dengan benar. Jumat ini, kita bahas tuntas — gratis.</p>
  <img src="assets/flyer-rahasiaemasid.jpeg" alt="Flyer webinar finansial rahasiaemas.id" class="event-flyer">
  <a href="#daftar" class="btn-gold floating-cta" id="floatingCta">Ya, Saya Mau Ikut — Daftar Sekarang</a>
  <div class="scroll-hint">↓ Scroll untuk lihat detail acara</div>
</section>

<!-- ================= PAIN POINT ================= -->
<section>
  <div class="wrap">
    <h2 class="section-title">Kamu Pernah Ngerasa Salah Satu dari Ini?</h2>
    <ul class="checklist">
      <li><span class="checkmark">✓</span> Gaji sudah naik, tapi tabungan tetap segitu-segitu aja</li>
      <li><span class="checkmark">✓</span> Kerja dari pagi sampai malam, tapi akhir bulan selalu mepet</li>
      <li><span class="checkmark">✓</span> Pengen nabung, tapi nggak tahu mulai dari mana yang bener</li>
      <li><span class="checkmark">✓</span> Pernah dengar soal emas dan perak, tapi takut salah langkah</li>
    </ul>
    <p class="section-closer">"Kalau kamu angguk-angguk baca ini — kamu ada di tempat yang tepat."</p>
  </div>
</section>

<!-- ================= WHAT YOU'LL GET ================= -->
<section class="section-alt">
  <div class="wrap">
    <h2 class="section-title">Apa yang Akan Kamu Bawa Pulang?</h2>
    <div class="cards">
      <div class="card">
        <div class="icon">1</div>
        <h3>Clarity</h3>
        <p>Kamu akan paham kenapa uang terasa selalu habis — dan apa yang selama ini terlewat dari cara kamu menyimpannya.</p>
      </div>
      <div class="card">
        <div class="icon">2</div>
        <h3>Knowledge</h3>
        <p>Kamu akan tahu cara kerja emas dan perak sebagai penyimpan nilai — bukan mitos, bukan janji manis, tapi fakta yang bisa kamu pegang.</p>
      </div>
      <div class="card">
        <div class="icon">3</div>
        <h3>First Step</h3>
        <p>Kamu akan pulang dengan langkah pertama yang konkret — aksi kecil yang bisa dimulai bahkan dengan budget terbatas.</p>
      </div>
    </div>
  </div>
</section>

<!-- ================= EVENT DETAILS ================= -->
<section>
  <div class="wrap">
    <h2 class="section-title">Detail Acara</h2>
    <div class="event-card">
      <div class="event-row"><span class="label">Hari & Tanggal</span><span class="value"><?= htmlspecialchars($eventSettings['event_day']) ?></span></div>
      <div class="event-row"><span class="label">Waktu</span><span class="value"><?= htmlspecialchars($eventSettings['event_time']) ?></span></div>
      <div class="event-row"><span class="label">Lokasi</span><span class="value"><?= htmlspecialchars($eventSettings['event_location']) ?></span></div>
      <div class="event-row"><span class="label">Biaya</span><span class="value">GRATIS</span></div>
      <div class="event-row"><span class="label">Pembicara</span><span class="value"><?= htmlspecialchars($eventSettings['event_speaker']) ?></span></div>
      <div class="event-row"><span class="label">Kapasitas</span><span class="value">Terbatas — <?= htmlspecialchars($eventSettings['event_capacity']) ?> Peserta</span></div>
    </div>
    <p class="urgency">⚡ Tempat terbatas. Daftarkan dirimu sebelum penuh.</p>
  </div>
</section>

<!-- ================= REGISTRATION FORM ================= -->
<section class="section-alt" id="daftar">
  <div class="wrap">
    <h2 class="section-title">Daftarkan Dirimu Sekarang — Gratis</h2>
    <p style="text-align:center; opacity:0.8; margin-top:-24px; margin-bottom:32px; font-size:15px;">
      Isi data di bawah ini. Tim kami akan kirim konfirmasi langsung ke WhatsApp kamu.
    </p>

    <div class="form-card">
      <div class="form-msg" id="formMsg"></div>
      <form id="regForm" novalidate>
        <input type="hidden" name="ref" value="<?= htmlspecialchars($refCode) ?>">

        <div class="field">
          <label>Nama Lengkap <span class="req">*</span></label>
          <input type="text" name="name" placeholder="Nama lengkap kamu" required minlength="3">
          <div class="field-error">Nama lengkap minimal 3 karakter.</div>
        </div>

        <div class="field">
          <label>Email <span class="req">*</span></label>
          <input type="email" name="email" placeholder="email@kamu.com" required>
          <div class="field-error">Masukkan alamat email yang valid.</div>
        </div>

        <div class="field">
          <label>Nomor WhatsApp <span class="req">*</span></label>
          <input type="tel" name="whatsapp" placeholder="08xxxxxxxxxx" required minlength="9">
          <div class="field-error">Masukkan nomor WhatsApp yang valid.</div>
        </div>

        <div class="field">
          <label>Kota Domisili <span class="req">*</span></label>
          <input type="text" name="kota" placeholder="Kota kamu" required minlength="2">
          <div class="field-error">Kota domisili wajib diisi.</div>
        </div>

        <button type="submit" class="submit-btn" id="submitBtn">Daftar Sekarang — Saya Siap Hadir</button>
        <p class="microcopy">🔒 Data kamu aman. Tidak akan kami bagikan ke pihak manapun.</p>
      </form>
    </div>
  </div>
</section>

<!-- ================= FOOTER ================= -->
<footer>
  <div class="wrap">
    <div class="brand"><?= htmlspecialchars($brand['name']) ?></div>
    <p class="disclaimer"><?= htmlspecialchars($disclaimerText) ?></p>
    <p class="copyright">© <?= date('Y') ?> <?= htmlspecialchars($brand['name']) ?></p>
  </div>
</footer>

<script>
const form = document.getElementById('regForm');
const submitBtn = document.getElementById('submitBtn');
const formMsg = document.getElementById('formMsg');
const floatingCta = document.getElementById('floatingCta');
const daftarSection = document.getElementById('daftar');

if (floatingCta && daftarSection) {
  const setFloatingCtaVisibility = () => {
    const rect = daftarSection.getBoundingClientRect();
    const isNearRegistration = rect.top <= window.innerHeight * 0.78 && rect.bottom > 0;
    floatingCta.classList.toggle('is-hidden', isNearRegistration);
    floatingCta.setAttribute('aria-hidden', isNearRegistration ? 'true' : 'false');
  };

  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
      floatingCta.classList.toggle('is-hidden', entries[0].isIntersecting);
      floatingCta.setAttribute('aria-hidden', entries[0].isIntersecting ? 'true' : 'false');
    }, {
      root: null,
      threshold: 0,
      rootMargin: '0px 0px -22% 0px',
    });
    observer.observe(daftarSection);
  } else {
    setFloatingCtaVisibility();
    window.addEventListener('scroll', setFloatingCtaVisibility, { passive: true });
    window.addEventListener('resize', setFloatingCtaVisibility);
  }
}

form.addEventListener('submit', async function (e) {
  e.preventDefault();

  // reset error states
  form.querySelectorAll('.field-error').forEach(el => el.style.display = 'none');
  formMsg.style.display = 'none';
  formMsg.className = 'form-msg';

  const data = {
    name: form.name.value.trim(),
    email: form.email.value.trim(),
    whatsapp: form.whatsapp.value.trim(),
    kota: form.kota.value.trim(),
    ref: form.ref.value,
  };

  // validasi ringan di sisi client
  let valid = true;
  if (data.name.length < 3) { showFieldError('name'); valid = false; }
  if (!/^\S+@\S+\.\S+$/.test(data.email)) { showFieldError('email'); valid = false; }
  if (data.whatsapp.replace(/\D/g,'').length < 9) { showFieldError('whatsapp'); valid = false; }
  if (data.kota.length < 2) { showFieldError('kota'); valid = false; }
  if (!valid) return;

  submitBtn.disabled = true;
  submitBtn.textContent = 'Memproses...';

  try {
    const res = await fetch('api/submit_lead.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
    });
    const result = await res.json();

    if (result.success) {
      if (typeof fbq === 'function') fbq('track', 'Lead');
      if (typeof gtag === 'function') gtag('event', 'generate_lead');
      formMsg.classList.add('success');
      formMsg.textContent = '✅ Pendaftaran kamu berhasil! Kamu akan diarahkan ke WhatsApp sebentar lagi...';
      formMsg.style.display = 'block';
      form.reset();

      if (result.redirect_whatsapp) {
        const waUrl = `https://wa.me/${result.redirect_whatsapp}?text=${encodeURIComponent(result.whatsapp_text || '')}`;
        setTimeout(() => { window.location.href = waUrl; }, 1600);
      }
    } else {
      formMsg.classList.add('error');
      formMsg.textContent = '⚠️ ' + (result.message || 'Terjadi kesalahan. Coba lagi.');
      formMsg.style.display = 'block';
      submitBtn.disabled = false;
      submitBtn.textContent = 'Daftar Sekarang — Saya Siap Hadir';
    }
  } catch (err) {
    formMsg.classList.add('error');
    formMsg.textContent = '⚠️ Gagal terhubung ke server. Periksa koneksi internet kamu.';
    formMsg.style.display = 'block';
    submitBtn.disabled = false;
    submitBtn.textContent = 'Daftar Sekarang — Saya Siap Hadir';
  }
});

function showFieldError(fieldName) {
  const field = form.querySelector(`[name="${fieldName}"]`);
  const errorEl = field.closest('.field').querySelector('.field-error');
  errorEl.style.display = 'block';
}
</script>

</body>
</html>
