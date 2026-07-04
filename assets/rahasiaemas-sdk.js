/**
 * rahasiaemas-sdk.js — versi dengan dukungan "extra fields" opsional
 * PATCH: ganti file assets/rahasiaemas-sdk.js yang sudah ada di server dengan isi ini.
 * (Kalau file ini sudah di-rename jadi nama lain saat implementasi multi-brand,
 * ganti file dengan nama yang sesuai — isinya tetap sama.)
 *
 * PENAMBAHAN dari versi sebelumnya: form sekarang otomatis mengumpulkan
 * semua elemen dengan atribut `data-rg-extra` (checkbox/select/input apapun
 * di luar 4 field inti name/email/whatsapp/kota) dan mengirimkannya sebagai
 * object `extra` ke backend — dipakai untuk pertanyaan kualifikasi custom
 * per event, tanpa perlu ubah SDK lagi tiap kali ada event baru dengan
 * pertanyaan berbeda.
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

  // == EXTRA FIELDS == — kumpulkan semua field dengan atribut data-rg-extra
  function collectExtraFields(form) {
    var extra = {};
    form.querySelectorAll('[data-rg-extra]').forEach(function (el) {
      if (!el.name) return;
      var val = (el.value || '').toString().trim();
      if (val !== '') extra[el.name] = val;
    });
    return extra;
  }
  // == END EXTRA FIELDS ==

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
        extra: collectExtraFields(form), // == EXTRA FIELDS ==
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
      })
      .catch(function () {});

    bindForm();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
