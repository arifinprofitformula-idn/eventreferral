-- ============================================================
-- rahasiaemas.id — MIGRASI ke v12 (Kolom tanggal terstruktur untuk Kalender Event)
-- ADDITIVE ONLY — tidak menghapus atau mengubah kolom/data yang sudah ada.
-- Aman dijalankan berkali-kali (idempotent) berkat IF NOT EXISTS.
-- ============================================================

ALTER TABLE events ADD COLUMN IF NOT EXISTS event_date DATE NULL AFTER event_day;

-- Kolom event_day (teks bebas, contoh: "Jumat, 18 Juli 2026") TETAP ADA dan
-- TETAP DIPAKAI landing page lewat data-rg-field="event_day" — TIDAK BERUBAH.
-- event_date HANYA dipakai untuk sorting & filter di /kalender/, bersifat opsional.
-- Event lama yang belum diisi event_date akan tampil di bagian bawah timeline
-- dengan label "Jadwal Menyusul" sampai admin mengisi tanggalnya.

-- Selesai.
