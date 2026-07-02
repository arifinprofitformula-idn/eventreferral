# Deployment Production — Pola Aman Jangka Panjang

Dokumen ini menjelaskan pola deploy yang disarankan untuk `rahasiaemas.id`:

1. Update kode dari lokal via VSCode.
2. Commit dan push ke GitHub.
3. Server melakukan update otomatis ke folder `repositories`.
4. Domain `rahasiaemas.id` diarahkan ke release/current repo.
5. File runtime dan secret disimpan di folder `shared`, bukan di Git.

## Struktur Folder Server

Gunakan struktur seperti ini di home user hosting/server:

```text
/home/USER/
  repositories/
    rahasiaemas.id/
      .git/
      admin/
      api/
      assets/
      challenge/
      includes/
      index.php
      config.example.php
      config.php -> /home/USER/shared/rahasiaemas.id/config.php

  shared/
    rahasiaemas.id/
      config.php
      backups/
      deploy.log

  domains/rahasiaemas.id/public_html -> /home/USER/repositories/rahasiaemas.id
```

Jika hosting tidak mendukung symlink untuk document root, arahkan document root domain langsung ke:

```text
/home/USER/repositories/rahasiaemas.id
```

## Kenapa Struktur Ini Lebih Aman

- `repositories/rahasiaemas.id` berisi source code dari GitHub.
- `shared/rahasiaemas.id/config.php` berisi kredensial production dan tidak ikut Git.
- Folder `e/` di dalam repo tetap menjadi lokasi publik event hasil upload ZIP.
- Folder `assets/rewards/` di dalam repo tetap menjadi lokasi publik gambar hadiah runtime.
- Isi runtime `e/*` dan `assets/rewards/*` di-ignore Git, kecuali file placeholder/proteksi.
- Domain mengarah ke repo yang bisa di-update otomatis dengan `git pull`.

Catatan: Karena event dan gambar hadiah harus bisa diakses publik via `/e/...` dan
`/assets/rewards/...`, keduanya tetap berada di document root. Yang penting:
script deploy tidak menjalankan `git clean`, sehingga file upload runtime tidak
terhapus saat `git pull`.

## Setup Pertama Kali di Server

Ganti `USER`, URL repo, dan branch sesuai server kamu.

```bash
cd /home/USER
mkdir -p repositories shared/rahasiaemas.id/backups
git clone git@github.com:USERNAME/rahasiaemas.id.git repositories/rahasiaemas.id
cd repositories/rahasiaemas.id
cp config.example.php /home/USER/shared/rahasiaemas.id/config.php
```

Edit config production:

```bash
nano /home/USER/shared/rahasiaemas.id/config.php
```

Buat symlink shared:

```bash
cd /home/USER/repositories/rahasiaemas.id
ln -sfn /home/USER/shared/rahasiaemas.id/config.php config.php
```

Jika sebelumnya sudah ada event di folder lama, pindahkan dulu isi folder `e/` lama ke:

```text
/home/USER/repositories/rahasiaemas.id/e
```

Jika sebelumnya sudah ada gambar hadiah, pindahkan isi `assets/rewards/` lama ke:

```text
/home/USER/repositories/rahasiaemas.id/assets/rewards
```

Pastikan file proteksi dan placeholder tetap ada:

```bash
ls -la /home/USER/repositories/rahasiaemas.id/e/.htaccess
ls -la /home/USER/repositories/rahasiaemas.id/assets/rewards/default.png
```

## Arahkan Domain

Pilihan terbaik:

```text
Document root rahasiaemas.id = /home/USER/repositories/rahasiaemas.id
```

Jika control panel hanya menyediakan `public_html`, gunakan symlink:

```bash
mv /home/USER/domains/rahasiaemas.id/public_html /home/USER/domains/rahasiaemas.id/public_html_backup
ln -s /home/USER/repositories/rahasiaemas.id /home/USER/domains/rahasiaemas.id/public_html
```

Jangan hapus folder lama sebelum memastikan backup lengkap.

## Deploy Manual

Setelah push dari lokal:

```bash
cd /home/USER/repositories/rahasiaemas.id
bash deploy/deploy.sh
```

## Deploy Otomatis via Cron

Jika belum pakai webhook, cron adalah opsi paling sederhana.

Contoh setiap 1 menit:

```cron
* * * * * cd /home/USER/repositories/rahasiaemas.id && bash deploy/deploy.sh >> /home/USER/shared/rahasiaemas.id/deploy.log 2>&1
```

Script deploy hanya akan melakukan update jika remote branch punya commit baru.

## Deploy Otomatis via GitHub Webhook

Webhook lebih realtime, tapi butuh endpoint server yang aman. Minimal harus ada:

- Secret token.
- Validasi signature GitHub.
- Script deploy hanya boleh pull branch production.
- Log deploy tersimpan di `shared`.

Untuk shared hosting, cron biasanya lebih mudah dan cukup aman.

## Alur Kerja Harian

Di lokal:

```bash
git status
git add .
git commit -m "Deskripsi perubahan"
git push origin main
```

Di server:

```bash
cd /home/USER/repositories/rahasiaemas.id
bash deploy/deploy.sh
```

Atau biarkan cron/webhook menjalankannya otomatis.

## Checklist Keamanan

- `config.php` tidak boleh masuk Git.
- Folder `.git` tidak boleh bisa diakses dari browser.
- File `.sql`, `.md`, `.sh`, `.env`, backup, dan log tidak boleh bisa diakses publik.
- Folder runtime `e/` dan `assets/rewards/` harus persistent di `shared`.
- Sebelum mengaktifkan deploy otomatis, pastikan `git status` di server bersih.
- Backup database sebelum deploy besar.

## Rollback Cepat

Jika deploy terbaru bermasalah:

```bash
cd /home/USER/repositories/rahasiaemas.id
git log --oneline -5
git checkout COMMIT_SEBELUMNYA
```

Setelah fix sudah siap:

```bash
git checkout main
bash deploy/deploy.sh
```
