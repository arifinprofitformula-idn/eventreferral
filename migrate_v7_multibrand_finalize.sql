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
--
-- Jika file ini pernah gagal di tengah jalan, aman untuk dijalankan ulang:
-- constraint/index yang sudah ada akan dilewati.
-- ============================================================

-- Diagnostik cepat. Jika salah satu nilai *_null atau *_orphan > 0,
-- perbaiki data dulu sebelum lanjut, karena FK memang akan menolak data itu.
SELECT 'events_null' AS check_name, COUNT(*) AS total
FROM events
WHERE brand_id IS NULL
UNION ALL
SELECT 'referrers_null', COUNT(*)
FROM referrers
WHERE brand_id IS NULL
UNION ALL
SELECT 'leads_null', COUNT(*)
FROM leads
WHERE brand_id IS NULL
UNION ALL
SELECT 'events_orphan', COUNT(*)
FROM events e
LEFT JOIN brands b ON b.id = e.brand_id
WHERE e.brand_id IS NOT NULL AND b.id IS NULL
UNION ALL
SELECT 'referrers_orphan', COUNT(*)
FROM referrers r
LEFT JOIN brands b ON b.id = r.brand_id
WHERE r.brand_id IS NOT NULL AND b.id IS NULL
UNION ALL
SELECT 'leads_orphan', COUNT(*)
FROM leads l
LEFT JOIN brands b ON b.id = l.brand_id
WHERE l.brand_id IS NOT NULL AND b.id IS NULL;

ALTER TABLE events MODIFY brand_id INT NOT NULL;

SELECT IF(
    EXISTS (
        SELECT 1
        FROM information_schema.REFERENTIAL_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = 'events'
          AND CONSTRAINT_NAME = 'fk_events_brand'
    ),
    'SELECT 1',
    'ALTER TABLE events ADD CONSTRAINT fk_events_brand FOREIGN KEY (brand_id) REFERENCES brands(id)'
) INTO @sql;
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Slug event TETAP unik secara GLOBAL (bukan per-brand) — folder fisik
-- /e/{slug}/ dibagi bersama oleh semua domain (Addon Domain menunjuk ke
-- folder public_html yang sama), jadi dua brand tidak boleh berbagi slug.
-- (Tidak ada perubahan pada UNIQUE KEY `slug` yang sudah ada.)

ALTER TABLE referrers MODIFY brand_id INT NOT NULL;

SELECT IF(
    EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'referrers'
          AND INDEX_NAME = 'uniq_event_ref'
    ),
    'ALTER TABLE referrers DROP INDEX uniq_event_ref',
    'SELECT 1'
) INTO @sql;
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT IF(
    EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'referrers'
          AND INDEX_NAME = 'uniq_brand_event_ref'
    ),
    'SELECT 1',
    'ALTER TABLE referrers ADD UNIQUE KEY uniq_brand_event_ref (brand_id, event_slug, ref_code)'
) INTO @sql;
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT IF(
    EXISTS (
        SELECT 1
        FROM information_schema.REFERENTIAL_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = 'referrers'
          AND CONSTRAINT_NAME = 'fk_referrers_brand'
    ),
    'SELECT 1',
    'ALTER TABLE referrers ADD CONSTRAINT fk_referrers_brand FOREIGN KEY (brand_id) REFERENCES brands(id)'
) INTO @sql;
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE leads MODIFY brand_id INT NOT NULL;

SELECT IF(
    EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'leads'
          AND INDEX_NAME = 'idx_leads_brand'
    ),
    'SELECT 1',
    'ALTER TABLE leads ADD INDEX idx_leads_brand (brand_id)'
) INTO @sql;
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT IF(
    EXISTS (
        SELECT 1
        FROM information_schema.REFERENTIAL_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = 'leads'
          AND CONSTRAINT_NAME = 'fk_leads_brand'
    ),
    'SELECT 1',
    'ALTER TABLE leads ADD CONSTRAINT fk_leads_brand FOREIGN KEY (brand_id) REFERENCES brands(id)'
) INTO @sql;
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Selesai. Sistem sekarang multi-brand secara penuh di level database.
