<?php require_once __DIR__ . '/config.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Buat Link Undanganmu — rahasiaemas.id</title>
<link rel="icon" href="assets/logo.png">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root {
    --charcoal: #1A1A1A;
    --gold: #C9A84C;
    --gold-soft: #E8D5A3;
    --white: #FAFAFA;
    --muted: #9C9992;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    background: var(--charcoal);
    color: var(--white);
    font-family: 'Poppins', sans-serif;
    min-height: 100svh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
  }
  .box { max-width: 440px; width: 100%; text-align: center; }
  .logo { width: 80px; margin: 0 auto 24px; }
  h1 { font-family: 'Playfair Display', serif; color: var(--gold); font-size: 26px; margin-bottom: 10px; }
  p.sub { color: var(--muted); font-size: 14.5px; margin-bottom: 32px; }
  .field { text-align: left; margin-bottom: 16px; }
  .field label { display: block; font-size: 13px; color: var(--gold-soft); margin-bottom: 7px; font-weight: 600; }
  .field input {
    width: 100%; background: #242424; border: 1px solid rgba(255,255,255,0.15);
    border-radius: 10px; padding: 14px 16px; color: var(--white); font-size: 15px;
    font-family: inherit; outline: none;
  }
  .field input:focus { border-color: var(--gold); }
  .btn {
    width: 100%; background: var(--gold); color: var(--charcoal); font-weight: 700;
    font-size: 15.5px; padding: 15px; border: none; border-radius: 12px; cursor: pointer; margin-top: 8px;
  }
  .btn:disabled { opacity: 0.6; cursor: not-allowed; }
  .result {
    display: none; margin-top: 28px; background: rgba(201,168,76,0.08);
    border: 1px solid var(--gold); border-radius: 14px; padding: 20px;
  }
  .result p { font-size: 13px; color: var(--muted); margin-bottom: 10px; }
  .link-box {
    display: flex; gap: 8px; background: var(--charcoal); border-radius: 10px; padding: 10px 12px;
    align-items: center;
  }
  .link-box input { flex: 1; background: transparent; border: none; color: var(--gold-soft); font-size: 13.5px; outline: none; }
  .copy-btn { background: var(--gold); color: var(--charcoal); border: none; border-radius: 8px; padding: 8px 14px; font-weight: 700; font-size: 12.5px; cursor: pointer; }
  .share-btn {
    display: inline-block; margin-top: 14px; color: var(--gold); font-size: 13.5px; font-weight: 600; text-decoration: none;
  }
  .msg { display: none; font-size: 13.5px; margin-top: 14px; padding: 10px; border-radius: 8px; }
  .msg.error { background: rgba(217,116,58,0.12); color: #E8956B; display: block; }
</style>
</head>
<body>
<div class="box">
  <img src="assets/logo.png" alt="rahasiaemas.id" class="logo">
  <h1>Buat Link Undanganmu</h1>
  <p class="sub">Isi nama & WhatsApp kamu untuk membuat link undangan pribadi. Bagikan ke teman — dan setiap orang yang daftar lewat link ini akan langsung terhubung ke WhatsApp kamu.</p>

  <form id="genForm">
    <div class="field">
      <label>Nama Kamu</label>
      <input type="text" name="name" placeholder="Nama lengkap kamu" required minlength="3">
    </div>
    <div class="field">
      <label>Nomor WhatsApp Kamu</label>
      <input type="tel" name="whatsapp" placeholder="08xxxxxxxxxx" required minlength="9">
    </div>
    <button type="submit" class="btn" id="genBtn">Buat Link Undangan Saya</button>
  </form>

  <div class="msg" id="errMsg"></div>

  <div class="result" id="resultBox">
    <p>Link undangan kamu siap dibagikan:</p>
    <div class="link-box">
      <input type="text" id="linkOutput" readonly>
      <button class="copy-btn" id="copyBtn">Salin</button>
    </div>
    <a href="#" id="waShareBtn" class="share-btn" target="_blank">📲 Bagikan langsung ke WhatsApp →</a>
  </div>
</div>

<script>
const form = document.getElementById('genForm');
const genBtn = document.getElementById('genBtn');
const errMsg = document.getElementById('errMsg');
const resultBox = document.getElementById('resultBox');
const linkOutput = document.getElementById('linkOutput');
const copyBtn = document.getElementById('copyBtn');
const waShareBtn = document.getElementById('waShareBtn');

form.addEventListener('submit', async function (e) {
  e.preventDefault();
  errMsg.style.display = 'none';
  genBtn.disabled = true;
  genBtn.textContent = 'Memproses...';

  const data = {
    name: form.name.value.trim(),
    whatsapp: form.whatsapp.value.trim(),
  };

  try {
    const res = await fetch('api/create_referrer.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
    });
    const result = await res.json();

    if (result.success) {
      linkOutput.value = result.link;
      const shareText = `Halo! Aku mau undang kamu ke acara edukasi gratis "Rahasia Emas" — Jumat malam ini. Daftar di sini ya: ${result.link}`;
      waShareBtn.href = `https://wa.me/?text=${encodeURIComponent(shareText)}`;
      resultBox.style.display = 'block';
      form.style.display = 'none';
    } else {
      errMsg.textContent = '⚠️ ' + (result.message || 'Terjadi kesalahan.');
      errMsg.style.display = 'block';
    }
  } catch (err) {
    errMsg.textContent = '⚠️ Gagal terhubung ke server.';
    errMsg.style.display = 'block';
  } finally {
    genBtn.disabled = false;
    genBtn.textContent = 'Buat Link Undangan Saya';
  }
});

copyBtn.addEventListener('click', function () {
  linkOutput.select();
  navigator.clipboard.writeText(linkOutput.value).then(() => {
    copyBtn.textContent = 'Tersalin!';
    setTimeout(() => copyBtn.textContent = 'Salin', 1500);
  });
});
</script>
</body>
</html>
