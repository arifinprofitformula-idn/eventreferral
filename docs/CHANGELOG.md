# CHANGELOG — rahasiaemas.id v1 → v2

Dokumen ini merinci **persis** file/folder mana yang baru ditambahkan dan mana
yang diubah dari paket v1 (sistem referral single-event) menjadi v2 (multi-event
+ challenge publik). Berguna sebagai referensi kalau Coach ingin diff manual
dengan file yang sudah di-upload ke hosting.

---

## 📁 Folder Baru

| Folder | Isi | Fungsi |
|---|---|---|
| `includes/` | `functions.php` | Kumpulan fungsi bantu: validasi slug, ekstraksi ZIP yang aman, dsb. |
| `e/` | `.htaccess`, `README.txt` | Tempat penyimpanan landing page hasil upload ZIP. Awalnya kosong (isinya bertambah tiap kali admin upload event baru). |
| `challenge/` | `index.php` | Halaman publik leaderboard. |

---

## 🆕 File Baru

| File | Fungsi |
|---|---|
| `migrate_v2.sql` | Skrip migrasi database untuk instalasi v1 yang sudah berjalan (ALTER TABLE, bukan install ulang dari nol). |
| `includes/functions.php` | `slugify()`, `is_valid_event_slug()`, `safe_extract_zip()` (ekstraksi ZIP anti path-traversal & filter tipe file), `inject_sdk_script()`, `get_event_by_slug()`. |
| `assets/rahasiaemas-sdk.js` | Script yang disisipkan ke landing page HTML statis manapun — menangani baca `?ref=`, personalisasi, isi detail acara, dan submit form ke API. |
| `api/event_info.php` | Endpoint publik (dipanggil SDK) — mengembalikan data event + nama pengundang berdasarkan `?event=` & `?ref=`. |
| `e/.htaccess` | Menonaktifkan eksekusi PHP di dalam folder event hasil upload (lapisan keamanan). |
| `e/README.txt` | Catatan pengingat agar `.htaccess` di folder ini tidak terhapus. |
| `challenge/index.php` | Halaman publik `/challenge/` — leaderboard pengundang, bisa difilter per event. |
| `admin/events.php` | Panel admin: upload ZIP event baru, lihat semua event, arsipkan/aktifkan event. |
| `README-EVENTS.md` | Spesifikasi kontrak ZIP (struktur file, format `config.json`, atribut HTML wajib) untuk siapapun yang membuat landing page baru. |
| `template-event-starter.zip` | Contoh paket ZIP siap pakai (index.html + config.json + assets/) yang sudah mengikuti kontrak SDK — tinggal diduplikasi. |
| `CHANGELOG.md` | Dokumen ini. |

---

## ✏️ File yang Diubah

| File | Perubahan |
|---|---|
| `config.php` | Ditambah konstanta baru: `DEFAULT_EVENT_SLUG`, `EVENTS_DIR`, `EVENTS_URL_BASE`, `MAX_ZIP_SIZE`, `ALLOWED_ASSET_EXT`, `RESERVED_SLUGS`. Ditambah `require_once includes/functions.php` di baris akhir. |
| `install.sql` | Ditulis ulang total: sekarang termasuk tabel `events`, kolom `event_slug` di `referrers` & `leads`, dan constraint unik per-event (`event_slug` + `ref_code`) — untuk **instalasi baru** saja. |
| `api/submit_lead.php` | Ditulis ulang: menerima parameter `event`, mencari pengundang berdasarkan `(event_slug, ref_code)` bukan `ref_code` saja, fallback ke `whatsapp_default` milik event kalau tidak ada pengundang. |
| `api/create_referrer.php` | Ditulis ulang: menerima parameter `event`, `ref_code` sekarang unik per-event (bukan global), link yang dihasilkan otomatis menyesuaikan format (`/?ref=` untuk event utama, `/e/{slug}/?ref=` untuk event lain). |
| `buat-link.php` | Ditambah dukungan `?event=slug` di URL — judul halaman menampilkan nama event terkait, field tersembunyi `event` diteruskan ke API. |
| `admin/dashboard.php` | Ditambah navigasi ke halaman "Kelola Event", ditambah tabel ringkasan "Pendaftar per Event". **Perbaikan bug:** query leaderboard & data pendaftar sebelumnya mencocokkan hanya `ref_code` (berisiko salah hitung jika dua event berbeda punya kode referral yang sama) — sekarang mencocokkan `event_slug` + `ref_code` sekaligus. |
| `admin/export.php` | Ditambah kolom `Event` di file CSV yang diunduh; query JOIN diperbaiki agar ikut mencocokkan `event_slug`. |
| `README.md` | Ditulis ulang total: sekarang mencakup alur instalasi baru vs. upgrade, cara pakai fitur multi-event, cara pakai `/challenge`, dan tabel troubleshooting yang diperbarui. |

---

## ⏸️ File yang TIDAK Diubah (tetap sama persis seperti v1)

| File | Keterangan |
|---|---|
| `index.php` | Landing page acara utama di root domain — sengaja tidak disentuh agar yang sudah live tidak berubah/rusak. |
| `admin/login.php` | Halaman login PIN admin. |
| `admin/logout.php` | Handler logout admin. |
| `assets/logo.png` | Logo yang Coach upload sebelumnya. |

---

## Ringkasan Cepat (struktur folder)

```
rahasiaemas/
├── index.php                    (tetap)
├── buat-link.php                 (diubah — tambah dukungan multi-event)
├── config.php                    (diubah — tambah konfigurasi multi-event)
├── install.sql                   (diubah — skema v2, untuk instalasi baru)
├── migrate_v2.sql                (BARU — untuk upgrade dari v1)
├── README.md                     (diubah — panduan v2)
├── README-EVENTS.md              (BARU)
├── CHANGELOG.md                  (BARU — dokumen ini)
├── template-event-starter.zip    (BARU)
│
├── admin/
│   ├── login.php                 (tetap)
│   ├── logout.php                (tetap)
│   ├── dashboard.php             (diubah — perbaikan bug + ringkasan per event)
│   ├── export.php                (diubah — kolom event)
│   └── events.php                (BARU — upload & kelola event)
│
├── api/
│   ├── submit_lead.php           (diubah — event-aware)
│   ├── create_referrer.php       (diubah — event-aware)
│   └── event_info.php            (BARU)
│
├── assets/
│   ├── logo.png                  (tetap)
│   └── rahasiaemas-sdk.js        (BARU)
│
├── includes/                     (folder BARU)
│   └── functions.php             (BARU)
│
├── e/                             (folder BARU — kosong, terisi otomatis)
│   ├── .htaccess                 (BARU)
│   └── README.txt                (BARU)
│
└── challenge/                     (folder BARU)
    └── index.php                 (BARU)
```
