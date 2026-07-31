-- Fitur "Konfirmasi Kehadiran Event" — tandai asal pendaftaran.
-- Jalankan sekali di database production via phpMyAdmin/CLI.
--
-- Sudah dicek: belum ada kolom sejenis (registration_source / source) di tabel leads
-- pada migrasi manapun sebelumnya, jadi ini aman ditambahkan.
--
-- DEFAULT 'form_referral' supaya SEMUA baris leads lama (dan baru, lewat api/submit_lead.php
-- yang TIDAK diubah/tidak menyebut kolom ini secara eksplisit di INSERT-nya) otomatis
-- tercatat sebagai berasal dari form pendaftaran/referral biasa — tanpa perlu backfill
-- manual dan tanpa mengubah satu baris pun logic di api/submit_lead.php.
-- Baris baru dari /api/attendance-confirm.php (peserta walk-in yang confirm kehadiran
-- tanpa pernah daftar) akan diisi eksplisit 'hadir_langsung'.

ALTER TABLE leads
    ADD COLUMN registration_source VARCHAR(30) NOT NULL DEFAULT 'form_referral' AFTER event_slug;
