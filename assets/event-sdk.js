/**
 * event-sdk.js
 * ============================================================
 * SDK ringan yang menghubungkan landing page HTML/CSS statis apapun
 * ke sistem ini — tanpa perlu PHP di dalam file HTML-nya. Brand (nama,
 * logo, tema, domain) dideteksi otomatis di server berdasarkan domain
 * yang diakses — SDK ini sendiri tidak perlu tahu brand mana yang aktif.
 *
 * CARA PAKAI (untuk siapapun yang membuat landing page baru):
 *
 * 1. Sisipkan tag ini sebelum </body> di index.html Anda:
 *    <script src="/assets/event-sdk.js" defer></script>
 *    (Jika lupa, sistem akan menyisipkannya otomatis saat upload ZIP.)
 *
 * 2. Beri form pendaftaran atribut data-rg-form, dengan field:
 *    name="name", name="email", name="whatsapp", name="kota"
 *    Contoh: <form data-rg-form> ... </form>
 *
 * 3. (Opsional) Untuk personalisasi "Diundang oleh ...":
 *    <div data-rg-invited-by style="display:none">
 *      Diundang oleh <span data-rg-referrer-name></span>
 *    </div>
 *
 * 4. (Opsional) Untuk menampilkan detail acara dari database
 *    (bisa diubah admin tanpa edit HTML):
 *    <span data-rg-field="event_day"></span>
 *    <span data-rg-field="event_time"></span>
 *    <span data-rg-field="event_location"></span>
 *    <span data-rg-field="event_speaker"></span>
 *    <span data-rg-field="event_capacity"></span>
 *    <span data-rg-field="event_name"></span>
 *
 * 5. (Opsional) Untuk pesan sukses/error inline:
 *    <div data-rg-message style="display:none"></div>
 *    Jika tidak ada, SDK akan pakai alert() sebagai fallback.
 *
 * 6. (Opsional) Untuk field tambahan di luar name/email/whatsapp/kota,
 *    beri atribut data-rg-extra. Nilainya akan dikirim sebagai object extra.
 *
 * 7. Tracking Meta Pixel & GA4 otomatis kalau admin sudah mengisi
 *    ID-nya lewat admin/tracking.php — SDK ini yang inject tag-nya
 *    dan fire PageView + Lead (saat pendaftaran sukses).
 * ============================================================
 */
(function () {
  'use strict';

  var API_BASE = '/api/';

  function getEventSlug() {
    var parts = window.location.pathname.split('/').filter(Boolean);
    var idx = parts.indexOf('e');
    if (idx !== -1 && parts[idx + 1]) return parts[idx + 1];
    return ''; // server pakai default_event_slug brand yang aktif kalau kosong
  }

  function getRefCode() {
    var params = new URLSearchParams(window.location.search);
    return params.get('ref') || '';
  }

  var eventSlug = getEventSlug();
  var refCode = getRefCode();

  // ============================================================
  // VISITOR TRACKING — first-party, dikirim ke api/track.php lewat sendBeacon
  // ============================================================
  var SESSION_KEY = 'rg_session_id';
  var sessionId = null;
  try {
    sessionId = localStorage.getItem(SESSION_KEY);
    if (!sessionId) {
      sessionId = crypto.randomUUID();
      localStorage.setItem(SESSION_KEY, sessionId);
    }
  } catch (e) {
    sessionId = crypto.randomUUID(); // localStorage tidak tersedia (mis. private mode) — pakai sesi sekali pakai
  }

  function getUtmParams() {
    var params = new URLSearchParams(window.location.search);
    return {
      utm_source: params.get('utm_source') || '',
      utm_medium: params.get('utm_medium') || '',
      utm_campaign: params.get('utm_campaign') || '',
    };
  }

  function getDeviceType() {
    var w = window.innerWidth;
    if (w < 640) return 'mobile';
    if (w < 1024) return 'tablet';
    return 'desktop';
  }

  function rgTrack(eventType) {
    var utm = getUtmParams();
    var payload = JSON.stringify({
      event_type: eventType,
      session_id: sessionId,
      page_path: window.location.pathname,
      referrer_url: document.referrer || '',
      event_slug: eventSlug,
      ref_code: refCode,
      device_type: getDeviceType(),
      utm_source: utm.utm_source,
      utm_medium: utm.utm_medium,
      utm_campaign: utm.utm_campaign,
    });

    // sendBeacon: fire-and-forget, tidak blokir navigasi/render sama sekali
    if (navigator.sendBeacon) {
      navigator.sendBeacon(API_BASE + 'track.php', new Blob([payload], { type: 'application/json' }));
    } else {
      fetch(API_BASE + 'track.php', { method: 'POST', body: payload, keepalive: true });
    }
  }

  function bindVisitorTracking() {
    rgTrack('pageview');

    var fired50 = false, fired90 = false;
    window.addEventListener('scroll', function () {
      var scrollable = document.body.scrollHeight - window.innerHeight;
      var scrollPct = scrollable > 0 ? ((window.scrollY / scrollable) * 100) : 100;
      if (scrollPct >= 50 && !fired50) { fired50 = true; rgTrack('scroll_50'); }
      if (scrollPct >= 90 && !fired90) { fired90 = true; rgTrack('scroll_90'); }
    }, { passive: true });

    var formStarted = false;
    document.addEventListener('focusin', function (e) {
      var form = document.querySelector('[data-rg-form]') || document.getElementById('regForm');
      if (form && form.contains(e.target) && !formStarted) {
        formStarted = true;
        rgTrack('form_start');
      }
    });
  }

  window.__rgTrack = rgTrack;

  function applyEventData(event) {
    if (!event) return;
    var els = document.querySelectorAll('[data-rg-field]');
    els.forEach(function (el) {
      var key = el.getAttribute('data-rg-field');
      if (event[key] !== undefined && event[key] !== null && event[key] !== '') {
        el.textContent = event[key];
      }
    });
  }

  function applyReferrerData(referrer) {
    var invitedBlocks = document.querySelectorAll('[data-rg-invited-by]');
    var nameEls = document.querySelectorAll('[data-rg-referrer-name]');
    if (referrer && referrer.name) {
      nameEls.forEach(function (el) { el.textContent = referrer.name; });
      invitedBlocks.forEach(function (el) { el.style.display = ''; });
    } else {
      invitedBlocks.forEach(function (el) { el.style.display = 'none'; });
    }
  }

  // Meta Pixel + GA4, di-inject dinamis per event (ID diatur admin lewat admin/tracking.php).
  function applyTracking(event) {
    if (!event) return;

    if (event.meta_pixel_id && !window.fbq) {
      (function (f, b, e, v, n, t, s) {
        if (f.fbq) return;
        n = f.fbq = function () {
          n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments);
        };
        if (!f._fbq) f._fbq = n;
        n.push = n; n.loaded = true; n.version = '2.0'; n.queue = [];
        t = b.createElement(e); t.async = true; t.src = v;
        s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s);
      })(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
    }
    if (event.meta_pixel_id && window.fbq) {
      fbq('init', event.meta_pixel_id);
      fbq('track', 'PageView');
    }

    if (event.ga_measurement_id && !window.gtag) {
      var gaScript = document.createElement('script');
      gaScript.async = true;
      gaScript.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(event.ga_measurement_id);
      document.head.appendChild(gaScript);
      window.dataLayer = window.dataLayer || [];
      window.gtag = function () { dataLayer.push(arguments); };
      gtag('js', new Date());
    }
    if (event.ga_measurement_id && window.gtag) {
      gtag('config', event.ga_measurement_id);
    }
  }

  function showMessage(text, isError) {
    var el = document.querySelector('[data-rg-message]');
    if (!el) {
      if (isError) alert(text);
      return;
    }
    el.textContent = text;
    el.style.display = 'block';
    el.style.color = isError ? '#E8956B' : '#7CD79A';
  }

  function collectExtraFields(form) {
    var extra = {};
    form.querySelectorAll('[data-rg-extra]').forEach(function (el) {
      if (!el.name) return;
      var val;
      if (el.type === 'checkbox') {
        if (!el.checked) return;
        val = el.value || '1';
      } else if (el.type === 'radio') {
        if (!el.checked) return;
        val = el.value || '';
      } else {
        val = el.value || '';
      }
      val = val.toString().trim();
      if (val !== '') extra[el.name] = val;
    });
    return extra;
  }

  function bindForm() {
    var form = document.querySelector('[data-rg-form]') || document.getElementById('regForm');
    if (!form) return;

    var submitBtn = form.querySelector('[type="submit"], button:not([type])');
    var originalBtnText = submitBtn ? submitBtn.textContent : '';

    form.addEventListener('submit', function (e) {
      e.preventDefault();

      var fd = new FormData(form);
      var payload = {
        name: (fd.get('name') || '').toString().trim(),
        email: (fd.get('email') || '').toString().trim(),
        whatsapp: (fd.get('whatsapp') || '').toString().trim(),
        kota: (fd.get('kota') || '').toString().trim(),
        event: eventSlug,
        ref: refCode,
        extra: collectExtraFields(form),
      };

      if (payload.name.length < 3) return showMessage('Nama lengkap minimal 3 karakter.', true);
      if (!/^\S+@\S+\.\S+$/.test(payload.email)) return showMessage('Email tidak valid.', true);
      if (payload.whatsapp.replace(/\D/g, '').length < 9) return showMessage('Nomor WhatsApp tidak valid.', true);
      if (payload.kota.length < 2) return showMessage('Kota domisili wajib diisi.', true);

      if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Memproses...'; }

      fetch(API_BASE + 'submit_lead.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      })
        .then(function (res) { return res.json(); })
        .then(function (result) {
          if (result.success) {
            if (window.fbq) fbq('track', 'Lead');
            if (window.gtag) gtag('event', 'generate_lead');
            rgTrack('form_submit');
            showMessage('✅ Pendaftaran berhasil! Mengarahkan ke WhatsApp...', false);
            form.reset();
            if (result.redirect_whatsapp) {
              var waUrl = 'https://wa.me/' + result.redirect_whatsapp +
                '?text=' + encodeURIComponent(result.whatsapp_text || '');
              rgTrack('whatsapp_redirect');
              setTimeout(function () { window.location.href = waUrl; }, 1400);
            } else if (submitBtn) {
              submitBtn.disabled = false;
              submitBtn.textContent = originalBtnText;
            }
          } else {
            showMessage('⚠️ ' + (result.message || 'Terjadi kesalahan.'), true);
            if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = originalBtnText; }
          }
        })
        .catch(function () {
          showMessage('⚠️ Gagal terhubung ke server. Periksa koneksi internet.', true);
          if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = originalBtnText; }
        });
    });
  }

  function init() {
    fetch(API_BASE + 'event_info.php?event=' + encodeURIComponent(eventSlug) + '&ref=' + encodeURIComponent(refCode))
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (!data.success) return;
        applyEventData(data.event);
        applyReferrerData(data.referrer);
        applyTracking(data.event);
      })
      .catch(function () {
        // Diam saja — landing page tetap tampil normal walau personalisasi gagal dimuat.
      });

    bindForm();
    bindVisitorTracking();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
