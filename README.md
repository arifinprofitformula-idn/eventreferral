# rahasiaemas.id — Panduan Instalasi di Shared Hosting

Sistem ini terdiri dari 3 bagian:
1. **Landing page acara** (`index.php`) — bisa dibuka lewat `rahasiaemas.id/?ref=KODE`
2. **Buat link undangan** (`buat-link.php`) — dipakai siapapun untuk generate link pribadi
3. **Dashboard admin** (`admin/`) — khusus Coach Arifin, dilindungi PIN

---

## LANGKAH 1 — Buat Database MySQL

1. Login ke **cPanel** hosting Anda.
2. Buka menu **MySQL Databases**.
3. Buat database baru, misal: `rahasiaemas` → hasil akhirnya biasanya
   `namauser_rahasiaemas`.
4. Buat user database baru + password, lalu **hubungkan (Add User to Database)**
   user tersebut ke database di atas dengan hak akses **ALL PRIVILEGES**.
5. Catat 3 hal ini, akan dipakai di Langkah 3:
   - Nama database: `namauser_rahasiaemas`
   - Username database: `namauser_xxxxx`
   - Password database: `********`

## LANGKAH 2 — Import Struktur Tabel

1. Di cPanel, buka **phpMyAdmin**.
2. Klik database yang baru dibuat di sidebar kiri.
3. Klik tab **Import** di bagian atas.
4. Pilih file **`install.sql`** (ada di folder ini), lalu klik **Go**.
5. Pastikan muncul pesan sukses dan 2 tabel baru muncul: `referrers` dan `leads`.
6. **PENTING:** Buka tabel `referrers`, edit baris `admin`, ganti kolom
   `whatsapp` dengan nomor WhatsApp Coach Arifin sendiri (format: `628xxxxxxxxxx`,
   tanpa tanda `+`). Nomor ini dipakai kalau ada orang buka rahasiaemas.id
   TANPA kode referral siapapun.

## LANGKAH 3 — Upload File ke Hosting

1. Buka **File Manager** di cPanel (atau pakai FTP/FileZilla).
2. Masuk ke folder `public_html` (atau folder domain rahasiaemas.id jika
   berupa addon domain — cek di menu **Domains**).
3. Upload **semua isi** folder ini (bukan foldernya, tapi isinya) langsung
   ke `public_html`, sehingga strukturnya jadi:
   ```
   public_html/
     index.php
     buat-link.php
     config.php
     install.sql
     api/
     admin/
     assets/
   ```

## LANGKAH 4 — Buat dan Edit `config.php`

Salin `config.example.php` menjadi `config.php`, lalu buka file `config.php`
lewat **File Manager → Edit** (atau download, edit, upload ulang). Isi bagian
ini dengan data dari Langkah 1:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'namauser_rahasiaemas');
define('DB_USER', 'namauser_xxxxx');
define('DB_PASS', 'password_database_anda');
```

Ganti juga:
- `ADMIN_PIN` → PIN rahasia untuk masuk dashboard (default `482910`, WAJIB diganti).
- `EVENT_DAY`, `EVENT_TIME`, `EVENT_LOCATION`, `EVENT_SPEAKER`, `EVENT_CAPACITY`
  → nilai awal/fallback detail acara. Setelah login admin, detail acara bisa diubah
  langsung dari dashboard tanpa edit file.

## LANGKAH 5 — Selesai! Uji Coba

- **Landing page utama:** `https://rahasiaemas.id/`
- **Buat link undangan:** `https://rahasiaemas.id/buat-link.php`
- **Dashboard admin:** `https://rahasiaemas.id/admin/` (masukkan PIN)

Cara uji alur lengkap:
1. Buka `buat-link.php`, isi nama, WA, dan kode link pilihan → dapat link,
   misal `https://rahasiaemas.id/?ref=budiemas`.
2. Buka link tersebut → landing page muncul dengan tulisan
   "Kamu diundang oleh Budi".
3. Isi form pendaftaran → setelah submit, akan otomatis diarahkan ke
   WhatsApp nomor **Budi** dengan pesan konfirmasi siap kirim.
4. Cek `admin/` → data pendaftar & leaderboard pengundang langsung muncul.

---

## Mengganti Detail Acara Setiap Minggu

Setiap kali ada acara Jumat Malam baru, Coach cukup login ke `admin/`, lalu ubah
bagian **Pengaturan Acara** di dashboard. Seluruh landing page otomatis update,
tidak perlu edit file atau HTML.

## Troubleshooting

| Masalah | Kemungkinan Penyebab |
|---|---|
| Halaman putih / error 500 | Cek kembali isian `config.php`, khususnya `DB_NAME`, `DB_USER`, `DB_PASS` |
| "Koneksi database gagal" | Pastikan user database sudah di-attach ke database dengan ALL PRIVILEGES |
| Form submit tidak jalan | Pastikan hosting mendukung PHP 7.4 ke atas dan folder `api/` ikut ter-upload |
| Redirect WhatsApp tidak muncul | Cek format nomor WA pengundang di tabel `referrers`, harus diawali `62` |
| Tidak bisa login admin | Cek `ADMIN_PIN` di `config.php`, cocokkan dengan yang diketik |

## Keamanan Tambahan (Opsional tapi Disarankan)

- Ganti `ADMIN_PIN` secara berkala.
- Aktifkan **SSL/HTTPS gratis** (Let's Encrypt) lewat cPanel agar domain
  otomatis `https://` — biasanya tersedia gratis di menu **SSL/TLS Status**.
- Backup database secara berkala lewat phpMyAdmin → **Export**.

## Aturan Git / File yang Tidak Di-commit

Project ini sudah dilengkapi `.gitignore`. File yang aman masuk repo adalah
kode aplikasi, `install.sql`, asset publik, `.htaccess`, README, dan
`config.example.php`.

Jangan commit file/folder berikut:
- `config.php` karena berisi kredensial database dan `ADMIN_PIN`.
- `.env*`, kecuali `.env.example`.
- `.agents/` dan `.codex/` karena hanya untuk workspace lokal.
- `vendor/` dan `node_modules/` jika suatu saat dependency manager dipakai.
- File runtime seperti `logs/`, `cache/`, `tmp/`, `uploads/`, `storage/`,
  `exports/`, dan hasil export `*.csv`.
- Dump/backup lokal seperti `*.sql.gz`, `*.dump`, `*.bak`, `*.backup`,
  `*-old.*`, dan `*_old.*`.
# eventreferral
