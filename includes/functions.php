<?php
/**
 * includes/functions.php
 * Kumpulan fungsi bantu untuk sistem multi-event (upload ZIP landing page).
 */

/** Ubah teks bebas menjadi slug URL yang aman: huruf kecil, angka, strip */
function slugify($str) {
    $str = strtolower(trim($str));
    $str = preg_replace('/[^a-z0-9]+/', '-', $str);
    $str = preg_replace('/-+/', '-', $str);
    return trim($str, '-');
}

/** Cek apakah slug formatnya valid dan bukan kata yang dicadangkan sistem */
function is_valid_event_slug($slug) {
    if ($slug === '' || mb_strlen($slug) < 2 || mb_strlen($slug) > 60) {
        return false;
    }
    if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug)) {
        return false;
    }
    if (in_array($slug, RESERVED_SLUGS, true)) {
        return false;
    }
    return true;
}

/**
 * Ekstrak ZIP dengan aman ke folder tujuan.
 * - Menolak file dengan ekstensi tidak diizinkan (mis. .php) — dilewati, tidak diekstrak.
 * - Menolak path yang mencoba keluar dari folder tujuan (zip-slip / path traversal).
 *
 * @return array ['ok' => bool, 'error' => string|null, 'skipped' => string[], 'extracted' => int]
 */
function safe_extract_zip($zipPath, $destDir) {
    $result = ['ok' => false, 'error' => null, 'skipped' => [], 'extracted' => 0];

    if (!class_exists('ZipArchive')) {
        $result['error'] = 'Ekstensi PHP ZipArchive tidak tersedia di hosting ini. Hubungi provider hosting untuk mengaktifkannya.';
        return $result;
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        $result['error'] = 'File ZIP tidak valid atau rusak.';
        return $result;
    }

    if (!is_dir($destDir)) {
        if (!mkdir($destDir, 0755, true)) {
            $zip->close();
            $result['error'] = 'Gagal membuat folder tujuan di server.';
            return $result;
        }
    }

    $realDest = realpath($destDir);
    $extracted = 0;

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = $zip->getNameIndex($i);
        if ($entry === false) continue;

        // Lewati folder-only entries
        if (substr($entry, -1) === '/') continue;

        // Tolak path traversal / absolute path
        if (strpos($entry, '..') !== false || strpos($entry, "\0") !== false || $entry[0] === '/') {
            $result['skipped'][] = $entry . ' (path tidak aman)';
            continue;
        }

        // Lewati file/folder tersembunyi sistem (mis. __MACOSX, .DS_Store)
        if (strpos($entry, '__MACOSX') === 0 || basename($entry) === '.DS_Store') {
            continue;
        }

        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_ASSET_EXT, true)) {
            $result['skipped'][] = $entry . ' (tipe file .' . $ext . ' tidak diizinkan)';
            continue;
        }

        $targetPath = $destDir . '/' . $entry;
        $targetDir = dirname($targetPath);

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        // Pastikan target tetap di dalam folder tujuan (double-check setelah mkdir)
        $realTargetDir = realpath($targetDir);
        if ($realTargetDir === false || strpos($realTargetDir, $realDest) !== 0) {
            $result['skipped'][] = $entry . ' (di luar folder tujuan)';
            continue;
        }

        $content = $zip->getFromIndex($i);
        if ($content === false) {
            $result['skipped'][] = $entry . ' (gagal dibaca dari ZIP)';
            continue;
        }

        file_put_contents($targetPath, $content);
        $extracted++;
    }

    $zip->close();
    $result['ok'] = true;
    $result['extracted'] = $extracted;
    return $result;
}

/**
 * Sisipkan tag <script> SDK sebelum </body> di index.html hasil upload,
 * jika belum ada. Ini jaring pengaman agar landing page tetap tersambung
 * ke sistem walau pembuat HTML lupa menambahkannya secara manual.
 */
function inject_sdk_script($indexHtmlPath) {
    if (!file_exists($indexHtmlPath)) return false;

    $html = file_get_contents($indexHtmlPath);
    if ($html === false) return false;

    if (strpos($html, 'rahasiaemas-sdk.js') !== false) {
        return true; // sudah ada, tidak perlu diubah
    }

    $scriptTag = '<script src="/assets/rahasiaemas-sdk.js" defer></script>' . "\n</body>";

    if (stripos($html, '</body>') !== false) {
        $html = preg_replace('/<\/body>/i', $scriptTag, $html, 1);
    } else {
        $html .= "\n" . $scriptTag . "\n</html>";
    }

    file_put_contents($indexHtmlPath, $html);
    return true;
}

/** Ambil data event dari database berdasarkan slug. Return null jika tidak ada. */
function get_event_by_slug($slug) {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM events WHERE slug = ?');
    $stmt->execute([$slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** Perbarui detail acara (hari, waktu, lokasi, pembicara, kapasitas) milik sebuah event. */
function update_event_settings($slug, array $data) {
    $settings = [
        'event_day' => trim($data['event_day'] ?? ''),
        'event_time' => trim($data['event_time'] ?? ''),
        'event_location' => trim($data['event_location'] ?? ''),
        'event_speaker' => trim($data['event_speaker'] ?? ''),
        'event_capacity' => trim($data['event_capacity'] ?? ''),
    ];

    foreach ($settings as $value) {
        if ($value === '') {
            throw new InvalidArgumentException('Semua detail acara wajib diisi.');
        }
    }

    $pdo = get_db();
    $stmt = $pdo->prepare('
        UPDATE events
        SET event_day = ?, event_time = ?, event_location = ?, event_speaker = ?, event_capacity = ?
        WHERE slug = ?
    ');
    $stmt->execute([
        $settings['event_day'],
        $settings['event_time'],
        $settings['event_location'],
        $settings['event_speaker'],
        $settings['event_capacity'],
        $slug,
    ]);

    return $settings;
}

/** Ambil daftar hadiah per peringkat untuk sebuah event, terurut dari peringkat 1. */
function get_event_rewards($slug) {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT rank, reward_text FROM event_rewards WHERE event_slug = ? ORDER BY rank ASC');
    $stmt->execute([$slug]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Simpan gambar hadiah challenge untuk sebuah event.
 * Memvalidasi ekstensi DAN memastikan file benar-benar gambar (getimagesize),
 * lalu menyimpannya sebagai REWARD_IMAGES_DIR/{slug}.{ext} (menimpa versi lama).
 *
 * @return string|null Path URL gambar (REWARD_IMAGES_URL_BASE/{slug}.{ext}), atau null jika gagal.
 */
function save_reward_image($tmpPath, $originalName, $eventSlug) {
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_REWARD_IMAGE_EXT, true)) {
        return null;
    }

    if (@getimagesize($tmpPath) === false) {
        return null;
    }

    if (!is_dir(REWARD_IMAGES_DIR)) {
        mkdir(REWARD_IMAGES_DIR, 0755, true);
    }

    // Hapus file lama untuk slug ini walau ekstensinya berbeda.
    foreach (ALLOWED_REWARD_IMAGE_EXT as $oldExt) {
        $oldPath = REWARD_IMAGES_DIR . '/' . $eventSlug . '.' . $oldExt;
        if (is_file($oldPath)) {
            unlink($oldPath);
        }
    }

    $destPath = REWARD_IMAGES_DIR . '/' . $eventSlug . '.' . $ext;
    if (!move_uploaded_file($tmpPath, $destPath)) {
        return null;
    }

    return REWARD_IMAGES_URL_BASE . '/' . $eventSlug . '.' . $ext;
}

/** Hapus file gambar hadiah fisik milik sebuah event, jika ada. */
function delete_reward_image($imagePath) {
    if (!$imagePath) return;
    $fileName = basename($imagePath);
    $fullPath = REWARD_IMAGES_DIR . '/' . $fileName;
    if (is_file($fullPath)) {
        unlink($fullPath);
    }
}
