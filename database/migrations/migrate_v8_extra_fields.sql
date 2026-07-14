-- ============================================================
-- migrate_v4_extra_fields.sql
-- OPSIONAL — jalankan ini kalau Coach mau memakai fitur "pertanyaan
-- kualifikasi tambahan" (seperti "riwayat pembelian" & "minat utama"
-- di event Rahasia Perak). Sifatnya ADDITIVE, tidak mengubah data yang
-- sudah ada, dan event-event lain yang tidak pakai fitur ini tetap
-- jalan normal (kolom baru akan NULL).
-- ============================================================

ALTER TABLE leads ADD COLUMN IF NOT EXISTS extra_fields TEXT NULL AFTER kota;

-- extra_fields menyimpan JSON string, contoh isi:
-- {"riwayat_pembelian":"pernah_emas","minat_utama":"edukasi"}
--
-- Kenapa JSON di 1 kolom (bukan kolom terpisah per pertanyaan)?
-- Supaya setiap event ZIP bisa punya pertanyaan kualifikasi yang
-- BERBEDA-BEDA tanpa perlu ALTER TABLE lagi tiap kali ada event baru
-- dengan pertanyaan custom baru.
