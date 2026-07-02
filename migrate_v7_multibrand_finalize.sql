-- ============================================================
-- rahasiaemas.id — MIGRASI ke v7 (Multi-Brand) — BAGIAN 2/2, FINALISASI
--
-- JANGAN jalankan file ini sebelum:
--   1. migrate_v7_multibrand.sql sudah dijalankan, DAN
--   2. admin/migrate-legacy.php sudah dijalankan sekali dan berhasil
--      (semua baris events/referrers/leads sudah punya brand_id).
--
-- Checkpoint sebelum menjalankan file ini — jalankan query berikut,
-- HARUS mengembalikan 0 untuk ketiganya:
--   SELECT COUNT(*) FROM events    WHERE brand_id IS NULL;
--   SELECT COUNT(*) FROM referrers WHERE brand_id IS NULL;
--   SELECT COUNT(*) FROM leads     WHERE brand_id IS NULL;
-- ============================================================

ALTER TABLE events
    MODIFY brand_id INT NOT NULL,
    ADD CONSTRAINT fk_events_brand FOREIGN KEY (brand_id) REFERENCES brands(id);

-- Slug event TETAP unik secara GLOBAL (bukan per-brand) — folder fisik
-- /e/{slug}/ dibagi bersama oleh semua domain (Addon Domain menunjuk ke
-- folder public_html yang sama), jadi dua brand tidak boleh berbagi slug.
-- (Tidak ada perubahan pada UNIQUE KEY `slug` yang sudah ada.)

ALTER TABLE referrers
    MODIFY brand_id INT NOT NULL,
    ADD CONSTRAINT fk_referrers_brand FOREIGN KEY (brand_id) REFERENCES brands(id),
    DROP INDEX uniq_event_ref,
    ADD UNIQUE KEY uniq_brand_event_ref (brand_id, event_slug, ref_code);

ALTER TABLE leads
    MODIFY brand_id INT NOT NULL,
    ADD CONSTRAINT fk_leads_brand FOREIGN KEY (brand_id) REFERENCES brands(id),
    ADD INDEX idx_leads_brand (brand_id);

-- Selesai. Sistem sekarang multi-brand secara penuh di level database.
