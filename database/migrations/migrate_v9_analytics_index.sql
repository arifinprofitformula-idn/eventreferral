-- migrate_v6_analytics_index.sql
-- Index tambahan supaya query "Top Halaman Masuk" dan "Performa Per
-- Pengundang" di admin/visitor-analytics.php tetap cepat saat data
-- visitor_events sudah besar.

ALTER TABLE visitor_events ADD INDEX IF NOT EXISTS idx_page_path (page_path(100));
ALTER TABLE visitor_events ADD INDEX IF NOT EXISTS idx_ref_code_lookup (ref_code, event_slug);
