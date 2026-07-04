-- v11: Tambah kolom flyer_path pada tabel events untuk menyimpan
-- flyer/poster acara yang bisa diunduh pengundang di halaman buat-link.php.
ALTER TABLE events ADD COLUMN IF NOT EXISTS flyer_path VARCHAR(255) NULL AFTER reward_image;
