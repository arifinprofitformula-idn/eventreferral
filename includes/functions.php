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

    if (strpos($html, 'rahasiaemas-sdk.js') !== false || strpos($html, 'event-sdk.js') !== false) {
        $replaceCount = 0;
        $normalizedHtml = preg_replace(
            '/(<script\b[^>]*\bsrc=["\'])(?:\/?assets\/)?(?:rahasiaemas-sdk|event-sdk)\.js(["\'][^>]*><\/script>)/i',
            '$1/assets/event-sdk.js$2',
            $html,
            -1,
            $replaceCount
        );
        if ($replaceCount > 0 && $normalizedHtml !== null) {
            file_put_contents($indexHtmlPath, $normalizedHtml);
            return true;
        }
    }

    $scriptTag = '<script src="/assets/event-sdk.js" defer></script>' . "\n</body>";

    if (stripos($html, '</body>') !== false) {
        $html = preg_replace('/<\/body>/i', $scriptTag, $html, 1);
    } else {
        $html .= "\n" . $scriptTag . "\n</html>";
    }

    file_put_contents($indexHtmlPath, $html);
    return true;
}

/** Upsert satu baris tabel `events`, dipakai bersama oleh alur upload ZIP dan alur landing page AI. */
function upsert_event_record(PDO $pdo, int $brandId, string $slug, array $cfg): void {
    $stmt = $pdo->prepare('
        INSERT INTO events (brand_id, slug, name, status, whatsapp_default, event_day, event_time, event_location, event_speaker, event_capacity)
        VALUES (?, ?, ?, "active", ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            name = VALUES(name), status = "active", whatsapp_default = VALUES(whatsapp_default),
            event_day = VALUES(event_day), event_time = VALUES(event_time),
            event_location = VALUES(event_location), event_speaker = VALUES(event_speaker),
            event_capacity = VALUES(event_capacity)
    ');
    $stmt->execute([
        $brandId,
        $slug,
        clean($cfg['name']),
        normalize_whatsapp(clean($cfg['whatsapp'] ?? '')),
        clean($cfg['event_day'] ?? ''),
        clean($cfg['event_time'] ?? ''),
        clean($cfg['event_location'] ?? ''),
        clean($cfg['event_speaker'] ?? ''),
        clean($cfg['event_capacity'] ?? ''),
    ]);
}

/** Ubah warna hex jadi versi lebih terang (blend ke putih) untuk variabel CSS "*-soft". */
function lighten_hex_color(string $hex, float $ratio): string {
    if (!preg_match('/^#([0-9a-fA-F]{6})$/', $hex, $m)) {
        return '#F4D27A';
    }
    $ratio = max(0.0, min(1.0, $ratio));
    $rgb = str_split($m[1], 2);
    $blended = array_map(static function ($channel) use ($ratio) {
        $value = hexdec($channel);
        $value = (int)round($value + (255 - $value) * $ratio);
        return str_pad(dechex(max(0, min(255, $value))), 2, '0', STR_PAD_LEFT);
    }, $rgb);
    return '#' . implode('', $blended);
}

/** Konversi **bold** markdown jadi <strong>, dengan sisa teks di-escape HTML terlebih dahulu. */
function convert_landing_markdown_bold(string $text): string {
    $escaped = htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8');
    return preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped);
}

/** Render deskripsi AI (paragraf dipisah newline ganda) jadi tag <p> yang aman dari HTML/script injection. */
function render_landing_description(string $description): string {
    $paragraphs = preg_split('/\n{2,}/', trim($description)) ?: [];
    $html = '';
    foreach ($paragraphs as $paragraph) {
        $paragraph = trim($paragraph);
        if ($paragraph === '') {
            continue;
        }
        $html .= '<p style="margin-bottom:16px;">' . nl2br(htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8')) . '</p>' . "\n";
    }
    return $html;
}

/**
 * Render blok berulang (list) yang ditandai <!--LOOP:key--> ... <!--/LOOP:key--> di template,
 * satu kali per item di $items, dengan token {{ITEM.field}} diganti nilai per-item (di-escape HTML).
 * Item berupa array asosiatif field => value (mis. ['title'=>..., 'desc'=>...]) atau, untuk list
 * string sederhana (chip/benefit), array ['value' => $string].
 * Jika $items kosong, seluruh blok (termasuk marker) dihapus.
 */
function render_landing_loop(string $html, string $loopKey, array $items): string {
    $pattern = '/<!--LOOP:' . preg_quote($loopKey, '/') . '-->(.*?)<!--\/LOOP:' . preg_quote($loopKey, '/') . '-->/s';

    return preg_replace_callback($pattern, static function ($matches) use ($items) {
        $itemTemplate = $matches[1];
        $rendered = '';
        foreach ($items as $item) {
            $itemHtml = $itemTemplate;
            foreach ($item as $field => $value) {
                $itemHtml = str_replace(
                    '{{ITEM.' . $field . '}}',
                    htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'),
                    $itemHtml
                );
            }
            $rendered .= $itemHtml;
        }
        return $rendered;
    }, $html) ?? $html;
}

/** Bungkus list string polos (mis. chip/benefit) jadi array asosiatif ['value' => $string] untuk render_landing_loop(). */
function wrap_landing_string_list(array $strings): array {
    return array_map(static fn ($s) => ['value' => (string)$s], $strings);
}

/**
 * Susun ulang section landing page sesuai urutan/pilihan AI ("layout"), supaya setiap event bisa
 * punya kombinasi & urutan section yang berbeda-beda — bukan struktur kaku yang selalu sama.
 *
 * Template menandai tiap section yang boleh dipilih/diurutkan AI dengan:
 *   <!--BLOCK:key:start--> ... <!--BLOCK:key:end-->
 * Semua blok diekstrak dari $html, lalu disusun ulang sesuai urutan $layout (blok yang tidak
 * disebut di $layout tidak ikut ditampilkan). Section yang TIDAK dibungkus BLOCK marker (hero,
 * header, footer) tetap di posisi aslinya — tidak pernah ikut diacak.
 */
function render_landing_blocks(string $html, array $layout): string {
    $blocks = [];
    $isFirst = true;

    $html = preg_replace_callback(
        '/<!--BLOCK:([a-z_]+):start-->(.*?)<!--BLOCK:\1:end-->/s',
        static function ($matches) use (&$blocks, &$isFirst) {
            $blocks[$matches[1]] = $matches[2];
            if ($isFirst) {
                $isFirst = false;
                return '{{BLOCKS_HERE}}';
            }
            return '';
        },
        $html
    ) ?? $html;

    $assembled = '';
    foreach ($layout as $key) {
        if (isset($blocks[$key])) {
            $assembled .= $blocks[$key];
        }
    }

    return str_replace('{{BLOCKS_HERE}}', $assembled, $html);
}

/** Hapus atau simpan blok section opsional yang ditandai <!--SECTION:key:start--> ... <!--SECTION:key:end--> di template. */
function strip_landing_template_section(string $html, string $sectionKey, bool $keep): string {
    $pattern = '/<!--SECTION:' . preg_quote($sectionKey, '/') . ':start-->(.*?)<!--SECTION:' . preg_quote($sectionKey, '/') . ':end-->/s';

    return preg_replace_callback($pattern, static function ($matches) use ($keep) {
        return $keep ? $matches[1] : '';
    }, $html) ?? $html;
}

/** Ambil satu field string dari $blocks[$blockKey][$field], escaped HTML. Default string kosong kalau tidak ada. */
function landing_block_field(array $blocks, string $blockKey, string $field): string {
    $value = $blocks[$blockKey][$field] ?? '';
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** Ambil satu field string dari $blocks[$blockKey][$subKey][$field] (nested, mis. formula_steps.plus.title), escaped HTML. */
function landing_nested_block_field(array $blocks, string $blockKey, string $subKey, string $field): string {
    $value = $blocks[$blockKey][$subKey][$field] ?? '';
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Render HTML landing page dari template AI + konten yang sudah diisi (hasil generate_ai_landing_page()).
 * Dipakai untuk preview DAN untuk publish, supaya hasil publish identik dengan yang di-preview admin.
 *
 * AI bebas memilih KOMBINASI dan URUTAN section lewat $filled['layout'] (lihat render_landing_blocks()) —
 * bukan struktur section yang selalu sama persis di setiap event. Hanya hero, form pendaftaran (dipaksa
 * ikut lewat 'registration_form'), dan footer yang jaminan tampil, supaya alur sistem referral tetap utuh.
 *
 * @param array $filled Hasil generate_ai_landing_page(): template_key, accent_color, hero fields, layout, blocks.
 * @param array $eventBrief Data brief event: name, dan field detail acara lainnya (dipakai untuk {{EVENT_NAME}}).
 */
function render_landing_template(array $filled, array $brand, array $eventBrief): string {
    $templates = get_ai_landing_templates();
    $templateKey = $filled['template_key'] ?? '';
    if (!isset($templates[$templateKey])) {
        throw new RuntimeException('Template landing page tidak ditemukan.');
    }

    $templateDir = __DIR__ . '/event_templates/' . $templateKey;
    $html = file_get_contents($templateDir . '/index.html');
    if ($html === false) {
        throw new RuntimeException('Gagal membaca file template landing page.');
    }

    $templateMeta = $templates[$templateKey];
    $accentColor = (string)($filled['accent_color'] ?? '');
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accentColor)) {
        $accentColor = $templateMeta['default_accent'] ?? '#C9A84C';
    }
    $accentSoft = lighten_hex_color($accentColor, 0.4);

    $brandName = $brand['name'] ?? ($brand['slug'] ?? 'rahasiaemas.id');
    $logoUrl = !empty($brand['logo_path']) ? $brand['logo_path'] : '/assets/logo.png';

    $blocks = is_array($filled['blocks'] ?? null) ? $filled['blocks'] : [];
    $layout = is_array($filled['layout'] ?? null) ? $filled['layout'] : [];

    // Susun ulang section sesuai pilihan/urutan AI SEBELUM loop & token diproses.
    $html = render_landing_blocks($html, $layout);

    // Blok berulang (list) per section — aman dipanggil walau blok tsb tidak terpilih (tidak ada match, no-op).
    $html = render_landing_loop($html, 'stats', $blocks['stat_grid']['stats'] ?? []);
    $html = render_landing_loop($html, 'formula_steps', $blocks['formula_steps']['steps'] ?? []);
    $html = render_landing_loop(
        $html,
        'formula_plus_points',
        wrap_landing_string_list($blocks['formula_steps']['plus']['points'] ?? [])
    );
    $html = render_landing_loop($html, 'why_cards', $blocks['why_now']['cards'] ?? []);
    $html = render_landing_loop($html, 'audience_cards', $blocks['audience_cards']['cards'] ?? []);
    $html = render_landing_loop(
        $html,
        'audience_chips',
        wrap_landing_string_list($blocks['audience_chips']['chips'] ?? [])
    );
    $html = render_landing_loop($html, 'roadmap', $blocks['roadmap']['steps'] ?? []);
    $html = render_landing_loop($html, 'benefits', wrap_landing_string_list($blocks['benefits']['items'] ?? []));
    $html = render_landing_loop($html, 'faq', $blocks['faq']['items'] ?? []);

    $showFormulaPlus = trim((string)($blocks['formula_steps']['plus']['title'] ?? '')) !== '';
    $html = strip_landing_template_section($html, 'formula_plus', $showFormulaPlus);

    $replacements = [
        '{{EVENT_NAME}}' => htmlspecialchars((string)($eventBrief['name'] ?? ''), ENT_QUOTES, 'UTF-8'),
        '{{BRAND_NAME}}' => htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8'),
        '{{BRAND_LOGO_URL}}' => htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'),
        '{{ACCENT_COLOR}}' => $accentColor,
        '{{ACCENT_COLOR_SOFT}}' => $accentSoft,
        '{{EYEBROW}}' => htmlspecialchars((string)($filled['eyebrow'] ?? ''), ENT_QUOTES, 'UTF-8'),
        '{{HEADLINE}}' => convert_landing_markdown_bold((string)($filled['headline'] ?? '')),
        '{{SUBHEADLINE}}' => htmlspecialchars((string)($filled['subheadline'] ?? ''), ENT_QUOTES, 'UTF-8'),
        '{{DESCRIPTION}}' => render_landing_description((string)($filled['description'] ?? '')),
        '{{CTA_TEXT}}' => htmlspecialchars((string)($filled['cta_text'] ?? ''), ENT_QUOTES, 'UTF-8'),
        '{{SPEAKER_NAME}}' => htmlspecialchars((string)($eventBrief['event_speaker'] ?? ''), ENT_QUOTES, 'UTF-8'),

        '{{STAT_TITLE}}' => landing_block_field($blocks, 'stat_grid', 'title'),
        '{{STAT_LEDE}}' => landing_block_field($blocks, 'stat_grid', 'lede'),
        '{{STAT_QUOTE}}' => landing_block_field($blocks, 'stat_grid', 'quote'),

        '{{FORMULA_TITLE}}' => landing_block_field($blocks, 'formula_steps', 'title'),
        '{{FORMULA_LEDE}}' => landing_block_field($blocks, 'formula_steps', 'lede'),
        '{{FORMULA_PLUS_BADGE}}' => landing_nested_block_field($blocks, 'formula_steps', 'plus', 'badge'),
        '{{FORMULA_PLUS_TITLE}}' => landing_nested_block_field($blocks, 'formula_steps', 'plus', 'title'),
        '{{FORMULA_PLUS_DESC}}' => landing_nested_block_field($blocks, 'formula_steps', 'plus', 'desc'),

        '{{WHY_NOW_TITLE}}' => landing_block_field($blocks, 'why_now', 'title'),
        '{{WHY_NOW_LEDE}}' => landing_block_field($blocks, 'why_now', 'lede'),

        '{{AUDIENCE_CARDS_TITLE}}' => landing_block_field($blocks, 'audience_cards', 'title'),
        '{{AUDIENCE_CHIPS_TITLE}}' => landing_block_field($blocks, 'audience_chips', 'title'),
        '{{AUDIENCE_CHIPS_FOOTNOTE}}' => landing_block_field($blocks, 'audience_chips', 'footnote'),

        '{{ROADMAP_TITLE}}' => landing_block_field($blocks, 'roadmap', 'title'),
        '{{ROADMAP_LEDE}}' => landing_block_field($blocks, 'roadmap', 'lede'),

        '{{SPEAKER_ROLE}}' => landing_block_field($blocks, 'speaker', 'role'),
        '{{SPEAKER_BIO}}' => landing_block_field($blocks, 'speaker', 'bio'),

        '{{BENEFITS_TITLE}}' => landing_block_field($blocks, 'benefits', 'title'),

        '{{TESTIMONIAL_QUOTE}}' => landing_block_field($blocks, 'testimonial', 'quote'),
        '{{TESTIMONIAL_NAME}}' => landing_block_field($blocks, 'testimonial', 'name'),

        '{{FAQ_TITLE}}' => landing_block_field($blocks, 'faq', 'title'),
    ];

    return strtr($html, $replacements);
}

/** Ambil data event dari database berdasarkan slug. Return null jika tidak ada. */
function get_event_by_slug($slug) {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM events WHERE slug = ?');
    $stmt->execute([$slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** Perbarui detail acara (hari, waktu, lokasi, pembicara, kapasitas, tanggal) milik sebuah event. */
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

    // event_date bersifat OPSIONAL — tidak mengubah validasi field lama di atas.
    // Dipakai khusus untuk sorting kronologis di /kalender/index.php.
    $eventDateRaw = trim($data['event_date'] ?? '');
    $eventDate = null;
    if ($eventDateRaw !== '') {
        $parsed = DateTime::createFromFormat('Y-m-d', $eventDateRaw);
        if (!$parsed || $parsed->format('Y-m-d') !== $eventDateRaw) {
            throw new InvalidArgumentException('Format tanggal acara tidak valid.');
        }
        $eventDate = $eventDateRaw;
    }

    $pdo = get_db();
    $stmt = $pdo->prepare('
        UPDATE events
        SET event_day = ?, event_time = ?, event_location = ?, event_speaker = ?, event_capacity = ?, event_date = ?
        WHERE slug = ?
    ');
    $stmt->execute([
        $settings['event_day'],
        $settings['event_time'],
        $settings['event_location'],
        $settings['event_speaker'],
        $settings['event_capacity'],
        $eventDate,
        $slug,
    ]);

    $settings['event_date'] = $eventDate;
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

/**
 * Simpan flyer/poster acara untuk dibagikan pengundang di halaman buat-link.php.
 * Memvalidasi ekstensi DAN memastikan file benar-benar gambar (getimagesize),
 * lalu menyimpannya sebagai EVENT_FLYERS_DIR/{slug}.{ext} (menimpa versi lama).
 *
 * @return string|null Path URL flyer (EVENT_FLYERS_URL_BASE/{slug}.{ext}), atau null jika gagal.
 */
function save_event_flyer($tmpPath, $originalName, $eventSlug) {
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EVENT_FLYER_EXT, true)) {
        return null;
    }

    if (@getimagesize($tmpPath) === false) {
        return null;
    }

    if (!is_dir(EVENT_FLYERS_DIR)) {
        mkdir(EVENT_FLYERS_DIR, 0755, true);
    }

    // Hapus file lama untuk slug ini walau ekstensinya berbeda.
    foreach (ALLOWED_EVENT_FLYER_EXT as $oldExt) {
        $oldPath = EVENT_FLYERS_DIR . '/' . $eventSlug . '.' . $oldExt;
        if (is_file($oldPath)) {
            unlink($oldPath);
        }
    }

    $destPath = EVENT_FLYERS_DIR . '/' . $eventSlug . '.' . $ext;
    if (!move_uploaded_file($tmpPath, $destPath)) {
        return null;
    }

    return EVENT_FLYERS_URL_BASE . '/' . $eventSlug . '.' . $ext;
}

/** Hapus file flyer fisik milik sebuah event, jika ada. */
function delete_event_flyer($flyerPath) {
    if (!$flyerPath) return;
    $fileName = basename($flyerPath);
    $fullPath = EVENT_FLYERS_DIR . '/' . $fileName;
    if (is_file($fullPath)) {
        unlink($fullPath);
    }
}

/**
 * Simpan logo brand yang diunggah lewat admin/setup-brand.php.
 * - Validasi ekstensi (png/jpg/jpeg/webp/svg) dan ukuran maks (MAX_LOGO_SIZE).
 * - File raster divalidasi dengan getimagesize() (memastikan benar-benar gambar).
 * - File SVG divalidasi sebagai XML valid dan ditolak jika mengandung
 *   <script>, event handler (on*=), atau javascript: — mencegah stored XSS
 *   kalau file SVG-nya suatu saat dibuka langsung lewat URL (bukan lewat <img>).
 *
 * @return string|null Path URL logo (BRAND_LOGOS_URL_BASE/{slug}/logo.{ext}), atau null jika gagal.
 */
function safe_upload_logo($tmpPath, $originalName, $brandSlug) {
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_LOGO_EXT, true)) {
        return null;
    }

    if (!is_file($tmpPath) || filesize($tmpPath) > MAX_LOGO_SIZE) {
        return null;
    }

    if ($ext === 'svg') {
        $content = file_get_contents($tmpPath);
        if ($content === false) {
            return null;
        }
        libxml_use_internal_errors(true);
        $isValidXml = simplexml_load_string($content) !== false;
        libxml_clear_errors();
        if (!$isValidXml) {
            return null;
        }
        if (preg_match('/<script|on\w+\s*=|javascript:/i', $content)) {
            return null;
        }
    } elseif (@getimagesize($tmpPath) === false) {
        return null;
    }

    $destDir = BRAND_LOGOS_DIR . '/' . $brandSlug;
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }

    // Hapus logo lama untuk brand ini walau ekstensinya berbeda.
    foreach (ALLOWED_LOGO_EXT as $oldExt) {
        $oldPath = $destDir . '/logo.' . $oldExt;
        if (is_file($oldPath)) {
            unlink($oldPath);
        }
    }

    $destPath = $destDir . '/logo.' . $ext;
    if (!move_uploaded_file($tmpPath, $destPath)) {
        return null;
    }

    return BRAND_LOGOS_URL_BASE . '/' . $brandSlug . '/logo.' . $ext;
}
