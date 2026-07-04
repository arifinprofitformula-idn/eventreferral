<?php
/**
 * admin/documentation.php
 * Dokumentasi teknis internal sistem.
 * Cukup dilindungi login admin brand aktif; tidak perlu MASTER_SETUP_KEY.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
start_secure_session();

$brand = require_admin_for_brand(get_current_brand());
$brandInitials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $brand['name'] ?? 'RE'), 0, 2));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dokumentasi Sistem — <?= htmlspecialchars($brand['name']) ?></title>
<style>
  :root {
    --bg-0: #0B0B0A;
    --bg-1: #10100F;
    --surface: #171716;
    --surface-elevated: #20201E;
    --border-gold: rgba(214,165,54,0.18);
    --gold: #D6A536;
    --gold-soft: #F4D27A;
    --text: #F7F3E8;
    --text-secondary: #A8A29A;
    --danger: #EF4444;
    --success: #22C55E;
    --warning: #F59E0B;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html { scroll-behavior: smooth; }
  body {
    background: var(--bg-0);
    color: var(--text);
    font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    min-height: 100vh;
    line-height: 1.6;
    background-image:
      radial-gradient(720px 480px at 92% -6%, rgba(214,165,54,0.10), transparent 60%),
      radial-gradient(640px 480px at 4% 108%, rgba(214,165,54,0.07), transparent 60%);
    background-attachment: fixed;
  }

  .topbar {
    position: sticky; top: 0; z-index: 20;
    height: 72px;
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 32px;
    background: rgba(16,16,15,0.82);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border-bottom: 1px solid rgba(214,165,54,0.14);
  }
  .topbar-brand { display: flex; align-items: center; gap: 12px; }
  .topbar-emblem {
    width: 38px; height: 38px; border-radius: 10px;
    background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    display: flex; align-items: center; justify-content: center;
    color: #171716; font-weight: 800; font-size: 14px;
    flex-shrink: 0;
  }
  .topbar-label { font-size: 14.5px; font-weight: 600; }
  .badge-secure {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 12px; font-weight: 600; letter-spacing: 0.03em;
    color: var(--gold-soft);
    background: rgba(214,165,54,0.10);
    border: 1px solid var(--border-gold);
    padding: 6px 12px; border-radius: 999px;
  }

  .layout { max-width: 1320px; margin: 0 auto; padding: 32px; display: grid; grid-template-columns: 240px 1fr; gap: 32px; align-items: start; }
  @media (max-width: 640px) { .layout { padding: 16px; } .topbar { padding: 0 16px; } }
  @media (max-width: 900px) { .layout { grid-template-columns: 1fr; } .toc { display: none; } }

  .toc { position: sticky; top: 96px; }
  .toc-title { font-size: 11.5px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 14px; }
  .toc ol { list-style: none; display: flex; flex-direction: column; gap: 2px; counter-reset: toc; }
  .toc li { counter-increment: toc; }
  .toc a {
    display: flex; align-items: baseline; gap: 8px;
    font-size: 13px; color: var(--text-secondary); text-decoration: none;
    padding: 7px 10px; border-radius: 8px;
    transition: background 150ms ease, color 150ms ease;
  }
  .toc a::before { content: counter(toc, decimal-leading-zero); color: var(--gold); font-variant-numeric: tabular-nums; font-size: 11.5px; }
  .toc a:hover { background: rgba(214,165,54,0.08); color: var(--text); }

  .hero {
    position: relative; overflow: hidden;
    border-radius: 28px; border: 1px solid var(--border-gold);
    background:
      radial-gradient(420px 260px at 88% 10%, rgba(214,165,54,0.16), transparent 65%),
      linear-gradient(160deg, var(--surface), var(--bg-1));
    padding: 32px 36px; margin-bottom: 24px;
  }
  .hero-eyebrow {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 11.5px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
    color: #17170f; background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    padding: 5px 12px; border-radius: 999px; margin-bottom: 14px;
  }
  .hero h1 { font-size: clamp(22px, 3vw, 30px); font-weight: 700; margin-bottom: 8px; text-wrap: balance; }
  .hero p { color: var(--text-secondary); font-size: 14.5px; max-width: 62ch; }
  .hero-meta { display: flex; gap: 18px; margin-top: 18px; flex-wrap: wrap; }
  .hero-meta div { font-size: 12px; color: var(--text-secondary); }
  .hero-meta strong { display: block; color: var(--text); font-size: 14px; font-weight: 700; font-variant-numeric: tabular-nums; }

  section { margin-bottom: 26px; scroll-margin-top: 90px; }
  .card {
    background: var(--surface); border: 1px solid var(--border-gold);
    border-radius: 22px; padding: 28px 30px;
  }
  .card-title { font-size: 18px; font-weight: 700; margin-bottom: 4px; display: flex; align-items: center; gap: 10px; }
  .card-title svg { color: var(--gold); flex-shrink: 0; }
  .card-subtitle { font-size: 13.5px; color: var(--text-secondary); margin-bottom: 22px; }

  h3 { font-size: 15px; font-weight: 700; margin: 22px 0 10px; color: var(--gold-soft); }
  h3:first-of-type { margin-top: 0; }
  p.body-text { font-size: 14px; color: var(--text-secondary); margin-bottom: 12px; }
  p.body-text:last-child { margin-bottom: 0; }
  code { font-family: "SF Mono", Consolas, Menlo, monospace; background: rgba(255,255,255,0.06); padding: 2px 6px; border-radius: 5px; font-size: 0.88em; color: var(--gold-soft); }
  pre {
    background: #0E0E0C; border: 1px solid rgba(255,255,255,0.08); border-radius: 12px;
    padding: 14px 16px; overflow-x: auto; font-size: 13px; line-height: 1.6; margin: 12px 0;
  }
  pre code { background: none; padding: 0; color: var(--text); }

  table { width: 100%; border-collapse: collapse; font-size: 13.5px; margin: 12px 0 20px; }
  th, td { text-align: left; padding: 9px 12px; border-bottom: 1px solid rgba(255,255,255,0.07); vertical-align: top; }
  th { color: var(--text-secondary); font-weight: 600; font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.03em; }
  td { color: var(--text-secondary); }
  td:first-child, td.strong { color: var(--text); font-weight: 600; }
  .table-wrap { overflow-x: auto; }

  .timeline { display: flex; flex-direction: column; gap: 0; }
  .tl-item { display: grid; grid-template-columns: 64px 1fr; gap: 18px; position: relative; padding-bottom: 24px; }
  .tl-item:last-child { padding-bottom: 0; }
  .tl-item::before {
    content: ''; position: absolute; left: 31px; top: 30px; bottom: 0; width: 1px;
    background: rgba(255,255,255,0.08);
  }
  .tl-item:last-child::before { display: none; }
  .tl-badge {
    width: 64px; height: 26px; border-radius: 8px;
    background: rgba(214,165,54,0.12); border: 1px solid var(--border-gold);
    color: var(--gold-soft); font-size: 12px; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    z-index: 1; font-variant-numeric: tabular-nums;
  }
  .tl-badge.current { background: linear-gradient(135deg, var(--gold), var(--gold-soft)); color: #171716; }
  .tl-body strong { display: block; font-size: 14.5px; margin-bottom: 4px; }
  .tl-body p { font-size: 13px; color: var(--text-secondary); }
  .tl-tags { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px; }
  .tag { font-size: 11px; color: var(--text-secondary); background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); padding: 3px 9px; border-radius: 999px; }

  .steps { list-style: none; counter-reset: step; display: flex; flex-direction: column; gap: 14px; }
  .steps li { counter-increment: step; display: grid; grid-template-columns: 30px 1fr; gap: 14px; font-size: 13.5px; color: var(--text-secondary); line-height: 1.65; }
  .steps li::before {
    content: counter(step); width: 26px; height: 26px; border-radius: 8px;
    background: rgba(214,165,54,0.12); border: 1px solid var(--border-gold); color: var(--gold-soft);
    display: flex; align-items: center; justify-content: center; font-size: 12.5px; font-weight: 700; flex-shrink: 0;
  }
  .steps li strong { color: var(--text); }

  .checklist { list-style: none; display: flex; flex-direction: column; gap: 10px; }
  .checklist li { display: flex; align-items: flex-start; gap: 10px; font-size: 13.5px; color: var(--text-secondary); line-height: 1.6; }
  .checklist svg { color: var(--success); flex-shrink: 0; margin-top: 3px; }

  .callout {
    border-left: 3px solid var(--danger); background: rgba(239,68,68,0.08);
    padding: 12px 16px; border-radius: 0 10px 10px 0; font-size: 13px; color: var(--text); margin: 14px 0;
  }
  .callout.warn { border-left-color: var(--warning); background: rgba(245,158,11,0.08); }
  .callout strong { color: var(--danger); }
  .callout.warn strong { color: var(--warning); }

  .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
  @media (max-width: 720px) { .grid-2 { grid-template-columns: 1fr; } }

  .foot { text-align: center; color: var(--text-secondary); font-size: 12.5px; padding: 20px 0 8px; }
</style>
</head>
<body>

<div class="topbar">
  <div class="topbar-brand">
    <div class="topbar-emblem"><?= htmlspecialchars($brandInitials ?: 'RE') ?></div>
    <span class="topbar-label">Dokumentasi Sistem</span>
  </div>
  <span class="badge-secure">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 2 4 5v6c0 5 3.4 8.6 8 11 4.6-2.4 8-6 8-11V5l-8-3Z"/><path d="m9 12 2 2 4-4"/></svg>
    Admin Terverifikasi
  </span>
</div>

<div class="layout">

  <nav class="toc">
    <div class="toc-title">Daftar Isi</div>
    <ol>
      <li><a href="#arsitektur">Arsitektur Sistem</a></li>
      <li><a href="#riwayat">Riwayat Versi (v1 → v8)</a></li>
      <li><a href="#brand-baru">Menambah Brand Baru</a></li>
      <li><a href="#migrasi">Urutan Migrasi Database</a></li>
      <li><a href="#deploy">Deploy Production</a></li>
      <li><a href="#keamanan">Checklist Keamanan</a></li>
      <li><a href="#troubleshooting">Troubleshooting</a></li>
    </ol>
  </nav>

  <main>

    <div class="hero">
      <span class="hero-eyebrow">Dokumentasi Internal</span>
      <h1>Dokumentasi Sistem <?= htmlspecialchars($brand['name']) ?></h1>
      <p>Referensi teknis lengkap: arsitektur saat ini, riwayat perubahan sejak pertama dibangun, urutan migrasi database, dan pola deploy production yang dipakai tim.</p>
      <div class="hero-meta">
        <div><strong>v8</strong>Versi skema saat ini</div>
        <div><strong>Multi-brand</strong>Mode operasi</div>
        <div><strong>Git + cron/webhook</strong>Pola deploy</div>
      </div>
    </div>

    <section id="arsitektur">
      <div class="card">
        <div class="card-title">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
          Arsitektur Sistem
        </div>
        <p class="card-subtitle">Empat lapisan utama, semuanya berjalan di atas satu document root PHP tanpa framework/build step.</p>

        <div class="table-wrap">
          <table>
            <tr><th>Lapisan</th><th>Lokasi</th><th>Fungsi</th></tr>
            <tr><td class="strong">Landing page acara</td><td><code>index.php</code>, <code>/e/{slug}/</code></td><td>Halaman publik per event, dipersonalisasi lewat <code>?ref=</code> dan SDK <code>assets/rahasiaemas-sdk.js</code>.</td></tr>
            <tr><td class="strong">Buat link undangan</td><td><code>buat-link.php</code></td><td>Dipakai siapapun untuk generate link referral pribadi per event.</td></tr>
            <tr><td class="strong">Dashboard admin per brand</td><td><code>admin/</code></td><td>Login username+password per brand — kelola event, reward, tracking, export data.</td></tr>
            <tr><td class="strong">Onboarding brand</td><td><code>admin/setup-brand.php</code></td><td>Hanya Coach — dilindungi <code>MASTER_SETUP_KEY</code>, bukan bagian dari dashboard brand manapun.</td></tr>
          </table>
        </div>

        <h3>Model data inti</h3>
        <p class="body-text">Sejak v7, setiap baris <code>events</code>, <code>referrers</code>, dan <code>leads</code> terikat ke satu <code>brand_id</code>. Satu domain (Addon Domain di cPanel) = satu baris di tabel <code>brands</code>, dengan kredensial admin, tema, dan logo sendiri-sendiri — tapi tetap berbagi satu database dan satu codebase.</p>
        <p class="body-text">Slug event (<code>events.slug</code>) tetap unik <strong>secara global</strong>, bukan per-brand, karena folder fisik <code>/e/{slug}/</code> dibagi oleh semua domain lewat document root yang sama. Karena itu setiap brand punya <code>default_event_slug</code> sendiri (v8) untuk event root domainnya, alih-alih berbagi slug <code>default</code>.</p>
      </div>
    </section>

    <section id="riwayat">
      <div class="card">
        <div class="card-title">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="9"/></svg>
          Riwayat Versi — Sejak Pertama Dibangun
        </div>
        <p class="card-subtitle">Setiap versi menambah kemampuan tanpa mengubah fondasi versi sebelumnya. Detail lengkap tiap file ada di <code>CHANGELOG.md</code> dan file <code>migrate_v*.sql</code> di root repo.</p>

        <div class="timeline">
          <div class="tl-item">
            <div class="tl-badge">v1</div>
            <div class="tl-body">
              <strong>Referral single-event</strong>
              <p>Landing page tunggal di root domain, tabel <code>referrers</code> dan <code>leads</code>, redirect WhatsApp otomatis berdasarkan <code>ref_code</code>.</p>
            </div>
          </div>
          <div class="tl-item">
            <div class="tl-badge">v2</div>
            <div class="tl-body">
              <strong>Multi-event + challenge publik</strong>
              <p>Tabel <code>events</code>, folder <code>e/</code> untuk landing page hasil upload ZIP, SDK <code>rahasiaemas-sdk.js</code>, halaman publik <code>/challenge/</code>, endpoint <code>api/event_info.php</code>.</p>
              <div class="tl-tags"><span class="tag">migrate_v2.sql</span><span class="tag">includes/functions.php</span></div>
            </div>
          </div>
          <div class="tl-item">
            <div class="tl-badge">v3</div>
            <div class="tl-body">
              <strong>Hadiah challenge per event</strong>
              <p>Kolom <code>reward_image</code> di <code>events</code>, tabel <code>event_rewards</code> (hadiah per peringkat, opsional per event).</p>
              <div class="tl-tags"><span class="tag">migrate_v3.sql</span></div>
            </div>
          </div>
          <div class="tl-item">
            <div class="tl-badge">v4</div>
            <div class="tl-body">
              <strong>Tracking Meta Pixel + Google Analytics</strong>
              <p>Kolom <code>meta_pixel_id</code> dan <code>ga_measurement_id</code> di <code>events</code>, dikelola lewat <code>admin/tracking.php</code>.</p>
              <div class="tl-tags"><span class="tag">migrate_v4.sql</span></div>
            </div>
          </div>
          <div class="tl-item">
            <div class="tl-badge">v5</div>
            <div class="tl-body">
              <strong>Login admin username+password + rate-limiting</strong>
              <p>Ganti PIN menjadi <code>ADMIN_USERNAME</code>/<code>ADMIN_PASSWORD_HASH</code>, tabel <code>login_attempts</code> untuk membatasi percobaan login gagal per IP.</p>
              <div class="tl-tags"><span class="tag">migrate_v5.sql</span><span class="tag">admin/generate-password-hash.php</span></div>
            </div>
          </div>
          <div class="tl-item">
            <div class="tl-badge">v6</div>
            <div class="tl-body">
              <strong>Satukan pengaturan acara ke tabel events</strong>
              <p>Tabel <code>event_settings</code> (pengaturan tunggal global) dihapus — setiap event kini punya pengaturannya sendiri langsung di tabel <code>events</code>, dikelola lewat <code>admin/event-settings.php</code>.</p>
              <div class="tl-tags"><span class="tag">migrate_v6.sql</span></div>
            </div>
          </div>
          <div class="tl-item">
            <div class="tl-badge">v7</div>
            <div class="tl-body">
              <strong>Multi-brand / multi-domain</strong>
              <p>Tabel <code>brands</code> baru; <code>brand_id</code> ditambahkan ke <code>events</code>/<code>referrers</code>/<code>leads</code> lalu dikunci NOT NULL + foreign key. Dijalankan 2 tahap SQL plus satu script backfill sekali-pakai.</p>
              <div class="tl-tags"><span class="tag">migrate_v7_multibrand.sql</span><span class="tag">admin/migrate-legacy.php</span><span class="tag">migrate_v7_multibrand_finalize.sql</span></div>
            </div>
          </div>
          <div class="tl-item">
            <div class="tl-badge current">v8</div>
            <div class="tl-body">
              <strong>Root event per brand — versi saat ini</strong>
              <p>Kolom <code>default_event_slug</code> di <code>brands</code>, sehingga brand baru tidak berebut slug <code>default</code> dengan brand rahasiaemas yang sudah ada. Brand baru diberi slug root <code>{brand_slug}-default</code> otomatis oleh <code>admin/setup-brand.php</code>.</p>
              <div class="tl-tags"><span class="tag">migrate_v8_default_event_slug.sql</span></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section id="brand-baru">
      <div class="card">
        <div class="card-title">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2 4 5v6c0 5 3.4 8.6 8 11 4.6-2.4 8-6 8-11V5l-8-3Z"/></svg>
          Menambah Brand Baru
        </div>
        <p class="card-subtitle">Alur operasional lewat <code>admin/setup-brand.php</code> — hanya Coach yang menjalankan ini.</p>

        <ol class="steps">
          <li><strong>Siapkan domain.</strong> Domain baru sudah diarahkan sebagai Addon Domain di cPanel ke folder <code>public_html</code> yang sama dengan brand lain.</li>
          <li><strong>Buka <code>admin/setup-brand.php?key=...</code></strong> memakai <code>MASTER_SETUP_KEY</code> dari <code>config.php</code> — bukan PIN/password admin brand manapun.</li>
          <li><strong>Isi identitas brand</strong> — slug, domain, nama, tagline, logo, WhatsApp default, disclaimer, preset tema (atau warna custom).</li>
          <li><strong>Isi kredensial admin brand ini</strong> — username dan password terpisah dari brand lain.</li>
          <li><strong>Simpan.</strong> Sistem otomatis membuat baris <code>brands</code> baru dan satu event root domain dengan slug <code>{brand_slug}-default</code>.</li>
          <li><strong>Aktifkan SSL</strong> untuk domain baru lewat cPanel (Let's Encrypt).</li>
        </ol>

        <div class="callout warn"><strong>Catatan —</strong> slug event tetap unik secara global. Jika slug yang diinginkan sudah dipakai brand lain, sistem akan menolak saat validasi domain/slug brand (bukan slug event, yang dibuat otomatis).</div>
      </div>
    </section>

    <section id="migrasi">
      <div class="card">
        <div class="card-title">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h10"/></svg>
          Urutan Migrasi Database
        </div>
        <p class="card-subtitle">Untuk instalasi yang sudah berjalan (bukan instalasi baru). Instalasi baru cukup import <code>install.sql</code> — sudah mencakup semua versi.</p>

        <div class="table-wrap">
          <table>
            <tr><th>Urutan</th><th>File / Aksi</th><th>Catatan</th></tr>
            <tr><td class="strong">1</td><td><code>migrate_v2.sql</code></td><td>Wajib sebelum v3 ke atas.</td></tr>
            <tr><td class="strong">2</td><td><code>migrate_v3.sql</code></td><td>Butuh tabel <code>events</code> dari v2.</td></tr>
            <tr><td class="strong">3</td><td><code>migrate_v4.sql</code></td><td>Butuh kolom <code>reward_image</code> dari v3.</td></tr>
            <tr><td class="strong">4</td><td><code>migrate_v5.sql</code></td><td>Lanjutkan dengan generate password hash + update <code>config.php</code>.</td></tr>
            <tr><td class="strong">5</td><td><code>migrate_v6.sql</code></td><td>Menghapus tabel <code>event_settings</code> — pastikan data sudah dipindah otomatis oleh script.</td></tr>
            <tr><td class="strong">6</td><td><code>migrate_v7_multibrand.sql</code></td><td>Tahap 1/3 — brand_id masih boleh <code>NULL</code>.</td></tr>
            <tr><td class="strong">7</td><td><code>admin/migrate-legacy.php</code> (buka sekali di browser)</td><td>Tahap 2/3 — backfill <code>brand_id</code>. <strong>Hapus file ini setelah sukses.</strong></td></tr>
            <tr><td class="strong">8</td><td><code>migrate_v7_multibrand_finalize.sql</code></td><td>Tahap 3/3 — mengunci <code>brand_id</code> NOT NULL + foreign key. Cek dulu tidak ada baris <code>brand_id IS NULL</code>.</td></tr>
            <tr><td class="strong">9</td><td><code>migrate_v8_default_event_slug.sql</code></td><td>Versi saat ini. Aman diulang (idempoten).</td></tr>
          </table>
        </div>

        <div class="callout"><strong>Wajib berurutan —</strong> langkah 6 → 7 → 8 tidak boleh dilompat atau dibalik. Masing-masing memeriksa hasil langkah sebelumnya sebelum mengunci constraint.</div>
      </div>
    </section>

    <section id="deploy">
      <div class="card">
        <div class="card-title">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M2 12h20"/><circle cx="12" cy="12" r="9"/></svg>
          Deploy Production
        </div>
        <p class="card-subtitle">Pola resmi sejak sistem ini pertama dibangun dengan Git — detail lengkap ada di <code>DEPLOYMENT.md</code>.</p>

        <h3>Struktur folder server</h3>
        <pre><code>/home/USER/
  repositories/rahasiaemas.id/     <span style="color:var(--text-secondary)"># source code dari GitHub</span>
    config.php -> /home/USER/shared/rahasiaemas.id/config.php
  shared/rahasiaemas.id/
    config.php                    <span style="color:var(--text-secondary)"># kredensial production, TIDAK ikut Git</span>
    backups/
  domains/rahasiaemas.id/public_html -> repositories/rahasiaemas.id</code></pre>

        <h3>Alur kerja harian</h3>
        <div class="grid-2">
          <div>
            <p class="body-text" style="color:var(--text);font-weight:600;margin-bottom:6px;">Di lokal</p>
            <pre><code>git status
git add .
git commit -m "Deskripsi perubahan"
git push origin main</code></pre>
          </div>
          <div>
            <p class="body-text" style="color:var(--text);font-weight:600;margin-bottom:6px;">Di server</p>
            <pre><code>cd /home/USER/repositories/rahasiaemas.id
bash deploy/deploy.sh</code></pre>
          </div>
        </div>

        <h3>Deploy otomatis</h3>
        <p class="body-text">Cron setiap 1 menit (lebih sederhana untuk shared hosting) atau GitHub webhook (realtime, butuh endpoint dengan validasi signature). <code>deploy/deploy.sh</code> hanya mengeksekusi jika ada commit baru di remote, menolak jalan kalau working tree kotor, dan menjalankan <code>php -l</code> pada file inti sebelum selesai.</p>
        <pre><code>* * * * * cd /home/USER/repositories/rahasiaemas.id && bash deploy/deploy.sh >> /home/USER/shared/rahasiaemas.id/deploy.log 2>&1</code></pre>

        <h3>Rollback cepat</h3>
        <pre><code>git log --oneline -5
git checkout COMMIT_SEBELUMNYA
<span style="color:var(--text-secondary)"># setelah fix siap:</span>
git checkout main && bash deploy/deploy.sh</code></pre>
      </div>
    </section>

    <section id="keamanan">
      <div class="card">
        <div class="card-title">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2 4 5v6c0 5 3.4 8.6 8 11 4.6-2.4 8-6 8-11V5l-8-3Z"/><path d="m9 12 2 2 4-4"/></svg>
          Checklist Keamanan
        </div>
        <p class="card-subtitle">Berlaku untuk setiap deploy besar atau penambahan brand baru.</p>
        <ul class="checklist">
          <li><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m5 12 5 5L20 7"/></svg><code>config.php</code> tidak pernah masuk Git — hanya <code>config.example.php</code>.</li>
          <li><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m5 12 5 5L20 7"/></svg><code>MASTER_SETUP_KEY</code> adalah string acak panjang milik Coach, bukan nilai contoh di <code>config.example.php</code>.</li>
          <li><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m5 12 5 5L20 7"/></svg>File sekali-pakai (<code>admin/migrate-legacy.php</code>, <code>admin/generate-password-hash.php</code>) dihapus dari server setelah dipakai.</li>
          <li><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m5 12 5 5L20 7"/></svg>Folder <code>.git</code>, file <code>.sql/.md/.sh/.env</code> dan backup diblokir lewat <code>.htaccess</code> — tidak bisa diakses browser.</li>
          <li><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m5 12 5 5L20 7"/></svg>Folder runtime <code>e/</code>, <code>assets/rewards/</code>, <code>uploads/brands/</code> tidak bisa mengeksekusi PHP.</li>
          <li><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m5 12 5 5L20 7"/></svg>Backup database dijalankan sebelum migrasi besar (v7/v8 dan seterusnya).</li>
        </ul>
      </div>
    </section>

    <section id="troubleshooting">
      <div class="card">
        <div class="card-title">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
          Troubleshooting
        </div>
        <div class="table-wrap">
          <table>
            <tr><th>Masalah</th><th>Kemungkinan Penyebab</th></tr>
            <tr><td class="strong">Halaman putih / error 500</td><td>Cek isian <code>config.php</code>, khususnya <code>DB_NAME</code>/<code>DB_USER</code>/<code>DB_PASS</code>.</td></tr>
            <tr><td class="strong">Brand baru tidak bisa dibuat</td><td>Slug atau domain sudah dipakai brand lain — cek tabel <code>brands</code> lewat phpMyAdmin.</td></tr>
            <tr><td class="strong">Migrasi v7 finalize gagal</td><td>Masih ada baris <code>brand_id IS NULL</code> — jalankan ulang <code>admin/migrate-legacy.php</code> sebelum finalize.</td></tr>
            <tr><td class="strong">Login admin brand gagal</td><td>Cek <code>admin_username</code>/<code>admin_password_hash</code> di tabel <code>brands</code> untuk brand terkait.</td></tr>
            <tr><td class="strong">Deploy otomatis tidak jalan</td><td>Cek <code>deploy.log</code> di folder <code>shared</code> — kemungkinan working tree di server tidak bersih.</td></tr>
          </table>
        </div>
      </div>
    </section>

  </main>
</div>

<p class="foot">Dokumentasi ini mengikuti kondisi sistem versi v8 (multi-brand). Perbarui halaman ini setiap kali menambah <code>migrate_v*.sql</code> baru.</p>

</body>
</html>
