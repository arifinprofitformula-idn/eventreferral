# README-EVENTS.md — Cara Membuat Landing Page Event Baru

Dokumen ini untuk siapapun (desainer, tim Marcom, atau pihak ketiga) yang
akan membuatkan landing page baru untuk sistem rahasiaemas.id.

Landing page bisa didesain **bebas** — pakai tool apapun (Figma export,
Webflow export, coding manual, dsb) — selama mengikuti 3 aturan kontrak
di bawah ini. Setelah itu, sistem otomatis mengurus: database, form
pendaftaran, link referral, dan redirect WhatsApp.

---

## 1. Struktur ZIP yang Wajib

```
nama-event.zip
├── index.html          ← WAJIB, di posisi root (bukan di dalam subfolder)
├── config.json          ← WAJIB, di posisi root
└── assets/               ← opsional: gambar, CSS, JS tambahan
    ├── style.css
    ├── hero.jpg
    └── ...
```

**Tipe file yang diizinkan di dalam ZIP:** html, htm, css, js, json, txt,
png, jpg, jpeg, gif, svg, webp, ico, woff, woff2, ttf, otf, eot, mp4, webm.

File dengan tipe lain (terutama `.php`) akan **otomatis dilewati** oleh
sistem demi keamanan — tidak akan ikut terupload.

---

## 2. Format `config.json`

```json
{
  "slug": "funtactic-selling",
  "name": "Funtactic Selling — Edisi Juli",
  "whatsapp": "6281234567890",
  "event_day": "Jumat, 18 Juli 2026",
  "event_time": "19.30 WIB",
  "event_location": "Online via Zoom",
  "event_speaker": "Coach Arifin",
  "event_capacity": "150"
}
```

| Field                                                                          | Wajib?    | Keterangan                                                                                                                        |
| ------------------------------------------------------------------------------ | --------- | --------------------------------------------------------------------------------------------------------------------------------- |
| `slug`                                                                         | Opsional  | Menentukan URL akhir: `rahasiaemas.id/e/{slug}/`. Jika kosong, admin bisa isi manual saat upload. Huruf kecil, angka, strip saja. |
| `name`                                                                         | **Wajib** | Nama event, dipakai di dashboard admin & pesan WhatsApp otomatis.                                                                 |
| `whatsapp`                                                                     | Wajib     | Nomor WA fallback — dipakai kalau ada pendaftar yang buka landing page TANPA kode referral (`?ref=`).                             |
| `event_day`, `event_time`, `event_location`, `event_speaker`, `event_capacity` | Opsional  | Bisa ditampilkan otomatis di landing page lewat atribut `data-rg-field` (lihat bawah).                                            |

---

## 3. Kontrak HTML — Wajib Diikuti agar Form & Referral Berfungsi

### a. Sisipkan SDK

Tambahkan baris ini sebelum `</body>` di `index.html`:

```html
<script src="/assets/event-sdk.js" defer></script>
```

_(Kalau lupa, sistem akan otomatis menyisipkannya saat upload — tapi lebih aman kalau ditambahkan manual.)_

### b. Form Pendaftaran

Beri atribut `data-rg-form` pada tag `<form>`, dan pastikan field menggunakan
`name` persis seperti ini:

```html
<form data-rg-form>
  <input type="text" name="name" required />
  <input type="email" name="email" required />
  <input type="tel" name="whatsapp" required />
  <input type="text" name="kota" required />
  <button type="submit">Daftar Sekarang</button>
</form>
```

### c. Pesan Sukses/Error (opsional tapi disarankan)

```html
<div data-rg-message style="display:none;"></div>
```

### d. Personalisasi "Diundang oleh..." (opsional)

```html
<div data-rg-invited-by style="display:none;">
  Diundang oleh <strong data-rg-referrer-name></strong>
</div>
```

### e. Detail Acara Otomatis dari config.json (opsional)

```html
<span data-rg-field="event_day"></span>
<span data-rg-field="event_time"></span>
<span data-rg-field="event_location"></span>
<span data-rg-field="event_speaker"></span>
<span data-rg-field="event_capacity"></span>
<span data-rg-field="event_name"></span>
```

Elemen-elemen ini otomatis terisi teks dari database — jadi kalau tanggal/jam
acara berubah, admin cukup upload ulang ZIP dengan `config.json` baru tanpa
harus mengedit HTML.

---

## 4. Setelah Upload

- Landing page hidup di: `https://rahasiaemas.id/e/{slug}/`
- Setiap orang bisa buat link referral pribadi di: `https://rahasiaemas.id/buat-link.php?event={slug}`
  → hasilnya: `https://rahasiaemas.id/e/{slug}/?ref=kode-unik`
- Leaderboard publik: `https://rahasiaemas.id/challenge/?event={slug}`
- Semua data masuk otomatis ke dashboard admin: `https://rahasiaemas.id/admin/`

## 5. Template Siap Pakai

Lihat file **`template-event-starter.zip`** yang disertakan bersama paket
ini — sudah lengkap dengan `index.html`, `config.json`, dan desain dasar
yang sudah mengikuti semua aturan di atas. Tinggal duplikat, ganti isi,
ganti nama file jadi ZIP baru, lalu upload.
