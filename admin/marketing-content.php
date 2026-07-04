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

$pageTitle = $eventNotFound ? 'Event Tidak Ditemukan' : 'Konten Marketing — ' . $event['name'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle) ?></title>
<style><?= get_theme_css_vars($brand) ?></style>
<style>
  :root {
    --bg: #0B0B0A;
    --surface: #171716;
    --text-strong: #F5F1E6;
    --text-muted: #B8B2A4;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    min-height: 100vh;
    background:
      radial-gradient(circle at 82% 6%, color-mix(in srgb, var(--brand-primary) 20%, transparent), transparent 30vw),
      linear-gradient(135deg, var(--bg), #10100F);
    color: var(--text-strong);
    font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  }
  .mkt-wrap { max-width: 1100px; margin: 0 auto; padding: 24px 20px 60px; }
  .mkt-hero { display: flex; flex-wrap: wrap; gap: 16px; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; }
  .mkt-hero h1 { font-size: 26px; margin: 4px 0 6px; color: var(--text-strong, #F5F1E6); }
  .mkt-hero p { color: var(--text-muted, #B8B2A4); margin: 0; font-size: 14px; }
  .mkt-eyebrow { font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: var(--brand-primary, #C9A84C); }
  .mkt-panel { background: rgba(255,255,255,0.03); border: 1px solid rgba(201,168,76,0.25); border-radius: 24px; padding: 24px; backdrop-filter: blur(6px); margin-bottom: 28px; }
  .mkt-field { margin-bottom: 18px; }
  .mkt-field label { font-size: 13px; color: var(--text-muted, #B8B2A4); display:block; margin-bottom:8px; }
  .mkt-field .required { color: #e0a25a; }
  .mkt-panel input[type=text], .mkt-panel textarea {
    width: 100%; border-radius: 14px; padding: 12px 14px;
    background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.12);
    color: #fff; font-size: 14px; box-sizing: border-box; font-family: inherit;
  }
  .mkt-panel textarea { min-height: 80px; resize: vertical; }
  .mkt-field-error { color: #e08a8a; font-size: 12px; margin-top: 6px; display:none; }
  .mkt-actions { display: flex; gap: 12px; margin-top: 6px; flex-wrap: wrap; }
  .mkt-btn { border: none; border-radius: 14px; padding: 12px 22px; font-size: 14px; font-weight: 600; cursor: pointer; transition: transform .15s ease; }
  .mkt-btn-primary { background: linear-gradient(135deg, var(--brand-primary,#C9A84C), var(--brand-soft,#E8D5A3)); color: #1A1A1A; }
  .mkt-btn-primary:disabled { opacity: .5; cursor: not-allowed; }
  .mkt-btn-primary:not(:disabled):hover { transform: translateY(-1px); }
  .mkt-btn-ghost { background: transparent; border: 1px solid rgba(255,255,255,0.2); color: #fff; text-decoration: none; display: inline-block; }
  .mkt-link-box { display:flex; gap:8px; align-items:center; margin-top:14px; flex-wrap:wrap; font-size: 13px; color: var(--text-muted, #B8B2A4); }
  .mkt-link-box code { background: rgba(0,0,0,0.3); padding: 6px 10px; border-radius: 10px; color: #fff; word-break: break-all; }
  .mkt-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 18px; }
  .mkt-card { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; padding: 20px; display: flex; flex-direction: column; gap: 10px; }
  .mkt-style-badge { align-self: flex-start; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; background: rgba(201,168,76,0.15); color: var(--brand-primary,#C9A84C); padding: 4px 10px; border-radius: 999px; }
  .mkt-card h3 { margin: 0; font-size: 17px; color: #fff; line-height: 1.35; }
  .mkt-card .sub { font-size: 13px; color: var(--text-muted,#B8B2A4); margin:0; }
  .mkt-card .desc { font-size: 13px; color: #ddd; margin: 0; line-height: 1.55; white-space: pre-line; }
  .mkt-card .cta { font-size: 13px; font-weight: 700; color: var(--brand-primary,#C9A84C); }
  .mkt-card-actions { display:flex; gap:8px; margin-top: 6px; }
  .mkt-copy-btn { font-size: 12px; padding: 8px 12px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.15); background: rgba(255,255,255,0.05); color: #fff; cursor: pointer; }
  .mkt-copy-btn.copied { background: rgba(76,201,130,0.2); border-color: rgba(76,201,130,0.4); }
  .mkt-empty, .mkt-error { text-align:center; padding: 40px 20px; color: var(--text-muted,#B8B2A4); }
  .mkt-error { color: #e08a8a; }
  .mkt-loading { text-align:center; padding: 30px; color: var(--text-muted,#B8B2A4); font-size: 14px; }
  @media (max-width: 480px) {
    .mkt-wrap { padding: 18px 14px 44px; }
    .mkt-panel { padding: 18px; border-radius: 20px; }
    .mkt-grid { grid-template-columns: 1fr; }
    .mkt-btn { width: 100%; }
    .mkt-card-actions { flex-direction: column; }
  }
</style>
</head>
<body>
<div class="mkt-wrap">

  <?php if ($eventNotFound): ?>
    <div class="mkt-panel mkt-empty">
      <p>Event tidak ditemukan. Silakan kembali ke daftar event.</p>
      <a class="mkt-btn mkt-btn-primary" href="events.php">Kembali ke Kelola Event</a>
    </div>
  <?php else: ?>

    <div class="mkt-hero">
      <div>
        <span class="mkt-eyebrow">Marketing Content Generator</span>
        <h1>Konten Marketing — <?= htmlspecialchars($event['name']) ?></h1>
        <p>Generate 5 variasi copywriting siap-tempel, selalu sesuai judul event yang kamu tentukan.</p>
      </div>
      <a class="mkt-btn mkt-btn-ghost" href="events.php">Kembali ke Kelola Event</a>
    </div>

    <div class="mkt-panel">
      <div class="mkt-field">
        <label for="mkt-event-title">Judul Event <span class="required">*wajib diisi</span></label>
        <input type="text" id="mkt-event-title" maxlength="150"
               value="<?= htmlspecialchars($event['name']) ?>"
               placeholder="Contoh: Rahasia Cuan Emas — Strategi Anti Inflasi 2026">
        <div class="mkt-field-error" id="mkt-title-error">Judul Event wajib diisi agar konten tidak keluar konteks.</div>
      </div>

      <div class="mkt-field">
        <label for="mkt-context">Konteks tambahan (opsional) — keunggulan acara, target audiens, promo khusus.</label>
        <textarea id="mkt-context" maxlength="500" placeholder="Contoh: acara ini untuk pemula, tekankan sisi edukasi dan komunitas."></textarea>
      </div>

      <div class="mkt-actions">
        <button type="button" class="mkt-btn mkt-btn-primary" id="mkt-generate-btn">Generate 5 Variasi Copywriting</button>
      </div>

      <div class="mkt-link-box" id="mkt-link-box" style="display:none;">
        <span>Link undangan yang dipakai di semua CTA:</span>
        <code id="mkt-invite-link"></code>
        <button type="button" class="mkt-copy-btn" id="mkt-copy-link-btn">Salin Link</button>
      </div>
    </div>

    <div id="mkt-result-area"></div>

  <?php endif; ?>
</div>

<?php if (!$eventNotFound): ?>
<script>
(function () {
  const eventSlug   = <?= json_encode($eventSlug) ?>;
  const csrfToken   = <?= json_encode($_SESSION['csrf_token']) ?>;

  const titleInput   = document.getElementById('mkt-event-title');
  const titleError   = document.getElementById('mkt-title-error');
  const contextInput = document.getElementById('mkt-context');
  const generateBtn  = document.getElementById('mkt-generate-btn');
  const resultArea   = document.getElementById('mkt-result-area');
  const linkBox      = document.getElementById('mkt-link-box');
  const inviteLinkEl = document.getElementById('mkt-invite-link');
  const copyLinkBtn  = document.getElementById('mkt-copy-link-btn');

  let currentInviteLink = '';

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
    const div = document.createElement('div');
    div.className = 'mkt-panel mkt-loading';
    div.textContent = 'Sedang membuat 5 variasi copywriting…';
    resultArea.appendChild(div);
  }

  function renderError(message) {
    resultArea.textContent = '';
    const div = document.createElement('div');
    div.className = 'mkt-panel mkt-error';
    div.textContent = message;
    resultArea.appendChild(div);
  }

  function renderVariations(variations, inviteLink) {
    currentInviteLink = inviteLink;
    inviteLinkEl.textContent = inviteLink;
    linkBox.style.display = 'flex';

    resultArea.textContent = '';
    const grid = document.createElement('div');
    grid.className = 'mkt-grid';

    variations.forEach(function (v) {
      const card = document.createElement('article');
      card.className = 'mkt-card';

      const badge = document.createElement('span');
      badge.className = 'mkt-style-badge';
      badge.textContent = v.style;
      card.appendChild(badge);

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
        desc.textContent = v.description;
        card.appendChild(desc);
      }

      const cta = document.createElement('p');
      cta.className = 'cta';
      cta.textContent = '👉 ' + v.cta_text;
      card.appendChild(cta);

      const actions = document.createElement('div');
      actions.className = 'mkt-card-actions';

      const copyBtn = document.createElement('button');
      copyBtn.type = 'button';
      copyBtn.className = 'mkt-copy-btn';
      copyBtn.textContent = 'Salin Konten + Link';
      copyBtn.addEventListener('click', function () {
        const fullText = [
          v.headline,
          v.subheadline,
          v.description,
          '',
          v.cta_text + ': ' + inviteLink
        ].filter(Boolean).join('\n\n');

        copyText(fullText).then(function () {
          copyBtn.textContent = 'Tersalin!';
          copyBtn.classList.add('copied');
          setTimeout(function () {
            copyBtn.textContent = 'Salin Konten + Link';
            copyBtn.classList.remove('copied');
          }, 1600);
        });
      });

      actions.appendChild(copyBtn);
      card.appendChild(actions);
      grid.appendChild(card);
    });

    resultArea.appendChild(grid);
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
    generateBtn.textContent = 'Memproses…';
    renderLoading();

    try {
      const res = await fetch('../api/generate_marketing_content.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          event: eventSlug,
          event_title: eventTitle,
          context: contextInput.value.trim(),
          csrf_token: csrfToken,
        }),
      });
      const result = await res.json();

      if (result.success) {
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
      setTimeout(function () { copyLinkBtn.textContent = 'Salin Link'; }, 1500);
    });
  });
})();
</script>
<?php endif; ?>
</body>
</html>
