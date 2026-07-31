-- Fitur "Konfirmasi Kehadiran Event" — kode kehadiran statis per event.
-- Jalankan sekali di database production via phpMyAdmin/CLI.
--
-- attendance_code: string pendek alfanumerik yang admin set manual di admin/event-settings.php
-- dan dibagikan ke peserta saat acara (lisan/slide/chat Zoom). NULL/kosong berarti
-- fitur konfirmasi kehadiran belum diaktifkan untuk event tsb (halaman /hadir/{slug}
-- tetap bisa diakses tapi form tidak akan pernah valid).

ALTER TABLE events
    ADD COLUMN attendance_code VARCHAR(20) NULL AFTER event_capacity;
