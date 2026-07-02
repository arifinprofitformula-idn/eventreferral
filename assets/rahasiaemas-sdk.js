/**
 * rahasiaemas-sdk.js
 * ============================================================
 * SDK ringan yang menghubungkan landing page HTML/CSS statis apapun
 * ke sistem rahasiaemas.id — tanpa perlu PHP di dalam file HTML-nya.
 *
 * CARA PAKAI (untuk siapapun yang membuat landing page baru):
 *
 * 1. Sisipkan tag ini sebelum </body> di index.html Anda:
 *    <script src="/assets/rahasiaemas-sdk.js" defer></script>
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
 * 6. Tracking Meta Pixel & GA4 otomatis kalau admin sudah mengisi
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
    return 'default';
  }

  function getRefCode() {
    var params = new URLSearchParams(window.location.search);
    return params.get('ref') || '';
  }

  var eventSlug = getEventSlug();
  var refCode = getRefCode();

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
            showMessage('✅ Pendaftaran berhasil! Mengarahkan ke WhatsApp...', false);
            form.reset();
            if (result.redirect_whatsapp) {
              var waUrl = 'https://wa.me/' + result.redirect_whatsapp +
                '?text=' + encodeURIComponent(result.whatsapp_text || '');
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
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
