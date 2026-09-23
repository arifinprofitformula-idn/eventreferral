# rahasiaemas.id — Panduan Instalasi di Shared Hosting

Sistem ini terdiri dari 3 bagian:

1. **Landing page acara** (`index.php`) — bisa dibuka lewat `rahasiaemas.id/?ref=KODE`
2. **Buat link undangan** (`buat-link.php`) — dipakai siapapun untuk generate link pribadi
3. **Dashboard admin** (`admin/`) — khusus Coach Arifin, dilindungi login username+password

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
4. Pilih file **`database/install.sql`** (ada di folder ini), lalu klik **Go**.
5. Pastikan muncul pesan sukses dan tabel inti muncul: `brands`, `events`,
   `referrers`, `leads`, dan `login_attempts`.
6. **PENTING untuk staging:** jika domain yang dites adalah
   `staging.rahasiaemas.id`, buka tabel `brands`, edit baris `rahasiaemas`,
   lalu ganti kolom `domain` dari `rahasiaemas.id` menjadi
   `staging.rahasiaemas.id`.
7. **PENTING:** Buka tabel `referrers`, edit baris `admin`, ganti kolom
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
     database/install.sql
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

- `ADMIN_USERNAME` → username login dashboard (WAJIB diganti dari default `admin`).
- `ADMIN_PASSWORD_HASH` → buka `admin/generate-password-hash.php` di browser,
  masukkan password pilihan Anda, salin hasil hash-nya ke sini, lalu **hapus
  file `admin/generate-password-hash.php` dari server** setelah selesai.
- `EVENT_DAY`, `EVENT_TIME`, `EVENT_LOCATION`, `EVENT_SPEAKER`, `EVENT_CAPACITY`
  → nilai awal/fallback detail acara. Setelah login admin, detail acara bisa diubah
  langsung dari dashboard tanpa edit file.

## LANGKAH 5 — Selesai! Uji Coba

- **Landing page utama:** `https://rahasiaemas.id/`
- **Buat link undangan:** `https://rahasiaemas.id/buat-link.php`
- **Dashboard admin:** `https://rahasiaemas.id/admin/` (masukkan username & password)

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

| Masalah                                   | Kemungkinan Penyebab                                                                                                           |
| ----------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------ |
| Halaman putih / error 500                 | Cek kembali isian `config.php`, khususnya `DB_NAME`, `DB_USER`, `DB_PASS`                                                      |
| "Koneksi database gagal"                  | Pastikan user database sudah di-attach ke database dengan ALL PRIVILEGES                                                       |
| Form submit tidak jalan                   | Pastikan hosting mendukung PHP 7.4 ke atas dan folder `api/` ikut ter-upload                                                   |
| Redirect WhatsApp tidak muncul            | Cek format nomor WA pengundang di tabel `referrers`, harus diawali `62`                                                        |
| Tidak bisa login admin                    | Cek kolom `admin_username` dan `admin_password_hash` di tabel `brands` untuk domain yang sedang dibuka                         |
| Login diblokir "Terlalu banyak percobaan" | Tunggu `LOGIN_LOCKOUT_MINUTES` (default 15 menit), atau hapus baris terkait IP Anda di tabel `login_attempts` lewat phpMyAdmin |

## Keamanan Tambahan (Opsional tapi Disarankan)

- Ganti password admin brand secara berkala dengan hash dari `admin/generate-password-hash.php`, lalu simpan ke kolom `admin_password_hash` di tabel `brands` (hapus file generator itu lagi setelah dipakai).
- Aktifkan **SSL/HTTPS gratis** (Let's Encrypt) lewat cPanel agar domain
  otomatis `https://` — biasanya tersedia gratis di menu **SSL/TLS Status**.
- Backup database secara berkala lewat phpMyAdmin → **Export**.

## Aturan Git / File yang Tidak Di-commit

Project ini sudah dilengkapi `.gitignore`. File yang aman masuk repo adalah
kode aplikasi, `database/install.sql`, asset publik, `.htaccess`, README, dan
`config.example.php`.

Jangan commit file/folder berikut:

- `config.php` karena berisi kredensial database dan hash password admin.
- `.env*`, kecuali `.env.example`.
- `.agents/` dan `.codex/` karena hanya untuk workspace lokal.
- `vendor/` dan `node_modules/` jika suatu saat dependency manager dipakai.
- File runtime seperti `logs/`, `cache/`, `tmp/`, `uploads/`, `storage/`,
  `exports/`, dan hasil export `*.csv`.
- Dump/backup lokal seperti `*.sql.gz`, `*.dump`, `*.bak`, `*.backup`,
  `*-old.*`, dan `*_old.*`.

## Deployment via GitHub

Untuk production jangka panjang, gunakan pola:

```text
repositories/rahasiaemas.id = source code dari GitHub
shared/rahasiaemas.id       = config dan data runtime production
domain root                 = diarahkan ke repositories/rahasiaemas.id
```

Panduan lengkap ada di `docs/DEPLOYMENT.md`, termasuk contoh symlink `config.php`,
folder event upload `e/`, folder reward image, cron deploy, dan script
`deploy/deploy.sh`.

# eventreferral

Perintah untuk update project:

```bash
cd /home/bisnisem/repositories/rahasiaemas.id
bash deploy/deploy.sh
```

## Simple LMS & eCourse

Fitur Simple LMS tersedia di:

- Katalog publik: `/course/`
- Register siswa: `/course/register.php`
- Login siswa: `/course/login.php`
- Dashboard siswa: `/course/my-courses.php`
- Admin eCourse: `/admin/lms-courses.php`
- Admin user & role: `/admin/lms-users.php`
- Admin order: `/admin/lms-orders.php`
- Admin progress: `/admin/lms-progress.php`

### Role LMS

- Guest: hanya melihat katalog dan detail course.
- Free User: akses course gratis + progress tracking.
- Paid User: akses course premium, quiz, sertifikat, dan course gratis.
- Admin LMS: akses penuh untuk course dan monitoring.

Role utama disimpan di `lms_users.primary_role`. Role tambahan scalable lewat `lms_roles` dan `lms_user_roles` untuk kebutuhan masa depan seperti `instructor` dan `moderator`.

### Database LMS

Migration LMS ada di:

```text
database/migrations/migrate_v25_lms.sql
database/migrations/migrate_v26_lms_course_builder.sql
```

`migrate_v25_lms.sql` membuat struktur LMS inti. `migrate_v26_lms_course_builder.sql` menambah metadata course, kategori, tag, instructor, bundle, promo code, dan material pendukung.

Modul LMS juga menjalankan auto-check schema saat halaman LMS/admin LMS dibuka. Untuk production, tetap disarankan import migration manual lewat phpMyAdmin agar struktur database eksplisit dan terkontrol.

Tabel utama:

- `lms_users`
- `lms_roles`
- `lms_user_roles`
- `lms_courses`
- `lms_modules`
- `lms_lessons`
- `lms_enrollments`
- `lms_lesson_progress`
- `lms_orders`
- `lms_role_logs`
- `lms_notifications`
- `lms_categories`
- `lms_tags`
- `lms_course_tags`
- `lms_course_instructors`
- `lms_bundles`
- `lms_bundle_courses`
- `lms_promo_codes`
- `lms_course_materials`

### Course Builder Admin

Form pengisian course tersedia di:

```text
/admin/lms-course-form.php
```

Akses juga tersedia dari `/admin/lms-courses.php` lewat tombol `+ Form Pengisian Course Baru` dan link `Edit Course`.

Field wajib:

- Judul course
- Deskripsi
- Kategori
- Tipe akses (`free` atau `paid`)
- Harga untuk course premium
- Status (`draft`, `published`, `archived`)

Field opsional:

- Cover image
- Upload media awal: video, PDF, atau external URL
- Durasi course
- Level (`beginner`, `intermediate`, `advanced`)
- Tags
- Sertifikat aktif/nonaktif
- Instructor

UX form:

- Dropdown dark theme dengan background `#222` dan teks putih.
- Validasi client-side dan server-side.
- Tombol `Save Draft`, `Publish`, dan `Cancel`.
- Live preview modal sebelum publish.
- Alert status setelah simpan.

Upload disimpan ke `uploads/lms/` dan dilindungi validasi MIME serta ekstensi file.

### Checkout & Payment

Checkout premium saat ini memakai simulator internal di `/course/checkout.php` karena codebase belum punya payment gateway existing.

- Simulasi sukses: membuat order `paid`, upgrade role ke `paid`, enroll course premium, kirim notifikasi/email.
- Simulasi gagal: membuat order `failed`, role tetap `free`.

Untuk integrasi gateway riil seperti Midtrans/Xendit, gunakan alur:

1. Buat invoice pada tombol checkout.
2. Simpan order sebagai `pending`.
3. Terima webhook payment provider.
4. Verifikasi signature webhook.
5. Jika settlement/success, panggil `lms_process_checkout_success()`.
6. Jika failed/expired, panggil `lms_process_checkout_failed()`.

Jangan mengandalkan redirect browser sebagai bukti pembayaran.

### Keamanan LMS

- Semua mutasi siswa butuh session login.
- Admin LMS memakai `require_admin_for_brand()`.
- Semua query LMS memakai prepared statement.
- Akses premium divalidasi server-side lewat role dan enrollment.
- Progress API menolak lesson yang tidak dimiliki brand aktif.
- Session LMS disinkronkan ulang dari database di setiap request protected.
